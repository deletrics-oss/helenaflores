<?php
// catalogo/includes/user_auth.php
session_start();

function checkUser()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function isAdmin()
{
    $allowed_roles = ['admin', 'manager', 'factory'];
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
        header("Location: " . BASE_URL . "/admin/login.php");
        exit;
    }
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
?>