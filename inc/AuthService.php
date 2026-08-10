<?php
/**
 * OrinHeberge — Service d'authentification (v3 multi-providers)
 * Gère : login local, register, OAuth Discord/Google (multi), Pterodactyl, emails
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

        // Vérifier si le compte a UNIQUEMENT un provider OAuth (pas de mot de passe)
        if (empty($user['password'])) {
            $providers = $this->getUserOAuthProviders($user['id']);
            if (!empty($providers)) {
                $providerNames = array_map(fn($p) => ucfirst($p['provider']), $providers);
                return [
                    'success'   => false,
                    'error'     => 'oauth_only_account',
                    'providers' => $providerNames,
                    'hint'      => "Utilisez la connexion " . implode(' ou ', $providerNames)
                ];
            }
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
                'success'        => true,
                'user'           => $this->sanitizeUser($user),
                'panel_created'  => $panelCreated,
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
 * Logger une tentative de connexion
 */
public function logLoginAttempt($userId, $status = 'success', $authMethod = 'local', $failureReason = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_login_history 
            (user_id, ip_address, user_agent, auth_method, status, failure_reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $ip, $ua, $authMethod, $status, $failureReason]);
        
        // Géolocalisation IP asynchrone (optionnel)
        $this->geolocateIpAsync($this->pdo->lastInsertId(), $ip);
        
    } catch (Exception $e) {
        error_log('[Auth] Login log error: ' . $e->getMessage());
    }
}

/**
 * Géolocaliser une IP (version simple sans API externe)
 */
private function geolocateIpAsync($logId, $ip) {
    // Version simple : on laisse NULL, à remplir plus tard avec une BDD GeoIP
    // Ou via API : file_get_contents("http://ip-api.com/json/$ip")
}

/**
 * Récupérer l'historique des connexions
 */
