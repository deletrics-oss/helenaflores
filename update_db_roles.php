<?php
// catalogo/update_db_roles.php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando Permissões (Roles)...</h1>";

try {
    // Modify 'role' column to include 'factory'
    // MySQL ENUMs are tricky to update, usually we just redefine the column
    $sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'customer', 'factory') NOT NULL DEFAULT 'customer'";
    $pdo->exec($sql);

    echo "<p>✅ Coluna 'role' atualizada para aceitar 'factory'.</p>";
    echo "<h2>Permissões de Fábrica Ativas!</h2>";

} catch (Exception $e) {
    die("Erro ao atualizar roles: " . $e->getMessage());
}
