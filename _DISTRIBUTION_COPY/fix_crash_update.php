<?php
// fix_crash_update.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<html><body style='background:#111; color:#0f0; font-family:monospace; padding:2rem;'>";
echo "<h1>🚑 Resgate do Banco de Dados (Fix Crash)</h1><pre>";
echo "Seu site caiu porque o formulário novo tentou salvar campos (SEO, Marca, MPN) que ainda não existem no banco.\n";
echo "Vamos corrigir isso agora!\n\n";

try {
    // 1. PRODUCTS UPDATE
    echo ">>> 1. Atualizando Tabela 'products'...\n";
    $cols = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);

    $updates = [
        "ean" => "ALTER TABLE products ADD COLUMN ean VARCHAR(50)",
        "ncm" => "ALTER TABLE products ADD COLUMN ncm VARCHAR(20)",
        "video_url" => "ALTER TABLE products ADD COLUMN video_url VARCHAR(255)",
        "price_wholesale" => "ALTER TABLE products ADD COLUMN price_wholesale DECIMAL(10,2) DEFAULT 0.00",
        "min_wholesale_qty" => "ALTER TABLE products ADD COLUMN min_wholesale_qty INT DEFAULT 10",
        "slug" => "ALTER TABLE products ADD COLUMN slug VARCHAR(255) UNIQUE",
        "active" => "ALTER TABLE products ADD COLUMN active TINYINT(1) DEFAULT 1",
        "is_vip" => "ALTER TABLE products ADD COLUMN is_vip TINYINT(1) DEFAULT 0",
        "is_manufactured" => "ALTER TABLE products ADD COLUMN is_manufactured TINYINT(1) DEFAULT 0",
        "weight_kg" => "ALTER TABLE products ADD COLUMN weight_kg DECIMAL(10,3) DEFAULT 0.100",
        "length_cm" => "ALTER TABLE products ADD COLUMN length_cm INT DEFAULT 20",
        "width_cm" => "ALTER TABLE products ADD COLUMN width_cm INT DEFAULT 15",
        "height_cm" => "ALTER TABLE products ADD COLUMN height_cm INT DEFAULT 10",
        // SEO FIELDS (The Culprits)
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
            echo " + Coluna '$col' criada com sucesso.\n";
        } else {
            echo " . Coluna '$col' já existe (OK).\n";
        }
    }

    echo "\n>>> 2. Verificando Tabela de Imagens (Galeria)...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_main TINYINT(1) DEFAULT 0,
        display_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo " ✅ Tabela de Imagens OK.\n";

    echo "\n\n---------------------------------------------------";
    echo "\n   ✅ PRONTO! AGORA PODE SALVAR O PRODUTO.";
    echo "\n---------------------------------------------------";

} catch (Exception $e) {
    echo "\n\n❌ ERRO: " . $e->getMessage();
}

echo "</pre></body></html>";
?>