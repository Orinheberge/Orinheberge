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
// AJOUT DE 'de' DANS LA LISTE AUTORISÉE
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en', 'de'])) {
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
                    // Note: Si vous avez une colonne 'de' dans la BDD, ajoutez-la ici aussi
                    // 'de' => (string)($row['de'] ?? ''), 
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
    'nav.home'            => ['fr' => 'Accueil',           'en' => 'Home',              'de' => 'Startseite'],
    'nav.servers'         => ['fr' => 'Mes serveurs',      'en' => 'My servers',        'de' => 'Meine Server'],
    'nav.offers'          => ['fr' => 'Offres',            'en' => 'Offers',            'de' => 'Angebote'],
    'nav.support'         => ['fr' => 'Support',           'en' => 'Support',           'de' => 'Support'],
    'nav.login'           => ['fr' => 'Connexion',         'en' => 'Login',             'de' => 'Anmelden'],
    'nav.register'        => ['fr' => 'Inscription',       'en' => 'Sign up',           'de' => 'Registrieren'],
    'nav.logout'          => ['fr' => 'Déconnexion',       'en' => 'Logout',            'de' => 'Abmelden'],
    'nav.profile'         => ['fr' => 'Mon Profil',        'en' => 'My Profile',        'de' => 'Mein Profil'],
    'nav.admin_tickets'   => ['fr' => 'Gérer les tickets (Admin)', 'en' => 'Manage tickets (Admin)', 'de' => 'Tickets verwalten (Admin)'],
    'nav.admin_tickets_short' => ['fr' => 'Gérer les tickets', 'en' => 'Manage tickets', 'de' => 'Tickets verwalten'],
    'nav.phpmyadmin'      => ['fr' => 'phpMyAdmin',        'en' => 'phpMyAdmin',        'de' => 'phpMyAdmin'],
    'nav.panel'           => ['fr' => 'Panel',             'en' => 'Panel',             'de' => 'Panel'],

    // ══════════════════════════════════════════════════════════════════════
    // FOOTER
    // ══════════════════════════════════════════════════════════════════════
    'footer.nav'          => ['fr' => 'Navigation',        'en' => 'Navigation',        'de' => 'Navigation'],
    'footer.network'      => ['fr' => 'Notre Réseau',      'en' => 'Our Network',       'de' => 'Unser Netzwerk'],
    'footer.discord'      => ['fr' => 'Notre Discord',     'en' => 'Our Discord',       'de' => 'Unser Discord'],
    'footer.status'       => ['fr' => 'Statut des Services', 'en' => 'Service Status',  'de' => 'Service-Status'],
    'footer.links'        => ['fr' => 'Liens Utiles',      'en' => 'Useful Links',      'de' => 'Nützliche Links'],
    'footer.payments'     => ['fr' => 'Moyens de Paiements Acceptés', 'en' => 'Accepted Payment Methods', 'de' => 'Akzeptierte Zahlungsmethoden'],
    'footer.legal'        => ['fr' => 'Mentions Légales',  'en' => 'Legal Notice',      'de' => 'Impressum'],
    'footer.cgu'          => ['fr' => "Conditions Générales d'Utilisation", 'en' => 'Terms of Service', 'de' => 'AGB'],
    'footer.privacy'      => ['fr' => 'Politique de Confidentialité', 'en' => 'Privacy Policy', 'de' => 'Datenschutz'],
    'footer.copyright'    => ['fr' => '© 2026-2029 OrinHeberge — Infrastructure OrinStone. Tous droits réservés.', 'en' => '© 2026-2029 OrinHeberge — OrinStone Infrastructure. All rights reserved.', 'de' => '© 2026-2029 OrinHeberge — OrinStone Infrastruktur. Alle Rechte vorbehalten.'],
    'footer.powered'      => ['fr' => 'Propulsé par',      'en' => 'Powered by',        'de' => 'Betrieben durch'],

    // ══════════════════════════════════════════════════════════════════════
    // DISCORD BUTTON (partagé)
    // ══════════════════════════════════════════════════════════════════════
    'discord.help'        => ['fr' => 'Besoin d\'aide ? Discord', 'en' => 'Need help? Discord', 'de' => 'Hilfe benötigt? Discord'],

    // ══════════════════════════════════════════════════════════════════════
    // INDEX — Hero
    // ══════════════════════════════════════════════════════════════════════
    'hero.title'          => ['fr' => 'L\'hébergement réinventé', 'en' => 'Hosting reinvented', 'de' => 'Hosting neu erfunden'],
    'hero.subtitle'       => ['fr' => 'Déployez vos infrastructures sur du matériel de pointe. Performance brute, sécurité maximale et stabilité garantie.', 'en' => 'Deploy your infrastructure on cutting-edge hardware. Raw performance, maximum security and guaranteed stability.', 'de' => 'Stellen Sie Ihre Infrastruktur auf modernster Hardware bereit. Rohe Leistung, maximale Sicherheit und garantierte Stabilität.'],
    'hero.cta'            => ['fr' => 'Découvrir nos offres', 'en' => 'Browse our plans', 'de' => 'Angebote entdecken'],
    'hero.status_ok'      => ['fr' => 'Infrastructure opérationnelle', 'en' => 'Infrastructure operational', 'de' => 'Infrastruktur betriebsbereit'],
    'hero.status_ko'      => ['fr' => 'Maintenance en cours',  'en' => 'Maintenance in progress', 'de' => 'Wartungsarbeiten laufen'],

    // ══════════════════════════════════════════════════════════════════════
    // INDEX — About section
    // ══════════════════════════════════════════════════════════════════════
    'about.badge'         => ['fr' => 'À PROPOS DE NOUS',  'en' => 'ABOUT US', 'de' => 'ÜBER UNS'],
    'about.title'         => ['fr' => 'Pourquoi faire confiance à', 'en' => 'Why trust', 'de' => 'Warum vertrauen auf'],
    'about.p1'            => ['fr' => 'OrinHeberge est un hébergeur de nouvelle génération conçu pour offrir un équilibre parfait entre haute performance, accessibilité et simplicité d\'utilisation. Structuré autour d\'une infrastructure robuste (<strong>OrinStone</strong>), il s\'adresse aussi bien aux passionnés, aux développeurs indépendants qu\'aux communautés exigeantes.', 'en' => 'OrinHeberge is a next-generation hosting provider designed to offer the perfect balance between high performance, accessibility and ease of use. Built around a robust infrastructure (<strong>OrinStone</strong>), it serves enthusiasts, independent developers and demanding communities alike.', 'de' => 'OrinHeberge ist ein Hosting-Anbieter der nächsten Generation, der das perfekte Gleichgewicht zwischen hoher Leistung, Zugänglichkeit und Benutzerfreundlichkeit bietet. Aufbauend auf einer robusten Infrastruktur (<strong>OrinStone</strong>) richtet es sich an Enthusiasten, unabhängige Entwickler und anspruchsvolle Communities.'],
    'about.p2'            => ['fr' => 'Plus qu\'un simple fournisseur, l\'hébergeur s\'appuie sur une forte présence communautaire pour assurer un support de proximité, ultra-réactif et constamment à l\'écoute de ses utilisateurs.', 'en' => 'More than just a provider, the host relies on a strong community presence to ensure responsive, close-knit support that constantly listens to its users.', 'de' => 'Mehr als nur ein Anbieter setzt der Hoster auf eine starke Community-Präsenz, um einen nahbaren, ultraschnellen Support zu gewährleisten, der ständig auf die Bedürfnisse seiner Nutzer eingeht.'],
    'about.feat1_title'   => ['fr' => 'Infrastructure Top Tier',  'en' => 'Top-Tier Infrastructure', 'de' => 'Top-Tier Infrastruktur'],
    'about.feat1_desc'    => ['fr' => 'Processeurs Ryzen de dernière génération et stockage SSD NVMe ultra-rapide.', 'en' => 'Latest-generation Ryzen processors and ultra-fast NVMe SSD storage.', 'de' => 'Prozessoren der neuesten Ryzen-Generation und ultraschneller NVMe-SSD-Speicher.'],
    'about.feat2_title'   => ['fr' => 'Écosystème Polyvalent',    'en' => 'Versatile Ecosystem', 'de' => 'Vielseitiges Ökosystem'],
    'about.feat2_desc'    => ['fr' => 'Environnements optimisés pour vos serveurs Minecraft, PHP et projets Node.js.', 'en' => 'Optimised environments for your Minecraft servers, PHP sites and Node.js projects.', 'de' => 'Optimierte Umgebungen für Ihre Minecraft-Server, PHP-Seiten und Node.js-Projekte.'],
    'about.feat3_title'   => ['fr' => 'Modèle Équitable',         'en' => 'Fair Model', 'de' => 'Faires Modell'],
    'about.feat3_desc'    => ['fr' => 'Des offres gratuites généreuses et des formules Premium pour aller plus loin.', 'en' => 'Generous free plans and Premium packages to go further.', 'de' => 'Großzügige kostenlose Pläne und Premium-Pakete für noch mehr Möglichkeiten.'],
    'about.feat4_title'   => ['fr' => 'Gestion Sécurisée',        'en' => 'Secure Management', 'de' => 'Sicheres Management'],
    'about.feat4_desc'    => ['fr' => 'Panel d\'administration intuitif, outils complets et protection DDoS native.', 'en' => 'Intuitive admin panel, comprehensive tools and native DDoS protection.', 'de' => 'Intuitives Admin-Panel, umfassende Tools und nativer DDoS-Schutz.'],

    // ══════════════════════════════════════════════════════════════════════
    // INDEX + SHOP — Offres
    // ══════════════════════════════════════════════════════════════════════
    'offers.title'        => ['fr' => 'Nos Offres',        'en' => 'Our Plans', 'de' => 'Unsere Angebote'],
    'offers.subtitle'     => ['fr' => 'Hébergements gratuits et premium pour chaque type de projet.', 'en' => 'Free and premium hosting for every type of project.', 'de' => 'Kostenloses und Premium-Hosting für jede Art von Projekt.'],
    'offers.tab.all'      => ['fr' => 'Tous',              'en' => 'All', 'de' => 'Alle'],
    'offers.tab.cat_subtitle' => ['fr' => 'Toutes les offres disponibles, du moins cher au plus cher.', 'en' => 'All available plans, cheapest to most expensive.', 'de' => 'Alle verfügbaren Pläne, vom günstigsten bis zum teuersten.'],

    // Tiers
    'tier.free.title'     => ['fr' => 'Offres Gratuites',  'en' => 'Free Plans', 'de' => 'Kostenlose Pläne'],
    'tier.free.subtitle'  => ['fr' => 'Déployez gratuitement vos projets avec puissance et sécurité.', 'en' => 'Deploy your projects for free with power and security.', 'de' => 'Stellen Sie Ihre Projekte kostenlos mit Leistung und Sicherheit bereit.'],
    'tier.free.label'     => ['fr' => 'Gratuit',           'en' => 'Free', 'de' => 'Kostenlos'],
    'tier.basic.title'    => ['fr' => 'Offres Basic',      'en' => 'Basic Plans', 'de' => 'Basic Pläne'],
    'tier.basic.subtitle' => ['fr' => 'Un premier pas vers plus de performances, à petit prix.', 'en' => 'A first step toward more performance, at a low price.', 'de' => 'Ein erster Schritt zu mehr Leistung zum kleinen Preis.'],
    'tier.basic.label'    => ['fr' => 'Basic',             'en' => 'Basic', 'de' => 'Basic'],
    'tier.medium.title'   => ['fr' => 'Offres Medium',     'en' => 'Medium Plans', 'de' => 'Medium Pläne'],
    'tier.medium.subtitle'=> ['fr' => 'Plus de puissance pour vos projets en croissance.', 'en' => 'More power for your growing projects.', 'de' => 'Mehr Leistung für Ihre wachsenden Projekte.'],
    'tier.medium.label'   => ['fr' => 'Medium',            'en' => 'Medium', 'de' => 'Medium'],
    'tier.premium.title'  => ['fr' => 'Offres Premium',    'en' => 'Premium Plans', 'de' => 'Premium Pläne'],
    'tier.premium.subtitle'=> ['fr' => 'Ressources 100% dédiées, support prioritaire et puissance sans limite.', 'en' => '100% dedicated resources, priority support and unlimited power.', 'de' => '100% dedizierte Ressourcen, Priority-Support und unbegrenzte Leistung.'],
    'tier.premium.label'  => ['fr' => 'Premium',           'en' => 'Premium', 'de' => 'Premium'],
    'tier.mythic.title'    => ['fr' => 'Offres Mythic',    'en' => 'Mythic Plans', 'de' => 'Mythic Pläne'],
    'tier.mythic.subtitle' => ['fr' => 'Ressources illimitées, accès exclusif et performances ultimes.', 'en' => 'Unlimited resources, exclusive access and ultimate performance.', 'de' => 'Unbegrenzte Ressourcen, exklusiver Zugang und ultimative Leistung.'],
    'tier.mythic.label'    => ['fr' => 'Mythic',           'en' => 'Mythic', 'de' => 'Mythic'],

    // Boutons offres
    'btn.deploy'          => ['fr' => 'Déployer maintenant', 'en' => 'Deploy now', 'de' => 'Jetzt bereitstellen'],
    'btn.host_site'       => ['fr' => 'Héberger mon site',  'en' => 'Host my site', 'de' => 'Meine Seite hosten'],
    'btn.buy'             => ['fr' => 'Acheter',            'en' => 'Buy', 'de' => 'Kaufen'],
    'btn.login_to_buy'    => ['fr' => 'Se connecter',       'en' => 'Log in', 'de' => 'Anmelden'],
    'btn.popular'         => ['fr' => 'POPULAIRE',          'en' => 'POPULAR', 'de' => 'BELIEBT'],
    'offers.period.free'  => ['fr' => '/ à vie',            'en' => '/ lifetime', 'de' => '/ lebenslang'],
    'offers.period.month' => ['fr' => '/ mois',             'en' => '/ month', 'de' => '/ Monat'],

    // ══════════════════════════════════════════════════════════════════════
    // LOGIN PAGE
    // ══════════════════════════════════════════════════════════════════════
    'login.title'         => ['fr' => 'Connexion | OrinHeberge',  'en' => 'Login | OrinHeberge', 'de' => 'Anmelden | OrinHeberge'],
    'login.subtitle'      => ['fr' => 'Connexion à votre espace', 'en' => 'Sign in to your account', 'de' => 'Melden Sie sich bei Ihrem Konto an'],
    'login.email'         => ['fr' => 'Email',                    'en' => 'Email', 'de' => 'E-Mail'],
    'login.password'      => ['fr' => 'Mot de passe',             'en' => 'Password', 'de' => 'Passwort'],
    'login.forgot'        => ['fr' => 'Mot de passe oublié ?',    'en' => 'Forgot password?', 'de' => 'Passwort vergessen?'],
    'login.submit'        => ['fr' => 'Se Connecter',             'en' => 'Log in', 'de' => 'Anmelden'],
    'login.no_account'    => ['fr' => 'Pas encore de compte ?',   'en' => 'No account yet?', 'de' => 'Noch kein Konto?'],
    'login.create'        => ['fr' => 'Créer un compte',          'en' => 'Create an account', 'de' => 'Konto erstellen'],
    'login.back'          => ['fr' => 'Retour à l\'accueil',      'en' => 'Back to home', 'de' => 'Zurück zur Startseite'],
    'login.error'         => ['fr' => 'Identifiants incorrects.',  'en' => 'Invalid credentials.', 'de' => 'Ungültige Anmeldedaten.'],
    'login.db_error'      => ['fr' => 'Erreur de connexion à la base de données.', 'en' => 'Database connection error.', 'de' => 'Datenbankverbindungsfehler.'],

    // ══════════════════════════════════════════════════════════════════════
    // REGISTER PAGE
    // ══════════════════════════════════════════════════════════════════════
    'register.title'      => ['fr' => 'Inscription | OrinHeberge', 'en' => 'Sign up | OrinHeberge', 'de' => 'Registrieren | OrinHeberge'],
    'register.subtitle'   => ['fr' => 'Créez votre compte OrinHeberge', 'en' => 'Create your OrinHeberge account', 'de' => 'Erstellen Sie Ihr OrinHeberge-Konto'],
    'register.firstname'  => ['fr' => 'Prénom',                    'en' => 'First name', 'de' => 'Vorname'],
    'register.lastname'   => ['fr' => 'Nom',                       'en' => 'Last name', 'de' => 'Nachname'],
    'register.email'      => ['fr' => 'Email',                     'en' => 'Email', 'de' => 'E-Mail'],
    'register.password'   => ['fr' => 'Mot de passe',              'en' => 'Password', 'de' => 'Passwort'],
    'register.submit'     => ['fr' => 'Créer mon compte',          'en' => 'Create my account', 'de' => 'Konto erstellen'],
    'register.have_account'=> ['fr' => 'Déjà un compte ?',         'en' => 'Already have an account?', 'de' => 'Bereits ein Konto?'],
    'register.login_link' => ['fr' => 'Se connecter',              'en' => 'Sign in', 'de' => 'Anmelden'],
    'register.back'       => ['fr' => 'Retour à l\'accueil',       'en' => 'Back to home', 'de' => 'Zurück zur Startseite'],
    'register.success'    => ['fr' => 'Inscription réussie ! <a href=\'/login/\' class=\'font-bold underline\'>Connectez-vous ici</a>', 'en' => 'Registration successful! <a href=\'/login/\' class=\'font-bold underline\'>Log in here</a>', 'de' => 'Registrierung erfolgreich! <a href=\'/login/\' class=\'font-bold underline\'>Hier anmelden</a>'],
    'register.error_dup'  => ['fr' => 'Erreur : Cet email est déjà utilisé ou le serveur est indisponible.', 'en' => 'Error: This email is already in use or the server is unavailable.', 'de' => 'Fehler: Diese E-Mail wird bereits verwendet oder der Server ist nicht verfügbar.'],

    // ══════════════════════════════════════════════════════════════════════
    // PROFILE PAGE
    // ══════════════════════════════════════════════════════════════════════
    'profil.title'        => ['fr' => 'Mon Profil | OrinHeberge', 'en' => 'My Profile | OrinHeberge', 'de' => 'Mein Profil | OrinHeberge'],
    'profil.heading'      => ['fr' => 'Mon Profil',               'en' => 'My Profile', 'de' => 'Mein Profil'],
    'profil.pseudo'       => ['fr' => 'Pseudo (Affichage site)',  'en' => 'Username (display name)', 'de' => 'Benutzername (Anzeigename)'],
    'profil.firstname'    => ['fr' => 'Prénom',                   'en' => 'First name', 'de' => 'Vorname'],
    'profil.lastname'     => ['fr' => 'Nom',                      'en' => 'Last name', 'de' => 'Nachname'],
    'profil.change_pw'    => ['fr' => 'Modifier le mot de passe (laisser vide si inchangé)', 'en' => 'Change password (leave blank to keep current)', 'de' => 'Passwort ändern (leer lassen, wenn unverändert)'],
    'profil.save'         => ['fr' => 'Sauvegarder les modifications', 'en' => 'Save changes', 'de' => 'Änderungen speichern'],
    'profil.success'      => ['fr' => 'Profil mis à jour avec succès !', 'en' => 'Profile updated successfully!', 'de' => 'Profil erfolgreich aktualisiert!'],
    'profil.db_error'     => ['fr' => 'Erreur de connexion à la base de données.', 'en' => 'Database connection error.', 'de' => 'Datenbankverbindungsfehler.'],

    // ══════════════════════════════════════════════════════════════════════
    // MY SERVERS PAGE
    // ══════════════════════════════════════════════════════════════════════
    'servers.title'       => ['fr' => 'OrinHeberge | Mes serveurs', 'en' => 'OrinHeberge | My Servers', 'de' => 'OrinHeberge | Meine Server'],
    'servers.heading'     => ['fr' => '🚀 Mes serveurs',            'en' => '🚀 My Servers', 'de' => '🚀 Meine Server'],
    'servers.manage'      => ['fr' => 'Gérer',                      'en' => 'Manage', 'de' => 'Verwalten'],
    'servers.manage_full' => ['fr' => 'Gérer les détails',          'en' => 'Manage details', 'de' => 'Details verwalten'],
    'servers.no_response' => ['fr' => 'Aucune réponse',             'en' => 'No response', 'de' => 'Keine Antwort'],

    // ══════════════════════════════════════════════════════════════════════
    // SUPPORT PAGE
    // ══════════════════════════════════════════════════════════════════════
    'support.title'         => ['fr' => 'OrinHeberge | Support & Réclamations', 'en' => 'OrinHeberge | Support & Claims', 'de' => 'OrinHeberge | Support & Anfragen'],
    'support.open_ticket'   => ['fr' => 'Ouvrir un ticket de support',           'en' => 'Open a support ticket', 'de' => 'Support-Ticket eröffnen'],
    'support.open_desc'     => ['fr' => 'Un problème avec une machine ? Un bug sur le site ? Envoyez-nous les détails.', 'en' => 'Issue with a server? Bug on the site? Send us the details.', 'de' => 'Problem mit einem Server? Fehler auf der Website? Senden Sie uns die Details.'],
    'support.type_label'    => ['fr' => 'Type de demande *',       'en' => 'Request type *', 'de' => 'Anfragetyp *'],
    'support.subject_label' => ['fr' => 'Sujet du ticket *',       'en' => 'Ticket subject *', 'de' => 'Ticket-Betreff *'],
    'support.send'          => ['fr' => 'Envoyer le ticket',        'en' => 'Send ticket', 'de' => 'Ticket senden'],
    'support.admin_access'  => ['fr' => 'Accéder aux réclamations', 'en' => 'Access claims', 'de' => 'Auf Anfragen zugreifen'],
    'support.discord_title' => ['fr' => 'Support Discord',          'en' => 'Discord Support', 'de' => 'Discord Support'],
    'support.no_ticket'     => ['fr' => 'Aucun ticket pour le moment.', 'en' => 'No tickets yet.', 'de' => 'Noch keine Tickets.'],
    'support.ticket_type'   => ['fr' => 'Type :',                   'en' => 'Type:', 'de' => 'Typ:'],
    'support.error_fields'  => ['fr' => '⚠️ Veuillez remplir tous les champs obligatoires.', 'en' => '⚠️ Please fill in all required fields.', 'de' => '⚠️ Bitte füllen Sie alle Pflichtfelder aus.'],
    'support.success_send'  => ['fr' => '🚀 Votre ticket a été soumis avec succès ! Notre équipe va l\'analyser.', 'en' => '🚀 Your ticket has been submitted! Our team will review it.', 'de' => '🚀 Ihr Ticket wurde erfolgreich übermittelt! Unser Team wird es prüfen.'],
    'support.error_send'    => ['fr' => '❌ Une erreur est survenue lors de l\'envoi.', 'en' => '❌ An error occurred while sending.', 'de' => '❌ Beim Senden ist ein Fehler aufgetreten.'],

    // ══════════════════════════════════════════════════════════════════════
    // CGU / LEGAL / PRIVACY (titres de page)
    // ══════════════════════════════════════════════════════════════════════
    'cgu.title'           => ['fr' => "Conditions Générales d'Utilisation | OrinHeberge", 'en' => 'Terms of Service | OrinHeberge', 'de' => 'AGB | OrinHeberge'],
    'legal.title'         => ['fr' => 'Mentions Légales | OrinHeberge', 'en' => 'Legal Notice | OrinHeberge', 'de' => 'Impressum | OrinHeberge'],
    'privacy.title'       => ['fr' => 'Politique de Confidentialité | OrinHeberge', 'en' => 'Privacy Policy | OrinHeberge', 'de' => 'Datenschutz | OrinHeberge'],

    // ══════════════════════════════════════════════════════════════════════
    // SHOP PAGE (page /shop/ propre)
    // ══════════════════════════════════════════════════════════════════════
    'shop.title'          => ['fr' => 'OrinHeberge | Boutique & Offres', 'en' => 'OrinHeberge | Shop & Plans', 'de' => 'OrinHeberge | Shop & Angebote'],
    'shop.meta_desc'      => ['fr' => 'Boutique OrinHeberge - Choisissez votre hébergement Minecraft, PHP, Node.js ou Python.', 'en' => 'OrinHeberge shop - Choose your Minecraft, PHP, Node.js or Python hosting.', 'de' => 'OrinHeberge Shop - Wählen Sie Ihr Minecraft, PHP, Node.js oder Python Hosting.'],

    // ══════════════════════════════════════════════════════════════════════
    // COMMON UI
    // ══════════════════════════════════════════════════════════════════════
    'ui.back_home'        => ['fr' => 'Retour à l\'accueil', 'en' => 'Back to home', 'de' => 'Zurück zur Startseite'],
    'ui.loading'          => ['fr' => 'Chargement...',       'en' => 'Loading...', 'de' => 'Laden...'],

    // ══════════════════════════════════════════════════════════════════════
    // COOKIES (inc/cookie.php)
    // ══════════════════════════════════════════════════════════════════════
    'cookie.title'       => ['fr' => 'Gestion des cookies', 'en' => 'Cookie Management', 'de' => 'Cookie-Verwaltung'],
    'cookie.text'        => ['fr' => 'OrinHeberge utilise des cookies essentiels pour assurer le bon fonctionnement du site. En poursuivant, vous acceptez notre utilisation des cookies conformément à nos ', 'en' => 'OrinHeberge uses essential cookies to ensure proper site operation. By continuing, you accept our use of cookies in accordance with our ', 'de' => 'OrinHeberge verwendet essentielle Cookies, um den ordnungsgemäßen Betrieb der Website zu gewährleisten. Durch die weitere Nutzung stimmen Sie unserer Verwendung von Cookies gemäß unseren '],
    'cookie.text_and'    => ['fr' => ' et ', 'en' => ' and ', 'de' => ' und '],
    'cookie.ml'          => ['fr' => 'Mentions Légales', 'en' => 'Legal Notice', 'de' => 'Impressum'],
    'cookie.cgu'         => ['fr' => 'CGU', 'en' => 'Terms of Service', 'de' => 'AGB'],
    'cookie.pp'          => ['fr' => 'Politique de Confidentialité', 'en' => 'Privacy Policy', 'de' => 'Datenschutz'],
    'cookie.accept'      => ['fr' => 'Tout accepter', 'en' => 'Accept all', 'de' => 'Alle akzeptieren'],
    'cookie.deny'        => ['fr' => 'Refuser', 'en' => 'Decline', 'de' => 'Ablehnen'],
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

