<?php
require __DIR__ . '/../auth.php';
require_login();

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../statuses.php';

$root = realpath(__DIR__ . '/..'); if (!$root) die('Path error');
$cfg  = require $root . '/config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');

require $root . '/db.php';
$pdo = db();

$columns = ops_board_statuses();

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function age_in_words($from) {
  if (!$from) return '—';
  $t = strtotime($from); if (!$t) return '—';
  $d = time() - $t;
  if ($d < 60) return $d.'s';
  if ($d < 3600) return floor($d/60).'m';
  if ($d < 86400) return floor($d/3600).'h';
  return floor($d/86400).'d';
}
/* Turn a tracking number into a carrier URL */
function tracking_url($tn){
  if (!$tn) return null;
  $tn = trim($tn);
  $digits = preg_replace('/\D+/', '', $tn);
  $upper  = strtoupper($tn);

  // Amazon Logistics (TBA…)
  if (str_starts_with($upper, 'TBA')) {
    return "https://track.amazon.com/tracking/{$upper}";
  }
  // UPS: starts with 1Z
  if (str_starts_with($upper, '1Z')) {
    return "https://www.ups.com/track?tracknum=" . urlencode($upper);
  }
  // USPS: common 92/93/94/95 prefixes (22 digits typically)
  if (preg_match('/^(92|93|94|95)\d{18,}$/', $digits)) {
    return "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=" . urlencode($digits);
  }
  // FedEx: 12, 14, 15 or 20–22 digits are common
  if (preg_match('/^\d{12}$|^\d{14}$|^\d{15}$|^\d{20,22}$/', $digits)) {
    return "https://www.fedex.com/fedextrack/?trknbr=" . urlencode($digits);
  }
  // Fallback to AfterShip universal
  return "https://track.aftership.com/" . urlencode($upper);
}

/* Pull orders (exclude 'complete' from main board) */
$sql = "
SELECT
  o.id, o.client_id, o.status, o.priority, o.external_ref, o.tracking_number,
  o.due_date, o.created_at,
  o.customer_name, o.customer_email, o.customer_phone,
  o.edited_count, o.last_edited_at,
  c.name AS client_name,
  (
    SELECT oi.name
    FROM order_items oi
    WHERE oi.order_id = o.id
    ORDER BY oi.id ASC
    LIMIT 1
  ) AS first_item_name
FROM orders o
LEFT JOIN clients c ON c.id = o.client_id
WHERE o.status NOT IN ('delivered', 'complete', 'completed')
ORDER BY o.priority DESC, o.created_at DESC;
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* Group for columns */
$byStatus = [];
foreach ($columns as $key => $_) $byStatus[$key] = [];
foreach ($rows as $r) {
  $st = ops_normalize_status($r['status'] ?? 'received');
  if (!isset($byStatus[$st])) $byStatus[$st] = [];
  $byStatus[$st][] = $r;
}

include $root . '/partials/header.php';
?>
<style>
.board { display:grid; grid-template-columns: repeat(4, minmax(260px,1fr)); gap:14px; }
@media (max-width:1200px){ .board { grid-template-columns: repeat(2, minmax(260px,1fr)); } }
@media (max-width:700px){ .board { grid-template-columns: 1fr; } }

