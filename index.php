<?php
session_start();
require_once __DIR__ . '/inc/lang.php';

// ==========================================
// SYSTÈME DE TRADUCTION COMPLET (FR / EN / DE)
// Ajoutez ces clés à votre fichier inc/lang.php
// ==========================================
if (!function_exists('add_custom_translations')) {
    function add_custom_translations() {
        global $translations; 
        
        // --- AVERTISSEMENT ---
     $translations['warning.game_purchase.title'] = [
    'fr' => '⚠️ Avertissement Important',
    'en' => '⚠️ Important Warning',
    'de' => '⚠️ Wichtiger Hinweis'
];

$translations['warning.game_purchase.text'] = [
    'fr' => 'Merci de ne pas vouloir acheter des offres de jeux (Minecraft, Hytale, FiveM, Terraria). Cette indisponibilité est temporaire.',
    'en' => 'Please do not attempt to purchase game offers (Minecraft, Hytale, FiveM, Terraria). This suspension is temporary.',
    'de' => 'Bitte versuchen Sie nicht, Spiel-Angebote (Minecraft, Hytale, FiveM, Terraria) gekauft werden können. Dies ist nur vorübergehend.'
];

$translations['warning.signoff'] = [
    'fr' => 'Cordialement, l\'équipe OrinHeberge',
    'en' => 'Best regards, The OrinHeberge Team',
    'de' => 'Mit freundlichen Grüßen, Das OrinHeberge-Team'
];

// ➕ Nouvelles entrées ajoutées temporairement à la fin
$translations['warning.temp_notice.title'] = [
    'fr' => '📌 Information Temporaire',
    'en' => '📌 Temporary Notice',
    'de' => '📌 Vorübergehender Hinweis'
];

$translations['warning.temp_notice.text'] = [
    'fr' => 'Cette section est en cours de mise à jour. Merci de votre compréhension.',
    'en' => 'This section is currently being updated. Thank you for your understanding.',
    'de' => 'Dieser Bereich wird derzeit aktualisiert. Vielen Dank für Ihr Verständnis.'
];

$translations['warning.temp_notice.signoff'] = [
    'fr' => 'Cordialement, l\'équipe OrinHeberge',
    'en' => 'Best regards, The OrinHeberge Team',
    'de' => 'Mit freundlichen Grüßen, Das OrinHeberge-Team'
];

        // --- HERO SECTION ---
        $translations['hero.status.online'] = ['fr' => 'Tous les systèmes opérationnels', 'en' => 'All systems operational', 'de' => 'Alle Systeme betriebsbereit'];
        $translations['hero.status.offline'] = ['fr' => 'Connexion BDD indisponible', 'en' => 'Database connection unavailable', 'de' => 'Datenbankverbindung nicht verfügbar'];
        $translations['hero.subtitle'] = ['fr' => 'Hébergement nouvelle génération — Minecraft, PHP, Node.js, Python.', 'en' => 'Next-generation hosting — Minecraft, PHP, Node.js, Python.', 'de' => 'Hosting der nächsten Generation — Minecraft, PHP, Node.js, Python.'];
        $translations['hero.subtext'] = ['fr' => 'Gratuit pour démarrer. Premium pour aller plus loin.', 'en' => 'Free to start. Premium to go further.', 'de' => 'Kostenlos starten. Premium für mehr Möglichkeiten.'];
        $translations['hero.btn.offers'] = ['fr' => 'Voir les offres', 'en' => 'View offers', 'de' => 'Angebote ansehen'];
        $translations['hero.btn.client'] = ['fr' => 'Mon espace client', 'en' => 'My client area', 'de' => 'Mein Kundenbereich'];
        $translations['hero.btn.register'] = ['fr' => 'Créer un compte gratuit', 'en' => 'Create free account', 'de' => 'Kostenloses Konto erstellen'];

        // --- STATS ---
        $translations['stat.ssd'] = ['fr' => 'SSD NVMe', 'en' => 'SSD NVMe', 'de' => 'SSD NVMe'];
        $translations['stat.free'] = ['fr' => 'Pour commencer', 'en' => 'To get started', 'de' => 'Zum Starten'];
        $translations['stat.support'] = ['fr' => 'Support Discord', 'en' => 'Discord Support', 'de' => 'Discord-Support'];
        $translations['stat.ddos'] = ['fr' => 'Protection incluse', 'en' => 'Protection included', 'de' => 'Schutz inklusive'];

        // --- ENVIRONNEMENTS ---
        $translations['env.title'] = ['fr' => 'Environnements Supportés', 'en' => 'Supported Environments', 'de' => 'Unterstützte Umgebungen'];

        // --- PERFORMANCES ---
        $translations['perf.badge'] = ['fr' => 'Performances Brutes', 'en' => 'Raw Performance', 'de' => 'Rohleistung'];
        $translations['perf.title'] = ['fr' => 'Une infrastructure optimisée pour le <span class="gradient-text">zéro-lenteur</span>.', 'en' => 'Infrastructure optimized for <span class="gradient-text">zero-lag</span>.', 'de' => 'Infrastruktur optimiert für <span class="gradient-text">Null-Latenz</span>.'];
        $translations['perf.desc'] = ['fr' => 'Nous n\'hébergeons pas vos projets sur du matériel obsolète. Nos machines physiques exploitent la puissance des coeurs AMD Ryzen couplée à une architecture réseau ultra redondante.', 'en' => 'We don\'t host your projects on obsolete hardware. Our physical machines leverage AMD Ryzen cores coupled with an ultra-redundant network architecture.', 'de' => 'Wir hosten Ihre Projekte nicht auf veralteter Hardware. Unsere physischen Maschinen nutzen die Leistung von AMD Ryzen-Kernen mit einer hochredundanten Netzwerkarchitektur.'];
        $translations['perf.net.title'] = ['fr' => 'Réseau 10 Gbps', 'en' => '10 Gbps Network', 'de' => '10 Gbps Netzwerk'];
        $translations['perf.net.desc'] = ['fr' => 'Uplink haut débit pour une latence minimale en Europe.', 'en' => 'High-speed uplink for minimal latency in Europe.', 'de' => 'Hochgeschwindigkeits-Uplink für minimale Latenz in Europa.'];
        $translations['perf.ram.title'] = ['fr' => 'RAM DDR5 ECC', 'en' => 'DDR5 ECC RAM', 'de' => 'DDR5 ECC RAM'];
        $translations['perf.ram.desc'] = ['fr' => 'Correction d\'erreurs intégrée pour éviter tout crash.', 'en' => 'Built-in error correction to prevent crashes.', 'de' => 'Integrierte Fehlerkorrektur zur Vermeidung von Abstürzen.'];
        $translations['perf.node.status'] = ['fr' => 'Node Status', 'en' => 'Node Status', 'de' => 'Knotenstatus'];
        $translations['perf.node.online'] = ['fr' => 'ONLINE', 'en' => 'ONLINE', 'de' => 'ONLINE'];

        // --- ÉQUIPE ---
        $translations['team.title'] = ['fr' => 'L\'<span class="gradient-text">Équipe</span> OrinHeberge', 'en' => 'The OrinHeberge <span class="gradient-text">Team</span>', 'de' => 'Das OrinHeberge <span class="gradient-text">Team</span>'];
        $translations['team.subtitle'] = ['fr' => 'Les passionnés qui travaillent chaque jour pour maintenir vos serveurs en ligne.', 'en' => 'The enthusiasts working daily to keep your servers online.', 'de' => 'Die Enthusiasten, die täglich daran arbeiten, Ihre Server online zu halten.'];
        $translations['team.role.founder'] = ['fr' => 'Fondateur & Dev SysAdmin', 'en' => 'Founder & Dev SysAdmin', 'de' => 'Gründer & Dev SysAdmin'];
        $translations['team.desc.matheo'] = ['fr' => 'Architecte de l\'infrastructure, il pilote le développement du panel et garantit la stabilité globale de nos serveurs.', 'en' => 'Infrastructure architect, he leads panel development and ensures overall server stability.', 'de' => 'Infrastrukturarchitekt, leitet die Panel-Entwicklung und gewährleistet die Gesamtstabilität unserer Server.'];
        $translations['team.role.cofounder'] = ['fr' => 'Co-Fondateur & Dev SysAdmin', 'en' => 'Co-Founder & Dev SysAdmin', 'de' => 'Co-Gründer & Dev SysAdmin'];
        $translations['team.desc.wixy'] = ['fr' => 'Artisan de l\'expérience utilisateur, il façonne une interface intuitive, rapide et agréable au quotidien.', 'en' => 'UX craftsman, he shapes an intuitive, fast, and pleasant daily interface.', 'de' => 'UX-Handwerker, er gestaltet eine intuitive, schnelle und angenehme Oberfläche.'];
        $translations['team.role.nexium.dir'] = ['fr' => 'Direction & Responsable Support', 'en' => 'Management & Support Lead', 'de' => 'Leitung & Support-Leiter'];
        $translations['team.role.nexium.partner'] = ['fr' => 'Partenaire Infrastructure', 'en' => 'Infrastructure Partner', 'de' => 'Infrastrukturpartner'];
        $translations['team.desc.nexium'] = ['fr' => 'Pilote la stratégie et anime la communauté Discord, tout en assurant un support technique réactif disponible 24h/24.', 'en' => 'Steers strategy and animates the Discord community, while providing responsive 24/7 technical support.', 'de' => 'Steuert die Strategie und belebt die Discord-Community, während er reaktionsschnellen 24/7-Support bietet.'];
        $translations['team.discord.link'] = ['fr' => 'Notre serveur Discord', 'en' => 'Our Discord Server', 'de' => 'Unser Discord-Server'];
        
        // NitroHebergeur Partenariat
        $translations['partner.nitro.tag'] = ['fr' => 'Partenariat · Géré par Nexium', 'en' => 'Partnership · Managed by Nexium', 'de' => 'Partnerschaft · Verwaltet von Nexium'];
        $translations['partner.nitro.desc'] = ['fr' => 'Hébergement web haute performance et infrastructure cloud professionnelle. Solutions adaptées aux besoins des développeurs et entreprises.', 'en' => 'High-performance web hosting and professional cloud infrastructure. Solutions tailored for developers and businesses.', 'de' => 'Hochleistungs-Webhosting und professionelle Cloud-Infrastruktur. Lösungen für Entwickler und Unternehmen.'];
        $translations['partner.nitro.web'] = ['fr' => 'Hébergement Web', 'en' => 'Web Hosting', 'de' => 'Webhosting'];
        $translations['partner.nitro.cloud'] = ['fr' => 'Serveurs Cloud', 'en' => 'Cloud Servers', 'de' => 'Cloud-Server'];
        $translations['partner.btn.more'] = ['fr' => 'En savoir plus', 'en' => 'Learn more', 'de' => 'Mehr erfahren'];

        // --- POURQUOI NOUS ---
        $translations['why.title'] = ['fr' => 'Pourquoi <span class="gradient-text">OrinHeberge</span> ?', 'en' => 'Why <span class="gradient-text">OrinHeberge</span>?', 'de' => 'Warum <span class="gradient-text">OrinHeberge</span>?'];
        $translations['why.subtitle'] = ['fr' => 'Une infrastructure pensée pour la performance, la simplicité et la fiabilité.', 'en' => 'Infrastructure designed for performance, simplicity, and reliability.', 'de' => 'Infrastruktur für Leistung, Einfachheit und Zuverlässigkeit.'];
        $translations['why.cpu.title'] = ['fr' => 'CPU Ryzen HF', 'en' => 'Ryzen HF CPU', 'de' => 'Ryzen HF CPU'];
        $translations['why.cpu.desc'] = ['fr' => 'Processeurs Ryzen haute fréquence et stockage SSD NVMe ultra-rapide.', 'en' => 'High-frequency Ryzen processors and ultra-fast NVMe SSD storage.', 'de' => 'Hochfrequenz-Ryzen-Prozessoren und ultraschneller NVMe-SSD-Speicher.'];
        $translations['why.multi.title'] = ['fr' => 'Multi-environnements', 'en' => 'Multi-environment', 'de' => 'Multi-Umgebung'];
        $translations['why.multi.desc'] = ['fr' => 'Minecraft, PHP, Node.js, Python, Java — tout en un seul endroit.', 'en' => 'Minecraft, PHP, Node.js, Python, Java — all in one place.', 'de' => 'Minecraft, PHP, Node.js, Python, Java — alles an einem Ort.'];
        $translations['why.free.title'] = ['fr' => 'Gratuit & Abordable', 'en' => 'Free & Affordable', 'de' => 'Kostenlos & Günstig'];
        $translations['why.free.desc'] = ['fr' => 'Commencez gratuitement, évoluez selon vos besoins avec des tarifs compétitifs.', 'en' => 'Start for free, scale as needed with competitive pricing.', 'de' => 'Kostenlos starten, nach Bedarf skalieren mit wettbewerbsfähigen Preisen.'];
        $translations['why.ddos.title'] = ['fr' => 'Protection DDoS', 'en' => 'DDoS Protection', 'de' => 'DDoS-Schutz'];
        $translations['why.ddos.desc'] = ['fr' => 'Anti-DDoS inclus sur toutes les offres, même gratuites.', 'en' => 'Anti-DDoS included on all plans, even free ones.', 'de' => 'Anti-DDoS in allen Tarifen enthalten, auch in kostenlosen.'];

        // --- AVIS CLIENTS ---
        $translations['reviews.badge'] = ['fr' => 'Témoignages', 'en' => 'Testimonials', 'de' => 'Erfahrungsberichte'];
        $translations['reviews.title'] = ['fr' => 'Ce que disent nos <span class="gradient-text">Clients</span>', 'en' => 'What our <span class="gradient-text">Clients</span> say', 'de' => 'Was unsere <span class="gradient-text">Kunden</span> sagen'];
        $translations['reviews.subtitle'] = ['fr' => 'Votre satisfaction est notre meilleure récompense.', 'en' => 'Your satisfaction is our best reward.', 'de' => 'Ihre Zufriedenheit ist unsere beste Belohnung.'];
        $translations['reviews.count'] = ['fr' => 'avis', 'en' => 'reviews', 'de' => 'Bewertungen'];
        $translations['reviews.empty'] = ['fr' => 'Aucun avis pour le moment. Soyez le premier !', 'en' => 'No reviews yet. Be the first!', 'de' => 'Noch keine Bewertungen. Seien Sie der Erste!'];
        $translations['reviews.load_more'] = ['fr' => 'Voir plus d\'avis', 'en' => 'Load more reviews', 'de' => 'Mehr Bewertungen laden'];
        $translations['reviews.form.title'] = ['fr' => 'Laissez votre avis', 'en' => 'Leave a review', 'de' => 'Hinterlassen Sie eine Bewertung'];
        $translations['reviews.form.name'] = ['fr' => 'Votre Pseudo', 'en' => 'Your Nickname', 'de' => 'Ihr Spitzname'];
        $translations['reviews.form.rating'] = ['fr' => 'Note', 'en' => 'Rating', 'de' => 'Bewertung'];
        $translations['reviews.form.comment'] = ['fr' => 'Votre Commentaire', 'en' => 'Your Comment', 'de' => 'Ihr Kommentar'];
        $translations['reviews.success'] = ['fr' => 'Merci ! Votre avis a été envoyé avec succès.', 'en' => 'Thank you! Your review has been submitted successfully.', 'de' => 'Danke! Ihre Bewertung wurde erfolgreich gesendet.'];
        $translations['reviews.submit'] = ['fr' => 'Envoyer l\'avis', 'en' => 'Submit review', 'de' => 'Bewertung absenden'];
        
        // Select Options
        $translations['reviews.opt.5'] = ['fr' => '⭐⭐⭐⭐⭐ Excellent', 'en' => '⭐⭐⭐⭐⭐ Excellent', 'de' => '⭐⭐⭐⭐⭐ Ausgezeichnet'];
        $translations['reviews.opt.4'] = ['fr' => '⭐⭐⭐⭐ Très bien', 'en' => '⭐⭐⭐⭐ Very Good', 'de' => '⭐⭐⭐⭐ Sehr gut'];
        $translations['reviews.opt.3'] = ['fr' => '⭐⭐⭐ Bien', 'en' => '⭐⭐⭐ Good', 'de' => '⭐⭐⭐ Gut'];
        $translations['reviews.opt.2'] = ['fr' => '⭐⭐ Moyen', 'en' => '⭐⭐ Fair', 'de' => '⭐⭐ Befriedigend'];
        $translations['reviews.opt.1'] = ['fr' => '⭐ Décevant', 'en' => '⭐ Poor', 'de' => '⭐ Mangelhaft'];

        // --- TRUSTPILOT ---
        $translations['tp.badge'] = ['fr' => 'Avis vérifiés Trustpilot', 'en' => 'Verified Trustpilot Reviews', 'de' => 'Verifizierte Trustpilot-Bewertungen'];
        $translations['tp.title'] = ['fr' => 'Ils nous font confiance sur <span class="gradient-text">Trustpilot</span>', 'en' => 'Trusted by users on <span class="gradient-text">Trustpilot</span>', 'de' => 'Vertrauen auf <span class="gradient-text">Trustpilot</span>'];
        $translations['tp.subtitle'] = ['fr' => 'Des avis 100% authentiques laissés par de vrais clients via la plateforme Trustpilot.', 'en' => '100% authentic reviews left by real customers via the Trustpilot platform.', 'de' => '100% authentische Bewertungen von echten Kunden über die Trustpilot-Plattform.'];
        $translations['tp.fallback.title'] = ['fr' => 'Soyez le premier à nous évaluer !', 'en' => 'Be the first to rate us!', 'de' => 'Seien Sie der Erste, der uns bewertet!'];
        $translations['tp.fallback.desc'] = ['fr' => 'Notre page Trustpilot est toute neuve. Partagez votre expérience pour aider d\'autres utilisateurs à nous découvrir.', 'en' => 'Our Trustpilot page is brand new. Share your experience to help others discover us.', 'de' => 'Unsere Trustpilot-Seite ist ganz neu. Teilen Sie Ihre Erfahrung, damit andere uns entdecken können.'];
        $translations['tp.btn.write'] = ['fr' => 'Laisser un avis sur Trustpilot', 'en' => 'Write a review on Trustpilot', 'de' => 'Bewertung auf Trustpilot schreiben'];
        $translations['tp.btn.view'] = ['fr' => 'Voir tous les avis Trustpilot', 'en' => 'View all Trustpilot reviews', 'de' => 'Alle Trustpilot-Bewertungen ansehen'];
        $translations['tp.link.fallback'] = ['fr' => 'Voir nos avis sur Trustpilot', 'en' => 'See our reviews on Trustpilot', 'de' => 'Unsere Bewertungen auf Trustpilot ansehen'];

        // --- FAQ ---
        $translations['faq.title'] = ['fr' => 'Questions <span class="gradient-text">Fréquentes</span>', 'en' => 'Frequently Asked <span class="gradient-text">Questions</span>', 'de' => 'Häufig gestellte <span class="gradient-text">Fragen</span>'];
        $translations['faq.subtitle'] = ['fr' => 'Tout ce que vous devez savoir pour démarrer sereinement.', 'en' => 'Everything you need to know to get started with peace of mind.', 'de' => 'Alles, was Sie wissen müssen, um beruhigt zu starten.'];
        
        $translations['faq.q1'] = ['fr' => 'Comment l\'offre gratuite fonctionne-t-elle ?', 'en' => 'How does the free plan work?', 'de' => 'Wie funktioniert das kostenlose Angebot?'];
        $translations['faq.a1'] = ['fr' => 'Notre offre gratuite est financée par nos utilisateurs Premium. Elle vous permet de concevoir, tester et faire tourner de petits projets sans aucune limite de temps ni coûts cachés.', 'en' => 'Our free plan is funded by Premium users. It allows you to design, test, and run small projects without time limits or hidden costs.', 'de' => 'Unser kostenloses Angebot wird durch Premium-Nutzer finanziert. Es ermöglicht Ihnen, kleine Projekte ohne Zeitlimit oder versteckte Kosten zu entwerfen und zu testen.'];
        
        $translations['faq.q2'] = ['fr' => 'Puis-je changer d\'offre ou migrer plus tard ?', 'en' => 'Can I upgrade or migrate later?', 'de' => 'Kann ich später upgraden oder migrieren?'];
        $translations['faq.a2'] = ['fr' => 'Tout à fait ! Vous pouvez passer d\'une formule gratuite à une version Premium à tout moment depuis votre console de gestion client sans perte de données.', 'en' => 'Absolutely! You can switch from a free plan to Premium anytime from your client console without data loss.', 'de' => 'Absolut! Sie können jederzeit ohne Datenverlust von einem kostenlosen Tarif zu Premium wechseln.'];
        
        $translations['faq.q3'] = ['fr' => 'Où sont situés vos serveurs ?', 'en' => 'Where are your servers located?', 'de' => 'Wo befinden sich Ihre Server?'];
        $translations['faq.a3'] = ['fr' => 'Nos infrastructures physiques sont hébergées dans des centres de données hautement sécurisés situés en Europe (France et Allemagne).', 'en' => 'Our physical infrastructure is hosted in highly secure data centers located in Europe (France and Germany).', 'de' => 'Unsere physische Infrastruktur wird in hochsicheren Rechenzentren in Europa (Frankreich und Deutschland) gehostet.'];
        
        $translations['faq.q4'] = ['fr' => 'La protection DDoS est-elle vraiment incluse sur toutes les offres ?', 'en' => 'Is DDoS protection really included on all plans?', 'de' => 'Ist DDoS-Schutz wirklich in allen Tarifen enthalten?'];
        $translations['faq.a4'] = ['fr' => 'Oui, absolument. Notre protection anti-DDoS est activée par défaut et gratuitement sur l\'ensemble de nos offres, y compris l\'offre gratuite.', 'en' => 'Yes, absolutely. Our anti-DDoS protection is enabled by default and free on all plans, including the free one.', 'de' => 'Ja, absolut. Unser Anti-DDoS-Schutz ist standardmäßig und kostenlos in allen Tarifen aktiviert, einschließlich dem kostenlosen.'];
        
        $translations['faq.q5'] = ['fr' => 'Puis-je utiliser mes propres bases de données ?', 'en' => 'Can I use my own databases?', 'de' => 'Kann ich eigene Datenbanken verwenden?'];
        $translations['faq.a5'] = ['fr' => 'Bien sûr ! Chaque offre inclut un accès à des bases de données MySQL/MariaDB. Vous pouvez créer et gérer vos bases directement depuis le panel.', 'en' => 'Of course! Each plan includes MySQL/MariaDB database access. You can create and manage databases directly from the panel.', 'de' => 'Natürlich! Jeder Tarif beinhaltet MySQL/MariaDB-Zugang. Sie können Datenbanken direkt im Panel erstellen und verwalten.'];
        
        $translations['faq.q6'] = ['fr' => 'Mes données sont-elles sauvegardées ?', 'en' => 'Are my data backed up?', 'de' => 'Werden meine Daten gesichert?'];
        $translations['faq.a6'] = ['fr' => 'Oui, nous effectuons des sauvegardes régulières. Nous vous recommandons toutefois de conserver une copie locale de vos fichiers importants.', 'en' => 'Yes, we perform regular backups. However, we recommend keeping a local copy of your important files.', 'de' => 'Ja, wir führen regelmäßige Backups durch. Wir empfehlen jedoch, eine lokale Kopie wichtiger Dateien aufzubewahren.'];
        
        $translations['faq.q7'] = ['fr' => 'Puis-je installer des mods ou plugins sur mon serveur Minecraft ?', 'en' => 'Can I install mods or plugins on my Minecraft server?', 'de' => 'Kann ich Mods oder Plugins auf meinem Minecraft-Server installieren?'];
        $translations['faq.a7'] = ['fr' => 'Oui ! Vous avez un accès complet à vos fichiers via SFTP. Vous pouvez installer vos mods, plugins et choisir parmi plusieurs versions (Paper, Spigot, Forge, Fabric...).', 'en' => 'Yes! You have full file access via SFTP. You can install mods, plugins, and choose from multiple versions (Paper, Spigot, Forge, Fabric...).', 'de' => 'Ja! Sie haben vollen Dateizugriff via SFTP. Sie können Mods, Plugins installieren und aus vielen Versionen wählen (Paper, Spigot, Forge, Fabric...).'];
        
        $translations['faq.q8'] = ['fr' => 'Quelle est votre garantie de disponibilité (uptime) ?', 'en' => 'What is your uptime guarantee?', 'de' => 'Welche Verfügbarkeitsgarantie bieten Sie?'];
        $translations['faq.a8'] = ['fr' => 'Nous visons un uptime de 99,9%. Nos serveurs sont monitorés 24h/24 et 7j/7 pour minimiser toute interruption.', 'en' => 'We aim for 99.9% uptime. Our servers are monitored 24/7 to minimize any downtime.', 'de' => 'Wir streben 99,9% Verfügbarkeit an. Unsere Server werden 24/7 überwacht, um Ausfallzeiten zu minimieren.'];
        
        $translations['faq.q9'] = ['fr' => 'Quels moyens de paiement acceptez-vous ?', 'en' => 'What payment methods do you accept?', 'de' => 'Welche Zahlungsmethoden akzeptieren Sie?'];
        $translations['faq.a9'] = ['fr' => 'Nous acceptons plusieurs moyens de paiement sécurisés. Consultez la page boutique pour voir la liste complète.', 'en' => 'We accept several secure payment methods. Check the shop page for the full list.', 'de' => 'Wir akzeptieren verschiedene sichere Zahlungsmethoden. Die vollständige Liste finden Sie auf der Shop-Seite.'];
        
        $translations['faq.q10'] = ['fr' => 'Puis-je être remboursé si je ne suis pas satisfait ?', 'en' => 'Can I get a refund if unsatisfied?', 'de' => 'Kann ich eine Rückerstattung erhalten, wenn ich unzufrieden bin?'];
        $translations['faq.a10'] = ['fr' => 'Nous étudions chaque demande au cas par cas. Contactez notre support via Discord pour trouver une solution adaptée.', 'en' => 'We review each request case by case. Contact our support via Discord to find a suitable solution.', 'de' => 'Wir prüfen jede Anfrage individuell. Kontaktieren Sie unseren Support via Discord für eine passende Lösung.'];
        
        $translations['faq.q11'] = ['fr' => 'Comment obtenir de l\'aide en cas de problème ?', 'en' => 'How to get help if issues arise?', 'de' => 'Wie bekomme ich Hilfe bei Problemen?'];
        $translations['faq.a11'] = ['fr' => 'Notre équipe est disponible 24/7 sur Discord. Vous pouvez aussi ouvrir un ticket depuis votre espace client.', 'en' => 'Our team is available 24/7 on Discord. You can also open a ticket from your client area.', 'de' => 'Unser Team ist 24/7 auf Discord erreichbar. Sie können auch ein Ticket im Kundenbereich eröffnen.'];
        
        $translations['faq.q12'] = ['fr' => 'Puis-je utiliser mon propre nom de domaine ?', 'en' => 'Can I use my own domain name?', 'de' => 'Kann ich meinen eigenen Domainnamen verwenden?'];
        $translations['faq.a12'] = ['fr' => 'Oui, configurez simplement les enregistrements DNS (A ou CNAME) avec l\'adresse IP fournie dans votre panel.', 'en' => 'Yes, simply configure DNS records (A or CNAME) with the IP address provided in your panel.', 'de' => 'Ja, konfigurieren Sie einfach die DNS-Einträge (A oder CNAME) mit der IP-Adresse in Ihrem Panel.'];
        
        $translations['faq.q13'] = ['fr' => 'Puis-je exécuter des tâches planifiées (cron jobs) ?', 'en' => 'Can I run scheduled tasks (cron jobs)?', 'de' => 'Kann ich geplante Aufgaben (Cron-Jobs) ausführen?'];
        $translations['faq.a13'] = ['fr' => 'Oui, cette fonctionnalité est accessible depuis le panel de gestion selon l\'offre choisie.', 'en' => 'Yes, this feature is accessible from the management panel depending on your plan.', 'de' => 'Ja, diese Funktion ist je nach Tarif im Verwaltungspanel zugänglich.'];

        $translations['faq.cta.text'] = ['fr' => 'Vous ne trouvez pas réponse à votre question ?', 'en' => 'Can\'t find the answer to your question?', 'de' => 'Finden Sie keine Antwort auf Ihre Frage?'];
        $translations['faq.cta.discord'] = ['fr' => 'Posez-la sur notre Discord', 'en' => 'Ask on our Discord', 'de' => 'Fragen Sie auf unserem Discord'];
        $translations['faq.cta.ticket'] = ['fr' => 'Ouvrir un ticket support', 'en' => 'Open a support ticket', 'de' => 'Support-Ticket öffnen'];
    }
    add_custom_translations();
}

