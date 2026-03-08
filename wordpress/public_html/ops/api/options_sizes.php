<?php
require __DIR__.'/../bootstrap.php';
require __DIR__.'/../db.php';
require __DIR__.'/../auth.php';
require_login();
header('Content-Type: application/json');
$pdo = db();
$client = (int)($_GET['client_id'] ?? 0);
$product = (int)($_GET['product_id'] ?? 0);
if (!$client || !$product) { echo json_encode([]); exit; }
$stmt = $pdo->prepare("
  SELECT s.id, s.code, s.label
  FROM client_product_sizes x
  JOIN sizes s ON s.id=x.size_id
  WHERE x.client_id=? AND x.product_id=?
  ORDER BY s.sort_order, s.label
");
$stmt->execute([$client,$product]);
echo json_encode($stmt->fetchAll());
