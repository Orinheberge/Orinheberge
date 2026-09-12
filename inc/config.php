<?php
/**
 * Fichier de configuration global
 * Chemin : /inc/config.php
 */

// ═══════════════════════════════════════════
// 1. CONFIGURATION DE LA BASE DE DONNÉES
// ═══════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_NAME', 's43_orinheberge'); // Votre nom de base
define('DB_USER', 'root');            // Votre utilisateur
define('DB_PASS', '1504');            // Votre mot de passe
define('DB_CHARSET', 'utf8mb4');

// ═══════════════════════════════════════════
// 2. DÉMARRAGE DE LA SESSION
// ═══════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) {
    // Paramètres de sécurité pour les cookies de session
    ini_set('session.cookie_httponly', true);
    ini_set('session.use_only_cookies', true);
    ini_set('session.cookie_secure', false); // Mettre à true si vous êtes en HTTPS strict
    
    session_start();
}

// ═══════════════════════════════════════════
// 3. CONNEXION PDO (Singleton pattern simple)
// ═══════════════════════════════════════════
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // Lève des exceptions en cas d'erreur
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // Retourne des tableaux associatifs par défaut
        PDO::ATTR_EMULATE_PREPARES   => false,                     // Utilise les vraies requêtes préparées
        PDO::ATTR_PERSISTENT         => false                      // Connexion non persistante pour éviter les locks
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Optionnel : Définir le fuseau horaire pour MySQL
    $pdo->exec("SET time_zone = '+00:00'"); 

} catch (PDOException $e) {
    // En production, ne jamais afficher le message d'erreur exact à l'utilisateur
    error_log("Erreur de connexion BDD : " . $e->getMessage());
    
    // Si c'est une API ou une page critique, on peut arrêter proprement
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        die("Une erreur technique est survenue. Veuillez réessayer plus tard.");
    } else {
        die("CLI Error: DB Connection failed.\n");
    }
}

// ═══════════════════════════════════════════
// 4. CONSTANTES GLOBALES UTILES
// ═══════════════════════════════════════════

// Racine du site (pour les liens relatifs)
define('BASE_URL', '/'); 
define('ADMIN_URL', BASE_URL . 'admin/');

// Chemins absolus
define('ROOT_PATH', dirname(__DIR__)); // Remonte d'un cran depuis /inc
define('UPLOADS_PATH', ROOT_PATH . '/inc/uploads/');

// Version de l'application (optionnel, peut être lu depuis version.json)
define('APP_VERSION', '1.0.0');

// ═══════════════════════════════════════════
// 5. FONCTIONS UTILITAIRES DE BASE
// ═══════════════════════════════════════════

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est admin
 */
function is_admin() {
    return isset($_SESSION['isadmin']) && $_SESSION['isadmin'] === 'admin';
}

/**
 * Redirection sécurisée
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Nettoyage basique des entrées (si pas de framework)
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}