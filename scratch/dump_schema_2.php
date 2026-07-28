<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "TABELAS:\n" . implode("\n", $tables) . "\n\n";

if (in_array('purchase_orders', $tables)) {
    echo "COLUNAS purchase_orders:\n";
    $stmt = $pdo->query("DESCRIBE purchase_orders");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if (in_array('purchase_order_items', $tables)) {
    echo "COLUNAS purchase_order_items:\n";
    $stmt = $pdo->query("DESCRIBE purchase_order_items");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
