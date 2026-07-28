<?php
// api/webhook_mercadopago.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Mercado Pago Webhook receives notification ID or type/data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// MP notifications can be IPN or Webhook
$id = $data['data']['id'] ?? $data['id'] ?? $_GET['id'] ?? null;
$type = $data['type'] ?? $data['topic'] ?? null; // 'payment' or 'merchant_order'

if (!$id || ($type !== 'payment' && $type !== 'merchant_order' && !isset($_GET['topic']))) {
    http_response_code(400);
    echo "ID ou Tipo inválido";
    exit;
}

// 1. Get Access Token from Settings
$stmtMod = $pdo->prepare("SELECT * FROM module_settings WHERE module_key = 'payment_mercadopago'");
$stmtMod->execute();
$modMP = $stmtMod->fetch(PDO::FETCH_ASSOC);

if (!$modMP || $modMP['is_active'] == 0) {
    http_response_code(500);
    echo "Módulo Mercado Pago inativo";
    file_put_contents('debug_mp_webhook.log', date('Y-m-d H:i:s') . " - MP Module Inactive\n", FILE_APPEND);
    exit;
}

$keys = json_decode($modMP['settings_json'], true);
$accessToken = $keys['access_token'] ?? '';

if (empty($accessToken)) {
    http_response_code(500);
    echo "Access Token ausente";
    exit;
}

// 2. Query MP for detail
// For simplicity, we mostly care about 'payment' notifications
$paymentId = $id;
if ($type === 'merchant_order') {
    // If it's a merchant order, we could fetch details to find payments, 
    // but usually MP sends 'payment' notifications separately.
}

$url = "https://api.mercadopago.com/v1/payments/$paymentId";
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken"]
]);
$response = curl_exec($curl);
$payData = json_decode($response, true);
curl_close($curl);

if (isset($payData['status'])) {
    $orderId = (int) $payData['external_reference'];
    $mpStatus = $payData['status']; // 'approved', 'pending', 'in_process', 'rejected', 'cancelled'

    file_put_contents('debug_mp_webhook.log', date('Y-m-d H:i:s') . " - Order #$orderId status: $mpStatus (MP ID: $paymentId)\n", FILE_APPEND);

    if ($orderId > 0) {
        // Find current status to avoid redundant triggers
        $checkStmt = $pdo->prepare("SELECT status, user_id FROM orders WHERE id = ?");
        $checkStmt->execute([$orderId]);
        $order = $checkStmt->fetch();

        if ($order) {
            $newStatus = null;
            if ($mpStatus === 'approved') {
                $newStatus = 'paid';
            } elseif ($mpStatus === 'rejected') {
                // $newStatus = 'canceled'; // Optional: Be careful with auto-cancel
            }

            if ($newStatus && $order['status'] !== $newStatus) {
                // Update Order
                $upd = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$newStatus, $orderId]);

                // --- SEND EMAIL NOTIFICATION ---
                if ($newStatus === 'paid') {
                    $uStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $uStmt->execute([$order['user_id']]);
                    $user = $uStmt->fetch();

                    if ($user && !empty($user['email'])) {
                        $title = "Pagamento Confirmado! Pedido #$orderId ✅";
                        $body = "Olá {$user['name']},\n\nRecebemos a confirmação de pagamento do seu pedido #$orderId.\n\nJá estamos preparando seu pedido para o envio! Você receberá uma nova notificação com o código de rastreio em breve.\n\nObrigado por comprar na Fight Arcade!";
                        $headers = "From: contato@fightarcade.com.br\r\nContent-Type: text/plain; charset=UTF-8";
                        @mail($user['email'], $title, $body, $headers);
                    }
                }
            }
        }
    }
}

// Respond 200/201 to MP
http_response_code(200);
echo "OK";
