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
function loadDbTranslations(array &$translations): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    try {
        $pdo = new PDO('mysql:host=localhost;port=3306;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $stmt = $pdo->query('SELECT translation_key, fr, en FROM lang_boutique');
        while ($row = $stmt->fetch()) {
            if (!empty($row['translation_key'])) {
                $translations[$row['translation_key']] = [
                    'fr' => (string)($row['fr'] ?? ''),
                    'en' => (string)($row['en'] ?? ''),
                ];
            }
        }
    } catch (PDOException $e) {
        // Fallback silencieux si la table n'existe pas ou si la base est inaccessible.
    }
}

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
    'tier.mythic.title'    => ['fr' => 'Offres Mythic',    'en' => 'Mythic Plans'],
    'tier.mythic.subtitle' => ['fr' => 'Ressources illimitées, accès exclusif et performances ultimes.', 'en' => 'Unlimited resources, exclusive access and ultimate performance.'],
    'tier.mythic.label'    => ['fr' => 'Mythic',           'en' => 'Mythic'],

    // Boutons offres
    'btn.deploy'          => ['fr' => 'Déployer maintenant', 'en' => 'Deploy now'],
    'btn.host_site'       => ['fr' => 'Héberger mon site',  'en' => 'Host my site'],
    'btn.buy'             => ['fr' => 'Acheter',            'en' => 'Buy'],
    'btn.login_to_buy'    => ['fr' => 'Se connecter',       'en' => 'Log in'],
    'btn.popular'         => ['fr' => 'POPULAIRE',          'en' => 'POPULAR'],
    'offers.period.free'  => ['fr' => '/ à vie',            'en' => '/ lifetime'],
    'offers.period.month' => ['fr' => '/ mois',             'en' => '/ month'],

    // Noms & descriptions des offres — Minecraft

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
loadDbTranslations($translations);

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


$translations['admin.title']              = ['fr' => 'Admin | Gestion des tickets',                   'en' => 'Admin | Ticket management'];
$translations['admin.heading']            = ['fr' => 'Gestion des tickets',                           'en' => 'Ticket management'];
$translations['admin.no_ticket']          = ['fr' => 'Aucun ticket ouvert.',                          'en' => 'No open tickets.'];
$translations['admin.reply']              = ['fr' => 'Répondre',                                      'en' => 'Reply'];
$translations['admin.close']             = ['fr' => 'Fermer le ticket',                               'en' => 'Close ticket'];
$translations['admin.status']             = ['fr' => 'Statut',                                        'en' => 'Status'];
$translations['admin.reply_saved']        = ['fr' => '✅ Réponse enregistrée et statut mis à jour !', 'en' => '✅ Reply saved and status updated!'];
$translations['admin.reply_empty']        = ['fr' => '⚠️ Le message de réponse ne peut pas être vide.','en' => '⚠️ The reply message cannot be empty.'];
$translations['admin.closed_ok']          = ['fr' => '🔒 Ticket marqué comme Fermé.',                 'en' => '🔒 Ticket marked as Closed.'];

// ══════════════════════════════════════════════════════════════════════
// Status
// ══════════════════════════════════════════════════════════════════════
$translations['status.title']              = ['fr' => 'Statut des services | OrinHeberge',                    'en' => 'Service Status | OrinHeberge'];
$translations['status.heading']            = ['fr' => 'Statut de nos',                                        'en' => 'Status of our'];
$translations['status.heading2']           = ['fr' => 'services',                                             'en' => 'services'];
$translations['status.subtitle']           = ['fr' => 'Consultez en temps réel la disponibilité de l\'ensemble de notre infrastructure et de nos services.', 'en' => 'Check in real time the availability of our entire infrastructure and services.'];

// ── Statut global ─────────────────────────────────────────────
$translations['status.global_operational'] = ['fr' => 'Tous les systèmes sont opérationnels',                 'en' => 'All systems are operational'];
$translations['status.global_degraded']    = ['fr' => 'Certains services sont dégradés',                      'en' => 'Some services are degraded'];
$translations['status.global_maintenance'] = ['fr' => 'Maintenance en cours',                                 'en' => 'Maintenance in progress'];

// ── Compteurs et infos ────────────────────────────────────────
$translations['status.services_online']    = ['fr' => 'services en ligne',                                    'en' => 'services online'];
$translations['status.degraded']           = ['fr' => 'dégradé(s)',                                           'en' => 'degraded'];
$translations['status.last_check']         = ['fr' => 'Dernière vérification',                                'en' => 'Last check'];

