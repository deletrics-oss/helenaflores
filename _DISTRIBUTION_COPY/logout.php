<?php
require_once __DIR__ . '/config.php';
session_start();
// Check role before destroying
$role = $_SESSION['user_role'] ?? 'user';

// Clear persistence cookie
setcookie('b2b_access', '', time() - 3600, '/');

session_destroy();

if ($role === 'admin') {
    header("Location: " . BASE_URL . "/admin/login.php");
} else {
    header("Location: " . BASE_URL . "/index.php");
}
exit;
