<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "TABELAS:\n" . implode("\n", $tables) . "\n\n";

$targetTables = ['purchase_orders', 'purchase_order_items', 'suppliers', 'products'];
foreach ($targetTables as $table) {
    if (in_array($table, $tables)) {
        echo "COLUNAS $table:\n";
        $stmt = $pdo->query("DESCRIBE $table");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "\n";
    }
}
