<?php
require_once __DIR__ . 
'/config.php';
require_once __DIR__ . 
'/includes/db.php';

// Configurações do NuPay
define('NUPAY_API_URL', 'https://sandbox-api.spinpay.com.br/v1/checkouts/payments'); // URL da API do NuPay (sandbox)
define('NUPAY_MERCHANT_API_KEY', 'YOUR_MERCHANT_API_KEY'); // Substitua pela sua Merchant API Key
define('NUPAY_MERCHANT_API_TOKEN', 'YOUR_MERCHANT_API_TOKEN'); // Substitua pelo seu Merchant API Token

function createNuPayPayment($order_data, $items, $shopper_data, $return_url, $cancel_url, $callback_url) {
    $payload = [
        'merchantOrderReference' => $order_data['order_reference'],
        'referenceId' => $order_data['payment_reference_id'],
        'amount' => [
            'value' => (float)$order_data['total_amount'],
            'currency' => 'BRL',
        ],
        'shopper' => [
            'reference' => $shopper_data['shopper_reference'],
            'firstName' => $shopper_data['first_name'],
            'lastName' => $shopper_data['last_name'],
            'document' => $shopper_data['document'],
            'documentType' => $shopper_data['document_type'],
            'email' => $shopper_data['email'],
        ],
        'items' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unitPrice' => (float)$item['unit_price'],
            ];
        }, $items),
        'paymentMethod' => [
            'type' => 'nupay',
            'authorizationType' => 'manually_authorized', // Ou 'otp_authorized' se usar OTP
        ],
        'paymentFlow' => [
            'returnUrl' => $return_url,
            'cancelUrl' => $cancel_url,
        ],
        'merchantName' => 'Fight Arcade',
        'storeName' => 'Fight Arcade Online',
        'callbackUrl' => $callback_url,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . NUPAY_MERCHANT_API_TOKEN, // Para pagamentos pré-autorizados
        'X-Merchant-Api-Key: ' . NUPAY_MERCHANT_API_KEY,
    ];

    $ch = curl_init(NUPAY_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return json_decode($response, true);
    } else {
        throw new Exception("Erro ao criar pagamento NuPay: " . $response);
    }
}

// Exemplo de uso (você integraria isso no seu fluxo de checkout)
if (isset($_GET["action"]) && $_GET["action"] === "checkout_nupay") {
    // Simular dados do pedido
    $order_data = [
        'order_reference' => 'ORDER_' . uniqid(),
        'payment_reference_id' => uniqid(),
        'total_amount' => 150.00,
    ];

    // Simular itens do carrinho
    $cart_items = [
        ['name' => 'Produto A', 'quantity' => 1, 'unit_price' => 100.00],
        ['name' => 'Produto B', 'quantity' => 1, 'unit_price' => 50.00],
    ];

    // Simular dados do comprador
    $shopper_data = [
        'shopper_reference' => 'USER_' . uniqid(),
        'first_name' => 'João',
        'last_name' => 'Silva',
        'document' => '12345678900', // CPF do comprador
        'document_type' => 'CPF',
        'email' => 'joao.silva@example.com',
    ];

    $return_url = BASE_URL . "/nupay_success.php"; // URL de sucesso após o pagamento NuPay
    $cancel_url = BASE_URL . "/nupay_cancel.php"; // URL de cancelamento do pagamento NuPay
    $callback_url = BASE_URL . "/nupay_webhook.php"; // URL para notificações de status NuPay

    try {
        $payment_response = createNuPayPayment($order_data, $cart_items, $shopper_data, $return_url, $cancel_url, $callback_url);
        // Redirecionar o usuário para a URL de pagamento do NuPay
        if (isset($payment_response['paymentUrl'])) {
            header("Location: " . $payment_response['paymentUrl']);
            exit();
        } else {
            echo "Erro: URL de pagamento NuPay não recebida.";
        }
    } catch (Exception $e) {
        echo "Erro ao criar pagamento NuPay: " . $e->getMessage();
    }
}

// Webhook para receber notificações do NuPay
// Crie um arquivo nupay_webhook.php para lidar com isso

?>

<!-- Exemplo de botão de checkout no seu HTML -->
<a href="?action=checkout_nupay" class="btn">Pagar com NuPay</a>
