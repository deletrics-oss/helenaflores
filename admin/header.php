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
            <a href="../seed_helena_flores.php" target="_blank" style="background:#8B263E; color:#FFF; font-weight:bold; border-radius:4px;">🌹 Semeador Helena Flores</a>
            <a href="import-catalog-bot.php" style="background:#C5A059; color:#FFF; font-weight:bold; border-radius:4px;">🤖 Robô Extrator (Clonar)</a>
            <a href="dashboard.php">📊 Dash</a>
            <a href="orders.php">📦 Pedidos</a>
            <a href="purchase_pos.php" style="color:#00e676; font-weight:bold;">🛒 PDV de Compra</a>
            <a href="products.php">🕹️ Produtos</a>
            <a href="categories.php" style="color:#f1c40f;">📂 Categorias</a>
            <a href="service_channels.php" style="color:#0084FF;">💬 Atendimento</a>
            <a href="rma.php" style="color:#9b59b6; font-weight:bold;">🔄 RMA</a>
            <?php 
            try {
                $debtorCount = $pdo->query("SELECT COUNT(*) FROM users WHERE current_debt > 0 AND role != 'admin'")->fetchColumn();
            } catch(Exception $e) { $debtorCount = 0; }
            ?>
            <a href="customers.php?filter=debtors" style="color:#e74c3c; font-weight:bold; position:relative;">
                💰 Devedores
                <?php if($debtorCount > 0): ?>
                    <span style="background:red; color:white; font-size:0.65rem; padding:2px 6px; border-radius:10px; position:absolute; top:-10px; right:-12px; box-shadow:0 0 5px rgba(231,76,60,0.5);"><?php echo $debtorCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="reports.php" style="color:#2ecc71; font-weight:bold;">📈 Relatórios</a>

            <div class="dropdown">
                <a class="dropdown-btn">🛠️ Ferramentas ▾</a>
                <div class="dropdown-content">
                    <a href="banners.php">🖼️ Banners (Carrossel)</a>
                    <a href="pos.php" style="font-weight:bold; color:var(--primary);">🏧 Frente de Loja (PDV)</a>
                    <a href="pos-reverso.php" style="font-weight:bold; color:#e74c3c;">🔄 PDV Reverso (Devolução/Crédito)</a>
                    <a href="reports-returns.php" style="font-weight:bold; color:#f1c40f;">📋 Auditoria de Retornos (RMA/PDV)</a>
                    <a href="stock-entry.php" style="color:#00e676;">➕ Entrada de Estoque (NFE)</a>
                    <a href="stock-logs.php">📦 Log de Estoque</a>
                    <a href="pos-manual.php">📖 Manual do PDV</a>
                    <a href="rma.php" style="color:#9b59b6; font-weight:bold;">🔄 RMA / Pós-Venda</a>
                    <a href="notifications.php" style="color:#00e676; font-weight:bold;">🔔 Notificações (Whats/SMS)</a>
                    <a href="ai_knowledge.php" style="color:#7209b7; font-weight:bold;">🧠 Cérebro do SDR (Suporte)</a>
                    <a href="inventory_scanner.php" style="color:#00ff88; font-weight:bold;">📱 Scanner de Estoque (Mobile)</a>
                    <a href="replenishment.php" style="color:#f1c40f; font-weight:bold;">📉 Reposição Inteligente (IA)</a>
                    <a href="../support.php" target="_blank" style="color:#4cc9f0;">🛠️ Portal de Autoatendimento</a>
                    <a href="../suporte.php" target="_blank" style="color:#e74c3c; font-weight:bold;">📦 Solicitação de Peças (Link p/ Cliente)</a>
                    <a href="lalamove.php" style="color:#FF6600; font-weight:bold;">🏍️ Lalamove Express</a>
                    <a href="uber_settings.php" style="color:#fff; background:#000; padding:2px 8px; border-radius:4px; font-weight:bold; margin-top:5px; display:inline-block;">🚙 Uber Delivery / Eats</a>
                    <a href="retention.php" style="color:#e67e22; font-weight:bold;">🎯 Retenção (CRM Vendas)</a>
                    <a href="import-product.php">📥 Importador IA</a>
                    <a href="import-bulk.php">🚀 Importar em Massa</a>
                    <a href="import-catalog-bot.php" style="color:#e8c3c8; font-weight:bold;">🤖 Robô Extrator (WhatsApp/Site)</a>
                    <a href="melhorenvio.php" style="color:#e74c3c; font-weight:bold;">📦 Melhor Envio (Frete)</a>
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
                    <?php if(canAccess('customers')): ?>
                        <a href="customers.php">👥 Clientes</a>
                        <a href="customers.php?filter=debtors" style="color:#e74c3c;">📜 Extratos Financeiros</a>
                    <?php endif; ?>
                    <?php if(canAccess('suppliers')): ?><a href="suppliers.php" style="color:var(--primary); font-weight:bold;">🏭 Fornecedores</a><?php endif; ?>
                    <?php if(canAccess('marketing')): ?><a href="leads.php" style="color:#00e676;">📢 Leads (Exportar)</a><?php endif; ?>
                    <?php if(canAccess('admin_manage')): ?><a href="manage-admins.php" style="border-top:1px solid #333; margin-top:5px; padding-top:10px; color:#3498db; font-weight:bold;"><i class="fas fa-users-cog"></i> GESTÃO DE EQUIPE (NOVO)</a><?php endif; ?>
                    <?php if(canAccess('products')): ?><a href="reviews.php">⭐ Avaliações</a><?php endif; ?>
                    <?php if(canAccess('settings')): ?>
                        <a href="settings_modules.php">🔌 Módulos/API</a>
                        <a href="settings.php">🔧 Configurações Gerais</a>
                    <?php endif; ?>
                    <?php if($_SESSION['user_role'] === 'admin'): ?>
                        <a href="?action=fix_db_schema" onclick="return confirm('Isso vai verificar e criar tabelas faltantes. Continuar?');" style="color:#f39c12;">🚑 Reparar Banco</a>
                    <?php endif; ?>
                </div>
            </div>

            <a href="javascript:void(0)" onclick="toggleFinance()" title="Ocultar/Mostrar Valores Financeiros">
                <i class="fas fa-eye" id="finance-eye-icon" style="color:#f1c40f; font-size:1.2rem; margin-left:10px;"></i>
            </a>
            
            <a href="../logout.php" style="color:#e74c3c; margin-left:15px;">🚪 Sair</a>
            
            <!-- Google Translate -->
            <div id="google_translate_element" style="margin-left:15px; display:inline-block; vertical-align:middle;"></div>
            <script type="text/javascript">
                function googleTranslateElementInit() {
                    new google.translate.TranslateElement({pageLanguage: 'pt', layout: google.translate.TranslateElement.InlineLayout.SIMPLE}, 'google_translate_element');
                }
            </script>
            <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
        </nav>
    </div>
