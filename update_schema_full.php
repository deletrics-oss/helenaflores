<?php
// update_schema_full.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>🚀 Atualização Completa do Banco de Dados</h1><pre>";

function execSQL($pdo, $sql, $msg)
{
    try {
        $pdo->exec($sql);
        echo "✅ $msg\n";
    } catch (Exception $e) {
        // Ignorar erro de coluna duplicada ("Duplicate column name")
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "🔹 $msg (Já existia)\n";
        } else {
            echo "❌ Falha em $msg: " . $e->getMessage() . "\n";
        }
    }
}

// 1. Tabela BANNERS
execSQL($pdo, "CREATE TABLE IF NOT EXISTS banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(100),
    subtitle VARCHAR(255),
    link_url VARCHAR(255),
    display_order INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Tabela 'banners'");

// 2. Tabela PRODUTOS (Colunas Extras)
execSQL($pdo, "ALTER TABLE products ADD COLUMN active TINYINT(1) DEFAULT 1", "Coluna 'active' em products");
execSQL($pdo, "ALTER TABLE products ADD COLUMN is_vip TINYINT(1) DEFAULT 0", "Coluna 'is_vip' em products");
execSQL($pdo, "ALTER TABLE products ADD COLUMN is_manufactured TINYINT(1) DEFAULT 0", "Coluna 'is_manufactured' em products");
execSQL($pdo, "ALTER TABLE products ADD COLUMN seo_title VARCHAR(255)", "Coluna 'seo_title' em products");
execSQL($pdo, "ALTER TABLE products ADD COLUMN seo_description TEXT", "Coluna 'seo_description' em products");
execSQL($pdo, "ALTER TABLE products ADD COLUMN video_url VARCHAR(255)", "Coluna 'video_url' em products");
execSQL($pdo, "ALTER TABLE products ADD COLUMN price_wholesale DECIMAL(10,2) DEFAULT 0.00", "Coluna 'price_wholesale' em products");
execSQL($pdo, "ALTER TABLE products ADD COLUMN min_wholesale_qty INT DEFAULT 0", "Coluna 'min_wholesale_qty' em products");

// 3. Tabela CATEGORIAS (Slug)
execSQL($pdo, "ALTER TABLE categories ADD COLUMN slug VARCHAR(255)", "Coluna 'slug' em categories");

// 4. Tabela IMAGENS (Galeria)
execSQL($pdo, "CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)", "Tabela 'product_images'");

// 5. Tabela REVIEWS (Avaliações)
execSQL($pdo, "CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_name VARCHAR(100),
    rating INT DEFAULT 5,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)", "Tabela 'product_reviews'");

// 6. Tabela VARIAÇÕES (Cores, Tamanhos)
execSQL($pdo, "CREATE TABLE IF NOT EXISTS product_variations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'Cor',
    value VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    sku VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)", "Tabela 'product_variations'");

echo "\n--- FIM ---</pre>";
echo "<br><a href='index.php'>Voltar para o Site</a>";
?>