$translations['legal.back']               = ['fr' => 'Retour',                                        'en' => 'Back', 'de' => 'Zurück'];
$translations['cgu.heading']              = ['fr' => "Conditions Générales d'Utilisation",            'en' => 'Terms of Service', 'de' => 'Allgemeine Geschäftsbedingungen'];
$translations['cgu.subtitle']             = ['fr' => "Règles d'utilisation de nos services d'hébergement", 'en' => 'Rules for using our hosting services', 'de' => 'Nutzungsregeln für unsere Hosting-Dienste'];
$translations['cgu.s1.title']             = ['fr' => '1. Acceptation des conditions',                 'en' => '1. Acceptance of terms', 'de' => '1. Annahme der Bedingungen'];
$translations['cgu.s1.text']              = ['fr' => "L'accès et l'utilisation des services d'OrinHeberge impliquent l'acceptation pleine et entière des présentes Conditions Générales d'Utilisation (CGU).", 'en' => 'Access to and use of OrinHeberge services implies full acceptance of these Terms of Service.', 'de' => 'Der Zugriff auf und die Nutzung der Dienste von OrinHeberge setzt die vollständige Akzeptanz dieser Allgemeinen Geschäftsbedingungen (AGB) voraus.'];
$translations['cgu.s2.title']             = ['fr' => '2. Description des Services',                   'en' => '2. Description of Services', 'de' => '2. Beschreibung der Dienste'];
$translations['cgu.s2.text']              = ['fr' => "OrinHeberge fournit des solutions d'hébergement web et de serveurs. Les utilisateurs sont responsables du contenu stocké et partagé sur leurs instances.", 'en' => 'OrinHeberge provides web and server hosting solutions. Users are responsible for the content stored and shared on their instances.', 'de' => 'OrinHeberge bietet Web- und Server-Hosting-Lösungen. Die Benutzer sind für die auf ihren Instanzen gespeicherten und geteilten Inhalte verantwortlich.'];
$translations['cgu.s3.title']             = ['fr' => '3. Activités interdites',                       'en' => '3. Prohibited activities', 'de' => '3. Verbotene Aktivitäten'];
$translations['cgu.s3.text']              = ['fr' => "Sont strictement interdites sur nos infrastructures les activités illégales, les attaques DDoS, l'hébergement de scripts malveillants (phishing, malwares), ou toute utilisation abusive surchargeant volontairement les serveurs partagés.", 'en' => 'Strictly prohibited on our infrastructure: illegal activities, DDoS attacks, hosting malicious scripts (phishing, malware), or any abusive use that intentionally overloads shared servers.', 'de' => 'Streng verboten auf unserer Infrastruktur: Illegale Aktivitäten, DDoS-Angriffe, das Hosten bösartiger Skripte (Phishing, Malware) oder jede missbräuchliche Nutzung, die Shared-Server absichtlich überlastet.'];
$translations['cgu.s4.title']             = ['fr' => '4. Suspension et résiliation',                  'en' => '4. Suspension and termination', 'de' => '4. Sperrung und Kündigung'];
$translations['cgu.s4.text']              = ['fr' => "OrinHeberge se réserve le droit de suspendre temporairement ou définitivement l'accès aux services d'un utilisateur en cas de violation des présentes clauses ou de non-paiement des renouvellements.", 'en' => 'OrinHeberge reserves the right to temporarily or permanently suspend a user\'s access in case of breach of these terms or non-payment of renewals.', 'de' => 'OrinHeberge behält sich das Recht vor, den Zugriff eines Benutzers bei Verstoß gegen diese Bedingungen oder bei Nichtzahlung der Verlängerungen vorübergehend oder dauerhaft zu sperren.'];

