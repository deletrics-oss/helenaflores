<?php
// catalogo/update_db_vip.php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando para Sistema VIP (Produtos + Clientes)...</h1>";

try {
    // 1. Add is_vip column to products
    try {
        $pdo->query("ALTER TABLE products ADD COLUMN is_vip TINYINT(1) DEFAULT 0");
        echo "<p>✅ Coluna 'is_vip' adicionada em 'products'.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Duplicate column") !== false) {
            echo "<p>ℹ️ Coluna 'is_vip' já existia em 'products'.</p>";
        } else {
            echo "<p>❌ Erro Products: " . $e->getMessage() . "</p>";
        }
    }

    // 2. Add is_vip column to users (New)
    try {
        $pdo->query("ALTER TABLE users ADD COLUMN is_vip TINYINT(1) DEFAULT 0");
        echo "<p>✅ Coluna 'is_vip' adicionada em 'users'.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Duplicate column") !== false) {
            echo "<p>ℹ️ Coluna 'is_vip' já existia em 'users'.</p>";
        } else {
            echo "<p>❌ Erro Users: " . $e->getMessage() . "</p>";
        }
    }

    echo "<h2>Tudo Pronto! Sistema VIP Completo.</h2>";
    echo "<p><a href='admin/customer-create.php'>Cadastrar Cliente VIP</a></p>";

} catch (Exception $e) {
    die("Erro Geral: " . $e->getMessage());
}
