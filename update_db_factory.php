<?php
// catalogo/update_db_factory.php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando para Módulo Fábrica...</h1>";

try {
    // Tabela de Ordens de Produção
    $sql = "CREATE TABLE IF NOT EXISTS production_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        status ENUM('pending', 'in_production', 'completed', 'canceled') DEFAULT 'pending',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "<p>✅ Tabela 'production_orders' criada.</p>";

    echo "<h2>🏭 Módulo Fábrica Pronto!</h2>";

} catch (Exception $e) {
    die("Erro Geral: " . $e->getMessage());
}
