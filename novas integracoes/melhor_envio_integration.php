<?php
require_once __DIR__ . 
'/config.php';
require_once __DIR__ . 
'/includes/db.php';

// Configurações do Melhor Envio
define('MELHOR_ENVIO_API_URL', 'https://www.melhorenvio.com.br/api/v2/me/shipment/calculate'); // URL da API de cálculo de frete
define('MELHOR_ENVIO_SANDBOX_API_URL', 'https://sandbox.melhorenvio.com.br/api/v2/me/shipment/calculate'); // URL da API de cálculo de frete (sandbox)
define('MELHOR_ENVIO_TOKEN', 'YOUR_MELHOR_ENVIO_TOKEN'); // Substitua pelo seu token de acesso do Melhor Envio
define('MELHOR_ENVIO_USER_AGENT', 'Fight Arcade (seu_email@exemplo.com)'); // Substitua pelo nome da sua aplicação e seu email

function calculateMelhorEnvioShipping($from_zipcode, $to_zipcode, $products, $options = []) {
    $payload = [
        'from' => [
            'postal_code' => $from_zipcode,
        ],
        'to' => [
            'postal_code' => $to_zipcode,
        ],
        'products' => array_map(function($product) {
            return [
                'id' => $product['id'],
                'width' => $product['width'],
                'height' => $product['height'],
                'length' => $product['length'],
                'weight' => $product['weight'],
                'quantity' => $product['quantity'],
            ];
        }, $products),
        'options' => $options,
    ];

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . MELHOR_ENVIO_TOKEN,
        'User-Agent: ' . MELHOR_ENVIO_USER_AGENT,
    ];

    // Use a URL de sandbox para testes e a de produção para ambiente real
    $api_url = MELHOR_ENVIO_SANDBOX_API_URL; // Altere para MELHOR_ENVIO_API_URL em produção

    $ch = curl_init($api_url);
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
        throw new Exception("Erro ao calcular frete Melhor Envio: " . $response);
    }
}

// Exemplo de uso
if (isset($_GET["action"]) && $_GET["action"] === "calculate_shipping") {
    $from_zipcode = "01001000"; // CEP de origem (exemplo)
    $to_zipcode = "04547000";   // CEP de destino (exemplo)

    // Simular produtos com dimensões e peso
    $products_to_ship = [
        [
            'id' => 1,
            'width' => 15, // cm
            'height' => 10, // cm
            'length' => 20, // cm
            'weight' => 1.5, // kg
            'quantity' => 1,
        ],
        [
            'id' => 2,
            'width' => 20,
            'height' => 15,
            'length' => 25,
            'weight' => 2.0,
            'quantity' => 2,
        ],
    ];

    try {
        $shipping_options = calculateMelhorEnvioShipping($from_zipcode, $to_zipcode, $products_to_ship);
        echo "<pre>";
        print_r($shipping_options);
        echo "</pre>";
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
}

?>

<!-- Exemplo de formulário para cálculo de frete -->
<form action="?action=calculate_shipping" method="GET">
    <label for="from_zip">CEP Origem:</label>
    <input type="text" id="from_zip" name="from_zip" value="01001000"><br><br>
    <label for="to_zip">CEP Destino:</label>
    <input type="text" id="to_zip" name="to_zip" value="04547000"><br><br>
    <button type="submit">Calcular Frete Melhor Envio</button>
</form>
