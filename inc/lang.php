<?php
/**
 * OrinHeberge — Système de traduction
 * Inclure EN PREMIER après session_start() dans chaque page.
 *
 * Usage : echo t('clé');
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Détection & persistance de la langue ──────────────────────────────────
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    // Redirige proprement pour supprimer ?lang= de l'URL
    $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $clean_url);
    exit;
}
$lang = $_SESSION['lang'] ?? 'fr';

// ─── Dictionnaire complet ───────────────────────────────────────────────────
$translations = [

    // ══════════════════════════════════════════════════════════════════════
    // NAVIGATION (partagée sur toutes les pages)
    // ══════════════════════════════════════════════════════════════════════
    'nav.home'            => ['fr' => 'Accueil',           'en' => 'Home'],
    'nav.servers'         => ['fr' => 'Mes serveurs',      'en' => 'My servers'],
    'nav.offers'          => ['fr' => 'Offres',            'en' => 'Offers'],
    'nav.support'         => ['fr' => 'Support',           'en' => 'Support'],
    'nav.login'           => ['fr' => 'Connexion',         'en' => 'Login'],
    'nav.register'        => ['fr' => 'Inscription',       'en' => 'Sign up'],
    'nav.logout'          => ['fr' => 'Déconnexion',       'en' => 'Logout'],
    'nav.profile'         => ['fr' => 'Mon Profil',        'en' => 'My Profile'],
    'nav.admin_tickets'   => ['fr' => 'Gérer les tickets (Admin)', 'en' => 'Manage tickets (Admin)'],
    'nav.admin_tickets_short' => ['fr' => 'Gérer les tickets', 'en' => 'Manage tickets'],
    'nav.phpmyadmin'      => ['fr' => 'phpMyAdmin',        'en' => 'phpMyAdmin'],
    'nav.panel'           => ['fr' => 'Panel',             'en' => 'Panel'],

    // ══════════════════════════════════════════════════════════════════════
    // FOOTER
    // ══════════════════════════════════════════════════════════════════════
    'footer.nav'          => ['fr' => 'Navigation',        'en' => 'Navigation'],
    'footer.network'      => ['fr' => 'Notre Réseau',      'en' => 'Our Network'],
    'footer.discord'      => ['fr' => 'Notre Discord',     'en' => 'Our Discord'],
    'footer.status'       => ['fr' => 'Statut des Services', 'en' => 'Service Status'],
    'footer.links'        => ['fr' => 'Liens Utiles',      'en' => 'Useful Links'],
    'footer.payments'     => ['fr' => 'Moyens de Paiements Acceptés', 'en' => 'Accepted Payment Methods'],
    'footer.legal'        => ['fr' => 'Mentions Légales',  'en' => 'Legal Notice'],
    'footer.cgu'          => ['fr' => "Conditions Générales d'Utilisation", 'en' => 'Terms of Service'],
    'footer.privacy'      => ['fr' => 'Politique de Confidentialité', 'en' => 'Privacy Policy'],
    'footer.copyright'    => ['fr' => '© 2026-2029 OrinHeberge — Infrastructure OrinStone. Tous droits réservés.', 'en' => '© 2026-2029 OrinHeberge — OrinStone Infrastructure. All rights reserved.'],
    'footer.powered'      => ['fr' => 'Propulsé par',      'en' => 'Powered by'],

    // ══════════════════════════════════════════════════════════════════════
    // DISCORD BUTTON (partagé)
    // ══════════════════════════════════════════════════════════════════════
    'discord.help'        => ['fr' => 'Besoin d\'aide ? Discord', 'en' => 'Need help? Discord'],

    // ══════════════════════════════════════════════════════════════════════
    // INDEX — Hero
    // ══════════════════════════════════════════════════════════════════════
    'hero.title'          => ['fr' => 'L\'hébergement réinventé', 'en' => 'Hosting reinvented'],
    'hero.subtitle'       => ['fr' => 'Déployez vos infrastructures sur du matériel de pointe. Performance brute, sécurité maximale et stabilité garantie.', 'en' => 'Deploy your infrastructure on cutting-edge hardware. Raw performance, maximum security and guaranteed stability.'],
    'hero.cta'            => ['fr' => 'Découvrir nos offres', 'en' => 'Browse our plans'],
    'hero.status_ok'      => ['fr' => 'Infrastructure opérationnelle', 'en' => 'Infrastructure operational'],
    'hero.status_ko'      => ['fr' => 'Maintenance en cours',  'en' => 'Maintenance in progress'],

    // ══════════════════════════════════════════════════════════════════════
    // INDEX — About section
    // ══════════════════════════════════════════════════════════════════════
    'about.badge'         => ['fr' => 'À PROPOS DE NOUS',  'en' => 'ABOUT US'],
    'about.title'         => ['fr' => 'Pourquoi faire confiance à', 'en' => 'Why trust'],
    'about.p1'            => ['fr' => 'OrinHeberge est un hébergeur de nouvelle génération conçu pour offrir un équilibre parfait entre haute performance, accessibilité et simplicité d\'utilisation. Structuré autour d\'une infrastructure robuste (<strong>OrinStone</strong>), il s\'adresse aussi bien aux passionnés, aux développeurs indépendants qu\'aux communautés exigeantes.', 'en' => 'OrinHeberge is a next-generation hosting provider designed to offer the perfect balance between high performance, accessibility and ease of use. Built around a robust infrastructure (<strong>OrinStone</strong>), it serves enthusiasts, independent developers and demanding communities alike.'],
    'about.p2'            => ['fr' => 'Plus qu\'un simple fournisseur, l\'hébergeur s\'appuie sur une forte présence communautaire pour assurer un support de proximité, ultra-réactif et constamment à l\'écoute de ses utilisateurs.', 'en' => 'More than just a provider, the host relies on a strong community presence to ensure responsive, close-knit support that constantly listens to its users.'],
    'about.feat1_title'   => ['fr' => 'Infrastructure Top Tier',  'en' => 'Top-Tier Infrastructure'],
    'about.feat1_desc'    => ['fr' => 'Processeurs Ryzen de dernière génération et stockage SSD NVMe ultra-rapide.', 'en' => 'Latest-generation Ryzen processors and ultra-fast NVMe SSD storage.'],
    'about.feat2_title'   => ['fr' => 'Écosystème Polyvalent',    'en' => 'Versatile Ecosystem'],
    'about.feat2_desc'    => ['fr' => 'Environnements optimisés pour vos serveurs Minecraft, PHP et projets Node.js.', 'en' => 'Optimised environments for your Minecraft servers, PHP sites and Node.js projects.'],
    'about.feat3_title'   => ['fr' => 'Modèle Équitable',         'en' => 'Fair Model'],
    'about.feat3_desc'    => ['fr' => 'Des offres gratuites généreuses et des formules Premium pour aller plus loin.', 'en' => 'Generous free plans and Premium packages to go further.'],
    'about.feat4_title'   => ['fr' => 'Gestion Sécurisée',        'en' => 'Secure Management'],
    'about.feat4_desc'    => ['fr' => 'Panel d\'administration intuitif, outils complets et protection DDoS native.', 'en' => 'Intuitive admin panel, comprehensive tools and native DDoS protection.'],

    // ══════════════════════════════════════════════════════════════════════
    // INDEX + SHOP — Offres
    // ══════════════════════════════════════════════════════════════════════
    'offers.title'        => ['fr' => 'Nos Offres',        'en' => 'Our Plans'],
    'offers.subtitle'     => ['fr' => 'Hébergements gratuits et premium pour chaque type de projet.', 'en' => 'Free and premium hosting for every type of project.'],
    'offers.tab.all'      => ['fr' => 'Tous',              'en' => 'All'],
    'offers.tab.cat_subtitle' => ['fr' => 'Toutes les offres disponibles, du moins cher au plus cher.', 'en' => 'All available plans, cheapest to most expensive.'],

    // Tiers
    'tier.free.title'     => ['fr' => 'Offres Gratuites',  'en' => 'Free Plans'],
    'tier.free.subtitle'  => ['fr' => 'Déployez gratuitement vos projets avec puissance et sécurité.', 'en' => 'Deploy your projects for free with power and security.'],
    'tier.free.label'     => ['fr' => 'Gratuit',           'en' => 'Free'],
    'tier.basic.title'    => ['fr' => 'Offres Basic',      'en' => 'Basic Plans'],
    'tier.basic.subtitle' => ['fr' => 'Un premier pas vers plus de performances, à petit prix.', 'en' => 'A first step toward more performance, at a low price.'],
    'tier.basic.label'    => ['fr' => 'Basic',             'en' => 'Basic'],
    'tier.medium.title'   => ['fr' => 'Offres Medium',     'en' => 'Medium Plans'],
    'tier.medium.subtitle'=> ['fr' => 'Plus de puissance pour vos projets en croissance.', 'en' => 'More power for your growing projects.'],
    'tier.medium.label'   => ['fr' => 'Medium',            'en' => 'Medium'],
    'tier.premium.title'  => ['fr' => 'Offres Premium',    'en' => 'Premium Plans'],
    'tier.premium.subtitle'=> ['fr' => 'Ressources 100% dédiées, support prioritaire et puissance sans limite.', 'en' => '100% dedicated resources, priority support and unlimited power.'],
    'tier.premium.label'  => ['fr' => 'Premium',           'en' => 'Premium'],

    // Boutons offres
    'btn.deploy'          => ['fr' => 'Déployer maintenant', 'en' => 'Deploy now'],
    'btn.host_site'       => ['fr' => 'Héberger mon site',  'en' => 'Host my site'],
    'btn.buy'             => ['fr' => 'Acheter',            'en' => 'Buy'],
    'btn.login_to_buy'    => ['fr' => 'Se connecter',       'en' => 'Log in'],
    'btn.popular'         => ['fr' => 'POPULAIRE',          'en' => 'POPULAR'],
    'offers.period.free'  => ['fr' => '/ à vie',            'en' => '/ lifetime'],
    'offers.period.month' => ['fr' => '/ mois',             'en' => '/ month'],

    // Noms & descriptions des offres — Minecraft
    'offer.mc_free.name'    => ['fr' => 'Minecraft Free',    'en' => 'Minecraft Free'],
    'offer.mc_free.desc'    => ['fr' => 'Hébergement Minecraft fluide pour jouer en communauté ou tester vos configurations.', 'en' => 'Smooth Minecraft hosting to play with friends or test your setups.'],
    'offer.mc_basic.name'   => ['fr' => 'Minecraft Basic',   'en' => 'Minecraft Basic'],
    'offer.mc_basic.desc'   => ['fr' => 'Parfait pour un petit serveur entre amis avec quelques mods légers.', 'en' => 'Perfect for a small server between friends with a few light mods.'],
    'offer.mc_medium.name'  => ['fr' => 'Minecraft Medium',  'en' => 'Minecraft Medium'],
    'offer.mc_medium.desc'  => ['fr' => 'Un serveur Minecraft confortable pour une communauté active avec plugins.', 'en' => 'A comfortable Minecraft server for an active community with plugins.'],
    'offer.mc_premium.name' => ['fr' => 'Minecraft Pro',     'en' => 'Minecraft Pro'],
    'offer.mc_premium.desc' => ['fr' => 'Pour les architectures moddées exigeantes ou serveurs à fort trafic. Zéro latence.', 'en' => 'For demanding modded setups or high-traffic servers. Zero latency.'],

    // Hytale
    'offer.hy_free.name'    => ['fr' => 'Hytale Free',       'en' => 'Hytale Free'],
    'offer.hy_free.desc'    => ['fr' => 'Explorez Orbis et lancez votre premier serveur de test Hytale gratuitement.', 'en' => 'Explore Orbis and launch your first Hytale test server for free.'],
    'offer.hy_basic.name'   => ['fr' => 'Hytale Basic',      'en' => 'Hytale Basic'],
    'offer.hy_basic.desc'   => ['fr' => 'Lancez un petit serveur Hytale privé avec des ressources dédiées stables.', 'en' => 'Launch a small private Hytale server with stable dedicated resources.'],
    'offer.hy_medium.name'  => ['fr' => 'Hytale Medium',     'en' => 'Hytale Medium'],
    'offer.hy_medium.desc'  => ['fr' => 'Hébergez un serveur Hytale communautaire stable avec mods et plugins.', 'en' => 'Host a stable Hytale community server with mods and plugins.'],
    'offer.hy_premium.name' => ['fr' => 'Hytale Pro',        'en' => 'Hytale Pro'],
    'offer.hy_premium.desc' => ['fr' => 'Idéal pour les grands serveurs communautaires Hytale, mods complexes et scripts lourds.', 'en' => 'Ideal for large Hytale community servers, complex mods and heavy scripts.'],

    // PHP / Web
    'offer.php_free.name'    => ['fr' => 'Web PHP Free',     'en' => 'Web PHP Free'],
    'offer.php_free.desc'    => ['fr' => 'Mettez vos scripts et sites web en ligne instantanément avec un environnement PHP complet.', 'en' => 'Put your scripts and websites online instantly with a full PHP environment.'],
    'offer.php_basic.name'   => ['fr' => 'PHP Basic',        'en' => 'PHP Basic'],
    'offer.php_basic.desc'   => ['fr' => 'Hébergez un site vitrine ou un blog sans vous ruiner.', 'en' => 'Host a showcase website or blog without breaking the bank.'],
    'offer.php_medium.name'  => ['fr' => 'PHP Medium',       'en' => 'PHP Medium'],
    'offer.php_medium.desc'  => ['fr' => 'Un site WordPress, boutique ou application PHP avec de bonnes performances.', 'en' => 'A WordPress site, shop or PHP application with solid performance.'],
    'offer.php_premium.name' => ['fr' => 'PHP Pro',          'en' => 'PHP Pro'],
    'offer.php_premium.desc' => ['fr' => 'Solution haut de gamme idéale pour vos boutiques e-commerce ou APIs complexes.', 'en' => 'Top-of-the-range solution ideal for e-commerce shops or complex APIs.'],

    // Python
    'offer.py_free.name'    => ['fr' => 'Python Free',       'en' => 'Python Free'],
    'offer.py_free.desc'    => ['fr' => 'Idéal pour héberger vos bots Discord, apps asynchrones ou APIs Python.', 'en' => 'Ideal for hosting your Discord bots, async apps or Python APIs.'],
    'offer.py_basic.name'   => ['fr' => 'Python Basic',      'en' => 'Python Basic'],
    'offer.py_basic.desc'   => ['fr' => 'Un bot Discord ou une petite API Python avec des ressources dédiées.', 'en' => 'A Discord bot or small Python API with dedicated resources.'],
    'offer.py_medium.name'  => ['fr' => 'Python Medium',     'en' => 'Python Medium'],
    'offer.py_medium.desc'  => ['fr' => 'Applications Python de taille moyenne, bots avancés ou API publiques.', 'en' => 'Medium-sized Python applications, advanced bots or public APIs.'],
    'offer.py_premium.name' => ['fr' => 'Python Pro',        'en' => 'Python Pro'],
    'offer.py_premium.desc' => ['fr' => 'Réactivité maximale pour vos applications Python les plus exigeantes.', 'en' => 'Maximum responsiveness for your most demanding Python applications.'],

    // Node.js
    'offer.node_free.name'    => ['fr' => 'Node.js Free',    'en' => 'Node.js Free'],
    'offer.node_free.desc'    => ['fr' => 'Idéal pour héberger vos bots Discord, apps asynchrones ou APIs JavaScript.', 'en' => 'Ideal for hosting your Discord bots, async apps or JavaScript APIs.'],
    'offer.node_basic.name'   => ['fr' => 'NodeJS Basic',    'en' => 'NodeJS Basic'],
    'offer.node_basic.desc'   => ['fr' => 'Un bot Discord ou une petite API Node.js avec des ressources dédiées.', 'en' => 'A Discord bot or small Node.js API with dedicated resources.'],
    'offer.node_medium.name'  => ['fr' => 'NodeJS Medium',   'en' => 'NodeJS Medium'],
    'offer.node_medium.desc'  => ['fr' => 'Applications Node.js de taille moyenne, bots avancés ou API publiques.', 'en' => 'Medium-sized Node.js applications, advanced bots or public APIs.'],
    'offer.node_premium.name' => ['fr' => 'NodeJS Pro',      'en' => 'NodeJS Pro'],
    'offer.node_premium.desc' => ['fr' => 'Réactivité maximale pour vos applications Node.js les plus exigeantes.', 'en' => 'Maximum responsiveness for your most demanding Node.js applications.'],

    // FiveM
    'offer.fivem_free.name'    => ['fr' => 'FiveM Free',      'en' => 'FiveM Free'],
    'offer.fivem_free.desc'    => ['fr' => 'Lancez votre serveur GTA RP FiveM gratuitement avec txAdmin inclus.', 'en' => 'Launch your FiveM GTA RP server for free with txAdmin included.'],
    'offer.fivem_basic.name'   => ['fr' => 'FiveM Basic',     'en' => 'FiveM Basic'],
    'offer.fivem_basic.desc'   => ['fr' => 'Parfait pour un petit serveur FiveM privé entre amis.', 'en' => 'Perfect for a small private FiveM server between friends.'],
    'offer.fivem_medium.name'  => ['fr' => 'FiveM Medium',    'en' => 'FiveM Medium'],
    'offer.fivem_medium.desc'  => ['fr' => 'Un serveur FiveM communautaire stable avec ressources dédiées.', 'en' => 'A stable FiveM community server with dedicated resources.'],
    'offer.fivem_premium.name' => ['fr' => 'FiveM Pro',       'en' => 'FiveM Pro'],
    'offer.fivem_premium.desc' => ['fr' => 'Pour les grands serveurs RP FiveM avec nombreux scripts et joueurs.', 'en' => 'For large FiveM RP servers with many scripts and players.'],

    // Java
    'offer.java_free.name'    => ['fr' => 'Java Free',       'en' => 'Java Free'],
    'offer.java_free.desc'    => ['fr' => 'Idéal pour héberger vos bots Discord, apps asynchrones ou APIs en Java.', 'en' => 'Ideal for hosting your Discord bots, async apps or Java APIs.'],
    'offer.java_basic.name'   => ['fr' => 'Java Basic',      'en' => 'Java Basic'],
    'offer.java_basic.desc'   => ['fr' => 'Un bot Discord ou une petite app Java avec des ressources dédiées.', 'en' => 'A Discord bot or small Java app with dedicated resources.'],
    'offer.java_medium.name'  => ['fr' => 'Java Medium',     'en' => 'Java Medium'],
    'offer.java_medium.desc'  => ['fr' => 'Applications Java de taille moyenne, microservices ou APIs robustes.', 'en' => 'Medium-sized Java applications, microservices or robust APIs.'],
    'offer.java_premium.name' => ['fr' => 'Java Pro',        'en' => 'Java Pro'],
    'offer.java_premium.desc' => ['fr' => 'Réactivité maximale pour vos applications Java les plus exigeantes.', 'en' => 'Maximum responsiveness for your most demanding Java applications.'],

    // Features des offres (textes communs)
    'feat.ssl_free'       => ['fr' => 'SSL Gratuit',          'en' => 'Free SSL'],
    'feat.ssl_le'         => ['fr' => "SSL Let's Encrypt",    'en' => "Let's Encrypt SSL"],
    'feat.ddos'           => ['fr' => 'Protection DDoS',      'en' => 'DDoS Protection'],
    'feat.mysql_1'        => ['fr' => '1 Base MySQL',         'en' => '1 MySQL Database'],
    'feat.mysql_unlim'    => ['fr' => 'Base MySQL illimitée', 'en' => 'Unlimited MySQL'],
    'feat.fast_install'   => ['fr' => 'Installation rapide',  'en' => 'Quick setup'],
    'feat.git_auto'       => ['fr' => 'Auto-update Git',      'en' => 'Auto-update Git'],
    'feat.support247'     => ['fr' => 'Support 24/7',         'en' => '24/7 Support'],
    'feat.priority_sup'   => ['fr' => 'Support Prioritaire',  'en' => 'Priority Support'],
    'feat.php8'           => ['fr' => 'PHP 8.x ultra optimisé', 'en' => 'Ultra-optimised PHP 8.x'],
    'feat.cron'           => ['fr' => 'Tâches Cron avancées', 'en' => 'Advanced Cron Jobs'],

    // ══════════════════════════════════════════════════════════════════════
    // LOGIN PAGE
    // ══════════════════════════════════════════════════════════════════════
    'login.title'         => ['fr' => 'Connexion | OrinHeberge',  'en' => 'Login | OrinHeberge'],
    'login.subtitle'      => ['fr' => 'Connexion à votre espace', 'en' => 'Sign in to your account'],
    'login.email'         => ['fr' => 'Email',                    'en' => 'Email'],
    'login.password'      => ['fr' => 'Mot de passe',             'en' => 'Password'],
    'login.forgot'        => ['fr' => 'Mot de passe oublié ?',    'en' => 'Forgot password?'],
    'login.submit'        => ['fr' => 'Se Connecter',             'en' => 'Log in'],
    'login.no_account'    => ['fr' => 'Pas encore de compte ?',   'en' => 'No account yet?'],
    'login.create'        => ['fr' => 'Créer un compte',          'en' => 'Create an account'],
    'login.back'          => ['fr' => 'Retour à l\'accueil',      'en' => 'Back to home'],
    'login.error'         => ['fr' => 'Identifiants incorrects.',  'en' => 'Invalid credentials.'],
    'login.db_error'      => ['fr' => 'Erreur de connexion à la base de données.', 'en' => 'Database connection error.'],

    // ══════════════════════════════════════════════════════════════════════
    // REGISTER PAGE
    // ══════════════════════════════════════════════════════════════════════
    'register.title'      => ['fr' => 'Inscription | OrinHeberge', 'en' => 'Sign up | OrinHeberge'],
    'register.subtitle'   => ['fr' => 'Créez votre compte OrinHeberge', 'en' => 'Create your OrinHeberge account'],
    'register.firstname'  => ['fr' => 'Prénom',                    'en' => 'First name'],
    'register.lastname'   => ['fr' => 'Nom',                       'en' => 'Last name'],
    'register.email'      => ['fr' => 'Email',                     'en' => 'Email'],
    'register.password'   => ['fr' => 'Mot de passe',              'en' => 'Password'],
    'register.submit'     => ['fr' => 'Créer mon compte',          'en' => 'Create my account'],
    'register.have_account'=> ['fr' => 'Déjà un compte ?',         'en' => 'Already have an account?'],
    'register.login_link' => ['fr' => 'Se connecter',              'en' => 'Sign in'],
    'register.back'       => ['fr' => 'Retour à l\'accueil',       'en' => 'Back to home'],
    'register.success'    => ['fr' => 'Inscription réussie ! <a href=\'/login/\' class=\'font-bold underline\'>Connectez-vous ici</a>', 'en' => 'Registration successful! <a href=\'/login/\' class=\'font-bold underline\'>Log in here</a>'],
    'register.error_dup'  => ['fr' => 'Erreur : Cet email est déjà utilisé ou le serveur est indisponible.', 'en' => 'Error: This email is already in use or the server is unavailable.'],

    // ══════════════════════════════════════════════════════════════════════
    // PROFILE PAGE
    // ══════════════════════════════════════════════════════════════════════
    'profil.title'        => ['fr' => 'Mon Profil | OrinHeberge', 'en' => 'My Profile | OrinHeberge'],
    'profil.heading'      => ['fr' => 'Mon Profil',               'en' => 'My Profile'],
    'profil.pseudo'       => ['fr' => 'Pseudo (Affichage site)',  'en' => 'Username (display name)'],
    'profil.firstname'    => ['fr' => 'Prénom',                   'en' => 'First name'],
    'profil.lastname'     => ['fr' => 'Nom',                      'en' => 'Last name'],
    'profil.change_pw'    => ['fr' => 'Modifier le mot de passe (laisser vide si inchangé)', 'en' => 'Change password (leave blank to keep current)'],
    'profil.save'         => ['fr' => 'Sauvegarder les modifications', 'en' => 'Save changes'],
    'profil.success'      => ['fr' => 'Profil mis à jour avec succès !', 'en' => 'Profile updated successfully!'],
    'profil.db_error'     => ['fr' => 'Erreur de connexion à la base de données.', 'en' => 'Database connection error.'],

    // ══════════════════════════════════════════════════════════════════════
    // MY SERVERS PAGE
    // ══════════════════════════════════════════════════════════════════════
    'servers.title'       => ['fr' => 'OrinHeberge | Mes serveurs', 'en' => 'OrinHeberge | My Servers'],
    'servers.heading'     => ['fr' => '🚀 Mes serveurs',            'en' => '🚀 My Servers'],
    'servers.manage'      => ['fr' => 'Gérer',                      'en' => 'Manage'],
    'servers.manage_full' => ['fr' => 'Gérer les détails',          'en' => 'Manage details'],
    'servers.no_response' => ['fr' => 'Aucune réponse',             'en' => 'No response'],

    // ══════════════════════════════════════════════════════════════════════
    // SUPPORT PAGE
    // ══════════════════════════════════════════════════════════════════════
    'support.title'         => ['fr' => 'OrinHeberge | Support & Réclamations', 'en' => 'OrinHeberge | Support & Claims'],
    'support.open_ticket'   => ['fr' => 'Ouvrir un ticket de support',           'en' => 'Open a support ticket'],
    'support.open_desc'     => ['fr' => 'Un problème avec une machine ? Un bug sur le site ? Envoyez-nous les détails.', 'en' => 'Issue with a server? Bug on the site? Send us the details.'],
    'support.type_label'    => ['fr' => 'Type de demande *',       'en' => 'Request type *'],
    'support.subject_label' => ['fr' => 'Sujet du ticket *',       'en' => 'Ticket subject *'],
    'support.send'          => ['fr' => 'Envoyer le ticket',        'en' => 'Send ticket'],
    'support.admin_access'  => ['fr' => 'Accéder aux réclamations', 'en' => 'Access claims'],
    'support.discord_title' => ['fr' => 'Support Discord',          'en' => 'Discord Support'],
    'support.no_ticket'     => ['fr' => 'Aucun ticket pour le moment.', 'en' => 'No tickets yet.'],
    'support.ticket_type'   => ['fr' => 'Type :',                   'en' => 'Type:'],
    'support.error_fields'  => ['fr' => '⚠️ Veuillez remplir tous les champs obligatoires.', 'en' => '⚠️ Please fill in all required fields.'],
    'support.success_send'  => ['fr' => '🚀 Votre ticket a été soumis avec succès ! Notre équipe va l\'analyser.', 'en' => '🚀 Your ticket has been submitted! Our team will review it.'],
    'support.error_send'    => ['fr' => '❌ Une erreur est survenue lors de l\'envoi.', 'en' => '❌ An error occurred while sending.'],

    // ══════════════════════════════════════════════════════════════════════
    // CGU / LEGAL / PRIVACY (titres de page)
    // ══════════════════════════════════════════════════════════════════════
    'cgu.title'           => ['fr' => "Conditions Générales d'Utilisation | OrinHeberge", 'en' => 'Terms of Service | OrinHeberge'],
    'legal.title'         => ['fr' => 'Mentions Légales | OrinHeberge', 'en' => 'Legal Notice | OrinHeberge'],
    'privacy.title'       => ['fr' => 'Politique de Confidentialité | OrinHeberge', 'en' => 'Privacy Policy | OrinHeberge'],

    // ══════════════════════════════════════════════════════════════════════
    // SHOP PAGE (page /shop/ propre)
    // ══════════════════════════════════════════════════════════════════════
    'shop.title'          => ['fr' => 'OrinHeberge | Boutique & Offres', 'en' => 'OrinHeberge | Shop & Plans'],
    'shop.meta_desc'      => ['fr' => 'Boutique OrinHeberge - Choisissez votre hébergement Minecraft, PHP, Node.js ou Python.', 'en' => 'OrinHeberge shop - Choose your Minecraft, PHP, Node.js or Python hosting.'],

    // ══════════════════════════════════════════════════════════════════════
    // COMMON UI
    // ══════════════════════════════════════════════════════════════════════
    'ui.back_home'        => ['fr' => 'Retour à l\'accueil', 'en' => 'Back to home'],
    'ui.loading'          => ['fr' => 'Chargement...',       'en' => 'Loading...'],
	// ══════════════════════════════════════════════════════════════════════
    // PAGE DE STATUT (status_index.php)
    // ══════════════════════════════════════════════════════════════════════
    'status.nav'          => ['fr' => 'Statut',            'en' => 'Status'],
    'status.title'        => ['fr' => 'Statut des Services | OrinHeberge', 'en' => 'Service Status | OrinHeberge'],
    'status.heading'      => ['fr' => 'Statut des',        'en' => 'Services'],
    'status.heading2'     => ['fr' => 'Services',          'en' => 'Status'],
    'status.subtitle'     => ['fr' => 'Disponibilité en temps réel et historique de nos infrastructures sur les 90 derniers jours.', 'en' => 'Real-time availability and uptime history of our infrastructures over the last 90 days.'],
    'status.operational'  => ['fr' => 'Opérationnel',      'en' => 'Operational'],
    'status.disruption'   => ['fr' => 'Perturbation',      'en' => 'Disruption'],
    'status.log_ok'       => ['fr' => 'Aucun incident',    'en' => 'No incidents'],
    'status.log_ko'       => ['fr' => 'Perturbation détectée', 'en' => 'Disruption detected'],
    'status.days_ago'     => ['fr' => 'Il y a 90 jours',   'en' => '90 days ago'],
    'status.today'        => ['fr' => 'Aujourd\'hui',       'en' => 'Today'],
    'status.uptime_pct'   => ['fr' => '% de disponibilité', 'en' => '% uptime'],
	// Mois (Versions courtes)
    'month.jan'           => ['fr' => 'Jan',               'en' => 'Jan'],
    'month.feb'           => ['fr' => 'Fév',               'en' => 'Feb'],
    'month.mar'           => ['fr' => 'Mar',               'en' => 'Mar'],
    'month.apr'           => ['fr' => 'Avr',               'en' => 'Apr'],
    'month.may'           => ['fr' => 'Mai',               'en' => 'May'],
    'month.jun'           => ['fr' => 'Juin',              'en' => 'Jun'],
    'month.jul'           => ['fr' => 'Juil',              'en' => 'Jul'],
    'month.aug'           => ['fr' => 'Août',              'en' => 'Aug'],
    'month.sep'           => ['fr' => 'Sep',               'en' => 'Sep'],
    'month.oct'           => ['fr' => 'Oct',               'en' => 'Oct'],
    'month.nov'           => ['fr' => 'Nov',               'en' => 'Nov'],
    'month.dec'           => ['fr' => 'Déc',               'en' => 'Dec'],
// ══════════════════════════════════════════════════════════════════════
    // COOKIES (inc/cookie.php)
    // ══════════════════════════════════════════════════════════════════════
    'cookie.title'       => ['fr' => 'Gestion des cookies', 'en' => 'Cookie Management'],
    'cookie.text'        => ['fr' => 'OrinHeberge utilise des cookies essentiels pour assurer le bon fonctionnement du site. En poursuivant, vous acceptez notre utilisation des cookies conformément à nos ', 'en' => 'OrinHeberge uses essential cookies to ensure proper site operation. By continuing, you accept our use of cookies in accordance with our '],
    'cookie.text_and'    => ['fr' => ' et ', 'en' => ' and '],
    'cookie.ml'          => ['fr' => 'Mentions Légales', 'en' => 'Legal Notice'],
    'cookie.cgu'         => ['fr' => 'CGU', 'en' => 'Terms of Service'],
    'cookie.pp'          => ['fr' => 'Politique de Confidentialité', 'en' => 'Privacy Policy'],
    'cookie.accept'      => ['fr' => 'Tout accepter', 'en' => 'Accept all'],
    'cookie.deny'        => ['fr' => 'Refuser', 'en' => 'Decline'],
];

/**
 * Retourne le texte traduit pour la clé donnée.
 * Fallback : français → clé brute si introuvable.
 */
