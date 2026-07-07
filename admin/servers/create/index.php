<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/Facture.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }
$chk = $pdo->prepare('SELECT is_admin, pseudo, firstname, avatar FROM users WHERE id=? LIMIT 1');
$chk->execute([$_SESSION['user_id']]);
$admin = $chk->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 — Accès refusé.'); }
$_SESSION['username'] = !empty($admin['pseudo']) ? $admin['pseudo'] : $admin['firstname'];

// ── Listes pour le formulaire ──────────────────────────────────────────────────
$all_users    = $pdo->query('SELECT id, pseudo, firstname, lastname, email FROM users WHERE is_admin=0 ORDER BY email ASC')->fetchAll();
$all_products = $pdo->query('SELECT p.*, e.name AS egg_name, n.name AS node_name FROM products p JOIN eggs e ON e.id=p.egg_id JOIN nodes n ON n.id=p.node_id WHERE p.is_active=1 ORDER BY p.sort_order ASC')->fetchAll();

$flash = '';

// ── POST : créer le serveur ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id    = (int)($_POST['user_id']    ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $days       = max(1, (int)($_POST['days'] ?? 30));
    $price      = (float)($_POST['price']    ?? 0);
    $note       = trim($_POST['note'] ?? '');

    // Charger le client et le produit
    $user    = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $user->execute([$user_id]);
    $client  = $user->fetch();

    $prod_stmt = $pdo->prepare('
        SELECT p.*, n.panel_node_id, n.location_id, e.panel_egg_id, e.panel_nest_id,
               e.docker_image, e.startup AS egg_startup, e.env_vars AS egg_env_vars
        FROM products p
        JOIN nodes n ON n.id = p.node_id
        JOIN eggs  e ON e.id = p.egg_id
        WHERE p.id=? AND p.is_active=1 LIMIT 1
    ');
    $prod_stmt->execute([$product_id]);
    $product = $prod_stmt->fetch();

    if (!$client || !$product) {
        $flash = 'err:Client ou produit introuvable.';
    } else {
        // 1. Créer ou récupérer le compte panel
        $api_key_admin = $cfg['api_key_admin'] ?? '';
        $panel_url     = $cfg['panel_url'] ?? '';
        $headers       = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

        // Chercher si le compte panel existe déjà
        $search = _adminApi($panel_url, $headers, 'users?filter[email]=' . urlencode($client['email']));
        if (!empty($search['data'][0]['attributes']['id'])) {
            $panel_user_id = $search['data'][0]['attributes']['id'];
        } else {
            // Créer le compte panel
            $pass_gen = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#'), 0, 14);
            $created  = _adminApi($panel_url, $headers, 'users', [
                'email'      => $client['email'],
                'username'   => $client['pseudo'] ?? ('user' . $client['id']),
                'first_name' => $client['firstname'] ?? 'User',
                'last_name'  => $client['lastname']  ?? 'Account',
                'password'   => $pass_gen,
            ]);
            if (empty($created['attributes']['id'])) {
                $flash = 'err:Impossible de créer le compte panel : ' . json_encode($created);
                goto render;
            }
            $panel_user_id = $created['attributes']['id'];
            $pdo->prepare('UPDATE users SET panel_password=? WHERE id=?')->execute([$pass_gen, $client['id']]);
        }

        // 2. Fusionner env_vars
        $env = json_decode($product['egg_env_vars'] ?? '{}', true) ?: [];
        if (!empty($product['env_override'])) {
            $override = json_decode($product['env_override'], true) ?: [];
            $env = array_merge($env, $override);
        }

        // 3. Créer le serveur sur le panel
        $server = _adminApi($panel_url, $headers, 'servers', [
            'name'         => $product['name'] . ' — ' . ($client['pseudo'] ?: $client['firstname']),
            'user'         => $panel_user_id,
            'egg'          => $product['panel_egg_id'],
            'nest'         => $product['panel_nest_id'],
            'docker_image' => $product['docker_image'],
            'startup'      => $product['egg_startup'],
            'environment'  => $env,
            'deploy'       => ['locations' => [$product['location_id'] ?? 1], 'dedicated_ip' => false, 'port_range' => []],
            'limits'       => ['memory' => $product['ram'], 'swap' => 0, 'disk' => $product['disk'], 'io' => 500, 'cpu' => $product['cpu']],
            'feature_limits' => ['databases' => $product['databases'] ?? 1, 'allocations' => $product['allocations'] ?? 1, 'backups' => $product['backups'] ?? 1],
            'start_on_completion' => false,
        ]);

        if (empty($server['attributes']['id'])) {
            $flash = 'err:Erreur création serveur panel : ' . json_encode($server);
            goto render;
        }

        $server_id  = $server['attributes']['id'];
        $uuid       = $server['attributes']['uuid'];
        $identifier = $server['attributes']['identifier'] ?? $uuid;
        $order_id   = 'ADMIN-' . strtoupper(substr(md5(uniqid()), 0, 10));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $next_pay   = date('Y-m-d', strtotime("+{$days} days"));

        // 4. Enregistrer en BDD
        $pdo->prepare("
            INSERT INTO orders
                (user_id, order_id, service_name, ram, disk, cpu,
                 server_id, uuid, id_server_panel, status,
                 renewal_price, amount, next_payment_date, expires_at,
                 created_by_admin, product_id)
            VALUES (?,?,?,?,?,?, ?,?,?,'paid', ?,?,?,?, 1,?)
        ")->execute([
            $client['id'], $order_id, $product['name'],
            $product['ram'], $product['disk'], $product['cpu'],
            $server_id, $uuid, $identifier,
            $price, $price, $next_pay, $expires_at,
            $product['id']
        ]);

        // 🔵 Création de la facture via createInvoice()
        $invoice_method = $price > 0 ? 'admin' : 'free';
        $created_invoice = createInvoice($pdo, [
            'user_id'        => $client['id'],
            'order_id'       => $order_id,
            'service_name'   => $product['name'],
            'amount'         => $price,
            'type'           => 'purchase',
            'status'         => 'paid',
            'payment_method' => $invoice_method,
            'payment_ref'    => 'ADMIN-MANUAL-' . $_SESSION['user_id'],
            'paid_at'        => date('Y-m-d H:i:s'),
        ]);

        $invoice_id = $created_invoice['invoice_id'] ?? null;

        // 🔵 REDIRECTION VERS LA PAGE DE SUCCÈS
        $success_params = http_build_query([
            'order'   => $order_id,
            'invoice' => $invoice_id ?? '',
            'uuid'    => $uuid,
            'client'  => $client['id'],
            'product' => $product['id'],
            'server'  => $server_id,
        ]);
        
        header('Location: /admin/servers/create/success/?' . $success_params);
        exit();
    }
}

