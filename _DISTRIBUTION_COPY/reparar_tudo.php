<?php
/**
 * REPARAR_TUDO.PHP
 * Script unificado de reparo e atualização do banco de dados (MySQL)
 * Fight Arcade - Sistema de Catálogo
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<html><body style='background:#0f131a; color:#ecf0f1; font-family:monospace; padding:2rem;'>";
echo "<h1 style='color:#f1c40f;'>🛠️ Reparador Geral do Banco de Dados</h1>";
echo "<pre style='background:#1a1e26; padding:1.5rem; border-radius:10px; border:1px solid #333;'>";

try {
    // 1. PRODUCTS TABLE UPDATES
    echo "\n>>> [1/5] Verificando Tabela 'products'...\n";
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
        "seo_title" => "ALTER TABLE products ADD COLUMN seo_title VARCHAR(70) NULL",
        "seo_description" => "ALTER TABLE products ADD COLUMN seo_description VARCHAR(160) NULL",
        "brand" => "ALTER TABLE products ADD COLUMN brand VARCHAR(100) NULL",
        "gtin" => "ALTER TABLE products ADD COLUMN gtin VARCHAR(14) NULL",
        "mpn" => "ALTER TABLE products ADD COLUMN mpn VARCHAR(100) NULL"
    ];

    foreach ($updates as $col => $sql) {
        if (!in_array($col, $cols)) {
            $pdo->exec($sql);
            echo " + Coluna '$col' adicionada.\n";
        } else {
            echo " . Coluna '$col' ok.\n";
        }
    }

    // 2. USERS TABLE UPDATES (B2B/Address)
    echo "\n>>> [2/5] Verificando Tabela 'users' (Endereço/Documentos)...\n";
    $userCols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    $userUpdates = [
        "company_name" => "ALTER TABLE users ADD COLUMN company_name VARCHAR(255) NULL AFTER name",
        "document" => "ALTER TABLE users ADD COLUMN document VARCHAR(20) NULL",
        "zip_code" => "ALTER TABLE users ADD COLUMN zip_code VARCHAR(10) NULL",
        "street" => "ALTER TABLE users ADD COLUMN street VARCHAR(255) NULL",
        "number" => "ALTER TABLE users ADD COLUMN number VARCHAR(20) NULL",
        "district" => "ALTER TABLE users ADD COLUMN district VARCHAR(100) NULL",
        "complement" => "ALTER TABLE users ADD COLUMN complement VARCHAR(100) NULL",
        "city" => "ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL",
        "state" => "ALTER TABLE users ADD COLUMN state VARCHAR(2) NULL",
        "source" => "ALTER TABLE users ADD COLUMN source VARCHAR(100) DEFAULT 'Site'",
        "is_lead" => "ALTER TABLE users ADD COLUMN is_lead TINYINT(1) DEFAULT 1",
        "role" => "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'customer'"
    ];

    foreach ($userUpdates as $col => $sql) {
        if (!in_array($col, $userCols)) {
            $pdo->exec($sql);
            echo " + Coluna '$col' adicionada em users.\n";
        } else {
            echo " . Coluna '$col' ok.\n";
        }
    }

    // 3. PRODUCT VARIATIONS TABLE
    echo "\n>>> [3/5] Verificando Variações...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_variations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        type VARCHAR(50) NOT NULL COMMENT 'Cor, Tamanho, Modelo',
        value VARCHAR(100) NOT NULL,
        sku VARCHAR(100),
        price DECIMAL(10,2) DEFAULT 0.00,
        stock_qty INT DEFAULT 0,
        image_path VARCHAR(255),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo " ✅ product_variations ok.\n";

    // 4. POS TABLES
    echo "\n>>> [4/5] Verificando Tabelas do PDV...\n";
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
    echo " ✅ Tabelas PDV prontas.\n";

    // 5. NEWSLETTER & REVIEWS
    echo "\n>>> [5/5] Verificando Newsletter & Reviews...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

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
    echo " ✅ Newsletter e Reviews ok.\n";

    echo "\n\n---------------------------------------------------";
    echo "\n   🎉 TUDO PRONTO! SEU BANCO ESTÁ 100% REPARADO.";
    echo "\n---------------------------------------------------";

} catch (Exception $e) {
    echo "\n\n❌ ERRO FATAL: " . $e->getMessage();
}

echo "</pre>";
echo "<p style='text-align:center;'><a href='index.php' style='background:#f1c40f; color:#000; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>VOLTAR PARA A LOJA</a></p>";
echo "</body></html>";
