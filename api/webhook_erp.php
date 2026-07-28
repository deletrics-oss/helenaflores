<?php
// api/webhook_erp.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Log incoming webhook for debugging
$input = file_get_contents('php://input');
$logFile = __DIR__ . '/../webhook_erp.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Recebido: " . $input . "\n", FILE_APPEND);

// Basic Logic to update order status if ERP sends it back
// Example payload structure varies by ERP (Bling vs Tiny)
// This is a placeholder to confirm receipt.
$data = json_decode($input, true);

if ($data) {
    // Logic to update orders table would go here
    // e.g. if ($data['status'] == 'faturado') UPDATE orders SET status = 'paid' ...
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Payload']);
}
?>