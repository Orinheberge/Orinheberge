<?php
// Simple SMTP sender using plain sockets (supports STARTTLS)
// Configure these values to match your SMTP server
$smtp_config = [
    'host'     => 'ssl0.ovh.net', // Serveur SMTP
    'port'     => 465,                      // Port (ex: 465 pour SSL, 587 pour TLS)
    'username' => 'deepstone@deepstone.fr',
    'password' => '4A.bPUg9_bShK5P',
    'auth'     => true,                     // Activer l'authentification
    'secure'   => 'ssl'                     // 'ssl' ou 'tls'
];

function send_smtp_mail($to, $subject, $htmlBody, $fromName = 'OrinHeberge', $fromEmail = 'no-reply@orinheberge.local', $custom_config = null) {
    // Utilise la configuration passée en paramètre ou globale
    global $smtp_config;
    $config = $custom_config ?? $smtp_config;

    $host = $config['host'];
    $port = (int)$config['port'];
    $user = $config['username'];
    $pass = $config['password'];
    $secure = strtolower($config['secure'] ?? '');

    $newline = "\r\n";
    $timeout = 15;
    $errno = 0; 
    $errstr = '';

    // CORRECTION : Si SSL (Port 465), on doit ajouter le protocole ssl:// à l'hôte
    if ($secure === 'ssl') {
        $host = 'ssl://' . $host;
    }

    $sock = fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$sock) {
        return false;
    }
    stream_set_timeout($sock, $timeout);

    // Lecture de la bannière du serveur
    fgets($sock, 512);

    // EHLO initial
    fputs($sock, "EHLO localhost" . $newline);
    while ($line = fgets($sock, 512)) { 
        if (substr($line, 3, 1) == ' ') break; 
    }

    // Gestion du STARTTLS (Port 587)
    if ($secure === 'tls') {
        fputs($sock, "STARTTLS" . $newline);
        fgets($sock, 512);
        
        // Activation du chiffrement sur le socket existant
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock);
            return false;
        }
        
        // Ré-envoi de EHLO après sécurisation TLS
        fputs($sock, "EHLO localhost" . $newline);
        while ($line = fgets($sock, 512)) { 
            if (substr($line, 3, 1) == ' ') break; 
        }
    }

    // Authentification SMTP
    fputs($sock, "AUTH LOGIN" . $newline);
    fgets($sock, 512);
    fputs($sock, base64_encode($user) . $newline);
    fgets($sock, 512);
    fputs($sock, base64_encode($pass) . $newline);
    fgets($sock, 512);

    // Enveloppe de transmission
    fputs($sock, "MAIL FROM:<" . $fromEmail . ">" . $newline);
    fgets($sock, 512);
    fputs($sock, "RCPT TO:<" . $to . ">" . $newline);
    fgets($sock, 512);
    fputs($sock, "DATA" . $newline);
    fgets($sock, 512);

    // En-têtes du mail (Correction de l'encodage du sujet pour éviter les bugs d'accents)
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = '';
    $headers .= 'From: ' . $fromName . ' <' . $fromEmail . '>' . $newline;
    $headers .= 'MIME-Version: 1.0' . $newline;
    $headers .= 'Content-Type: text/html; charset=UTF-8' . $newline;

    // Construction et envoi du contenu
    $data = 'Subject: ' . $encoded_subject . $newline . $headers . $newline . $htmlBody . $newline . '.' . $newline;
    fputs($sock, $data);
    $res = fgets($sock, 512);

    // Fermeture propre
    fputs($sock, "QUIT" . $newline);
    fclose($sock);

    // Retourne true si le serveur a bien accepté le mail (Codes 250 ou 354)
    return (strpos($res, '250') !== false || strpos($res, '354') !== false);
}
?>
