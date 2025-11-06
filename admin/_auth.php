<?php
// /admin/_auth.php
// Session + helpers for admin auth

// Harden session cookies a bit
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => isset($_SERVER['HTTPS']),
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

require_once __DIR__ . '/../api/_db.php'; // $mysqli + send_json()

function admin_login(string $username, string $password): bool {
  global $mysqli;
  $stmt = $mysqli->prepare("SELECT admin_id, password_hash FROM admin_user WHERE username = ? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  if (!$row) return false;

  if (!password_verify($password, $row['password_hash'])) return false;

  // OK
  $_SESSION['admin_id'] = (int)$row['admin_id'];
  $_SESSION['admin_username'] = $username;
  return true;
}

function admin_logged_in(): bool {
  return !empty($_SESSION['admin_id']);
}

function require_admin(): void {
  if (!admin_logged_in()) {
    header('Location: /admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php'));
    exit;
  }
}

function admin_logout(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
}
