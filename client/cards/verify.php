<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['setup_intent'])) {
    header('Location: /client/cards/');
    exit();
}

$setup_intent_id = $_GET['setup_intent'];
$stripe_pub_key = get_setting('stripe_public_key');

if (empty($stripe_pub_key)) {
    die('Configuration Stripe manquante.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Validation bancaire</title>
    <script src="https://js.stripe.com/v3/"></script>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body { 
            background: #0f172a; 
            color: white; 
            font-family: system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .loader {
            border: 4px solid rgba(255,255,255,0.1);
            border-top: 4px solid #38bdf8;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div style="text-align: center;">
        <div class="loader"></div>
        <h2>Validation par votre banque</h2>
        <p style="color: #94a3b8;">Vous allez être redirigé vers votre banque pour sécuriser votre carte...</p>
    </div>

    <script>
        const stripe = Stripe('<?php echo htmlspecialchars($stripe_pub_key); ?>');
        
        stripe.confirmCardSetup('<?php echo htmlspecialchars($setup_intent_id); ?>')
            .then(function(result) {
                if (result.error) {
                    alert('Erreur: ' + result.error.message);
                    window.location.href = '/client/cards/';
                } else {
                    fetch('/client/cards/save-card.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            payment_method_id: result.setupIntent.payment_method
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '/client/cards/?success=1';
                        } else {
                            alert('Erreur: ' + data.error);
                            window.location.href = '/client/cards/';
                        }
                    });
                }
            });
    </script>
</body>
</html>