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

$success_message = '';
$error_message = '';
$form_key = '';
$form_fr = '';
$form_en = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_translation'])) {
    $translation_key = trim($_POST['translation_key'] ?? '');
    $fr = trim($_POST['fr'] ?? '');
    $en = trim($_POST['en'] ?? '');

    if ($translation_key === '') {
        $error_message = 'La clé de traduction est obligatoire.';
    } else {
        $form_key = $translation_key;
        $form_fr = $fr;
        $form_en = $en;

        $check_stmt = $pdo->prepare('SELECT id FROM lang_boutique WHERE translation_key = ? LIMIT 1');
        $check_stmt->execute([$translation_key]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            $update_stmt = $pdo->prepare('UPDATE lang_boutique SET fr = ?, en = ?, updated_at = NOW() WHERE translation_key = ?');
            $update_stmt->execute([$fr, $en, $translation_key]);
            $success_message = 'Traduction mise à jour avec succès.';
        } else {
            $insert_stmt = $pdo->prepare('INSERT INTO lang_boutique (translation_key, fr, en, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
            $insert_stmt->execute([$translation_key, $fr, $en]);
            $success_message = 'Traduction ajoutée avec succès.';
        }
    }
} elseif (isset($_GET['edit_key']) && trim($_GET['edit_key']) !== '') {
    $edit_key = trim($_GET['edit_key']);
    $edit_stmt = $pdo->prepare('SELECT translation_key, fr, en FROM lang_boutique WHERE translation_key = ? LIMIT 1');
    $edit_stmt->execute([$edit_key]);
    $edit_row = $edit_stmt->fetch();

    if ($edit_row) {
        $form_key = $edit_row['translation_key'];
        $form_fr = $edit_row['fr'];
        $form_en = $edit_row['en'];
    }
}

$table_exists = false;
$translations = [];
$total_translations = 0;
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

try {
    $check = $pdo->query("SHOW TABLES LIKE 'lang_boutique'");
    $table_exists = (bool)$check->fetch();

    if ($table_exists) {
        $count_stmt = $pdo->query('SELECT COUNT(*) AS total FROM lang_boutique');
        $total_translations = (int)$count_stmt->fetchColumn();

        $offset = ($page - 1) * $per_page;
        $translations_stmt = $pdo->prepare('SELECT translation_key, fr, en FROM lang_boutique ORDER BY translation_key LIMIT :limit OFFSET :offset');
        $translations_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $translations_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $translations_stmt->execute();
        $translations = $translations_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $table_exists = false;
}

$total_pages = $table_exists ? max(1, (int)ceil($total_translations / $per_page)) : 1;
if ($page > $total_pages) {
    $page = $total_pages;
}
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
          <p class="text-sm text-gray-400 mt-1">Cette page permet de consulter, ajouter et modifier les clés de traduction enregistrées pour la boutique.</p>
        </div>
        <span class="badge badge-blue"><i class="fas fa-check-circle"></i> En ligne</span>
      </div>
    </div>

    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-white">Ajouter ou modifier une traduction</h3>
        <span class="text-xs text-gray-500"><?= $table_exists ? 'Table détectée' : 'Table absente' ?></span>
      </div>

      <?php if (!empty($success_message)): ?>
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-sm text-emerald-300 mb-4">
          <?= htmlspecialchars($success_message) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-300 mb-4">
          <?= htmlspecialchars($error_message) ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-4">
        <input type="hidden" name="save_translation" value="1">
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <label class="block text-xs uppercase text-gray-500 mb-1">Clé</label>
            <input type="text" name="translation_key" value="<?= htmlspecialchars($form_key) ?>" required class="w-full rounded-xl border border-gray-700 bg-gray-900/80 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-xs uppercase text-gray-500 mb-1">Français</label>
            <textarea name="fr" rows="3" class="w-full rounded-xl border border-gray-700 bg-gray-900/80 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none"><?= htmlspecialchars($form_fr) ?></textarea>
          </div>
          <div>
            <label class="block text-xs uppercase text-gray-500 mb-1">English</label>
            <textarea name="en" rows="3" class="w-full rounded-xl border border-gray-700 bg-gray-900/80 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none"><?= htmlspecialchars($form_en) ?></textarea>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button type="submit" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Enregistrer</button>
          <?php if ($form_key !== ''): ?>
            <a href="/admin/lang/" class="text-sm text-gray-400 hover:text-white">Créer une nouvelle traduction</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-white">Table des traductions</h3>
        <span class="text-xs text-gray-500">Page <?= $page ?> / <?= $total_pages ?></span>
      </div>

      <?php if ($table_exists && !empty($translations)): ?>
        <div class="overflow-x-auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>Clé</th>
                <th>FR</th>
                <th>EN</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($translations as $row): ?>
                <tr>
                  <td class="font-mono text-xs text-sky-400"><?= htmlspecialchars($row['translation_key']) ?></td>
                  <td class="text-sm text-gray-200"><?= htmlspecialchars($row['fr']) ?></td>
                  <td class="text-sm text-gray-300"><?= htmlspecialchars($row['en']) ?></td>
                  <td>
                    <a href="/admin/lang/?edit_key=<?= urlencode($row['translation_key']) ?>" class="text-sm text-sky-400 hover:text-sky-300">Modifier</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($total_pages > 1): ?>
          <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-500">
              Affichage de <?= min($per_page, $total_translations) ?> entrées sur <?= $total_translations ?>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <?php if ($page > 1): ?>
                <a href="/admin/lang/?page=<?= max(1, $page - 1) ?>" class="rounded-lg border border-gray-700 px-3 py-1 text-sm text-gray-300 hover:text-white">Précédent</a>
              <?php endif; ?>

              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="/admin/lang/?page=<?= $i ?>" class="rounded-lg px-3 py-1 text-sm <?= $i === $page ? 'bg-sky-600 text-white' : 'border border-gray-700 text-gray-300 hover:text-white' ?>"><?= $i ?></a>
              <?php endfor; ?>

              <?php if ($page < $total_pages): ?>
                <a href="/admin/lang/?page=<?= min($total_pages, $page + 1) ?>" class="rounded-lg border border-gray-700 px-3 py-1 text-sm text-gray-300 hover:text-white">Suivant</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
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
