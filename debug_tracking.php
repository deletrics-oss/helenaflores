<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/melhorenvio.php';

$me = new MelhorEnvioAPI($pdo);

echo "--- TESTE DE RASTREIO DETALHADO ---\n\n";

// Query recent RMA tickets
$tickets = $pdo->query("SELECT id, customer_name, me_order_id, tracking_code, status FROM rma_tickets ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

echo "Últimos tickets no banco:\n";
print_r($tickets);

$ids = [];
foreach ($tickets as $t) {
    if (!empty($t['me_order_id'])) {
        $ids[] = $t['me_order_id'];
    }
}

if (!empty($ids)) {
    echo "\nTestando tracking() para os IDs: " . implode(', ', $ids) . "\n";
    $trackRes = $me->tracking($ids);
    echo "Resposta do tracking():\n";
    print_r($trackRes);

    echo "\nTestando getOrder() individualmente:\n";
    foreach ($ids as $id) {
        echo "\nID: $id\n";
        $orderRes = $me->getOrder($id);
        print_r($orderRes);
    }
} else {
    echo "\nNenhum ticket com me_order_id encontrado.\n";
}
?>