$db_status   = false;
$is_logged_in = isset($_SESSION['user_id']);

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_TIMEOUT=>3,PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db_status = true;
    if ($is_logged_in) {
        $stmt = $pdo->prepare("SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
            $_SESSION['avatar']   = $user_data['avatar'];
        }
    }
} catch (PDOException $e) { $db_status = false; }

$sections = [
    'free'    => ['title_key' => 'tier.free.title', 'subtitle_key' => 'tier.free.subtitle', 'label_key' => 'tier.free.label', 'accent' => 'bg-green-500', 'bg' => 'bg-white/[0.01] border-y border-white/5', 'offers' => []],
    'basic'   => ['title_key' => 'tier.basic.title', 'subtitle_key' => 'tier.basic.subtitle', 'label_key' => 'tier.basic.label', 'accent' => 'bg-blue-500', 'bg' => 'bg-black/10', 'offers' => []],
    'medium'  => ['title_key' => 'tier.medium.title', 'subtitle_key' => 'tier.medium.subtitle', 'label_key' => 'tier.medium.label', 'accent' => 'bg-purple-500', 'bg' => 'bg-white/[0.02] border-y border-white/5', 'offers' => []],
    'premium' => ['title_key' => 'tier.premium.title', 'subtitle_key' => 'tier.premium.subtitle', 'label_key' => 'tier.premium.label', 'accent' => 'bg-yellow-500', 'bg' => 'bg-black/20', 'offers' => []],
    'mythic'  => ['title_key' => 'tier.mythic.title', 'subtitle_key' => 'tier.mythic.subtitle', 'label_key' => 'tier.mythic.label', 'accent' => 'bg-red-500', 'bg' => 'bg-black/30', 'offers' => []],
];

