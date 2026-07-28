<?php
// catalogo/admin/header.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin(); // Security Check
include_once __DIR__ . '/../includes/theme_injector.php';
?>
<header style="background: #0f131a; border-bottom: 1px solid #333; padding: 1rem 0;">
    <div class="container nav-wrapper">
        <div class="logo">
            <a href="dashboard.php">
                <?php if (file_exists(__DIR__ . '/../../assets/logo.png')): ?>
                    <img src="../assets/logo.png?v=<?php echo time(); ?>" alt="Admin Painel"
                        style="max-height:40px; width:auto; vertical-align:middle;">
                <?php else: ?>
                    Admin<span>Painel</span>
                <?php endif; ?>
            </a>
        </div>
        <nav class="nav-links admin-nav">
            <a href="dashboard.php">📊 Dash</a>
            <a href="orders.php">📦 Pedidos</a>
            <a href="products.php">🕹️ Produtos</a>
            <a href="categories.php" style="color:#f1c40f;">📂 Categorias</a>

            <div class="dropdown">
                <a class="dropdown-btn">🛠️ Ferramentas ▾</a>
                <div class="dropdown-content">
                    <a href="banners.php">🖼️ Banners (Carrossel)</a>
                    <a href="pos.php" style="font-weight:bold; color:var(--primary);">🏧 Frente de Loja (PDV)</a>
                    <a href="stock-entry.php" style="color:#00e676;">➕ Entrada de Estoque (NFE)</a>
                    <a href="stock-logs.php">📦 Log de Estoque</a>
                    <a href="pos-manual.php">📖 Manual do PDV</a>
                    <a href="import-product.php">📥 Importador IA</a>
                    <a href="import-bulk.php">🚀 Importar em Massa</a>
                    <?php if (file_exists(__DIR__ . '/../fabrica/dashboard.php')): ?>
                        <a href="../fabrica/dashboard.php" target="_blank" style="color:#00e676;">🏭 Fábrica / ERP</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dropdown">
                <a class="dropdown-btn" style="color:#ff6b35;">🛒 Marketplaces ▾</a>
                <div class="dropdown-content">
                    <a href="verificar_ean.php" style="color:#00e676;">🔍 Verificar EANs</a>
                    <a href="corrigir_ean.php" style="color:#f39c12;">🔧 Corrigir EANs</a>
                    <a href="export_shopee_csv.php" style="color:#ee4d2d;">🟠 Exportar Shopee (CSV)</a>
                    <a href="export_shopee_all.php">🟠 Exportar Shopee (XLSX)</a>
                    <a href="shopee_template_manager.php" style="color:#ee4d2d; font-size:0.85em;">└ 📁 Gestão de
                        Templates Shopee</a>
                    <a href="export_mercadolivre.php" style="color:#ffe600;">🟡 Exportar Mercado Livre</a>
                    <a href="ml_template_manager.php" style="color:#ffe600; font-size:0.85em;">└ 📁 Gestão de Templates
                        ML</a>
                    <a href="marketplace_categories.php">📂 Categorias Marketplace</a>
                    <a href="run_migration.php">🗄️ Migração Shopee</a>
                </div>
            </div>

            <div class="dropdown">
                <a class="dropdown-btn">⚙️ Sistema ▾</a>
                <div class="dropdown-content">
                    <a href="customers.php">👥 Clientes</a>
                    <a href="leads.php" style="color:#00e676;">📢 Leads (Exportar)</a>
                    <a href="manage-admins.php">🛡️ Equipe Admin</a>
                    <a href="reviews.php">⭐ Avaliações</a>
                    <a href="settings_modules.php">🔌 Módulos/API</a>
                    <a href="settings.php">🔧 Configurações Gerais</a>
                    <a href="?action=fix_db_schema"
                        onclick="return confirm('Isso vai verificar e criar tabelas faltantes. Continuar?');"
                        style="color:#f39c12;">🚑 Reparar Banco</a>
                    <a href="../update_db_source.php" target="_blank" style="color:#e67e22;">🛠️ Auto Reparador
                        (Source)</a>
                </div>
            </div>

            <a href="../logout.php" style="color:#e74c3c;">🚪 Sair</a>
        </nav>
    </div>