</header>

<!-- SCRIPT OLHO FINANCEIRO -->
<script>
let financeObserver;

function toggleFinance() {
    let isHidden = localStorage.getItem('hide_finance') === 'true';
    localStorage.setItem('hide_finance', !isHidden);
    applyFinanceToggle();
}

function applyFinanceToggle() {
    // Disconnect observer if active to avoid recursive mutation trigger
    if (financeObserver) {
        financeObserver.disconnect();
    }

    let isHidden = localStorage.getItem('hide_finance') === 'true';
    let icon = document.getElementById('finance-eye-icon');
    if (icon) {
        icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
        icon.style.color = isHidden ? '#e74c3c' : '#f1c40f';
    }
    
    document.querySelectorAll('.finance-value').forEach(el => {
        if (isHidden) {
            if (!el.hasAttribute('data-original')) {
                el.setAttribute('data-original', el.innerHTML);
            }
            const maskedHtml = '<span style="opacity:0.5;">R$ ****</span>';
            if (el.innerHTML !== maskedHtml) {
                el.innerHTML = maskedHtml;
            }
        } else {
            if (el.hasAttribute('data-original')) {
                el.innerHTML = el.getAttribute('data-original');
                el.removeAttribute('data-original');
            }
        }
    });

    // Reconnect the observer if finance elements are hidden
    if (financeObserver && isHidden) {
        financeObserver.observe(document.body, { childList: true, subtree: true });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    financeObserver = new MutationObserver(function(mutations) {
        if (localStorage.getItem('hide_finance') === 'true') {
            applyFinanceToggle();
        }
    });
    applyFinanceToggle();
});
</script>

<!-- HEALTH & SHORTCUTS -->
<div id="wa-health-indicator" style="display:none; padding:8px; text-align:center; font-size:0.8rem; font-weight:bold; background:#e74c3c; color:white;"></div>

<div id="shortcuts-bar" style="background:#141820; border-bottom:1px solid #333; padding:8px 0; font-size:0.8rem;">
    <div class="container" style="display:flex; align-items:center; gap:15px; overflow-x:auto; white-space:nowrap;">
        <span style="color:#5a6478; font-weight:bold; text-transform:uppercase; font-size:0.7rem;"><i class="fas fa-bolt" style="color:#f1c40f"></i> Atalhos:</span>
        <div id="shortcuts-container" style="display:flex; gap:5px;"></div>
    </div>
</div>

