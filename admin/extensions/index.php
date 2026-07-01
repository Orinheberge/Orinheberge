<?php
ini_set('display_errors',1); error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

$stmt = $pdo->prepare('SELECT id,pseudo,firstname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]); $admin = $stmt->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 Forbidden'); }

$cfg = [];
foreach ($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']] = $r['value'];
$panel_url = $cfg['panel_url'] ?? '';

$flash = '';

// Schéma de config par extension
$ext_fields = [
    'pterodactyl' => [
        ['key'=>'panel_url',      'label'=>'URL du Panel',             'type'=>'url',      'placeholder'=>'https://panel.exemple.fr'],
        ['key'=>'api_key_admin',  'label'=>'Clé API Admin (ptla_...)', 'type'=>'password', 'placeholder'=>'ptla_...'],
        ['key'=>'api_key_client', 'label'=>'Clé API Client (ptlc_...)','type'=>'password', 'placeholder'=>'ptlc_...'],
    ],
    'stripe' => [
        ['key'=>'secret_key', 'label'=>'Clé Secrète (sk_live_...)',  'type'=>'password', 'placeholder'=>'sk_live_...'],
        ['key'=>'public_key', 'label'=>'Clé Publique (pk_live_...)', 'type'=>'text',     'placeholder'=>'pk_live_...'],
    ],
    'paypal' => [
        ['key'=>'username', 'label'=>'Username PayPal.me', 'type'=>'text', 'placeholder'=>'votre_username'],
    ],
    'discord' => [
        ['key'=>'webhook_url', 'label'=>'URL du Webhook Discord', 'type'=>'url', 'placeholder'=>'https://discord.com/api/webhooks/...'],
    ],
    'smtp' => [
        ['key'=>'host',      'label'=>'Serveur SMTP',        'type'=>'text',     'placeholder'=>'smtp.gmail.com'],
        ['key'=>'port',      'label'=>'Port',                'type'=>'number',   'placeholder'=>'587'],
        ['key'=>'user',      'label'=>'Utilisateur SMTP',    'type'=>'email',    'placeholder'=>'no-reply@exemple.fr'],
        ['key'=>'pass',      'label'=>'Mot de passe SMTP',   'type'=>'password', 'placeholder'=>''],
        ['key'=>'from',      'label'=>'Email expéditeur',    'type'=>'email',    'placeholder'=>'no-reply@exemple.fr'],
        ['key'=>'from_name', 'label'=>'Nom expéditeur',      'type'=>'text',     'placeholder'=>'OrinHeberge'],
    ],
    'promo' => [
        ['key'=>'promo_enabled', 'label'=>'Activer les promotions', 'type'=>'checkbox', 'placeholder'=>''],
    ],
];

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE extensions SET is_enabled = 1 - is_enabled WHERE id=?')->execute([$id]);
        header('Location: /admin/extensions/'); exit();
    }

    if ($action === 'save_settings') {
        $ext_id = (int)($_POST['ext_id'] ?? 0);
        $ext    = $pdo->prepare('SELECT slug FROM extensions WHERE id=?');
        $ext->execute([$ext_id]); $ext_row = $ext->fetch();
        $slug   = $ext_row['slug'] ?? '';

        if ($slug && isset($ext_fields[$slug])) {
            $stmt = $pdo->prepare('INSERT INTO extension_settings (extension_id,`key`,`value`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
            foreach ($ext_fields[$slug] as $field) {
                $val = trim($_POST[$field['key']] ?? '');
                $stmt->execute([$ext_id, $field['key'], $val]);
            }

            // Synchroniser aussi vers la table settings pour compatibilité
            if ($slug === 'pterodactyl') {
                $sync = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                foreach (['panel_url','api_key_admin','api_key_client'] as $k) {
                    if (isset($_POST[$k])) $sync->execute([$k, trim($_POST[$k])]);
                }
            }
            if ($slug === 'smtp') {
                $sync = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                $map = ['host'=>'smtp_host','port'=>'smtp_port','user'=>'smtp_user','pass'=>'smtp_pass','from'=>'smtp_from','from_name'=>'smtp_from_name'];
                foreach ($map as $fk => $sk) {
                    if (isset($_POST[$fk])) $sync->execute([$sk, trim($_POST[$fk])]);
                }
            }

              if ($slug === 'promo') {
                $sync = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                if (isset($_POST['promo_enabled'])) {
                    $sync->execute(['promo_enabled', '1']);
                } else {
                    $sync->execute(['promo_enabled', '0']);
                }

            $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Configuration sauvegardée.</div>';
        }
    }
}

// Charger extensions + leurs settings
$extensions = $pdo->query('SELECT * FROM extensions ORDER BY id')->fetchAll();
$ext_settings = [];
foreach ($pdo->query('SELECT * FROM extension_settings') as $r) {
    $ext_settings[$r['extension_id']][$r['key']] = $r['value'];
}

$active_nav = 'extensions';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
<div class="main-content">
  <div class="topbar">
    <div class="flex items-center gap-3">
      <button onclick="document.getElementById('sidebar').classList.toggle('open')" class="md:hidden text-gray-400 text-lg"><i class="fas fa-bars"></i></button>
      <div>
        <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-puzzle-piece text-purple-400 text-xs"></i> Extensions</div>
        <div class="text-xs text-gray-500"><?= count(array_filter($extensions, fn($e) => $e['is_enabled'])) ?>/<?= count($extensions) ?> active(s)</div>
      </div>
    </div>
  </div>

  <div class="content">
    <?= $flash ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <?php foreach ($extensions as $ext):
        $settings = $ext_settings[$ext['id']] ?? [];
        $fields   = $ext_fields[$ext['slug']] ?? [];
        $has_config = !empty($fields);
      ?>
      <div class="card overflow-hidden">
        <!-- En-tête extension -->
        <div class="p-5 border-b border-white/[0.05]">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-lg shrink-0">
                <i class="<?= htmlspecialchars($ext['icon']) ?> text-purple-400"></i>
              </div>
              <div>
                <div class="font-bold text-white text-sm"><?= htmlspecialchars($ext['name']) ?></div>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($ext['description'] ?? '') ?></div>
              </div>
            </div>
            <form method="POST" class="shrink-0">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $ext['id'] ?>">
              <!-- Toggle switch -->
              <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full border transition-colors <?= $ext['is_enabled'] ? 'bg-sky-500 border-sky-400' : 'bg-gray-700 border-gray-600' ?>">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?= $ext['is_enabled'] ? 'translate-x-6' : 'translate-x-1' ?>"></span>
              </button>
            </form>
          </div>
          <?php if ($ext['is_enabled']): ?>
            <div class="mt-3"><span class="badge badge-green"><i class="fas fa-circle text-[8px]"></i> Activée</span></div>
          <?php else: ?>
            <div class="mt-3"><span class="badge badge-gray">Désactivée</span></div>
          <?php endif; ?>
        </div>

        <!-- Configuration -->
        <?php if ($has_config): ?>
        <div class="p-5">
          <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="ext_id" value="<?= $ext['id'] ?>">
            <?php foreach ($fields as $f):
              $val = $settings[$f['key']] ?? '';
            ?>
            <div>
              <label class="block text-[11px] font-semibold text-gray-400 mb-1"><?= htmlspecialchars($f['label']) ?></label>
              <input
                name="<?= htmlspecialchars($f['key']) ?>"
                type="<?= htmlspecialchars($f['type']) ?>"
                class="input text-xs"
                placeholder="<?= htmlspecialchars($f['placeholder'] ?? '') ?>"
                value="<?= htmlspecialchars($val) ?>"
                <?= $f['type'] === 'password' && $val ? 'placeholder="(enregistré — laisser vide pour ne pas changer)"' : '' ?>
              >
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary w-full text-xs mt-1">
              <i class="fas fa-save"></i> Sauvegarder
            </button>
          </form>
        </div>
        <?php elseif ($ext['slug'] === 'promo'): ?>
        <div class="p-5">
          <p class="text-xs text-gray-500">Les codes promo sont gérés via le fichier <code class="bg-white/5 px-1.5 py-0.5 rounded text-sky-400">shop/order/lib/promo/promo.php</code>.</p>
          <a href="/admin/?view=settings" class="btn btn-ghost w-full mt-3 text-xs justify-center">Aller aux paramètres généraux</a>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body></html>
