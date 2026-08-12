<?php
session_start();
// Pas besoin d'être connecté — page publique post-annulation Stripe
$order_id = htmlspecialchars($_GET['order_id'] ?? '', ENT_QUOTES, 'UTF-8');
$plan     = htmlspecialchars($_GET['plan'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Paiement annulé</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
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
<body class="text-gray-200 font-sans min-h-screen flex flex-col items-center justify-center px-4 py-10">

    <div class="glass p-8 sm:p-10 rounded-2xl w-full max-w-md text-center shadow-2xl">

        <!-- Icône annulation -->
        <div class="w-16 h-16 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
            <i class="fas fa-circle-xmark"></i>
        </div>

        <h1 class="text-2xl font-black tracking-tight mb-2">Paiement annulé</h1>
        <p class="text-gray-500 text-sm mb-6">
            Vous avez annulé le processus de paiement. Aucun montant n'a été débité.
        </p>

        <?php if ($order_id): ?>
        <div class="bg-white/[0.03] border border-white/[0.05] p-3 rounded-xl text-xs text-gray-500 font-mono mb-6">
            Référence : <span class="text-gray-300">#<?= $order_id ?></span>
        </div>
        <?php endif; ?>

        <div class="space-y-3">
            <?php if ($plan): ?>
            <a href="/shop/order/?plan=<?= urlencode($plan) ?>"
               class="flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-500 text-white p-3.5 rounded-xl font-bold transition text-sm">
                <i class="fas fa-rotate-left"></i> Réessayer le paiement
            </a>
            <?php endif; ?>
            <a href="/shop/"
               class="flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 p-3.5 rounded-xl font-bold transition text-sm">
                <i class="fas fa-tags"></i> Voir toutes les offres
            </a>
            <a href="/client/"
               class="flex items-center justify-center gap-2 text-gray-500 hover:text-gray-300 transition text-xs mt-2">
                <i class="fas fa-arrow-left text-[10px]"></i> Retour à l'espace client
            </a>
        </div>

        <div class="mt-6 p-3 rounded-xl bg-blue-500/5 border border-blue-500/10 text-xs text-gray-500 text-left flex gap-2">
            <i class="fas fa-circle-info text-blue-400 mt-0.5 shrink-0"></i>
            <span>Si vous rencontrez un problème lors du paiement, <a href="/support/" class="text-sky-400 hover:underline">ouvrez un ticket support</a>.</span>
        </div>
    </div>

</body>
</html>
