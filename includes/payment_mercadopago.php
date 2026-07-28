<?php

function createMercadoPagoPreference($accessToken, $orderId, $items, $payerInfo, $shippingCost)
{
    if (empty($accessToken))
        return null;

    $curl = curl_init();

    // 1. Prepare Items
    $mpItems = [];
    foreach ($items as $item) {
        $mpItems[] = [
            'id' => $item['id'] ?? 'PROD-' . rand(1, 999),
            'title' => $item['name'],
            'description' => 'Produto da Loja',
            'picture_url' => '', // Could add image URL if available
            'category_id' => 'others',
            'quantity' => intval($item['qty']),
            'currency_id' => 'BRL',
            'unit_price' => floatval($item['price'])
        ];
    }

    // Add Shipping as an Item (Simpler for MP than 'shipments' object sometimes)
    if ($shippingCost > 0) {
        $mpItems[] = [
            'id' => 'SHIP',
            'title' => 'Frete e Envio',
            'quantity' => 1,
            'currency_id' => 'BRL',
            'unit_price' => floatval($shippingCost)
        ];
    }

    // 2. Prepare Payer
    $payer = [
        'name' => $payerInfo['name'] ?? 'Cliente',
        'surname' => '',
        'email' => $payerInfo['email'] ?? 'email@test.com',
        'phone' => [
            'area_code' => '',
            'number' => $payerInfo['phone'] ?? ''
        ],
        'identification' => [
            'type' => 'CPF',
            'number' => $payerInfo['document'] ?? ''
        ]
    ];

    // 3. Payload
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $fullBaseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . BASE_URL;

    $preferenceData = [
        'items' => $mpItems,
        'payer' => $payer,
        'external_reference' => strval($orderId),
        'back_urls' => [
            'success' => $fullBaseUrl . "/order-success.php?id=$orderId&status=success",
            'failure' => $fullBaseUrl . "/checkout_payment.php?error=payment_failed",
            'pending' => $fullBaseUrl . "/order-success.php?id=$orderId&status=pending"
        ],
        'notification_url' => $fullBaseUrl . "/api/webhook_mercadopago.php",
        'auto_return' => 'approved',
        'payment_methods' => [
            'excluded_payment_types' => [
                ['id' => 'ticket'] // Optional: Disable boleto if wanted, or leave it.
            ],
            'installments' => 12
        ],
        'statement_descriptor' => 'FIGHTARCADE'
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadopago.com/checkout/preferences",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($preferenceData),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $accessToken
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        file_put_contents(__DIR__ . '/../debug_mp.log', date('Y-m-d H:i:s') . " - Curl Error: " . $err . "\n", FILE_APPEND);
        return null;
    }

    $data = json_decode($response, true);

    // Log API Response if error or init_point missing
    if (!isset($data['init_point'])) {
        $errorMsg = $data['message'] ?? 'Erro desconhecido na API MP';
        file_put_contents(__DIR__ . '/../debug_mp.log', date('Y-m-d H:i:s') . " - API Error: " . $response . "\n", FILE_APPEND);
        return "ERROR: " . $errorMsg; // Return error string to show user
    }

    return $data['init_point'] ?? null; // 'init_point' is the checkout URL
}
?>