$dynamic_categories = [];

if ($db_status) {
    try {
        $cat_stmt = $pdo->query("SELECT category_slug, name_key, icon, image_url FROM categories_products WHERE is_active = 1 GROUP BY category_slug ORDER BY sort_order ASC");
        while ($c_row = $cat_stmt->fetch()) {
            $dynamic_categories[$c_row['category_slug']] = ['name_key' => $c_row['name_key'], 'icon' => $c_row['icon'], 'image_url' => $c_row['image_url']];
        }

        $stmt = $pdo->query("SELECT p.*, cp.category_slug, cp.name_key AS cat_name_key, cp.icon AS cat_icon, cp.image_url AS cat_image FROM categories_products cp LEFT JOIN products p ON p.id = cp.product_id WHERE cp.is_active = 1 AND (p.is_active = 1 OR p.id IS NULL) ORDER BY p.sort_order ASC, p.id ASC");
        $all_rows = $stmt->fetchAll();

        foreach ($all_rows as $product) {
            if (empty($product['id'])) continue;
            $slug = $product['slug'];
            $category = strtolower($product['category_slug']); 

            $tier_found = 'premium'; 
            if (strpos($slug, 'free') !== false) $tier_found = 'free';
            elseif (strpos($slug, 'basic') !== false) $tier_found = 'basic';
            elseif (strpos($slug, 'medium') !== false) $tier_found = 'medium';
            elseif (strpos($slug, 'mythic') !== false) $tier_found = 'mythic';

            $short_cat = ($category === 'minecraft') ? 'mc' : (($category === 'python') ? 'py' : (($category === 'nodejs') ? 'node' : $category));
            
            $ram_text = ($product['ram'] >= 1024) ? number_format($product['ram'] / 1024, 0) . ' GB' : $product['ram'] . ' MB';
            $disk_text = ($product['disk'] >= 1024) ? number_format($product['disk'] / 1024, 0) . ' GB' : $product['disk'] . ' MB';

            $sections[$tier_found]['offers'][] = [
                'category'   => $category,
                'slug'       => $slug,
                'name_key'   => "offer.{$short_cat}_{$tier_found}.name",
                'desc_key'   => "offer.{$short_cat}_{$tier_found}.desc",
                'price'      => ($product['type'] === 'free') ? '0€' : number_format($product['price'], 2, ',', '') . '€',
                'price_value'=> ($product['type'] === 'free') ? 0.0 : (float)$product['price'],
                'period_key' => ($product['type'] === 'free') ? 'offers.period.free' : 'offers.period.month',
                'free'       => ($product['type'] === 'free'),
                'icon'       => $product['cat_icon'] ?: 'fas fa-server',
                'image_url'  => $product['cat_image'] ?: 'https://www.4netplayers.com/images/minecraft/blog/teaser-image.jpg',
                'features'   => [
                    ['icon' => 'fas fa-memory', 'text' => $ram_text . ' RAM'],
                    ['icon' => 'fas fa-hard-drive', 'text' => $disk_text . ' SSD NVMe'],
                    ['icon' => 'fas fa-microchip', 'text' => $product['cpu'] . '% CPU'],
                    ['icon' => 'fas fa-database', 'text' => $product['databases'] . ' Database(s)']
                ]
            ];
        }
    } catch (PDOException $e) { /* Fallback silencieux */ }
}

