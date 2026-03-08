<?php
ini_set('display_errors',1); error_reporting(E_ALL);

require __DIR__.'/../db.php';
$cfg  = require __DIR__.'/../config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');
$pdo = db();

function norm($v){ return ($v === '' ? null : $v); }

try {
  $pdo->beginTransaction();

  $orderId = (int)($_POST['order_id'] ?? 0);
  if (!$orderId) throw new Exception('order_id required');

  // Lock row for update
  $st = $pdo->prepare("SELECT * FROM orders WHERE id=? FOR UPDATE");
  $st->execute([$orderId]);
  $oldOrder = $st->fetch(PDO::FETCH_ASSOC);
  if (!$oldOrder) throw new Exception('Order not found');

  $st = $pdo->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id LIMIT 1");
  $st->execute([$orderId]);
  $oldItem = $st->fetch(PDO::FETCH_ASSOC);

  // New values
  $newOrder = $oldOrder;
  $newOrder['client_id']        = (int)($_POST['client_id'] ?? $oldOrder['client_id']);
  $newOrder['due_date']         = norm($_POST['due_date'] ?? $oldOrder['due_date']);
  $newOrder['external_ref']     = norm($_POST['external_ref'] ?? $oldOrder['external_ref']);
  $newOrder['tracking_number']  = norm($_POST['tracking_number'] ?? $oldOrder['tracking_number']); // NEW
  $newOrder['customer_name']    = norm($_POST['customer_name'] ?? $oldOrder['customer_name']);
  $newOrder['customer_email']   = norm($_POST['customer_email'] ?? $oldOrder['customer_email']);
  $newOrder['customer_phone']   = norm($_POST['customer_phone'] ?? $oldOrder['customer_phone']);

  if ($newOrder['customer_email'] && !filter_var($newOrder['customer_email'], FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Invalid email format');
  }
  if ($newOrder['customer_phone'] && strlen(preg_replace('/\D+/', '', $newOrder['customer_phone'])) < 7) {
    throw new Exception('Invalid phone number');
  }

  // Item
  $newItem = $oldItem ?: [];
  $newItem['product_id'] = (int)($_POST['product_id'] ?? ($oldItem['product_id'] ?? 0));
  $newItem['color_id']   = (int)($_POST['color_id']   ?? ($oldItem['color_id']   ?? 0));
  $newItem['size_id']    = (int)($_POST['size_id']    ?? ($oldItem['size_id']    ?? 0));
  $newItem['design_id']  = (int)($_POST['design_id']  ?? ($oldItem['design_id']  ?? 0));
  $newItem['quantity']   = max(1, (int)($_POST['item_qty_1'] ?? ($oldItem['quantity'] ?? 1)));

  $prod = $newItem['product_id'] ? $pdo->query("SELECT id, sku, name FROM products WHERE id=".(int)$newItem['product_id'])->fetch(PDO::FETCH_ASSOC) : null;
  $col  = $newItem['color_id']   ? $pdo->query("SELECT id, name FROM colors WHERE id=".(int)$newItem['color_id'])->fetch(PDO::FETCH_ASSOC) : null;
  $siz  = $newItem['size_id']    ? $pdo->query("SELECT id, label FROM sizes WHERE id=".(int)$newItem['size_id'])->fetch(PDO::FETCH_ASSOC) : null;
  $des  = $newItem['design_id']  ? $pdo->query("SELECT id, name FROM designs WHERE id=".(int)$newItem['design_id'])->fetch(PDO::FETCH_ASSOC) : null;

  // Changeset
  $changes = [];
  $fields = [
    'client_id'        => ['before'=>$oldOrder['client_id'],       'after'=>$newOrder['client_id']],
    'due_date'         => ['before'=>$oldOrder['due_date'],        'after'=>$newOrder['due_date']],
    'external_ref'     => ['before'=>$oldOrder['external_ref'],    'after'=>$newOrder['external_ref']],
    'tracking_number'  => ['before'=>$oldOrder['tracking_number'] ?? null, 'after'=>$newOrder['tracking_number']], // NEW
    'customer_name'    => ['before'=>$oldOrder['customer_name'],   'after'=>$newOrder['customer_name']],
    'customer_email'   => ['before'=>$oldOrder['customer_email'],  'after'=>$newOrder['customer_email']],
    'customer_phone'   => ['before'=>$oldOrder['customer_phone'],  'after'=>$newOrder['customer_phone']],
    'product_id'       => ['before'=>$oldItem['product_id'] ?? null, 'after'=>$newItem['product_id']],
    'color_id'         => ['before'=>$oldItem['color_id']   ?? null, 'after'=>$newItem['color_id']],
    'size_id'          => ['before'=>$oldItem['size_id']    ?? null, 'after'=>$newItem['size_id']],
    'design_id'        => ['before'=>$oldItem['design_id']  ?? null, 'after'=>$newItem['design_id']],
    'quantity'         => ['before'=>$oldItem['quantity']   ?? null, 'after'=>$newItem['quantity']],
  ];
  foreach ($fields as $k=>$v) {
    $before = ($v['before'] === '' ? null : $v['before']);
    $after  = ($v['after']  === '' ? null : $v['after']);
    if ($before != $after) $changes[$k] = ['before'=>$before,'after'=>$after];
  }

  if (!$changes) {
    $pdo->rollBack();
    header("Location: {$base}/pages/orders_edit.php?id={$orderId}");
    exit;
  }

  // Update order core
  $up = $pdo->prepare("
    UPDATE orders
       SET client_id=?,
           customer_name=?,
           customer_email=?,
           customer_phone=?,
           external_ref=?,
           tracking_number=?,
           due_date=?,
           last_edited_at=NOW(),
           edited_count=edited_count+1
     WHERE id=?
  ");
  $up->execute([
    $newOrder['client_id'],
    $newOrder['customer_name'],
    $newOrder['customer_email'],
    $newOrder['customer_phone'],
    $newOrder['external_ref'],
    $newOrder['tracking_number'],
    $newOrder['due_date'],
    $orderId
  ]);

  // Update/insert first item
  if ($oldItem) {
    $up = $pdo->prepare("
      UPDATE order_items
         SET product_id=?, color_id=?, size_id=?, design_id=?,
             sku=?, name=?, quantity=?,
             customization=JSON_OBJECT('color', ?, 'size', ?, 'design', ?)
       WHERE id=?
    ");
    $up->execute([
      $prod['id'] ?? null, $col['id'] ?? null, $siz['id'] ?? null, $des['id'] ?? null,
      $prod['sku'] ?? null, $prod['name'] ?? 'Custom Item', $newItem['quantity'],
      $col['name'] ?? null, $siz['label'] ?? null, $des['name'] ?? null,
      $oldItem['id']
    ]);
  } else {
    $ins = $pdo->prepare("
      INSERT INTO order_items(order_id, product_id, color_id, size_id, design_id, sku, name, quantity, unit_price, customization)
      VALUES(?,?,?,?,?,?,?,?,?, JSON_OBJECT('color', ?, 'size', ?, 'design', ?))
    ");
    $ins->execute([
      $orderId,
      $prod['id'] ?? null, $col['id'] ?? null, $siz['id'] ?? null, $des['id'] ?? null,
      $prod['sku'] ?? null, $prod['name'] ?? 'Custom Item', $newItem['quantity'],
      0,
      $col['name'] ?? null, $siz['label'] ?? null, $des['name'] ?? null
    ]);
  }

  // Audit
  $insAudit = $pdo->prepare("INSERT INTO order_audits(order_id, stage, changes_json) VALUES(?,?,?)");
  $insAudit->execute([
    $orderId,
    $oldOrder['status'] ?? null,
    json_encode($changes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
  ]);

  $pdo->commit();
  header("Location: {$base}/pages/orders_edit.php?id={$orderId}&saved=1");
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo "Error: " . htmlspecialchars($e->getMessage());
}
