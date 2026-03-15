<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth.php';
require_login('admin');

$root = realpath(__DIR__ . '/..');
if (!$root) {
    die('Path error');
}

$cfg = require $root . '/config.php';
$base = rtrim($cfg['app']['base_path'] ?? '/ops', '/');

if (!defined('BWQT_OPS_CONTEXT')) {
    define('BWQT_OPS_CONTEXT', true);
}

require_once realpath($root . '/../wp-load.php');

if (!class_exists('BW_Project_Quote_Tool') || !method_exists('BW_Project_Quote_Tool', 'get_instance')) {
    http_response_code(500);
    die('Quote tool plugin is not available.');
}

$tool = BW_Project_Quote_Tool::get_instance();
if (!$tool) {
    http_response_code(500);
    die('Quote tool plugin failed to initialize.');
}

$status = trim((string) ($_GET['status'] ?? ''));
$quotes = $tool->get_review_queue([
    'status' => $status,
    'limit' => 200,
]);

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

include $root . '/partials/header.php';
?>
<style>
.card{background:#161616;border:1px solid #262626;border-radius:12px;padding:14px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px 8px;border-bottom:1px solid #242424;text-align:left}
.table th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#a9a9a9}
.pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#0f1720;border:1px solid #2b3a4f;font-size:12px}
.filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.filters a{padding:8px 12px;border-radius:8px;border:1px solid #2a2a2a;background:#1b1b1b;color:#eee;text-decoration:none}
.filters a.active{background:#2a4563}
</style>

<h2>Quote Review</h2>

<div class="card">
  <div class="filters">
    <?php
    $statuses = ['', 'Pending Review', 'Reviewed', 'Ready to Send'];
    foreach ($statuses as $filterStatus):
      $label = $filterStatus === '' ? 'All' : $filterStatus;
      $url = $base . '/pages/quotes_review.php' . ($filterStatus !== '' ? '?status=' . urlencode($filterStatus) : '');
    ?>
      <a href="<?= h($url) ?>" class="<?= $status === $filterStatus ? 'active' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Updated</th>
        <th>Client</th>
        <th>Project</th>
        <th>Status</th>
        <th>Base Total</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($quotes): ?>
        <?php foreach ($quotes as $quote): ?>
          <tr>
            <td><?= h($quote['updated_at']) ?></td>
            <td><?= h($quote['client_name']) ?></td>
            <td><?= h($quote['project_name']) ?></td>
            <td><span class="pill"><?= h($quote['quote_status']) ?></span></td>
            <td>$<?= number_format((float) $quote['total'], 2) ?></td>
            <td><a href="<?= h($base . '/pages/quotes_view.php?post_id=' . (int) $quote['post_id']) ?>" style="color:#a9c7ff;">Open Review</a></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6"><em>No quotes found for this filter.</em></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include $root . '/partials/footer.php'; ?>
