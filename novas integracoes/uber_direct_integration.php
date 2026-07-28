<?php
require_once __DIR__ . 
'/config.php';
require_once __DIR__ . 
'/includes/db.php';

// Configurações do Uber Direct
define('UBER_DIRECT_API_URL', 'https://api.uber.com/v1/deliveries'); // URL da API do Uber Direct
define('UBER_DIRECT_TOKEN', 'YOUR_UBER_DIRECT_ACCESS_TOKEN'); // Substitua pelo seu Access Token do Uber Direct

function createUberDirectDelivery($pickup_details, $dropoff_details, $package_details, $delivery_id) {
    $payload = [
        'external_delivery_id' => $delivery_id,
        'pickup_address' => $pickup_details['address'],
        'pickup_lat' => $pickup_details['latitude'],
        'pickup_lng' => $pickup_details['longitude'],
        'pickup_phone_number' => $pickup_details['phone'],
        'pickup_business_name' => $pickup_details['business_name'],
        'dropoff_address' => $dropoff_details['address'],
        'dropoff_lat' => $dropoff_details['latitude'],
        'dropoff_lng' => $dropoff_details['longitude'],
        'dropoff_phone_number' => $dropoff_details['phone'],
        'dropoff_name' => $dropoff_details['name'],
        'dropoff_notes' => $dropoff_details['notes'],
        'manifest_items' => [
            [
                'name' => $package_details['item_name'],
                'quantity' => $package_details['quantity'],
                'price' => $package_details['price'],
                'weight' => $package_details['weight'],
            ]
        ],
        'undeliverable_action' => 'return_to_sender', // ou 'leave_at_door'
        'requires_signature' => false,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . UBER_DIRECT_TOKEN,
        'Accept-Language: pt-BR',
    ];

    $ch = curl_init(UBER_DIRECT_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        return json_decode($response, true);
    } else {
        throw new Exception("Erro ao criar entrega Uber Direct: " . $response);
    }
}

// Exemplo de uso (você integraria isso no seu fluxo de pedido)
if (isset($_GET["action"]) && $_GET["action"] === "create_uber_delivery") {
    $delivery_id = "UBER_" . uniqid();

    $pickup_details = [
        'address' => 'Rua Exemplo, 123, São Paulo - SP',
        'latitude' => -23.550520,
        'longitude' => -46.633308,
        'phone' => '+5511987654321',
        'business_name' => 'Fight Arcade',
    ];

    $dropoff_details = [
        'address' => 'Avenida Paulista, 1000, São Paulo - SP',
        'latitude' => -23.561353,
        'longitude' => -46.656000,
        'phone' => '+5511912345678',
        'name' => 'Cliente Teste',
        'notes' => 'Deixar na portaria.',
    ];

    $package_details = [
        'item_name' => 'Controle Arcade',
        'quantity' => 1,
        'price' => 250.00,
        'weight' => 2.5, // kg
    ];

    try {
        $delivery_response = createUberDirectDelivery($pickup_details, $dropoff_details, $package_details, $delivery_id);
        echo "<pre>";
        print_r($delivery_response);
        echo "</pre>";
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
}

?>

<!-- Exemplo de link para criar uma entrega Uber Direct -->
<a href="?action=create_uber_delivery" class="btn">Solicitar Entrega Uber Direct</a>