.col { background:#111; border:1px solid #222; border-radius:14px; overflow:hidden; display:flex; flex-direction:column; }
.colHeader { padding:10px 12px; display:flex; align-items:center; gap:10px; cursor:pointer; background:#0e0e0e; border-bottom:1px solid #1e1e1e; }
.colHeader h3 { margin:0; font-size:14px; font-weight:600; letter-spacing:.3px; }
.count { font-size:12px; opacity:.7; }
.chev { margin-left:auto; opacity:.7; transform:rotate(0deg); transition:transform .2s; }
.collapsed .chev { transform:rotate(-90deg); }
.colBody { padding:10px; overflow:auto; max-height: 65vh; }
.card { background:#161616; border:1px solid #262626; border-radius:10px; padding:10px; margin-bottom:10px; }
.card .title { font-weight:600; margin-bottom:4px; display:flex; align-items:center; gap:8px; }
.badge { display:inline-block; padding:1px 6px; border-radius:999px; font-size:11px; background:#222; color:#aaa; }
.pill-edited { background:#ffe9a8; color:#6b4e00; }
.meta { font-size:12px; color:#aaa; display:flex; gap:10px; flex-wrap:wrap; }
.kv { font-size:12px; color:#bbb; }
.kv b { color:#ddd; }
.actions { margin-top:8px; display:flex; gap:8px; }
a.btn { display:inline-block; padding:5px 8px; border-radius:8px; border:1px solid #2a2a2a; color:#ddd; text-decoration:none; font-size:12px; }
a.btn:hover { background:#1e1e1e; }
a.link { color:#ddd; text-decoration:underline; }
a.trk { color:#a6c8ff; text-decoration:underline; }
</style>

<h2>Orders Board</h2>

<div class="board" id="board">
  <?php foreach ($columns as $key => $label):
    $list = $byStatus[$key] ?? [];
    $count = count($list);
  ?>
  <div class="col" data-col="<?= h($key) ?>">
    <div class="colHeader" onclick="toggleCol(this.parentElement)">
      <h3><?= h($label) ?></h3>
      <span class="count">(<?= $count ?>)</span>
      <span class="chev">▾</span>
    </div>
    <div class="colBody">
      <?php if (!$count): ?>
        <div class="card" style="opacity:.7;">No orders</div>
      <?php else: ?>
        <?php foreach ($list as $o): ?>
          <div class="card">
            <div class="title">
              <a class="link" href="<?= $base ?>/pages/orders_view.php?id=<?= (int)$o['id'] ?>">
                <span class="badge">#<?= (int)$o['id'] ?></span>
              </a>
              <?php if (!empty($o['edited_count'])): ?>
                <span class="badge pill-edited" title="Last edited <?= h($o['last_edited_at']) ?>">
                  Edited × <?= (int)$o['edited_count'] ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($o['priority']) && (int)$o['priority'] !== 3): ?>
                <span class="badge" title="Priority"><?= (int)$o['priority'] ?></span>
              <?php endif; ?>
            </div>

            <?php if (!empty($o['first_item_name'])): ?>
              <div class="kv"><b>Item:</b> <?= h($o['first_item_name']) ?></div>
            <?php endif; ?>

            <div class="kv"><b>Client:</b> <?= h($o['client_name'] ?? '') ?></div>
            <?php if (!empty($o['external_ref'])): ?>
              <div class="kv"><b>Ref:</b> <?= h($o['external_ref']) ?></div>
            <?php endif; ?>

            <?php if (($o['status'] ?? '') === 'shipped' && !empty($o['tracking_number'])):
              $url = tracking_url($o['tracking_number']); ?>
              <div class="kv"><b>Tracking:</b>
                <?php if ($url): ?>
                  <a class="trk" href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h($o['tracking_number']) ?></a>
                <?php else: ?>
                  <?= h($o['tracking_number']) ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($o['customer_name']) || !empty($o['customer_email']) || !empty($o['customer_phone'])): ?>
              <div class="kv">
                <b>Customer:</b>
                <?= h($o['customer_name'] ?? '') ?>
                <?php if (!empty($o['customer_email'])): ?> · <?= h($o['customer_email']) ?><?php endif; ?>
                <?php if (!empty($o['customer_phone'])): ?> · <?= h($o['customer_phone']) ?><?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="meta">
              <span title="Created at <?= h($o['created_at']) ?>">Age: <?= h(age_in_words($o['created_at'])) ?></span>
              <?php if (!empty($o['due_date'])): ?>
                <span title="Due date"><?= 'Due: '.h($o['due_date']) ?></span>
              <?php endif; ?>
            </div>

            <div class="actions">
              <a class="btn" href="<?= $base ?>/pages/orders_edit.php?id=<?= (int)$o['id'] ?>">Edit</a>
              <a class="btn" href="<?= $base ?>/pages/orders_view.php?id=<?= (int)$o['id'] ?>">Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
function toggleCol(col){
  col.classList.toggle('collapsed');
  const body = col.querySelector('.colBody');
  body.style.display = col.classList.contains('collapsed') ? 'none' : 'block';
}
</script>

<?php include $root . '/partials/footer.php'; ?>