function t(string $key): string {
    global $translations, $lang;
    return $translations[$key][$lang]
        ?? $translations[$key]['fr']
        ?? $key;
}

/**
 * Comme t() mais sans htmlspecialchars — pour les contenus HTML (attributs, HTML injecté).
 * À utiliser uniquement quand le contenu est de confiance et contient du vrai HTML.
 */
function th(string $key): string {
    global $translations, $lang;
    return $translations[$key][$lang]
        ?? $translations[$key]['fr']
        ?? $key;
}

// ══════════════════════════════════════════════════════════════════════
// PAGES LÉGALES (CGU / Mentions / Confidentialité)
// ══════════════════════════════════════════════════════════════════════
$translations['legal.back']               = ['fr' => 'Retour',                                        'en' => 'Back'];
$translations['cgu.heading']              = ['fr' => "Conditions Générales d'Utilisation",            'en' => 'Terms of Service'];
$translations['cgu.subtitle']             = ['fr' => "Règles d'utilisation de nos services d'hébergement", 'en' => 'Rules for using our hosting services'];
$translations['cgu.s1.title']             = ['fr' => '1. Acceptation des conditions',                 'en' => '1. Acceptance of terms'];
$translations['cgu.s1.text']              = ['fr' => "L'accès et l'utilisation des services d'OrinHeberge impliquent l'acceptation pleine et entière des présentes Conditions Générales d'Utilisation (CGU).", 'en' => 'Access to and use of OrinHeberge services implies full acceptance of these Terms of Service.'];
$translations['cgu.s2.title']             = ['fr' => '2. Description des Services',                   'en' => '2. Description of Services'];
$translations['cgu.s2.text']              = ['fr' => "OrinHeberge fournit des solutions d'hébergement web et de serveurs. Les utilisateurs sont responsables du contenu stocké et partagé sur leurs instances.", 'en' => 'OrinHeberge provides web and server hosting solutions. Users are responsible for the content stored and shared on their instances.'];
$translations['cgu.s3.title']             = ['fr' => '3. Activités interdites',                       'en' => '3. Prohibited activities'];
$translations['cgu.s3.text']              = ['fr' => "Sont strictement interdites sur nos infrastructures les activités illégales, les attaques DDoS, l'hébergement de scripts malveillants (phishing, malwares), ou toute utilisation abusive surchargeant volontairement les serveurs partagés.", 'en' => 'Strictly prohibited on our infrastructure: illegal activities, DDoS attacks, hosting malicious scripts (phishing, malware), or any abusive use that intentionally overloads shared servers.'];
$translations['cgu.s4.title']             = ['fr' => '4. Suspension et résiliation',                  'en' => '4. Suspension and termination'];
$translations['cgu.s4.text']              = ['fr' => "OrinHeberge se réserve le droit de suspendre temporairement ou définitivement l'accès aux services d'un utilisateur en cas de violation des présentes clauses ou de non-paiement des renouvellements.", 'en' => 'OrinHeberge reserves the right to temporarily or permanently suspend a user\'s access in case of breach of these terms or non-payment of renewals.'];