render:
function _adminApi(string $url, array $headers, string $ep, ?array $data = null): mixed {
    $ch = curl_init($url . '/api/application/' . $ep);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false]);
    if ($data !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 204) return true;
    return $res ? json_decode($res, true) : null;
}

[$ft, $fm] = $flash ? explode(':', $flash, 2) : ['', ''];
$active_nav = 'servers';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_sidebar.php';
?>

 <style>
        :root{--sidebar:240px;}
        *{box-sizing:border-box;}
        body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:#111318;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:40;overflow-y:auto;}
        .sidebar-logo{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);}
        .sidebar-nav{padding:.75rem .75rem;flex:1;}
        .nav-item{display:flex;align-items:center;gap:.75rem;padding:.625rem .875rem;border-radius:.625rem;font-size:.82rem;font-weight:500;color:#6b7280;transition:all .15s;text-decoration:none;margin-bottom:.15rem;border:1px solid transparent;}
        .nav-item:hover{background:rgba(255,255,255,.04);color:#d1d5db;}
        .nav-item.active{background:rgba(244,63,94,.08);color:#f43f5e;border-color:rgba(244,63,94,.15);}
        .nav-item .icon{width:1.1rem;text-align:center;font-size:.85rem;flex-shrink:0;}
        .nav-section{font-size:.65rem;font-weight:700;letter-spacing:.1em;color:#374151;text-transform:uppercase;padding:.75rem .875rem .35rem;}
        .nav-separator{height:1px;background:rgba(255,255,255,.05);margin:.5rem .75rem;}
        .sidebar-footer{padding:.875rem 1rem;border-top:1px solid rgba(255,255,255,.05);}
        .main-content{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
        .topbar{background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;}
        .content{padding:1.75rem;flex:1;}
        .card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;}
        .stat-card{background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.25rem;}
        .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
        .badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
        .badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
        .badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
        .badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
        .badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
        .badge-rose{background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.2);}
        table{width:100%;border-collapse:collapse;}
        th{text-align:left;padding:.625rem 1rem;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#4b5563;border-bottom:1px solid rgba(255,255,255,.05);}
        td{padding:.875rem 1rem;font-size:.83rem;border-bottom:1px solid rgba(255,255,255,.04);}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(255,255,255,.015);}
        input,textarea,select{background:#1e2330 !important;border:1px solid rgba(255,255,255,.08) !important;color:#e2e8f0 !important;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;outline:none;transition:border-color .15s;}
        input:focus,textarea:focus,select:focus{border-color:rgba(244,63,94,.4) !important;}
        .btn-action{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:600;transition:all .15s;border:1px solid transparent;cursor:pointer;}
        .btn-red{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.2);}
        .btn-red:hover{background:rgba(239,68,68,.2);}
        .btn-orange{background:rgba(249,115,22,.1);color:#f97316;border-color:rgba(249,115,22,.2);}
        .btn-orange:hover{background:rgba(249,115,22,.2);}
        .btn-blue{background:rgba(56,189,248,.1);color:#38bdf8;border-color:rgba(56,189,248,.2);}
        .btn-blue:hover{background:rgba(56,189,248,.2);}
        .btn-sky{background:rgba(14,165,233,.1);color:#0ea5e9;border-color:rgba(14,165,233,.2);}
        .btn-sky:hover{background:rgba(14,165,233,.2);}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar,.content{padding:.875rem 1rem;}
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
        function confirmDel(msg){return confirm('⚠️ '+msg+'\nCette action est irréversible.');}
        function openEmail(email){document.getElementById('modal-email').classList.remove('hidden');document.getElementById('email-to').value=email;}
        function closeEmail(){document.getElementById('modal-email').classList.add('hidden');}
    </script>
<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="sticky top-0 z-30 flex items-center justify-between border-b border-white/[.06] bg-[#111318] px-7 py-3.5">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="w-8 text-lg text-gray-400 hover:text-white md:hidden"><i class="fas fa-bars"></i></button>
            <div>
                <div class="text-sm font-bold text-white">Créer un serveur pour un client</div>
                <div class="text-xs text-gray-500">Provisionnement manuel admin</div>
            </div>
        </div>
        <a href="/admin/?view=servers" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-400 hover:bg-white/5 hover:text-white">
            <i class="fas fa-arrow-left text-[10px]"></i> Retour
        </a>
    </div>

    <div class="max-w-[680px] p-7">
        <?php if ($fm): ?>
        <div class="mb-5 flex items-center gap-2 rounded-xl p-4 text-sm font-medium <?= $ft==='ok' ? 'bg-green-500/[.08] border border-green-500/20 text-green-500' : 'bg-red-500/[.08] border border-red-500/20 text-red-500' ?>">
            <i class="fas fa-<?= $ft==='ok' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($fm) ?>
        </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-white/[.07] bg-[#161a22] p-7">
            <h2 class="mb-6 flex items-center gap-2 text-[.95rem] font-extrabold text-white">
                <i class="fas fa-server text-[.85rem] text-rose-500"></i> Nouveau serveur
            </h2>

            <form method="POST" id="createForm">
                <div class="mb-4 grid grid-cols-2 gap-4">
                    <!-- Client -->
                    <div class="col-span-2">
                        <label class="mb-1.5 block text-[.7rem] font-bold uppercase tracking-wider text-gray-500">Client *</label>
                        <select name="user_id" required class="w-full rounded-lg border border-white/[.08] bg-[#1e2330] px-3.5 py-2.5 text-[.83rem] text-slate-200">
                            <option value="">— Sélectionner un client —</option>
                            <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['email']) ?> (<?= htmlspecialchars($u['pseudo'] ?: $u['firstname'].' '.$u['lastname']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Offre -->
                    <div class="col-span-2">
                        <label class="mb-1.5 block text-[.7rem] font-bold uppercase tracking-wider text-gray-500">Offre / Produit *</label>
                        <select name="product_id" required id="productSelect" onchange="updatePrice()" class="w-full rounded-lg border border-white/[.08] bg-[#1e2330] px-3.5 py-2.5 text-[.83rem] text-slate-200">
                            <option value="">— Sélectionner une offre —</option>
                            <?php foreach ($all_products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>">
                                <?= htmlspecialchars($p['name']) ?> — <?= number_format($p['price'],2,',','') ?>€/mois
                                (<?= htmlspecialchars($p['egg_name']) ?> · <?= htmlspecialchars($p['node_name']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Durée -->
                    <div>
                        <label class="mb-1.5 block text-[.7rem] font-bold uppercase tracking-wider text-gray-500">Durée (jours) *</label>
                        <input type="number" name="days" value="30" min="1" max="3650" required class="w-full rounded-lg border border-white/[.08] bg-[#1e2330] px-3.5 py-2.5 text-[.83rem] text-slate-200">
                        <div class="mt-1 text-[.7rem] text-gray-500">Expiration automatique dans X jours</div>
                    </div>

                    <!-- Prix -->
                    <div>
                        <label class="mb-1.5 block text-[.7rem] font-bold uppercase tracking-wider text-gray-500">Prix facturé (€)</label>
                        <input type="number" name="price" id="priceInput" value="0" min="0" step="0.01" class="w-full rounded-lg border border-white/[.08] bg-[#1e2330] px-3.5 py-2.5 text-[.83rem] text-slate-200">
                        <div class="mt-1 text-[.7rem] text-gray-500">0€ = gratuit, facture marquée "free"</div>
                    </div>

                    <!-- Note interne -->
                    <div class="col-span-2">
                        <label class="mb-1.5 block text-[.7rem] font-bold uppercase tracking-wider text-gray-500">Note interne (optionnel)</label>
                        <input type="text" name="note" placeholder="Ex: client VIP, offre spéciale…" class="w-full rounded-lg border border-white/[.08] bg-[#1e2330] px-3.5 py-2.5 text-[.83rem] text-slate-200">
                    </div>
                </div>

                <!-- Récap -->
                <div id="recap" class="mb-5 hidden rounded-xl border border-rose-500/[.15] bg-rose-500/[.05] px-5 py-4 text-[.8rem] text-slate-100">
                    <div class="mb-2 text-[.7rem] font-bold uppercase tracking-wider text-rose-500">Récapitulatif</div>
                    <div id="recap-content"></div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="flex-1 rounded-lg border-none bg-rose-500 px-5 py-3 text-[.83rem] font-bold text-white transition-colors hover:bg-rose-600">
                        <i class="fas fa-rocket mr-1.5 text-[.75rem]"></i> Créer le serveur
                    </button>
                    <a href="/admin/?view=servers" class="flex items-center rounded-lg border border-white/[.07] bg-white/[.04] px-5 py-3 text-[.83rem] font-semibold text-gray-500 no-underline">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const products = <?= json_encode(array_column($all_products, null, 'id')) ?>;

function updatePrice() {
    const sel = document.getElementById('productSelect');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) { document.getElementById('recap').classList.add('hidden'); return; }
    const price = opt.dataset.price || '0';
    document.getElementById('priceInput').value = parseFloat(price).toFixed(2);
    const p = products[opt.value];
    if (p) {
        document.getElementById('recap-content').innerHTML =
            `<b>${p.name}</b> · ${p.egg_name} sur ${p.node_name}<br>
             RAM: <b>${p.ram} MB</b> · CPU: <b>${p.cpu}%</b> · Disque: <b>${p.disk} MB</b><br>
             BDD: ${p.databases} · Backups: ${p.backups} · Ports: ${p.allocations}`;
        document.getElementById('recap').classList.remove('hidden');
    }
}
</script>
