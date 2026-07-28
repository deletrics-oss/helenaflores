<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h2>Iniciando Migração Fight Arcade OS...</h2><ul>";

// 1. Tabela de Lembretes / Tarefas
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATETIME NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    notify_wa TINYINT(1) DEFAULT 1,
    last_notified TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "<li>Tabela 'admin_reminders': OK</li>";

// 2. Tabela Financeira (Dívidas / Obrigações)
$pdo->exec("CREATE TABLE IF NOT EXISTS financial_debts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('payable', 'receivable') DEFAULT 'payable',
    due_date DATE NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    category VARCHAR(100) DEFAULT 'Geral',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "<li>Tabela 'financial_debts': OK</li>";

// 3. Adicionar coluna de estoque mínimo se não existir
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN min_stock INT DEFAULT 2 AFTER stock");
    echo "<li>Coluna 'min_stock' adicionada aos produtos</li>";
} catch(Exception $e) {}

echo "</ul><h3>✅ Infraestrutura pronta!</h3>";
?>
