<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        name VARCHAR(100) NOT NULL, 
        type ENUM('pix', 'bank') DEFAULT 'pix', 
        pix_key VARCHAR(255), 
        bank_info TEXT, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table payment_accounts created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
