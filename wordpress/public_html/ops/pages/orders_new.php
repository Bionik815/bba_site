<?php
// /ops/pages/orders_new.php
require __DIR__ . '/../bootstrap.php';

$root = realpath(__DIR__ . '/..'); if (!$root) die('Path error');

require $root . '/config.php';
require $root . '/db.php';
require $root . '/auth.php';
require_login();

$pdo  = db();
$base = rtrim(($cfg['app']['base_path'] ?? '/ops'), '/');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- helpers ----------
function save_edit($pdo,$orderId,$userId,$field,$old,$new){
  $st = $pdo->prepare("INSERT INTO order_edits(order_id,user_id,field,old_value,new_value,created_at)
                       VALUES(?,?,?,?,?,NOW())");
  $st->execute([$orderId,$userId,$field,(string)$old,(string)$new]);
  $pdo->prepare("UPDATE orders SET edited_count=edited_count+1, last_edited_at=NOW() WHERE id=?")
      ->execute([$orderId]);
}

// ---------- preload dropdowns ----------
// IMPORTANT: we now read from `customers` (the same table you use in Admin)
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// These are only for graceful initial UI; actual allowed options load via AJAX
$colors   = $pdo->query("SELECT id, name FROM colors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$sizes    = $pdo->query("SELECT id, label FROM sizes ORDER BY sort_order, label")->fetchAll(PDO::FETCH_ASSOC);

// ---------- dependent options API (uses customer_id consistently) ----------
if (isset($_GET['api']) && $_GET['api'] === 'options') {
  header('Content-Type: application/json');
  $type = $_GET['type'] ?? '';
  $cust = (int)($_GET['customer_id'] ?? $_GET['client_id'] ?? 0); // accept either param name
  $pid  = (int)($_GET['product_id'] ?? 0);

  if ($type === 'products' && $cust) {
    $sql = "
      SELECT DISTINCT p.id, p.name
      FROM client_products cp
      JOIN products p ON p.id = cp.product_id AND p.active=1
      WHERE cp.customer_id = ? AND cp.active = 1
      ORDER BY p.name
    ";
    $st = $pdo->prepare($sql); $st->execute([$cust]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC)); exit;
  }

  if ($type === 'colors' && $cust && $pid) {
    $sql = "
      SELECT DISTINCT c.id, c.name
      FROM client_product_colors x
      JOIN colors c ON c.id = x.color_id
      WHERE x.customer_id = ? AND x.product_id = ?
      ORDER BY c.name
    ";
    $st = $pdo->prepare($sql); $st->execute([$cust,$pid]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC)); exit;
  }

  if ($type === 'sizes' && $cust && $pid) {
    $sql = "
      SELECT DISTINCT s.id, s.label
      FROM client_product_sizes x
      JOIN sizes s ON s.id = x.size_id
      WHERE x.customer_id = ? AND x.product_id = ?
      ORDER BY s.sort_order, s.label
    ";
    $st = $pdo->prepare($sql); $st->execute([$cust,$pid]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC)); exit;
  }

  if ($type === 'designs' && $cust && $pid) {
    $sql = "
      SELECT DISTINCT d.id, d.name
      FROM client_product_designs x
      JOIN designs d ON d.id = x.design_id
      WHERE x.customer_id = ? AND x.product_id = ?
      ORDER BY d.name
    ";
    $st = $pdo->prepare($sql); $st->execute([$cust,$pid]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC)); exit;
  }

  echo json_encode([]); exit;
}

// ---------- create order ----------
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__action'] ?? '') === 'create') {
  verify_csrf_or_die();

  $customer_id = (int)($_POST['customer_id'] ?? 0); // selected customer
  $product_id  = (int)($_POST['product_id'] ?? 0);
  $color_id    = (int)($_POST['color_id'] ?? 0);
  $size_id     = (int)($_POST['size_id'] ?? 0);
  $design_id   = (int)($_POST['design_id'] ?? 0);
  $qty         = max(1, (int)($_POST['quantity'] ?? 1));

  $external_ref = trim($_POST['external_ref'] ?? '');
  $status = 'received';

  $cust_name  = trim($_POST['customer_name'] ?? '');
  $cust_email = trim($_POST['customer_email'] ?? '');
  $cust_phone = trim($_POST['customer_phone'] ?? '');

  if (!$customer_id || !$product_id) {
    $notice = 'Please select a client and a product.';
  } else {
    $pdo->beginTransaction();
    try {
      // We still store it in orders.client_id, but it is actually the customers.id value
      $ins = $pdo->prepare("
        INSERT INTO orders
          (client_id, product_id, color_id, size_id, design_id,
           quantity, status, external_ref, customer_name, customer_email, customer_phone,
           created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())
      ");
      $ins->execute([
        $customer_id ?: null,
        $product_id ?: null,
        $color_id ?: null,
        $size_id ?: null,
        $design_id ?: null,
        $qty,
        $status,
        $external_ref ?: null,
        $cust_name ?: null,
        $cust_email ?: null,
        $cust_phone ?: null
      ]);

      $orderId = (int)$pdo->lastInsertId();
      save_edit($pdo, $orderId, ($_SESSION['user_id'] ?? null), 'created', '', 'created');

      $pdo->commit();
      header("Location: {$base}/pages/orders_view.php?id={$orderId}");
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $notice = 'Error creating order: ' . h($e->getMessage());
    }
  }
}

