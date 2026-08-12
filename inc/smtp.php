<?php
/**
 * inc/smtp.php — SMTP socket sender + helpers emails transactionnels
 * La config SMTP est lue depuis la table `settings` (BDD).
 * Toutes les fonctions d'envoi d'emails OrinHeberge sont ici.
 */

/**
 * Charge la config SMTP depuis la BDD.
 * Retourne un tableau ['host','port','username','password','secure'].
 */
function smtp_config_from_db(PDO $pdo): array {
    $rows = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_secure','smtp_from','smtp_from_name')")->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'host'      => $rows['smtp_host']      ?? '',
        'port'      => (int)($rows['smtp_port']    ?? 587),
        'username'  => $rows['smtp_user']      ?? '',
        'password'  => $rows['smtp_pass']      ?? '',
        'secure'    => strtolower($rows['smtp_secure'] ?? 'tls'),
        'from'      => $rows['smtp_from']      ?? 'no-reply@orinheberge.fr',
        'from_name' => $rows['smtp_from_name'] ?? 'OrinHeberge',
    ];
}

/**
 * Lit toutes les lignes d'une réponse SMTP multi-lignes.
 * Retourne le code numérique (int) et log l'échange en cas d'erreur.
 */
function _smtp_read(mixed $sock, int $timeout): int {
    $code = 0;
    $deadline = microtime(true) + $timeout;
    while (microtime(true) < $deadline) {
        $line = fgets($sock, 512);
        if ($line === false) break;
        $code = (int)substr($line, 0, 3);
        if (substr($line, 3, 1) === ' ') break; // dernière ligne de la réponse
    }
    return $code;
}

/**
 * Envoie un email via socket SMTP (SSL/TLS).
 * Compatible OVH ssl0.ovh.net port 465 (SSL natif) et Gmail port 587 (STARTTLS).
 * Retourne true si accepté, false sinon.
 */
function send_smtp_mail(string $to, string $subject, string $htmlBody, string $fromName = 'OrinHeberge', string $fromEmail = 'no-reply@orinheberge.fr', ?array $custom_config = null): bool {
    global $pdo;

    // Priorité : config passée → BDD → fallback
    if ($custom_config) {
        $config = $custom_config;
    } elseif (isset($pdo)) {
        try { $config = smtp_config_from_db($pdo); } catch (Throwable $e) { $config = []; }
    } else {
        $config = [];
    }

    $host    = $config['host']      ?? '';
    $port    = (int)($config['port']     ?? 587);
    $user    = $config['username']  ?? $config['smtp_user'] ?? '';
    $pass    = $config['password']  ?? $config['smtp_pass'] ?? '';
    $secure  = strtolower($config['secure']  ?? 'tls');
    $from    = $config['from']      ?? $fromEmail;
    $fname   = $config['from_name'] ?? $fromName;

    // Si pas de config SMTP valide on abandonne silencieusement
    if (!$host || !$user || !$pass) {
        error_log("SMTP: config manquante (host=$host user=$user)");
        return false;
    }

    $nl      = "\r\n";
    $timeout = 15;
    $errno   = 0;
    $errstr  = '';

    // SSL natif (OVH port 465) : on ouvre le socket avec ssl://
    // STARTTLS (Gmail port 587) : on ouvre en clair, puis on upgrade
    if ($secure === 'ssl') {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);
        $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    } else {
        $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
    }

    if (!$sock) {
        error_log("SMTP: connexion échouée ({$host}:{$port} secure={$secure}) — {$errstr}");
        return false;
    }
    stream_set_timeout($sock, $timeout);

    _smtp_read($sock, $timeout); // banner 220

    fputs($sock, "EHLO localhost{$nl}");
    _smtp_read($sock, $timeout); // 250-...

    if ($secure === 'tls') {
        fputs($sock, "STARTTLS{$nl}");
        $code = _smtp_read($sock, $timeout); // 220
        if ($code !== 220) {
            error_log("SMTP: STARTTLS refusé (code={$code})");
            fclose($sock); return false;
        }
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);
        stream_context_set_option($sock, $ctx);
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("SMTP: upgrade TLS échoué");
            fclose($sock); return false;
        }
        fputs($sock, "EHLO localhost{$nl}");
        _smtp_read($sock, $timeout); // 250-...
    }

    fputs($sock, "AUTH LOGIN{$nl}");
    _smtp_read($sock, $timeout); // 334
    fputs($sock, base64_encode($user) . $nl);
    _smtp_read($sock, $timeout); // 334
    fputs($sock, base64_encode($pass) . $nl);
    $auth_code = _smtp_read($sock, $timeout); // 235 = OK, 5xx = échec
    if ($auth_code !== 235) {
        error_log("SMTP: authentification échouée (code={$auth_code} user={$user})");
        fclose($sock); return false;
    }

    fputs($sock, "MAIL FROM:<{$from}>{$nl}");
    $code = _smtp_read($sock, $timeout); // 250
    if ($code !== 250) {
        error_log("SMTP: MAIL FROM rejeté (code={$code})");
        fclose($sock); return false;
    }

    fputs($sock, "RCPT TO:<{$to}>{$nl}");
    $code = _smtp_read($sock, $timeout); // 250
    if ($code !== 250 && $code !== 251) {
        error_log("SMTP: RCPT TO rejeté (code={$code} to={$to})");
        fclose($sock); return false;
    }

    fputs($sock, "DATA{$nl}");
    $code = _smtp_read($sock, $timeout); // 354
    if ($code !== 354) {
        error_log("SMTP: DATA refusé (code={$code})");
        fclose($sock); return false;
    }

    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers  = "From: =?UTF-8?B?" . base64_encode($fname) . "?= <{$from}>{$nl}";
    $headers .= "To: {$to}{$nl}";
    $headers .= "Subject: {$encoded_subject}{$nl}";
    $headers .= "MIME-Version: 1.0{$nl}";
    $headers .= "Content-Type: text/html; charset=UTF-8{$nl}";
    $headers .= "Content-Transfer-Encoding: quoted-printable{$nl}";

    fputs($sock, $headers . $nl . quoted_printable_encode($htmlBody) . $nl . ".{$nl}");
    $code = _smtp_read($sock, $timeout); // 250

    fputs($sock, "QUIT{$nl}");
    fclose($sock);

    if ($code !== 250) {
        error_log("SMTP: message rejeté (code={$code})");
        return false;
    }
    return true;
}

