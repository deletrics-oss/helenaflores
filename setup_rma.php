<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rma_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('garantia', 'devolucao') DEFAULT 'garantia',
        customer_name VARCHAR(255) NOT NULL,
        document VARCHAR(20) NULL,
        phone VARCHAR(20) NULL,
        address VARCHAR(255) NULL,
        number VARCHAR(20) NULL,
        complement VARCHAR(100) NULL,
        neighborhood VARCHAR(100) NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(2) NULL,
        zipcode VARCHAR(10) NULL,
        product_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        issue_type VARCHAR(100) NULL,
        issue_desc TEXT NULL,
        status ENUM('pending', 'shipped', 'received', 'resolved') DEFAULT 'pending',
        me_order_id VARCHAR(255) NULL,
        tracking_code VARCHAR(100) NULL,
        qty_returned INT DEFAULT 0,
        refund_price DECIMAL(10,2) DEFAULT 0.00,
        refund_method VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL
    )");
    echo "Tabela rma_tickets criada com sucesso!";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
