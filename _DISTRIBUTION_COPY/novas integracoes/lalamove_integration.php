<?php
require_once __DIR__ . 
'/config.php';
require_once __DIR__ . 
'/includes/db.php';

// Configurações do Lalamove
define('LALAMOVE_API_URL', 'https://rest.lalamove.com'); // URL da API do Lalamove (verificar para ambiente de produção/sandbox)
define('LALAMOVE_API_KEY', 'YOUR_LALAMOVE_API_KEY'); // Substitua pela sua API Key do Lalamove
define('LALAMOVE_API_SECRET', 'YOUR_LALAMOVE_API_SECRET'); // Substitua pelo seu API Secret do Lalamove
define('LALAMOVE_COUNTRY_CODE', 'BR'); // Código do país (ex: BR para Brasil)
define('LALAMOVE_LANGUAGE', 'pt_BR'); // Idioma (ex: pt_BR)

function createLalamoveOrder($pickup_details, $dropoff_details, $items, $order_id) {
    $timestamp = time() * 1000; // Timestamp em milissegundos
    $method = 'POST';
    $path = '/v3/orders'; // Caminho da API para criar pedidos
    $body = [
        'serviceType' => 'MOTORCYCLE', // Ou outro tipo de serviço (CAR, VAN, etc.)
        'specialRequests' => [],
        'deliveries' => [
            [
                'location' => [
                    'latitude' => (string)$dropoff_details['latitude'],
                    'longitude' => (string)$dropoff_details['longitude'],
                ],
                'address' => $dropoff_details['address'],
                'customerName' => $dropoff_details['name'],
                'customerContact' => $dropoff_details['phone'],
                'remarks' => $dropoff_details['notes'],
            ],
        ],
        'stops' => [
            [
                'location' => [
                    'latitude' => (string)$pickup_details['latitude'],
                    'longitude' => (string)$pickup_details['longitude'],
                ],
                'address' => $pickup_details['address'],
                'customerName' => $pickup_details['business_name'],
                'customerContact' => $pickup_details['phone'],
            ],
        ],
        'requesterContact' => [
            'name' => $pickup_details['business_name'],
            'phone' => $pickup_details['phone'],
        ],
        'quotedTotalFee' => [
            'amount' => '0.00', // Será preenchido pela API após a cotação
            'currency' => 'BRL',
        ],
        'externalId' => $order_id,
        'items' => array_map(function($item) {
            return [
                'category' => 'GOODS', // Ou outro tipo de categoria
                'quantity' => $item['quantity'],
                'weight' => $item['weight'],
                'remarks' => $item['name'],
            ];
        }, $items),
    ];

    // Gerar assinatura (HMAC SHA256)
    $raw_signature = $timestamp . '\r\n' . $method . '\r\n' . $path . '\r\n' . json_encode($body);
    $signature = hash_hmac('sha256', $raw_signature, LALAMOVE_API_SECRET);

    $headers = [
        'Content-Type: application/json',
        'X-Request-ID: ' . uniqid(),
        'X-Caller-ID: ' . LALAMOVE_API_KEY,
        'X-Request-Timestamp: ' . $timestamp,
        'X-Signature: ' . $signature,
        'Market: ' . LALAMOVE_COUNTRY_CODE,
        'Accept-Language: ' . LALAMOVE_LANGUAGE,
    ];

    $ch = curl_init(LALAMOVE_API_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        return json_decode($response, true);
    } else {
        throw new Exception("Erro ao criar pedido Lalamove: " . $response);
    }
}

// Exemplo de uso (você integraria isso no seu fluxo de pedido)
if (isset($_GET["action"]) && $_GET["action"] === "create_lalamove_order") {
    $order_id = "LALA_" . uniqid();

    $pickup_details = [
        'address' => 'Rua da Empresa, 456, São Paulo - SP',
        'latitude' => -23.550520,
        'longitude' => -46.633308,
        'phone' => '+5511998877665',
        'business_name' => 'Fight Arcade',
    ];

    $dropoff_details = [
        'address' => 'Rua do Cliente, 789, São Paulo - SP',
        'latitude' => -23.561353,
        'longitude' => -46.656000,
        'name' => 'Cliente Lalamove',
        'phone' => '+5511911223344',
        'notes' => 'Entregar para a Sra. Maria.',
    ];

    $items_to_deliver = [
        [
            'name' => 'Joystick Arcade',
            'quantity' => 1,
            'weight' => 3.0, // kg
        ],
    ];

    try {
        $lalamove_response = createLalamoveOrder($pickup_details, $dropoff_details, $items_to_deliver, $order_id);
        echo "<pre>";
        print_r($lalamove_response);
        echo "</pre>";
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
}

?>

<!-- Exemplo de link para criar um pedido Lalamove -->
<a href="?action=create_lalamove_order" class="btn">Solicitar Entrega Lalamove</a>
