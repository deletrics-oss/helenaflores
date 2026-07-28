<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h2>Atualizando Banco de Dados (Versão Final Correta)</h2>";
echo "<pre>";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // =================================================================
    // 1. Tabela USERS (Correção do erro 'Column not found: company_name')
    // =================================================================
    echo "1. Verificando tabela 'users'...\n";

    // Helper para verificar colunas de forma segura
    function checkColumn($pdo, $table, $col)
    {
        $stmt = $pdo->query("SELECT DATABASE()");
        $db = $stmt->fetchColumn();
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$db, $table, $col]);
        return $q->fetchColumn() > 0;
    }

    // Se tabela não existe, cria do zero completa
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL UNIQUE,
        email VARCHAR(255) NULL,
        password VARCHAR(255) NULL,
        company_name VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Se já existia, garante que a coluna company_name existe
    if (!checkColumn($pdo, 'users', 'company_name')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN company_name VARCHAR(255) NULL AFTER name");
        echo "   -> Coluna 'company_name' adicionada com sucesso.\n";
    } else {
        echo "   -> Coluna 'company_name' já existe.\n";
    }

    // Garante que phone existe e é unique (se der erro de duplicata, ignoramos por enquanto com try catch específico se fosse complexo, mas aqui vamos simples)
    if (!checkColumn($pdo, 'users', 'phone')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NOT NULL UNIQUE");
        echo "   -> Coluna 'phone' adicionada.\n";
    }

    // =================================================================
    // 2. Colunas na tabela PRODUCTS
    // =================================================================
    echo "\n2. Atualizando tabela 'products'...\n";

    $colsProd = [
        ['ean', "VARCHAR(13) NULL COMMENT 'Código de Barras'"],
        ['ncm', "VARCHAR(8) NULL COMMENT 'NCM Fiscal'"],
        ['weight_kg', "DECIMAL(10,3) DEFAULT '0.100' COMMENT 'Peso em KG'"],
        ['length_cm', "INT DEFAULT '10' COMMENT 'Comprimento cm'"],
        ['width_cm', "INT DEFAULT '10' COMMENT 'Largura cm'"],
        ['height_cm', "INT DEFAULT '5' COMMENT 'Altura cm'"],
        ['price_wholesale', "DECIMAL(10,2) DEFAULT NULL COMMENT 'Preço Atacado'"],
        ['min_wholesale_qty', "INT DEFAULT 5 COMMENT 'Qtd Mínima Atacado'"]
    ];

    foreach ($colsProd as $cp) {
        if (!checkColumn($pdo, 'products', $cp[0])) {
            $pdo->exec("ALTER TABLE products ADD COLUMN {$cp[0]} {$cp[1]}");
            echo "   -> Coluna '{$cp[0]}' adicionada.\n";
        } else {
            echo "   -> Coluna '{$cp[0]}' já existe.\n";
        }
    }

    // =================================================================
    // 3. Tabela CONFIGURAÇÕES (Module Settings)
    // =================================================================
    echo "\n3. Verificando tabela 'module_settings'...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS module_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module_key VARCHAR(50) NOT NULL UNIQUE,
        is_active TINYINT(1) DEFAULT 0,
        settings_json TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $defaultModules = [
        'payment_mercadopago' => 0,
        'payment_nupay' => 0,
        'shipping_correios' => 1,
        'shipping_melhorenvio' => 0,
        'shipping_motoboy' => 0
    ];

    foreach ($defaultModules as $key => $active) {
        $stmt = $pdo->prepare("SELECT id FROM module_settings WHERE module_key = ?");
        $stmt->execute([$key]);
        if (!$stmt->fetch()) {
            $stmtIn = $pdo->prepare("INSERT INTO module_settings (module_key, is_active) VALUES (?, ?)");
            $stmtIn->execute([$key, $active]);
            echo "   -> Módulo '$key' ativado.\n";
        }
    }

    echo "\n\n---------------------------------------------------";
    echo "\n   SUCESSO! O BANCO DE DADOS ESTÁ CORRIGIDO.   ";
    echo "\n   PODE APAGAR ESTE ARQUIVO E TESTAR O LOGIN.  ";
    echo "\n---------------------------------------------------";

} catch (PDOException $e) {
    echo "\n\nERRO: " . $e->getMessage();
    echo "\n\nVerifique se o usuário do banco tem permissão de ALTER TABLE.";
}

echo "</pre>";
?>