$translations['legal.heading']            = ['fr' => 'Mentions Légales',                              'en' => 'Legal Notice', 'de' => 'Impressum'];
$translations['legal.subtitle']           = ['fr' => "Informations obligatoires concernant l'éditeur du site", 'en' => 'Mandatory information about the site publisher', 'de' => 'Pflichtangaben zum Website-Betreiber'];
$translations['legal.s1.title']           = ['fr' => '1. Édition du site',                            'en' => '1. Site publisher', 'de' => '1. Website-Herausgeber'];
$translations['legal.s1.text']            = ['fr' => 'Le site internet <strong>OrinHeberge</strong> est édité par l\'infrastructure OrinStone / DeepStone.', 'en' => 'The <strong>OrinHeberge</strong> website is published by the OrinStone / DeepStone infrastructure.', 'de' => 'Die Website <strong>OrinHeberge</strong> wird von der OrinStone / DeepStone Infrastruktur betrieben.'];
$translations['legal.s1.pub']             = ['fr' => 'Responsable de la publication :',               'en' => 'Publication manager:', 'de' => 'Verantwortlich für den Inhalt:'];
$translations['legal.s1.contact']         = ['fr' => 'Contact Email :',                               'en' => 'Email contact:', 'de' => 'E-Mail-Kontakt:'];
$translations['legal.s2.title']           = ['fr' => '2. Hébergement',                                'en' => '2. Hosting', 'de' => '2. Hosting'];
$translations['legal.s2.text']            = ['fr' => "Ce site et l'ensemble des infrastructures d'hébergement associées sont hébergés par :", 'en' => 'This site and all associated hosting infrastructure are hosted by:', 'de' => 'Diese Website und die gesamte zugehörige Hosting-Infrastruktur werden gehostet von:'];
$translations['legal.s2.host']            = ['fr' => 'Hébergeur du site :',                           'en' => 'Site host:', 'de' => 'Website-Host:'];
$translations['legal.s2.web']             = ['fr' => 'Site Web :',                                    'en' => 'Website:', 'de' => 'Website:'];
$translations['legal.s3.title']           = ['fr' => '3. Propriété intellectuelle',                   'en' => '3. Intellectual property', 'de' => '3. Geistiges Eigentum'];
$translations['legal.s3.text']            = ['fr' => "Tous les éléments graphiques, marques, logos (notamment OrinHeberge et Orinstone Studio) ainsi que les codes sources de la plateforme sont la propriété exclusive de leurs auteurs respectifs. Toute reproduction ou distribution non autorisée est strictement interdite.", 'en' => 'All graphic elements, trademarks, logos (including OrinHeberge and Orinstone Studio) and the platform source code are the exclusive property of their respective authors. Any unauthorised reproduction or distribution is strictly prohibited.', 'de' => 'Alle grafischen Elemente, Marken, Logos (insbesondere OrinHeberge und Orinstone Studio) sowie der Quellcode der Plattform sind das ausschließliche Eigentum ihrer jeweiligen Urheber. Jede unbefugte Vervielfältigung oder Verbreitung ist strengstens untersagt.'];

