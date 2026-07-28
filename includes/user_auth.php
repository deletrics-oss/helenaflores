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
    $allowed_roles = ['admin', 'manager', 'factory', 'vendedor', 'cadastro'];
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
        header("Location: " . BASE_URL . "/admin/login.php");
        exit;
    }
}

/**
 * Checks if current user has a specific permission
 * @param string $key Permission key
 * @return bool
 */
function canAccess($key)
{
    // CRITICAL: Ensure session exists and role is checked safely
    if (!isset($_SESSION['user_role'])) return false;

    // Super-admins/admin role always have full access
    if ($_SESSION['user_role'] === 'admin') return true;

    if (!isset($_SESSION['user_id'])) return false;

    // We store permissions in the session for performance
    if (!isset($_SESSION['user_permissions'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT permissions FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $perms = $stmt->fetchColumn();
        $_SESSION['user_permissions'] = json_decode($perms ?: '[]', true);
    }

    return in_array($key, $_SESSION['user_permissions']);
}

/**
 * Enforce a permission on a page
 */
function requirePermission($key)
{
    isAdmin();
    if (!canAccess($key)) {
        die("<div style='background:#1a1e2a; color:#e74c3c; padding:2rem; text-align:center; font-family:sans-serif; height:100vh; display:flex; align-items:center; justify-content:center; flex-direction:column;'>
            <h1 style='font-size:4rem; margin:0;'>🚫</h1>
            <h2>Acesso Negado</h2>
            <p style='color:#666;'>Você não tem permissão para acessar esta área ({$key}).</p>
            <a href='dashboard.php' style='color:var(--primary); text-decoration:none; margin-top:20px; border:1px solid #333; padding:10px 20px; border-radius:5px;'>Voltar ao Início</a>
        </div>");
    }
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
?>