$translations['legal.heading']            = ['fr' => 'Mentions Légales',                              'en' => 'Legal Notice'];
$translations['legal.subtitle']           = ['fr' => "Informations obligatoires concernant l'éditeur du site", 'en' => 'Mandatory information about the site publisher'];
$translations['legal.s1.title']           = ['fr' => '1. Édition du site',                            'en' => '1. Site publisher'];
$translations['legal.s1.text']            = ['fr' => 'Le site internet <strong>OrinHeberge</strong> est édité par l\'infrastructure OrinStone / DeepStone.', 'en' => 'The <strong>OrinHeberge</strong> website is published by the OrinStone / DeepStone infrastructure.'];
$translations['legal.s1.pub']             = ['fr' => 'Responsable de la publication :',               'en' => 'Publication manager:'];
$translations['legal.s1.contact']         = ['fr' => 'Contact Email :',                               'en' => 'Email contact:'];
$translations['legal.s2.title']           = ['fr' => '2. Hébergement',                                'en' => '2. Hosting'];
$translations['legal.s2.text']            = ['fr' => "Ce site et l'ensemble des infrastructures d'hébergement associées sont hébergés par :", 'en' => 'This site and all associated hosting infrastructure are hosted by:'];
$translations['legal.s2.host']            = ['fr' => 'Hébergeur du site :',                           'en' => 'Site host:'];
$translations['legal.s2.web']             = ['fr' => 'Site Web :',                                    'en' => 'Website:'];
$translations['legal.s3.title']           = ['fr' => '3. Propriété intellectuelle',                   'en' => '3. Intellectual property'];
$translations['legal.s3.text']            = ['fr' => "Tous les éléments graphiques, marques, logos (notamment OrinHeberge et Orinstone Studio) ainsi que les codes sources de la plateforme sont la propriété exclusive de leurs auteurs respectifs. Toute reproduction ou distribution non autorisée est strictement interdite.", 'en' => 'All graphic elements, trademarks, logos (including OrinHeberge and Orinstone Studio) and the platform source code are the exclusive property of their respective authors. Any unauthorised reproduction or distribution is strictly prohibited.'];