$translations['privacy.heading']          = ['fr' => 'Politique de Confidentialité',                  'en' => 'Privacy Policy', 'de' => 'Datenschutzerklärung'];
$translations['privacy.subtitle']         = ['fr' => 'Comment nous protégeons et gérons vos données personnelles', 'en' => 'How we protect and manage your personal data', 'de' => 'Wie wir Ihre persönlichen Daten schützen und verwalten'];
$translations['privacy.s1.title']         = ['fr' => '1. Collecte des données',                       'en' => '1. Data collection', 'de' => '1. Datenerhebung'];
$translations['privacy.s1.text']          = ['fr' => "Nous collectons uniquement les informations nécessaires au bon fonctionnement de votre compte : nom, prénom, pseudo, adresse email et adresse IP lors des connexions à des fins de sécurité.", 'en' => 'We collect only the information necessary for your account to function: surname, first name, username, email address and IP address at login for security purposes.', 'de' => 'Wir erfassen nur die Informationen, die für die Funktion Ihres Kontos erforderlich sind: Nachname, Vorname, Benutzername, E-Mail-Adresse und IP-Adresse bei der Anmeldung zu Sicherheitszwecken.'];
$translations['privacy.s2.title']         = ['fr' => '2. Utilisation et Partage',                     'en' => '2. Use and sharing', 'de' => '2. Nutzung und Weitergabe'];
$translations['privacy.s2.text']          = ['fr' => "Vos données personnelles restent strictement internes à <strong>OrinHeberge</strong>. Elles ne sont ni vendues, ni partagées, ni échangées avec des entités tierces commerciales.", 'en' => 'Your personal data remains strictly internal to <strong>OrinHeberge</strong>. It is not sold, shared or exchanged with commercial third parties.', 'de' => 'Ihre persönlichen Daten bleiben strikt intern bei <strong>OrinHeberge</strong>. Sie werden nicht an kommerzielle Dritte verkauft, geteilt oder ausgetauscht.'];
$translations['privacy.s3.title']         = ['fr' => '3. Sécurité de vos informations',               'en' => '3. Security of your information', 'de' => '3. Sicherheit Ihrer Informationen'];
$translations['privacy.s3.text']          = ['fr' => "Vos mots de passe sont hachés de manière sécurisée via l'algorithme fort BCRYPT/DEFAULT en base de données. Les sessions et jetons de réinitialisation sont limités dans le temps pour prévenir les intrusions.", 'en' => 'Your passwords are securely hashed using the strong BCRYPT/DEFAULT algorithm in the database. Sessions and reset tokens are time-limited to prevent intrusions.', 'de' => 'Ihre Passwörter werden sicher mit dem starken BCRYPT/DEFAULT-Algorithmus in der Datenbank gehasht. Sitzungen und Reset-Tokens sind zeitlich begrenzt, um Eindringlinge abzuwehren.'];
$translations['privacy.s4.title']         = ['fr' => '4. Vos droits (RGPD)',                          'en' => '4. Your rights (GDPR)', 'de' => '4. Ihre Rechte (DSGVO)'];
$translations['privacy.s4.text']          = ['fr' => "Conformément aux réglementations en vigueur, vous disposez d'un droit d'accès, de modification et de suppression de vos données. Vous pouvez effectuer ces ajustements depuis votre espace profil ou en contactant notre support.", 'en' => 'In accordance with current regulations, you have the right to access, modify and delete your data. You can make these adjustments from your profile or by contacting our support.', 'de' => 'Gemäß den geltenden Vorschriften haben Sie das Recht auf Zugriff, Berichtigung und Löschung Ihrer Daten. Sie können diese Anpassungen in Ihrem Profilbereich vornehmen oder unseren Support kontaktieren.'];

