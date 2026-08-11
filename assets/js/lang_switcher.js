/**
 * OrinHeberge — Language Switcher Logic (v2)
 * Gère la persistance de la langue via localStorage, la détection automatique,
 * et synchronise avec l'URL/PHP et les sélecteurs visuels.
 * 
 * Compatible avec :
 * - Anciens liens ?lang=xx
 * - Nouveaux sélecteurs data-lang-option
 * - Détection automatique de la langue du navigateur
 */

'use strict';

(function() {
    const STORAGE_KEY = 'orinheberge_lang';
    const SUPPORTED_LANGS = ['fr', 'en', 'de'];
    const DEFAULT_LANG = 'fr';

    console.log('[LangSwitcher] Script initialisé.');

    // ============================================
    // UTILITAIRES
    // ============================================

    /**
     * Récupère la langue stockée dans localStorage
     */
    function getStoredLang() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            console.warn('[LangSwitcher] localStorage non disponible:', e);
            return null;
        }
    }

    /**
     * Sauvegarde la langue dans localStorage
     */
    function setStoredLang(lang) {
        try {
            localStorage.setItem(STORAGE_KEY, lang);
            console.log('[LangSwitcher] Langue sauvegardée:', lang);
        } catch (e) {
            console.warn('[LangSwitcher] Impossible de sauvegarder:', e);
        }
    }

    /**
     * Détecte la langue du navigateur
     */
    function detectBrowserLang() {
        const browserLang = navigator.language || navigator.userLanguage;
        if (!browserLang) return null;

        // Extraire le code langue (ex: "fr-FR" → "fr")
        const langCode = browserLang.split('-')[0].toLowerCase();
        
        // Vérifier si c'est une langue supportée
        if (SUPPORTED_LANGS.includes(langCode)) {
            console.log('[LangSwitcher] Langue navigateur détectée:', langCode);
            return langCode;
        }
        
        return null;
    }

    /**
     * Récupère la langue actuelle depuis l'URL
     */
    function getUrlLang() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('lang');
    }

    /**
     * Met à jour visuellement les sélecteurs de langue
     */
    function updateLangSwitcherUI(currentLang) {
        // Anciens sélecteurs (liens)
        const oldLinks = document.querySelectorAll('a[href*="?lang="]');
        oldLinks.forEach(link => {
            const href = link.getAttribute('href');
            const match = href.match(/[?&]lang=([a-z]{2})/);
            if (match && match[1] === currentLang) {
                link.classList.add('bg-sky-600/10', 'text-sky-400');
            }
        });

        // Nouveaux sélecteurs (data-lang-option)
        const newOptions = document.querySelectorAll('[data-lang-option]');
        newOptions.forEach(option => {
            const href = option.getAttribute('href');
            const match = href.match(/[?&]lang=([a-z]{2})/);
            if (match && match[1] === currentLang) {
                option.classList.add('bg-sky-600/10', 'text-sky-400');
            }
        });

        console.log('[LangSwitcher] UI mise à jour pour:', currentLang);
    }

    // ============================================
    // LOGIQUE PRINCIPALE
    // ============================================

    document.addEventListener('DOMContentLoaded', function() {
        console.log('[LangSwitcher] DOM chargé.');

        const urlLang = getUrlLang();
        const storedLang = getStoredLang();
        const browserLang = detectBrowserLang();

        // Déterminer la langue active
        let activeLang = urlLang || storedLang || browserLang || DEFAULT_LANG;

        // Si l'URL n'a pas de langue mais qu'on a une préférence, on pourrait rediriger
        // Mais on laisse le PHP gérer ça proprement avec header('Location')
        if (!urlLang && (storedLang || browserLang)) {
            console.log('[LangSwitcher] Langue préférée détectée:', activeLang);
            console.log('[LangSwitcher] Le PHP devrait gérer la redirection si nécessaire.');
        }

        // Sauvegarder la langue active si elle vient du navigateur
        if (!storedLang && browserLang) {
            setStoredLang(browserLang);
        }

        // Mettre à jour l'UI
        updateLangSwitcherUI(activeLang);

        // ============================================
        // GESTION DES CLICS
        // ============================================

        // 1. Anciens liens ?lang=xx
        const oldLinks = document.querySelectorAll('a[href*="?lang="]');
        oldLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                const match = href.match(/[?&]lang=([a-z]{2})/);
                
                if (match && match[1]) {
                    const targetLang = match[1];
                    console.log('[LangSwitcher] Changement vers:', targetLang);
                    
                    // Sauvegarder dans localStorage
                    setStoredLang(targetLang);
                    
                    // Feedback visuel immédiat
                    updateLangSwitcherUI(targetLang);
                }
            });
        });

        // 2. Nouveaux sélecteurs data-lang-option
        const newOptions = document.querySelectorAll('[data-lang-option]');
        newOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                const match = href.match(/[?&]lang=([a-z]{2})/);
                
                if (match && match[1]) {
                    const targetLang = match[1];
                    console.log('[LangSwitcher] Changement vers:', targetLang);
                    
                    // Sauvegarder dans localStorage
                    setStoredLang(targetLang);
                    
                    // Feedback visuel immédiat
                    updateLangSwitcherUI(targetLang);
                    
                    // Fermer le dropdown si ouvert
                    const switcher = this.closest('[data-lang-switcher]');
                    if (switcher) {
                        const dropdown = switcher.querySelector('[data-lang-dropdown]');
                        const btn = switcher.querySelector('[data-lang-toggle]');
                        if (dropdown) {
                            dropdown.classList.add('opacity-0', 'invisible');
                            dropdown.classList.remove('opacity-100', 'visible');
                        }
                        if (btn) {
                            btn.setAttribute('aria-expanded', 'false');
                        }
                    }
                }
            });
        });

        console.log('[LangSwitcher] Initialisation terminée. Langue active:', activeLang);
    });

    // ============================================
    // API PUBLIQUE (optionnel)
    // ============================================

    // Exposer une API globale si besoin
    window.OrinHebergeLang = {
        getCurrent: function() {
            return getUrlLang() || getStoredLang() || DEFAULT_LANG;
        },
        set: function(lang) {
            if (SUPPORTED_LANGS.includes(lang)) {
                setStoredLang(lang);
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('lang', lang);
                window.location.href = newUrl.toString();
            }
        },
        getSupported: function() {
            return SUPPORTED_LANGS;
        }
    };

})();