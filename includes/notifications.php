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
    private $isFactory = false;

    public function __construct($pdo, $isFactory = false) {
        $this->pdo = $pdo;
        $this->isFactory = $isFactory;
        $this->ensureTables();
        $this->loadConfig();
    }

    private function ensureTables() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS notification_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(30),
                message TEXT,
                success TINYINT DEFAULT 0,
                response TEXT,
                provider VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            error_log('[NotificationService] ensureTables error: ' . $e->getMessage());
        }
    }

    private function loadConfig() {
        try {
            // Primeiro busca todas as chaves padrão (B2C)
            $rows = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'notif_%'");
            $b2c = [];
            while ($r = $rows->fetch()) {
                $b2c[$r['setting_key']] = $r['setting_value'];
            }

            if ($this->isFactory) {
                $this->cfg = [];
                // Busca as chaves específicas da fábrica (B2B)
                $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'notif_factory_%'");
                $stmt->execute();
                while ($r = $stmt->fetch()) {
                    $key = str_replace('notif_factory_', 'notif_', $r['setting_key']);
                    $this->cfg[$key] = $r['setting_value'];
                }

                // Fallbacks automáticos se vazio na fábrica
                if (empty($this->cfg['notif_waapi_url'])) {
                    $this->cfg['notif_waapi_url'] = $b2c['notif_waapi_url'] ?? '';
                }
                if (empty($this->cfg['notif_waapi_key'])) {
                    $this->cfg['notif_waapi_key'] = $b2c['notif_waapi_key'] ?? '';
                }
                if (empty($this->cfg['notif_site_url'])) {
                    $this->cfg['notif_site_url'] = $b2c['notif_site_url'] ?? '';
                }
                if (empty($this->cfg['notif_provider'])) {
                    $this->cfg['notif_provider'] = $b2c['notif_provider'] ?? 'log';
                }
                if (empty($this->cfg['notif_waapi_instance'])) {
                    $this->cfg['notif_waapi_instance'] = 'fabrica';
                }
            } else {
                $this->cfg = $b2c;
            }
        } catch (\Throwable $e) {
            error_log('[NotificationService] loadConfig error: ' . $e->getMessage());
        }
        $this->adminPhone = $this->cfg['notif_admin_phone'] ?? '';
    }

    /** Retorna o nome da loja baseado na preferência de privacidade do cliente */
    private function getStoreName($userId = 0, $phone = '') {
        $defaultName = $this->cfg['notif_store_name'] ?? 'Fight Arcade';
        $hide = false;
        try {
            if ($userId > 0) {
                $hide = $this->pdo->query("SELECT hide_store_name FROM users WHERE id = " . (int)$userId)->fetchColumn();
            } elseif (!empty($phone)) {
                $clean = preg_replace('/\D/', '', $phone);
                if (strpos($clean, '55') === 0 && strlen($clean) > 10) {
                    $clean = substr($clean, 2);
                }
                if (strlen($clean) >= 8) {
                    $hide = $this->pdo->query("SELECT hide_store_name FROM users WHERE phone LIKE '%$clean%' LIMIT 1")->fetchColumn();
                }
            }
        } catch (\Throwable $e) {
            // Column may not exist yet - silently ignore
        }

        if ($hide) return "Catálogo de Games";
        return "*" . $defaultName . "*";
    }

    // =========================================================
    // MÉTODOS PRONTOS — use estes no seu código
    // =========================================================

    /** Notificação de Novo Pedido (Admin) */
    public function newOrder($orderId, $customerName, $total, $city = '') {
        $store = $this->cfg['notif_store_name'] ?? 'Fight Arcade';
        $msg = "🛍️ *$store — NOVO PEDIDO!*\n"
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

    /** Pedido enviado → notifica o CLIENTE com rastreio */
    public function orderShipped($customerPhone, $customerName, $orderId, $trackingCode, $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        
        $track_codes = preg_split('/[\s,;]+/', $trackingCode);
        $track_codes = array_filter(array_map('trim', $track_codes));
        
        $trackingText = "";
        if (count($track_codes) > 1) {
            $trackingText = "📦 Seus códigos de rastreio:\n";
            foreach ($track_codes as $tc) {
                $trackingText .= "• *$tc* (🔍 https://www.melhorrastreio.com.br/rastreio/$tc)\n";
            }
        } else {
            $trackingText = "📦 Código de Rastreio: *$trackingCode*\n🔍 https://www.melhorrastreio.com.br/rastreio/$trackingCode";
        }

        $msg = "Olá *$customerName*! 👋\n\n"
             . "Seu pedido *#$orderId* foi postado com sucesso! 🚀\n"
             . "$trackingText\n\n"
             . "Pode acompanhar pelo site da transportadora ou nos consultar aqui.\n\n"
             . "Equipe $store 🕹️";
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** RMA enviado → notifica o CLIENTE */
    public function rmaShipped($customerPhone, $customerName, $rmaId, $trackingCode = '', $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        $msg = "Olá *$customerName*! 🛠️\n\n"
             . "Sua peça de reposição/garantia *#$rmaId* foi enviada!\n";
        if ($trackingCode) {
            $msg .= "📦 Rastreio: *$trackingCode*\n"
                 . "🔍 https://www.melhorrastreio.com.br/rastreio/$trackingCode\n";
        }
        $msg .= "\nAguardamos seu feedback após o recebimento no $store! 🚀";
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** Atualização de status de Rastreio → notifica o CLIENTE */
    public function trackingUpdate($customerPhone, $customerName, $trackingCode, $newStatus, $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        
        $statusBr = strtoupper($newStatus);
        if ($newStatus == 'delivered') $statusBr = 'ENTREGUE ✅';
        elseif ($newStatus == 'posted') $statusBr = 'POSTADO 📦';
        elseif ($newStatus == 'canceled') $statusBr = 'CANCELADO ❌';
        elseif ($newStatus == 'undelivered') $statusBr = 'NÃO ENTREGUE ⚠️';
        elseif ($newStatus == 'pending') $statusBr = 'PENDENTE ⏳';
        elseif ($newStatus == 'released') $statusBr = 'EM TRÂNSITO / LIBERADO 🚚';
        elseif ($newStatus == 'received') $statusBr = 'RECEBIDO 📥';
        elseif ($newStatus == 'in_transit') $statusBr = 'EM TRÂNSITO 🚚';
        elseif ($newStatus == 'waiting_post') $statusBr = 'AGUARDANDO POSTAGEM ⏳';
        elseif ($newStatus == 'lost') $statusBr = 'EXTRAVIADO ⚠️';
        elseif ($newStatus == 'returned') $statusBr = 'DEVOLVIDO 🔄';
        
        $msg = "Olá *$customerName*! 🚚\n\n"
             . "Temos uma atualização sobre o seu pacote (Rastreio: *$trackingCode*)!\n"
             . "Novo Status: *$statusBr*\n\n"
             . "🔗 https://www.melhorrastreio.com.br/rastreio/$trackingCode\n\n"
             . "Equipe $store 🕹️";
        return $this->notifyCustomer($customerPhone, $msg);
    }
    
    /** Etiqueta Reversa Gerada → Envia o link PDF para o CLIENTE */
    public function reverseLabelGenerated($customerPhone, $customerName, $rmaId, $pdfUrl, $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        
        // Busca agência mais próxima pelo CEP/cidade do ticket RMA
        $agencyInfo = $this->getClosestAgencyStringByRma($rmaId);
        
        $msg = "Olá *$customerName*! 🔄\n\n"
             . "A sua etiqueta de devolução referente à ocorrência *#$rmaId* já está pronta!\n\n"
             . "📄 *Clique no link abaixo para baixar e imprimir a etiqueta:*\n$pdfUrl\n\n"
             . "📦 *Como fazer o envio:*\n"
             . "1. Imprima o arquivo PDF acima e cole na parte de fora da caixa.\n"
             . "2. Leve o pacote bem embalado até o ponto de coleta mais próximo.\n";
        
        if (!empty($agencyInfo)) {
            $msg .= $agencyInfo . "\n";
        } else {
            $msg .= "\n📍 *Encontre o ponto de coleta mais próximo do seu endereço aqui:*\nhttps://melhorenvio.com.br/mapa\n\n";
        }
        
        $msg .= "Assim que recebermos o pacote aqui, daremos andamento imediato. Qualquer dúvida, é só nos chamar!\n\n"
             . "Equipe $store 🕹️";
             
        $sent = $this->notifyCustomer($customerPhone, $msg);
        
        // Avisa também o Admin (Número de notificações)
        $adminMsg = "🔄 *ETIQUETA REVERSA GERADA!*\n"
                  . "RMA: *#$rmaId*\n"
                  . "Cliente: $customerName\n"
                  . "O PDF foi gerado e enviado para o WhatsApp do cliente!\n"
                  . "🔗 Link do PDF: $pdfUrl";
        $this->notifyAdmin($adminMsg);
        
        return $sent;
    }
    
    /** Cobrança/Lembrete de Envio de Reversa → Notifica o CLIENTE */
    public function reverseReminder($customerPhone, $customerName, $rmaId, $pdfUrl, $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        
        // Busca agência mais próxima pelo CEP/cidade do ticket RMA
        $agencyInfo = $this->getClosestAgencyStringByRma($rmaId);
        
        $msg = "Olá *$customerName*! 🔄\n\n"
             . "Passando para lembrar de realizar o envio do seu produto referente à ocorrência *RMA #$rmaId* via Logística Reversa.\n\n"
             . "Precisamos receber a peça antiga de volta para dar andamento ao seu atendimento ou reembolso. 📦\n\n";
             
        if (!empty($pdfUrl)) {
            $msg .= "📄 *Link para baixar/imprimir a etiqueta:*\n$pdfUrl\n\n";
            if (!empty($agencyInfo)) {
                $msg .= $agencyInfo . "\n";
            } else {
                $msg .= "📍 *Encontre o ponto de coleta mais próximo aqui:*\nhttps://melhorenvio.com.br/mapa\n\n";
            }
        } else {
            $msg .= "A etiqueta de envio está disponível em seu painel de cliente. 📄\n\n";
        }
        
        $msg .= "Qualquer dúvida ou dificuldade com o envio, estamos à disposição!\n\n"
             . "Equipe $store 🕹️";
             
        return $this->notifyCustomer($customerPhone, $msg);
    }
    
    /** Busca a agência/ponto de postagem mais próximo do cliente do RMA */
    public function getClosestAgencyStringByRma($rmaId) {
        try {
            $stmt = $this->pdo->prepare("SELECT city, state, zipcode, me_order_id FROM rma_tickets WHERE id = ?");
            $stmt->execute([(int)$rmaId]);
            $ticket = $stmt->fetch();
            
            if (!$ticket || empty($ticket['state']) || empty($ticket['city'])) {
                return '';
            }
            
            require_once __DIR__ . '/melhorenvio.php';
            $me = new MelhorEnvioAPI($this->pdo);
            if (!$me->isConfigured() || !$me->hasToken()) {
                return '';
            }
            
            $agencies = $me->getAgencies($ticket['state'], $ticket['city']);
            if (empty($agencies) || !is_array($agencies)) {
                // Fallback: tenta só pelo estado
                $agencies = $me->getAgencies($ticket['state']);
                if (empty($agencies) || !is_array($agencies)) {
                    return '';
                }
            }
            
            // Filtra agências ativas
            $activeAgencies = [];
            foreach ($agencies as $agency) {
                if (!isset($agency['status']) || $agency['status'] === 'active') {
                    $activeAgencies[] = $agency;
                }
            }
            
            if (empty($activeAgencies)) {
                return '';
            }
            
            // Tenta encontrar a transportadora do pedido
            $carrierAgencies = [];
            if (!empty($ticket['me_order_id'])) {
                $order = $me->getOrder($ticket['me_order_id']);
                if ($order && is_array($order) && isset($order['service']['company']['name'])) {
                    $companyName = $order['service']['company']['name'];
                    foreach ($activeAgencies as $agency) {
                        $agCompany = $agency['company']['name'] ?? '';
                        if (stripos($agCompany, $companyName) !== false || stripos($companyName, $agCompany) !== false) {
                            $carrierAgencies[] = $agency;
                        }
                    }
                }
            }
            
            $pool = !empty($carrierAgencies) ? $carrierAgencies : $activeAgencies;
            
            // Encontra a mais próxima por prefixo de CEP
            $customerCep = preg_replace('/\D/', '', $ticket['zipcode'] ?? '');
            $bestAgency = null;
            $bestScore = -1;
            
            foreach ($pool as $agency) {
                $agencyCep = preg_replace('/\D/', '', $agency['address']['postal_code'] ?? '');
                $score = 0;
                $len = min(strlen($customerCep), strlen($agencyCep));
                for ($i = 0; $i < $len; $i++) {
                    if ($customerCep[$i] === $agencyCep[$i]) {
                        $score++;
                    } else {
                        break;
                    }
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestAgency = $agency;
                }
            }
            
            if (!$bestAgency) {
                $bestAgency = $pool[0];
            }
            
            $compName = $bestAgency['company']['name'] ?? 'Ponto de Coleta';
            $agName   = $bestAgency['name'] ?? '';
            $addr     = $bestAgency['address']['address'] ?? '';
            $num      = $bestAgency['address']['number'] ?? '';
            $compl    = $bestAgency['address']['complement'] ?? '';
            $dist     = $bestAgency['address']['district'] ?? '';
            $city     = $bestAgency['address']['city'] ?? '';
            $state    = $bestAgency['address']['state'] ?? '';
            $cep      = $bestAgency['address']['postal_code'] ?? '';
            
            $complText = !empty($compl) ? " ({$compl})" : "";
            $agLabel   = !empty($agName) ? " - {$agName}" : "";
            
            return "\n📍 *Ponto de Postagem mais próximo:*\n"
                 . "🏢 *{$compName}{$agLabel}*\n"
                 . "📍 {$addr}, {$num}{$complText} - {$dist}, {$city} - {$state}\n"
                 . "📮 CEP: {$cep}\n";
                 
        } catch (\Throwable $e) {
            error_log('[NotificationService] getClosestAgencyStringByRma error: ' . $e->getMessage());
        }
        return '';
    }
    
    /** Lalamove → Motorista a caminho */
    public function lalamoveOnTheWay($phone, $name, $driverName = '', $plate = '', $driverPhone = '', $trackingUrl = '', $userId = 0) {
        if ($this->isBlocked($phone)) return false;
        $store = $this->getStoreName($userId, $phone);
        $msg = "Olá *$name*! 🏍️\n\n"
             . "Seu pedido do $store já foi coletado e está a caminho!\n\n"
             . ($driverName ? "👤 Motorista: *$driverName*\n" : "")
             . ($plate      ? "🚲 Placa: *$plate*\n" : "")
             . ($driverPhone? "📞 Contato: $driverPhone\n" : "")
             . ($trackingUrl? "\n📍 Acompanhe em tempo real:\n$trackingUrl\n" : "")
             . "\nFique atento ao celular! 🚀";
        return $this->notifyCustomer($phone, $msg);
    }

    /** Pagamento aprovado → notifica o CLIENTE */
    public function orderPaid($customerPhone, $customerName, $orderId, $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        $msg = "Olá *$customerName*! ✅\n\n"
             . "Pagamento do pedido *#$orderId* confirmado!\n"
             . "Estamos preparando tudo para o envio no $store. 📦\n\n"
             . "Obrigado! 🚀";
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** Pagamento recebido → notifica o CLIENTE */
    public function notifyPaymentReceived($customerPhone, $customerName, $amount, $newBalance, $desc = '', $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        $msg = "Olá *$customerName*! 👋\n\n"
             . "Recebemos seu pagamento de *R$ " . number_format($amount, 2, ',', '.') . "*.\n"
             . "\n💰 *Saldo Atual:* R$ " . number_format($newBalance, 2, ',', '.') . "\n\n"
             . "Equipe $store 🕹️";
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** Mensagem de dívida/cobrança (Customizada com Itens) */
    public function sendDebtReminder($customerPhone, $customerName, $amount, $accName, $accKey, $accBank = '', $itemsList = '', $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        $msg = "Olá *$customerName*! 👋\n\n"
             . "Passando para te enviar seu resumo de conta no $store. 🕹️\n\n"
             . "Você possui um saldo pendente de *R$ " . number_format($amount, 2, ',', '.') . "*.\n\n";

        if (!empty($itemsList)) {
            $msg .= "📦 *Itens Pendentes:*\n" . $itemsList . "\n";
        }

        $msg .= "Pode realizar o pagamento através da seguinte conta:\n"
             . "🏦 *$accName*\n";
             
        if (!empty($accKey)) {
            $msg .= "🔑 *PIX:* $accKey\n";
        }
        
        if (!empty($accBank)) {
            $msg .= "📝 *Dados:* $accBank\n";
        }

        $msg .= "\nApós realizar o pagamento, por favor envie o comprovante aqui. Muito obrigado! 🚀";
        
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** Extrato Financeiro Completo (Orders + Payments) 
     * @param bool $detailed Se true, inclui os itens de cada pedido
     */
    public function notifyFinancialStatement($userId, $accName, $accKey, $accBank = '', $detailed = false, $targetPhone = '', $orderIds = []) {
        $user = $this->pdo->query("SELECT * FROM users WHERE id = " . (int)$userId)->fetch();
        if (!$user || empty($user['phone'])) return false;
        
        $phoneToSend = !empty($targetPhone) ? $targetPhone : $user['phone'];
        if ($this->isBlocked($phoneToSend)) return false;

        $orderCondition = "";
        if (!empty($orderIds) && is_array($orderIds)) {
            if (in_array("NONE", $orderIds)) {
                $orderCondition = " AND id = -1";
            } else {
                $ids = implode(',', array_map('intval', $orderIds));
                $orderCondition = " AND id IN ($ids)";
            }
        }

        $orders = $this->pdo->query("SELECT id, total_amount, created_at FROM orders WHERE user_id = $userId AND status != 'canceled' $orderCondition ORDER BY created_at ASC")->fetchAll();
        $pays   = $this->pdo->query("SELECT amount, created_at, description FROM customer_payments WHERE user_id = $userId ORDER BY created_at ASC")->fetchAll();

        $totalBought = 0;
        $totalPaid   = 0;
        $store = $this->getStoreName($userId, $user['phone']);
        
        $msg = "📜 *EXTRATO FINANCEIRO — $store*\n";
        $msg .= "Cliente: *" . $user['name'] . "*\n\n";

        $msg .= "🛍️ *COMPRAS:*\n";
        if (empty($orders)) {
            $msg .= "_Nenhum pedido processado._\n";
        } else {
            foreach ($orders as $o) {
                $msg .= "• Pedido #{$o['id']} (" . date('d/m', strtotime($o['created_at'])) . "): *R$ " . number_format($o['total_amount'], 2, ',', '.') . "*\n";
                
                if ($detailed) {
                    $items = $this->pdo->query("SELECT product_name, quantity FROM order_items WHERE order_id = " . (int)$o['id'])->fetchAll();
                    foreach ($items as $it) {
                        $msg .= "  └ " . $it['quantity'] . "x " . $it['product_name'] . "\n";
                    }
                }
                
                $totalBought += $o['total_amount'];
            }
        }

        $msg .= "\n💰 *PAGAMENTOS:*\n";
        if (empty($pays)) {
            $msg .= "_Nenhum pagamento registrado._\n";
        } else {
            foreach ($pays as $p) {
                $msg .= "• " . date('d/m', strtotime($p['created_at'])) . ": *R$ " . number_format($p['amount'], 2, ',', '.') . "*\n";
                $totalPaid += $p['amount'];
            }
        }

        $balance = $totalBought - $totalPaid;
        $msg .= "\n📊 *RESUMO:*\n";
        $msg .= "Total Comprado: R$ " . number_format($totalBought, 2, ',', '.') . "\n";
        $msg .= "Total Pago: R$ " . number_format($totalPaid, 2, ',', '.') . "\n";
        $msg .= "🔴 *SALDO DEVEDOR: R$ " . number_format($balance, 2, ',', '.') . "*\n\n";

        if ($balance > 0) {
            $msg .= "🏦 *DADOS PARA PAGAMENTO:*\n";
            $msg .= "Conta: *$accName*\n";
            if (!empty($accKey))  $msg .= "🔑 *PIX:* $accKey\n";
            if (!empty($accBank)) $msg .= "📝 *Dados:* $accBank\n";
            
            $msg .= "\n⚠️ *AVISO:* NUNCA realize depósitos sem confirmar a conta aqui no chat. Proteja-se contra golpes! 🕹️\n";
        }

        $msg .= "\nQualquer dúvida, estamos à disposição! 🚀";

        return $this->notifyCustomer($phoneToSend, $msg);
    }

    /** Notificação de Cobrança de UM pedido específico com itens */
    public function notifyOrderDebt($userId, $orderId, $accName, $accKey, $accBank = '', $detailed = true) {
        $user = $this->pdo->query("SELECT * FROM users WHERE id = " . (int)$userId)->fetch();
        if (!$user || empty($user['phone'])) return false;
        if ($this->isBlocked($user['phone'])) return false;
        $store = $this->getStoreName($userId, $user['phone']);

        $order = $this->pdo->query("SELECT * FROM orders WHERE id = " . (int)$orderId)->fetch();
        if (!$order) return false;

        $itemsList = "";
        if ($detailed) {
            $items = $this->pdo->query("SELECT product_name, quantity FROM order_items WHERE order_id = " . (int)$orderId)->fetchAll();
            foreach ($items as $it) {
                $itemsList .= "• " . $it['quantity'] . "x " . $it['product_name'] . "\n";
            }
        }

        $msg = "Olá *" . $user['name'] . "*! 👋\n\n"
             . "Estamos processando seu pedido *#$orderId* no $store e aguardamos a confirmação do pagamento para prosseguir. 🕹️\n\n";

        if (!empty($itemsList)) {
            $msg .= "📦 *Itens do Pedido:*\n" . $itemsList . "\n";
        }

        $msg .= "💰 Valor a pagar: *R$ " . number_format($order['total_amount'], 2, ',', '.') . "*\n\n"
             . "Pode realizar o pagamento através da seguinte conta:\n"
             . "🏦 *$accName*\n";
             
        if (!empty($accKey))  $msg .= "🔑 *PIX:* $accKey\n";
        if (!empty($accBank)) $msg .= "📝 *Dados:* $accBank\n";

        $msg .= "\n⚠️ *AVISO:* NUNCA realize depósitos sem confirmar a conta aqui no chat. Proteja-se contra golpes! 🕹️\n"
             . "\nApós realizar o pagamento, por favor envie o comprovante aqui. Muito obrigado! 🚀";
        
        return $this->notifyCustomer($user['phone'], $msg);
    }

    /** RMA resolvido → notifica o CLIENTE */
    public function rmaResolved($customerPhone, $customerName, $rmaId, $userId = 0) {
        if ($this->isBlocked($customerPhone)) return false;
        $store = $this->getStoreName($userId, $customerPhone);
        $msg = "Olá *$customerName*! 👋\n\n"
             . "Seu ticket de suporte *RMA #$rmaId* foi resolvido!\n"
             . "Verifique os detalhes no seu painel ou aguarde novas instruções aqui.\n\n"
             . "Atenciosamente,\nEquipe $store 🕹️";
        return $this->notifyCustomer($customerPhone, $msg);
    }

    /** 
     * Configura o Webhook na Evolution API automaticamente 
     * Aponta para o arquivo webhook_evolution.php na raiz do site
     */
    public function setWebhookEvolution() {
        $url  = rtrim($this->cfg['notif_waapi_url'] ?? '', '/');
        $key  = $this->cfg['notif_waapi_key'] ?? '';
        $inst = $this->cfg['notif_waapi_instance'] ?? 'default';

        if (!$url || !$key) return ['success' => false, 'error' => 'Configuração incompleta'];

        $site = rtrim($this->cfg['notif_site_url'] ?? '', '/');
        
        // Força o domínio correto se estiver vindo incompleto
        if (empty($site) || strpos($site, 'http') === false) {
            $baseUrl = 'https://fightarcade.com.br/catalogo';
        } else {
            $baseUrl = $site;
        }

        $webhookFile = $this->isFactory ? 'webhook_factory_evolution.php' : 'webhook_evolution.php';
        $webhookUrl = rtrim($baseUrl, '/') . '/' . $webhookFile;

        $payload = [
            'url' => $webhookUrl,
            'enabled' => true,
            'events' => [
                "MESSAGES_UPSERT",
                "MESSAGES_UPDATE"
            ]
        ];

        // Estratégia de Múltiplos Endpoints (v1, v2, global e por instância)
        $endpoints = [
            "/webhook/set/$inst",
            "/webhook/instance/set/$inst",
            "/instance/setWebhook/$inst"
        ];

        $lastResponse = "";
        $lastStatus = 0;

        foreach ($endpoints as $path) {
            $fullUrl = $url . $path;
            
            // Tenta com 'apikey' e depois com 'Authorization'
            $authMethods = [
                ['apikey: ' . $key],
                ['Authorization: Bearer ' . $key, 'Content-Type: application/json']
            ];

            foreach ($authMethods as $headers) {
                $ch = curl_init($fullUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                // Tenta payload normal e payload envelopado
                $variants = [
                    json_encode($payload),
                    json_encode(["webhook" => $payload])
                ];

                foreach ($variants as $body) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    $res = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    
                    if ($status >= 200 && $status < 300) {
                        curl_close($ch);
                        return ['success' => true, 'url' => $webhookUrl, 'path' => $path];
                    }
                    $lastResponse = $res;
                    $lastStatus = $status;
                }
                curl_close($ch);
            }
        }

        return [
            'success' => false, 
            'error' => "Falha na API Evolution (Status $lastStatus)", 
            'url' => $webhookUrl,
            'api_response' => $lastResponse
        ];
    }

    /** Extrato Financeiro → notifica o CLIENTE (Versão unificada para evitar erro de redeclaração) */
    public function notifyFinancialStatementLegacy($userId) {
        // Redireciona para a versão completa ou mantém como backup se necessário
        return $this->notifyFinancialStatement($userId, '', '', '');
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
        
        // NOVO: Salva mensagens enviadas na tabela whatsapp_messages para aparecer no CRM
        if ($sent) {
            try {
                $cleanPhone = preg_replace('/\D/', '', $phone);
                $remoteJid = (strpos($phone, '@') !== false) ? $phone : $cleanPhone . '@s.whatsapp.net';
                $stmt = $this->pdo->prepare("INSERT INTO whatsapp_messages (remote_jid, from_me, message_text) VALUES (?, 1, ?)");
                $stmt->execute([$remoteJid, $message]);
            } catch (\Exception $e) {}
        }
        
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

        // Evolution resolve o JID automaticamente a partir do número limpo (ex: 5511999999999)
        // Isso evita falhas de 9º dígito em contas mais antigas
        $phoneClean = trim($phone);
        if (strpos($phoneClean, '@') !== false) {
            $number = $phoneClean;
        } else {
            $number = preg_replace('/\D/', '', $phoneClean);
        }

        $body = json_encode(['number' => $number, 'text' => $message]);
        $res = $this->curlPost("$url/message/sendText/$inst", $body, [
            'Content-Type: application/json', 'apikey: ' . $key
        ], true); // true para retornar a resposta
        
        $success = ($res && (isset($res['key']) || isset($res['messageId']))); 
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
        $defaultBase = $this->isFactory ? 'https://www.fightarcade.com.br/catalogo/fabrica' : 'https://www.fightarcade.com.br/catalogo/admin';
        $base = rtrim($this->cfg['notif_site_url'] ?? $defaultBase, '/');
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
            $dbKey = $k;
            if ($this->isFactory && strpos($k, 'notif_factory_') !== 0) {
                $dbKey = str_replace('notif_', 'notif_factory_', $k);
            }
            $this->pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?"
            )->execute([$dbKey, $v, $v]);
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