// ══════════════════════════════════════════════════════════════════════
// MOT DE PASSE OUBLIÉ / RÉINITIALISATION
// ══════════════════════════════════════════════════════════════════════
$translations['forgot.title']             = ['fr' => 'Mot de passe oublié | OrinHeberge',             'en' => 'Forgot password | OrinHeberge', 'de' => 'Passwort vergessen | OrinHeberge'];
$translations['forgot.heading']           = ['fr' => 'Mot de passe oublié ?',                         'en' => 'Forgot your password?', 'de' => 'Passwort vergessen?'];
$translations['forgot.subtitle']          = ['fr' => 'Entrez votre adresse email pour recevoir un lien de réinitialisation.', 'en' => 'Enter your email address to receive a reset link.', 'de' => 'Geben Sie Ihre E-Mail-Adresse ein, um einen Link zum Zurücksetzen zu erhalten.'];
$translations['forgot.email']             = ['fr' => 'Adresse email',                                 'en' => 'Email address', 'de' => 'E-Mail-Adresse'];
$translations['forgot.submit']            = ['fr' => 'Envoyer le lien',                               'en' => 'Send link', 'de' => 'Link senden'];
$translations['forgot.back']              = ['fr' => 'Retour à la connexion',                         'en' => 'Back to login', 'de' => 'Zurück zur Anmeldung'];
$translations['forgot.err_empty']         = ['fr' => 'Entrez une adresse email.',                     'en' => 'Please enter an email address.', 'de' => 'Bitte geben Sie eine E-Mail-Adresse ein.'];
$translations['forgot.msg_generic']       = ['fr' => 'Si un compte existe pour cette adresse, vous recevrez un email contenant un lien de réinitialisation.', 'en' => 'If an account exists for this address, you will receive an email with a reset link.', 'de' => 'Wenn ein Konto für diese Adresse existiert, erhalten Sie eine E-Mail mit einem Link zum Zurücksetzen.'];
$translations['forgot.msg_sent']          = ['fr' => 'Un email a été envoyé. Vérifiez votre boîte de réception.', 'en' => 'An email has been sent. Check your inbox.', 'de' => 'Eine E-Mail wurde gesendet. Überprüfen Sie Ihren Posteingang.'];
$translations['forgot.err_server']        = ['fr' => 'Erreur serveur, réessayez plus tard.',          'en' => 'Server error, please try again later.', 'de' => 'Serverfehler, bitte versuchen Sie es später erneut.'];

$translations['reset.title']              = ['fr' => 'Réinitialiser le mot de passe | OrinHeberge',   'en' => 'Reset password | OrinHeberge', 'de' => 'Passwort zurücksetzen | OrinHeberge'];
$translations['reset.heading']            = ['fr' => 'Nouveau mot de passe',                          'en' => 'New password', 'de' => 'Neues Passwort'];
$translations['reset.new_pw']             = ['fr' => 'Nouveau mot de passe',                          'en' => 'New password', 'de' => 'Neues Passwort'];
$translations['reset.confirm_pw']         = ['fr' => 'Confirmer le mot de passe',                    'en' => 'Confirm password', 'de' => 'Passwort bestätigen'];
$translations['reset.submit']             = ['fr' => 'Réinitialiser',                                 'en' => 'Reset password', 'de' => 'Zurücksetzen'];
$translations['reset.success']            = ['fr' => 'Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.', 'en' => 'Password successfully reset. You can now log in.', 'de' => 'Passwort erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.'];
$translations['reset.err_token']          = ['fr' => 'Token invalide.',                               'en' => 'Invalid token.', 'de' => 'Ungültiger Token.'];
$translations['reset.err_used']           = ['fr' => 'Ce lien a déjà été utilisé.',                  'en' => 'This link has already been used.', 'de' => 'Dieser Link wurde bereits verwendet.'];
$translations['reset.err_expired']        = ['fr' => 'Le lien a expiré.',                             'en' => 'The link has expired.', 'de' => 'Der Link ist abgelaufen.'];
$translations['reset.err_missing']        = ['fr' => 'Token manquant.',                               'en' => 'Missing token.', 'de' => 'Fehlender Token.'];
$translations['reset.err_length']         = ['fr' => 'Le mot de passe doit contenir au moins 8 caractères.', 'en' => 'Password must be at least 8 characters.', 'de' => 'Das Passwort muss mindestens 8 Zeichen lang sein.'];
$translations['reset.err_match']          = ['fr' => 'Les mots de passe ne correspondent pas.',       'en' => 'Passwords do not match.', 'de' => 'Die Passwörter stimmen nicht überein.'];

// ══════════════════════════════════════════════════════════════════════
// CLIENT / MES SERVEURS
// ══════════════════════════════════════════════════════════════════════
$translations['servers.no_server']        = ['fr' => 'Aucun serveur pour le moment.',                 'en' => 'No servers yet.', 'de' => 'Noch keine Server.'];
$translations['servers.renew']            = ['fr' => 'Renouveler',                                    'en' => 'Renew', 'de' => 'Verlängern'];
$translations['servers.status.active']    = ['fr' => 'Actif',                                         'en' => 'Active', 'de' => 'Aktiv'];
$translations['servers.status.suspended'] = ['fr' => 'Suspendu',                                      'en' => 'Suspended', 'de' => 'Gesperrt'];
$translations['servers.open_panel']       = ['fr' => 'Ouvrir dans le panel',                          'en' => 'Open in panel', 'de' => 'Im Panel öffnen'];
$translations['servers.expires']          = ['fr' => 'Expire le',                                     'en' => 'Expires on', 'de' => 'Läuft ab am'];
$translations['servers.order_new']        = ['fr' => 'Commander un serveur',                          'en' => 'Order a server', 'de' => 'Server bestellen'];

// ══════════════════════════════════════════════════════════════════════
// SHOP / COMMANDE / SUCCÈS
// ══════════════════════════════════════════════════════════════════════
$translations['order.title']              = ['fr' => 'OrinHeberge | Commande',                        'en' => 'OrinHeberge | Order', 'de' => 'OrinHeberge | Bestellung'];
$translations['order.success.title']      = ['fr' => 'OrinHeberge | Commande confirmée',              'en' => 'OrinHeberge | Order confirmed', 'de' => 'OrinHeberge | Bestellung bestätigt'];
$translations['order.confirmed']          = ['fr' => 'Commande confirmée !',                          'en' => 'Order confirmed!', 'de' => 'Bestellung bestätigt!'];
$translations['order.confirmed_sub']      = ['fr' => 'Paiement validé — votre instance sera activée très prochainement.', 'en' => 'Payment validated — your instance will be activated very soon.', 'de' => 'Zahlung bestätigt — Ihre Instanz wird in Kürze aktiviert.'];
$translations['order.label_id']           = ['fr' => 'Commande :',                                    'en' => 'Order:', 'de' => 'Bestellung:'];
$translations['order.label_offer']        = ['fr' => 'Offre :',                                       'en' => 'Plan:', 'de' => 'Plan:'];
$translations['order.label_email']        = ['fr' => 'Email :',                                       'en' => 'Email:', 'de' => 'E-Mail:'];
$translations['order.label_pw']           = ['fr' => '🔑 Mot de passe panel :',                       'en' => '🔑 Panel password:', 'de' => '🔑 Panel-Passwort:'];
$translations['order.pw_note']            = ['fr' => 'Notez-le, il ne sera plus affiché.',            'en' => 'Note it down, it will not be shown again.', 'de' => 'Notieren Sie es, es wird nicht mehr angezeigt.'];
$translations['order.goto_servers']       = ['fr' => 'Voir mes serveurs',                             'en' => 'View my servers', 'de' => 'Meine Server anzeigen'];
$translations['order.goto_panel']         = ['fr' => 'Accéder au panel',                              'en' => 'Go to panel', 'de' => 'Zum Panel gehen'];

$translations['free.success.title']       = ['fr' => 'OrinHeberge | Serveur créé',                    'en' => 'OrinHeberge | Server created', 'de' => 'OrinHeberge | Server erstellt'];
$translations['free.success.heading']     = ['fr' => '🚀 Votre serveur est en ligne !',               'en' => '🚀 Your server is online!', 'de' => '🚀 Ihr Server ist online!'];
$translations['free.success.sub']         = ['fr' => 'Votre instance a été créée avec succès.',       'en' => 'Your instance was created successfully.', 'de' => 'Ihre Instanz wurde erfolgreich erstellt.'];

// ══════════════════════════════════════════════════════════════════════
// INFRASTRUCTURE
// ══════════════════════════════════════════════════════════════════════
$translations['infra.badge']              = ['fr' => 'Réseau & Puissance',                            'en' => 'Network & Power', 'de' => 'Netzwerk & Leistung'];
$translations['infra.subtitle']           = ['fr' => "Découvrez l'infrastructure haute performance et redondante qui propulse l'ensemble de nos plateformes et services d'hébergement.", 'en' => 'Discover the high-performance, redundant infrastructure powering all our hosting platforms and services.', 'de' => 'Entdecken Sie die leistungsstarke, redundante Infrastruktur, die alle unsere Hosting-Plattformen und Dienste antreibt.'];
$translations['infra.hw.title']           = ['fr' => 'Matériel de pointe',                            'en' => 'Cutting-edge hardware', 'de' => 'Modernste Hardware'];
$translations['infra.hw.desc']            = ['fr' => "Des processeurs de dernière génération combinés à un stockage NVMe ultra-rapide pour garantir des temps de latence minimaux à vos applications.", 'en' => 'Latest-generation processors combined with ultra-fast NVMe storage to guarantee minimal latency for your applications.', 'de' => 'Prozessoren der neuesten Generation kombiniert mit ultraschnellem NVMe-Speicher, um minimale Latenzzeiten für Ihre Anwendungen zu garantieren.'];
$translations['infra.sec.title']          = ['fr' => 'Sécurité & DDoS',                               'en' => 'Security & DDoS', 'de' => 'Sicherheit & DDoS'];
$translations['infra.sec.desc']           = ['fr' => "Une protection anti-DDoS robuste et active en continu, filtrant les attaques avant qu'elles n'atteignent et n'impactent vos services en ligne.", 'en' => 'Robust, continuously active DDoS protection filtering attacks before they reach and impact your online services.', 'de' => 'Robuster, kontinuierlich aktiver DDoS-Schutz, der Angriffe filtert, bevor sie Ihre Online-Dienste erreichen und beeinträchtigen.'];
$translations['infra.up.title']           = ['fr' => 'Disponibilité 99.9%',                           'en' => '99.9% Uptime', 'de' => '99,9% Verfügbarkeit'];
$translations['infra.up.desc']            = ['fr' => "Grâce à l'écosystème DeepStone, profitez d'une redondance réseau complète assurant la continuité de vos projets web et serveurs.", 'en' => 'Thanks to the DeepStone ecosystem, enjoy full network redundancy ensuring the continuity of your web projects and servers.', 'de' => 'Dank des DeepStone-Ökosystems profitieren Sie von vollständiger Netzwerk-Redundanz, die die Kontinuität Ihrer Webprojekte und Server sicherstellt.'];


