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
  SELECT c.id, c.name, c.hex
  FROM client_product_colors x
  JOIN colors c ON c.id=x.color_id
  WHERE x.client_id=? AND x.product_id=?
  ORDER BY c.name
");
$stmt->execute([$client,$product]);
echo json_encode($stmt->fetchAll());
