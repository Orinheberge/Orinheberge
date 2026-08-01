/**
 * OrinHeberge — Language Switcher Logic
 * Gère la persistance de la langue via localStorage et synchronise avec l'URL/PHP.
 */

document.addEventListener('DOMContentLoaded', function() {
    const STORAGE_KEY = 'orinheberge_lang';
    
    // 1. Récupérer la langue stockée ou définir la défaut (fr)
    let storedLang = localStorage.getItem(STORAGE_KEY);
    
    // 2. Vérifier si l'URL actuelle force déjà une langue (?lang=xx)
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = urlParams.get('lang');

    // 3. Logique de redirection initiale
    // Si aucune langue n'est dans l'URL mais qu'il y en a une en mémoire, on redirige silencieusement
    if (!urlLang && storedLang) {
        // On construit la nouvelle URL avec le paramètre lang
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('lang', storedLang);
        
        // Optionnel : Pour éviter une boucle de refresh si on est déjà sur la bonne page mais sans paramètre
        // On ne redirige que si ce n'est pas la page d'accueil pure ou si on veut forcer l'affichage du paramètre
        // Ici, on laisse le PHP gérer la redirection propre via header('Location'), 
        // donc le JS sert surtout à "rappeler" le choix au serveur lors du premier chargement.
        
        // Note: Comme ton PHP fait une redirection 302 propre, le JS ci-dessous est surtout utile 
        // pour les appels AJAX futurs ou pour pré-sélectionner visuellement si tu changes d'approche.
        // Mais pour être robuste, on peut forcer l'ajout du paramètre si absent :
        /*
        window.history.replaceState(null, '', newUrl.toString());
        */
    }

    // 4. Gestion des clics sur les liens de langue
    const langLinks = document.querySelectorAll('a[href*="?lang="]');
    
    langLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Extraire la langue cible depuis le href (ex: ?lang=de)
            const href = this.getAttribute('href');
            const match = href.match(/[?&]lang=([a-z]{2})/);
            
            if (match && match[1]) {
                const targetLang = match[1];
                
                // Sauvegarder dans le localStorage
                localStorage.setItem(STORAGE_KEY, targetLang);
                
                // Le lien continue son comportement normal (redirection vers ?lang=xx)
                // qui déclenchera la mise à jour de la session PHP via inc/lang.php
            }
        });
    });
});