$translations['admin.title']              = ['fr' => 'Admin | Gestion des tickets',                   'en' => 'Admin | Ticket management', 'de' => 'Admin | Ticket-Verwaltung'];
$translations['admin.heading']            = ['fr' => 'Gestion des tickets',                           'en' => 'Ticket management', 'de' => 'Ticket-Verwaltung'];
$translations['admin.no_ticket']          = ['fr' => 'Aucun ticket ouvert.',                          'en' => 'No open tickets.', 'de' => 'Keine offenen Tickets.'];
$translations['admin.reply']              = ['fr' => 'Répondre',                                      'en' => 'Reply', 'de' => 'Antworten'];
$translations['admin.close']             = ['fr' => 'Fermer le ticket',                               'en' => 'Close ticket', 'de' => 'Ticket schließen'];
$translations['admin.status']             = ['fr' => 'Statut',                                        'en' => 'Status', 'de' => 'Status'];
$translations['admin.reply_saved']        = ['fr' => '✅ Réponse enregistrée et statut mis à jour !', 'en' => '✅ Reply saved and status updated!', 'de' => '✅ Antwort gespeichert und Status aktualisiert!'];
$translations['admin.reply_empty']        = ['fr' => '⚠️ Le message de réponse ne peut pas être vide.','en' => '⚠️ The reply message cannot be empty.', 'de' => '⚠️ Die Antwortnachricht darf nicht leer sein.'];
$translations['admin.closed_ok']          = ['fr' => '🔒 Ticket marqué comme Fermé.',                 'en' => '🔒 Ticket marked as Closed.', 'de' => '🔒 Ticket als Geschlossen markiert.'];

// ══════════════════════════════════════════════════════════════════════
// Status
// ══════════════════════════════════════════════════════════════════════
$translations['status.title']              = ['fr' => 'Statut des services | OrinHeberge',                    'en' => 'Service Status | OrinHeberge', 'de' => 'Service-Status | OrinHeberge'];
$translations['status.heading']            = ['fr' => 'Statut de nos',                                        'en' => 'Status of our', 'de' => 'Status unserer'];
$translations['status.heading2']           = ['fr' => 'services',                                             'en' => 'services', 'de' => 'Dienste'];
$translations['status.subtitle']           = ['fr' => 'Consultez en temps réel la disponibilité de l\'ensemble de notre infrastructure et de nos services.', 'en' => 'Check in real time the availability of our entire infrastructure and services.', 'de' => 'Überprüfen Sie in Echtzeit die Verfügbarkeit unserer gesamten Infrastruktur und Dienste.'];

// ── Statut global ─────────────────────────────────────────────
$translations['status.global_operational'] = ['fr' => 'Tous les systèmes sont opérationnels',                 'en' => 'All systems are operational', 'de' => 'Alle Systeme sind betriebsbereit'];
$translations['status.global_degraded']    = ['fr' => 'Certains services sont dégradés',                      'en' => 'Some services are degraded', 'de' => 'Einige Dienste sind eingeschränkt'];
$translations['status.global_maintenance'] = ['fr' => 'Maintenance en cours',                                 'en' => 'Maintenance in progress', 'de' => 'Wartungsarbeiten laufen'];

// ── Compteurs et infos ────────────────────────────────────────
$translations['status.services_online']    = ['fr' => 'services en ligne',                                    'en' => 'services online', 'de' => 'Dienste online'];
$translations['status.degraded']           = ['fr' => 'dégradé(s)',                                           'en' => 'degraded', 'de' => 'eingeschränkt'];
$translations['status.last_check']         = ['fr' => 'Dernière vérification',                                'en' => 'Last check', 'de' => 'Letzte Prüfung'];

// ── Statut des services individuels ──────────────────────────
$translations['status.operational']        = ['fr' => 'Opérationnel',                                         'en' => 'Operational', 'de' => 'Betriebsbereit'];
$translations['status.disruption']         = ['fr' => 'Perturbation',                                         'en' => 'Disruption', 'de' => 'Störung'];
$translations['status.log_ok']             = ['fr' => '✅ Service en ligne',                                  'en' => '✅ Service online', 'de' => '✅ Dienst online'];
$translations['status.log_ko']             = ['fr' => '❌ Service hors ligne',                                'en' => '❌ Service offline', 'de' => '❌ Dienst offline'];

// ── Timeline et statistiques ─────────────────────────────────
$translations['status.days']               = ['fr' => 'jours',                                                'en' => 'days', 'de' => 'Tage'];
$translations['status.days_ago']           = ['fr' => 'jours en arrière',                                     'en' => 'days ago', 'de' => 'Tage her'];
$translations['status.uptime_pct']         = ['fr' => 'de disponibilité',                                     'en' => 'uptime', 'de' => 'Verfügbarkeit'];
$translations['status.today']              = ['fr' => 'Aujourd\'hui',                                         'en' => 'Today', 'de' => 'Heute'];

// ── Maintenances ─────────────────────────────────────────────
$translations['status.ongoing_maintenance']  = ['fr' => 'Maintenance en cours',                               'en' => 'Ongoing maintenance', 'de' => 'Laufende Wartung'];
$translations['status.upcoming_maintenance'] = ['fr' => 'Maintenances planifiées',                            'en' => 'Scheduled maintenance', 'de' => 'Geplante Wartung'];
$translations['status.recent_maintenance']   = ['fr' => 'Maintenances récentes',                              'en' => 'Recent maintenance', 'de' => 'Kürzliche Wartung'];

// ── Sections ─────────────────────────────────────────────────
$translations['status.services_status']      = ['fr' => 'État des services',                                  'en' => 'Service status', 'de' => 'Dienstestatus'];

// ── Temps relatif ────────────────────────────────────────────
$translations['status.in_days']              = ['fr' => 'Dans',                                               'en' => 'In', 'de' => 'In'];
$translations['status.in_hours']             = ['fr' => 'Dans',                                               'en' => 'In', 'de' => 'In'];
$translations['status.soon']                 = ['fr' => 'Bientôt',                                            'en' => 'Soon', 'de' => 'Bald'];
$translations['status.hours_remaining']      = ['fr' => 'h restantes',                                        'en' => 'h remaining', 'de' => 'h verbleibend'];
$translations['status.minutes_remaining']    = ['fr' => 'min restantes',                                      'en' => 'min remaining', 'de' => 'min verbleibend'];

// ── Sévérité ─────────────────────────────────────────────────
$translations['status.severity_info']        = ['fr' => 'Information',                                        'en' => 'Information', 'de' => 'Information'];
$translations['status.severity_warning']     = ['fr' => 'Attention',                                          'en' => 'Warning', 'de' => 'Warnung'];
$translations['status.severity_critical']    = ['fr' => 'Critique',                                           'en' => 'Critical', 'de' => 'Kritisch'];

// ── Types de maintenance ─────────────────────────────────────
$translations['status.type_planned']         = ['fr' => 'Planifiée',                                          'en' => 'Planned', 'de' => 'Geplant'];
$translations['status.type_emergency']       = ['fr' => 'Urgence',                                            'en' => 'Emergency', 'de' => 'Notfall'];
$translations['status.type_improvement']     = ['fr' => 'Amélioration',                                       'en' => 'Improvement', 'de' => 'Verbesserung'];
$translations['status.type_security']        = ['fr' => 'Sécurité',                                           'en' => 'Security', 'de' => 'Sicherheit'];

// ── Abonnement et notifications ──────────────────────────────
$translations['status.subscribe_title']      = ['fr' => 'Restez informé',                                     'en' => 'Stay informed', 'de' => 'Bleiben Sie informiert'];
$translations['status.subscribe_desc']       = ['fr' => 'Rejoignez notre Discord pour recevoir les notifications en temps réel.', 'en' => 'Join our Discord to receive real-time notifications.', 'de' => 'Treten Sie unserem Discord bei, um Echtzeit-Benachrichtigungen zu erhalten.'];

// ── Discord ──────────────────────────────────────────────────
$translations['discord.join']                = ['fr' => 'Rejoindre Discord',                                  'en' => 'Join Discord', 'de' => 'Discord beitreten'];

// ── Navigation ───────────────────────────────────────────────
$translations['status.nav']                  = ['fr' => 'Statut',                                             'en' => 'Status', 'de' => 'Status'];

