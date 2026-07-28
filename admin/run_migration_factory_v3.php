<?php
// catalogo/admin/run_migration_factory_v3.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

echo "<h1>🏭 Executando Migração do Módulo Fábrica (Fase 2)...</h1>";

try {
    // 1. Alinha a tabela factory_clients com a tabela users do CRM principal
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS neighborhood VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS number VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS complement VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS is_vip TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS is_lead TINYINT(1) DEFAULT 0");
    echo "<p>✅ Colunas adicionais adicionadas em 'factory_clients' para paridade total com o CRM principal.</p>";

    // 2. Alinha a tabela factory_products com a tabela products
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(10,3) DEFAULT 0.000");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS length_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS width_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS height_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL");
    echo "<p>✅ Colunas de dimensões/pesos e fotos adicionadas em 'factory_products' para integração de frete.</p>";

    $pdo->exec("ALTER TABLE factory_employees ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL");
    echo "<p>✅ Coluna 'phone' adicionada em 'factory_employees' para WhatsApp de contato.</p>";

    // 3. Adiciona campo de telefone de notificação na ordem de produção e no cadastro de usuários para alertar operador/Salvador
    $pdo->exec("ALTER TABLE factory_production_orders ADD COLUMN IF NOT EXISTS notification_phone VARCHAR(30) DEFAULT NULL");
    echo "<p>✅ Coluna 'notification_phone' adicionada em 'factory_production_orders' para alertas automáticos via WhatsApp.</p>";

    // 4. Criação da tabela de defeitos relatados (factory_defects)
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_defects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        production_order_id INT DEFAULT NULL,
        product_id INT DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        sender_phone VARCHAR(30) DEFAULT NULL,
        status ENUM('pending', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (production_order_id) REFERENCES factory_production_orders(id) ON DELETE SET NULL,
        FOREIGN KEY (product_id) REFERENCES factory_products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_defects' pronta para receber relatos de problemas via WhatsApp e Celular.</p>";

    // 5. Criação da tabela de lembretes/tarefas (factory_tasks)
    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_text TEXT NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p>✅ Tabela 'factory_tasks' criada para gerenciar os lembretes do painel.</p>";

    echo "<h2>🎉 Migração Concluída com Sucesso!</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Erro ao rodar migração: " . $e->getMessage() . "</h2>";
}
?>
