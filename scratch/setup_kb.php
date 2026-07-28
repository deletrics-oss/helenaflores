<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_knowledge (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'suporte',
            image_url VARCHAR(255) DEFAULT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela ai_knowledge criada com sucesso!\n";
} catch(Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
