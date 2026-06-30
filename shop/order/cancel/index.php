<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orinheberge | Paiement annulé</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; }
        .glass {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .gradient-text {
            background: linear-gradient(90deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col items-center justify-center px-4">
    <div class="glass p-10 rounded-2xl w-full max-w-md text-center shadow-2xl">
        <div class="w-16 h-16 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
            <i class="fas fa-times-circle"></i>
        </div>
        <h1 class="text-2xl font-black mb-2">Paiement annulé</h1>
        <p class="text-gray-400 mb-6">Votre commande a bien été annulée. Aucun montant n'a été débité.</p>
        <a href="/shop/" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold transition inline-flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Retour aux offres
        </a>
    </div>
</body>
</html>