<!-- SMART SHORTCUTS BAR -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Monitoramento de Saúde do WhatsApp
    function checkWaHealth() {
        fetch('notifications.php?wa_action=status')
            .then(r => r.json())
            .then(data => {
                const state = data.instance ? data.instance.state : (data.state || 'disconnected');
                const el = document.getElementById('wa-health-indicator');
                if (state !== 'open' && state !== 'CONNECTED') {
                    el.style.background = 'rgba(231, 76, 60, 0.9)';
                    el.style.color = '#fff';
                    el.innerHTML = '<i class="fas fa-exclamation-triangle"></i> WhatsApp Desconectado! <a href="notifications.php" style="color:#fff; text-decoration:underline; margin-left:10px;">Conectar</a>';
                    el.style.display = 'block';
                } else {
                    el.style.display = 'none';
                }
            }).catch(() => {});
    }
    
    checkWaHealth();
    setInterval(checkWaHealth, 60000);
    
    // Atalhos Inteligentes
    const defaultLinks = [
        <?php if(canAccess('pos_purchase')): ?>{name: "🛒 PDV Compra", url: "purchase_pos.php", id: "l_ppos"},<?php endif; ?>
        <?php if(canAccess('suppliers')): ?>{name: "🏭 Fornecedores", url: "suppliers.php", id: "l_suppliers"},<?php endif; ?>
        <?php if(canAccess('orders')): ?>{name: "📦 Pedidos", url: "orders.php", id: "l_orders"},<?php endif; ?>
        <?php if(canAccess('marketing')): ?>{name: "🔔 Notificações", url: "notifications.php", id: "l_notif"},<?php endif; ?>
        <?php if(canAccess('customers')): ?>{name: "💰 Devedores", url: "customers.php?filter=debtors", id: "l_debt", pinned: true},<?php endif; ?>
        <?php if(canAccess('rma')): ?>{name: "🔄 RMA", url: "rma.php", id: "l_rma"},<?php endif; ?>
        <?php if(canAccess('logistics')): ?>
            {name: "🏍️ Lalamove", url: "lalamove.php", id: "l_llm"},
            {name: "🚚 Fretes", url: "melhorenvio.php", id: "l_me"},
        <?php endif; ?>
        <?php if(canAccess('customers')): ?>{name: "👥 Clientes", url: "customers.php", id: "l_customers"},<?php endif; ?>
        <?php if(canAccess('pos_sale')): ?>{name: "🏧 PDV", url: "pos.php", id: "l_pos"},<?php endif; ?>
        <?php if(canAccess('rma')): ?>{name: "🔄 PDV Reverso", url: "pos-reverso.php", id: "l_pos_rev"}<?php endif; ?>
    ];
    
    let counts = JSON.parse(localStorage.getItem("admin_link_counts") || "{}");
    document.querySelectorAll(".admin-nav a, .dropdown-content a").forEach(a => {
        a.addEventListener("click", function() {
            let url = this.getAttribute("href");
            if(url && !url.startsWith("#") && !url.startsWith("javascript") && !url.startsWith("?")) {
                let base = url.split("?")[0].replace("../", "").replace("./", "");
                counts[base] = (counts[base] || 0) + 1;
                localStorage.setItem("admin_link_counts", JSON.stringify(counts));
            }
        });
    });

    defaultLinks.forEach(link => {
        let base = link.url.split("?")[0];
        link.clicks = counts[base] || 0;
    });

    defaultLinks.sort((a, b) => {
        if(a.pinned && !b.pinned) return -1;
        if(!a.pinned && b.pinned) return 1;
        return b.clicks - a.clicks;
    });
    let html = "";
    defaultLinks.slice(0, 6).forEach(link => {
        html += `<a href="${link.url}" class="shortcut-link" style="color:#fff; text-decoration:none; margin:0 10px; opacity:0.8; transition:0.2s" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.8">${link.name}</a>`;
    });
    const container = document.getElementById("shortcuts-container");
    if(container) container.innerHTML = html;
});
</script>
<!-- END SMART SHORTCUTS -->
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
        // 2. Extra Columns Products
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

        // 3. Extra Columns Users
        $userCols = [
            'document' => 'VARCHAR(20)',
            'zipcode' => 'VARCHAR(20)',
            'address' => 'VARCHAR(255)',
            'number' => 'VARCHAR(20)',
            'complement' => 'VARCHAR(255)',
            'neighborhood' => 'VARCHAR(255)',
            'city' => 'VARCHAR(255)',
            'state' => 'VARCHAR(2)',
            'is_vip' => 'TINYINT(1) DEFAULT 0',
            'is_lead' => 'TINYINT(1) DEFAULT 0',
            'current_debt' => 'DECIMAL(10,2) DEFAULT 0',
            'total_spent' => 'DECIMAL(10,2) DEFAULT 0'
        ];
        foreach ($userCols as $col => $def) {
            try { $pdo->exec("ALTER TABLE users ADD COLUMN $col $def"); } catch (Exception $e) {}
        }
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN price_wholesale DECIMAL(10,2) DEFAULT NULL");
        } catch (Exception $e) { }
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0");
        } catch (Exception $e) { }
        try {
            $pdo->exec("ALTER TABLE product_variations ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0");
        } catch (Exception $e) { }
        try {
            $pdo->exec("ALTER TABLE order_items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0");
        } catch (Exception $e) { }
        try {
            $pdo->exec("ALTER TABLE pos_sale_items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0");
        } catch (Exception $e) { }
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN min_wholesale_qty INT DEFAULT 10");
        } catch (Exception $e) { }

        // 9. Custom Message Templates
        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50), 
            title VARCHAR(100),
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Ledger table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
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

        // 9. Payment Accounts for Debt Collection
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            name VARCHAR(100) NOT NULL, 
            type ENUM('pix', 'bank') DEFAULT 'pix', 
            pix_key VARCHAR(255), 
            bank_info TEXT, 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        // 10. Sync current_debt for all users
        $pdo->exec("UPDATE users u SET current_debt = (
            (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
            (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
        )");

        // 11. AI Knowledge Table for SDR
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'suporte',
            bot_role ENUM('geral', 'suporte', 'vendas') DEFAULT 'geral',
            image_url VARCHAR(255),
            link_url VARCHAR(255),
            video_url VARCHAR(255),
            tags TEXT,
            related_products TEXT,
            ai_instructions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        // Migration: Add bot_role if not exists
        try { $pdo->exec("ALTER TABLE ai_knowledge ADD COLUMN bot_role ENUM('geral', 'suporte', 'vendas') DEFAULT 'geral' AFTER category"); } catch(Exception $e){}

        // 12. Add wa_notify_active to users
        try { $pdo->exec("ALTER TABLE users ADD COLUMN wa_notify_active TINYINT(1) DEFAULT 1 AFTER role"); } catch(Exception $e){}
        
        // 13. Add cost_price to order_items for profit calculation
        try { $pdo->exec("ALTER TABLE order_items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0 AFTER price"); } catch(Exception $e){}

        // 14. Add notify_blocked to users (emergency sync)
        try { $pdo->exec("ALTER TABLE users ADD COLUMN notify_blocked TINYINT(1) DEFAULT 0"); } catch(Exception $e){}

        echo "<script>alert('✅ Banco de Dados Verificado, Reparado e Saldos Sincronizados!'); window.location.href='dashboard.php';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "'); window.location.href='dashboard.php';</script>";
    }
}
?>
<div style="background:var(--primary); color:#000; text-align:center; padding:0.5rem; font-weight:bold;">
    ÁREA ADMINISTRATIVA - <a href="../index.php" target="_blank" style="text-decoration:underline;">Ver Loja</a>
