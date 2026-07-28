<?php
/**
 * webhooks/uber.php — Fight Arcade
 * Receptor de eventos da Uber (Status de entrega, Pedidos Eats, etc)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

// Recebe o payload da Uber
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// 1. Fetch Uber Signing Key
$signingKey = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'uber_webhook_signing_key'")->fetchColumn();

// 2. HMAC Verification (Security)
if ($signingKey) {
    $signature = $_SERVER['HTTP_X_UBER_SIGNATURE'] ?? '';
    $computed = hash_hmac('sha256', $payload, $signingKey);
    if (!hash_equals($computed, $signature)) {
        file_put_contents(__DIR__ . '/../logs/uber_webhook.log', date('[Y-m-d H:i:s] ') . "INVALID SIGNATURE: " . $signature . PHP_EOL, FILE_APPEND);
        http_response_code(401);
        exit('Unauthorized');
    }
}

// Log do evento para debug (opcional)
file_put_contents(__DIR__ . '/../logs/uber_webhook.log', date('[Y-m-d H:i:s] ') . $payload . PHP_EOL, FILE_APPEND);

$notif = new NotificationService($pdo);

// Lógica de processamento de eventos
$event = $data['event_type'] ?? '';
$resourceId = $data['resource_id'] ?? '';

switch ($event) {
    case 'delivery.status_changed':
        $status = $data['status'] ?? '';
        // Atualiza o status no seu banco (RMA ou Pedidos)
        // O resourceId aqui seria o ID da entrega na Uber
        $pdo->prepare("UPDATE orders SET status = ? WHERE uber_delivery_id = ?")
            ->execute([strtolower($status), $resourceId]);
        break;

    case 'orders.notification':
        // Novo pedido vindo do Uber Eats!
        $notif->newUberOrder($resourceId);
        break;
}

// Responde 200 OK para a Uber não tentar reenviar
http_response_code(200);
echo json_encode(['status' => 'received']);
?>
