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

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function when($ts){ return $ts ? date('Y-m-d H:i', strtotime($ts)) : '—'; }
function tracking_url($tn){
  if (!$tn) return null; $tn=trim($tn); $digits=preg_replace('/\D+/','',$tn); $up=strtoupper($tn);
  if (str_starts_with($up,'TBA')) return "https://track.amazon.com/tracking/$up";
  if (str_starts_with($up,'1Z'))  return "https://www.ups.com/track?tracknum=".urlencode($up);
  if (preg_match('/^(92|93|94|95)\d{18,}$/',$digits)) return "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=".urlencode($digits);
  if (preg_match('/^\d{12}$|^\d{14}$|^\d{15}$|^\d{20,22}$/',$digits)) return "https://www.fedex.com/fedextrack/?trknbr=".urlencode($digits);
  return "https://track.aftership.com/".urlencode($up);
}

$finals = ops_final_statuses();
$in = "'" . implode("','", array_map('addslashes', $finals)) . "'";

$sql = "
  SELECT
    o.id, o.created_at, o.due_date, o.external_ref, o.tracking_number,
    o.customer_name, o.customer_email, o.customer_phone,
    o.status, c.name AS client_name,
    COALESCE(
      o.completed_at,
      (SELECT MAX(sh.changed_at) FROM status_history sh WHERE sh.order_id = o.id AND sh.to_status IN ($in)),
      o.last_edited_at, o.created_at
    ) AS completed_at,
    (SELECT oi.name FROM order_items oi WHERE oi.order_id=o.id ORDER BY oi.id ASC LIMIT 1) AS first_item_name
  FROM orders o
  LEFT JOIN clients c ON c.id=o.client_id
  WHERE o.status IN ($in)
  ORDER BY completed_at DESC, o.id DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include $root . '/partials/header.php';
?>
<style>
.card { background:#161616; border:1px solid #262626; border-radius:12px; padding:12px; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { border-bottom:1px solid #2a2a2a; padding:8px 10px; text-align:left; font-size:13px; }
.table th { color:#bbb; font-weight:600; }
.small { font-size:12px; opacity:.85; }
.actions { display:flex; gap:8px; }
a.btn { display:inline-block; padding:6px 10px; border-radius:8px; border:1px solid #2a2a2a; color:#ddd; text-decoration:none; font-size:12px; }
a.btn:hover { background:#1e1e1e; }
a.trk { color:#a6c8ff; text-decoration:underline; }
.badge { display:inline-block; padding:2px 6px; border-radius:999px; font-size:11px; background:#222; color:#aaa; }
</style>

<h2>Completed Orders</h2>

<div class="card" style="margin-top:10px;">
  <?php if (!$rows): ?>
    <div class="small">No completed orders yet.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Completed</th>
          <th>Client</th>
          <th>Customer</th>
          <th>Item</th>
          <th>Ref</th>
          <th>Tracking</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td class="small"><?= h(when($r['completed_at'])) ?></td>
            <td><?= h($r['client_name'] ?: '—') ?></td>
            <td class="small">
              <?= h($r['customer_name'] ?: '') ?>
              <?php if (!empty($r['customer_email'])): ?> · <?= h($r['customer_email']) ?><?php endif; ?>
            </td>
            <td><?= h($r['first_item_name'] ?: '') ?></td>
            <td class="small"><?= h($r['external_ref'] ?: '') ?></td>
            <td class="small">
              <?php if (!empty($r['tracking_number'])):
                $u = tracking_url($r['tracking_number']); ?>
                <?php if ($u): ?><a class="trk" href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($r['tracking_number']) ?></a><?php else: ?>
                  <?= h($r['tracking_number']) ?>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td><span class="badge"><?= h(ops_status_label($r['status'])) ?></span></td>
            <td class="actions">
              <a class="btn" href="<?= $base ?>/pages/orders_view.php?id=<?= (int)$r['id'] ?>">Details</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include $root . '/partials/footer.php'; ?>
