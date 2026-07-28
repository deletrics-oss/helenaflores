<?php
require_once __DIR__ . 
'/config.php';
require_once __DIR__ . 
'/includes/db.php';
require_once __DIR__ . 
'/vendor/autoload.php'; // Certifique-se de ter o SDK do Mercado Pago via Composer

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

// Configurações do Mercado Pago
MercadoPagoConfig::setAccessToken("YOUR_ACCESS_TOKEN"); // Substitua pelo seu Access Token

function createMercadoPagoPreference($items, $payer_email, $order_id) {
    $client = new PreferenceClient();

    $preference = $client->create([
        "items" => array_map(function($item) {
            return [
                "title" => $item["title"],
                "quantity" => $item["quantity"],
                "unit_price" => (float)$item["unit_price"],
            ];
        }, $items),
        "payer" => [
            "email" => $payer_email,
        ],
        "back_urls" => [
            "success" => BASE_URL . "/success.php", // URL de sucesso após o pagamento
            "failure" => BASE_URL . "/failure.php", // URL de falha no pagamento
            "pending" => BASE_URL . "/pending.php", // URL de pagamento pendente
        ],
        "auto_return" => "approved",
        "external_reference" => $order_id, // Referência externa para o seu pedido
        "notification_url" => BASE_URL . "/mercadopago_webhook.php", // URL para notificações de status
    ]);

    return $preference;
}

// Exemplo de uso (você integraria isso no seu fluxo de checkout)
if (isset($_GET["action"]) && $_GET["action"] === "checkout_mp") {
    // Simular itens do carrinho
    $cart_items = [
        ["title" => "Produto A", "quantity" => 1, "unit_price" => 100.00],
        ["title" => "Produto B", "quantity" => 2, "unit_price" => 50.00],
    ];
    $user_email = "test@example.com"; // Email do usuário logado
    $current_order_id = "ORDER_" . uniqid(); // Gerar um ID de pedido único

    try {
        $preference = createMercadoPagoPreference($cart_items, $user_email, $current_order_id);
        // Redirecionar o usuário para o Checkout Pro do Mercado Pago
        header("Location: " . $preference->init_point);
        exit();
    } catch (Exception $e) {
        echo "Erro ao criar preferência de pagamento: " . $e->getMessage();
    }
}

// Webhook para receber notificações do Mercado Pago
// Crie um arquivo mercadopago_webhook.php para lidar com isso

?>

<!-- Exemplo de botão de checkout no seu HTML -->
<a href="?action=checkout_mp" class="btn">Pagar com Mercado Pago</a>
