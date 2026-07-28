<?php
// update_schema_reviews.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Atualizando Banco de Dados (Reviews) 🌟</h1><pre>";

try {
    // Tabela de Avaliações
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        rating INT NOT NULL DEFAULT 5,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved TINYINT(1) DEFAULT 1,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo "✅ Tabela 'product_reviews' verificada/criada.\n";

} catch (Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n--- Concluído ---</pre>";
?>