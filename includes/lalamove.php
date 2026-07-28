<?php
/**
 * Lalamove API Client v3 - Fight Arcade
 * Entrega expressa intra-cidade (São Paulo / Rio de Janeiro)
 *
 * Docs:    https://developers.lalamove.com/
 * Sandbox: https://rest.sandbox.lalamove.com/v3
 * Prod:    https://rest.lalamove.com/v3
 *
 * Auth: HMAC-SHA256
 * Header: Authorization: hmac {apiKey}:{timestamp}:{signature}
 * Signature = HMAC-SHA256( timestamp\r\nMETHOD\r\n/path\r\n\r\nbody , secret )
 * Last Updated: 2026-05-06 20:36
 */

class LalamoveAPI {
    private $pdo;
    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    private $market = 'BR_SAO'; // São Paulo market code
    private $language = 'pt_BR';
    private $isSandbox;

    // Coordenadas da loja (remetente fixo - Daniel Souza, SP)
    private $storeLatLng  = ['lat' => '-23.543598', 'lng' => '-46.574902']; // Tatuapé, SP
    private $storeAddress = 'Rua Cristiano Osorio, 143, Vila Esperança, São Paulo, SP';
    private $storeName    = 'Daniel Souza';
    private $storePhone   = '+5511999999999';

    // Veículos disponíveis no Brasil com labels amigáveis (v3)
    const SERVICE_TYPES = [
        'LALAGO_POOL' => '📦 Agrupado (Econômico)',
        'LALAGO'      => '🏍️ Moto (até 10kg)',
        'LALAPRO'     => '🏍️ Moto Pro (Prioritário)',
        'HATCHBACK'  => '🚗 Carro Hatch (até 50kg)',
        'CAR'        => '🚙 Carro Sedan (até 100kg)',
        'UV_FIORINO' => '🚐 Fiorino (até 500kg)',
        'VAN'        => '🚐 Van (até 500kg)',
        'TRUCK330'   => '🚛 Caminhão 1,75t (VUC)',
        'TRUCK3_5T'  => '🚛 Caminhão 3,5t',
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
    }

    private function loadConfig() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'llm_%'");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }

        $this->isSandbox = ($settings['llm_sandbox'] ?? '1') === '1';
        $this->baseUrl   = $this->isSandbox
            ? 'https://rest.sandbox.lalamove.com'
            : 'https://rest.lalamove.com';
        
        // Limpeza rigorosa das chaves (Abordagem Gemini)
        $this->apiKey    = trim($settings['llm_api_key']    ?? '');
        $this->apiSecret = trim($settings['llm_api_secret'] ?? '');

        // Market code por cidade (BR_SAO = São Paulo, BR_RIO = Rio de Janeiro)
        $this->market = $settings['llm_market'] ?? 'BR_SAO';