$translations['privacy.heading']          = ['fr' => 'Politique de Confidentialité',                  'en' => 'Privacy Policy'];
$translations['privacy.subtitle']         = ['fr' => 'Comment nous protégeons et gérons vos données personnelles', 'en' => 'How we protect and manage your personal data'];
$translations['privacy.s1.title']         = ['fr' => '1. Collecte des données',                       'en' => '1. Data collection'];
$translations['privacy.s1.text']          = ['fr' => "Nous collectons uniquement les informations nécessaires au bon fonctionnement de votre compte : nom, prénom, pseudo, adresse email et adresse IP lors des connexions à des fins de sécurité.", 'en' => 'We collect only the information necessary for your account to function: surname, first name, username, email address and IP address at login for security purposes.'];
$translations['privacy.s2.title']         = ['fr' => '2. Utilisation et Partage',                     'en' => '2. Use and sharing'];
$translations['privacy.s2.text']          = ['fr' => "Vos données personnelles restent strictement internes à <strong>OrinHeberge</strong>. Elles ne sont ni vendues, ni partagées, ni échangées avec des entités tierces commerciales.", 'en' => 'Your personal data remains strictly internal to <strong>OrinHeberge</strong>. It is not sold, shared or exchanged with commercial third parties.'];
$translations['privacy.s3.title']         = ['fr' => '3. Sécurité de vos informations',               'en' => '3. Security of your information'];
$translations['privacy.s3.text']          = ['fr' => "Vos mots de passe sont hachés de manière sécurisée via l'algorithme fort BCRYPT/DEFAULT en base de données. Les sessions et jetons de réinitialisation sont limités dans le temps pour prévenir les intrusions.", 'en' => 'Your passwords are securely hashed using the strong BCRYPT/DEFAULT algorithm in the database. Sessions and reset tokens are time-limited to prevent intrusions.'];
$translations['privacy.s4.title']         = ['fr' => '4. Vos droits (RGPD)',                          'en' => '4. Your rights (GDPR)'];
$translations['privacy.s4.text']          = ['fr' => "Conformément aux réglementations en vigueur, vous disposez d'un droit d'accès, de modification et de suppression de vos données. Vous pouvez effectuer ces ajustements depuis votre espace profil ou en contactant notre support.", 'en' => 'In accordance with current regulations, you have the right to access, modify and delete your data. You can make these adjustments from your profile or by contacting our support.'];

