<?php
/**
 * Melhor Envio API Client - Fight Arcade
 * Handles OAuth2 auth, shipping quotes, label purchase and generation
 * 
 * API Base URLs:
 *   Sandbox: https://sandbox.melhorenvio.com.br
 *   Production: https://melhorenvio.com.br
 */

class MelhorEnvioAPI {
    private $pdo;
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $accessToken;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Load config from settings
        $this->loadConfig();
    }

    private function loadConfig() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'me_%'");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }

        $sandbox = ($settings['me_sandbox'] ?? '1') === '1';
        $this->baseUrl = $sandbox ? 'https://sandbox.melhorenvio.com.br' : 'https://melhorenvio.com.br';
        $this->clientId = $settings['me_client_id'] ?? '';
        $this->clientSecret = $settings['me_client_secret'] ?? '';
        $this->redirectUri = $settings['me_redirect_uri'] ?? '';
        $this->accessToken = $settings['me_access_token'] ?? '';
    }

    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function hasToken() {
        return !empty($this->accessToken);
    }

    // ==================== OAuth2 ====================

    public function getAuthUrl() {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'cart-read cart-write companies-read companies-write coupons-read coupons-write notifications-read orders-read products-read products-write purchases-read shipping-calculate shipping-cancel shipping-checkout shipping-companies shipping-generate shipping-preview shipping-print shipping-share shipping-tracking ecommerce-shipping transactions-read users-read users-write webhooks-read webhooks-write',
            'state' => bin2hex(random_bytes(16))
        ]);
        return $this->baseUrl . '/oauth/authorize?' . $params;
    }

    public function exchangeCode($code) {
        $response = $this->httpPost($this->baseUrl . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code
        ], false);

        if (isset($response['access_token'])) {
            $this->saveSettings([
                'me_access_token' => $response['access_token'],
                'me_refresh_token' => $response['refresh_token'] ?? '',
                'me_token_expires' => date('Y-m-d H:i:s', time() + ($response['expires_in'] ?? 2592000))
            ]);
            $this->accessToken = $response['access_token'];
            return true;
        }
        return false;
    }

    public function refreshToken() {
        $refreshToken = $this->getSetting('me_refresh_token');
        if (empty($refreshToken)) return false;

        $response = $this->httpPost($this->baseUrl . '/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken
        ], false);

        if (isset($response['access_token'])) {
            $this->saveSettings([
                'me_access_token' => $response['access_token'],
                'me_refresh_token' => $response['refresh_token'] ?? $refreshToken,
                'me_token_expires' => date('Y-m-d H:i:s', time() + ($response['expires_in'] ?? 2592000))
            ]);
            $this->accessToken = $response['access_token'];
            return true;
        }
        return false;
    }

    // ==================== Cotação ====================

    public function calculateShipping($fromZip, $toZip, $products) {
        $payload = [
            'from' => ['postal_code' => preg_replace('/\D/', '', $fromZip)],
            'to' => ['postal_code' => preg_replace('/\D/', '', $toZip)],
            'products' => $products, // Array of {id, width, height, length, weight, insurance_value, quantity}
            'options' => [
                'receipt' => false,
                'own_hand' => false,
                'collect' => false
            ]
        ];

        return $this->httpPost($this->baseUrl . '/api/v2/me/shipment/calculate', $payload);
    }

    // ==================== Carrinho / Inserir Frete ====================

    public function addToCart($orderData) {
        // $orderData should follow the ME cart structure
        return $this->httpPost($this->baseUrl . '/api/v2/me/cart', $orderData);
    }

    // ==================== Checkout (Pagar) ====================

    public function checkout($orderIds) {
        return $this->httpPost($this->baseUrl . '/api/v2/me/shipment/checkout', [
            'orders' => $orderIds // Array of ME order IDs
        ]);
    }

    // ==================== Gerar Etiqueta ====================

    public function generateLabel($orderIds) {
        return $this->httpPost($this->baseUrl . '/api/v2/me/shipment/generate', [
            'orders' => $orderIds
        ]);
    }

    // ==================== Imprimir Etiqueta ====================

    public function printLabel($orderIds) {
        return $this->httpPost($this->baseUrl . '/api/v2/me/shipment/print', [
            'orders' => $orderIds
        ]);
    }

    // ==================== Rastreio ====================

    public function tracking($orderIds) {
        return $this->httpPost($this->baseUrl . '/api/v2/me/shipment/tracking', [
            'orders' => $orderIds
        ]);
    }

    // ==================== Cancelar ====================

    public function cancelLabel($orderData) {
        return $this->httpPost($this->baseUrl . '/api/v2/me/shipment/cancel', $orderData);
    }

    // ==================== Listar Etiquetas ====================

    public function listLabels($status = null) {
        $url = $this->baseUrl . '/api/v2/me/orders';
        if ($status) $url .= '?status=' . $status;
        return $this->httpGet($url);
    }

    public function getOrder($id) {
        return $this->httpGet($this->baseUrl . '/api/v2/me/orders/' . $id);
    }

    // ==================== Listar Agências ====================

    public function getAgencies($state = null, $city = null, $company = null) {
        $url = $this->baseUrl . '/api/v2/me/shipment/agencies';
        $params = [];
        if ($state)   $params['state']   = $state;
        if ($city)    $params['city']    = $city;
        if ($company) $params['company'] = $company;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->httpGet($url);
    }

    // ==================== Saldo ====================

    public function getBalance() {
        return $this->httpGet($this->baseUrl . '/api/v2/me/balance');
    }

    // ==================== User Info ====================

    public function getUserInfo() {
        return $this->httpGet($this->baseUrl . '/api/v2/me');
    }

    // ==================== HTTP Helpers ====================

    private function httpGet($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
                'User-Agent: Fight Arcade Catalogo (deletrics@gmail.com)'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode === 401) {
            // Try refresh
            if ($this->refreshToken()) {
                return $this->httpGet($url);
            }
        }
        return $data;
    }

    private function httpPost($url, $data, $useAuth = true) {
        $ch = curl_init($url);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Fight Arcade Catalogo (deletrics@gmail.com)'
        ];
        if ($useAuth) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resData = json_decode($response, true);
        if ($useAuth && $httpCode === 401) {
            if ($this->refreshToken()) {
                return $this->httpPost($url, $data, $useAuth);
            }
        }
        return $resData;
    }

    // ==================== Settings Helpers ====================

    public function getSetting($key) {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetchColumn() ?: '';
        } catch (Exception $e) {
            return '';
        }
    }

    private function saveSettings($pairs) {
        foreach ($pairs as $key => $val) {
            $stmt = $this->pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $val, $val]);
        }
    }
}
