<?php
// catalogo/update_db_ledger.php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando para Sistema de Fiado (Ledger)...</h1>";

try {
    // Create customer_payments table
    $sql = "CREATE TABLE IF NOT EXISTS customer_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50),
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "<p>✅ Tabela 'customer_payments' criada.</p>";

    echo "<h2>Sistema Financeiro Atualizado!</h2>";

} catch (Exception $e) {
    die("Erro Geral: " . $e->getMessage());
}
