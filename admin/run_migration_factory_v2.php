<?php
// catalogo/admin/run_migration_factory_v2.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

echo "<h1>🏭 Executando Migração do Módulo Fábrica (ERP)...</h1>";

try {
    // 1. factory_users
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'worker') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_users' pronta.</p>";

    // 2. factory_clients
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        document VARCHAR(20) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        state VARCHAR(2) DEFAULT NULL,
        zipcode VARCHAR(10) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_clients' pronta.</p>";

    // 3. factory_suppliers
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        cnpj VARCHAR(20) DEFAULT NULL,
        contact_info TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_suppliers' pronta.</p>";

    // 4. factory_products
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        sku VARCHAR(60) DEFAULT NULL,
        raw_material_cost DECIMAL(10,2) DEFAULT 0.00,
        labor_cost DECIMAL(10,2) DEFAULT 0.00,
        machinery_cost DECIMAL(10,2) DEFAULT 0.00,
        total_cost DECIMAL(10,2) DEFAULT 0.00,
        sale_price DECIMAL(10,2) DEFAULT 0.00,
        stock_qty INT DEFAULT 0,
        image_path VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_products' pronta.</p>";

    // 5. factory_machines
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_machines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
        cost_per_hour DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_machines' pronta.</p>";

    // 6. factory_employees
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        role VARCHAR(100) DEFAULT NULL,
        cost_per_hour DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_employees' pronta.</p>";

    // 7. factory_production_orders (Dropping old if existed without links or just create)
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_production_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        employee_id INT DEFAULT NULL,
        machine_id INT DEFAULT NULL,
        cost_applied DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('pending', 'in_production', 'completed', 'canceled') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        FOREIGN KEY (product_id) REFERENCES factory_products(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES factory_employees(id) ON DELETE SET NULL,
        FOREIGN KEY (machine_id) REFERENCES factory_machines(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_production_orders' pronta.</p>";

    // 8. factory_sales
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) DEFAULT 'pix',
        shipping_method VARCHAR(50) DEFAULT NULL,
        tracking_code VARCHAR(100) DEFAULT NULL,
        invoice_number VARCHAR(100) DEFAULT NULL,
        status ENUM('pending', 'paid', 'shipped', 'canceled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES factory_clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_sales' pronta.</p>";

    // 9. factory_sale_items
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(120) NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES factory_sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES factory_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_sale_items' pronta.</p>";

    // 10. factory_cashbook
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_cashbook (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('income', 'expense') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_cashbook' pronta.</p>";

    // 11. factory_vehicles
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        plate VARCHAR(20) DEFAULT NULL,
        driver VARCHAR(100) DEFAULT NULL,
        status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_vehicles' pronta.</p>";

    // Seeding - 1. Default User
    $pass = password_hash('fabrica', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO factory_users (name, username, password, role) VALUES ('Fábrica Admin', 'fabrica', ?, 'admin')");
    $stmt->execute([$pass]);
    echo "<p>👤 Usuário padrão '<strong>fabrica</strong>' com senha '<strong>fabrica</strong>' semeado.</p>";

    // Seeding - 2. Default Products
    $products = [
        ['Botões', 'FAC-BTN', 1.50, 0.50, 0.20, 2.20, 5.00, 500],
        ['Comandos', 'FAC-CMD', 12.00, 3.50, 1.50, 17.00, 35.00, 150],
        ['Suporte de Celular', 'FAC-CEL', 2.00, 1.00, 0.50, 3.50, 10.00, 300],
        ['Pezinho', 'FAC-PEZ', 0.50, 0.20, 0.10, 0.80, 2.50, 1000],
        ['Travinhas', 'FAC-TRAV', 0.30, 0.15, 0.05, 0.50, 1.50, 2000],
        ['Fliperama de Metal 2 Players', 'FAC-FLIP-2M', 250.00, 80.00, 40.00, 370.00, 890.00, 15],
        ['Controle Fliperama 2 Player', 'FAC-CTRL-2P', 120.00, 30.00, 15.00, 165.00, 399.00, 45],
        ['Controle Fliperama 1 Player', 'FAC-CTRL-1P', 70.00, 20.00, 10.00, 100.00, 249.00, 60]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO factory_products (name, sku, raw_material_cost, labor_cost, machinery_cost, total_cost, sale_price, stock_qty) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($products as $p) {
        $stmt->execute($p);
    }
    echo "<p>📦 Produtos iniciais (Botões, Comandos, Suportes, Pezinho, Travinhas, Fliperamas) semeados com sucesso.</p>";

    // Seeding - 3. Default Client (Fight Arcade itself B2B)
    $stmt = $pdo->prepare("INSERT IGNORE INTO factory_clients (name, document, phone, email, address, city, state, zipcode) VALUES ('Fight Arcade Matriz', '12.345.678/0001-90', '(11) 98812-1976', 'contato@fightarcade.com.br', 'Av. Paulista, 1000', 'São Paulo', 'SP', '01311-100')");
    $stmt->execute();
    echo "<p>👥 Cliente B2B padrão '<strong>Fight Arcade Matriz</strong>' semeado.</p>";

    echo "<h2>🎉 Migração Concluída com Sucesso!</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Erro ao rodar migração: " . $e->getMessage() . "</h2>";
}