// ── Statut des services individuels ──────────────────────────
$translations['status.operational']        = ['fr' => 'Opérationnel',                                         'en' => 'Operational'];
$translations['status.disruption']         = ['fr' => 'Perturbation',                                         'en' => 'Disruption'];
$translations['status.log_ok']             = ['fr' => '✅ Service en ligne',                                  'en' => '✅ Service online'];
$translations['status.log_ko']             = ['fr' => '❌ Service hors ligne',                                'en' => '❌ Service offline'];

// ── Timeline et statistiques ─────────────────────────────────
$translations['status.days']               = ['fr' => 'jours',                                                'en' => 'days'];
$translations['status.days_ago']           = ['fr' => 'jours en arrière',                                     'en' => 'days ago'];
$translations['status.uptime_pct']         = ['fr' => 'de disponibilité',                                     'en' => 'uptime'];
$translations['status.today']              = ['fr' => 'Aujourd\'hui',                                         'en' => 'Today'];

// ── Maintenances ─────────────────────────────────────────────
$translations['status.ongoing_maintenance']  = ['fr' => 'Maintenance en cours',                               'en' => 'Ongoing maintenance'];
$translations['status.upcoming_maintenance'] = ['fr' => 'Maintenances planifiées',                            'en' => 'Scheduled maintenance'];
$translations['status.recent_maintenance']   = ['fr' => 'Maintenances récentes',                              'en' => 'Recent maintenance'];

// ── Sections ─────────────────────────────────────────────────
$translations['status.services_status']      = ['fr' => 'État des services',                                  'en' => 'Service status'];

// ── Temps relatif ────────────────────────────────────────────
$translations['status.in_days']              = ['fr' => 'Dans',                                               'en' => 'In'];
$translations['status.in_hours']             = ['fr' => 'Dans',                                               'en' => 'In'];
$translations['status.soon']                 = ['fr' => 'Bientôt',                                            'en' => 'Soon'];
$translations['status.hours_remaining']      = ['fr' => 'h restantes',                                        'en' => 'h remaining'];
$translations['status.minutes_remaining']    = ['fr' => 'min restantes',                                      'en' => 'min remaining'];

// ── Sévérité ─────────────────────────────────────────────────
$translations['status.severity_info']        = ['fr' => 'Information',                                        'en' => 'Information'];
$translations['status.severity_warning']     = ['fr' => 'Attention',                                          'en' => 'Warning'];
$translations['status.severity_critical']    = ['fr' => 'Critique',                                           'en' => 'Critical'];

// ── Types de maintenance ─────────────────────────────────────
$translations['status.type_planned']         = ['fr' => 'Planifiée',                                          'en' => 'Planned'];
$translations['status.type_emergency']       = ['fr' => 'Urgence',                                            'en' => 'Emergency'];
$translations['status.type_improvement']     = ['fr' => 'Amélioration',                                       'en' => 'Improvement'];
$translations['status.type_security']        = ['fr' => 'Sécurité',                                           'en' => 'Security'];

// ── Abonnement et notifications ──────────────────────────────
$translations['status.subscribe_title']      = ['fr' => 'Restez informé',                                     'en' => 'Stay informed'];
$translations['status.subscribe_desc']       = ['fr' => 'Rejoignez notre Discord pour recevoir les notifications en temps réel.', 'en' => 'Join our Discord to receive real-time notifications.'];

// ── Discord ──────────────────────────────────────────────────
$translations['discord.help']                = ['fr' => 'Besoin d\'aide ?',                                   'en' => 'Need help?'];
$translations['discord.join']                = ['fr' => 'Rejoindre Discord',                                  'en' => 'Join Discord'];

// ── Navigation ───────────────────────────────────────────────
$translations['status.nav']                  = ['fr' => 'Statut',                                             'en' => 'Status'];

// ── Mois (pour getLocalizedDate) ─────────────────────────────
$translations['month.jan']                   = ['fr' => 'janvier',    'en' => 'January'];
$translations['month.feb']                   = ['fr' => 'février',    'en' => 'February'];
$translations['month.mar']                   = ['fr' => 'mars',       'en' => 'March'];
$translations['month.apr']                   = ['fr' => 'avril',      'en' => 'April'];
$translations['month.may']                   = ['fr' => 'mai',        'en' => 'May'];
$translations['month.jun']                   = ['fr' => 'juin',       'en' => 'June'];
$translations['month.jul']                   = ['fr' => 'juillet',    'en' => 'July'];
$translations['month.aug']                   = ['fr' => 'août',       'en' => 'August'];
$translations['month.sep']                   = ['fr' => 'septembre',  'en' => 'September'];
$translations['month.oct']                   = ['fr' => 'octobre',    'en' => 'October'];
$translations['month.nov']                   = ['fr' => 'novembre',   'en' => 'November'];
$translations['month.dec']                   = ['fr' => 'décembre',   'en' => 'December'];
	
