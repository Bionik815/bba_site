<?php
require __DIR__ . '/../auth.php';
require_login();

// /public_html/ops/pages/orders_view.php
ini_set('display_errors',1); error_reporting(E_ALL);

$root = realpath(__DIR__ . '/..'); if (!$root) die('Path error');
$cfg  = require $root . '/config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');

$authPath = $root . '/auth.php';
if (file_exists($authPath)) { require $authPath; if (function_exists('require_login')) require_login(); }

require $root . '/db.php'; 
$pdo = db();
session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function minutes_ago($ts){
  if (!$ts) return '';
  $dt = new DateTime($ts);
  $now = new DateTime('now');
  $diff = $now->getTimestamp() - $dt->getTimestamp();
  if ($diff < 60) return $diff.'s';
  if ($diff < 3600) return floor($diff/60).'m';
  if ($diff < 86400) return floor($diff/3600).'h';
  return floor($diff/86400).'d';
}
function save_edit($pdo,$orderId,$userId,$field,$old,$new){
  if ($old === $new) return;
  $st = $pdo->prepare("INSERT INTO order_edits(order_id,user_id,field,old_value,new_value,created_at)
                       VALUES(?,?,?,?,?,NOW())");
  $st->execute([$orderId,$userId,$field,(string)$old,(string)$new]);
  $pdo->prepare("UPDATE orders SET edited_count=edited_count+1, last_edited_at=NOW() WHERE id=?")
      ->execute([$orderId]);
}

// status map in display order you wanted
$STATUS = [
  'received'           => 'Received',
  'supplies_ordered'   => 'Supplies Ordered',
  'awaiting_supplies'  => 'Awaiting Supplies',
  'supplies_received'  => 'Supplies Received',
  'in_production'      => 'In Production',
  'ready_for_pickup'   => 'Ready for Customer',
  'shipped'            => 'Shipped',
  'delivered'          => 'Order Complete',
];
$DONE = ['shipped','delivered'];

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) { http_response_code(400); exit('Missing order id'); }

// ------------------ ACTIONS ------------------

