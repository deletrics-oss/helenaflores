<?php
// update_schema_seo.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Atualizando Banco de Dados (SEO Google) 🔍</h1><pre>";

try {
    $cols = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);

    $updates = [
        "seo_title" => "ALTER TABLE products ADD COLUMN seo_title VARCHAR(70) NULL COMMENT 'Título Otimizado'",
        "seo_description" => "ALTER TABLE products ADD COLUMN seo_description VARCHAR(160) NULL COMMENT 'Meta Descrição'",
        "brand" => "ALTER TABLE products ADD COLUMN brand VARCHAR(100) NULL COMMENT 'Marca'",
        "gtin" => "ALTER TABLE products ADD COLUMN gtin VARCHAR(14) NULL COMMENT 'EAN/UPC'",
        "mpn" => "ALTER TABLE products ADD COLUMN mpn VARCHAR(100) NULL COMMENT 'Part Number'",
        "condition_status" => "ALTER TABLE products ADD COLUMN condition_status ENUM('new', 'used', 'refurbished') DEFAULT 'new'"
    ];

    foreach ($updates as $col => $sql) {
        if (!in_array($col, $cols)) {
            $pdo->exec($sql);
            echo " + Coluna '$col' adicionada.\n";
        } else {
            echo " . Coluna '$col' já existe.\n";
        }
    }

    echo "\n✅ Estrutura de SEO Pronta!";

} catch (Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n--- Concluído ---</pre>";
?>