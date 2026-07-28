<?php
// update_schema_v2.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Atualizando Banco de Dados (v2)</h1><pre>";

function addColumn($pdo, $table, $col, $def)
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
            echo "✅ Coluna '$col' adicionada em '$table'.\n";
        } else {
            echo "ℹ️ Coluna '$col' já existe em '$table'.\n";
        }
    } catch (Throwable $e) {
        echo "❌ Erro coluna $col: " . $e->getMessage() . "\n";
    }
}

// 1. Novos Campos na Tabela Products
addColumn($pdo, 'products', 'ean', 'VARCHAR(20) NULL AFTER sku');
addColumn($pdo, 'products', 'ncm', 'VARCHAR(20) NULL AFTER ean');
addColumn($pdo, 'products', 'video_url', 'VARCHAR(255) NULL AFTER image_path');

// 2. Tabela de Imagens (Galeria)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo "✅ Tabela 'product_images' verificada/criada.\n";
} catch (Throwable $e) {
    echo "❌ Erro tabela product_images: " . $e->getMessage() . "\n";
}

echo "\n--- Concluído ---</pre>";
?>