public function getLoginHistory($userId, $limit = 20) {
    try {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_login_history
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[Auth] Get login history error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Récupérer les préférences de notifications
 */
public function getNotificationPreferences($userId) {
    try {
        $stmt = $this->pdo->prepare("SELECT * FROM user_notification_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prefs) {
            // Créer avec valeurs par défaut
            $this->pdo->prepare("INSERT INTO user_notification_preferences (user_id) VALUES (?)")
                      ->execute([$userId]);
            return [
                'user_id' => $userId,
                'newsletter' => 1,
                'security_alerts' => 1,
                'payment_notifications' => 1,
                'support_tickets' => 1,
                'maintenance_alerts' => 1,
                'marketing_emails' => 0,
                'product_updates' => 1,
                'email_digest' => 'none'
            ];
        }
        
        return $prefs;
    } catch (Exception $e) {
        error_log('[Auth] Get notif prefs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Sauvegarder les préférences de notifications
 */
public function saveNotificationPreferences($userId, $prefs) {
    try {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_notification_preferences 
            (user_id, newsletter, security_alerts, payment_notifications, 
             support_tickets, maintenance_alerts, marketing_emails, product_updates, email_digest)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                newsletter = VALUES(newsletter),
                security_alerts = VALUES(security_alerts),
                payment_notifications = VALUES(payment_notifications),
                support_tickets = VALUES(support_tickets),
                maintenance_alerts = VALUES(maintenance_alerts),
                marketing_emails = VALUES(marketing_emails),
                product_updates = VALUES(product_updates),
                email_digest = VALUES(email_digest),
                updated_at = NOW()
        ");
        $stmt->execute([
            $userId,
            isset($prefs['newsletter']) ? 1 : 0,
            isset($prefs['security_alerts']) ? 1 : 0,
            isset($prefs['payment_notifications']) ? 1 : 0,
            isset($prefs['support_tickets']) ? 1 : 0,
            isset($prefs['maintenance_alerts']) ? 1 : 0,
            isset($prefs['marketing_emails']) ? 1 : 0,
            isset($prefs['product_updates']) ? 1 : 0,
            $prefs['email_digest'] ?? 'none'
        ]);
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log('[Auth] Save notif prefs error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

    // ============================================
    // 🔑 RÉINITIALISATION MOT DE PASSE
    // ============================================

    public function requestPasswordReset($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => true];
        }

        // Rate limiting : max 3 demandes par email par heure
        $key = 'reset_attempts_' . md5($email);
        $attempts = $_SESSION[$key] ?? [];
        $recentAttempts = array_filter($attempts, fn($t) => $t > time() - 3600);
        
        if (count($recentAttempts) >= 3) {
            return ['success' => false, 'error' => 'too_many_attempts', 'retry_after' => 3600];
        }
        
        $_SESSION[$key][] = time();

        $stmt = $this->pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => true]; // Silencieux
        }

        // Si compte OAuth-only (pas de password), bloquer le reset
        if (empty($user['password'])) {
            $providers = $this->getUserOAuthProviders($user['id']);
            if (!empty($providers)) {
                return [
                    'success'   => false,
                    'error'     => 'oauth_only_account',
                    'providers' => array_map(fn($p) => $p['provider'], $providers),
                    'hint'      => "Utilisez la connexion OAuth"
                ];
            }
        }

        // Nettoyer anciens tokens inutilisés
        $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 0")
                  ->execute([$user['id']]);

        // Générer nouveau token (1 heure)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, used, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$user['id'], $token, $expires]);
        } catch (Exception $e) {
            error_log('[Auth] Password reset insert error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'db_error'];
        }

        $this->sendPasswordResetEmail($email, $token);

        return ['success' => true, 'token' => $token];
    }

    public function resetPassword($token, $newPassword) {
        if (!$token || strlen($token) !== 64) {
            return ['success' => false, 'error' => 'invalid_token'];
        }
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'password_too_short'];
        }
        if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return ['success' => false, 'error' => 'password_too_weak'];
        }

        $stmt = $this->pdo->prepare("
            SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? LIMIT 1
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row)             return ['success' => false, 'error' => 'invalid_token'];
        if ($row['used'])      return ['success' => false, 'error' => 'token_used'];
        if (strtotime($row['expires_at']) < time()) return ['success' => false, 'error' => 'token_expired'];

        try {
            $this->pdo->beginTransaction();

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                      ->execute([$hash, $row['user_id']]);

            $this->pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
                      ->execute([$row['id']]);

            $this->pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND id != ?")
                      ->execute([$row['user_id'], $row['id']]);

            $this->pdo->commit();

            $this->sendPasswordChangedEmail($row['email']);

            return ['success' => true, 'user_id' => $row['user_id']];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[Auth] Reset password error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    public function validateResetToken($token) {
        if (!$token || strlen($token) !== 64) {
            return ['valid' => false, 'error' => 'invalid_token'];
        }

        $stmt = $this->pdo->prepare("
            SELECT expires_at, used 
            FROM password_resets 
            WHERE token = ? LIMIT 1
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row)             return ['valid' => false, 'error' => 'invalid_token'];
        if ($row['used'])      return ['valid' => false, 'error' => 'token_used'];
        if (strtotime($row['expires_at']) < time()) return ['valid' => false, 'error' => 'token_expired'];

        return ['valid' => true, 'expires_at' => $row['expires_at']];
    }

    // ============================================
    // 📧 EMAILS RESET
    // ============================================

    private function sendPasswordResetEmail($email, $token) {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';

            $resetLink = 'https://heberge.orinstone.deepstone.fr/resetpassword/?token=' . $token;

            $body = '
                <p>Bonjour,</p>
                <p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte OrinHeberge.</p>
                <p>Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :</p>
                <p style="text-align:center;margin:24px 0;">
                    <a href="' . htmlspecialchars($resetLink) . '" 
                       style="display:inline-block;padding:14px 28px;background:#0284c7;color:white;text-decoration:none;border-radius:12px;font-weight:bold;">
                        Réinitialiser mon mot de passe
                    </a>
                </p>
                <p style="font-size:13px;color:#6b7280;">
                    ⏱️ Ce lien expirera dans <strong>1 heure</strong>.<br>
                    Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.
                </p>
            ';

            if (function_exists('send_smtp_mail') && function_exists('email_layout')) {
                send_smtp_mail($email, '🔐 Réinitialisation de votre mot de passe - OrinHeberge', email_layout('Réinitialisation mot de passe', $body));
            }
        } catch (Throwable $e) {
            error_log('[Auth] Reset email error: ' . $e->getMessage());
        }
    }

    private function sendPasswordChangedEmail($email) {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';

            $body = '
                <p>Bonjour,</p>
                <p>Votre mot de passe OrinHeberge a été <strong>modifié avec succès</strong>.</p>
                <p>Si vous n\'êtes pas à l\'origine de ce changement, contactez immédiatement notre support :</p>
                <p style="text-align:center;margin:24px 0;">
                    <a href="https://heberge.orinstone.deepstone.fr/support/" 
                       style="display:inline-block;padding:14px 28px;background:#dc2626;color:white;text-decoration:none;border-radius:12px;font-weight:bold;">
                        Contacter le support
                    </a>
                </p>
            ';

            if (function_exists('send_smtp_mail') && function_exists('email_layout')) {
                send_smtp_mail($email, '✅ Mot de passe modifié - OrinHeberge', email_layout('Sécurité du compte', $body));
            }
        } catch (Throwable $e) {
            error_log('[Auth] Password changed email error: ' . $e->getMessage());
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
    // 🌐 OAUTH MULTI-PROVIDERS (Discord + Google)
    // ============================================

    /**
     * Login ou register via OAuth - Supporte plusieurs providers par user
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

        try {
            $this->pdo->beginTransaction();

            // ── 1. Chercher par provider+id dans user_oauth_providers ──
            $user = $this->findUserByOAuthProvider($provider, $providerId);

            if ($user) {
                // ✅ Compte trouvé via ce provider → update data
                $this->updateOAuthProvider($user['id'], $provider, $providerId, $email, $avatar, $rawData);
                
                // Refresh user data
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $user = $stmt->fetch();

                // Update avatar principal si fourni et absent
                if ($avatar && empty($user['avatar'])) {
                    $this->pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")
                              ->execute([$avatar, $user['id']]);
                }

                $this->startSession($user);
                $this->updateLastLogin($user['id']);
                
                $this->pdo->commit();
                return ['success' => true, 'user' => $this->sanitizeUser($user), 'is_new' => false];
            }

            // ── 2. Chercher par email (compte existant) → lier ce provider ──
            if ($email) {
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $existingUser = $stmt->fetch();

                if ($existingUser) {
                    // ✅ Lier ce nouveau provider au compte existant
                    $this->linkOAuthProviderToUser(
                        $existingUser['id'], 
                        $provider, 
                        $providerId, 
                        $email, 
                        $avatar, 
                        $rawData
                    );
                    
                    // Mettre à jour avatar si absent
                    if (empty($existingUser['avatar']) && $avatar) {
                        $this->pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")
                                  ->execute([$avatar, $existingUser['id']]);
                    }

                    $this->startSession($existingUser);
                    $this->updateLastLogin($existingUser['id']);
                    
                    $this->pdo->commit();
                    return [
                        'success' => true, 
                        'user' => $this->sanitizeUser($existingUser), 
                        'is_new' => false, 
                        'linked' => true,
                        'message' => "Compte lié avec " . ucfirst($provider)
                    ];
                }
            }

            // ── 3. Créer nouveau compte ──
            $pseudo = $this->generateUniquePseudo($firstname, $oauthData['username'] ?? null);

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

            // Ajouter dans la table dédiée aussi
            $this->linkOAuthProviderToUser($userId, $provider, $providerId, $email, $avatar, $rawData);

            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $this->startSession($user);
            $this->updateLastLogin($user['id']);

            // Email de bienvenue
            if ($sendWelcomeEmail && $email) {
                $this->sendOAuthWelcomeEmail($user, $provider);
            }

            $this->pdo->commit();
            return ['success' => true, 'user' => $this->sanitizeUser($user), 'is_new' => true];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log("[Auth] Erreur OAuth $provider : " . $e->getMessage());
            return ['success' => false, 'error' => 'db_error', 'details' => $e->getMessage()];
        }
    }

    /**
     * Trouver un user par provider+id (table dédiée OU legacy)
     */
    private function findUserByOAuthProvider($provider, $providerId) {
        // 1. Essayer la table dédiée d'abord
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.* FROM users u
                JOIN user_oauth_providers uop ON u.id = uop.user_id
                WHERE uop.provider = ? AND uop.provider_id = ?
                LIMIT 1
            ");
            $stmt->execute([$provider, $providerId]);
            $user = $stmt->fetch();
            if ($user) return $user;
        } catch (Exception $e) {
            // Table peut ne pas exister encore
        }

        // 2. Fallback : colonne legacy oauth_provider + oauth_id
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1");
        $stmt->execute([$provider, $providerId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lier un provider OAuth à un utilisateur existant
     */
    private function linkOAuthProviderToUser($userId, $provider, $providerId, $email, $avatar, $rawData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_oauth_providers (user_id, provider, provider_id, provider_email, provider_data, linked_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    provider_email = VALUES(provider_email),
                    provider_data = VALUES(provider_data),
                    linked_at = NOW()
            ");
            $stmt->execute([$userId, $provider, $providerId, $email, json_encode($rawData)]);
        } catch (Exception $e) {
            // Table peut ne pas exister, on essaie de la créer
            error_log('[Auth] linkOAuthProviderToUser error: ' . $e->getMessage());
        }
    }

    /**
     * Mettre à jour les données d'un provider existant
     */
    private function updateOAuthProvider($userId, $provider, $providerId, $email, $avatar, $rawData) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_oauth_providers 
                SET provider_email = ?, provider_data = ?, linked_at = NOW()
                WHERE user_id = ? AND provider = ? AND provider_id = ?
            ");
            $stmt->execute([$email, json_encode($rawData), $userId, $provider, $providerId]);
        } catch (Exception $e) {
            error_log('[Auth] updateOAuthProvider error: ' . $e->getMessage());
        }
        
        // Update avatar principal si fourni
        if ($avatar) {
            $this->pdo->prepare("UPDATE users SET avatar = ? WHERE id = ? AND (avatar IS NULL OR avatar = '')")
                      ->execute([$avatar, $userId]);
        }
    }

    /**
     * Récupérer tous les providers liés à un utilisateur
     */
    public function getUserOAuthProviders($userId) {
        $providers = [];
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT provider, provider_id, provider_email, linked_at
                FROM user_oauth_providers
                WHERE user_id = ?
                ORDER BY linked_at ASC
            ");
            $stmt->execute([$userId]);
            $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table peut ne pas exister
        }
        
        // Fallback : vérifier la colonne legacy
        if (empty($providers)) {
            $stmt = $this->pdo->prepare("SELECT oauth_provider, oauth_id, oauth_email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && !empty($user['oauth_provider']) && $user['oauth_provider'] !== 'local' && !empty($user['oauth_id'])) {
                $providers[] = [
                    'provider'       => $user['oauth_provider'],
                    'provider_id'    => $user['oauth_id'],
                    'provider_email' => $user['oauth_email'],
                    'linked_at'      => null
                ];
            }
        }
        
        return $providers;
    }

    /**
     * Dissocier un provider d'un utilisateur
     */
    public function unlinkOAuthProvider($userId, $provider) {
        // Compter providers actuels
        $currentProviders = $this->getUserOAuthProviders($userId);
        $oauthCount = count($currentProviders);
        
        // Vérifier si mot de passe local existe
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $hasPassword = !empty($user['password']);
        
        // Empêcher la déconnexion du dernier moyen
        if ($oauthCount <= 1 && !$hasPassword) {
            return ['success' => false, 'error' => 'cannot_unlink_last_provider'];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_oauth_providers 
                WHERE user_id = ? AND provider = ?
            ");
            $stmt->execute([$userId, $provider]);
            $deleted = $stmt->rowCount();
            
            // Si c'était le provider principal (legacy), nettoyer
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET oauth_provider = 'local', oauth_id = NULL, oauth_email = NULL, oauth_avatar = NULL, oauth_data = NULL
                WHERE id = ? AND oauth_provider = ?
            ");
            $stmt->execute([$userId, $provider]);
            
            return ['success' => true, 'unlinked' => $deleted > 0];
        } catch (Exception $e) {
            error_log('[Auth] unlinkOAuthProvider error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'db_error'];
        }
    }

    /**
     * Lier manuellement un provider OAuth à un compte existant (API publique)
     */
    public function linkOAuthProvider($userId, $provider, $oauthData) {
        // Vérifier que ce provider n'est pas déjà lié à un autre user
        $existing = $this->findUserByOAuthProvider($provider, (string)$oauthData['id']);
        if ($existing && $existing['id'] != $userId) {
            return ['success' => false, 'error' => 'provider_already_linked'];
        }
        
        $this->linkOAuthProviderToUser(
            $userId, 
            $provider, 
            (string)$oauthData['id'],
            $oauthData['email'] ?? null,
            $oauthData['avatar'] ?? null,
            $oauthData['raw'] ?? []
        );
        
        return ['success' => true];
    }

    // ============================================
    // 🦅 PTERODACTYL
    // ============================================

    private function createPterodactylUser($userId, $email, $pseudo, $firstname, $lastname, $password) {
        if (!$this->pterodactylEnabled || !function_exists('pterodactylApi')) {
            return false;
        }

        try {
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
                $this->pdo->prepare('UPDATE users SET panel_password = ? WHERE id = ?')->execute([$password, $userId]);
                return true;
            }
        } catch (Throwable $e) {
            error_log('[Auth] Pterodactyl creation error: ' . $e->getMessage());
        }

        return false;
    }

    // ============================================
    // 📧 EMAILS BIENVENUE
    // ============================================

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
            ';

            if (function_exists('send_smtp_mail') && function_exists('email_layout')) {
                send_smtp_mail($user['email'], '🎉 Bienvenue sur OrinHeberge — vos identifiants', email_layout('Bienvenue !', $body));
            }
        } catch (Throwable $e) {
            error_log('[Auth] Email error: ' . $e->getMessage());
        }
    }

    private function sendOAuthWelcomeEmail($user, $provider) {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';

            $providerName = ucfirst($provider);
            $body = '
                <p>Bonjour <strong>' . htmlspecialchars($user['firstname']) . '</strong>,</p>
                <p>Votre compte OrinHeberge a été créé via <strong>' . $providerName . '</strong> !</p>
                <p>Vous pouvez désormais vous connecter en un clic avec votre compte ' . $providerName . '.</p>
                <p style="margin-top:24px;">
                    <a href="https://heberge.orinstone.deepstone.fr/login/" class="btn">Accéder à mon compte →</a>
                </p>
            ';

            if (function_exists('send_smtp_mail') && function_exists('email_layout')) {
                send_smtp_mail($user['email'], "🎉 Bienvenue sur OrinHeberge via $providerName", email_layout('Bienvenue !', $body));
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
        return count($recentAttempts) >= 5;
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

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 3600);
            
            setcookie('remember_token', $token, $expires, '/', '', true, true);
            
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO remember_tokens (user_id, token, expires_at)
                    VALUES (?, ?, FROM_UNIXTIME(?))
                ");
                $stmt->execute([$user['id'], hash('sha256', $token), $expires]);
            } catch (Exception $e) {
                // Silencieux
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
}