// ══════════════════════════════════════════════════════════════════════
// MOT DE PASSE OUBLIÉ / RÉINITIALISATION
// ══════════════════════════════════════════════════════════════════════
$translations['forgot.title']             = ['fr' => 'Mot de passe oublié | OrinHeberge',             'en' => 'Forgot password | OrinHeberge'];
$translations['forgot.heading']           = ['fr' => 'Mot de passe oublié ?',                         'en' => 'Forgot your password?'];
$translations['forgot.subtitle']          = ['fr' => 'Entrez votre adresse email pour recevoir un lien de réinitialisation.', 'en' => 'Enter your email address to receive a reset link.'];
$translations['forgot.email']             = ['fr' => 'Adresse email',                                 'en' => 'Email address'];
$translations['forgot.submit']            = ['fr' => 'Envoyer le lien',                               'en' => 'Send link'];
$translations['forgot.back']              = ['fr' => 'Retour à la connexion',                         'en' => 'Back to login'];
$translations['forgot.err_empty']         = ['fr' => 'Entrez une adresse email.',                     'en' => 'Please enter an email address.'];
$translations['forgot.msg_generic']       = ['fr' => 'Si un compte existe pour cette adresse, vous recevrez un email contenant un lien de réinitialisation.', 'en' => 'If an account exists for this address, you will receive an email with a reset link.'];
$translations['forgot.msg_sent']          = ['fr' => 'Un email a été envoyé. Vérifiez votre boîte de réception.', 'en' => 'An email has been sent. Check your inbox.'];
$translations['forgot.err_server']        = ['fr' => 'Erreur serveur, réessayez plus tard.',          'en' => 'Server error, please try again later.'];

