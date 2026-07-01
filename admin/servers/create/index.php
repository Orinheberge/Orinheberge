<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

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
            $server_id, $uuid, $uuid,
            $price, $price, $next_pay, $expires_at,
            $product['id']
        ]);

        // 5. Créer une facture si prix > 0
        if ($price > 0) {
            $inv_num   = 'INV-' . date('Y') . '-' . str_pad((int)$pdo->query("SELECT COUNT(*)+1 FROM invoices")->fetchColumn(), 5, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO invoices (invoice_id,user_id,order_id,service_name,amount,type,status,payment_method,paid_at) VALUES (?,?,?,?,?,'purchase','paid','admin',NOW())")
                ->execute([$inv_num, $client['id'], $order_id, $product['name'], $price]);
        }

        header('Location: /admin/?view=servers&created=' . urlencode($order_id));
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
<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="topbar" style="background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8"><i class="fas fa-bars"></i></button>
            <div>
                <div class="text-sm font-bold text-white">Créer un serveur pour un client</div>
                <div class="text-xs text-gray-500">Provisionnement manuel admin</div>
            </div>
        </div>
        <a href="/admin/?view=servers" class="text-xs text-gray-400 hover:text-white font-semibold px-3 py-1.5 rounded-lg hover:bg-white/5 flex items-center gap-1.5">
            <i class="fas fa-arrow-left text-[10px]"></i> Retour
        </a>
    </div>

    <div style="padding:1.75rem;max-width:680px;">
        <?php if ($fm): ?>
        <div class="mb-5 p-4 rounded-xl text-sm font-medium flex items-center gap-2" style="<?= $ft==='ok' ? 'background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#22c55e;' : 'background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#ef4444;' ?>">
            <i class="fas fa-<?= $ft==='ok' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($fm) ?>
        </div>
        <?php endif; ?>

        <div style="background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.75rem;">
            <h2 style="font-size:.95rem;font-weight:800;color:#fff;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
                <i class="fas fa-server" style="color:#f43f5e;font-size:.85rem;"></i> Nouveau serveur
            </h2>

            <form method="POST" id="createForm">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <!-- Client -->
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;margin-bottom:.4rem;">Client *</label>
                        <select name="user_id" required style="background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;">
                            <option value="">— Sélectionner un client —</option>
                            <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['email']) ?> (<?= htmlspecialchars($u['pseudo'] ?: $u['firstname'].' '.$u['lastname']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Offre -->
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;margin-bottom:.4rem;">Offre / Produit *</label>
                        <select name="product_id" required id="productSelect" onchange="updatePrice()" style="background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;">
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
                        <label style="display:block;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;margin-bottom:.4rem;">Durée (jours) *</label>
                        <input type="number" name="days" value="30" min="1" max="3650" required style="background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;">
                        <div style="font-size:.7rem;color:#6b7280;margin-top:.3rem;">Expiration automatique dans X jours</div>
                    </div>

                    <!-- Prix -->
                    <div>
                        <label style="display:block;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;margin-bottom:.4rem;">Prix facturé (€)</label>
                        <input type="number" name="price" id="priceInput" value="0" min="0" step="0.01" style="background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;">
                        <div style="font-size:.7rem;color:#6b7280;margin-top:.3rem;">0€ = gratuit, pas de facture générée</div>
                    </div>

                    <!-- Note interne -->
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;margin-bottom:.4rem;">Note interne (optionnel)</label>
                        <input type="text" name="note" placeholder="Ex: client VIP, offre spéciale…" style="background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;">
                    </div>
                </div>

                <!-- Récap -->
                <div id="recap" style="background:rgba(244,63,94,.05);border:1px solid rgba(244,63,94,.15);border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.8rem;color:#f1f5f9;display:none;">
                    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#f43f5e;margin-bottom:.5rem;">Récapitulatif</div>
                    <div id="recap-content"></div>
                </div>

                <div style="display:flex;gap:.75rem;">
                    <button type="submit" style="flex:1;background:#f43f5e;color:#fff;font-weight:700;padding:.75rem 1.25rem;border-radius:.625rem;font-size:.83rem;border:none;cursor:pointer;transition:background .15s;" onmouseover="this.style.background='#e11d48'" onmouseout="this.style.background='#f43f5e'">
                        <i class="fas fa-rocket" style="margin-right:.4rem;font-size:.75rem;"></i> Créer le serveur
                    </button>
                    <a href="/admin/?view=servers" style="padding:.75rem 1.25rem;border-radius:.625rem;font-size:.83rem;font-weight:600;color:#6b7280;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);text-decoration:none;display:flex;align-items:center;">
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
    if (!opt.value) { document.getElementById('recap').style.display='none'; return; }
    const price = opt.dataset.price || '0';
    document.getElementById('priceInput').value = parseFloat(price).toFixed(2);
    const p = products[opt.value];
    if (p) {
        document.getElementById('recap-content').innerHTML =
            `<b>${p.name}</b> · ${p.egg_name} sur ${p.node_name}<br>
             RAM: <b>${p.ram} MB</b> · CPU: <b>${p.cpu}%</b> · Disque: <b>${p.disk} MB</b><br>
             BDD: ${p.databases} · Backups: ${p.backups} · Ports: ${p.allocations}`;
        document.getElementById('recap').style.display = 'block';
    }
}
</script>
