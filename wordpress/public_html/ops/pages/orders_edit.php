<?php
require __DIR__ . '/../auth.php';
require_login();

require __DIR__ . '/../bootstrap.php';

$root = realpath(__DIR__ . '/..'); if (!$root) die('Path error');
$cfg  = require $root . '/config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');

require $root . '/db.php';
$pdo = db();

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { http_response_code(400); die('Order id required'); }

$order = $pdo->prepare("SELECT * FROM orders WHERE id=?");
$order->execute([$orderId]);
$o = $order->fetch(PDO::FETCH_ASSOC);
if (!$o) { http_response_code(404); die('Order not found'); }

$item = $pdo->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id LIMIT 1");
$item->execute([$orderId]);
$it = $item->fetch(PDO::FETCH_ASSOC);

$clients = $pdo->query("SELECT id, name FROM clients WHERE active=1 ORDER BY name")->fetchAll();

$products = [];
if (!empty($o['client_id'])) {
  $stmt = $pdo->prepare("
    SELECT p.id, COALESCE(p.sku,'') AS sku, p.name
    FROM client_products cp
    JOIN products p ON p.id=cp.product_id AND p.active=1
    WHERE cp.client_id=? AND cp.active=1
    ORDER BY p.name
  ");
  $stmt->execute([$o['client_id']]);
  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$colors = $sizes = $designs = [];
if (!empty($o['client_id']) && !empty($it['product_id'])) {
  $cid = (int)$o['client_id']; $pid = (int)$it['product_id'];

  $q = $pdo->prepare("
    SELECT c.id, c.name FROM client_product_colors x
    JOIN colors c ON c.id=x.color_id
    WHERE x.client_id=? AND x.product_id=? ORDER BY c.name
  "); $q->execute([$cid,$pid]); $colors = $q->fetchAll(PDO::FETCH_ASSOC);

  $q = $pdo->prepare("
    SELECT s.id, s.code, s.label FROM client_product_sizes x
    JOIN sizes s ON s.id=x.size_id
    WHERE x.client_id=? AND x.product_id=? ORDER BY s.sort_order, s.label
  "); $q->execute([$cid,$pid]); $sizes = $q->fetchAll(PDO::FETCH_ASSOC);

  $q = $pdo->prepare("
    SELECT d.id, d.name
    FROM client_product_designs cpd
    JOIN designs d ON d.id = cpd.design_id AND d.client_id = cpd.client_id
    WHERE cpd.client_id=? AND cpd.product_id=? ORDER BY d.name
  "); $q->execute([$cid,$pid]); $designs = $q->fetchAll(PDO::FETCH_ASSOC);
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

include $root . '/partials/header.php';
?>
<h2>Edit Order #<?= (int)$o['id'] ?></h2>

<div class="card">
  <form method="post" action="<?= $base ?>/api/orders_update.php" id="orderForm">
    <?= csrf_input() ?>
    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">

    <label>Client (Storefront)</label>
    <select name="client_id" id="clientSel" required>
      <option value="">Select client…</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === (int)$o['client_id']) ? 'selected' : '' ?>>
          <?= h($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Product</label>
    <select name="product_id" id="productSel" required <?= empty($products) ? 'disabled' : '' ?>>
      <option value="">Select product…</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)$p['id'] === (int)($it['product_id'] ?? 0)) ? 'selected' : '' ?>>
          <?= h($p['name']) ?><?= $p['sku'] ? ' ('.h($p['sku']).')' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Color</label>
    <select name="color_id" id="colorSel" required <?= empty($colors) ? 'disabled' : '' ?>>
      <option value="">Select color…</option>
      <?php foreach ($colors as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === (int)($it['color_id'] ?? 0)) ? 'selected' : '' ?>>
          <?= h($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Size</label>
    <select name="size_id" id="sizeSel" required <?= empty($sizes) ? 'disabled' : '' ?>>
      <option value="">Select size…</option>
      <?php foreach ($sizes as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === (int)($it['size_id'] ?? 0)) ? 'selected' : '' ?>>
          <?= h($s['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Design</label>
    <select name="design_id" id="designSel" required <?= empty($designs) ? 'disabled' : '' ?>>
      <option value="">Select design…</option>
      <?php foreach ($designs as $d): ?>
        <option value="<?= (int)$d['id'] ?>" <?= ((int)$d['id'] === (int)($it['design_id'] ?? 0)) ? 'selected' : '' ?>>
          <?= h($d['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Quantity</label>
    <input name="item_qty_1" type="number" min="1" value="<?= (int)($it['quantity'] ?? 1) ?>" required>

    <label>Due Date</label>
    <input name="due_date" type="date" value="<?= h((string)$o['due_date']) ?>">

    <label>External Ref (Wix/PO)</label>
    <input name="external_ref" value="<?= h((string)$o['external_ref']) ?>">

    <label>Tracking Number (for Shipped/Delivered)</label>
    <input name="tracking_number" value="<?= h((string)$o['tracking_number']) ?>" placeholder="1Z..., TBA..., 9400..., etc.">

    <hr style="margin:14px 0; opacity:.25;">
    <h3 style="margin-top:0;">Customer Contact</h3>

    <label>Name</label>
    <input name="customer_name" value="<?= h((string)$o['customer_name']) ?>" maxlength="150">

    <label>Email</label>
    <input name="customer_email" type="email" value="<?= h((string)$o['customer_email']) ?>" maxlength="190">

    <label>Phone</label>
    <input name="customer_phone" value="<?= h((string)$o['customer_phone']) ?>" maxlength="40">

    <button type="submit" style="margin-top:12px;">Save Changes</button>
  </form>
</div>

<script>
const base = "<?= $base ?>";
const clientSel  = document.getElementById('clientSel');
const prodSel    = document.getElementById('productSel');
const colorSel   = document.getElementById('colorSel');
const sizeSel    = document.getElementById('sizeSel');
const designSel  = document.getElementById('designSel');

function resetSelect(sel, label){
  sel.innerHTML = `<option value="">Select ${label}…</option>`;
  sel.disabled = true;
}
function enableFromData(sel, label, rows, build) {
  sel.innerHTML = `<option value="">Select ${label}…</option>` + rows.map(build).join('');
  sel.disabled = rows.length === 0;
}

clientSel.addEventListener('change', async () => {
  const cid = clientSel.value;
  resetSelect(prodSel, 'product');
  resetSelect(colorSel, 'color');
  resetSelect(sizeSel, 'size');
  resetSelect(designSel, 'design');
  if(!cid) return;

  const products = await fetch(`${base}/api/options_products.php?client_id=${cid}`).then(r=>r.json());
  enableFromData(prodSel, 'product', products, x => `<option value="${x.id}">${x.name}${x.sku?` (${x.sku})`:''}</option>`);
});

prodSel.addEventListener('change', async () => {
  const cid = clientSel.value, pid = prodSel.value;
  resetSelect(colorSel, 'color');
  resetSelect(sizeSel, 'size');
  resetSelect(designSel, 'design');
  if(!cid || !pid) return;

  const colors  = await fetch(`${base}/api/options_colors.php?client_id=${cid}&product_id=${pid}`).then(r=>r.json());
  enableFromData(colorSel, 'color', colors, x => `<option value="${x.id}">${x.name}</option>`);

  const sizes   = await fetch(`${base}/api/options_sizes.php?client_id=${cid}&product_id=${pid}`).then(r=>r.json());
  enableFromData(sizeSel, 'size', sizes, x => `<option value="${x.id}">${x.label}</option>`);

  const designs = await fetch(`${base}/api/options_designs.php?client_id=${cid}&product_id=${pid}`).then(r=>r.json());
  enableFromData(designSel, 'design', designs, x => `<option value="${x.id}">${x.name}</option>`);
});
</script>

<?php include $root . '/partials/footer.php'; ?>