$translations['reset.title']              = ['fr' => 'Réinitialiser le mot de passe | OrinHeberge',   'en' => 'Reset password | OrinHeberge'];
$translations['reset.heading']            = ['fr' => 'Nouveau mot de passe',                          'en' => 'New password'];
$translations['reset.new_pw']             = ['fr' => 'Nouveau mot de passe',                          'en' => 'New password'];
$translations['reset.confirm_pw']         = ['fr' => 'Confirmer le mot de passe',                    'en' => 'Confirm password'];
$translations['reset.submit']             = ['fr' => 'Réinitialiser',                                 'en' => 'Reset password'];
$translations['reset.success']            = ['fr' => 'Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.', 'en' => 'Password successfully reset. You can now log in.'];
$translations['reset.err_token']          = ['fr' => 'Token invalide.',                               'en' => 'Invalid token.'];
$translations['reset.err_used']           = ['fr' => 'Ce lien a déjà été utilisé.',                  'en' => 'This link has already been used.'];
$translations['reset.err_expired']        = ['fr' => 'Le lien a expiré.',                             'en' => 'The link has expired.'];
$translations['reset.err_missing']        = ['fr' => 'Token manquant.',                               'en' => 'Missing token.'];
$translations['reset.err_length']         = ['fr' => 'Le mot de passe doit contenir au moins 8 caractères.', 'en' => 'Password must be at least 8 characters.'];
$translations['reset.err_match']          = ['fr' => 'Les mots de passe ne correspondent pas.',       'en' => 'Passwords do not match.'];

