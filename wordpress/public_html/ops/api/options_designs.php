<?php
require __DIR__.'/../bootstrap.php';
require __DIR__.'/../db.php';
require __DIR__.'/../auth.php';
require_login();
header('Content-Type: application/json');
$pdo = db();
$client  = (int)($_GET['client_id'] ?? 0);
$product = (int)($_GET['product_id'] ?? 0);
if (!$client || !$product) { echo json_encode([]); exit; }
$stmt = $pdo->prepare("
  SELECT d.id, d.name
  FROM client_product_designs cpd
  JOIN designs d ON d.id = cpd.design_id AND d.client_id = cpd.client_id
  WHERE cpd.client_id=? AND cpd.product_id=?
  ORDER BY d.name
");
$stmt->execute([$client, $product]);
echo json_encode($stmt->fetchAll());
