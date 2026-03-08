<?php
// /ops/tools/create_user.php — full file
ini_set('display_errors', 1); error_reporting(E_ALL);

require __DIR__ . '/../db.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../auth.php';

$pdo = db();

/* Robust base resolver for /ops/tools -> /ops */
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');      // /ops/tools
$autoBase  = rtrim(preg_replace('#/tools$#', '', $scriptDir), '');
$base      = rtrim(($cfg['app']['base_path'] ?? $autoBase), '/');

/* How many users exist? */
$count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$mustBeAdmin = $count > 0; // if any user exists, only admins may use this page

if ($mustBeAdmin) {
  if (!is_logged_in() || current_user_role() !== 'admin') {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

$err = $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $role  = $mustBeAdmin ? ($_POST['role'] ?? 'staff') : 'admin';

  if (!$name || !$email || strlen($pass) < 6) {
    $err = 'Provide name, email, and a password (min 6 chars).';
  } else {
    try {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $st = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,?,1)");
      $st->execute([$name,$email,$hash,$role]);

      if (!$mustBeAdmin) {
        // First admin: auto-login and land on /ops/index.php
        $_SESSION['user_id'] = (string)$pdo->lastInsertId();
        $_SESSION['name']    = $name;
        $_SESSION['role']    = 'admin';
        header("Location: {$base}/index.php");
        exit;
      }
      $ok = "User created: {$email} ({$role})";
    } catch (Throwable $e) {
      $err = 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
  }
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Create User</title>
<style>
  body{background:#0b0b0b;color:#eee;font-family:system-ui;margin:0;display:grid;place-items:center;min-height:100vh}
  .card{background:#151515;border:1px solid #2a2a2a;border-radius:12px;padding:20px;min-width:320px;width:420px;box-shadow:0 8px 30px rgba(0,0,0,.35)}
  label{display:block;font-size:12px;color:#aaa;margin:8px 0 4px}
  input,select{width:100%;padding:8px 10px;background:#111;border:1px solid #2a2a2a;border-radius:8px;color:#eee}
  button{margin-top:10px;padding:8px 12px;border:1px solid #2a2a2a;background:#1b1b1b;color:#eee;border-radius:8px;cursor:pointer}
  .msg{padding:8px 10px;border-radius:8px;margin-bottom:10px}
  .err{color:#ffd6d6;background:#3a1313;border:1px solid #5a2323}
  .ok{color:#c7f8cf;background:#0f2a12;border:1px solid #214a26}
  a{color:#a9c7ff;text-decoration:none}
  a:hover{color:#d7e6ff}
</style>
<div class="card">
  <h3 style="margin:0 0 10px 0;"><?= $mustBeAdmin ? 'Create User' : 'Create First Admin' ?></h3>
  <?php if ($err): ?><div class="msg err"><?= $err ?></div><?php endif; ?>
  <?php if ($ok):  ?><div class="msg ok"><?= $ok  ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <label>Name</label>
    <input name="name" required>
    <label>Email</label>
    <input type="email" name="email" required autocomplete="username">
    <label>Password</label>
    <input type="password" name="password" required minlength="6" autocomplete="new-password">
    <?php if ($mustBeAdmin): ?>
      <label>Role</label>
      <select name="role">
        <option value="staff">staff</option>
        <option value="viewer">viewer</option>
        <option value="admin">admin</option>
      </select>
    <?php endif; ?>
    <button type="submit"><?= $mustBeAdmin ? 'Create' : 'Create Admin' ?></button>
  </form>

  <div style="margin-top:10px;font-size:12px;opacity:.8">
    <a href="<?= $base ?>/index.php">Back</a>
  </div>
</div>