// ── Mois (pour getLocalizedDate) ─────────────────────────────
$translations['month.jan']                   = ['fr' => 'janvier',    'en' => 'January',  'de' => 'Januar'];
$translations['month.feb']                   = ['fr' => 'février',    'en' => 'February', 'de' => 'Februar'];
$translations['month.mar']                   = ['fr' => 'mars',       'en' => 'March',    'de' => 'März'];
$translations['month.apr']                   = ['fr' => 'avril',      'en' => 'April',    'de' => 'April'];
$translations['month.may']                   = ['fr' => 'mai',        'en' => 'May',      'de' => 'Mai'];
$translations['month.jun']                   = ['fr' => 'juin',       'en' => 'June',     'de' => 'Juni'];
$translations['month.jul']                   = ['fr' => 'juillet',    'en' => 'July',     'de' => 'Juli'];
$translations['month.aug']                   = ['fr' => 'août',       'en' => 'August',   'de' => 'August'];
$translations['month.sep']                   = ['fr' => 'septembre',  'en' => 'September','de' => 'September'];
$translations['month.oct']                   = ['fr' => 'octobre',    'en' => 'October',  'de' => 'Oktober'];
$translations['month.nov']                   = ['fr' => 'novembre',   'en' => 'November', 'de' => 'November'];
$translations['month.dec']                   = ['fr' => 'décembre',   'en' => 'December', 'de' => 'Dezember'];
	
// --- TEXTES POLITIQUE DE PAIEMENT ---
$translations['payment.title']     = ['fr' => 'Politique de Paiement - OrinHeberge', 'en' => 'Payment Policy - OrinHeberge', 'de' => 'Zahlungsrichtlinie - OrinHeberge'];
$translations['payment.heading']   = ['fr' => 'Politique de Paiement', 'en' => 'Payment Policy', 'de' => 'Zahlungsrichtlinie'];
// Correction de la syntaxe pour le subtitle
$translations['payment.subtitle']  = ['fr' => 'Dernière mise à jour : ' . date('d') . ' ' . ($translations['month.' . strtolower(date('M'))]['fr'] ?? '') . ' ' . date('Y'), 'en' => 'Last updated: ' . ($translations['month.' . strtolower(date('M'))]['en'] ?? '') . ' ' . date('d, Y'), 'de' => 'Zuletzt aktualisiert: ' . ($translations['month.' . strtolower(date('M'))]['de'] ?? '') . ' ' . date('d, Y')];

// Section 1
$translations['payment.s1.title']  = ['fr' => 'Objet', 'en' => 'Purpose', 'de' => 'Zweck'];
$translations['payment.s1.text']   = ['fr' => 'La présente Politique de Paiement complète les Conditions Générales d\'Utilisation (CGU) et la Licence OrinStone Studio régissant le Logiciel utilisé par OrinHeberge. Elle décrit les modalités de paiement, de facturation et de remboursement applicables aux services d\'hébergement proposés.', 'en' => 'This Payment Policy supplements the General Terms of Use (TOU) and the OrinStone Studio License governing the Software used by OrinHeberge. It describes the payment, billing, and refund terms applicable to the hosting services offered.', 'de' => 'Diese Zahlungsrichtlinie ergänzt die Allgemeinen Geschäftsbedingungen (AGB) und die OrinStone Studio-Lizenz, die die von OrinHeberge verwendete Software regeln. Sie beschreibt die Zahlungs-, Rechnungsstellungs- und Rückerstattungsbedingungen für die angebotenen Hosting-Dienste.'];

// Section 2
$translations['payment.s2.title']  = ['fr' => 'Moyens de paiement acceptés', 'en' => 'Accepted Payment Methods', 'de' => 'Akzeptierte Zahlungsmethoden'];
$translations['payment.s2.intro']  = ['fr' => 'OrinHeberge propose les moyens de paiement suivants, via des prestataires de paiement tiers agréés :', 'en' => 'OrinHeberge offers the following payment methods through authorized third-party payment providers:', 'de' => 'OrinHeberge bietet die folgenden Zahlungsmethoden über autorisierte Drittanbieter-Zahlungsanbieter an:'];
$translations['payment.s2.text']   = ['fr' => 'Ces transactions sont traitées par Stripe (et, le cas échéant, PayPal), des prestataires de services de paiement (PSP) tiers indépendants d\'OrinHeberge. OrinHeberge ne stocke à aucun moment les données bancaires complètes de ses clients : celles-ci transitent directement et de façon chiffrée vers le PSP concerné.', 'en' => 'These transactions are processed by Stripe (and, where applicable, PayPal), third-party payment service providers (PSPs) independent of OrinHeberge. OrinHeberge never stores complete banking details: these are transmitted directly and securely encrypted to the relevant PSP.', 'de' => 'Diese Transaktionen werden von Stripe (und ggf. PayPal), unabhängigen Drittanbieter-Zahlungsdienstleistern (PSPs), abgewickelt. OrinHeberge speichert zu keinem Zeitpunkt vollständige Bankdaten: Diese werden direkt und verschlüsselt an den jeweiligen PSP übertragen.'];

// Section 3
$translations['payment.s3.title']  = ['fr' => 'Sécurité des transactions', 'en' => 'Transaction Security', 'de' => 'Transaktionssicherheit'];
$translations['payment.s3.t1']     = ['fr' => 'Toutes les transactions sont sécurisées par chiffrement TLS et traitées selon les standards PCI-DSS appliqués par nos prestataires de paiement.', 'en' => 'All transactions are secured by TLS encryption and processed according to PCI-DSS standards applied by our payment providers.', 'de' => 'Alle Transaktionen sind durch TLS-Verschlüsselung gesichert und werden gemäß den PCI-DSS-Standards unserer Zahlungsanbieter verarbeitet.'];
$translations['payment.s3.t2']     = ['fr' => 'Conformément à l\'article 5 de la Licence OrinStone Studio, OrinHeberge est seul responsable de la configuration sécurisée de son infrastructure de paiement et de la conformité à la réglementation (RGPD, DSP2).', 'en' => 'In accordance with Article 5 of the OrinStone Studio License, OrinHeberge is solely responsible for the secure configuration of its payment infrastructure and compliance with regulations (GDPR, PSD2).', 'de' => 'Gemäß Artikel 5 der OrinStone Studio-Lizenz ist OrinHeberge allein verantwortlich für die sichere Konfiguration seiner Zahlungsinfrastruktur und die Einhaltung der Vorschriften (DSGVO, PSD2).'];
$translations['payment.s3.t3']     = ['fr' => 'OrinStone Studio, éditeur du Logiciel, n\'intervient à aucun moment dans le traitement des paiements et n\'a pas accès aux données de transaction.', 'en' => 'OrinStone Studio, the software publisher, does not intervene in payment processing at any time and has no access to transaction data.', 'de' => 'OrinStone Studio, der Softwarehersteller, greift zu keinem Zeitpunkt in die Zahlungsabwicklung ein und hat keinen Zugriff auf Transaktionsdaten.'];

// Section 4
$translations['payment.s4.title']  = ['fr' => 'Facturation', 'en' => 'Billing', 'de' => 'Rechnungsstellung'];
$translations['payment.s4.t1']     = ['fr' => 'Toute commande donne lieu à l\'émission d\'une facture, disponible dans l\'espace client, après confirmation du paiement.', 'en' => 'Every order generates an invoice, available in the customer area, after payment confirmation.', 'de' => 'Jede Bestellung führt zur Ausstellung einer Rechnung, die nach Zahlungsbestätigung im Kundenbereich verfügbar ist.'];
$translations['payment.s4.t2']     = ['fr' => 'Les prix affichés sont en euros (€) et incluent les taxes applicables, sauf mention contraire.', 'en' => 'Displayed prices are in euros (€) and include applicable taxes, unless stated otherwise.', 'de' => 'Die angezeigten Preise sind in Euro (€) und enthalten die geltenden Steuern, sofern nicht anders angegeben.'];
$translations['payment.s4.t3']     = ['fr' => 'Pour les services à renouvellement périodique, le client est informé de la date du prochain prélèvement dans son espace client. Le non-paiement peut entraîner la suspension puis la suppression du service.', 'en' => 'For recurring services, the customer is notified of the next charge date in their account. Non-payment may lead to service suspension and termination.', 'de' => 'Bei wiederkehrenden Diensten wird der Kunde in seinem Konto über das Datum der nächsten Abbuchung informiert. Bei Nichtzahlung kann der Dienst gesperrt und schließlich gekündigt werden.'];

// Section 5
$translations['payment.s5.title']  = ['fr' => 'Confirmation et activation du service', 'en' => 'Confirmation & Service Activation', 'de' => 'Bestätigung & Dienstaktivierung'];
$translations['payment.s5.t1']     = ['fr' => 'L\'activation du service (création du serveur, accès au panel) intervient automatiquement après confirmation du paiement, dans un délai habituel de quelques secondes à quelques minutes.', 'en' => 'Service activation (server setup, panel access) occurs automatically after payment confirmation, usually within seconds or minutes.', 'de' => 'Die Dienstaktivierung (Server-Einrichtung, Panel-Zugriff) erfolgt automatisch nach Zahlungsbestätigung, normalerweise innerhalb von Sekunden oder Minuten.'];
$translations['payment.s5.t2']     = ['fr' => 'En cas de délai anormal d\'activation malgré un paiement confirmé, le client est invité à contacter le support.', 'en' => 'In case of abnormal delay despite payment confirmation, the customer is invited to contact support.', 'de' => 'Im Falle einer anomalen Verzögerung trotz Zahlungsbestätigung wird der Kunde gebeten, den Support zu kontaktieren.'];

