<?php
require __DIR__ . '/../bootstrap.php';

$root = realpath(__DIR__ . '/..');
if (!$root) {
    die('Path error');
}

$cfg = require $root . '/config.php';
require $root . '/auth.php';
require_login();

if (!defined('BWQT_OPS_CONTEXT')) {
    define('BWQT_OPS_CONTEXT', true);
}

$wpLoad = realpath($root . '/../wp-load.php');
if (!$wpLoad) {
    http_response_code(500);
    die('Unable to load WordPress bootstrap.');
}

require_once $wpLoad;

$base = rtrim(($cfg['app']['base_path'] ?? '/ops'), '/');
$noticeHtml = '';
$error = '';

if (!class_exists('BW_Project_Quote_Tool') || !method_exists('BW_Project_Quote_Tool', 'get_instance')) {
    $error = 'Quote tool plugin is not available. Activate BW Project Quote Tool in WordPress first.';
} else {
    $quoteTool = BW_Project_Quote_Tool::get_instance();
    if (!$quoteTool) {
        $error = 'Quote tool plugin did not initialize correctly.';
    }
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $saved = $quoteTool->save_quote_from_request($_POST);

    if (is_wp_error($saved)) {
        $error = $saved->get_error_message();
    } else {
        header('Location: ' . $base . '/pages/quotes_new.php?saved=' . (int) $saved);
        exit;
    }
}

if (!$error && isset($_GET['saved'])) {
    $savedId = (int) $_GET['saved'];
    $editLink = get_edit_post_link($savedId, '');
    $noticeHtml = '<div class="bwqt-notice"><strong>Quote saved for review.</strong>';
    if ($editLink) {
        $noticeHtml .= ' <a href="' . htmlspecialchars($editLink, ENT_QUOTES, 'UTF-8') . '">Open in admin</a>';
    }
    $noticeHtml .= '</div>';
}

include $root . '/partials/header.php';
?>
<?php if ($error): ?>
  <div style="background:#311010;color:#ffd6d6;border:1px solid #5a2323;border-radius:10px;padding:10px 12px;margin-bottom:14px;">
    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php
if (!$error) {
    echo $quoteTool->render_ops_page($base . '/pages/quotes_new.php', csrf_input(), $noticeHtml);
}
?>

<?php include $root . '/partials/footer.php'; ?>
