<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données.');
}

$stmt = $pdo->prepare('SELECT id, pseudo, firstname, is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || !$admin['is_admin']) {
    http_response_code(403);
    die('Accès refusé.');
}

$active_nav = 'lang';
$page_title = 'Langues';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';

$table_exists = false;
$translations = [];

try {
    $check = $pdo->query("SHOW TABLES LIKE 'lang_boutique'");
    $table_exists = (bool)$check->fetch();

    if ($table_exists) {
        $translations = $pdo->query("SELECT translation_key, fr, en FROM lang_boutique ORDER BY translation_key LIMIT 20")->fetchAll();
    }
} catch (PDOException $e) {
    $table_exists = false;
}
?>
<div class="main-content">
  <div class="topbar">
    <div>
      <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-language text-sky-400 text-xs"></i> Langues</div>
      <div class="text-xs text-gray-500">Gestion des traductions de la boutique</div>
    </div>
  </div>

  <div class="content space-y-4">
    <div class="card p-5">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-bold text-white">Traductions disponibles</h2>
          <p class="text-sm text-gray-400 mt-1">Cette page permet de consulter les clés de traduction enregistrées pour la boutique.</p>
        </div>
        <span class="badge badge-blue"><i class="fas fa-check-circle"></i> En ligne</span>
      </div>
    </div>

    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-white">Table des traductions</h3>
        <span class="text-xs text-gray-500"><?= $table_exists ? 'Table détectée' : 'Table absente' ?></span>
      </div>

      <?php if ($table_exists && !empty($translations)): ?>
        <div class="overflow-x-auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>Clé</th>
                <th>FR</th>
                <th>EN</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($translations as $row): ?>
                <tr>
                  <td class="font-mono text-xs text-sky-400"><?= htmlspecialchars($row['translation_key']) ?></td>
                  <td class="text-sm text-gray-200"><?= htmlspecialchars($row['fr']) ?></td>
                  <td class="text-sm text-gray-300"><?= htmlspecialchars($row['en']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="rounded-xl border border-yellow-500/20 bg-yellow-500/10 p-4 text-sm text-yellow-300">
          Aucune traduction n’a encore été chargée, ou la table <strong>lang_boutique</strong> n’existe pas encore.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
