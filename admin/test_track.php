<?php
require_once 'includes/db.php';
require_once 'includes/melhorenvio.php';

$me = new MelhorEnvioAPI($pdo);
// We don't have the order ID, so let's try to track by code if possible
// The API v2 tracking endpoint usually expects ME Order IDs.
// If we pass a tracking code, it might fail.

$code = 'LGI-ME2627Q0MR7BR';
// Let's try to find an order with this tracking code in the DB first to get the ME ID
$stmt = $pdo->prepare("SELECT me_order_id FROM rma_tickets WHERE tracking_code = ?");
$stmt->execute([$code]);
$meId = $stmt->fetchColumn();

if ($meId) {
    $res = $me->tracking([$meId]);
    header('Content-Type: application/json');
    echo json_encode($res, JSON_PRETTY_PRINT);
} else {
    echo "ME Order ID not found for code $code";
}
