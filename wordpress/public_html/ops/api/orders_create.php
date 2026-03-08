<?php
require __DIR__.'/../bootstrap.php';

require __DIR__.'/../db.php';
require __DIR__.'/../auth.php';
require_login();
verify_csrf_or_die();
$cfg  = require __DIR__.'/../config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');
$pdo = db();

try {
  $pdo->beginTransaction();

  // Required: client
  $clientId = (int)($_POST['client_id'] ?? 0);
  if (!$clientId) throw new Exception('Client is required');

  $chk = $pdo->prepare("SELECT id FROM clients WHERE id=? AND active=1");
  $chk->execute([$clientId]);
  if (!$chk->fetch()) throw new Exception('Client not found');

  // Optional customer contact (basic sanity checks)
  $customer_name  = trim($_POST['customer_name']  ?? '');
  $customer_email = trim($_POST['customer_email'] ?? '');
  $customer_phone = trim($_POST['customer_phone'] ?? '');

  if ($customer_email && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Invalid email format');
  }
  if ($customer_phone && strlen(preg_replace('/\D+/', '', $customer_phone)) < 7) {
    throw new Exception('Invalid phone number');
  }

  // Create order
  $stmt = $pdo->prepare("
    INSERT INTO orders (
      external_ref,
      client_id,
      customer_id,
      customer_name,
      customer_email,
      customer_phone,
      intake_channel,
      status,
      priority,
      due_date
    ) VALUES (?,?,?,?,?,?,?,?,?,?)
  ");

  $stmt->execute([
    $_POST['external_ref'] ?? null,
    $clientId,
    null, // future: real customers table
    $customer_name ?: null,
    $customer_email ?: null,
    $customer_phone ?: null,
    'web',                // later: let user choose 'phone'/'paper'
    'received',
    3,
    !empty($_POST['due_date']) ? $_POST['due_date'] : null
  ]);

  $orderId = $pdo->lastInsertId();

  // Resolve product/color/size/design
  $pid = (int)($_POST['product_id'] ?? 0);
  $cid = (int)($_POST['color_id'] ?? 0);
  $sid = (int)($_POST['size_id'] ?? 0);
  $did = (int)($_POST['design_id'] ?? 0);
  $qty = max(1, (int)($_POST['item_qty_1'] ?? 1));

  $prod = $pid ? $pdo->query("SELECT id, sku, name FROM products WHERE id={$pid}")->fetch(PDO::FETCH_ASSOC) : null;
  $col  = $cid ? $pdo->query("SELECT id, name FROM colors WHERE id={$cid}")->fetch(PDO::FETCH_ASSOC) : null;
  $siz  = $sid ? $pdo->query("SELECT id, label FROM sizes  WHERE id={$sid}")->fetch(PDO::FETCH_ASSOC) : null;
  $des  = $did ? $pdo->query("SELECT id, name FROM designs WHERE id={$did}")->fetch(PDO::FETCH_ASSOC) : null;

  // Insert single line item (store IDs + human-friendly customization JSON)
  $insItem = $pdo->prepare("
    INSERT INTO order_items(order_id, product_id, color_id, size_id, design_id, sku, name, quantity, unit_price, customization)
    VALUES(?,?,?,?,?,?,?,?,?, JSON_OBJECT('color', ?, 'size', ?, 'design', ?))
  ");
  $insItem->execute([
    $orderId,
    $prod['id']  ?? null,
    $col['id']   ?? null,
    $siz['id']   ?? null,
    $des['id']   ?? null,
    $prod['sku'] ?? null,
    $prod['name'] ?? 'Custom Item',
    $qty,
    0, // price later
    $col['name']   ?? null,
    $siz['label']  ?? null,
    $des['name']   ?? null
  ]);

  // Initial status history
  $pdo->prepare("INSERT INTO status_history(order_id, from_status, to_status) VALUES(?,?,?)")
      ->execute([$orderId, null, 'received']);

  $pdo->commit();
  header("Location: {$base}/pages/orders_board.php?ok=1");
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo "Error: " . htmlspecialchars($e->getMessage());
}
