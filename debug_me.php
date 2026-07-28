<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/melhorenvio.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO MELHOR ENVIO (TICKETS #55 E #56) ===\n\n";

$me = new MelhorEnvioAPI($pdo);

// 1. Mostrar configurações básicas
echo "--- CONFIGURAÇÕES ---\n";
echo "Sandbox: " . ($me->getSetting('me_sandbox') === '1' ? "Sim" : "Não") . "\n";
echo "Client ID: " . $me->getSetting('me_client_id') . "\n";
echo "Token Expira em: " . $me->getSetting('me_token_expires') . "\n";
echo "Tem Token? " . ($me->hasToken() ? "Sim" : "Não") . "\n\n";

// 2. Buscar tickets #55 e #56 no banco
echo "--- TICKETS NO BANCO ---\n";
$stmt = $pdo->prepare("SELECT id, customer_name, me_order_id, tracking_code, me_status, status FROM rma_tickets WHERE id IN (55, 56)");
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tickets)) {
    echo "Nenhum ticket com ID 55 ou 56 encontrado no banco.\n\n";
} else {
    print_r($tickets);
    echo "\n";
}

// 3. Consultar Melhor Envio
echo "--- CONSULTA DA API DO MELHOR ENVIO ---\n";
$ids = [];
foreach ($tickets as $t) {
    if (!empty($t['me_order_id'])) {
        $ids[] = $t['me_order_id'];
    }
}

if (!empty($ids)) {
    echo "Consultando tracking para os IDs: " . implode(', ', $ids) . "\n";
    $resTrack = $me->tracking($ids);
    echo "Resposta do tracking():\n";
    print_r($resTrack);
    echo "\n";

    foreach ($ids as $id) {
        echo "Resposta do getOrder($id):\n";
        $resOrder = $me->getOrder($id);
        print_r($resOrder);
        echo "\n";
    }
} else {
    echo "Nenhum me_order_id encontrado nos tickets 55/56 do banco.\n";
}
?>
