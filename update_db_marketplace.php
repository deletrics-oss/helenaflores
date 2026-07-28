<?php
/**
 * Atualização do Banco de Dados para Marketplace
 * Adiciona campos necessários para exportação Shopee/ML
 * 
 * COMO USAR: Acesse http://localhost/catalogo/update_db_marketplace.php
 */
require_once 'includes/db.php';

echo "<h1>🔧 Atualizando Banco de Dados...</h1>";
echo "<pre>";

$queries = [
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS ean VARCHAR(50) NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS ncm VARCHAR(20) NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS brand VARCHAR(100) NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS gtin VARCHAR(50) NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS mpn VARCHAR(50) NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(10,3) DEFAULT 0.100",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS length_cm INT DEFAULT 20",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS width_cm INT DEFAULT 15",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS height_cm INT DEFAULT 10",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS condition_status VARCHAR(20) DEFAULT 'new'",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS seo_title VARCHAR(255) NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS seo_description TEXT NULL"
];

$success = 0;
$skipped = 0;

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        $success++;
        echo "✅ OK\n";
    } catch (PDOException $e) {
        // Se a coluna já existe, não é erro
        if (
            strpos($e->getMessage(), 'Duplicate column') !== false ||
            strpos($e->getMessage(), 'already exists') !== false
        ) {
            $skipped++;
            echo "⏭️ Já existe, pulando...\n";
        } else {
            echo "❌ Erro: " . $e->getMessage() . "\n";
        }
    }
}

echo "</pre>";
echo "<h2>✅ Concluído!</h2>";
echo "<p>$success colunas adicionadas, $skipped já existiam.</p>";
echo "<p><a href='admin/products.php'>👉 Ir para Produtos</a></p>";
?>