</div>

<!-- SYSTEM-WIDE SCROLL IMPROVEMENTS (Auto-scroll to alerts + Floating Back to Top button) -->
<style>
#back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 46px;
    height: 46px;
    background: rgba(15, 19, 26, 0.85);
    border: 1px solid rgba(241, 196, 15, 0.5);
    color: #f1c40f;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    z-index: 99999;
    box-shadow: 0 6px 20px rgba(0,0,0,0.6);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    font-size: 1.1rem;
}
#back-to-top:hover {
    background: #f1c40f;
    color: #000;
    border-color: #f1c40f;
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(241, 196, 15, 0.5);
}
#back-to-top.visible {
    opacity: 1;
    visibility: visible;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Auto-scroll suave para alertas/mensagens de sucesso ou erro se existirem na tela
    const alertEl = document.querySelector(".alert");
    if (alertEl) {
        setTimeout(() => {
            alertEl.scrollIntoView({ behavior: "smooth", block: "center" });
        }, 150); // Pequeno atraso para garantir renderização correta
    }

    // 2. Criar e gerenciar o botão flutuante "Voltar ao Topo"
    const btnTop = document.createElement("div");
    btnTop.id = "back-to-top";
    btnTop.setAttribute("title", "Voltar ao Topo");
    btnTop.innerHTML = '<i class="fas fa-chevron-up"></i>';
    document.body.appendChild(btnTop);

    window.addEventListener("scroll", function() {
        if (window.pageYOffset > 300) {
            btnTop.classList.add("visible");
        } else {
            btnTop.classList.remove("visible");
        }
    });

    btnTop.addEventListener("click", function() {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
});
</script>