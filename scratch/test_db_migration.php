<?php
// scratch/test_db_migration.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    // Run the migrations manually just in case
    echo "Executando migrações...\n";
    $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN refund_price DECIMAL(10,2) DEFAULT 0.00");
    echo "refund_price adicionado ou já existente.\n";
} catch (Exception $e) {
    echo "refund_price: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN refund_method VARCHAR(50) DEFAULT NULL");
    echo "refund_method adicionado ou já existente.\n";
} catch (Exception $e) {
    echo "refund_method: " . $e->getMessage() . "\n";
}

// Check columns list
try {
    $stmt = $pdo->query("DESCRIBE rma_tickets");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colunas da tabela rma_tickets:\n";
    print_r($columns);
} catch (Exception $e) {
    echo "Erro ao descrever tabela: " . $e->getMessage() . "\n";
}
