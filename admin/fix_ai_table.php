<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(50) DEFAULT 'suporte',
        image_url VARCHAR(255),
        link_url VARCHAR(255),
        video_url VARCHAR(255),
        tags TEXT,
        related_products TEXT,
        ai_instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table ai_knowledge verified/created.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
