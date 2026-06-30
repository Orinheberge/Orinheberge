<?php
// Meta SEO avant redirection
echo '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">

    <title>Discord - OrinHeberge</title>

    <meta name="description" content="Rejoignez le serveur Discord officiel OrinHeberge pour obtenir de l’aide, discuter avec la communauté et suivre les annonces.">
    <meta name="keywords" content="discord, orinheberge, communauté, support, minecraft, hébergement">
    <meta name="author" content="OrinHeberge">

    <meta property="og:title" content="Discord - OrinHeberge">
    <meta property="og:description" content="Rejoignez la communauté officielle OrinHeberge sur Discord.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr/discord/">

    <meta http-equiv="refresh" content="0;url=https://discord.gg/rnM2fngc7Z">
</head>
<body>
    <script>
        window.location.href = "https://discord.gg/rnM2fngc7Z";
    </script>
</body>
</html>
';
exit();
?>