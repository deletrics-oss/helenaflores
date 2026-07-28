<?php

// ---------------------------------------------------------
// MÓDULO DE CÁLCULO DE FRETE HELENA FLORES (Lalamove & Melhor Envio)
// ---------------------------------------------------------

/**
 * Calcula o preço do frete baseado no CEP e peso/dimensões para Helena Flores
 */
function calculateShippingOptions($zipCode, $items)
{
    global $pdo;

    $totalWeight = 0;
    $totalValue = 0;

    foreach ($items as $item) {
        $qty = $item['qty'] ?? 1;
        $weight = $item['weight_kg'] ?? 0.5;
        $price = $item['price'] ?? 100;
        $totalWeight += ($weight * $qty);
        $totalValue += ($price * $qty);
    }

    $zipCode = preg_replace('/\D/', '', $zipCode);
    $options = [];

    // 1. LALAMOVE ENTREGA EXPRESSA MOTOBOY (Jardins & São Paulo)
    // Se o CEP for da grande SP (começa com 01 a 09)
    $zipPrefix = substr($zipCode, 0, 2);
    if (in_array($zipPrefix, ['01', '02', '03', '04', '05', '06', '07', '08', '09'])) {
        $options[] = [
            'id' => 'lalamove_motoboy',
            'name' => 'Lalamove Express Motoboy (Mesmo Dia SP & Jardins)',
            'price' => 22.90,
            'days' => 0,
            'icon' => '🛵'
        ];
        $options[] = [
            'id' => 'lalamove_carro',
            'name' => 'Lalamove Carro (Cestas & Arranjos Grandes SP)',
            'price' => 34.90,
            'days' => 0,
            'icon' => '🚗'
        ];
    }

    // 2. MELHOR ENVIO (SEDEX / PAC / JADLOG)
    $me_token = '';
    $me_active = 0;
    $originZip = '01420001'; // Helena Flores Jardins CEP (Alameda Jaú, 1777)

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'me_%'");
        $s_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($s_settings['me_access_token'])) {
            $me_token = $s_settings['me_access_token'];
            $originZip = $s_settings['me_from_zipcode'] ?? $originZip;
            $me_active = 1;
        }
    } catch (Exception $e) {}

    if ($me_active && !empty($me_token)) {
        $curl = curl_init();
        $payload = [
            'from' => ['postal_code' => preg_replace('/\D/', '', $originZip)],
            'to' => ['postal_code' => $zipCode],
            'products' => [
                [
                    'id' => 'florex',
                    'width' => 20,
                    'height' => 15,
                    'length' => 20,
                    'weight' => max($totalWeight, 0.5),
                    'insurance_value' => min((float)$totalValue, 1000.00),
                    'quantity' => 1
                ]
            ]
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://melhorenvio.com.br/api/v2/me/shipment/calculate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Authorization: Bearer " . $me_token,
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && is_array($json)) {
            foreach ($json as $rate) {
                if (isset($rate['price']) && !isset($rate['error'])) {
                    $options[] = [
                        'id' => 'me_' . $rate['id'],
                        'name' => 'Melhor Envio: ' . $rate['company']['name'] . ' ' . $rate['name'],
                        'price' => floatval($rate['price']),
                        'days' => intval($rate['delivery_time']),
                        'icon' => getIcon($rate['company']['name'])
                    ];
                }
            }
        }
    }

    // 3. FALLBACK CORREIOS
    if (empty($options)) {
        $basePrice = 18.50 + ($totalWeight * 2);
        $options[] = [
            'id' => 'sedex_exp',
            'name' => 'SEDEX Expresso (Correios)',
            'price' => round($basePrice * 1.5, 2),
            'days' => 2,
            'icon' => '🚀'
        ];
        $options[] = [
            'id' => 'pac_econ',
            'name' => 'PAC Econômico (Correios)',
            'price' => round($basePrice, 2),
            'days' => 5,
            'icon' => '📦'
        ];
    }

    // 4. RETIRADA NO ATELIER (Jardins SP)
    $options[] = [
        'id' => 'pickup',
        'name' => 'Retirar no Atelier Helena Flores (Alameda Jaú, 1777 - Jardins)',
        'price' => 0.00,
        'days' => 0,
        'icon' => '🌸'
    ];

    return $options;
}

function getIcon($name)
{
    if (stripos($name, 'Correios') !== false) return '📦';
    if (stripos($name, 'Jadlog') !== false) return '🚛';
    if (stripos($name, 'Loggi') !== false) return '🛵';
    if (stripos($name, 'Lalamove') !== false) return '🛵';
    return '🚚';
}
?>