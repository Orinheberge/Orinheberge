<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4',
        'root', '1504',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Erreur de connexion base de données : ' . $e->getMessage());
}

$stmt = $pdo->prepare('SELECT id,pseudo,firstname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 Forbidden'); }

// Générer un token CSRF pour sécuriser les POST
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = '';
$message_type = '';

// Schéma de config par extension
$ext_fields = [
    'pterodactyl' => [
        ['key'=>'panel_url',      'label'=>'URL du Panel',             'type'=>'url',      'placeholder'=>'https://panel.exemple.fr'],
        ['key'=>'api_key_admin',  'label'=>'Clé API Admin (ptla_...)', 'type'=>'password', 'placeholder'=>'ptla_...'],
        ['key'=>'api_key_client', 'label'=>'Clé API Client (ptlc_...)','type'=>'password', 'placeholder'=>'ptlc_...'],
    ],
    'stripe' => [
        ['key'=>'secret_key', 'label'=>'Clé Secrète (sk_live_...)',  'type'=>'password', 'placeholder'=>'sk_live_...', 'required'=>true],
        ['key'=>'public_key', 'label'=>'Clé Publique (pk_live_...)', 'type'=>'text',     'placeholder'=>'pk_live_...', 'required'=>true],
        ['key'=>'webhook_secret', 'label'=>'Secret Webhook (whsec_...)', 'type'=>'password', 'placeholder'=>'whsec_...'],
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
    // Vérification CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token CSRF invalide.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE extensions SET is_enabled = 1 - is_enabled WHERE id=?')->execute([$id]);
        $flash = '<div class="bg-sky-500/15 text-sky-400 border border-sky-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-check-circle mr-2"></i>État de l\'extension mis à jour.</div>';
        $message_type = 'success';
    }

    if ($action === 'toggle_promo') {
        $id = (int)($_POST['promo_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE promos SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        }
        $flash = '<div class="bg-sky-500/15 text-sky-400 border border-sky-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-check-circle mr-2"></i>État de la promo mis à jour.</div>';
        $message_type = 'success';
    }

    if ($action === 'save_settings') {
        $ext_id = (int)($_POST['ext_id'] ?? 0);
        $ext_stmt = $pdo->prepare('SELECT slug FROM extensions WHERE id=?');
        $ext_stmt->execute([$ext_id]);
        $ext_row = $ext_stmt->fetch();
        $slug = $ext_row['slug'] ?? '';

        if ($slug && isset($ext_fields[$slug])) {
            $errors = [];
            
            // Validation des champs requis
            foreach ($ext_fields[$slug] as $field) {
                if (($field['required'] ?? false) && empty(trim($_POST[$field['key']] ?? ''))) {
                    $errors[] = "Le champ \"{$field['label']}\" est obligatoire.";
                }
            }

            if (!empty($errors)) {
                $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-exclamation-triangle mr-2"></i>' . implode('<br>', $errors) . '</div>';
                $message_type = 'error';
            } else {
                // Sauvegarde dans extension_settings
                $stmt = $pdo->prepare('INSERT INTO extension_settings (extension_id,`key`,`value`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                foreach ($ext_fields[$slug] as $field) {
                    $val = trim($_POST[$field['key']] ?? '');
                    // Ne pas écraser un mot de passe existant si le champ est vide
                    if ($field['type'] === 'password' && $val === '') continue;
                    $stmt->execute([$ext_id, $field['key'], $val]);
                }

                // Synchronisation avec la table settings globale
                if ($slug === 'stripe') {
                    $sync = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                    $map = ['secret_key'=>'stripe_secret_key', 'public_key'=>'stripe_public_key', 'webhook_secret'=>'stripe_webhook_secret'];
                    foreach ($map as $fk => $sk) {
                        $val = trim($_POST[$fk] ?? '');
                        if ($val !== '') {
                            $sync->execute([$sk, $val]);
                        }
                    }
                }

                if ($slug === 'pterodactyl') {
                    $sync = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                    foreach (['panel_url','api_key_admin','api_key_client'] as $k) {
                        $val = trim($_POST[$k] ?? '');
                        if ($val !== '') {
                            $sync->execute([$k, $val]);
                        }
                    }
                }

                if ($slug === 'smtp') {
                    $sync = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                    $map = ['host'=>'smtp_host','port'=>'smtp_port','user'=>'smtp_user','pass'=>'smtp_pass','from'=>'smtp_from','from_name'=>'smtp_from_name'];
                    foreach ($map as $fk => $sk) {
                        $val = trim($_POST[$fk] ?? '');
                        if ($val !== '') {
                            $sync->execute([$sk, $val]);
                        }
                    }
                }

                if ($slug === 'promo') {
                    $sync = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
                    $sync->execute(['promo_enabled', isset($_POST['promo_enabled']) ? '1' : '0']);
                }

                $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-check-circle mr-2"></i>Configuration sauvegardée avec succès.</div>';
                $message_type = 'success';
                
                // Régénérer le token CSRF après un POST réussi
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }
}

// Charger extensions + leurs settings
$extensions = $pdo->query('SELECT * FROM extensions ORDER BY id')->fetchAll();
$ext_settings = [];
foreach ($pdo->query('SELECT * FROM extension_settings') as $r) {
    $ext_settings[$r['extension_id']][$r['key']] = $r['value'];
}

// Charger les promos
$promos = [];
try {
    $promoTable = $pdo->query("SHOW TABLES LIKE 'promos'")->fetch();
    if ($promoTable) {
        $promos = $pdo->query('SELECT id, slug, name, code, is_active FROM promos ORDER BY id')->fetchAll();
    }
} catch (PDOException $e) {
    $promos = [];
}

$active_nav = 'extensions';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<div class="main-content">
    <div class="topbar">
        <div class="flex items-center gap-3">
            <button id="adminSidebarToggle" class="md:hidden text-gray-400 hover:text-white text-lg w-8" aria-label="Ouvrir le menu admin">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-puzzle-piece text-purple-400 text-xs"></i> Extensions
                </div>
                <div class="text-xs text-gray-500">
                    <?= count(array_filter($extensions, fn($e) => $e['is_enabled'])) ?>/<?= count($extensions) ?> active(s)
                </div>
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
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" 
                                    class="relative inline-flex h-6 w-11 items-center rounded-full border transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500/50 <?= $ext['is_enabled'] ? 'bg-sky-500 border-sky-400' : 'bg-gray-700 border-gray-600' ?>"
                                    title="<?= $ext['is_enabled'] ? 'Désactiver' : 'Activer' ?>">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?= $ext['is_enabled'] ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                            </button>
                        </form>
                    </div>
                    <div class="mt-3">
                        <?php if ($ext['is_enabled']): ?>
                            <span class="badge badge-green"><i class="fas fa-circle text-[8px]"></i> Activée</span>
                        <?php else: ?>
                            <span class="badge badge-gray">Désactivée</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Configuration Promo -->
                <?php if ($ext['slug'] === 'promo'): ?>
                <div class="p-5 space-y-3">
                    <div class="text-xs text-gray-500 mb-3">Les codes promo sont gérés depuis la table promos du site.</div>
                    
                    <!-- Toggle global des promos -->
                    <?php if (!empty($fields)): ?>
                    <form method="POST" class="mb-4 p-3 rounded-lg border border-white/10 bg-white/5">
                        <input type="hidden" name="action" value="save_settings">
                        <input type="hidden" name="ext_id" value="<?= $ext['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <?php foreach ($fields as $f):
                            $val = $settings[$f['key']] ?? '';
                        ?>
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" 
                                   name="<?= htmlspecialchars($f['key']) ?>" 
                                   value="1" 
                                   <?= ($val === '1') ? 'checked' : '' ?>
                                   class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-sky-500 focus:ring-sky-500/50">
                            <span class="text-sm text-gray-300 group-hover:text-white transition"><?= htmlspecialchars($f['label']) ?></span>
                        </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary w-full text-xs mt-3">
                            <i class="fas fa-save"></i> Sauvegarder
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if (!empty($promos)): ?>
                        <div class="space-y-2">
                            <?php foreach ($promos as $promo): ?>
                                <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold text-white"><?= htmlspecialchars($promo['code']) ?></div>
                                            <div class="text-[11px] text-gray-400"><?= htmlspecialchars($promo['name']) ?></div>
                                        </div>
                                        <form method="POST" class="shrink-0">
                                            <input type="hidden" name="action" value="toggle_promo">
                                            <input type="hidden" name="promo_id" value="<?= (int)$promo['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" 
                                                    class="relative inline-flex h-6 w-11 items-center rounded-full border transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500/50 <?= (int)$promo['is_active'] ? 'bg-sky-500 border-sky-400' : 'bg-gray-700 border-gray-600' ?>"
                                                    title="<?= (int)$promo['is_active'] ? 'Désactiver' : 'Activer' ?>">
                                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?= (int)$promo['is_active'] ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="mt-2 text-[11px] <?= (int)$promo['is_active'] ? 'text-emerald-400' : 'text-gray-500' ?>">
                                        <?= (int)$promo['is_active'] ? '<i class="fas fa-check mr-1"></i>Activé' : '<i class="fas fa-times mr-1"></i>Désactivé' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-3 text-xs text-yellow-300">
                            <i class="fas fa-info-circle mr-1"></i>Aucune promo n'a été trouvée dans la base.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Configuration standard -->
                <?php elseif ($has_config): ?>
                <div class="p-5">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="save_settings">
                        <input type="hidden" name="ext_id" value="<?= $ext['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <?php foreach ($fields as $f):
                            $val = $settings[$f['key']] ?? '';
                            $is_password_with_value = ($f['type'] === 'password' && $val !== '');
                        ?>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 mb-1">
                                <?= htmlspecialchars($f['label']) ?>
                                <?php if ($f['required'] ?? false): ?>
                                    <span class="text-red-400">*</span>
                                <?php endif; ?>
                            </label>
                            <?php if ($f['type'] === 'checkbox'): ?>
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" 
                                           name="<?= htmlspecialchars($f['key']) ?>" 
                                           value="1" 
                                           <?= ($val === '1') ? 'checked' : '' ?>
                                           class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-sky-500 focus:ring-sky-500/50">
                                    <span class="text-xs text-gray-300 group-hover:text-white transition"><?= htmlspecialchars($f['placeholder'] ?? '') ?></span>
                                </label>
                            <?php else: ?>
                                <input
                                    name="<?= htmlspecialchars($f['key']) ?>"
                                    type="<?= htmlspecialchars($f['type']) ?>"
                                    class="input text-xs"
                                    placeholder="<?= htmlspecialchars($f['placeholder'] ?? '') ?>"
                                    value="<?= $is_password_with_value ? '' : htmlspecialchars($val) ?>"
                                    <?= $is_password_with_value ? 'placeholder="(enregistré — laisser vide pour ne pas changer)"' : '' ?>
                                    <?= ($f['required'] ?? false) && !$is_password_with_value ? 'required' : '' ?>
                                >
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" class="btn btn-primary w-full text-xs mt-1">
                            <i class="fas fa-save"></i> Sauvegarder
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="p-5 text-center text-xs text-gray-500">
                    <i class="fas fa-cog text-2xl mb-2 block opacity-30"></i>
                    Aucune configuration disponible pour cette extension.
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body></html>