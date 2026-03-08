<?php
require __DIR__.'/db.php';
include __DIR__.'/partials/header.php';
$pdo = db();
$rows = $pdo->query("SELECT status, COUNT(*) c FROM orders GROUP BY status ORDER BY status")->fetchAll();
?>
<h2>Work in Progress</h2>
<div class="board">
  <?php foreach ($rows as $r): ?>
    <div class="card">
      <div class="badge"><?= htmlspecialchars($r['status']) ?></div>
      <h3 style="margin-top:8px;"><?= (int)$r['c'] ?> orders</h3>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__.'/partials/footer.php'; ?>