// Reopen action
if (($_GET['do'] ?? '') === 'reopen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $to  = $_POST['to_status'] ?: 'in_production';
  $st  = $pdo->prepare("SELECT status FROM orders WHERE id=?");
  $st->execute([$orderId]);
  $from = $st->fetchColumn();
  if ($from) {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE orders SET status=?, completed_at=NULL, updated_at=NOW() WHERE id=?")
        ->execute([$to, $orderId]);

    // log to order_reopens if table exists
    try {
      $pdo->prepare("INSERT INTO order_reopens(order_id,user_id,from_status,to_status,reason)
                     VALUES(?,?,?,?,NULL)")
          ->execute([$orderId, ($_SESSION['user_id'] ?? null), $from, $to]);
    } catch (Throwable $e) { /* table might not exist yet; ignore */ }

    save_edit($pdo,$orderId,($_SESSION['user_id'] ?? null),'status',$from,$to);
    $pdo->commit();
  }
  header("Location: ".$base."/pages/orders_view.php?id=".$orderId);
  exit;
}

// Update status (and optionally tracking)
if (($_GET['do'] ?? '') === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $new = $_POST['status'] ?? '';
  if (!isset($STATUS[$new])) { http_response_code(400); exit('Bad status'); }

  // Load current
  $st = $pdo->prepare("SELECT status, completed_at, tracking_number FROM orders WHERE id=?");
  $st->execute([$orderId]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cur) { http_response_code(404); exit('Order not found'); }

  $userId = $_SESSION['user_id'] ?? null;
  $tracking = null;

  $pdo->beginTransaction();

  // shipped needs tracking number optionally; we still allow blank but display it
  if ($new === 'shipped') {
    $tracking = trim($_POST['tracking_number'] ?? '');
  }

  // compute completed_at rules
  $completedAtSql = 'completed_at = NULL';
  if (in_array($new, $DONE, true)) $completedAtSql = 'completed_at = IF(completed_at IS NULL, NOW(), completed_at)';

  // update row
  $sql = "UPDATE orders SET status=?, updated_at=NOW(), $completedAtSql".
         ($tracking !== null ? ", tracking_number=?" : "").
         " WHERE id=?";
  $params = [$new];
  if ($tracking !== null) $params[] = ($tracking !== '' ? $tracking : null);
  $params[] = $orderId;
  $pdo->prepare($sql)->execute($params);

  save_edit($pdo,$orderId,$userId,'status',$cur['status'],$new);
  if ($tracking !== null && $tracking !== ($cur['tracking_number'] ?? '')) {
    save_edit($pdo,$orderId,$userId,'tracking_number',$cur['tracking_number'],$tracking);
  }

  $pdo->commit();

  header("Location: ".$base."/pages/orders_view.php?id=".$orderId);
  exit;
}

// Update basic customer fields
if (($_GET['do'] ?? '') === 'update_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $st = $pdo->prepare("SELECT customer_name, customer_email, customer_phone FROM orders WHERE id=?");
  $st->execute([$orderId]);
  $old = $st->fetch(PDO::FETCH_ASSOC) ?: ['customer_name'=>'','customer_email'=>'','customer_phone'=>''];

  $name  = trim($_POST['customer_name'] ?? '');
  $email = trim($_POST['customer_email'] ?? '');
  $phone = trim($_POST['customer_phone'] ?? '');

  $pdo->prepare("UPDATE orders SET customer_name=?, customer_email=?, customer_phone=?, updated_at=NOW() WHERE id=?")
      ->execute([$name ?: null, $email ?: null, $phone ?: null, $orderId]);

  $uid = $_SESSION['user_id'] ?? null;
  save_edit($pdo,$orderId,$uid,'customer_name',$old['customer_name'],$name);
  save_edit($pdo,$orderId,$uid,'customer_email',$old['customer_email'],$email);
  save_edit($pdo,$orderId,$uid,'customer_phone',$old['customer_phone'],$phone);

  header("Location: ".$base."/pages/orders_view.php?id=".$orderId);
  exit;
}

// ------------------ LOAD DATA ------------------

$st = $pdo->prepare("
  SELECT o.*, c.name AS client_name, p.name AS product_name
  FROM orders o
  LEFT JOIN clients  c ON c.id = o.client_id
  LEFT JOIN products p ON p.id = o.product_id
  WHERE o.id=?
");
$st->execute([$orderId]);
$order = $st->fetch(PDO::FETCH_ASSOC);
if (!$order) { http_response_code(404); exit('Order not found'); }

$age     = minutes_ago($order['created_at']);
$stageForAge = minutes_ago($order['updated_at']);

$hist = $pdo->prepare("SELECT * FROM order_edits WHERE order_id=? ORDER BY created_at DESC");
$hist->execute([$orderId]);
$edits = $hist->fetchAll(PDO::FETCH_ASSOC);

// Related orders by email first, else by name
$rel = [];
if (!empty($order['customer_email'])) {
  $q = $pdo->prepare("SELECT id, external_ref, status, created_at FROM orders WHERE customer_email=? AND id<>? ORDER BY created_at DESC LIMIT 25");
  $q->execute([$order['customer_email'],$orderId]);
  $rel = $q->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($order['customer_name'])) {
  $q = $pdo->prepare("SELECT id, external_ref, status, created_at FROM orders WHERE customer_name=? AND id<>? ORDER BY created_at DESC LIMIT 25");
  $q->execute([$order['customer_name'],$orderId]);
  $rel = $q->fetchAll(PDO::FETCH_ASSOC);
}

include $root . '/partials/header.php';
?>
<style>
.wrap{display:grid;gap:14px}
.card{background:#161616;border:1px solid #262626;border-radius:12px;padding:14px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media (max-width:1000px){.grid2{grid-template-columns:1fr}}
label{display:block;font-size:12px;color:#aaa;margin:6px 0 4px}
input,select,textarea{width:100%;padding:8px 10px;background:#111;color:#eee;border:1px solid #2a2a2a;border-radius:8px}
.badge{display:inline-block;padding:2px 8px;border:1px solid #333;border-radius:999px;font-size:12px;background:#0e0e0e}
.list{display:grid;gap:8px}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.small{font-size:12px;opacity:.8}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid #222;padding:8px 6px;text-align:left;font-size:14px}
.kv{display:grid;grid-template-columns:160px 1fr;gap:8px;font-size:14px}
.btn{padding:8px 12px;border:1px solid #2a2a2a;background:#1b1b1b;border-radius:8px;color:#eee;text-decoration:none}
</style>

<h2>Order #<?= (int)$order['id'] ?> <?= $order['external_ref'] ? '· '.h($order['external_ref']) : '' ?></h2>
<div class="row">
  <span class="badge"><?= h($STATUS[$order['status']] ?? $order['status']) ?></span>
  <span class="small">Age: <?= h($age) ?></span>
  <span class="small">In current stage: <?= h($stageForAge) ?></span>
  <?php if (in_array($order['status'],$DONE,true)): ?>
    <span class="small">Completed: <?= h($order['completed_at'] ?: '') ?></span>
  <?php endif; ?>
</div>

<div class="wrap">

  <!-- Meta -->
  <div class="card">
    <div class="grid2">
      <div>
        <div class="kv">
          <div>Client</div><div><?= h($order['client_name'] ?: '-') ?></div>
          <div>Product</div><div><?= h($order['product_name'] ?: '-') ?></div>
          <div>Due date</div><div><?= h($order['due_date'] ?: '-') ?></div>
          <div>Priority</div><div><?= h($order['priority'] ?? '-') ?></div>
        </div>
      </div>
      <div>
        <form method="post" action="?id=<?= (int)$orderId ?>&do=update_status">
          <label>Status</label>
          <select name="status" onchange="document.getElementById('tnWrap').style.display=(this.value==='shipped')?'block':'none'">
            <?php foreach($STATUS as $k=>$v): ?>
              <option value="<?= h($k) ?>" <?= $order['status']===$k?'selected':'' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>

          <div id="tnWrap" style="display:<?= $order['status']==='shipped'?'block':'none' ?>;margin-top:8px;">
            <label>Tracking Number</label>
            <input name="tracking_number" value="<?= h($order['tracking_number'] ?? '') ?>" placeholder="Optional but nice">
          </div>

          <div class="row" style="margin-top:10px;">
            <button class="btn" type="submit">Update Status</button>

            <?php if (in_array($order['status'],$DONE,true)): ?>
              <form method="post" action="?id=<?= (int)$orderId ?>&do=reopen" style="display:inline">
                <input type="hidden" name="to_status" value="in_production">
                <button class="btn" type="submit">Reopen Order</button>
              </form>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Customer -->
  <div class="card">
    <h3 style="margin:0 0 8px 0;">Customer</h3>
    <form method="post" action="?id=<?= (int)$orderId ?>&do=update_customer">
      <div class="grid2">
        <div>
          <label>Name</label>
          <input name="customer_name" value="<?= h($order['customer_name'] ?? '') ?>">
        </div>
        <div>
          <label>Email</label>
          <input type="email" name="customer_email" value="<?= h($order['customer_email'] ?? '') ?>">
        </div>
      </div>
      <div class="grid2" style="margin-top:8px;">
        <div>
          <label>Phone</label>
          <input name="customer_phone" value="<?= h($order['customer_phone'] ?? '') ?>">
        </div>
        <div>
          <?php if ($order['status']==='shipped'): ?>
            <label>Tracking Number</label>
            <input disabled value="<?= h($order['tracking_number'] ?? '') ?>">
          <?php else: ?>
            <label>&nbsp;</label>
            <div class="small">Tracking number can be set when status is “Shipped.”</div>
          <?php endif; ?>
        </div>
      </div>
      <button class="btn" type="submit" style="margin-top:10px;">Save Customer</button>
    </form>
  </div>

  <!-- Edit history -->
  <div class="card">
    <h3 style="margin:0 0 8px 0;">Edit History</h3>
    <?php if (!$edits): ?>
      <div class="small">No edits recorded yet.</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>When</th><th>User</th><th>Field</th><th>Old</th><th>New</th></tr></thead>
        <tbody>
        <?php foreach($edits as $e): ?>
          <tr>
            <td><?= h($e['created_at']) ?></td>
            <td><?= h($e['user_id'] ?? '-') ?></td>
            <td><?= h($e['field']) ?></td>
            <td><?= h($e['old_value']) ?></td>
            <td><?= h($e['new_value']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Other orders from same customer -->
  <div class="card">
    <h3 style="margin:0 0 8px 0;">Other Orders from This Customer</h3>
    <?php if (!$rel): ?>
      <div class="small">None found.</div>
    <?php else: ?>
      <div class="list">
        <?php foreach($rel as $r): ?>
          <div class="row">
            <a class="btn" href="<?= $base ?>/pages/orders_view.php?id=<?= (int)$r['id'] ?>">View</a>
            <div>#<?= (int)$r['id'] ?> <?= $r['external_ref'] ? '· '.h($r['external_ref']) : '' ?></div>
            <div class="small"><?= h($STATUS[$r['status']] ?? $r['status']) ?></div>
            <div class="small">Created <?= h($r['created_at']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include $root . '/partials/footer.php'; ?>