function getCardStyle($tier_key) {
    $styles = [
        'free'    => ['label' => 'Offre Gratuite', 'badge_bg'=>'bg-green-500/20', 'badge_text'=>'text-green-400', 'badge_border'=>'border-green-500/30', 'icon_color'=>'text-green-400', 'card_border'=>'border-white/10', 'btn'=>'bg-green-500 hover:bg-green-400'],
        'basic'   => ['label' => 'Offre Basic', 'badge_bg'=>'bg-blue-500/20', 'badge_text'=>'text-blue-400', 'badge_border'=>'border-blue-500/30', 'icon_color'=>'text-blue-400', 'card_border'=>'border-blue-400/20', 'btn'=>'bg-blue-500 hover:bg-blue-400'],
        'medium'  => ['label' => 'Offre Medium', 'badge_bg'=>'bg-purple-500/20', 'badge_text'=>'text-purple-400', 'badge_border'=>'border-purple-500/30', 'icon_color'=>'text-purple-400', 'card_border'=>'border-purple-400/20', 'btn'=>'bg-purple-500 hover:bg-purple-400'],
        'premium' => ['label' => 'Offre Premium', 'badge_bg'=>'bg-yellow-500/20', 'badge_text'=>'text-yellow-400', 'badge_border'=>'border-yellow-500/30', 'icon_color'=>'text-yellow-400', 'card_border'=>'border-yellow-400/20', 'btn'=>'bg-yellow-500 hover:bg-yellow-400'],
        'mythic'  => ['label' => 'Offre Mythic', 'badge_bg'=>'bg-red-500/20', 'badge_text'=>'text-red-400', 'badge_border'=>'border-red-500/30', 'icon_color'=>'text-red-400', 'card_border'=>'border-red-400/20', 'btn'=>'bg-red-500 hover:bg-red-400']
    ];
    return $styles[$tier_key] ?? $styles['premium'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge - Hébergeur VPS, Minecraft, PHP et Node.js | Gratuit & Premium</title>
    <meta name="description" content="OrinHeberge - Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium. Des serveurs rapides, sécurisés et performants.">
    <meta name="keywords" content="hébergement VPS, serveur Minecraft, hébergement PHP, Node.js, VPS gratuit, hosting, cloud, hébergeur français">
    <meta name="author" content="OrinHeberge">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://heberge.orinstone.deepstone.fr/">
    <meta name="google-site-verification" content="eGHkY8THEtUW1G4XSC6tcuVZi_-bE86haj-kSCD0RuE" />

    <!-- Open Graph -->
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="OrinHeberge - Hébergeur VPS, Minecraft, PHP et Node.js">
    <meta property="og:description" content="Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium.">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr/">
    <meta property="og:site_name" content="OrinHeberge">
    <meta property="og:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="OrinHeberge - Hébergeur VPS, Minecraft, PHP et Node.js">
    <meta name="twitter:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">

    <meta name="theme-color" content="#6366f1">
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.png">
    <link rel="apple-touch-icon" href="https://heberge.orinstone.deepstone.fr/favicon.png">

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "OrinHeberge",
      "url": "https://heberge.orinstone.deepstone.fr/",
      "potentialAction": { "@type": "SearchAction", "target": "https://heberge.orinstone.deepstone.fr/search?q={search_term_string}", "query-input": "required name=search_term_string" }
    }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    
    <style>
        *{box-sizing:border-box;}
        body{background:#060911;scroll-behavior:smooth;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;}
        .glass{background:rgba(255,255,255,0.03);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.06);}
        .gradient-text{background:linear-gradient(135deg,#38bdf8 0%,#a78bfa 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .card-hover{transition:transform .3s cubic-bezier(.4,0,.2,1),box-shadow .3s,border-color .3s;}
        .card-hover:hover{transform:translateY(-6px);box-shadow:0 32px 64px rgba(0,0,0,.4);}
        .tab-btn{padding:.5rem 1.25rem;border-radius:9999px;font-size:.78rem;font-weight:700;transition:all .2s;border:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.025);color:#6b7280;cursor:pointer;}
        .tab-btn:hover{background:rgba(255,255,255,.06);color:#d1d5db;}
        .tab-btn.active{background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.3);color:#38bdf8;}
        #cat-view { display: none; }
        #cat-view .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .hero-glow {position:absolute;border-radius:50%;filter:blur(140px);pointer-events:none;animation:pulse-orb 8s ease-in-out infinite;z-index: 1;}
        @keyframes pulse-orb{0%,100%{opacity:.4;transform:translate(-50%, -50%) scale(1);}50%{opacity:.7;transform:translate(-50%, -50%) scale(1.1);}}
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col antialiased">
<?php $active_nav = 'home'; include __DIR__ . '/inc/navbar.php'; ?>

<main class="flex-grow">

   <!-- AVERTISSEMENT 1 — Achat de jeux -->
<section class="relative z-20 px-6 pt-6 pb-2">
    <div class="max-w-4xl mx-auto glass rounded-xl border border-amber-500/30 bg-amber-500/[0.04] backdrop-blur-md overflow-hidden shadow-lg shadow-amber-900/10 animate-fade-in">
        <div class="absolute top-0 left-0 w-1 h-full bg-amber-500"></div>
        <div class="p-5 md:p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-lg">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <div class="flex-grow">
                <h3 class="text-amber-400 font-bold text-sm mb-1 uppercase tracking-wide"><?php echo t('warning.game_purchase.title'); ?></h3>
                <p class="text-gray-300 text-sm leading-relaxed"><?php echo t('warning.game_purchase.text'); ?></p>
                <p class="text-amber-500/80 text-xs font-semibold mt-2 italic">— <?php echo t('warning.signoff'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- AVERTISSEMENT 2 — Notice temporaire -->
<section class="relative z-20 px-6 pt-2 pb-2">
    <div class="max-w-4xl mx-auto glass rounded-xl border border-amber-500/30 bg-amber-500/[0.04] backdrop-blur-md overflow-hidden shadow-lg shadow-amber-900/10 animate-fade-in">
        <div class="absolute top-0 left-0 w-1 h-full bg-amber-500"></div>
        <div class="p-5 md:p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-lg">
                <i class="fas fa-circle-info"></i>
            </div>
            <div class="flex-grow">
                <h3 class="text-amber-400 font-bold text-sm mb-1 uppercase tracking-wide"><?php echo t('warning.temp_notice.title'); ?></h3>
                <p class="text-gray-300 text-sm leading-relaxed"><?php echo t('warning.temp_notice.text'); ?></p>
                <p class="text-amber-500/80 text-xs font-semibold mt-2 italic">— <?php echo t('warning.temp_notice.signoff'); ?></p>
            </div>
        </div>
    </div>
</section>

    <!-- HERO -->
    <section class="relative text-center py-28 md:py-40 px-6 overflow-hidden flex items-center justify-center">
        <div class="hero-glow w-[500px] h-[500px] bg-sky-500/10 top-1/2 left-1/2"></div>
        <div class="hero-glow w-[300px] h-[300px] bg-purple-500/10 top-1/3 left-1/3"></div>
        <div class="relative z-10 max-w-4xl mx-auto">
            <div class="inline-flex items-center gap-2 bg-sky-500/10 text-sky-400 border border-sky-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-8 tracking-wide">
                <span class="h-2 w-2 rounded-full <?php echo $db_status ? 'bg-green-400 animate-pulse' : 'bg-red-400'; ?>"></span>
                <?php echo $db_status ? t('hero.status.online') : t('hero.status.offline'); ?>
            </div>
            <h1 class="text-6xl md:text-8xl font-black tracking-tight leading-[1.1] mb-6"><span class="gradient-text">Orin</span>Heberge</h1>
            <p class="text-gray-400 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed mb-10">
                <?php echo t('hero.subtitle'); ?><br>
                <span class="text-gray-500 text-base"><?php echo t('hero.subtext'); ?></span>
            </p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="#offres" class="bg-sky-600 hover:bg-sky-500 text-white px-8 py-4 rounded-2xl font-bold transition shadow-xl shadow-sky-900/30 text-sm flex items-center gap-2">
                    <i class="fas fa-rocket"></i> <?php echo t('hero.btn.offers'); ?>
                </a>
                <a href="<?php echo $is_logged_in ? '/client/servers/' : '/register/'; ?>" class="glass hover:bg-white/[0.07] px-8 py-4 rounded-2xl font-bold transition text-sm flex items-center gap-2">
                    <?php if ($is_logged_in): ?><i class="fas fa-server text-sky-400"></i> <?php echo t('hero.btn.client'); ?><?php else: ?><i class="fas fa-user-plus text-sky-400"></i> <?php echo t('hero.btn.register'); ?><?php endif; ?>
                </a>
            </div>
        </div>
    </section>

    <!-- STATS RAPIDES -->
    <section class="py-8 px-6 border-y border-white/[0.04]">
        <div class="max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div><p class="text-3xl font-black gradient-text"><i class="fas fa-bolt text-2xl mb-1 block"></i>100%</p><p class="text-gray-500 text-xs mt-1"><?php echo t('stat.ssd'); ?></p></div>
            <div><p class="text-3xl font-black gradient-text"><i class="fas fa-wallet text-2xl mb-1 block"></i>0€</p><p class="text-gray-500 text-xs mt-1"><?php echo t('stat.free'); ?></p></div>
            <div><p class="text-3xl font-black gradient-text"><i class="fas fa-headset text-2xl mb-1 block"></i>24/7</p><p class="text-gray-500 text-xs mt-1"><?php echo t('stat.support'); ?></p></div>
            <div><p class="text-3xl font-black gradient-text"><i class="fas fa-shield-alt text-2xl mb-1 block"></i>DDoS</p><p class="text-gray-500 text-xs mt-1"><?php echo t('stat.ddos'); ?></p></div>
        </div>
    </section>

    <!-- OFFRES -->
    <section id="offres" class="py-20 px-6 max-w-7xl mx-auto scroll-mt-10">
        <header class="text-center py-16 px-6 relative overflow-hidden">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-sky-500/10 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="inline-flex items-center gap-2 bg-sky-500/10 text-sky-400 border border-sky-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-5"><i class="fas fa-tags"></i> <?php echo t('offers.tab.all'); ?></div>
            <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none gradient-text mb-4"><?php echo t('offers.title'); ?></h1>
            <p class="text-gray-400 max-w-xl mx-auto text-lg"><?php echo t('offers.subtitle'); ?></p>
            <div class="max-w-5xl mx-auto flex flex-wrap justify-center gap-2.5 px-4 mt-10">
                <button onclick="filterCategory('all')" id="tab-all" class="tab-btn active"><i class="fas fa-th-large text-xs"></i> <?php echo t('offers.tab.all'); ?></button>
                <?php foreach($dynamic_categories as $slug => $ci): ?>
                <button onclick="filterCategory('<?php echo htmlspecialchars($slug); ?>')" id="tab-<?php echo htmlspecialchars($slug); ?>" class="tab-btn"><i class="<?php echo htmlspecialchars($ci['icon']); ?> text-xs"></i> <?php echo t($ci['name_key']); ?></button>
                <?php endforeach; ?>
            </div>
        </header>

        <section id="cat-view" class="py-20 px-6">
            <div class="text-center mb-12"><h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3 gradient-text" id="cat-view-title"></h2><div class="h-1 w-20 bg-sky-500 mx-auto rounded-full"></div></div>
            <div class="max-w-7xl mx-auto cat-grid" id="cat-view-grid"></div>
        </section>

        <div id="all-sections">
        <?php foreach ($sections as $tier_key => $tier_data): ?>
            <?php if (empty($tier_data['offers'])) continue; ?>
            <section class="offers-section py-20 px-6 <?php echo $tier_data['bg']; ?>">
                <div class="text-center mb-16"><h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3"><?php echo t($tier_data['title_key']); ?></h2><div class="h-1 w-20 <?php echo $tier_data['accent']; ?> mx-auto rounded-full"></div></div>
                <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <?php foreach ($tier_data['offers'] as $offer): ?>
                <?php
                    $style = getCardStyle($tier_key);
                    $price_num = $offer['free'] ? 0 : $offer['price_value'];
                    $btn_text = $is_logged_in ? ($offer['free'] ? t('btn.deploy') : t('btn.buy')) : t('btn.login_to_buy');
                    $link = $is_logged_in ? '/shop/cart/' : '/login/';
                ?>
                <div data-category="<?php echo htmlspecialchars($offer['category']); ?>" data-price="<?php echo $price_num; ?>" class="offer-card glass rounded-2xl border <?php echo $style['card_border']; ?> flex flex-col card-hover overflow-hidden relative group">
                    <div class="h-36 w-full bg-cover bg-center relative transition-transform duration-500 group-hover:scale-105" style="background-image:url('<?php echo htmlspecialchars($offer['image_url']); ?>')">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#070a13] via-transparent to-transparent"></div>
                        <div class="absolute top-3 left-3 right-3 flex justify-between items-center">
                            <span class="<?php echo $style['badge_bg'].' '.$style['badge_text'].' '.$style['badge_border']; ?> px-2.5 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wide backdrop-blur-md"><?php echo t($tier_data['label_key']); ?></span>
                            <i class="<?php echo htmlspecialchars($offer['icon']).' '.$style['icon_color']; ?> text-2xl drop-shadow-lg transform group-hover:rotate-12 transition-transform"></i>
                        </div>
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <h3 class="text-base font-bold text-white mb-1"><?php echo t($offer['name_key']); ?></h3>
                        <p class="text-gray-400 text-xs flex-grow mb-4 leading-relaxed"><?php echo t($offer['desc_key']); ?></p>
                        <div class="flex items-baseline mb-4"><span class="text-2xl font-black text-white"><?php echo $offer['price']; ?></span><span class="text-gray-500 text-xs ml-1"><?php echo t($offer['period_key']); ?></span></div>
                        <ul class="space-y-2 text-xs text-gray-300 border-t border-white/5 pt-3 mb-4">
                            <?php foreach ($offer['features'] as $feat): ?><li class="flex items-center gap-2"><i class="<?php echo $feat['icon'].' '.$style['icon_color']; ?> w-4 text-center"></i><?php echo $feat['text']; ?></li><?php endforeach; ?>
                        </ul>
                        <?php if ($is_logged_in): ?>
                            <form method="post" action="/shop/cart/">
                                <input type="hidden" name="action" value="add_item">
                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($offer['slug']); ?>">
                                <input type="hidden" name="name" value="<?php echo htmlspecialchars(t($offer['name_key'])); ?>">
                                <input type="hidden" name="price" value="<?php echo htmlspecialchars((string)$offer['price_value']); ?>">
                                <button type="submit" class="w-full <?php echo $style['btn']; ?> text-slate-950 font-bold py-2.5 rounded-xl text-sm flex items-center justify-center gap-2 transition-all hover:shadow-lg hover:shadow-current/20"><i class="fas fa-shopping-cart"></i> <?php echo $btn_text; ?></button>
                            </form>
                        <?php else: ?>
                            <a href="<?php echo $link; ?>" class="w-full <?php echo $style['btn']; ?> text-slate-950 font-bold py-2.5 rounded-xl text-sm text-center block flex items-center justify-center gap-2 transition-all hover:shadow-lg hover:shadow-current/20"><i class="fas fa-lock"></i> <?php echo $btn_text; ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        </div>
    </section>

    <!-- ENVIRONNEMENTS SUPPORTÉS -->
    <section class="py-10 px-6 border-b border-white/[0.03]">
        <div class="max-w-7xl mx-auto">
            <p class="text-center text-gray-500 text-xs font-bold uppercase tracking-widest mb-6"><?php echo t('env.title'); ?></p>
            <div class="flex flex-wrap justify-center gap-8 md:gap-12 opacity-60 grayscale hover:grayscale-0 transition-all duration-500">
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fab fa-java text-4xl text-orange-500 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">Java</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fab fa-node-js text-4xl text-green-500 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">Node.js</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fab fa-python text-4xl text-blue-400 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">Python</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fab fa-php text-4xl text-indigo-400 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">PHP</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fas fa-cube text-4xl text-gray-300 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">Minecraft</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fas fa-car text-4xl text-red-500 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">FiveM</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fas fa-dragon text-4xl text-emerald-500 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">Terraria</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fas fa-cloud text-4xl text-sky-300 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">Hytale</span></div>
                <div class="flex flex-col items-center gap-2 group cursor-default"><i class="fab fa-linux text-4xl text-yellow-500 group-hover:scale-110 transition"></i><span class="text-xs font-bold text-gray-400">Linux</span></div>
            </div>
        </div>
    </section>

    <!-- PERFORMANCES -->
    <section class="py-16 px-6 bg-white/[0.01] border-y border-white/[0.03]">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="text-xs font-bold text-sky-400 tracking-widest uppercase mb-2 block"><i class="fas fa-tachometer-alt mr-1"></i> <?php echo t('perf.badge'); ?></span>
                <h3 class="text-3xl md:text-4xl font-black mb-6 leading-tight"><?php echo t('perf.title'); ?></h3>
                <p class="text-gray-400 text-sm leading-relaxed mb-6"><?php echo t('perf.desc'); ?></p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-white/[0.03] bg-white/[0.01] hover:border-sky-500/30 transition"><div class="text-white font-bold text-sm mb-1"><i class="fas fa-network-wired text-sky-400 mr-1.5"></i> <?php echo t('perf.net.title'); ?></div><p class="text-gray-500 text-xs"><?php echo t('perf.net.desc'); ?></p></div>
                    <div class="p-4 rounded-xl border border-white/[0.03] bg-white/[0.01] hover:border-purple-500/30 transition"><div class="text-white font-bold text-sm mb-1"><i class="fas fa-memory text-purple-400 mr-1.5"></i> <?php echo t('perf.ram.title'); ?></div><p class="text-gray-500 text-xs"><?php echo t('perf.ram.desc'); ?></p></div>
                </div>
            </div>
            <div class="relative flex justify-center">
                <div class="absolute w-72 h-72 bg-indigo-500/5 filter blur-3xl rounded-full top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></div>
                <div class="glass p-8 rounded-2xl border border-white/[0.06] max-w-md w-full font-mono text-xs text-gray-400 space-y-3 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-white/[0.05] pb-2"><span class="text-gray-500"><i class="fas fa-terminal mr-2"></i><?php echo t('perf.node.status'); ?></span><span class="text-green-400 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-green-400 animate-ping"></span> <?php echo t('perf.node.online'); ?></span></div>
                    <p><span class="text-purple-400">~ $</span> screen -r orin-node-01</p>
                    <p class="text-gray-500">[OS]: Ubuntu 24.04 LTS</p>
                    <p class="text-gray-500">[CPU]: AMD Ryzen 9 7950X @ 4.5 GHz</p>
                    <p class="text-gray-500">[Disk]: NVMe PCIe Gen4 x4 Read: 7000MB/s</p>
                    <p class="text-sky-400">>> Anti-DDoS Mitigation Layers ACTIVE</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ÉQUIPE -->
    <section class="py-20 px-6 max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-black mb-3"><?php echo t('team.title'); ?></h2>
            <p class="text-gray-500 max-w-lg mx-auto text-sm"><?php echo t('team.subtitle'); ?></p>
        </div> 
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 items-start">
            
            <!-- Mathéo Favier -->
            <div class="glass p-6 rounded-2xl border border-white/[0.05] text-center flex flex-col items-center group hover:border-sky-500/30 transition">
                <div class="relative mb-4">
                    <div class="w-20 h-20 bg-gradient-to-tr from-sky-400 to-purple-500 rounded-full p-0.5 shadow-xl group-hover:scale-105 transition">
                        <img src="/img/staff/Mathéo-Favier.jpg" alt="Avatar de Mathéo Favier" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]" title="Online"></span>
                </div>
                <h4 class="font-bold text-white text-lg">Mathéo Favier</h4>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-sky-500/10 text-sky-400 border border-sky-500/20 mt-1 mb-3"><?php echo t('team.role.founder'); ?></span>
                <p class="text-gray-500 text-xs leading-relaxed max-w-xs"><?php echo t('team.desc.matheo'); ?></p>

                <div class="flex flex-wrap justify-center gap-3 mt-4 text-gray-400 text-sm">
                    <a href="https://github.com/metal54400" target="_blank" rel="noopener noreferrer" title="GitHub" class="hover:text-white transition hover:scale-110"><i class="fab fa-github"></i></a>
                    <a href="https://tiktok.com/@metal54400" target="_blank" rel="noopener noreferrer" title="TikTok" class="hover:text-pink-400 transition hover:scale-110"><i class="fab fa-tiktok"></i></a>
                    <a href="https://open.spotify.com/user/31lwi37n2kwhkyu34hs6frdj4scq" target="_blank" rel="noopener noreferrer" title="Spotify" class="hover:text-green-400 transition hover:scale-110"><i class="fab fa-spotify"></i></a>
                    <a href="https://instagram.com/matheocillierfavier54" target="_blank" rel="noopener noreferrer" title="Instagram" class="hover:text-pink-500 transition hover:scale-110"><i class="fab fa-instagram"></i></a>
                    <a href="https://snapchat.com/add/matheo54400" target="_blank" rel="noopener noreferrer" title="Snapchat" class="hover:text-yellow-300 transition hover:scale-110"><i class="fab fa-snapchat"></i></a>
                    <a href="https://x.com/MCillierFaviier" target="_blank" rel="noopener noreferrer" title="X" class="hover:text-sky-400 transition hover:scale-110"><svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                    <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" title="Facebook" class="hover:text-blue-400 transition hover:scale-110"><i class="fab fa-facebook"></i></a>
                    <a href="https://portfolio.deepstone.fr" target="_blank" rel="noopener noreferrer" title="Portfolio" class="hover:text-sky-400 transition hover:scale-110"><i class="fas fa-globe"></i></a>
                </div>

                <a href="/discord/" class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-medium hover:bg-indigo-500/20 hover:scale-105 transition">
                    <i class="fab fa-discord"></i> <?php echo t('team.discord.link'); ?>
                </a>
            </div>

            <!-- WixyMc -->
            <div class="glass p-6 rounded-2xl border border-white/[0.05] text-center flex flex-col items-center group hover:border-purple-500/30 transition">
                <div class="relative mb-4">
                    <div class="w-20 h-20 bg-gradient-to-tr from-purple-500 to-pink-500 rounded-full p-0.5 shadow-xl group-hover:scale-105 transition">
                        <img src="/img/staff/WixyMc.png" alt="Avatar de WixyMc" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]" title="Online"></span>
                </div>
                <h4 class="font-bold text-white text-lg">WixyMc</h4>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 mt-1 mb-3"><?php echo t('team.role.cofounder'); ?></span>
                <p class="text-gray-500 text-xs leading-relaxed max-w-xs"><?php echo t('team.desc.wixy'); ?></p>
                <div class="flex gap-3 mt-4 text-gray-400 text-sm">
                    <a href="https://github.com/WixyMC" target="_blank" rel="noopener noreferrer" title="GitHub" class="hover:text-white transition hover:scale-110"><i class="fab fa-github"></i></a>
                    <a href="/discord/" title="Discord" class="hover:text-purple-400 transition hover:scale-110"><i class="fab fa-discord"></i></a>
                </div>
            </div>

            <!-- Nexium + NitroHebergeur -->
            <div class="glass p-6 rounded-2xl border border-white/[0.05] flex flex-col group hover:border-amber-500/30 transition sm:col-span-2 lg:col-span-1 relative overflow-hidden">
                <div class="text-center flex flex-col items-center">
                    <div class="relative mb-4">
                        <div class="w-20 h-20 bg-gradient-to-tr from-amber-400 to-orange-500 rounded-full p-0.5 shadow-xl group-hover:scale-105 transition">
                            <img src="/img/staff/Nexium.webp" alt="Avatar de Nexium" class="w-full h-full object-cover rounded-full bg-[#060911]">
                        </div>
                        <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]" title="Online"></span>
                    </div>
                    <h4 class="font-bold text-white text-lg">Nexium</h4>
                    <div class="flex flex-wrap justify-center gap-2 mt-1 mb-3">
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20"><?php echo t('team.role.nexium.dir'); ?></span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><?php echo t('team.role.nexium.partner'); ?></span>
                    </div>
                    <p class="text-gray-500 text-xs leading-relaxed max-w-xs"><?php echo t('team.desc.nexium'); ?></p>
                    <div class="flex gap-3 mt-4 text-gray-400 text-sm">
                        <a href="/discord/" title="Discord" class="hover:text-amber-400 transition hover:scale-110"><i class="fab fa-discord"></i></a>
                        <a href="https://github.com/neyrixmc" target="_blank" rel="noopener noreferrer" title="GitHub" class="hover:text-white transition hover:scale-110"><i class="fab fa-github"></i></a>
                        <a href="https://www.nitrohebergeur.fr/" target="_blank" rel="noopener noreferrer" title="NitroHebergeur" class="hover:text-sky-400 transition hover:scale-110"><i class="fas fa-globe"></i></a>
                    </div>
                </div>

                <div class="my-5 h-px w-full bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>

                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/[0.03] p-4 text-center flex flex-col items-center">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <i class="fas fa-bolt text-emerald-400 text-sm"></i>
                        <h5 class="font-bold text-white text-sm">NitroHebergeur</h5>
                    </div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-400 mb-2"><?php echo t('partner.nitro.tag'); ?></p>
                    <p class="text-gray-400 text-[11px] leading-relaxed mb-3"><?php echo t('partner.nitro.desc'); ?></p>
                    <div class="flex flex-wrap justify-center gap-1.5 mb-4">
                        <span class="text-[10px] font-medium px-2 py-1 rounded-md bg-white/[0.04] border border-white/[0.06] text-gray-300"><i class="fas fa-globe text-emerald-400 mr-1"></i><?php echo t('partner.nitro.web'); ?></span>
                        <span class="text-[10px] font-medium px-2 py-1 rounded-md bg-white/[0.04] border border-white/[0.06] text-gray-300"><i class="fas fa-cloud text-emerald-400 mr-1"></i><?php echo t('partner.nitro.cloud'); ?></span>
                        <span class="text-[10px] font-medium px-2 py-1 rounded-md bg-white/[0.04] border border-white/[0.06] text-gray-300"><i class="fas fa-server text-emerald-400 mr-1"></i>VPS</span>
                    </div>
                    <a href="/partenaires/" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold hover:bg-emerald-500/20 hover:text-emerald-300 hover:gap-3 transition-all">
                        <?php echo t('partner.btn.more'); ?> <i class="fas fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- POURQUOI NOUS -->
    <section class="py-20 px-6 max-w-7xl mx-auto">
        <div class="text-center mb-12"><h2 class="text-3xl md:text-4xl font-black mb-3"><?php echo t('why.title'); ?></h2><p class="text-gray-500 max-w-lg mx-auto text-sm"><?php echo t('why.subtitle'); ?></p></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05] group"><div class="w-12 h-12 bg-sky-500/10 rounded-xl flex items-center justify-center mb-4 group-hover:bg-sky-500/20 transition"><i class="fas fa-microchip text-sky-400 text-xl"></i></div><h4 class="font-bold text-white mb-2"><?php echo t('why.cpu.title'); ?></h4><p class="text-gray-500 text-xs leading-relaxed"><?php echo t('why.cpu.desc'); ?></p></div>
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05] group"><div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center mb-4 group-hover:bg-purple-500/20 transition"><i class="fas fa-layer-group text-purple-400 text-xl"></i></div><h4 class="font-bold text-white mb-2"><?php echo t('why.multi.title'); ?></h4><p class="text-gray-500 text-xs leading-relaxed"><?php echo t('why.multi.desc'); ?></p></div>
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05] group"><div class="w-12 h-12 bg-green-500/10 rounded-xl flex items-center justify-center mb-4 group-hover:bg-green-500/20 transition"><i class="fas fa-hand-holding-dollar text-green-400 text-xl"></i></div><h4 class="font-bold text-white mb-2"><?php echo t('why.free.title'); ?></h4><p class="text-gray-500 text-xs leading-relaxed"><?php echo t('why.free.desc'); ?></p></div>
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05] group"><div class="w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center mb-4 group-hover:bg-amber-500/20 transition"><i class="fas fa-shield-halved text-amber-400 text-xl"></i></div><h4 class="font-bold text-white mb-2"><?php echo t('why.ddos.title'); ?></h4><p class="text-gray-500 text-xs leading-relaxed"><?php echo t('why.ddos.desc'); ?></p></div>
        </div>
    </section>

    <!-- AVIS CLIENTS -->
    <section class="py-20 px-6 max-w-5xl mx-auto border-t border-white/[0.03]">
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 bg-pink-500/10 text-pink-400 border border-pink-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-4"><i class="fas fa-star"></i> <?php echo t('reviews.badge'); ?></div>
            <h2 class="text-3xl font-black mb-3"><?php echo t('reviews.title'); ?></h2>
            <p class="text-gray-500 text-sm"><?php echo t('reviews.subtitle'); ?></p>
            
            <div id="review-stats" class="hidden mt-6 flex items-center justify-center gap-6">
                <div class="flex items-center gap-2">
                    <span id="avg-rating" class="text-3xl font-black gradient-text">0</span>
                    <div class="text-left">
                        <div id="avg-stars" class="text-yellow-400 text-sm"></div>
                        <span id="total-count" class="text-gray-500 text-xs">0 <?php echo t('reviews.count'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div id="reviews-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div class="glass p-5 rounded-xl border border-white/[0.05] animate-pulse"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 rounded-full bg-white/10"></div><div class="flex-1 space-y-2"><div class="h-3 bg-white/10 rounded w-24"></div><div class="h-2 bg-white/10 rounded w-16"></div></div></div><div class="h-2 bg-white/10 rounded w-full mb-1"></div><div class="h-2 bg-white/10 rounded w-3/4"></div></div>
            <div class="glass p-5 rounded-xl border border-white/[0.05] animate-pulse"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 rounded-full bg-white/10"></div><div class="flex-1 space-y-2"><div class="h-3 bg-white/10 rounded w-20"></div><div class="h-2 bg-white/10 rounded w-14"></div></div></div><div class="h-2 bg-white/10 rounded w-full mb-1"></div><div class="h-2 bg-white/10 rounded w-2/3"></div></div>
        </div>

        <div id="no-reviews" class="hidden text-center py-8 text-gray-500 text-sm"><i class="fas fa-comment-slash text-3xl mb-3 opacity-30"></i><p><?php echo t('reviews.empty'); ?></p></div>

        <div id="load-more-wrap" class="hidden text-center mb-12">
            <button onclick="loadMoreReviews()" id="load-more-btn" class="glass hover:bg-white/[0.07] border border-white/10 text-gray-300 hover:text-white px-6 py-2.5 rounded-xl text-sm font-bold transition flex items-center gap-2 mx-auto"><i class="fas fa-chevron-down"></i> <?php echo t('reviews.load_more'); ?></button>
        </div>

        <div class="glass rounded-2xl p-6 md:p-8 border border-white/[0.08] relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-pink-500/10 blur-3xl rounded-full pointer-events-none"></div>
            <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="fas fa-pen-fancy text-pink-400"></i> <?php echo t('reviews.form.title'); ?></h3>

            <form id="review-form" class="space-y-4 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1"><?php echo t('reviews.form.name'); ?></label>
                        <input type="text" id="review-name" required maxlength="50" class="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-sm text-white focus:border-pink-500 focus:outline-none transition" placeholder="Ex: Alex">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-1"><?php echo t('reviews.form.rating'); ?></label>
                        <select id="review-rating" class="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-sm text-white focus:border-pink-500 focus:outline-none transition">
                            <option value="5"><?php echo t('reviews.opt.5'); ?></option>
                            <option value="4"><?php echo t('reviews.opt.4'); ?></option>
                            <option value="3"><?php echo t('reviews.opt.3'); ?></option>
                            <option value="2"><?php echo t('reviews.opt.2'); ?></option>
                            <option value="1"><?php echo t('reviews.opt.1'); ?></option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1"><?php echo t('reviews.form.comment'); ?></label>
                    <textarea id="review-comment" rows="3" required minlength="10" maxlength="500" class="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-sm text-white focus:border-pink-500 focus:outline-none transition" placeholder="..."></textarea>
                    <p class="text-xs text-gray-600 mt-1 text-right"><span id="char-count">0</span>/500</p>
                </div>

                <div id="review-success" class="hidden bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-3 rounded-lg text-sm flex items-center gap-2"><i class="fas fa-check-circle"></i> <?php echo t('reviews.success'); ?></div>
                <div id="review-error" class="hidden bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-lg text-sm flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> <span id="review-error-text"></span></div>

                <div class="flex justify-end pt-2">
                    <button type="submit" id="review-submit-btn" class="bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-400 hover:to-purple-500 text-white font-bold py-2 px-6 rounded-lg text-sm transition shadow-lg shadow-pink-900/20 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-paper-plane"></i> <span><?php echo t('reviews.submit'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- TRUSTPILOT -->
    <section class="py-20 px-6 max-w-7xl mx-auto border-t border-white/[0.03]">
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 bg-[#00B67A]/10 text-[#00B67A] border border-[#00B67A]/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-4">
                <i class="fas fa-award"></i> <?php echo t('tp.badge'); ?>
            </div>
            <h2 class="text-3xl md:text-4xl font-black mb-3"><?php echo t('tp.title'); ?></h2>
            <p class="text-gray-400 max-w-2xl mx-auto text-sm"><?php echo t('tp.subtitle'); ?></p>
        </div>

        <div class="glass rounded-2xl border border-white/[0.08] p-6 md:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-40 h-40 bg-[#00B67A]/5 blur-3xl rounded-full pointer-events-none"></div>
            
            <div class="trustpilot-widget" 
                 data-locale="fr-FR" 
                 data-template-id="5419b6ffb0d04a076446a9af" 
                 data-businessunit-id="688b3a7f4d3c2b1a0e9f8d7c" 
                 data-style-height="200px" 
                 data-style-width="100%" 
                 data-theme="dark" 
                 data-style-alignment="center"
                 data-stars="1,2,3,4,5"
                 style="min-height: 200px;">
                <a href="https://fr.trustpilot.com/review/heberge.orinstone.deepstone.fr" target="_blank" rel="noopener noreferrer"><?php echo t('tp.link.fallback'); ?></a>
            </div>

            <div id="tp-fallback" class="hidden text-center py-12">
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-[#00B67A]/10 flex items-center justify-center">
                    <i class="fas fa-star text-[#00B67A] text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2"><?php echo t('tp.fallback.title'); ?></h3>
                <p class="text-gray-400 text-sm max-w-md mx-auto mb-6"><?php echo t('tp.fallback.desc'); ?></p>
                <a href="https://fr.trustpilot.com/evaluate/heberge.orinstone.deepstone.fr" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 bg-[#00B67A] hover:bg-[#00A068] text-white font-bold py-3 px-8 rounded-xl text-sm transition shadow-lg shadow-emerald-900/30 hover:scale-105 transform duration-200">
                    <i class="fas fa-pen-to-square"></i> <?php echo t('tp.btn.write'); ?>
                </a>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-center items-center gap-4 mt-8">
            <a href="https://fr.trustpilot.com/evaluate/heberge.orinstone.deepstone.fr" target="_blank" rel="noopener noreferrer" class="bg-[#00B67A] hover:bg-[#00A068] text-white font-bold py-3 px-8 rounded-xl text-sm transition shadow-lg shadow-emerald-900/30 flex items-center gap-2 hover:scale-105 transform duration-200">
                <i class="fas fa-star"></i> <?php echo t('tp.btn.write'); ?>
            </a>
            <a href="https://fr.trustpilot.com/review/heberge.orinstone.deepstone.fr" target="_blank" rel="noopener noreferrer" class="glass hover:bg-white/[0.07] border border-white/10 text-gray-300 hover:text-white font-bold py-3 px-8 rounded-xl text-sm transition flex items-center gap-2">
                <i class="fas fa-external-link-alt"></i> <?php echo t('tp.btn.view'); ?>
            </a>
        </div>
    </section>

    <script type="text/javascript" src="//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js" async></script>

    <!-- FAQ -->
    <section class="py-20 px-6 max-w-4xl mx-auto border-t border-white/[0.03]">
        <div class="text-center mb-12"><h2 class="text-3xl font-black mb-3"><?php echo t('faq.title'); ?></h2><p class="text-gray-500 text-sm"><?php echo t('faq.subtitle'); ?></p></div>
        <div class="space-y-4">
            <?php for ($i = 1; $i <= 13; $i++): ?>
            <details class="glass p-5 rounded-xl border border-white/[0.05] group transition-all duration-300">
                <summary class="font-bold text-white text-sm flex justify-between items-center cursor-pointer list-none select-none"><span class="flex items-center gap-2"><i class="fas fa-question-circle text-sky-400"></i> <?php echo t("faq.q{$i}"); ?></span><span class="transition group-open:rotate-180"><i class="fas fa-chevron-down text-gray-500 text-xs"></i></span></summary>
                <p class="text-gray-400 text-xs leading-relaxed mt-3 pt-3 border-t border-white/[0.03]"><?php echo t("faq.a{$i}"); ?></p>
            </details>
            <?php endfor; ?>
        </div>

        <div class="mt-10 text-center">
            <p class="text-gray-500 text-xs mb-4"><?php echo t('faq.cta.text'); ?></p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="/discord/" target="_blank" class="inline-flex items-center gap-2 glass hover:bg-white/[0.07] border border-white/10 text-gray-300 hover:text-white px-6 py-3 rounded-xl text-sm font-bold transition">
                    <i class="fab fa-discord text-[#5865F2]"></i> <?php echo t('faq.cta.discord'); ?>
                </a>
                <a href="/support/" target="_blank" class="inline-flex items-center gap-2 glass hover:bg-white/[0.07] border border-white/10 text-gray-300 hover:text-white px-6 py-3 rounded-xl text-sm font-bold transition">
                    <i class="fas fa-headset text-sky-400"></i> <?php echo t('faq.cta.ticket'); ?>
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/inc/footer.php'; ?>

<div class="fixed bottom-6 right-6 z-40">
    <a href="/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
    </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/cookie.php'; ?>

<script>
    window.categoryLabels = <?php echo json_encode(array_map(fn($cat) => t($cat['name_key']), $dynamic_categories), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/inc/accueil.js"></script>
</body>
</html>