# Système de traduction OrinHeberge — Instructions

## Fichiers inclus
```
inc/lang.php          → Dictionnaire complet FR/EN + fonction t() + th()
inc/lang_switcher.php → Dropdown sélecteur de langue (navbar)
inc/navbar.php        → Navbar entièrement traduite (remplace la nav inline)
inc/footer.php        → Footer entièrement traduit (remplace le footer inline)

index.php             → Page d'accueil traduite
login/index.php       → Page connexion traduite
register/index.php    → Page inscription traduite
support/index.php     → Page support traduite
profil/index.php      → Page profil traduite
```

## Installation

1. **Copier les fichiers `inc/`** dans votre dossier `/inc/` sur le serveur.
2. **Remplacer les pages** listées ci-dessus par les versions de ce ZIP.
3. C'est tout ! La langue est persistée en session via `?lang=fr` / `?lang=en`.

## Ajouter la traduction aux autres pages (shop, client/servers…)

En haut de chaque page PHP, après `session_start()` :
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
```

Remplacer la navbar et le footer inline par :
```php
<?php $active_nav = 'servers'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>
// ... contenu de la page ...
<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
```

Utiliser `t('clé')` pour tout texte traduit :
```php
echo t('nav.home');        // "Accueil" ou "Home"
echo t('hero.cta');        // "Découvrir nos offres" ou "Browse our plans"
```

Pour du contenu contenant du HTML (liens, balises) utiliser `th('clé')` sans htmlspecialchars.

## Ajouter une nouvelle langue

Dans `inc/lang.php`, ajouter la langue dans chaque entrée du dictionnaire :
```php
'nav.home' => ['fr' => 'Accueil', 'en' => 'Home', 'de' => 'Startseite'],
```
Puis ajouter dans `inc/lang_switcher.php` :
```php
'de' => ['label' => 'DE', 'flag' => 'de', 'name' => 'Deutsch'],
```