</header>
<?php if (isset($_SESSION['flash_msg'])): ?>
    <div style="background: #27ae60; color: white; padding: 1rem; text-align: center; font-weight: bold; position: sticky; top: 0; z-index: 1000;"
        id="flash-msg">
        <?php echo $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']); ?>
        <button onclick="document.getElementById('flash-msg').remove()"
            style="background:none; border:none; color:white; margin-left:15px; cursor:pointer; font-weight:bold;">&times;</button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['error_msg'])): ?>
    <div style="background: #e74c3c; color: white; padding: 1rem; text-align: center; font-weight: bold; position: sticky; top: 0; z-index: 1000;"
        id="error-msg">
        <?php echo $_SESSION['error_msg'];
        unset($_SESSION['error_msg']); ?>
        <button onclick="document.getElementById('error-msg').remove()"
            style="background:none; border:none; color:white; margin-left:15px; cursor:pointer; font-weight:bold;">&times;</button>
    </div>
<?php endif; ?>
<?php
// Embedded DB Fixer (Emergency Tool)
if (isset($_GET['action']) && $_GET['action'] == 'fix_db_schema') {
    try {
        // 0. Categories (Essential)
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL
        )");

        // 1. Banners
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
        // 2. Extra Columns
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN active TINYINT(1) DEFAULT 1");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_vip TINYINT(1) DEFAULT 0");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN seo_title VARCHAR(255)");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN price_wholesale DECIMAL(10,2) DEFAULT NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN min_wholesale_qty INT DEFAULT 10");
        } catch (Exception $e) {
        }
        // 3. Gallery
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        // 4. Reviews
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_name VARCHAR(100),
            rating INT DEFAULT 5,
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");



        // 4.1 Update Reviews (Approval)
        try {
            $pdo->exec("ALTER TABLE product_reviews ADD COLUMN approved TINYINT(1) DEFAULT 0");
        } catch (Exception $e) {
        }

        // 5. Variations
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_variations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            type VARCHAR(50) DEFAULT 'Cor',
            value VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) DEFAULT NULL,
            price_wholesale DECIMAL(10,2) DEFAULT NULL,
            sku VARCHAR(50) DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");

        // 5.1 Update Variations (Extra Fields)
        try {
            $pdo->exec("ALTER TABLE product_variations ADD COLUMN price_wholesale DECIMAL(10,2) DEFAULT NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE product_variations ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
        } catch (Exception $e) {
        }

        // 6. POS & Inventory
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN stock_qty INT DEFAULT 0");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE product_variations ADD COLUMN stock_qty INT DEFAULT 0");
        } catch (Exception $e) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            variation_id INT NULL,
            type ENUM('in', 'out'),
            qty INT,
            reason VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pos_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            total DECIMAL(10,2),
            payment_method VARCHAR(50),
            status VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pos_sale_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT,
            product_id INT,
            variation_id INT NULL,
            qty INT,
            unit_price DECIMAL(10,2)
        )");

        // 7. Customer Area (Users Table expansion)
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN document VARCHAR(20) NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN zipcode VARCHAR(10) NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN number VARCHAR(20) NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN neighborhood VARCHAR(100) NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_lead TINYINT(1) DEFAULT 1");
        } catch (Exception $e) {
        }
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN state VARCHAR(2) NULL");
        } catch (Exception $e) {
        }

        // 8. Advanced Stock (Suppliers & Entries)
        $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            cnpj VARCHAR(20) NULL,
            contact_info TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NULL,
            invoice_number VARCHAR(100) NULL,
            total_value DECIMAL(10,2) DEFAULT 0,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_entry_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_id INT NOT NULL,
            product_id INT NOT NULL,
            variation_id INT NULL,
            qty INT NOT NULL,
            unit_cost DECIMAL(10,2) DEFAULT 0,
            FOREIGN KEY (entry_id) REFERENCES stock_entries(id) ON DELETE CASCADE
        )");

        // Add reason tracking to previous movements if not exists
        try {
            $pdo->exec("ALTER TABLE stock_movements ADD COLUMN entry_id INT NULL AFTER reason");
        } catch (Exception $e) {
        }

        echo "<script>alert('✅ Banco de Dados Verificado e Reparado!'); window.location.href='dashboard.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "'); window.location.href='dashboard.php';</script>";
    }
}
?>
<div style="background:var(--primary); color:#000; text-align:center; padding:0.5rem; font-weight:bold;">
    ÁREA ADMINISTRATIVA - <a href="../index.php" target="_blank" style="text-decoration:underline;">Ver Loja</a>
</div>