        // Coordenadas da loja do DB se configuradas
        $lat = $settings['llm_store_lat'] ?? '';
        $lng = $settings['llm_store_lng'] ?? '';
        if ($lat && $lng) {
            $this->storeLatLng = ['lat' => $lat, 'lng' => $lng];
        }
        if (!empty($settings['llm_store_name']))    $this->storeName    = $settings['llm_store_name'];
        if (!empty($settings['llm_store_phone']))   $this->storePhone   = $settings['llm_store_phone'];
        if (!empty($settings['llm_store_address'])) $this->storeAddress = $settings['llm_store_address'];
    }

    public function isConfigured() {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }

    public function isSandbox() {
        return $this->isSandbox;
    }

    public function getStoreLatLng() {
        return $this->storeLatLng;
    }

    public function getMarket() {
        return $this->market;
    }

    // ==================== Geocodificação ====================
    // Usa Nominatim (OpenStreetMap) — gratuito, sem chave de API

    public function geocodeAddress($address, $skipCepExtraction = false) {
        // Tenta extrair um CEP do texto (ex: 03611-060 ou 03611060) para usar a api ViaCEP que é mais precisa no BR
        if (!$skipCepExtraction && preg_match('/(?:CEP:?\s*)?(\b\d{5}-?\d{3}\b)/i', $address, $matches)) {
            $coords = $this->geocodeByCep($matches[1]);
            if ($coords) return $coords;
        }

        $query = urlencode($address . ', Brasil');
        $url   = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1&countrycodes=br";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['User-Agent: FightArcade/1.0 (deletrics@gmail.com)'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = $response ? json_decode($response, true) : null;

        // Fallback: se Nominatim falhar (muito comum com números de porta), tenta buscar só a rua
        if (empty($data[0])) {
            $cleanAddr = preg_replace('/[0-9]+/', '', $address); // Remove números
            $cleanAddr = preg_replace('/,\s*,/', ',', $cleanAddr); // Limpa virgulas duplas
            $query2 = urlencode(trim($cleanAddr) . ', Brasil');
            $url2   = "https://nominatim.openstreetmap.org/search?q={$query2}&format=json&limit=1&countrycodes=br";
            
            $ch2 = curl_init($url2);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp2 = curl_exec($ch2);
            curl_close($ch2);
            $data = $resp2 ? json_decode($resp2, true) : null;
        }

        if (!empty($data[0])) {
            return [
                'lat'          => $data[0]['lat'],
                'lng'          => $data[0]['lon'],
                'display_name' => $data[0]['display_name']
            ];
        }
        return null;
    }

    // Geocodifica a partir do CEP usando ViaCEP + Nominatim em sequência
    public function geocodeByCep($cep, $number = '', $complement = '') {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) return null;

        // Passo 1: buscar endereço completo via ViaCEP usando cURL
        $ch = curl_init("https://viacep.com.br/ws/{$cep}/json/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $viaCepRaw = curl_exec($ch);
        curl_close($ch);

        if (!$viaCepRaw) return null;
        $addr = json_decode($viaCepRaw, true);
        if (empty($addr) || isset($addr['erro'])) return null;

        $street = $addr['logradouro'] ?? '';
        $city   = $addr['localidade'] ?? '';
        $state  = $addr['uf']         ?? '';

        // Estratégia de busca em cascata:
        // 1. Endereço Completo (Rua, Número, Cidade, CEP)
        $fullAddr = trim(implode(', ', array_filter([$street, $number, $city, $state, $cep])));
        $coords = $this->geocodeAddress($fullAddr, true);

        // 2. Rua + Cidade (Se número falhar)
        if (!$coords && !empty($street)) {
            $coords = $this->geocodeAddress($street . ', ' . $city . ', ' . $state, true);
        }

        // 3. Apenas CEP + Cidade
        if (!$coords) {
            $coords = $this->geocodeAddress($cep . ', ' . $city, true);
        }

        if ($coords) {
            $coords['formatted_address'] = $fullAddr;
            $coords['city']  = $city;
            $coords['state'] = $state;
            return $coords;
        }

        return null;
    }

    // ==================== Cotação (Quotation) ====================

    public function getQuotation($toCoords, $toAddress, $serviceType = 'MOTORCYCLE', $specialRequests = [], $fromCoords = null, $fromAddress = null) {
        $path = '/v3/quotations';
        
        $originCoords = $fromCoords ?: $this->storeLatLng;
        $originAddr   = $fromAddress ?: $this->storeAddress;

        $data = [
            'serviceType' => $serviceType,
            'language'    => $this->language,
            'item' => [
                'quantity' => '1',
                'weight'   => 'LESS_THAN_5KG',
                'categories' => ['OTHER'],
                'handlingInstructions' => []
            ],
            'stops'       => [
                [
                    'coordinates' => [
                        'lat' => (string)$originCoords['lat'],
                        'lng' => (string)$originCoords['lng'],
                    ],
                    'address' => $originAddr,
                ],
                [
                    'coordinates' => [
                        'lat' => (string)$toCoords['lat'],
                        'lng' => (string)$toCoords['lng'],
                    ],
                    'address' => $toAddress,
                ],
            ],
        ];

        // Adicionar specialRequests se houver (ex: COD para pagamento no destino)
        if (!empty($specialRequests)) {
            $data['specialRequests'] = $specialRequests;
        }

        return $this->request('POST', $path, ['data' => $data]);
    }

    // Cotação em todos os veículos disponíveis (retorna array)
    public function getAllQuotations($toCoords, $toAddress, $specialRequests = [], $fromCoords = null, $fromAddress = null, $isGrouped = false) {
        $results = [];
        foreach (array_keys(self::SERVICE_TYPES) as $serviceType) {
            $res = $this->getQuotation($toCoords, $toAddress, $serviceType, $specialRequests, $fromCoords, $fromAddress);
            
            if (!isset($res['error']) && isset($res['data'])) {
                $data = $res['data'];
                $results[] = [
                    'serviceType'  => $serviceType,
                    'label'        => self::SERVICE_TYPES[$serviceType],
                    'quotationId'  => $data['quotationId'] ?? '',
                    'expiresAt'    => $data['expiresAt']   ?? '',
                    'priceBreakdown' => $data['priceBreakdown'] ?? [],
                    'total'        => $data['priceBreakdown']['total']    ?? 0,
                    'currency'     => $data['priceBreakdown']['currency'] ?? 'BRL',
                    'stops'        => $data['stops'] ?? [],
                ];
            } else {
                // Captura erro no formato {errors: [{message:...}]} ou {message:...} ou {error: ...}
                $errMsg = $res['errors'][0]['message'] 
                        ?? $res['message'] 
                        ?? (is_string($res['error'] ?? null) ? $res['error'] : null)
                        ?? 'Erro API Lalamove: ' . json_encode($res);
                
                // Traduzir erros comuns mas manter o original para debug
                $rawErr = $errMsg;
                if (stripos($errMsg, 'valid market') !== false) {
                    $errMsg = 'Mercado BR não reconhecido. Erro API: ' . $rawErr;
                }
                if (stripos($errMsg, 'service area') !== false) {
                    $errMsg = 'Fora da área de cobertura Lalamove.';
                }
                if (stripos($errMsg, 'INVALID_PHONE') !== false || stripos($errMsg, 'phone') !== false) {
                    $errMsg = 'Telefone inválido. Formato: +5511999999999';
                }
                        
                $results[] = [
                    'serviceType' => $serviceType,
                    'label'       => self::SERVICE_TYPES[$serviceType],
                    'error'       => $errMsg,
                    'raw_response'=> json_encode($res),
                ];
            }
        }
        return $results;
    }

    // ==================== Criar Pedido ====================
    public function createOrder($quotationId, $stops, $recipientName, $recipientPhone, $remarks = '', $notifySms = true, $paymentMethod = 'WALLET', $totalValue = 0, $senderName = null, $senderPhone = null) {
        $path = '/v3/orders';
        
        $sName = $senderName ?: $this->storeName;
        $sPhone = $senderPhone ? $this->formatPhone($senderPhone) : $this->formatPhone($this->storePhone);

        // Se for dinheiro, adicionamos o aviso automático no remarks para o motoboy
        if ($paymentMethod === 'CASH') {
            $formattedPrice = number_format($totalValue, 2, ',', '.');
            $remarks = "⚠️ PAGAMENTO NO LOCAL: RECEBER R$ {$formattedPrice} DO DESTINATÁRIO REFERENTE AO FRETE. " . $remarks;
        }

        // Formatar telefone com DDI +55 usando o helper unificado
        $phone = $this->formatPhone($recipientPhone);

        $recipients = [];
        foreach ($stops as $i => $stop) {
            if ($i === 0) continue; // Primeiro stop é o remetente
            $recipients[] = [
                'stopId' => $stop['stopId'] ?? '',
                'name'   => $recipientName,
                'phone'  => $phone,
                'remarks'=> $remarks,
            ];
        }

        $body = [
            'data' => [
                'quotationId' => $quotationId,
                'sender'      => [
                    'stopId' => $stops[0]['stopId'] ?? '',
                    'name'   => $sName,
                    'phone'  => $sPhone,
                ],
                'recipients' => $recipients,
                'isPODEnabled' => false,
                'isRecipientSMSEnabled' => $notifySms,
            ]
        ];

        return $this->request('POST', $path, $body);
    }

    // ==================== Buscar Pedido ====================

    public function getOrder($orderId) {
        return $this->request('GET', '/v3/orders/' . $orderId);
    }

    // ==================== Dados do Motorista ====================
    // Retorna: driverId, name, phone, plateNumber, photo, coordinates
    public function getDriverDetails($orderId, $driverId) {
        return $this->request('GET', '/v3/orders/' . $orderId . '/drivers/' . $driverId);
    }

    // ==================== Adicionar Taxa de Prioridade ====================
    // Chamado APÓS criar o pedido e ANTES do motorista aceitar
    // Regular = sem gorjeta | Prioridade = com gorjeta
    public function addPriorityFee($orderId, $amount) {
        return $this->request('POST', '/v3/orders/' . $orderId . '/priority-fee', [
            'data' => ['priorityFee' => (string)$amount]
        ]);
    }

    // ==================== Consultar Cidades e Serviços ====================
    public function getCities() {
        return $this->request('GET', '/v3/cities');
    }

    // ==================== Cancelar Pedido ====================

    public function cancelOrder($orderId) {
        return $this->request('DELETE', '/v3/orders/' . $orderId);
    }

    // ==================== Status Disponíveis ====================
    // ASSIGNING_DRIVER → ON_GOING → PICKED_UP → COMPLETED
    // Também: EXPIRED, CANCELLED, REJECTED

    public function getOrderStatus($orderId) {
        $res = $this->getOrder($orderId);
        return $res['data']['status'] ?? 'UNKNOWN';
    }

    // ==================== HTTP com HMAC ====================

    private function buildSignature($timestamp, $method, $path, $body = '') {
        $rawSignature = "{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}";
        return hash_hmac('sha256', $rawSignature, $this->apiSecret);
    }

    private function request($method, $path, $body = '') {
        $timestamp = (string)round(microtime(true) * 1000);
        
        // Se houver body, garantir que o JSON seja idêntico ao assinado
        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $signature = $this->buildSignature($timestamp, $method, $path, $body);
        $token     = "{$this->apiKey}:{$timestamp}:{$signature}"; // v3 usa string plana, não base64

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: hmac ' . $token,
            'Market: BR',
            'X-LLM-Market: BR',
            'X-Request-ID: ' . uniqid('fa_', true),
        ];

        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25, // Aumentado para maior estabilidade
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false, // Temporário para teste se houver erro de CA
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST,       true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $logMsg = date('[Y-m-d H:i:s] ') . "CURL ERROR: $curlError (HTTP $httpCode)\n";
            file_put_contents(__DIR__ . '/../lalamove_errors.log', $logMsg, FILE_APPEND);
            return ['error' => 'Falha de conexão com Lalamove: ' . $curlError];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            $logMsg = date('[Y-m-d H:i:s] ') . "INVALID JSON: $response (HTTP $httpCode)\n";
            file_put_contents(__DIR__ . '/../lalamove_errors.log', $logMsg, FILE_APPEND);
            return ['error' => 'Resposta inválida da API (v3)', 'raw' => $response];
        }

        // Log de erro da API se houver
        if (isset($decoded['errors']) || isset($decoded['message'])) {
             $logMsg = date('[Y-m-d H:i:s] ') . "API ERROR: " . $response . "\n";
             file_put_contents(__DIR__ . '/../lalamove_errors.log', $logMsg, FILE_APPEND);
        }

        return $decoded;
    }

    // ==================== Settings ====================

    public function getSetting($key) {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetchColumn() ?: '';
        } catch (Exception $e) { return ''; }
    }

    public function saveSetting($key, $value) {
        $this->pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $value, $value]);
    }

    private function formatPhone($phone) {
        $clean = preg_replace('/\D/', '', $phone);
        if (empty($clean)) return null; // Retorna null se não houver números, evitando o "+" sozinho
        
        // Se já tem 55 e mais de 10 dígitos, apenas garante o +
        if (strlen($clean) >= 12 && substr($clean, 0, 2) === '55') {
            return '+' . $clean;
        }
        // Se tem 10 ou 11 dígitos, adiciona o +55
        if (strlen($clean) === 10 || strlen($clean) === 11) {
            return '+55' . $clean;
        }
        // Fallback
        return '+' . $clean;
    }
}
