<?php
/**
 * OrinHeberge — Service d'authentification (v2)
 * Gère : login local, register, OAuth Discord/Google, Pterodactyl, emails
 */

class AuthService {
    private $pdo;
    private $pterodactylEnabled = false;
    private $pterodactylUrl;
    private $pterodactylHeaders;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Initialisation Pterodactyl si disponible
        if (!empty($GLOBALS['panel_url']) && !empty($GLOBALS['api_key_admin'])) {
            $this->pterodactylEnabled = true;
            $this->pterodactylUrl     = rtrim($GLOBALS['panel_url'], '/');
            $this->pterodactylHeaders = $GLOBALS['headers_admin'] ?? [];
        }
    }

    // ============================================
    // 🔐 AUTHENTIFICATION LOCALE
    // ============================================

    /**
     * Login avec email + mot de passe
     */
    public function login($email, $password, $remember = false) {
        if (!$email || !$password) {
            return ['success' => false, 'error' => 'missing_fields'];
        }

        // Protection anti-bruteforce
        if ($this->isRateLimited($email)) {
            return ['success' => false, 'error' => 'too_many_attempts', 'retry_after' => 900];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'] ?? '')) {
            $this->recordFailedAttempt($email);
            return ['success' => false, 'error' => 'invalid_credentials'];
        }

        if (($user['oauth_provider'] ?? 'local') !== 'local') {
            return [
                'success' => false, 
                'error' => 'oauth_account', 
                'provider' => $user['oauth_provider'],
                'hint' => "Utilisez la connexion " . ucfirst($user['oauth_provider'])
            ];
        }

        // Login réussi → reset des tentatives
        $this->resetFailedAttempts($email);
        
        $this->startSession($user, $remember);
        $this->updateLastLogin($user['id']);

        return ['success' => true, 'user' => $this->sanitizeUser($user)];
    }

    /**
     * Inscription complète avec Pterodactyl + email
     */
    public function register($firstname, $lastname, $email, $password, $sendWelcomeEmail = true) {
        // ── Validation ──
        if (!$firstname || !$lastname || !$email || !$password) {
            return ['success' => false, 'error' => 'missing_fields'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'invalid_email'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'password_too_short'];
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return ['success' => false, 'error' => 'password_too_weak'];
        }

        // Vérifier email déjà utilisé
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'email_exists'];
        }

        $pseudo = $this->generateUniquePseudo($firstname);

        try {
            $this->pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("
                INSERT INTO users (firstname, lastname, email, password, pseudo, oauth_provider, created_at)
                VALUES (?, ?, ?, ?, ?, 'local', NOW())
            ");
            $stmt->execute([$firstname, $lastname, $email, $hash, $pseudo]);
            $userId = (int)$this->pdo->lastInsertId();

            // ── Création compte Pterodactyl ──
            $panelCreated = false;
            if ($this->pterodactylEnabled) {
                $panelCreated = $this->createPterodactylUser($userId, $email, $pseudo, $firstname, $lastname, $password);
            }

            $this->pdo->commit();

            // ── Récupérer user complet ──
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // ── Login auto ──
            $this->startSession($user);
            $this->updateLastLogin($user['id']);

            // ── Email de bienvenue ──
            if ($sendWelcomeEmail) {
                $this->sendWelcomeEmail($user, $password, $panelCreated);
            }

            return [
                'success'       => true,
                'user'          => $this->sanitizeUser($user),
                'panel_created' => $panelCreated,
                'plain_password' => $password
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[Auth] Erreur register : ' . $e->getMessage());
            
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                return ['success' => false, 'error' => 'email_exists'];
            }
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    /**
     * Déconnexion complète
     */
    public function logout() {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ============================================
    // 🌐 OAUTH - DISCORD & GOOGLE
    // ============================================

    /**
     * Login ou register via OAuth
     */
    public function loginWithOAuth($provider, $oauthData, $sendWelcomeEmail = true) {
        $providerId = (string)($oauthData['id'] ?? '');
        $email      = $oauthData['email'] ?? null;
        $firstname  = $oauthData['firstname'] ?? $oauthData['username'] ?? 'User';
        $lastname   = $oauthData['lastname'] ?? '';
        $avatar     = $oauthData['avatar'] ?? null;
        $rawData    = $oauthData['raw'] ?? [];

        if (empty($providerId)) {
            return ['success' => false, 'error' => 'invalid_oauth_data'];
        }

        // 1. Chercher par provider+id
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ?");
        $stmt->execute([$provider, $providerId]);
        $user = $stmt->fetch();

        if ($user) {
            // Mettre à jour données OAuth
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET oauth_avatar = ?, oauth_data = ?, oauth_email = ?,
                    firstname = COALESCE(NULLIF(?, ''), firstname),
                    avatar = COALESCE(?, avatar)
                WHERE id = ?
            ");
            $stmt->execute([$avatar, json_encode($rawData), $email, $firstname, $avatar, $user['id']]);

            $this->startSession($user);
            $this->updateLastLogin($user['id']);

            return ['success' => true, 'user' => $this->sanitizeUser($user), 'is_new' => false];
        }

        // 2. Chercher par email (compte local existant) → liaison auto
        if ($email) {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            if ($existingUser && ($existingUser['oauth_provider'] ?? 'local') === 'local') {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET oauth_provider = ?, oauth_id = ?, oauth_email = ?, oauth_avatar = ?, oauth_data = ?
                    WHERE id = ?
                ");
                $stmt->execute([$provider, $providerId, $email, $avatar, json_encode($rawData), $existingUser['id']]);

                $this->startSession($existingUser);
                $this->updateLastLogin($existingUser['id']);

                return ['success' => true, 'user' => $this->sanitizeUser($existingUser), 'is_new' => false, 'linked' => true];
            }
        }

        // 3. Créer nouveau compte OAuth
        $pseudo = $this->generateUniquePseudo($firstname, $oauthData['username'] ?? null);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    firstname, lastname, email, pseudo, avatar,
                    oauth_provider, oauth_id, oauth_email, oauth_avatar, oauth_data,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $firstname, $lastname, $email, $pseudo, $avatar,
                $provider, $providerId, $email, $avatar, json_encode($rawData)
            ]);

            $userId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $this->startSession($user);
            $this->updateLastLogin($user['id']);

            // Email de bienvenue OAuth
            if ($sendWelcomeEmail && $email) {
                $this->sendOAuthWelcomeEmail($user, $provider);
            }

            return ['success' => true, 'user' => $this->sanitizeUser($user), 'is_new' => true];

        } catch (Exception $e) {
            error_log("[Auth] Erreur OAuth $provider : " . $e->getMessage());
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    // ============================================
    // 🦅 PTERODACTYL
    // ============================================

    /**
     * Crée un utilisateur sur le panel Pterodactyl
     */
    private function createPterodactylUser($userId, $email, $pseudo, $firstname, $lastname, $password) {
        if (!$this->pterodactylEnabled || !function_exists('pterodactylApi')) {
            return false;
        }

        try {
            // Vérifier si existe déjà
            $search = pterodactylApi($this->pterodactylUrl, $this->pterodactylHeaders, 'users?filter[email]=' . urlencode($email));
            $panelUid = $search['data'][0]['attributes']['id'] ?? null;

            if (!$panelUid) {
                $created = pterodactylApi($this->pterodactylUrl, $this->pterodactylHeaders, 'users', [
                    'email'      => $email,
                    'username'   => $pseudo,
                    'first_name' => $firstname,
                    'last_name'  => $lastname,
                    'password'   => $password,
                ]);
                $panelUid = $created['attributes']['id'] ?? null;
            }

            if ($panelUid) {
                // Stocker le mot de passe en clair pour affichage/email UNIQUEMENT
                // (à supprimer après premier affichage dans une vraie implémentation)
                $this->pdo->prepare('UPDATE users SET panel_password = ? WHERE id = ?')->execute([$password, $userId]);
                return true;
            }
        } catch (Throwable $e) {
            error_log('[Auth] Pterodactyl creation error: ' . $e->getMessage());
        }

        return false;
    }

    // ============================================
    // 📧 EMAILS
    // ============================================

    /**
     * Email de bienvenue pour inscription classique
     */
    private function sendWelcomeEmail($user, $plainPassword, $panelCreated) {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';

            $panelSection = '';
            if ($panelCreated && $this->pterodactylEnabled) {
                $panelSection = '
                    <h3 style="color:#38bdf8;margin-top:24px;">🦅 Panel Pterodactyl</h3>
                    <div class="box">
                        <div class="row"><span class="label">URL</span><span class="val mono">' . htmlspecialchars($this->pterodactylUrl) . '</span></div>
                        <div class="row"><span class="label">Identifiant</span><span class="val mono">' . htmlspecialchars($user['email']) . '</span></div>
                        <div class="row"><span class="label">Mot de passe</span><span class="val mono">' . htmlspecialchars($plainPassword) . '</span></div>
                    </div>
                    <p style="color:#f59e0b;font-size:13px;">⚠️ Notez ces identifiants — ils ne seront plus affichés.</p>
                ';
            } else {
                $panelSection = '<p style="font-size:13px;color:#6b7280;">Le compte panel sera créé automatiquement lors de votre première commande.</p>';
            }

            $body = '
                <p>Bonjour <strong>' . htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . '</strong>,</p>
                <p>Votre compte OrinHeberge a été créé avec succès !</p>
                
                <h3 style="color:#38bdf8;">🔐 Identifiants OrinHeberge</h3>
                <div class="box">
                    <div class="row"><span class="label">Pseudo</span><span class="val mono">' . htmlspecialchars($user['pseudo']) . '</span></div>
                    <div class="row"><span class="label">Email</span><span class="val mono">' . htmlspecialchars($user['email']) . '</span></div>
                    <div class="row"><span class="label">Mot de passe</span><span class="val mono">' . htmlspecialchars($plainPassword) . '</span></div>
                </div>
                ' . $panelSection . '
                <p style="margin-top:24px;">
                    <a href="https://heberge.orinstone.deepstone.fr/login/" class="btn">Se connecter →</a>
                </p>
                <p style="font-size:12px;color:#4b5563;margin-top:24px;">
                    🔒 Pour votre sécurité, changez votre mot de passe après votre première connexion.
                </p>
            ';

            if (function_exists('send_smtp_mail') && function_exists('email_layout')) {
                send_smtp_mail(
                    $user['email'], 
                    '🎉 Bienvenue sur OrinHeberge — vos identifiants', 
                    email_layout('Bienvenue !', $body)
                );
            }
        } catch (Throwable $e) {
            error_log('[Auth] Email error: ' . $e->getMessage());
            // Ne pas faire échouer l'inscription si l'email échoue
        }
    }

    /**
     * Email de bienvenue OAuth (pas de mot de passe)
     */
    private function sendOAuthWelcomeEmail($user, $provider) {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';

            $providerName = ucfirst($provider);
            $body = '
                <p>Bonjour <strong>' . htmlspecialchars($user['firstname']) . '</strong>,</p>
                <p>Votre compte OrinHeberge a été créé via <strong>' . $providerName . '</strong> !</p>
                <div class="box">
                    <div class="row"><span class="label">Pseudo</span><span class="val mono">' . htmlspecialchars($user['pseudo']) . '</span></div>
                    <div class="row"><span class="label">Email</span><span class="val mono">' . htmlspecialchars($user['email']) . '</span></div>
                    <div class="row"><span class="label">Provider</span><span class="val">' . $providerName . '</span></div>
                </div>
                <p>Vous pouvez désormais vous connecter en un clic avec votre compte ' . $providerName . '.</p>
                <p style="margin-top:24px;">
                    <a href="https://heberge.orinstone.deepstone.fr/login/" class="btn">Accéder à mon compte →</a>
                </p>
            ';

            if (function_exists('send_smtp_mail') && function_exists('email_layout')) {
                send_smtp_mail(
                    $user['email'],
                    "🎉 Bienvenue sur OrinHeberge via $providerName",
                    email_layout('Bienvenue !', $body)
                );
            }
        } catch (Throwable $e) {
            error_log('[Auth] OAuth email error: ' . $e->getMessage());
        }
    }

    // ============================================
    // 🛡️ SÉCURITÉ - RATE LIMITING
    // ============================================

    private function isRateLimited($email) {
        $key = 'login_attempts_' . md5($email);
        $attempts = $_SESSION[$key] ?? [];
        $recentAttempts = array_filter($attempts, fn($t) => $t > time() - 900);
        return count($recentAttempts) >= 5; // 5 tentatives max par 15 min
    }

    private function recordFailedAttempt($email) {
        $key = 'login_attempts_' . md5($email);
        $_SESSION[$key][] = time();
    }

    private function resetFailedAttempts($email) {
        $key = 'login_attempts_' . md5($email);
        unset($_SESSION[$key]);
    }

    // ============================================
    // 🔧 UTILITAIRES
    // ============================================

    private function startSession($user, $remember = false) {
        // Régénérer l'ID de session pour prévenir le fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']        = $user['id'];
        $_SESSION['username']       = $user['pseudo'] ?? $user['firstname'];
        $_SESSION['name']           = $user['firstname'];
        $_SESSION['avatar']         = $user['avatar'] ?? $user['oauth_avatar'] ?? null;
        $_SESSION['is_admin']       = (bool)($user['is_admin'] ?? false);
        $_SESSION['oauth_provider'] = $user['oauth_provider'] ?? 'local';
        $_SESSION['login_time']     = time();
        $_SESSION['ip']             = $_SERVER['REMOTE_ADDR'] ?? '';

        // Cookie "remember me" optionnel (30 jours)
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 3600);
            
            setcookie('remember_token', $token, $expires, '/', '', true, true);
            
            // Stocker en BDD (table remember_tokens à créer)
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO remember_tokens (user_id, token, expires_at)
                    VALUES (?, ?, FROM_UNIXTIME(?))
                ");
                $stmt->execute([$user['id'], hash('sha256', $token), $expires]);
            } catch (Exception $e) {
                // Table peut ne pas exister, silencieux
            }
        }
    }

    private function updateLastLogin($userId) {
        try {
            $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$userId]);
        } catch (Exception $e) {
            error_log('[Auth] updateLastLogin: ' . $e->getMessage());
        }
    }

    private function sanitizeUser($user) {
        return [
            'id'        => $user['id'],
            'firstname' => $user['firstname'],
            'lastname'  => $user['lastname'],
            'email'     => $user['email'],
            'pseudo'    => $user['pseudo'],
            'avatar'    => $user['avatar'] ?? $user['oauth_avatar'] ?? null,
            'is_admin'  => (bool)($user['is_admin'] ?? false),
            'provider'  => $user['oauth_provider'] ?? 'local',
        ];
    }

    private function generateUniquePseudo($firstname, $preferred = null) {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $preferred ?? $firstname));
        if (!$base) $base = 'user';
        
        $pseudo = $base;
        $i = 1;
        while ($this->pdo->query("SELECT id FROM users WHERE pseudo = " . $this->pdo->quote($pseudo))->fetch()) {
            $pseudo = $base . $i++;
            if ($i > 999) {
                $pseudo = $base . '_' . bin2hex(random_bytes(2));
                break;
            }
        }
        return $pseudo;
    }

    public function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) return null;

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        return $user ? $this->sanitizeUser($user) : null;
    }

    public function isAdmin() {
        return !empty($_SESSION['is_admin']);
    }

    /**
     * Lier manuellement un provider OAuth à un compte existant
     */
    public function linkOAuthProvider($userId, $provider, $oauthData) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET oauth_provider = ?, oauth_id = ?, oauth_email = ?, oauth_avatar = ?, oauth_data = ?
            WHERE id = ? AND oauth_provider = 'local'
        ");
        $stmt->execute([
            $provider,
            $oauthData['id'],
            $oauthData['email'] ?? null,
            $oauthData['avatar'] ?? null,
            json_encode($oauthData['raw'] ?? []),
            $userId
        ]);
        
        return $stmt->rowCount() > 0;
    }
}