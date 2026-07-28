<?php
// update_db_full.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<html><body style='background:#111; color:#0f0; font-family:monospace; padding:2rem;'>";
echo "<h1>🛠️ Atualização Geral do Banco de Dados</h1><pre>";

try {
    // 1. PRODUCTS UPDATE
    echo "\n>>> 1. Verificando Tabela 'products'...\n";
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
        "height_cm" => "ALTER TABLE products ADD COLUMN height_cm INT DEFAULT 10"
    ];

    foreach ($updates as $col => $sql) {
        if (!in_array($col, $cols)) {
            $pdo->exec($sql);
            echo " + Coluna '$col' adicionada.\n";
        } else {
            echo " . Coluna '$col' já existe.\n";
        }
    }

    // 2. REVIEWS TABLE
    echo "\n>>> 2. Verificando Tabela 'product_reviews'...\n";
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
    echo " ✅ Ckecked/Created.\n";

    // 3. NEWSLETTER TABLE
    echo "\n>>> 3. Verificando Tabela 'newsletter_subscribers'...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo " ✅ Ckecked/Created.\n";

    // 4. PRODUCT IMAGES TABLE (Gallery)
    echo "\n>>> 4. Verificando Tabela 'product_images'...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_main TINYINT(1) DEFAULT 0,
        display_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo " ✅ Checked/Created.\n";

    // 5. SEO COLUMNS UPDATE
    echo "\n>>> 5. Adicionando Colunas SEO (Google)...\n";
    $seo_updates = [
        "seo_title" => "ALTER TABLE products ADD COLUMN seo_title VARCHAR(70) NULL COMMENT 'Título Otimizado'",
        "seo_description" => "ALTER TABLE products ADD COLUMN seo_description VARCHAR(160) NULL COMMENT 'Meta Descrição'",
        "brand" => "ALTER TABLE products ADD COLUMN brand VARCHAR(100) NULL COMMENT 'Marca'",
        "gtin" => "ALTER TABLE products ADD COLUMN gtin VARCHAR(14) NULL COMMENT 'EAN/UPC'",
        "mpn" => "ALTER TABLE products ADD COLUMN mpn VARCHAR(100) NULL COMMENT 'Part Number'",
        "condition_status" => "ALTER TABLE products ADD COLUMN condition_status ENUM('new', 'used', 'refurbished') DEFAULT 'new'"
    ];
    foreach ($seo_updates as $col => $sql) {
        if (!in_array($col, $cols)) {
            $pdo->exec($sql);
            echo " + Coluna SEO '$col' adicionada.\n";
        } else {
            echo " . Coluna SEO '$col' já existe.\n";
        }
    }

    // 6. BANNERS TABLE
    echo "\n>>> 6. Verificando Tabela 'banners'...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        title VARCHAR(100),
        subtitle VARCHAR(255),
        link_url VARCHAR(255),
        display_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo " ✅ Checked/Created.\n";

    // Insert Default Banners if empty
    $count = $pdo->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if ($count == 0) {
        // Insert simulated defaults if table was just created
        $pdo->exec("INSERT INTO banners (image_path, title, subtitle, link_url, active) VALUES 
        ('assets/banners/banner1.png', 'Controles Arcade Profissionais', 'Precisão de campeonato. Acabamento premium.', '?cat=1', 1)");
        echo " + Banners padrão inseridos.\n";
    }

    // 7. POS TABLES
    echo "\n>>> 7. Verificando Tabelas PDV (Frente de Caixa)...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS pos_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        total DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50),
        status VARCHAR(20) DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pos_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        variation_id INT NULL,
        qty INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE
    )");
    echo " ✅ Checked/Created.\n";

    echo "\n\n---------------------------------------------------";
    echo "\n   ✅ TUDO PRONTO! O SISTEMA ESTÁ 100% ATUALIZADO.";
    echo "\n---------------------------------------------------";

} catch (Exception $e) {
    echo "\n\n❌ ERRO FATAL: " . $e->getMessage();
}

echo "</pre></body></html>";
?>