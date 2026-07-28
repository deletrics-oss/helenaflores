<?php
// re-pay.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/payment_mercadopago.php';

session_start();

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$orderId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// 1. Fetch Order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'pending'");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    die("Pedido não encontrado ou já processado.");
}

// 2. Fetch Items
$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItems->execute([$orderId]);
$dbItems = $stmtItems->fetchAll();

$items = [];
foreach ($dbItems as $i) {
    $items[] = [
        'id' => $i['product_id'],
        'name' => $i['product_name'],
        'qty' => $i['quantity'],
        'price' => $i['unit_price']
    ];
}

// 3. Fetch Shipping Cost
// The shipping_method column has "Name (R$ Cost)" or similar. 
// We need to extract the cost if we want to add it as an item like in preference creation.
// Actually, we can just pass the total if needed, but the createMercadoPagoPreference takes items and shippingCost separately.
// Let's see how it was saved in checkout_payment.php: ':ship' => "$ship_name (R$ $ship_cost)"

$shippingCost = 0;
if (preg_match('/R\$ ([\d,.]+)/', $order['shipping_method'], $matches)) {
    $shippingCost = (float) str_replace(',', '.', $matches[1]);
}

// 4. Get MP Config
$stmtMod = $pdo->prepare("SELECT * FROM module_settings WHERE module_key = 'payment_mercadopago'");
$stmtMod->execute();
$modMP = $stmtMod->fetch(PDO::FETCH_ASSOC);

if (!$modMP || $modMP['is_active'] == 0) {
    die("O método de pagamento Mercado Pago não está disponível no momento.");
}

$keys = json_decode($modMP['settings_json'], true);
$accessToken = $keys['access_token'] ?? '';

// 5. Payer Info from User Table (latest info)
$stmtU = $pdo->prepare("SELECT name, email, phone, document FROM users WHERE id = ?");
$stmtU->execute([$userId]);
$uData = $stmtU->fetch();

$payerInfo = $uData ?: [];

// 6. Create Preference
$mpUrl = createMercadoPagoPreference($accessToken, $orderId, $items, $payerInfo, $shippingCost);

if ($mpUrl && strpos($mpUrl, 'ERROR:') === 0) {
    die("Erro ao conectar com Mercado Pago: " . htmlspecialchars(str_replace('ERROR:', '', $mpUrl)));
}

if ($mpUrl) {
    header("Location: " . $mpUrl);
} else {
    die("Não foi possível gerar o link de pagamento. Tente novamente mais tarde.");
}
exit;
