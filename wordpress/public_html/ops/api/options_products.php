<?php
require __DIR__.'/../db.php';
header('Content-Type: application/json');
$pdo = db();
$client = (int)($_GET['client_id'] ?? 0);
if (!$client) { echo json_encode([]); exit; }
$stmt = $pdo->prepare("
  SELECT p.id, COALESCE(p.sku,'') AS sku, p.name
  FROM client_products cp
  JOIN products p ON p.id=cp.product_id AND p.active=1
  WHERE cp.client_id=? AND cp.active=1
  ORDER BY p.name
");
$stmt->execute([$client]);
echo json_encode($stmt->fetchAll());