// ──────────────────────────────────────────────────────────────────────────────
// TEMPLATES EMAILS
// ──────────────────────────────────────────────────────────────────────────────

function email_layout(string $title, string $body): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body{font-family:-apple-system,"Segoe UI",system-ui,sans-serif;background:#0d0f14;color:#e2e8f0;margin:0;padding:24px}
.wrap{max-width:560px;margin:0 auto;background:#161a22;border:1px solid rgba(255,255,255,.08);border-radius:16px;overflow:hidden}
.head{background:linear-gradient(135deg,#0ea5e9,#6366f1);padding:28px 32px}
.head h1{margin:0;font-size:22px;font-weight:900;color:#fff;letter-spacing:-.5px}
.head p{margin:4px 0 0;font-size:13px;color:rgba(255,255,255,.75)}
.body{padding:28px 32px}
.body p{margin:0 0 14px;font-size:14px;color:#cbd5e1;line-height:1.6}
.box{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:16px 20px;margin:18px 0}
.box .row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:13px;border-bottom:1px solid rgba(255,255,255,.05)}
.box .row:last-child{border-bottom:none}
.box .row .label{color:#6b7280}
.box .row .val{font-weight:700;color:#f1f5f9}
.box .row .val.mono{font-family:monospace;color:#38bdf8}
.btn{display:inline-block;background:#0ea5e9;color:#fff;text-decoration:none;padding:12px 28px;border-radius:10px;font-weight:700;font-size:14px;margin:8px 0}
.footer{padding:18px 32px;border-top:1px solid rgba(255,255,255,.06);font-size:11px;color:#374151;text-align:center}
</style></head><body>
<div class="wrap">
<div class="head"><h1>OrinHeberge</h1><p>Infrastructure OrinStone</p></div>
<div class="body">' . $body . '</div>
<div class="footer">© OrinHeberge — Infrastructure OrinStone. Ne pas répondre à cet email.</div>
</div></body></html>';
}

/**
 * Email de confirmation de commande (achat initial ou gratuit).
 */
function send_order_confirmation_email(
    PDO    $pdo,
    string $to,
    string $username,
    string $order_id,
    string $service_name,
    float  $price,
    string $panel_identifier,
    ?string $panel_password,
    string $panel_url
): void {
    $price_label = $price > 0 ? number_format($price, 2, ',', '') . '€/mois' : 'Gratuit';
    $panel_link  = rtrim($panel_url, '/') . '/server/' . $panel_identifier;

    $body = '
    <p>Bonjour <strong>' . htmlspecialchars($username) . '</strong>,</p>
    <p>Votre serveur a été déployé avec succès ! Voici le récapitulatif de votre commande :</p>
    <div class="box">
        <div class="row"><span class="label">N° Commande</span><span class="val mono">#' . htmlspecialchars($order_id) . '</span></div>
        <div class="row"><span class="label">Service</span><span class="val">' . htmlspecialchars($service_name) . '</span></div>
        <div class="row"><span class="label">Prix</span><span class="val">' . $price_label . '</span></div>
        <div class="row"><span class="label">Identifiant panel</span><span class="val mono">' . htmlspecialchars($panel_identifier) . '</span></div>
        ' . ($panel_password ? '<div class="row"><span class="label">Mot de passe panel</span><span class="val mono">' . htmlspecialchars($panel_password) . '</span></div>' : '') . '
    </div>
    ' . ($panel_password ? '<p style="color:#f59e0b;font-size:13px;">⚠️ Notez votre mot de passe panel — il ne vous sera plus affiché.</p>' : '') . '
    <p><a href="' . htmlspecialchars($panel_link) . '" class="btn">Accéder à mon serveur →</a></p>
    <p style="font-size:12px;color:#4b5563;">En cas de problème, ouvrez un ticket depuis votre espace client.</p>';

    $html = email_layout('Confirmation de commande', $body);
    send_smtp_mail($to, '✅ Votre serveur ' . $service_name . ' est prêt !', $html);
}

/**
 * Email de confirmation de renouvellement.
 */
function send_renewal_confirmation_email(
    PDO    $pdo,
    string $to,
    string $username,
    string $order_id,
    string $service_name,
    float  $price,
    string $next_payment_date
): void {
    $body = '
    <p>Bonjour <strong>' . htmlspecialchars($username) . '</strong>,</p>
    <p>Votre renouvellement a bien été enregistré. Votre serveur est actif jusqu\'à la prochaine échéance.</p>
    <div class="box">
        <div class="row"><span class="label">N° Commande</span><span class="val mono">#' . htmlspecialchars($order_id) . '</span></div>
        <div class="row"><span class="label">Service</span><span class="val">' . htmlspecialchars($service_name) . '</span></div>
        <div class="row"><span class="label">Montant payé</span><span class="val">' . number_format($price, 2, ',', '') . '€</span></div>
        <div class="row"><span class="label">Prochaine échéance</span><span class="val">' . htmlspecialchars($next_payment_date) . '</span></div>
    </div>
    <p><a href="https://heberge.orinstone.deepstone.fr/client/" class="btn">Mon espace client →</a></p>';

    $html = email_layout('Renouvellement confirmé', $body);
    send_smtp_mail($to, '🔄 Renouvellement confirmé — ' . $service_name, $html);
}

/**
 * Email de rappel d'expiration (utilisé par le cron).
 */
function send_expiry_reminder_email(
    PDO    $pdo,
    string $to,
    string $username,
    string $order_id,
    string $service_name,
    float  $price,
    string $due_date,
    string $renew_url
): void {
    $body = '
    <p>Bonjour <strong>' . htmlspecialchars($username) . '</strong>,</p>
    <p>Votre serveur <strong>' . htmlspecialchars($service_name) . '</strong> expire le <strong>' . htmlspecialchars($due_date) . '</strong>.</p>
    <p>Pour éviter la suspension, renouvelez dès maintenant :</p>
    <div class="box">
        <div class="row"><span class="label">N° Commande</span><span class="val mono">#' . htmlspecialchars($order_id) . '</span></div>
        <div class="row"><span class="label">Montant</span><span class="val">' . number_format($price, 2, ',', '') . '€/mois</span></div>
        <div class="row"><span class="label">Échéance</span><span class="val">' . htmlspecialchars($due_date) . '</span></div>
    </div>
    <p><a href="' . htmlspecialchars($renew_url) . '" class="btn">Renouveler maintenant →</a></p>
    <p style="font-size:12px;color:#4b5563;">Sans renouvellement, votre serveur sera suspendu à la date d\'expiration.</p>';

    $html = email_layout('Renouvellement requis', $body);
    send_smtp_mail($to, '⚠️ Votre serveur ' . $service_name . ' expire le ' . $due_date, $html);
}