include $root . '/partials/header.php';
?>
<style>
.card{background:#161616;border:1px solid #262626;border-radius:12px;padding:14px;margin-bottom:12px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
@media (max-width:1000px){.grid2,.grid3{grid-template-columns:1fr}}
label{display:block;font-size:12px;color:#aaa;margin:6px 0 4px}
input,select{width:100%;padding:8px 10px;background:#111;color:#eee;border:1px solid #2a2a2a;border-radius:8px}
button{padding:8px 12px;border:1px solid #2a2a2a;background:#1b1b1b;border-radius:8px;color:#eee;cursor:pointer}
.notice{background:#311010;color:#ffd6d6;border:1px solid #5a2323;border-radius:8px;padding:8px 10px;margin-bottom:10px}
.small{font-size:12px;opacity:.8}
</style>

<h2>New Order</h2>
<?php if ($notice): ?><div class="notice"><?= $notice ?></div><?php endif; ?>

<form method="post" id="orderForm">
  <?= csrf_input() ?>
  <input type="hidden" name="__action" value="create">

  <div class="card">
    <h3 style="margin:0 0 8px 0;">Order Details</h3>
    <div class="grid3">
      <div>
        <label>Client</label>
        <select name="customer_id" id="customer" required>
          <option value="">Select client…</option>
          <?php foreach($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Product</label>
        <select name="product_id" id="product" required disabled>
          <option value="">Select client first…</option>
        </select>
        <div class="small" id="prodHint" style="margin-top:6px; display:none;">
          No products linked to this client yet. Add them in Admin → Assign Product to Client.
        </div>
      </div>
      <div>
        <label>Quantity</label>
        <input type="number" name="quantity" value="1" min="1" required>
      </div>
    </div>

    <div class="grid3" style="margin-top:10px;">
      <div>
        <label>Color</label>
        <select name="color_id" id="color" disabled>
          <option value="">Select product first…</option>
        </select>
      </div>
      <div>
        <label>Size</label>
        <select name="size_id" id="size" disabled>
          <option value="">Select product first…</option>
        </select>
      </div>
      <div>
        <label>Design</label>
        <select name="design_id" id="design" disabled>
          <option value="">Select product first…</option>
        </select>
      </div>
    </div>

    <div class="grid2" style="margin-top:10px;">
      <div>
        <label>External Ref (optional)</label>
        <input name="external_ref" placeholder="PO #, Wix ID, etc.">
      </div>
      <div>
        <label>Status</label>
        <input value="Received" disabled>
      </div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px 0;">Customer</h3>
    <div class="grid3">
      <div>
        <label>Name</label>
        <input name="customer_name" placeholder="Customer name">
      </div>
      <div>
        <label>Email</label>
        <input type="email" name="customer_email" placeholder="email@example.com">
      </div>
      <div>
        <label>Phone</label>
        <input name="customer_phone" placeholder="(555) 555-5555">
      </div>
    </div>
  </div>

  <button type="submit">Create Order</button>
</form>

<script>
const base = "<?= $base ?>";
const customer = document.getElementById('customer');
const product  = document.getElementById('product');
const color    = document.getElementById('color');
const size     = document.getElementById('size');
const design   = document.getElementById('design');
const prodHint = document.getElementById('prodHint');

async function loadOpts(url, target, placeholder){
  target.innerHTML = `<option value="">${placeholder}</option>`;
  target.disabled = true;
  try {
    const rows = await fetch(url, {cache:'no-store'}).then(r=>r.json());
    if (rows.length) {
      target.innerHTML = rows.map(r => `<option value="${r.id}">${r.name || r.label}</option>`).join('');
      target.disabled = false;
      if (target === product) prodHint.style.display = 'none';
    } else {
      if (target === product) prodHint.style.display = 'block';
    }
  } catch(e){
    if (target === product) prodHint.style.display = 'block';
  }
}

customer.addEventListener('change', () => {
  const cid = customer.value;
  product.disabled = true; product.innerHTML = '<option value="">Loading…</option>';
  color.disabled = true;   color.innerHTML   = '<option value="">Select product first…</option>';
  size.disabled = true;    size.innerHTML    = '<option value="">Select product first…</option>';
  design.disabled = true;  design.innerHTML  = '<option value="">Select product first…</option>';
  prodHint.style.display = 'none';
  if (cid) loadOpts(`${base}/pages/orders_new.php?api=options&type=products&customer_id=${cid}`, product, 'Select product…');
});

product.addEventListener('change', () => {
  const cid = customer.value, pid = product.value;
  color.disabled = true; size.disabled = true; design.disabled = true;
  color.innerHTML  = '<option value="">Loading…</option>';
  size.innerHTML   = '<option value="">Loading…</option>';
  design.innerHTML = '<option value="">Loading…</option>';
  if (cid && pid) {
    loadOpts(`${base}/pages/orders_new.php?api=options&type=colors&customer_id=${cid}&product_id=${pid}`,  color,  'Select color…');
    loadOpts(`${base}/pages/orders_new.php?api=options&type=sizes&customer_id=${cid}&product_id=${pid}`,   size,   'Select size…');
    loadOpts(`${base}/pages/orders_new.php?api=options&type=designs&customer_id=${cid}&product_id=${pid}`, design, 'Select design…');
  }
});
</script>

<?php include $root . '/partials/footer.php'; ?>
