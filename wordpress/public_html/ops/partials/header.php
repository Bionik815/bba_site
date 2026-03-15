<?php
// /public_html/ops/partials/header.php  (full file)
if (!isset($cfg)) {
  $root = realpath(__DIR__ . '/..');
  $cfg  = require $root . '/config.php';
}
require_once __DIR__ . '/../auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* robust base resolver */
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');         // /ops or /ops/pages
$autoBase  = rtrim(preg_replace('#/pages$#', '', $scriptDir), ''); // -> /ops
$base      = rtrim(($cfg['app']['base_path'] ?? $autoBase), '/');

$current = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? '';

/* Correct web path for logo (no /public_html in URLs) */
$resolvedLogo = '/wp-content/uploads/2025/09/BArebones_White.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Barebones Ops</title>
<style>
body { background:#0b0b0b; color:#ddd; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; margin:0; padding:0; }
header { background:#111; border-bottom:1px solid #222; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
.brand { display:flex; align-items:center; gap:10px; min-height:32px; text-decoration:none; }
.brand img { height:28px; width:auto; display:block; }
.brand .fallback { font-size:18px; margin:0; letter-spacing:.3px; font-weight:600; }
nav { display:flex; gap:14px; flex-wrap:wrap; align-items:center; }
nav a { color:#ccc; text-decoration:none; padding:6px 10px; border-radius:6px; transition:background .2s, color .2s; }
nav a:hover { background:#1a1a1a; color:#fff; }
nav a.active { background:#246; color:#fff; font-weight:600; }
.user-info { display:flex; align-items:center; gap:8px; font-size:13px; opacity:.85; }
.user-info a { color:#aaa; text-decoration:none; }
.user-info a:hover { color:#fff; }
main { padding:16px; }
</style>
</head>
<body>
<header>
  <a href="<?= $base ?>/index.php" class="brand">
    <?php if ($resolvedLogo): ?>
      <img src="<?= htmlspecialchars($resolvedLogo, ENT_QUOTES, 'UTF-8') ?>"
           alt="Barebones Apparel"
           onerror="this.style.display='none'; this.previousElementSibling?.classList?.remove('hidden');">
      <span class="fallback hidden" style="display:none;">Barebones Ops</span>
    <?php else: ?>
      <span class="fallback">Barebones Ops</span>
    <?php endif; ?>
  </a>

  <nav>
    <a href="<?= $base ?>/index.php"                 class="<?= $current==='index.php'?'active':'' ?>">Dashboard</a>
    <a href="<?= $base ?>/pages/orders_board.php"    class="<?= $current==='orders_board.php'?'active':'' ?>">Board</a>
    <a href="<?= $base ?>/pages/orders_new.php"      class="<?= $current==='orders_new.php'?'active':'' ?>">New Order</a>
    <a href="<?= $base ?>/pages/quotes_new.php"      class="<?= $current==='quotes_new.php'?'active':'' ?>">Quotes</a>
    <?php if ($role === 'admin'): ?>
      <a href="<?= $base ?>/pages/quotes_review.php"   class="<?= in_array($current,['quotes_review.php','quotes_view.php'], true)?'active':'' ?>">Quote Review</a>
    <?php endif; ?>
    <a href="<?= $base ?>/pages/orders_search.php"   class="<?= $current==='orders_search.php'?'active':'' ?>">Search</a>
    <a href="<?= $base ?>/pages/orders_complete.php" class="<?= $current==='orders_complete.php'?'active':'' ?>">Completed</a>
    <?php if ($role === 'admin'): ?>
      <a href="<?= $base ?>/pages/admin_catalog.php" class="<?= $current==='admin_catalog.php'?'active':'' ?>">Admin</a>
    <?php endif; ?>
  </nav>

  <div class="user-info">
    <?php if (!empty($_SESSION['user_id'])): ?>
      <span><?= htmlspecialchars($name) ?><?= $role ? ' · '.htmlspecialchars($role) : '' ?></span>
      <a href="<?= $base ?>/logout.php">Logout</a>
    <?php else: ?>
      <a href="<?= $base ?>/login.php">Login</a>
    <?php endif; ?>
  </div>
</header>
<main>
