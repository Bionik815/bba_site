<?php
require __DIR__ . '/../auth.php';
require_login();

ini_set('display_errors',1); error_reporting(E_ALL); // remove later

$root = realpath(__DIR__ . '/..'); if (!$root) die('Path error');
$cfg  = require $root . '/config.php';
$base = rtrim($cfg['app']['base_path'] ?? '', '/');

require $root . '/db.php';
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function when($ts){ return $ts ? date('Y-m-d H:i', strtotime($ts)) : '—'; }

/* Digits-only expression for phone matching (nested REPLACE because shared hosting joy) */
$digits_sql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(o.customer_phone,'-',''),'(',''),')',''),' ',''),'+',''),'_',''),'.',''),'/', ''),'\\\', ''),',','')";

/* Turn a tracking number into a carrier URL */
function tracking_url($tn){
  if (!$tn) return null;
  $tn = trim($tn);
  $digits = preg_replace('/\D+/', '', $tn);
  $up = strtoupper($tn);
  if (strpos($up, 'TBA') === 0) return "https://track.amazon.com/tracking/$up";
  if (strpos($up, '1Z')  === 0) return "https://www.ups.com/track?tracknum=".urlencode($up);
  if (preg_match('/^(92|93|94|95)\d{18,}$/', $digits)) return "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=".urlencode($digits);
  if (preg_match('/^\d{12}$|^\d{14}$|^\d{15}$|^\d{20,22}$/', $digits)) return "https://www.fedex.com/fedextrack/?trknbr=".urlencode($digits);
  return "https://track.aftership.com/".urlencode($up);
}

$q        = trim($_GET['q'] ?? '');
$limit    = max(1, min(200, (int)($_GET['limit'] ?? 100)));
$results  = [];
$hadQuery = ($q !== '');

if ($hadQuery) {
  $like       = "%$q%";
  $q_digits   = preg_replace('/\D+/', '', $q);
  $hasId      = ($q_digits !== '' && strlen($q_digits) <= 10);
  $hasDigits  = ($q_digits !== '');

  // WHERE parts + params (bind only what we use)
  $where = [];
  $params = [];

  if ($hasId) {
    $where[] = "o.id = :id";
    $params[':id'] = (int)$q_digits;
  }

  $where[] = "o.external_ref LIKE :likeRef";
  $params[':likeRef'] = $like;

  $where[] = "o.customer_email LIKE :likeEmail";
  $params[':likeEmail'] = $like;

  $where[] = "o.customer_name LIKE :likeName";
  $params[':likeName'] = $like;

  $where[] = "c.name LIKE :likeClient";
  $params[':likeClient'] = $like;

  if ($hasDigits) {
    $where[] = "($digits_sql) LIKE :digits";
    $params[':digits'] = "%$q_digits%";
  }

  // Sort: exact id hit first when present, then fuzzy matches, then recent
  $orderParts = [];
  if ($hasId) $orderParts[] = "(o.id = :id) DESC";
  $orderParts[] = "(o.customer_email LIKE :likeEmail) DESC";
  $orderParts[] = "(o.customer_name  LIKE :likeName)  DESC";
  $orderParts[] = "(o.external_ref   LIKE :likeRef)   DESC";
  $orderParts[] = "(c.name           LIKE :likeClient) DESC";
  $orderParts[] = "o.created_at DESC";
  $orderBy = implode(", ", $orderParts);

  $sql = "
    SELECT
      o.id,
      o.created_at,
      o.customer_name,
      o.customer_email,
      o.customer_phone,
      o.tracking_number,
      c.name AS client_name
    FROM orders o
    LEFT JOIN clients c ON c.id = o.client_id
    WHERE " . implode(' OR ', $where) . "
    ORDER BY $orderBy
    LIMIT $limit
  ";

  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($k, $v, $type);
  }
  $stmt->execute();
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include $root . '/partials/header.php';
?>
<style>
.card { background:#161616; border:1px solid #262626; border-radius:12px; padding:12px; }
.formRow { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
input[type="text"]{ padding:8px 10px; border:1px solid #2a2a2a; border-radius:8px; background:#111; color:#eee; min-width:260px; }
button, .btn { padding:8px 12px; border-radius:8px; border:1px solid #2a2a2a; background:#1b1b1b; color:#eee; cursor:pointer; text-decoration:none; }
button:hover, .btn:hover { background:#232323; }
.table { width:100%; border-collapse:collapse; margin-top:12px; }
.table th, .table td { border-bottom:1px solid #2a2a2a; padding:8px 10px; text-align:left; font-size:13px; }
.table th { color:#bbb; font-weight:600; }
.small { font-size:12px; opacity:.85; }
.actions { display:flex; gap:8px; }
a.trk { color:#a6c8ff; text-decoration:underline; }
</style>

<h2>Search Orders</h2>

<div class="card">
  <form method="get" action="">
    <div class="formRow">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by Order #, customer name/email/phone, external ref, or client name">
      <button type="submit">Search</button>
      <a class="btn" href="<?= $base ?>/pages/orders_search.php">Clear</a>
    </div>
    <div class="formRow" style="margin-top:6px;">
      <span class="small">Tip: Phone search ignores punctuation (e.g., 6615550123). Results limited to <?= (int)$limit ?>.</span>
    </div>
  </form>

  <?php if ($hadQuery): ?>
    <div style="margin-top:12px;" class="small">Showing <?= count($results) ?> result(s)</div>
    <?php if ($results): ?>
      <table class="table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Created</th>
            <th>Customer</th>
            <th>Client</th>
            <th>Contact</th>
            <th>Tracking</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td class="small"><?= h(when($r['created_at'])) ?></td>
            <td><?= h($r['customer_name'] ?: '—') ?></td>
            <td><?= h($r['client_name'] ?: '—') ?></td>
            <td class="small">
              <?php if (!empty($r['customer_email'])): ?>
                <?= h($r['customer_email']) ?>
              <?php endif; ?>
              <?php if (!empty($r['customer_phone'])): ?>
                <?= !empty($r['customer_email']) ? ' · ' : '' ?><?= h($r['customer_phone']) ?>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($r['tracking_number'])):
                $u = tracking_url($r['tracking_number']); ?>
                <?php if ($u): ?><a class="trk" href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($r['tracking_number']) ?></a><?php else: ?>
                  <?= h($r['tracking_number']) ?>
                <?php endif; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td class="actions">
              <a class="btn" href="<?= $base ?>/pages/orders_view.php?id=<?= (int)$r['id'] ?>">Details</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div style="margin-top:10px;">No results.</div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include $root . '/partials/footer.php'; ?>
