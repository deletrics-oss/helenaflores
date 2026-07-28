<?php
// catalogo/scratch/update_db_tracking.php
require_once __DIR__ . '/../includes/db.php';

echo "Atualizando tabelas para suporte a rastreio em tempo real...\n";

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN last_tracking_status VARCHAR(50) DEFAULT NULL");
    echo "Coluna 'last_tracking_status' adicionada em 'orders'.\n";
} catch (Exception $e) { echo "Coluna já existe ou erro em 'orders': " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN last_tracking_status VARCHAR(50) DEFAULT NULL");
    echo "Coluna 'last_tracking_status' adicionada em 'rma_tickets'.\n";
} catch (Exception $e) { echo "Coluna já existe ou erro em 'rma_tickets': " . $e->getMessage() . "\n"; }

echo "Concluído.\n";
