<?php
/**
 * /inc/settings.php
 * Gestion centralisée des paramètres dynamiques stockés en BDD
 * Inclut un cache en mémoire pour éviter les requêtes SQL répétées
 */

// Cache statique pour éviter de recharger la config plusieurs fois par requête
static $_settings_cache = null;

if (!function_exists('load_settings')) {
    function load_settings($pdo = null): array {
        global $_settings_cache;
        
        // Retourner le cache si déjà chargé
        if ($_settings_cache !== null) {
            return $_settings_cache;
        }
        
        try {
            if (!$pdo) {
                $pdo = new PDO(
                    'mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4',
                    'root', '1504',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            }
            
            $stmt = $pdo->query('SELECT `key`, `value` FROM settings');
            $_settings_cache = [];
            
            while ($row = $stmt->fetch()) {
                $_settings_cache[$row['key']] = $row['value'];
            }
            
        } catch (PDOException $e) {
            error_log('[Settings] Erreur chargement config: ' . $e->getMessage());
            $_settings_cache = [];
        }
        
        return $_settings_cache;
    }
}

if (!function_exists('get_setting')) {
    /**
     * Récupère une valeur de configuration
     * @param string $key La clé du paramètre
     * @param mixed $default Valeur par défaut si la clé n'existe pas
     * @param PDO|null $pdo Connexion BDD optionnelle
     * @return mixed
     */
    function get_setting(string $key, $default = null, $pdo = null) {
        $settings = load_settings($pdo);
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('set_setting')) {
    /**
     * Met à jour ou crée un paramètre de configuration
     * Invalide automatiquement le cache après écriture
     * @param string $key La clé du paramètre
     * @param mixed $value La valeur à stocker
     * @param PDO|null $pdo Connexion BDD optionnelle
     * @return bool
     */
    function set_setting(string $key, $value, $pdo = null): bool {
        global $_settings_cache;
        
        try {
            if (!$pdo) {
                $pdo = new PDO(
                    'mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4',
                    'root', '1504',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            }
            
            $stmt = $pdo->prepare('
                INSERT INTO settings (`key`, `value`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()
            ');
            
            $result = $stmt->execute([$key, $value]);
            
            // Invalider le cache pour forcer un rechargement au prochain appel
            $_settings_cache = null;
            
            return $result;
            
        } catch (PDOException $e) {
            error_log('[Settings] Erreur sauvegarde "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('delete_setting')) {
    /**
     * Supprime un paramètre de configuration
     * @param string $key La clé à supprimer
     * @param PDO|null $pdo Connexion BDD optionnelle
     * @return bool
     */
    function delete_setting(string $key, $pdo = null): bool {
        global $_settings_cache;
        
        try {
            if (!$pdo) {
                $pdo = new PDO(
                    'mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4',
                    'root', '1504',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            }
            
            $stmt = $pdo->prepare('DELETE FROM settings WHERE `key` = ?');
            $result = $stmt->execute([$key]);
            
            // Invalider le cache
            $_settings_cache = null;
            
            return $result;
            
        } catch (PDOException $e) {
            error_log('[Settings] Erreur suppression "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_all_settings')) {
    /**
     * Récupère TOUS les paramètres sous forme de tableau associatif
     * Utile pour pré-remplir les formulaires d'admin
     * @param PDO|null $pdo Connexion BDD optionnelle
     * @return array
     */
    function get_all_settings($pdo = null): array {
        return load_settings($pdo);
    }
}