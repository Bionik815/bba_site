<?php
require __DIR__.'/../bootstrap.php';
require __DIR__.'/../statuses.php';
require __DIR__.'/../db.php';
require __DIR__.'/../auth.php';
require_login();
verify_csrf_or_die();
$cfg  = require __DIR__.'/../config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');
$pdo = db();

$orderId = (int)($_POST['order_id'] ?? 0);
$to      = ops_normalize_status($_POST['to_status'] ?? null);

if (!$orderId || !$to) { http_response_code(400); echo "Bad request"; exit; }
if (!isset(ops_statuses()[$to])) { http_response_code(400); echo "Bad status"; exit; }

try {
  $pdo->beginTransaction();

  $cur = $pdo->prepare("SELECT status FROM orders WHERE id=? FOR UPDATE");
  $cur->execute([$orderId]);
  $row = $cur->fetch();
  if (!$row) throw new Exception("Order not found");

  $completedAtSql = ops_is_final_status($to)
    ? "completed_at = IF(completed_at IS NULL, NOW(), completed_at), "
    : "completed_at = NULL, ";

  $pdo->prepare("UPDATE orders SET status=?, {$completedAtSql}updated_at=NOW() WHERE id=?")->execute([$to, $orderId]);
  $pdo->prepare("INSERT INTO status_history(order_id, from_status, to_status) VALUES(?,?,?)")
      ->execute([$orderId, ops_normalize_status($row['status']), $to]);

  $pdo->commit();
  header("Location: {$base}/pages/orders_board.php?moved=1");
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo "Error: " . htmlspecialchars($e->getMessage());
}
