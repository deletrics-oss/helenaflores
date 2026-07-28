<?php
// scratch/debug_customer_41.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$user_id = 41;

// Get Customer Info
$user = $pdo->query("SELECT * FROM users WHERE id = $user_id")->fetch();
echo "User: " . $user['name'] . "\n";
echo "Current Debt in DB: " . $user['current_debt'] . "\n";

// Get Orders
$orders = $pdo->query("SELECT id, total_amount, created_at, status FROM orders WHERE user_id = $user_id ORDER BY created_at DESC")->fetchAll();
echo "\n--- ORDERS ---\n";
foreach ($orders as $o) {
    echo "Pedido #{$o['id']}: R$ {$o['total_amount']} | Status: {$o['status']} | Data: {$o['created_at']}\n";
}

// Get Payments
$payments = $pdo->query("SELECT id, amount, payment_method, description, created_at FROM customer_payments WHERE user_id = $user_id ORDER BY created_at DESC")->fetchAll();
echo "\n--- PAYMENTS ---\n";
foreach ($payments as $p) {
    echo "Pagamento #{$p['id']}: R$ {$p['amount']} | Metodo: {$p['payment_method']} | Desc: {$p['description']} | Data: {$p['created_at']}\n";
}
