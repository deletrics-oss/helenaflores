<?php

// ---------------------------------------------------------
// MÓDULO DE CÁLCULO DE FRETE (Com Integração Real Melhor Envio)
// ---------------------------------------------------------

/**
 * Calcula o preço do frete baseado no CEP e peso/dimensões
 */
function calculateShippingOptions($zipCode, $items)
{
    global $pdo; // Precisa da conexão para pegar o token

    // 1. Total Weight & Dimensions Calculation
    // 1. Smart Packing Logic (Volume Based)
    $totalWeight = 0;
    $totalVolume = 0;

    $maxL = 0;
    $maxW = 0; // We will use length and width as base
    $totalValue = 0;

    foreach ($items as $item) {
        $qty = $item['qty'];
        // Use default values if product data is missing
        $weight = $item['weight_kg'] ?? 0.5;
        $l = $item['length_cm'] ?? 20;
        $w_dim = $item['width_cm'] ?? 15;
        $h = $item['height_cm'] ?? 10;

        // Normalize dimensions (Sort L > W > H for better stacking sim)
        $dims = [$l, $w_dim, $h];
        rsort($dims); // Descending order
        $l = $dims[0];
        $w_dim = $dims[1];
        $h = $dims[2];

        // Price Select
        $price = (isset($item['price_usage'])) ? $item['price_usage'] : $item['price'];
        $totalValue += ($price * $qty);

        // Sum Weight & Volume
        $totalWeight += ($weight * $qty);
        $totalVolume += ($l * $w_dim * $h * $qty);

        // Find largest base footprint
        if ($l > $maxL)
            $maxL = $l;
        if ($w_dim > $maxW)
            $maxW = $w_dim;
    }

    // Calculate Stacked Height
    // Avoid division by zero
    if ($maxL < 16)
        $maxL = 16;
    if ($maxW < 11)
        $maxW = 11;

    $baseArea = $maxL * $maxW;
    $calculatedHeight = $totalVolume / $baseArea;

    // Limits
    if ($calculatedHeight < 2)
        $calculatedHeight = 2; // Min height

    // Final Dimensions for API
    $maxLength = $maxL;
    $maxWidth = $maxW;
    $maxHeight = ceil($calculatedHeight); // Round up cm

    // Fallback Minimums (Correios limits)
    if ($totalWeight < 0.3)
        $totalWeight = 0.3;

    $options = [];
    $zipCode = preg_replace('/\D/', '', $zipCode);

    // -------------------------------------------------------------------------
    // 2. MELHOR ENVIO INTEGRATION
    // -------------------------------------------------------------------------
    $me_token = '';
    $me_active = 0;

    // Fetch Settings
    $originZip = '03611060'; // Default Fallback (Fight Arcade Store)
    $is_sandbox = true;

    try {
        // First try system_settings (New Standard)
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'me_%'");
        $s_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($s_settings['me_access_token'])) {
            $me_token = $s_settings['me_access_token'];
            $originZip = $s_settings['me_from_zipcode'] ?? $originZip;
            $is_sandbox = ($s_settings['me_sandbox'] ?? '1') === '1';
            $me_active = 1;
        } else {
            // Fallback to older module_settings if system_settings is empty
            $stmt = $pdo->prepare("SELECT * FROM module_settings WHERE module_key = 'shipping_melhorenvio'");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($config && $config['is_active'] == 1) {
                $keys = json_decode($config['settings_json'], true);
                $me_token = $keys['token'] ?? '';
                if (!empty($keys['zip_origin']))
                    $originZip = $keys['zip_origin'];
                $is_sandbox = false; // module_settings usually points to prod
                $me_active = 1;
            }
        }
    } catch (Exception $e) { /* Ignore error */ }

    $me_url = $is_sandbox ? "https://sandbox.melhorenvio.com.br" : "https://melhorenvio.com.br";

    if ($me_active && !empty($me_token)) {

        $curl = curl_init();

        // Payload according to Melhor Envio v2 Docs
        // Clean Zips
        $originZip = preg_replace('/\D/', '', $originZip);

        $payload = [
            'from' => [
                'postal_code' => $originZip // Your Shop CEP (Tatuapé/SP example) - CHANGE THIS IF NEEDED
            ],
            'to' => [
                'postal_code' => $zipCode
            ],
            'products' => [
                [
                    'id' => 'x',
                    'width' => $maxWidth,
                    'height' => $maxHeight,
                    'length' => $maxLength,
                    'weight' => $totalWeight,
                    'insurance_value' => min((float)$totalValue, 1500.00),
                    'quantity' => 1 // Bundle as one package for better rates
                ]
            ]
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $me_url . "/api/v2/me/shipment/calculate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10, // Short timeout to not block UI
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Authorization: Bearer " . $me_token,
                "Content-Type: application/json",
                "User-Agent: FightArcade/1.0 (contato@fightarcade.com.br)"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $json = json_decode($response, true);

        // DEBUG LOGGING (Temporary Force)
        file_put_contents(__DIR__ . '/../debug_me.log', date('Y-m-d H:i:s') . " - Code: $httpCode - Resp: " . $response . "\n", FILE_APPEND);

        if ($httpCode !== 200 || empty($json)) {
            // Already logged above
        }

        if ($httpCode >= 200 && $httpCode < 300 && is_array($json)) {
            // Filter services: SEDEX, PAC, Jadlog, Azul, etc.
            foreach ($json as $rate) {
                if (isset($rate['price']) && isset($rate['currency']) && !isset($rate['error'])) {
                    $options[] = [
                        'id' => 'me_' . $rate['id'],
                        'name' => $rate['company']['name'] . ' ' . $rate['name'] . ' (via Melhor Envio)', // Ex: Jadlog .Package (via Melhor Envio)
                        'price' => floatval($rate['price']),
                        'days' => intval($rate['delivery_time']),
                        'icon' => getIcon($rate['company']['name'])
                    ];
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // 3. LALAMOVE INTEGRATION (Express Delivery for SP/RJ)
    // -------------------------------------------------------------------------
    $llm_active = 0;
    try {
        $stmtLLM = $pdo->prepare("SELECT * FROM system_settings WHERE setting_key = 'llm_api_key'");
        $stmtLLM->execute();
        if ($stmtLLM->fetch()) {
            $llm_active = 1;
        }
    } catch (Exception $e) {}

    if ($llm_active) {
        require_once __DIR__ . '/lalamove.php';
        $llm = new LalamoveAPI($pdo);
        if ($llm->isConfigured()) {
            // Geocode receiver ZIP
            $coords = $llm->geocodeByCep($zipCode);
            if ($coords && !isset($coords['error'])) {
                $llm_quotes = $llm->getAllQuotations($coords, "CEP " . $zipCode);
                foreach ($llm_quotes as $q) {
                    if (!isset($q['error'])) {
                        $options[] = [
                            'id' => 'lalamove_' . $q['serviceType'],
                            'name' => 'Lalamove: ' . $q['serviceType'] . ' (Entrega Expressa)',
                            'price' => floatval($q['total']),
                            'days' => 1, // Same day
                            'icon' => '🛵'
                        ];
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // 4. FALLBACK / SIMULAÇÃO (Se nada retornou até agora)
    // -------------------------------------------------------------------------
    if (empty($options)) {
        // Preço Base Simulado
        $basePrice = 20.00 + ($totalWeight * 2.0);

        $options[] = [
            'id' => 'pac_sim',
            'name' => 'PAC (Normal)',
            'price' => $basePrice,
            'days' => 8,
            'icon' => '📦'
        ];
        $options[] = [
            'id' => 'sedex_sim',
            'name' => 'SEDEX (Expresso)',
            'price' => $basePrice * 1.6,
            'days' => 3,
            'icon' => '🚀'
        ];
    }

    // -------------------------------------------------------------------------
    // 5. MOTOBOY / ENTREGA PRÓPRIA (Zonas Fixas)
    // -------------------------------------------------------------------------
    try {
        $stmtMB = $pdo->prepare("SELECT * FROM module_settings WHERE module_key = 'shipping_motoboy'");
        $stmtMB->execute();
        $confMB = $stmtMB->fetch(PDO::FETCH_ASSOC);

        if ($confMB && $confMB['is_active'] == 1) {
            $mbKeys = json_decode($confMB['settings_json'], true);
            $zones = $mbKeys['zones'] ?? [];
            $userPrefix = substr($zipCode, 0, 5);

            foreach ($zones as $zone) {
                $zName = $zone['name'] ?? '';
                $zStart = $zone['zip_start'] ?? '';
                $zEnd = $zone['zip_end'] ?? '';
                $zPrice = floatval($zone['price'] ?? 0);

                if (!empty($zName) && !empty($zStart) && !empty($zEnd)) {
                    if ($userPrefix >= $zStart && $userPrefix <= $zEnd) {
                        $options[] = [
                            'id' => 'motoboy_' . md5($zName),
                            'name' => 'Entrega Local: ' . $zName,
                            'price' => $zPrice,
                            'days' => 1,
                            'icon' => '🏍️'
                        ];
                        break;
                    }
                }
            }
        }
    } catch (Exception $e) {}

    // -------------------------------------------------------------------------
    // 4. RETIRADA (Sempre disponível)
    // -------------------------------------------------------------------------
    $options[] = [
        'id' => 'pickup',
        'name' => 'Retirar na Loja (Tatuapé)',
        'price' => 0.00,
        'days' => 0,
        'icon' => '🏪'
    ];

    return $options;
}

function getIcon($name)
{
    if (stripos($name, 'Correios') !== false)
        return '📦';
    if (stripos($name, 'Jadlog') !== false)
        return '🚛';
    if (stripos($name, 'Azul') !== false)
        return '✈️';
    if (stripos($name, 'Latam') !== false)
        return '✈️';
    if (stripos($name, 'Loggi') !== false)
        return '🛵';
    return '🚚';
}
?>