// --- TEXTES POLITIQUE DE PAIEMENT ---
$translations['payment.title']     = ['fr' => 'Politique de Paiement - OrinHeberge', 'en' => 'Payment Policy - OrinHeberge'];
$translations['payment.heading']   = ['fr' => 'Politique de Paiement', 'en' => 'Payment Policy'];
$translations['payment.subtitle']  = ['fr' => 'Dernière mise à jour : ' . date('d') . ' ' . $translations['month.' . strtolower(date('M'))][$lang] . ' ' . date('Y'), 'en' => 'Last updated: ' . $translations['month.' . strtolower(date('M'))][$lang] . ' ' . date('d, Y')];

// Section 1
$translations['payment.s1.title']  = ['fr' => 'Objet', 'en' => 'Purpose'];
$translations['payment.s1.text']   = ['fr' => 'La présente Politique de Paiement complète les Conditions Générales d\'Utilisation (CGU) et la Licence OrinStone Studio régissant le Logiciel utilisé par OrinHeberge. Elle décrit les modalités de paiement, de facturation et de remboursement applicables aux services d\'hébergement proposés.', 'en' => 'This Payment Policy supplements the General Terms of Use (TOU) and the OrinStone Studio License governing the Software used by OrinHeberge. It describes the payment, billing, and refund terms applicable to the hosting services offered.'];

// Section 2
$translations['payment.s2.title']  = ['fr' => 'Moyens de paiement acceptés', 'en' => 'Accepted Payment Methods'];
$translations['payment.s2.intro']  = ['fr' => 'OrinHeberge propose les moyens de paiement suivants, via des prestataires de paiement tiers agréés :', 'en' => 'OrinHeberge offers the following payment methods through authorized third-party payment providers:'];
$translations['payment.s2.text']   = ['fr' => 'Ces transactions sont traitées par Stripe (et, le cas échéant, PayPal), des prestataires de services de paiement (PSP) tiers indépendants d\'OrinHeberge. OrinHeberge ne stocke à aucun moment les données bancaires complètes de ses clients : celles-ci transitent directement et de façon chiffrée vers le PSP concerné.', 'en' => 'These transactions are processed by Stripe (and, where applicable, PayPal), third-party payment service providers (PSPs) independent of OrinHeberge. OrinHeberge never stores complete banking details: these are transmitted directly and securely encrypted to the relevant PSP.'];

// Section 3
$translations['payment.s3.title']  = ['fr' => 'Sécurité des transactions', 'en' => 'Transaction Security'];
$translations['payment.s3.t1']     = ['fr' => 'Toutes les transactions sont sécurisées par chiffrement TLS et traitées selon les standards PCI-DSS appliqués par nos prestataires de paiement.', 'en' => 'All transactions are secured by TLS encryption and processed according to PCI-DSS standards applied by our payment providers.'];
$translations['payment.s3.t2']     = ['fr' => 'Conformément à l\'article 5 de la Licence OrinStone Studio, OrinHeberge est seul responsable de la configuration sécurisée de son infrastructure de paiement et de la conformité à la réglementation (RGPD, DSP2).', 'en' => 'In accordance with Article 5 of the OrinStone Studio License, OrinHeberge is solely responsible for the secure configuration of its payment infrastructure and compliance with regulations (GDPR, PSD2).'];
$translations['payment.s3.t3']     = ['fr' => 'OrinStone Studio, éditeur du Logiciel, n\'intervient à aucun moment dans le traitement des paiements et n\'a pas accès aux données de transaction.', 'en' => 'OrinStone Studio, the software publisher, does not intervene in payment processing at any time and has no access to transaction data.'];

// Section 4
$translations['payment.s4.title']  = ['fr' => 'Facturation', 'en' => 'Billing'];
$translations['payment.s4.t1']     = ['fr' => 'Toute commande donne lieu à l\'émission d\'une facture, disponible dans l\'espace client, après confirmation du paiement.', 'en' => 'Every order generates an invoice, available in the customer area, after payment confirmation.'];
$translations['payment.s4.t2']     = ['fr' => 'Les prix affichés sont en euros (€) et incluent les taxes applicables, sauf mention contraire.', 'en' => 'Displayed prices are in euros (€) and include applicable taxes, unless stated otherwise.'];
$translations['payment.s4.t3']     = ['fr' => 'Pour les services à renouvellement périodique, le client est informé de la date du prochain prélèvement dans son espace client. Le non-paiement peut entraîner la suspension puis la suppression du service.', 'en' => 'For recurring services, the customer is notified of the next charge date in their account. Non-payment may lead to service suspension and termination.'];