// ══════════════════════════════════════════════════════════════════════
// CLIENT / MES SERVEURS
// ══════════════════════════════════════════════════════════════════════
$translations['servers.no_server']        = ['fr' => 'Aucun serveur pour le moment.',                 'en' => 'No servers yet.'];
$translations['servers.renew']            = ['fr' => 'Renouveler',                                    'en' => 'Renew'];
$translations['servers.status.active']    = ['fr' => 'Actif',                                         'en' => 'Active'];
$translations['servers.status.suspended'] = ['fr' => 'Suspendu',                                      'en' => 'Suspended'];
$translations['servers.open_panel']       = ['fr' => 'Ouvrir dans le panel',                          'en' => 'Open in panel'];
$translations['servers.expires']          = ['fr' => 'Expire le',                                     'en' => 'Expires on'];
$translations['servers.order_new']        = ['fr' => 'Commander un serveur',                          'en' => 'Order a server'];

// ══════════════════════════════════════════════════════════════════════
// SHOP / COMMANDE / SUCCÈS
// ══════════════════════════════════════════════════════════════════════
$translations['order.title']              = ['fr' => 'OrinHeberge | Commande',                        'en' => 'OrinHeberge | Order'];
$translations['order.success.title']      = ['fr' => 'OrinHeberge | Commande confirmée',              'en' => 'OrinHeberge | Order confirmed'];
$translations['order.confirmed']          = ['fr' => 'Commande confirmée !',                          'en' => 'Order confirmed!'];
$translations['order.confirmed_sub']      = ['fr' => 'Paiement validé — votre instance sera activée très prochainement.', 'en' => 'Payment validated — your instance will be activated very soon.'];
$translations['order.label_id']           = ['fr' => 'Commande :',                                    'en' => 'Order:'];
$translations['order.label_offer']        = ['fr' => 'Offre :',                                       'en' => 'Plan:'];
$translations['order.label_email']        = ['fr' => 'Email :',                                       'en' => 'Email:'];
$translations['order.label_pw']           = ['fr' => '🔑 Mot de passe panel :',                       'en' => '🔑 Panel password:'];
$translations['order.pw_note']            = ['fr' => 'Notez-le, il ne sera plus affiché.',            'en' => 'Note it down, it will not be shown again.'];
$translations['order.goto_servers']       = ['fr' => 'Voir mes serveurs',                             'en' => 'View my servers'];
$translations['order.goto_panel']         = ['fr' => 'Accéder au panel',                              'en' => 'Go to panel'];

