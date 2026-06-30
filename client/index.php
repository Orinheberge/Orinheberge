<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }
$is_logged_in = true;

$panel_url      = 'https://panel.orinstone.deepstone.fr';
$api_key_client = 'ptlc_MfJSOUID0bnTgFCmm5VvYMML2jKUUA5RFZ2n2MeZCSU';
$api_key_admin  = 'ptla_YKix8PexQDCZ7nIeexST3NXC2sFwQAoefDtOQBvJkbx';
$headers_client = ["Authorization: Bearer $api_key_client","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];
$headers_admin  = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

try {
    $pdo = new PDO('mysql:host=pma.orinstone.deepstone.fr;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die(t('login.db_error')); }

// Rafraîchir session user
$stmt = $pdo->prepare('SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();
if ($user_data) {
    $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
    $_SESSION['avatar']   = $user_data['avatar'];
}

// Gestion action suppression
$api_message = '';
if (isset($_GET['action'], $_GET['uuid']) && $_GET['action'] === 'delete') {
    $target_uuid = $_GET['uuid'];
    $stmt = $pdo->prepare('SELECT uuid, server_id FROM orders WHERE user_id=? AND uuid=?');
    $stmt->execute([$_SESSION['user_id'], $target_uuid]);
    $sv = $stmt->fetch();
    if ($sv) {
        $ch = curl_init($panel_url . '/api/application/servers/' . $sv['server_id']);
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>$headers_admin, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_CUSTOMREQUEST=>'DELETE']);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 204 || $http_code === 200) {
            $pdo->prepare('DELETE FROM orders WHERE uuid=? AND user_id=?')->execute([$target_uuid, $_SESSION['user_id']]);
            $_SESSION['api_success'] = $lang === 'en' ? 'Server deleted successfully.' : 'Serveur supprimé avec succès.';
        } else {
            $_SESSION['api_error'] = $lang === 'en' ? 'Deletion failed. Try again.' : 'Échec de la suppression. Réessayez.';
        }
    }
    header('Location: /client/'); exit();
}
if (isset($_SESSION['api_error']))   { $api_message = "<div class='bg-red-500/20 text-red-400 border border-red-500/30 p-4 rounded-xl mb-6 text-sm'>".$_SESSION['api_error']."</div>"; unset($_SESSION['api_error']); }
elseif (isset($_SESSION['api_success'])) { $api_message = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl mb-6 text-sm'>".$_SESSION['api_success']."</div>"; unset($_SESSION['api_success']); }

// Récupérer tous les services
$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$services = $stmt->fetchAll();

// Compter les tickets ouverts
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status != 'Fermé'");
$stmt2->execute([$_SESSION['user_id']]);
$open_tickets = $stmt2->fetchColumn();
?>
