<?php
// auth.php – session + helpers for login/roles
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function is_logged_in(): bool {
  return !empty($_SESSION['user_id']);
}

function current_user_id() {
  return $_SESSION['user_id'] ?? null;
}

function current_user_role(): string {
  return $_SESSION['role'] ?? '';
}

function require_login(string $role = null): void {
  if (!is_logged_in()) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '/ops/index.php');
    header("Location: /ops/login.php?next={$next}");
    exit;
  }
  if ($role && current_user_role() !== 'admin' && current_user_role() !== $role) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

function csrf_token(): string {
  if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['_csrf_token'];
}

function csrf_input(): string {
  $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function verify_csrf_or_die(?string $token = null): void {
  $provided = $token ?? ($_POST['_csrf'] ?? '');
  $session = $_SESSION['_csrf_token'] ?? '';
  if (!is_string($provided) || !is_string($session) || $provided === '' || !hash_equals($session, $provided)) {
    http_response_code(403);
    echo "Invalid CSRF token";
    exit;
  }
}
