<?php
/**
 * includes/uber_api.php — Fight Arcade
 * Integração Oficial Uber Direct / Eats API (OAuth 2.0)
 */

class UberService {
    private $pdo;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $tokenData = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'uber_%'");
        $settings = [];
        while ($r = $stmt->fetch()) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }

        $this->clientId     = $settings['uber_client_id'] ?? '';
        $this->clientSecret = $settings['uber_client_secret'] ?? '';
        $this->redirectUri  = $settings['uber_redirect_uri'] ?? 'https://fightarcade.com.br/catalogo/admin/uber_callback.php';
        
        $this->tokenData = !empty($settings['uber_token_json']) ? json_decode($settings['uber_token_json'], true) : null;
    }

    // ─── VERIFICAÇÃO DE CREDENCIAIS ────────────────────────────
    public function hasCredentials(): bool {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function isConnected(): bool {
        return !empty($this->tokenData['access_token']);
    }

    /**
     * Fluxo 1: Client Credentials (Operações de Loja)
     */
    public function getClientToken() {
        // Se já tem token válido no cache, usa ele
        if ($this->tokenData && !empty($this->tokenData['access_token']) && $this->tokenData['expires_at'] > time() + 60) {
            return $this->tokenData['access_token'];
        }

        return $this->refreshToken();
    }

    /**
     * Fluxo 2: Authorization Code (URL para o lojista clicar e autorizar)
     */
    public function getAuthUrl($state = 'fightarcade') {
        if (!$this->hasCredentials()) {
            throw new Exception('Salve o Client ID e Secret antes de conectar.');
        }
        $redirectUri = urlencode($this->redirectUri);
        $scopes = urlencode('eats.store deliveries');
        return "https://login.uber.com/oauth/v2/authorize?client_id={$this->clientId}&response_type=code&redirect_uri={$redirectUri}&scope={$scopes}";
    }

    /**
     * Troca o CODE pelo TOKEN final
     */
    public function exchangeCode($code) {
        return $this->callAuthApi([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirectUri,
            'code'          => $code
        ]);
    }

    /**
     * Fluxo 3: Refresh Token (Renovação Automática)
     */
    public function refreshToken() {
        $payload = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials',
            'scope'         => 'eats.store deliveries'
        ];

        if ($this->tokenData && !empty($this->tokenData['refresh_token'])) {
            $payload['grant_type'] = 'refresh_token';
            $payload['refresh_token'] = $this->tokenData['refresh_token'];
            unset($payload['scope']); // Refresh não aceita scope
        }

        return $this->callAuthApi($payload);
    }

    private function callAuthApi($params) {
        $body = http_build_query($params);
        $ch = curl_init("https://auth.uber.com/oauth/v2/token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            // CORREÇÃO: Content-Type obrigatório para Uber
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            // CORREÇÃO: SSL habilitado para produção
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 15
        ]);
        
        $res = curl_exec($ch);
        $data = json_decode($res, true);
        curl_close($ch);

        if (isset($data['access_token'])) {
            $data['expires_at'] = time() + ($data['expires_in'] ?? 3600);
            
            // Mantém o refresh_token se ele não vier na renovação
            if (empty($data['refresh_token']) && !empty($this->tokenData['refresh_token'])) {
                $data['refresh_token'] = $this->tokenData['refresh_token'];
            }

            $this->saveToken($data);
            return $data['access_token'];
        }
        return null;
    }

    private function saveToken($data) {
        $this->tokenData = $data;
        $json = json_encode($data);
        $stmt = $this->pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('uber_token_json', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$json, $json]);
    }

    /**
     * Endpoints Eats
     */
    public function getOrders($storeId) {
        $token = $this->getClientToken();
        return $this->apiCall("GET", "/v1/eats/stores/$storeId/orders", $token);
    }

    public function updateStatus($storeId, $orderId, $status) {
        $token = $this->getClientToken();
        return $this->apiCall("PATCH", "/v1/eats/stores/$storeId/orders/$orderId", $token, ['status' => $status]);
    }

    /**
     * UBER DIRECT (Logística Sob Demanda)
     */
    public function getDeliveryQuote($pickup, $dropoff, $items = []) {
        $token = $this->getClientToken();
        if (!$token) return ['error' => 'Uber não conectado.'];

        $body = [
            'pickup_address'  => $pickup['address'],
            'pickup_coords'   => [
                'latitude'  => (float)$pickup['lat'],
                'longitude' => (float)$pickup['lng']
            ],
            'dropoff_address' => $dropoff['address'],
            'dropoff_coords'  => [
                'latitude'  => (float)$dropoff['lat'],
                'longitude' => (float)$dropoff['lng']
            ]
        ];

        return $this->apiCall("POST", "/v1/deliveries/quotes", $token, $body);
    }

    public function createDelivery($quoteId, $pickup, $dropoff, $manifest) {
        $token = $this->getClientToken();
        if (!$token) return ['error' => 'Uber não conectado.'];

        $body = [
            'quote_id' => $quoteId,
            'pickup_info' => [
                'address' => $pickup['address'],
                'business_name' => $pickup['name'] ?? 'Fight Arcade',
                'instructions' => $pickup['notes'] ?? ''
            ],
            'dropoff_info' => [
                'address' => $dropoff['address'],
                'business_name' => $dropoff['name'],
                'instructions' => $dropoff['notes'] ?? ''
            ],
            'manifest_items' => $manifest
        ];

        return $this->apiCall("POST", "/v1/deliveries", $token, $body);
    }

    /**
     * Consulta Status da Entrega Uber
     */
    public function getDelivery($deliveryId) {
        $token = $this->getClientToken();
        if (!$token) return ['error' => 'Uber não conectado.'];
        return $this->apiCall("GET", "/v1/deliveries/$deliveryId", $token);
    }

    private function apiCall($method, $path, $token, $body = null) {
        $url = "https://api.uber.com" . $path;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = ["Authorization: Bearer $token", "Content-Type: application/json"];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($res, true);
        if ($httpCode >= 400) {
            $errorMsg = $decoded['message'] ?? $decoded['error'] ?? 'Erro desconhecido na API Uber';
            return ['error' => $errorMsg, 'http_code' => $httpCode, 'raw' => $res];
        }
        
        return $decoded;
    }
}
?>