// Section 6
$translations['payment.s6.title']  = ['fr' => 'Remboursements et rétractation', 'en' => 'Refunds & Right of Withdrawal', 'de' => 'Rückerstattungen & Widerrufsrecht'];
$translations['payment.s6.t1']     = ['fr' => 'Conformément à l\'article L221-28 du Code de la consommation, le droit de rétractation ne s\'applique pas aux services pleinement exécutés avant la fin du délai de rétractation avec l\'accord préalable exprès du consommateur.', 'en' => 'In accordance with consumer regulations, the right of withdrawal does not apply to services fully executed prior to the withdrawal period with explicit consumer consent.', 'de' => 'Gemäß den Verbraucherschutzvorschriften gilt das Widerrufsrecht nicht für Dienste, die vor Ablauf der Widerrufsfrist mit ausdrücklicher Zustimmung des Verbrauchers vollständig erbracht wurden.'];
$translations['payment.s6.t2']     = ['fr' => 'En souscrivant à un service, le client reconnaît et accepte que l\'activation immédiate du serveur constitue le début d\'exécution du service, renonçant ainsi à son droit de rétractation.', 'en' => 'By subscribing, the customer agrees that immediate server setup constitutes service commencement, waiving their right of withdrawal.', 'de' => 'Mit der Bestellung stimmt der Kunde zu, dass die sofortige Server-Einrichtung den Beginn der Dienstleistung darstellt und verzichtet damit auf sein Widerrufsrecht.'];
$translations['payment.s6.t3']     = ['fr' => 'Garantie satisfait ou remboursé sous 24 heures sur demande auprès du support (sous réserve d\'un usage conforme aux CGU).', 'en' => '24-hour money-back guarantee upon request to support (provided usage complies with Terms of Service).', 'de' => '24-Stunden-Geld-zurück-Garantie auf Anfrage beim Support (vorausgesetzt, die Nutzung entspricht den AGB).'];
$translations['payment.s6.t4']     = ['fr' => 'Aucun remboursement ne sera accordé en cas de suspension ou résiliation pour violation des CGU, usage abusif ou activité illégale.', 'en' => 'No refund will be granted in case of suspension or termination due to TOS violation, abuse, or illegal activity.', 'de' => 'Es wird keine Rückerstattung gewährt im Falle einer Sperrung oder Kündigung aufgrund von AGB-Verstößen, Missbrauch oder illegaler Aktivitäten.'];

// Section 7
$translations['payment.s7.title']  = ['fr' => 'Litiges et rétrofacturation (Chargeback)', 'en' => 'Disputes & Chargebacks', 'de' => 'Streitigkeiten & Rückbuchungen'];
$translations['payment.s7.t1']     = ['fr' => 'En cas de désaccord sur une facturation, le client s\'engage à contacter le support OrinHeberge avant toute démarche de rétrofacturation (chargeback).', 'en' => 'In case of a billing dispute, the customer agrees to contact OrinHeberge support before initiating any chargeback process.', 'de' => 'Im Falle einer Rechnungsstreitigkeit verpflichtet sich der Kunde, den OrinHeberge-Support zu kontaktieren, bevor ein Chargeback-Verfahren eingeleitet wird.'];
$translations['payment.s7.t2']     = ['fr' => 'Toute rétrofacturation abusive entraînera la suspension immédiate et définitive des services concernés.', 'en' => 'Any abusive chargeback will result in the immediate and permanent suspension of the services concerned.', 'de' => 'Jeder missbräuchliche Chargeback führt zur sofortigen und dauerhaften Sperrung der betreffenden Dienste.'];

// Section 8
$translations['payment.s8.title']  = ['fr' => 'Limitation de responsabilité', 'en' => 'Limitation of Liability', 'de' => 'Haftungsbeschränkung'];
$translations['payment.s8.text']   = ['fr' => 'La responsabilité d\'OrinHeberge concernant les incidents de paiement ne saurait excéder les sommes effectivement perçues au titre de la commande concernée. OrinHeberge ne saurait être tenu responsable des dysfonctionnements propres aux plateformes tierces.', 'en' => 'OrinHeberge\'s liability regarding payment incidents shall not exceed the amounts actually collected for the relevant order. OrinHeberge cannot be held liable for third-party platform malfunctions.', 'de' => 'Die Haftung von OrinHeberge bezüglich Zahlungsvorfälle überschreitet nicht die tatsächlich für die betreffende Bestellung eingezogenen Beträge. OrinHeberge haftet nicht für Fehlfunktionen von Drittanbieter-Plattformen.'];

// Section 9
$translations['payment.s9.title']  = ['fr' => 'Données personnelles', 'en' => 'Personal Data', 'de' => 'Personenbezogene Daten'];
$translations['payment.s9.text']   = ['fr' => 'Les données transmises lors du paiement (nom, email, éléments de facturation) sont traitées conformément à notre Politique de Confidentialité et au RGPD.', 'en' => 'Data transmitted during payment (name, email, billing details) is processed in accordance with our Privacy Policy and GDPR.', 'de' => 'Die während der Zahlung übermittelten Daten (Name, E-Mail, Rechnungsdaten) werden gemäß unserer Datenschutzerklärung und der DSGVO verarbeitet.'];

// Section 10
$translations['payment.s10.title'] = ['fr' => 'Contact', 'en' => 'Contact', 'de' => 'Kontakt'];
$translations['payment.s10.text']  = ['fr' => 'Pour toute question relative à la présente Politique de Paiement :', 'en' => 'For any questions regarding this Payment Policy:', 'de' => 'Für Fragen zu dieser Zahlungsrichtlinie:'];

// --- POLITIQUE DES COOKIES ---
$translations['cookies.title']    = ['fr' => 'Politique relative aux Cookies — OrinHeberge', 'en' => 'Cookie Policy — OrinHeberge', 'de' => 'Cookie-Richtlinie — OrinHeberge'];
$translations['cookies.heading']  = ['fr' => 'Politique des Cookies', 'en' => 'Cookie Policy', 'de' => 'Cookie-Richtlinie'];
$translations['cookies.subtitle'] = ['fr' => 'Transparence sur l\'utilisation des traceurs et cookies sur notre plateforme.', 'en' => 'Transparency on the use of trackers and cookies on our platform.', 'de' => 'Transparenz über die Verwendung von Trackern und Cookies auf unserer Plattform.'];

$translations['cookies.s1.title'] = ['fr' => 'Qu\'est-ce qu\'un cookie ?', 'en' => 'What is a cookie?', 'de' => 'Was ist ein Cookie?'];
$translations['cookies.s1.text']  = ['fr' => 'Un cookie est un petit fichier texte déposé sur votre appareil lors de la visite d\'un site. Il permet de conserver des données de navigation afin d\'améliorer votre expérience utilisateur.', 'en' => 'A cookie is a small text file stored on your device when visiting a site. It holds browsing data to enhance your user experience.', 'de' => 'Ein Cookie ist eine kleine Textdatei, die beim Besuch einer Website auf Ihrem Gerät gespeichert wird. Sie enthält Browsedaten, um Ihre Benutzererfahrung zu verbessern.'];

$translations['cookies.s2.title'] = ['fr' => 'Les cookies utilisés', 'en' => 'Cookies used', 'de' => 'Verwendete Cookies'];
$translations['cookies.s2.text']  = ['fr' => 'Nous utilisons uniquement des cookies essentiels au fonctionnement du service : maintien de votre session active, mémorisation de votre langue et gestion de votre panier.', 'en' => 'We only use cookies strictly necessary for service operation: keeping your session active, remembering your language preference, and managing your shopping cart.', 'de' => 'Wir verwenden nur Cookies, die für den Betrieb des Dienstes unbedingt erforderlich sind: Aufrechterhaltung Ihrer aktiven Sitzung, Speicherung Ihrer Sprachpräferenz und Verwaltung Ihres Warenkorbs.'];

$translations['cookies.s3.title'] = ['fr' => 'Cookies tiers et sécurité', 'en' => 'Third-party cookies and security', 'de' => 'Cookies von Drittanbietern und Sicherheit'];
$translations['cookies.s3.text']  = ['fr' => 'Aucun cookie publicitaire ou de ciblage commercial n\'est utilisé. Seuls des services tiers de sécurité (Cloudflare) et de paiement (Stripe, PayPal) peuvent déposer des cookies techniques nécessaires.', 'en' => 'No advertising or marketing cookies are used. Only essential third-party services for security (Cloudflare) and payment (Stripe, PayPal) may issue necessary technical cookies.', 'de' => 'Es werden keine Werbe- oder Marketing-Cookies verwendet. Nur essentielle Drittanbieterdienste für Sicherheit (Cloudflare) und Zahlung (Stripe, PayPal) können notwendige technische Cookies setzen.'];

$translations['cookies.s4.title'] = ['fr' => 'Gestion des cookies', 'en' => 'Managing cookies', 'de' => 'Cookie-Verwaltung'];
$translations['cookies.s4.text']  = ['fr' => 'Vous pouvez configurer ou bloquer les cookies directement depuis les options de votre navigateur web. Attention, la désactivation complète des cookies peut restreindre l\'accès à l\'espace client.', 'en' => 'You can configure or block cookies directly through your web browser settings. Please note that disabling essential cookies may limit access to your client area.', 'de' => 'Sie können Cookies direkt über die Einstellungen Ihres Webbrowsers konfigurieren oder blockieren. Bitte beachten Sie, dass die Deaktivierung essentieller Cookies den Zugriff auf Ihren Kundenbereich einschränken kann.'];