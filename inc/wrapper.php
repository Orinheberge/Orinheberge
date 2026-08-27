<?php
// inc/wrapper.php

// Valeurs par défaut si non définies dans la page appelante
$page_title = $page_title ?? 'Orinheberge';
$body_class = $body_class ?? 'bg-neutral-50 text-gray-200 font-sans';
$csrf_token = $_SESSION['csrf_token'] ?? '';
$user_data_json = 'null';

// Préparation des données utilisateur si connecté
if (isset($_SESSION['user_id'])) {
    // Adaptez ceci selon votre structure de session/database
    $user_data = [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? '',
        'name' => $_SESSION['user_name'] ?? 'Utilisateur',
        // Ajoutez d'autres champs si nécessaire
    ];
    $user_data_json = json_encode($user_data);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <meta name="robots" content="noindex">
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="https://heberge.deepstone.fr/favicon.ico">
    <link rel="icon" type="image/png" href="https://heberge.deepstone.fr/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/png" href="https://heberge.deepstone.fr/favicon.ico" sizes="16x16">
    <link rel="manifest" href="/favicons/manifest.json">
    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#b91c1c">
    <link rel="shortcut icon" href="https://heberge.deepstone.fr/favicon.ico">
    <meta name="msapplication-config" content="/favicons/browserconfig.xml">
    <meta name="theme-color" content="#0a0a0a">

    <!-- Tailwind CSS & Font Awesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Scripts spécifiques à la page (si définis) -->
    <?php if (!empty($head_scripts)): echo $head_scripts; endif; ?>

    <style>
      #orin-loader {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: #050505; z-index: 999999; display: none;
        flex-direction: column; justify-content: center; align-items: center;
        color: #ffffff; font-family: 'Inter', system-ui, -apple-system, sans-serif;
        opacity: 1; visibility: visible;
        transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.8s;
        overflow: hidden;
      }
      .orin-bg-glow {
        position: absolute; width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(220, 38, 38, 0.18) 0%, rgba(5, 5, 5, 0) 70%);
        border-radius: 50%; top: 50%; left: 50%;
        transform: translate(-50%, -50%) scale(0.8);
        animation: orinPulseGlow 4s ease-in-out infinite alternate; pointer-events: none;
      }
      .orin-box {
        position: relative; z-index: 2; display: flex; flex-direction: column;
        align-items: center; gap: 14px; text-align: center; pointer-events: none;
      }
      .orin-subtext {
        font-size: 0.85rem; font-weight: 700; letter-spacing: 0.3em; text-transform: uppercase;
        color: #ef4444; opacity: 0; transform: translateY(12px);
        animation: orinFadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.2s;
      }
      .orin-title-row { display: flex; align-items: center; justify-content: center; gap: 14px; }
      .orin-logo {
        width: 48px; height: 48px; border-radius: 12px; object-fit: cover;
        opacity: 0; transform: translateY(20px) scale(0.8) rotate(-8deg);
        animation: orinLogoPop 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.4s;
        filter: drop-shadow(0 0 18px rgba(220, 38, 38, 0.45));
      }
      .orin-title {
        font-size: 3rem; font-weight: 800; letter-spacing: -0.03em;
        background: linear-gradient(135deg, #ffffff 30%, #b91c1c 100%);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        opacity: 0; transform: translateY(20px) scale(0.94);
        animation: orinPop 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.4s;
        filter: drop-shadow(0 0 25px rgba(220, 38, 38, 0.35));
      }
      .orin-spinner-container {
        margin-top: 28px; position: relative; width: 50px; height: 50px;
        display: flex; justify-content: center; align-items: center;
        opacity: 0; animation: orinFade 0.6s ease forwards 0.6s;
      }
      .orin-spinner {
        width: 46px; height: 46px; border: 3px solid rgba(255, 255, 255, 0.05);
        border-top: 3px solid #dc2626; border-radius: 50%;
        animation: orinSpin 1s cubic-bezier(0.5, 0.1, 0.5, 0.9) infinite;
      }
      .orin-spinner-inner {
        position: absolute; width: 30px; height: 30px; border: 2px solid transparent;
        border-bottom: 2px solid #7f1d1d; border-radius: 50%;
        animation: orinSpinReverse 0.8s linear infinite;
      }
      .orin-footer {
        position: absolute; bottom: 32px; font-size: 0.85rem; color: #9ca3af;
        letter-spacing: 0.15em; font-variant: small-caps; opacity: 0;
        animation: orinFade 0.8s ease forwards 0.8s; z-index: 2; pointer-events: none;
      }
      .orin-fade-out {
        opacity: 0 !important; transform: scale(1.05) !important; visibility: hidden !important;
      }
      @keyframes orinPulseGlow {
        0% { transform: translate(-50%, -50%) scale(0.85); opacity: 0.6; }
        100% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
      }
      @keyframes orinSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
      @keyframes orinSpinReverse { 0% { transform: rotate(360deg); } 100% { transform: rotate(0deg); } }
      @keyframes orinFadeUp { to { opacity: 0.95; transform: translateY(0); } }
      @keyframes orinPop { to { opacity: 1; transform: translateY(0) scale(1); } }
      @keyframes orinLogoPop { to { opacity: 1; transform: translateY(0) scale(1) rotate(0deg); } }
      @keyframes orinFade { to { opacity: 1; } }
    </style>
</head>
<body class="<?php echo $body_class; ?>">
    
    <!-- LOADER START -->
    <div id="orin-loader">
      <div class="orin-bg-glow"></div>
      <div class="orin-box">
        <div class="orin-subtext"><i class="fa-solid fa-server"></i> PROPULS&Eacute; PAR</div>
        <div class="orin-title-row">
          <img class="orin-logo" src="https://heberge.deepstone.fr/favicon.ico" alt="Orinheberge logo">
          <a id="orin-title" class="orin-title" href="https://heberge.deepstone.fr" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">Orinheberge</a>
        </div>
        <div class="orin-spinner-container">
          <div class="orin-spinner"></div>
          <div class="orin-spinner-inner"></div>
        </div>
      </div>
      <div class="orin-footer">2026-2029 Orinstudio Tous droits r&eacute;serv&eacute;s.</div>
    </div>
    <!-- LOADER END -->

    <script>
        window.PterodactylUser = <?php echo $user_data_json; ?>;
    </script>