// Section 5
$translations['payment.s5.title']  = ['fr' => 'Confirmation et activation du service', 'en' => 'Confirmation & Service Activation'];
$translations['payment.s5.t1']     = ['fr' => 'L\'activation du service (création du serveur, accès au panel) intervient automatiquement après confirmation du paiement, dans un délai habituel de quelques secondes à quelques minutes.', 'en' => 'Service activation (server setup, panel access) occurs automatically after payment confirmation, usually within seconds or minutes.'];
$translations['payment.s5.t2']     = ['fr' => 'En cas de délai anormal d\'activation malgré un paiement confirmé, le client est invité à contacter le support.', 'en' => 'In case of abnormal delay despite payment confirmation, the customer is invited to contact support.'];

// Section 6
$translations['payment.s6.title']  = ['fr' => 'Remboursements et rétractation', 'en' => 'Refunds & Right of Withdrawal'];
$translations['payment.s6.t1']     = ['fr' => 'Conformément à l\'article L221-28 du Code de la consommation, le droit de rétractation ne s\'applique pas aux services pleinement exécutés avant la fin du délai de rétractation avec l\'accord préalable exprès du consommateur.', 'en' => 'In accordance with consumer regulations, the right of withdrawal does not apply to services fully executed prior to the withdrawal period with explicit consumer consent.'];
$translations['payment.s6.t2']     = ['fr' => 'En souscrivant à un service, le client reconnaît et accepte que l\'activation immédiate du serveur constitue le début d\'exécution du service, renonçant ainsi à son droit de rétractation.', 'en' => 'By subscribing, the customer agrees that immediate server setup constitutes service commencement, waiving their right of withdrawal.'];
$translations['payment.s6.t3']     = ['fr' => 'Garantie satisfait ou remboursé sous 24 heures sur demande auprès du support (sous réserve d\'un usage conforme aux CGU).', 'en' => '24-hour money-back guarantee upon request to support (provided usage complies with Terms of Service).'];
$translations['payment.s6.t4']     = ['fr' => 'Aucun remboursement ne sera accordé en cas de suspension ou résiliation pour violation des CGU, usage abusif ou activité illégale.', 'en' => 'No refund will be granted in case of suspension or termination due to TOS violation, abuse, or illegal activity.'];

// Section 7
$translations['payment.s7.title']  = ['fr' => 'Litiges et rétrofacturation (Chargeback)', 'en' => 'Disputes & Chargebacks'];
$translations['payment.s7.t1']     = ['fr' => 'En cas de désaccord sur une facturation, le client s\'engage à contacter le support OrinHeberge avant toute démarche de rétrofacturation (chargeback).', 'en' => 'In case of a billing dispute, the customer agrees to contact OrinHeberge support before initiating any chargeback process.'];
$translations['payment.s7.t2']     = ['fr' => 'Toute rétrofacturation abusive entraînera la suspension immédiate et définitive des services concernés.', 'en' => 'Any abusive chargeback will result in the immediate and permanent suspension of the services concerned.'];

// Section 8
$translations['payment.s8.title']  = ['fr' => 'Limitation de responsabilité', 'en' => 'Limitation of Liability'];
$translations['payment.s8.text']   = ['fr' => 'La responsabilité d\'OrinHeberge concernant les incidents de paiement ne saurait excéder les sommes effectivement perçues au titre de la commande concernée. OrinHeberge ne saurait être tenu responsable des dysfonctionnements propres aux plateformes tierces.', 'en' => 'OrinHeberge\'s liability regarding payment incidents shall not exceed the amounts actually collected for the relevant order. OrinHeberge cannot be held liable for third-party platform malfunctions.'];

// Section 9
$translations['payment.s9.title']  = ['fr' => 'Données personnelles', 'en' => 'Personal Data'];
$translations['payment.s9.text']   = ['fr' => 'Les données transmises lors du paiement (nom, email, éléments de facturation) sont traitées conformément à notre Politique de Confidentialité et au RGPD.', 'en' => 'Data transmitted during payment (name, email, billing details) is processed in accordance with our Privacy Policy and GDPR.'];

// Section 10
$translations['payment.s10.title'] = ['fr' => 'Contact', 'en' => 'Contact'];
$translations['payment.s10.text']  = ['fr' => 'Pour toute question relative à la présente Politique de Paiement :', 'en' => 'For any questions regarding this Payment Policy:'];