$translations['free.success.title']       = ['fr' => 'OrinHeberge | Serveur créé',                    'en' => 'OrinHeberge | Server created'];
$translations['free.success.heading']     = ['fr' => '🚀 Votre serveur est en ligne !',               'en' => '🚀 Your server is online!'];
$translations['free.success.sub']         = ['fr' => 'Votre instance a été créée avec succès.',       'en' => 'Your instance was created successfully.'];

// ══════════════════════════════════════════════════════════════════════
// INFRASTRUCTURE
// ══════════════════════════════════════════════════════════════════════
$translations['infra.badge']              = ['fr' => 'Réseau & Puissance',                            'en' => 'Network & Power'];
$translations['infra.subtitle']           = ['fr' => "Découvrez l'infrastructure haute performance et redondante qui propulse l'ensemble de nos plateformes et services d'hébergement.", 'en' => 'Discover the high-performance, redundant infrastructure powering all our hosting platforms and services.'];
$translations['infra.hw.title']           = ['fr' => 'Matériel de pointe',                            'en' => 'Cutting-edge hardware'];
$translations['infra.hw.desc']            = ['fr' => "Des processeurs de dernière génération combinés à un stockage NVMe ultra-rapide pour garantir des temps de latence minimaux à vos applications.", 'en' => 'Latest-generation processors combined with ultra-fast NVMe storage to guarantee minimal latency for your applications.'];
$translations['infra.sec.title']          = ['fr' => 'Sécurité & DDoS',                               'en' => 'Security & DDoS'];
$translations['infra.sec.desc']           = ['fr' => "Une protection anti-DDoS robuste et active en continu, filtrant les attaques avant qu'elles n'atteignent et n'impactent vos services en ligne.", 'en' => 'Robust, continuously active DDoS protection filtering attacks before they reach and impact your online services.'];
$translations['infra.up.title']           = ['fr' => 'Disponibilité 99.9%',                           'en' => '99.9% Uptime'];
$translations['infra.up.desc']            = ['fr' => "Grâce à l'écosystème DeepStone, profitez d'une redondance réseau complète assurant la continuité de vos projets web et serveurs.", 'en' => 'Thanks to the DeepStone ecosystem, enjoy full network redundancy ensuring the continuity of your web projects and servers.'];

// ══════════════════════════════════════════════════════════════════════
// ADMIN TICKETS
// ══════════════════════════════════════════════════════════════════════
$translations['admin.title']              = ['fr' => 'Admin | Gestion des tickets',                   'en' => 'Admin | Ticket management'];
$translations['admin.heading']            = ['fr' => 'Gestion des tickets',                           'en' => 'Ticket management'];
$translations['admin.no_ticket']          = ['fr' => 'Aucun ticket ouvert.',                          'en' => 'No open tickets.'];
$translations['admin.reply']              = ['fr' => 'Répondre',                                      'en' => 'Reply'];
$translations['admin.close']             = ['fr' => 'Fermer le ticket',                               'en' => 'Close ticket'];
$translations['admin.status']             = ['fr' => 'Statut',                                        'en' => 'Status'];
$translations['admin.reply_saved']        = ['fr' => '✅ Réponse enregistrée et statut mis à jour !', 'en' => '✅ Reply saved and status updated!'];
$translations['admin.reply_empty']        = ['fr' => '⚠️ Le message de réponse ne peut pas être vide.','en' => '⚠️ The reply message cannot be empty.'];
$translations['admin.closed_ok']          = ['fr' => '🔒 Ticket marqué comme Fermé.',                 'en' => '🔒 Ticket marked as Closed.'];
