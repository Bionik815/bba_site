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
