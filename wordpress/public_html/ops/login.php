<?php
// /ops/login.php — full file
ini_set('display_errors', 1); error_reporting(E_ALL);

require __DIR__ . '/db.php';
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$pdo = db();

/* Robust base resolver: prefer config, fall back to auto */
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');      // e.g. /ops
$autoBase  = rtrim(preg_replace('#/pages$#', '', $scriptDir), '');
$base      = rtrim(($cfg['app']['base_path'] ?? $autoBase), '/');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');

  $st = $pdo->prepare("SELECT id, name, email, password_hash, role, active FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if ($u && (int)$u['active'] === 1 && password_verify($pass, $u['password_hash'])) {
    $_SESSION['user_id'] = (string)$u['id'];
    $_SESSION['name']    = $u['name'];
    $_SESSION['role']    = $u['role'];

    // Honor a safe ?next= inside /ops, else go to /ops/index.php
    $next = $_GET['next'] ?? '';
    if ($next && str_starts_with($next, $base . '/')) {
      header('Location: ' . $next);
    } else {
      header('Location: ' . $base . '/index.php');
    }
    exit;
  }
  $err = 'Invalid email or password';
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Sign in</title>
<style>
  body{background:#0b0b0b;color:#eee;font-family:system-ui;margin:0;display:grid;place-items:center;min-height:100vh}
  .card{background:#151515;border:1px solid #2a2a2a;border-radius:12px;padding:20px;min-width:320px;width:360px;box-shadow:0 8px 30px rgba(0,0,0,.35)}
  label{display:block;font-size:12px;color:#aaa;margin:8px 0 4px}
  input{width:100%;padding:8px 10px;background:#111;border:1px solid #2a2a2a;border-radius:8px;color:#eee}
  button{margin-top:10px;padding:8px 12px;border:1px solid #2a2a2a;background:#1b1b1b;color:#eee;border-radius:8px;cursor:pointer}
  .err{color:#ffbaba;background:#3a1313;border:1px solid #5a2323;padding:8px 10px;border-radius:8px;margin-bottom:10px}
  a{color:#a9c7ff;text-decoration:none}
  a:hover{color:#d7e6ff}
</style>
<div class="card">
  <h3 style="margin:0 0 10px 0;">Sign in</h3>
  <?php if ($err): ?><div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>Email</label>
    <input type="email" name="email" required autocomplete="username" autofocus>
    <label>Password</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button type="submit">Login</button>
  </form>
  <div style="margin-top:10px;font-size:12px;opacity:.8">
    <a href="<?= $base ?>/tools/create_user.php">Create first admin</a> (only works if no users yet)
  </div>
</div>
