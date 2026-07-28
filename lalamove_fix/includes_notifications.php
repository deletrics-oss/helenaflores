<?php
/**
 * includes/notifications.php — Fight Arcade
 * Central de notificações: WhatsApp (Z-API / Evolution API) + SMS (Twilio)
 *
 * Configurações salvas em system_settings com prefixo 'notif_'
 *
 * Provedores:
 *   zapi   → Z-API (WhatsApp BR)  https://z-api.io  ~R$30/mês
 *   waapi  → Evolution API (self-hosted gratuito) https://evolution-api.com
 *   twilio → Twilio SMS            https://twilio.com
 *   log    → Só registra no banco, não envia
 */
class NotificationService {

    private $pdo;
    private $cfg  = [];
    private $adminPhone = '';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
    }

    private function loadConfig() {
        try {
            $rows = $this->pdo->query(
                "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'notif_%'"
            );
            while ($r = $rows->fetch()) {
                $this->cfg[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {}
        $this->adminPhone = $this->cfg['notif_admin_phone'] ?? '';
    }

    // =========================================================
    // MÉTODOS PRONTOS — use estes no seu código
    // =========================================================

    /** Novo pedido → alerta o ADMIN */
    public function newOrder($orderId, $customerName, $total, $city = '') {
        $msg = "🛍️ *NOVO PEDIDO!*\n"
             . "Pedido: *#$orderId*\n"
             . "Cliente: $customerName\n"
             . ($city ? "Cidade: $city\n" : '')
             . "Total: *R$ " . number_format($total, 2, ',', '.') . "*\n"
             . "🔗 " . $this->adminUrl('orders.php');
        return $this->notifyAdmin($msg);
    }

    /** Novo RMA → alerta o ADMIN */
    public function newRMA($rmaId, $customerName, $product, $type = 'garantia') {
        $labels = ['garantia' => 'Garantia', 'devolucao' => 'Devolução', 'promessa' => 'Promessa'];
        $label  = $labels[$type] ?? $type;
        $msg = "🔔 *NOVO RMA!*\n"
             . "Ocorrência: *#$rmaId* ($label)\n"
             . "Cliente: $customerName\n"
             . "Produto: $product\n"
             . "🔗 " . $this->adminUrl('rma.php');
        return $this->notifyAdmin($msg);
    }

    /** Pedido enviado → notifica o CLIENTE */
    public function orderShipped($customerPhone, $customerName, $orderId, $trackingCode = '', $carrier = '') {
        if ($this->isBlocked($customerPhone)) return false;
        $msg = "Olá *$customerName*! 🎉\n\n"
             . "Seu pedido *#$orderId* foi enviado! 🚚\n";
        if ($trackingCode) {
            $msg .= "📦 Rastreio: *$trackingCode*\n";
            if ($carrier) $msg .= "Transportadora: $carrier\n";
            $msg .= "🔍 https://www.melhorrastreio.com.br/rastreio/$trackingCode\n";
        }
        $msg .= "\nObrigado pela compra! 🚀";
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** RMA enviado → notifica o CLIENTE */
    public function rmaShipped($customerPhone, $customerName, $rmaId, $trackingCode = '') {
        if ($this->isBlocked($customerPhone)) return false;
        $msg = "Olá *$customerName*! 🛠️\n\n"
             . "Sua peça de reposição/garantia *#$rmaId* foi enviada!\n";
        if ($trackingCode) {
            $msg .= "📦 Rastreio: *$trackingCode*\n"
                 . "🔍 https://www.melhorrastreio.com.br/rastreio/$trackingCode\n";
        }
        $msg .= "\nAguardamos seu feedback após o recebimento! 🚀";
        return $this->notifyCustomer($customerPhone, $msg);
    }
    
    /** Lalamove → Motorista a caminho */
    public function lalamoveOnTheWay($phone, $name, $driverName = '', $plate = '', $driverPhone = '', $trackingUrl = '') {
        if ($this->isBlocked($phone)) return false;
        $msg = "Olá *$name*! 🏍️\n\n"
             . "Seu pedido da *Fight Arcade* já foi coletado e está a caminho!\n\n"
             . ($driverName ? "👤 Motorista: *$driverName*\n" : "")
             . ($plate      ? "🚲 Placa: *$plate*\n" : "")
             . ($driverPhone? "📞 Contato: $driverPhone\n" : "")
             . ($trackingUrl? "\n📍 Acompanhe em tempo real:\n$trackingUrl\n" : "")
             . "\nFique atento ao celular! 🚀";
        return $this->notifyCustomer($phone, $msg);
    }

    /** Pagamento aprovado → notifica o CLIENTE */
    public function orderPaid($customerPhone, $customerName, $orderId) {
        if ($this->isBlocked($customerPhone)) return false;
        $msg = "Olá *$customerName*! ✅\n\n"
             . "Pagamento do pedido *#$orderId* confirmado!\n"
             . "Estamos preparando tudo para o envio. 📦\n\n"
             . "Obrigado! 🚀";
        return $this->notifyCustomer($customerPhone, $msg);
    }



    /** RMA resolvido → notifica o CLIENTE */
    public function rmaResolved($customerPhone, $customerName, $rmaId) {
        if ($this->isBlocked($customerPhone)) return false;
        $msg = "Olá *$customerName*! ✅\n\n"
             . "Sua ocorrência *#$rmaId* foi resolvida!\n"
             . "Qualquer dúvida estamos à disposição. 🚀";
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** Extrato Financeiro → notifica o CLIENTE */
    public function notifyFinancialStatement($userId) {
        $userId = (int)$userId;
        $user = $this->pdo->query("SELECT * FROM users WHERE id = $userId")->fetch();
        if (!$user) return false;

        // Totais
        $totalBought = $this->pdo->query("SELECT SUM(total_amount) FROM orders WHERE user_id = $userId")->fetchColumn() ?: 0;
        $totalPaid   = $this->pdo->query("SELECT SUM(amount) FROM customer_payments WHERE user_id = $userId")->fetchColumn() ?: 0;
        $balance     = $totalBought - $totalPaid;

        // Últimas 5
        $stmt_h = $this->pdo->prepare("
            (SELECT id, total_amount as val, created_at, 'order' as type FROM orders WHERE user_id = ?)
            UNION ALL
            (SELECT id, amount as val, created_at, 'payment' as type FROM customer_payments WHERE user_id = ?)
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt_h->execute([$userId, $userId]);
        $history = $stmt_h->fetchAll();

        $msg = "📄 *EXTRATO FINANCEIRO*\n"
             . "Cliente: *" . $user['name'] . "*\n\n"
             . "🛍️ Total Comprado: *R$ " . number_format($totalBought, 2, ',', '.') . "*\n"
             . "💰 Total Pago: *R$ " . number_format($totalPaid, 2, ',', '.') . "*\n";
        
        if ($balance > 0) {
            $msg .= "⚠️ Saldo Pendente: *R$ " . number_format($balance, 2, ',', '.') . "*\n";
        } else {
            $msg .= "✅ Saldo: *Tudo Quitado*\n";
        }

        $msg .= "\n*Últimas Movimentações:*\n";
        foreach ($history as $h) {
            $date = date('d/m', strtotime($h['created_at']));
            $prefix = ($h['type'] == 'order') ? "📦 Pedido #{$h['id']} (-)" : "💵 Pagamento (+)";
            $msg .= "• $date: $prefix R$ " . number_format($h['val'], 2, ',', '.') . "\n";
        }

        $msg .= "\n_Dúvidas? Fale com a gente!_ 🚀";
        return $this->notifyCustomer($user['phone'], $msg);
    }

    // =========================================================
    // CORE
    // =========================================================

    /** Check if a phone/user is blocked from receiving notifications */
    public function isBlocked($phone) {
        $phone = $this->formatPhone($phone);
        if (!$phone) return true;
        try {
            // Check by phone in users table
            $cleanPhone = preg_replace('/\D/', '', $phone);
            $stmt = $this->pdo->prepare("SELECT notify_blocked FROM users WHERE REPLACE(REPLACE(REPLACE(phone,'+',''),'-',''),' ','') LIKE ? LIMIT 1");
            $stmt->execute(['%' . substr($cleanPhone, -10)]);
            $user = $stmt->fetch();
            if ($user && $user['notify_blocked']) return true;
        } catch (Exception $e) {
            // Column may not exist yet — not blocked
        }
        return false;
    }

    public function notifyAdmin($message) {
        if (empty($this->adminPhone)) return false;
        return $this->send($this->adminPhone, $message);
    }

    public function notifyCustomer($phone, $message) {
        $phone = $this->formatPhone($phone);
        if (!$phone) return false;
        $sent = $this->send($phone, $message);
        
        // Se enviou para o cliente, avisa o Admin (Cópia de segurança)
        if ($sent && !empty($this->adminPhone)) {
            $adminMsg = "📑 *CONFIRMAÇÃO DE ENVIO*\n"
                      . "Destinatário: $phone\n"
                      . "Mensagem enviada com sucesso! ✅";
            $this->send($this->adminPhone, $adminMsg, false); // false para não gerar loop infinito
        }
        
        return $sent;
    }

    public function send($phone, $message, $logAdmin = true) {
        $phone    = $this->formatPhone($phone);
        if (!$phone) return false;
        $provider = $this->cfg['notif_provider'] ?? 'log';
        $sent     = false;

        if ($provider === 'zapi')   $resData = $this->sendZapi($phone, $message);
        if ($provider === 'waapi')  $resData = $this->sendWaApi($phone, $message);
        if ($provider === 'twilio') $resData = ['success' => $this->sendTwilio($phone, $message), 'response' => 'Twilio SMS'];

        $sent = (bool)($resData['success'] ?? false);
        $response = $resData['response'] ?? ($sent ? 'OK' : 'FAIL');

        $this->log($phone, $message, $sent, $provider, $response);
        return $sent;
    }

    // =========================================================
    // PROVIDERS
    // =========================================================

    /** Z-API — WhatsApp BR */
    private function sendZapi($phone, $message) {
        $inst  = $this->cfg['notif_zapi_instance']     ?? '';
        $token = $this->cfg['notif_zapi_token']        ?? '';
        $ctok  = $this->cfg['notif_zapi_client_token'] ?? '';
        if (!$inst || !$token) return ['success' => false, 'response' => 'Faltam credenciais Z-API'];

        // Z-API: apenas DDD+número, sem +55
        $zapiPhone = preg_replace('/^\+?55/', '', preg_replace('/\D/', '', $phone));
        $url  = "https://api.z-api.io/instances/{$inst}/token/{$token}/send-text";
        $body = json_encode(['phone' => $zapiPhone, 'message' => $message]);
        
        $res = $this->curlPost($url, $body, ['Content-Type: application/json', 'Client-Token: ' . $ctok], true);
        $success = ($res && isset($res['messageId']));
        return ['success' => $success, 'response' => json_encode($res)];
    }

    /** Evolution API — Self-hosted gratuito */
    private function sendWaApi($phone, $message) {
        $url  = rtrim($this->cfg['notif_waapi_url']      ?? '', '/');
        $key  = $this->cfg['notif_waapi_key']             ?? '';
        $inst = $this->cfg['notif_waapi_instance']        ?? 'default';
        if (!$url || !$key) return false;

        // Evolution espera o número puramente numérico (ex: 5511999999999)
        $cleanPhone = preg_replace('/\D/', '', $phone);

        $body = json_encode(['number' => $cleanPhone, 'text' => $message]);
        $res = $this->curlPost("$url/message/sendText/$inst", $body, [
            'Content-Type: application/json', 'apikey: ' . $key
        ], true); // true para retornar a resposta
        
        $success = ($res && isset($res['key'])); // Evolution retorna a key da mensagem se enviada
        return ['success' => $success, 'response' => json_encode($res)];
    }

    /** Twilio SMS */
    private function sendTwilio($phone, $message) {
        $sid   = $this->cfg['notif_twilio_sid']   ?? '';
        $token = $this->cfg['notif_twilio_token'] ?? '';
        $from  = $this->cfg['notif_twilio_from']  ?? '';
        if (!$sid || !$token || !$from) return false;

        $url  = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['To' => $phone, 'From' => $from, 'Body' => $message]),
            CURLOPT_USERPWD        => "$sid:$token",
            CURLOPT_TIMEOUT        => 10,
        ]);
        $code = curl_getinfo(curl_exec($ch) ? $ch : $ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 300);
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /** Formata para E.164 (+5511999999999) */
    public function formatPhone($phone) {
        $p = preg_replace('/\D/', '', $phone);
        if (!$p) return '';
        
        // Se começar com 0, remove (ex: 0119...)
        if (substr($p, 0, 1) === '0') $p = substr($p, 1);
        
        // Caso comum de erro: DDD duplicado (ex: 1111984343166)
        if (strlen($p) === 13 && substr($p, 0, 2) === substr($p, 2, 2)) {
            $p = substr($p, 2);
        }

        // Se tiver 10 ou 11 dígitos, é um número BR sem o código do país
        if (strlen($p) === 10 || strlen($p) === 11) {
            return '+55' . $p;
        }
        
        // Se já tem o 55 no começo
        if (substr($p, 0, 2) === '55') {
            // Verifica se o DDD está triplicado ou com erro de 5511119...
            if (strlen($p) === 15 && substr($p, 2, 2) === substr($p, 4, 2)) {
                $p = '55' . substr($p, 4);
            }
            return '+' . $p;
        }

        // Se for muito curto, ignorar
        if (strlen($p) < 10) return '';

        return '+' . $p;
    }

    /** Gera link wa.me para abrir WhatsApp sem API */
    public static function waLink($phone, $message = '') {
        $p = preg_replace('/\D/', '', $phone);
        if (strlen($p) <= 11) $p = '55' . $p;
        $url = 'https://api.whatsapp.com/send?phone=' . $p;
        if ($message) $url .= '&text=' . urlencode($message);
        return $url;
    }

    private function adminUrl($page) {
        $base = rtrim($this->cfg['notif_site_url'] ?? 'https://www.fightarcade.com.br/catalogo/admin', '/');
        return $base . '/' . $page;
    }

    private function curlPost($url, $body, $headers = [], $returnResponse = false) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($returnResponse) {
            return json_decode($res, true);
        }
        return ($res !== false && $code >= 200 && $code < 300);
    }

    private function log($phone, $message, $success, $provider, $response = '') {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS notification_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(20), message TEXT, provider VARCHAR(20),
                success TINYINT(1) DEFAULT 0,
                response TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            // Check if column exists
            try { $this->pdo->exec("ALTER TABLE notification_log ADD COLUMN response TEXT NULL"); } catch(Exception $e) {}
            
            $this->pdo->prepare(
                "INSERT INTO notification_log (phone, message, provider, success, response) VALUES (?,?,?,?,?)"
            )->execute([$phone, substr($message, 0, 500), $provider, $success ? 1 : 0, $response]);
        } catch (Exception $e) {}
    }

    public function saveSettings($pairs) {
        foreach ($pairs as $k => $v) {
            $this->pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?"
            )->execute([$k, $v, $v]);
        }
    }

    public function getConfig()     { return $this->cfg; }
    public function getRecentLogs($limit = 20) {
        try {
            return $this->pdo->query(
                "SELECT * FROM notification_log ORDER BY created_at DESC LIMIT $limit"
            )->fetchAll();
        } catch (Exception $e) { return []; }
    }
}
