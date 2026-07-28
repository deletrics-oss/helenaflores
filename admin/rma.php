<?php
/**
 * admin/rma.php — Fight Arcade
 */
try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/user_auth.php';
    require_once __DIR__ . '/../includes/melhorenvio.php';
    require_once __DIR__ . '/../includes/lalamove.php';
    require_once __DIR__ . '/../includes/notifications.php';
    isAdmin();

    $llm = new LalamoveAPI($pdo);
    $notif = new NotificationService($pdo);

// --- 1. SETUP DB TABELA RMA ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rma_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('garantia', 'devolucao', 'promessa') DEFAULT 'garantia',
        customer_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        document VARCHAR(20) NULL,
        phone VARCHAR(20) NULL,
        address VARCHAR(255) NULL,
        number VARCHAR(20) NULL,
        complement VARCHAR(100) NULL,
        neighborhood VARCHAR(100) NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(2) NULL,
        zipcode VARCHAR(10) NULL,
        product_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        issue_type VARCHAR(100) NULL,
        issue_desc TEXT NULL,
        preferred_action ENUM('enviar_peca', 'trazer_loja', 'outros') DEFAULT 'enviar_peca',
        status ENUM('pending', 'shipped', 'received', 'resolved') DEFAULT 'pending',
        source ENUM('admin', 'customer') DEFAULT 'admin',
        me_order_id VARCHAR(255) NULL,
        tracking_code VARCHAR(100) NULL,
        carrier VARCHAR(50) DEFAULT 'melhorenvio',
        qty_returned INT DEFAULT 0,
        marketplace VARCHAR(50) NULL,
        request_date DATE NULL,
        me_status VARCHAR(50) DEFAULT 'pending',
        wa_notify_tracking TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL
    )");
    
    // Migrations
    try { $pdo->exec("ALTER TABLE rma_tickets MODIFY COLUMN type ENUM('garantia', 'devolucao', 'promessa') DEFAULT 'garantia'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets MODIFY COLUMN preferred_action ENUM('enviar_peca', 'trazer_loja', 'outros', 'logistica_reversa') DEFAULT 'enviar_peca'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN marketplace VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN request_date DATE DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN me_status VARCHAR(50) DEFAULT 'pending'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN wa_notify_tracking TINYINT(1) DEFAULT 1"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN carrier VARCHAR(50) DEFAULT 'melhorenvio'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN refund_price DECIMAL(10,2) DEFAULT 0.00"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN refund_method VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}
} catch (Exception $e) {}

$me = new MelhorEnvioAPI($pdo);
$msg = '';

// Helper para tradução de status do Melhor Envio
function getMeStatusTranslation($status) {
    if (empty($status)) return '';
    $statusMap = [
        'pending' => 'Pendente',
        'released' => 'Liberado / Em Trânsito',
        'posted' => 'Postado',
        'delivered' => 'Entregue',
        'received' => 'Recebido',
        'undelivered' => 'Não Entregue / Falhou',
        'canceled' => 'Cancelado',
        'in_transit' => 'Em Trânsito',
        'waiting_post' => 'Aguardando Postagem',
        'lost' => 'Extraviado',
        'returned' => 'Devolvido'
    ];
    $lower = strtolower(trim($status));
    return isset($statusMap[$lower]) ? $statusMap[$lower] : ucfirst($status);
}

// Helper para obter a cor de cada status
function getMeStatusColor($status) {
    if (empty($status)) return '#888';
    $lower = strtolower(trim($status));
    switch ($lower) {
        case 'delivered':
        case 'received':
            return '#00e676'; // Verde
        case 'released':
        case 'in_transit':
        case 'posted':
            return '#3498db'; // Azul
        case 'pending':
        case 'waiting_post':
            return '#f1c40f'; // Amarelo
        case 'canceled':
        case 'undelivered':
        case 'lost':
            return '#e74c3c'; // Vermelho
        default:
            return '#e67e22'; // Laranja
    }
}

// --- 2. AJAX ENDPOINTS ---
if (isset($_POST['ajax_ai'])) {
    header('Content-Type: application/json');
    $text = $_POST['text'] ?? '';
    $res = [];
    
    // AI Smart Extraction (Gemini)
    require_once __DIR__ . '/../includes/ai_sdr.php';
    $ai = new AIService($pdo);
    
    if ($ai->isActive()) {
        $aiRes = $ai->extractCustomerData($text);
        if (!empty($aiRes)) {
            // Se a IA retornar dados, mesclamos (prioridade para a IA)
            foreach ($aiRes as $k => $v) {
                if (!empty($v)) $res[$k] = $v;
            }
        }
    }

    // Standard Regex Fallback (if AI is off or failed to find specific fields)
    if (empty($res['zipcode'])) {
        if(preg_match('/([\d]{5}[-\s]?[\d]{3})/', $text, $m)) {
            $res['zipcode'] = preg_replace('/\D/', '', $m[1]);
        }
    }
    
    if (empty($res['document'])) {
        if(preg_match('/([\d]{3}\.?[\d]{3}\.?[\d]{3}-?[\d]{2}|[\d]{2}\.?[\d]{3}\.?[\d]{3}\/?[\d]{4}-?[\d]{2})/', $text, $m)) {
            $res['document'] = preg_replace('/\D/', '', $m[1]);
        }
    }
    
    if (empty($res['phone'])) {
        if(preg_match('/(?:\(?\d{2}\)?\s?)?(?:9\s?)?\d{4,5}[-\s]?\d{4}/', $text, $m)) {
            $cleanPhone = preg_replace('/\D/', '', $m[0]);
            if (!isset($res['document']) || (strpos($res['document'], $cleanPhone) === false && strpos($cleanPhone, $res['document']) === false)) {
                $res['phone'] = $cleanPhone;
            }
        }
    }

    if (empty($res['number'])) {
        if(preg_match('/(?:n[º°.]?\s*|número\s*|,?\s+)(\d+[a-zA-Z]?)(?:\s|,|$)/i', $text, $m)) {
            $res['number'] = $m[1];
        }
    }
    
    if (empty($res['name'])) {
        $lines = explode("\n", str_replace("\r", "", $text));
        foreach($lines as $line) {
            $line = trim($line);
            if(empty($line) || strlen($line) < 4) continue;
            // Ignorar cabeçalhos do WhatsApp (ex: [17:34, 11/05/2026] +55...)
            if (preg_match('/^\[\d{2}:\d{2}, \d{2}\/\d{2}\/\d{4}\]/', $line)) continue;
            
            if(preg_match('/(?:cep|rua|av|bairro|avenida|travessa|complemento|n[º°.]|número|serra|peça|comando|shopee|mercado|site|belo horizonte)/i', $line)) continue;
            if(str_word_count($line) >= 2 && !preg_match('/[A-Z]{2}\s*$/', $line)) {
                $res['name'] = trim(preg_replace('/\b(shopee|mercado|venda|loja)\b/i', '', $line));
                break;
            }
        }
    }

    // Marketplace Detection (always run)
    $mktList = ['shopee' => 'Shopee', 'mercado livre' => 'Mercado Livre', 'ml' => 'Mercado Livre', 'amazon' => 'Amazon', 'site' => 'Site', 'venda direta' => 'Venda Direta'];
    foreach($mktList as $key => $val) {
        if(stripos($text, $key) !== false) {
            $res['marketplace'] = $val;
            break;
        }
    }

    echo json_encode($res);
    exit;
}

if (isset($_GET['ajax_quote_rma'])) {
    header('Content-Type: application/json');
    $toZip = $_GET['zipcode'] ?? '';
    $w = (int)($_GET['width'] ?? 15);
    $h = (int)($_GET['height'] ?? 4);
    $l = (int)($_GET['length'] ?? 20);
    $weight = (float)($_GET['weight'] ?? 0.3);
    $val = (float)($_GET['value'] ?? 50.0);
    $fromZip = '03611060'; // Daniel Souza - SP (Tatuapé)
    try { 
        $dbZip = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'me_from_zipcode'")->fetchColumn();
        if ($dbZip && strlen(preg_replace('/\D/', '', $dbZip)) === 8) {
            $fromZip = preg_replace('/\D/', '', $dbZip);
        }
    } catch(Exception $e) {}
    $finalH_quote = $h;
    $products = [['id'=>'rma','width'=>$w,'height'=>$finalH_quote,'length'=>$l,'weight'=>$weight,'insurance_value'=>$val,'quantity'=>1]];
    
    if (isset($_GET['action']) && $_GET['action'] === 'logistica_reversa') {
        $tempZip = $fromZip;
        $fromZip = $toZip;
        $toZip = $tempZip;
    }
    
    $result = $me->calculateShipping($fromZip, $toZip, $products);
    
    // Filtrar e ordenar cotações (Bug 4)
    $filtered = [];
    if (is_array($result)) {
        foreach ($result as $q) {
            if (isset($q['error'])) continue;
            // Garante que temos um preço numérico para o JS
            $q['final_price'] = $q['custom_price'] ?? $q['price'] ?? 0;
            $filtered[] = $q;
        }
        usort($filtered, function($a, $b) {
            return ($a['final_price'] ?? 999) <=> ($b['final_price'] ?? 999);
        });
    }
    
    echo json_encode(['quotes' => $filtered]);
    exit;
}

// --- 3. HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_rma'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO rma_tickets (type, customer_name, document, phone, address, number, complement, neighborhood, city, state, zipcode, product_name, issue_type, issue_desc, preferred_action, marketplace, request_date, wa_notify_tracking) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['type'], $_POST['customer_name'], preg_replace('/\D/', '', $_POST['document']), preg_replace('/\D/', '', $_POST['phone']),
            $_POST['address'], $_POST['number'], $_POST['complement'], $_POST['neighborhood'], $_POST['city'], $_POST['state'], preg_replace('/\D/', '', $_POST['zipcode']),
            $_POST['product_name'], $_POST['issue_type'] ?? 'Outro', $_POST['issue_desc'], $_POST['preferred_action'],
            $_POST['marketplace'] ?? '', $_POST['request_date'] ?? date('Y-m-d'),
            isset($_POST['wa_notify_tracking']) ? 1 : 0
        ]);
        $rma_id = $pdo->lastInsertId();
        $msg = '<div class="alert alert-success">✅ Ocorrência RMA #'.$rma_id.' criada com sucesso!</div>';
        
        // Notificar Admin
        $notif->newRMA($rma_id, $_POST['customer_name'], $_POST['product_name'], $_POST['type']);
        
        // Label logic only if service is selected
        if (!empty($_POST['selected_service'])) {
            $serviceId = (int) $_POST['selected_service'];
            $storeName = 'Daniel Souza';
            $storeDoc = '36542882863';
            $storePhone = '11999999999';
            $storeEmail = 'deletrics@gmail.com';
            $storeAddress = 'Rua Cristiano Osorio';
            $storeNumber = '143';
            $storeDistrict = 'Vila Esperança';
            $storeCity = 'São Paulo';
            $storeState = 'SP';
            $fromZip = '03611060';
            $w = (int)($_POST['width'] ?? 15);
            $h = (int)($_POST['height'] ?? 10);
            $l = (int)($_POST['length'] ?? 20);
            $weight = (float)($_POST['weight'] ?? 0.3);
            $val = (float)($_POST['value'] ?? 50.0);
            
            $storeObj = [ 
                'name' => $storeName, 
                'phone' => $storePhone, 
                'email' => $storeEmail, 
                'document' => (strlen($storeDoc) <= 11) ? $storeDoc : '',
                'company_document' => (strlen($storeDoc) > 11) ? $storeDoc : '',
                'address' => $storeAddress, 
                'number' => $storeNumber, 
                'district' => $storeDistrict, 
                'city' => $storeCity, 
                'state_abbr' => $storeState, 
                'country_id' => 'BR', 
                'postal_code' => $fromZip
            ];
            $customerObj = [ 
                'name' => $_POST['customer_name'], 
                'phone' => preg_replace('/\D/', '', $_POST['phone']), 
                'email' => !empty($_POST['email']) ? $_POST['email'] : 'cliente@fightarcade.temp', 
                'document' => (strlen(preg_replace('/\D/', '', $_POST['document'])) <= 11) ? preg_replace('/\D/', '', $_POST['document']) : '',
                'company_document' => (strlen(preg_replace('/\D/', '', $_POST['document'])) > 11) ? preg_replace('/\D/', '', $_POST['document']) : '',
                'address' => $_POST['address'], 
                'complement' => $_POST['complement'], 
                'number' => $_POST['number'], 
                'district' => $_POST['neighborhood'], 
                'city' => $_POST['city'], 
                'state_abbr' => $_POST['state'], 
                'country_id' => 'BR', 
                'postal_code' => preg_replace('/\D/', '', $_POST['zipcode']) 
            ];
            
            $isReverse = ($_POST['preferred_action'] === 'logistica_reversa');

            $cartPayload = [
                'service' => $serviceId,
                'agency' => null,
                'from' => $isReverse ? $customerObj : $storeObj,
                'to' => $isReverse ? $storeObj : $customerObj,
                'products' => [[ 'name' => 'RMA - ' . $_POST['product_name'], 'quantity' => 1, 'unitary_value' => $val ]],
                'volumes' => [[ 'height' => $h, 'width' => $w, 'length' => $l, 'weight' => $weight ]],
                'options' => [ 'insurance_value' => $val, 'receipt' => false, 'own_hand' => false, 'non_commercial' => true ]
            ];
            $cartResult = $me->addToCart($cartPayload);
            if (isset($cartResult['id'])) {
                // Try to checkout and generate label (requires balance)
                $payResult = $me->checkout([$cartResult['id']]);
                if (isset($payResult['purchase']) || (isset($payResult[0]) && !isset($payResult['error']))) {
                    // Generate label (creates tracking)
                    $me->generateLabel([$cartResult['id']]);
                    
                    // Try to get tracking immediately
                    $trackRes = $me->tracking([$cartResult['id']]);
                    $trackCode = $trackRes[$cartResult['id']]['tracking'] ?? '';
                    
                    $pdo->prepare("UPDATE rma_tickets SET me_order_id = ?, tracking_code = ?, status = 'shipped', carrier = 'melhorenvio' WHERE id = ?")
                        ->execute([$cartResult['id'], $trackCode, $rma_id]);
                    
                    // --- AUTO-NOTIFICAÇÃO: Envia rastreio/etiqueta via WhatsApp ---
                    if ($_POST['preferred_action'] === 'logistica_reversa' && !empty($_POST['phone'])) {
                        $printRes = $me->printLabel([$cartResult['id']]);
                        if (isset($printRes['url']) && !empty($printRes['url'])) {
                            $notif->reverseLabelGenerated($_POST['phone'], $_POST['customer_name'], $rma_id, $printRes['url'], 0);
                        }
                    } elseif (!empty($_POST['phone']) && !empty($trackCode)) {
                        $notif->rmaShipped($_POST['phone'], $_POST['customer_name'], $rma_id, $trackCode, 0);
                    }
                    // -----------------------------------------------------------
                        
                    $msg .= '<div class="alert alert-success">🏷️ Etiqueta gerada e rastreio sincronizado!</div>';
                } else {
                    // Just added to cart, no balance to pay
                    $pdo->prepare("UPDATE rma_tickets SET me_order_id = ?, carrier = 'melhorenvio' WHERE id = ?")->execute([$cartResult['id'], $rma_id]);
                    $msg .= '<div class="alert alert-warning">🛒 <strong>Enviado para o Carrinho!</strong> Como você está sem saldo, pague o frete manualmente no site do Melhor Envio e depois sincronize o pedido. <a href="https://melhorenvio.com.br/carrinho" target="_blank" style="color:inherit; font-weight:bold;">[Ir para o Carrinho]</a></div>';
                }
            } else {
                $envInfo = ($me->getSetting('me_sandbox') === '1') ? 'MODO SANDBOX (TESTE)' : 'MODO PRODUÇÃO (REAL)';
                $errDetail = is_array($cartResult) ? json_encode($cartResult) : $cartResult;
                $msg .= '<div class="alert alert-error">❌ Erro Melhor Envio (' . $envInfo . '): ' . $errDetail . '<br><small>Origem: ' . $fromZip . '</small></div>';
            }
        }

        // Lalamove Logic
        if (!empty($_POST['llm_quotation_id'])) {
            $quotationId = $_POST['llm_quotation_id'];
            $stops = json_decode($_POST['llm_stops_json'], true) ?: [];
            $notifySms = isset($_POST['llm_notify_sms']) && $_POST['llm_notify_sms'] == '1';
            $payMethod = $_POST['llm_payment_method'] ?? 'WALLET';
            $totVal    = (float)($_POST['llm_total_value'] ?? 0);
            
            $res = $llm->createOrder($quotationId, $stops, $_POST['customer_name'], preg_replace('/\D/', '', $_POST['phone']), 'RMA #' . $rma_id, $notifySms, $payMethod, $totVal);
            if (isset($res['data']['orderId'])) {
                $pdo->prepare("UPDATE rma_tickets SET me_order_id = ?, status = 'shipped', carrier = 'lalamove' WHERE id = ?")
                    ->execute([$res['data']['orderId'], $rma_id]);
                $msg .= '<div class="alert alert-success">🏍️ Pedido Lalamove criado com sucesso!</div>';
            } else {
                $msg .= '<div class="alert alert-error">❌ Erro Lalamove: ' . ($res['errors'][0]['message'] ?? 'Falha desconhecida') . '</div>';
            }
        }
    } catch(Exception $e) { $msg = '<div class="alert alert-error">❌ Erro: '.$e->getMessage().'</div>'; }
}

// Handle Update RMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rma'])) {
    $rid = (int)$_POST['rma_id'];
    try {
        $stmt = $pdo->prepare("UPDATE rma_tickets SET type=?, customer_name=?, document=?, phone=?, address=?, number=?, complement=?, neighborhood=?, city=?, state=?, zipcode=?, product_name=?, issue_type=?, issue_desc=?, preferred_action=?, marketplace=?, request_date=?, wa_notify_tracking=?, tracking_code=? WHERE id=?");
        $stmt->execute([
            $_POST['type'], $_POST['customer_name'], preg_replace('/\D/', '', $_POST['document']), preg_replace('/\D/', '', $_POST['phone']),
            $_POST['address'], $_POST['number'], $_POST['complement'], $_POST['neighborhood'], $_POST['city'], $_POST['state'], preg_replace('/\D/', '', $_POST['zipcode']),
            $_POST['product_name'], $_POST['issue_type'] ?? 'Outro', $_POST['issue_desc'], $_POST['preferred_action'],
            $_POST['marketplace'] ?? '', $_POST['request_date'] ?? date('Y-m-d'),
            isset($_POST['wa_notify_tracking']) ? 1 : 0,
            $_POST['tracking_code'] ?? '',
            $rid
        ]);
        
        $msg = '<div class="alert alert-success">✅ Ocorrência #'.$rid.' atualizada!</div>';
        
        // --- LOGÍSTICA NA ATUALIZAÇÃO ---
        // Se selecionou um serviço de frete na edição e ainda não tem frete gerado
        if (!empty($_POST['selected_service'])) {
            $serviceId = (int) $_POST['selected_service'];
            
            // Re-utilizar lógica de frete (Melhor Envio)
            $storeName = 'Daniel Souza';
            $storeDoc = '36542882863';
            $storePhone = '11999999999';
            $storeEmail = 'deletrics@gmail.com';
            $storeAddress = 'Rua Cristiano Osorio';
            $storeNumber = '143';
            $storeDistrict = 'Vila Esperança';
            $storeCity = 'São Paulo';
            $storeState = 'SP';
            $fromZip = '03611060';
            $w = (int)($_POST['width'] ?? 15);
            $h = (int)($_POST['height'] ?? 10);
            $l = (int)($_POST['length'] ?? 20);
            $weight = (float)($_POST['weight'] ?? 0.3);
            $val = (float)($_POST['value'] ?? 50.0);
            
            $storeObj = [ 'name' => $storeName, 'phone' => $storePhone, 'email' => $storeEmail, 'document' => (strlen($storeDoc) <= 11) ? $storeDoc : '', 'company_document' => (strlen($storeDoc) > 11) ? $storeDoc : '', 'address' => $storeAddress, 'number' => $storeNumber, 'district' => $storeDistrict, 'city' => $storeCity, 'state_abbr' => $storeState, 'country_id' => 'BR', 'postal_code' => $fromZip ];
            $customerObj = [ 'name' => $_POST['customer_name'], 'phone' => preg_replace('/\D/', '', $_POST['phone']), 'email' => !empty($_POST['email']) ? $_POST['email'] : 'cliente@fightarcade.temp', 'document' => (strlen(preg_replace('/\D/', '', $_POST['document'])) <= 11) ? preg_replace('/\D/', '', $_POST['document']) : '', 'company_document' => (strlen(preg_replace('/\D/', '', $_POST['document'])) > 11) ? preg_replace('/\D/', '', $_POST['document']) : '', 'address' => $_POST['address'], 'complement' => $_POST['complement'], 'number' => $_POST['number'], 'district' => $_POST['neighborhood'], 'city' => $_POST['city'], 'state_abbr' => $_POST['state'], 'country_id' => 'BR', 'postal_code' => preg_replace('/\D/', '', $_POST['zipcode']) ];
            
            $isReverse = ($_POST['preferred_action'] === 'logistica_reversa');

            $cartPayload = [
                'service' => $serviceId,
                'agency' => null,
                'from' => $isReverse ? $customerObj : $storeObj,
                'to' => $isReverse ? $storeObj : $customerObj,
                'products' => [[ 'name' => 'RMA - ' . $_POST['product_name'], 'quantity' => 1, 'unitary_value' => $val ]],
                'volumes' => [[ 'height' => $h, 'width' => $w, 'length' => $l, 'weight' => $weight ]],
                'options' => [ 'insurance_value' => $val, 'receipt' => false, 'own_hand' => false, 'non_commercial' => true ]
            ];
            $cartResult = $me->addToCart($cartPayload);
            if (isset($cartResult['id'])) {
                $payResult = $me->checkout([$cartResult['id']]);
                if (isset($payResult['purchase']) || (isset($payResult[0]) && !isset($payResult['error']))) {
                    $me->generateLabel([$cartResult['id']]);
                    $trackRes = $me->tracking([$cartResult['id']]);
                    $trackCode = $trackRes[$cartResult['id']]['tracking'] ?? '';
                    $pdo->prepare("UPDATE rma_tickets SET me_order_id = ?, tracking_code = ?, status = 'shipped', carrier = 'melhorenvio' WHERE id = ?")->execute([$cartResult['id'], $trackCode, $rid]);
                    
                    // --- AUTO-NOTIFICAÇÃO: Envia rastreio/etiqueta via WhatsApp ---
                    if ($_POST['preferred_action'] === 'logistica_reversa' && !empty($_POST['phone'])) {
                        $printRes = $me->printLabel([$cartResult['id']]);
                        if (isset($printRes['url']) && !empty($printRes['url'])) {
                            $notif->reverseLabelGenerated($_POST['phone'], $_POST['customer_name'], $rid, $printRes['url'], 0);
                        }
                    } elseif (!empty($_POST['phone']) && !empty($trackCode)) {
                        $notif->rmaShipped($_POST['phone'], $_POST['customer_name'], $rid, $trackCode, 0);
                    }
                    // -----------------------------------------------------------
                    
                    $msg .= '<div class="alert alert-success">🏷️ Etiqueta gerada na atualização!</div>';
                } else {
                    $pdo->prepare("UPDATE rma_tickets SET me_order_id = ?, carrier = 'melhorenvio' WHERE id = ?")->execute([$cartResult['id'], $rid]);
                    $msg .= '<div class="alert alert-warning">🛒 Enviado para o Carrinho! Pague o frete manualmente.</div>';
                }
            }
        }
        
        // Re-utilizar lógica de Lalamove se necessário
        if (!empty($_POST['llm_quotation_id'])) {
            $quotationId = $_POST['llm_quotation_id'];
            $stops = json_decode($_POST['llm_stops_json'], true) ?: [];
            $notifySms = isset($_POST['llm_notify_sms']) && $_POST['llm_notify_sms'] == '1';
            $payMethod = $_POST['llm_payment_method'] ?? 'WALLET';
            $totVal    = (float)($_POST['llm_total_value'] ?? 0);
            
            $res = $llm->createOrder($quotationId, $stops, $_POST['customer_name'], preg_replace('/\D/', '', $_POST['phone']), 'RMA #' . $rid, $notifySms, $payMethod, $totVal);
            if (isset($res['data']['orderId'])) {
                $pdo->prepare("UPDATE rma_tickets SET me_order_id = ?, status = 'shipped', carrier = 'lalamove' WHERE id = ?")->execute([$res['data']['orderId'], $rid]);
                $msg .= '<div class="alert alert-success">🏍️ Pedido Lalamove criado na atualização!</div>';
            }
        }

    } catch(Exception $e) { $msg = '<div class="alert alert-error">❌ Erro: '.$e->getMessage().'</div>'; }
}

// Handle Sync RMA Tracking
if (isset($_GET['sync_rma']) && isset($_GET['id']) && isset($_GET['me_id'])) {
    $tid = (int)$_GET['id'];
    $me_id = $_GET['me_id'];
    
    // Helper closure to parse shipment from tracking response
    $parseShipment = function($response, $meId) {
        $shipment = null;
        if (isset($response[$meId])) {
            $shipment = $response[$meId];
        } elseif (is_array($response)) {
            foreach ($response as $k => $v) {
                if ($k === $meId || (isset($v['id']) && $v['id'] === $meId)) {
                    $shipment = $v;
                    break;
                }
            }
            if (!$shipment && isset($response[0])) {
                $shipment = $response[0];
            }
        }
        return $shipment;
    };

    // Step 1: Check if tracking code is already generated and available
    $res = $me->tracking([$me_id]);
    $shipment = $parseShipment($res, $me_id);
    
    $track = '';
    $protocol = '';
    if ($shipment && is_array($shipment) && !isset($shipment['error'])) {
        $track = $shipment['tracking'] ?? '';
        $protocol = $shipment['protocol'] ?? '';
        if (empty($track) && !empty($protocol) && strpos($protocol, 'ORD-') === false) {
            $track = $protocol;
        }
    }
    
    // Fallback: Se o rastreio for vazio, tenta obter os detalhes do pedido diretamente (GET /orders/{id})
    if (empty($track)) {
        $orderDetail = $me->getOrder($me_id);
        if ($orderDetail && is_array($orderDetail) && !isset($orderDetail['error'])) {
            $track = $orderDetail['tracking'] ?? '';
            if (empty($track)) {
                $protocol = $orderDetail['protocol'] ?? $protocol;
                if (!empty($protocol) && strpos($protocol, 'ORD-') === false) {
                    $track = $protocol;
                }
            }
            if (!$shipment || !is_array($shipment)) {
                $shipment = $orderDetail;
            }
        }
    }
    
    $genRes = null;
    // Step 2: If tracking code is not available, try to generate the label and fetch tracking again
    if (empty($track)) {
        // Force generation
        $genRes = $me->generateLabel([$me_id]);
        
        // Wait 1.5 seconds for the asynchronous label generation to complete
        usleep(1500000);
        
        // Fetch tracking again
        $res = $me->tracking([$me_id]);
        $shipment = $parseShipment($res, $me_id);
        
        if ($shipment && is_array($shipment) && !isset($shipment['error'])) {
            $track = $shipment['tracking'] ?? '';
            $protocol = $shipment['protocol'] ?? '';
            if (empty($track) && !empty($protocol) && strpos($protocol, 'ORD-') === false) {
                $track = $protocol;
            }
        }
        
        // Fallback: Se mesmo após gerar o rastreio continuar vazio, consulta os detalhes do pedido
        if (empty($track)) {
            $orderDetail = $me->getOrder($me_id);
            if ($orderDetail && is_array($orderDetail) && !isset($orderDetail['error'])) {
                $track = $orderDetail['tracking'] ?? '';
                if (empty($track)) {
                    $protocol = $orderDetail['protocol'] ?? $protocol;
                    if (!empty($protocol) && strpos($protocol, 'ORD-') === false) {
                        $track = $protocol;
                    }
                }
                if (!$shipment || !is_array($shipment)) {
                    $shipment = $orderDetail;
                }
            }
        }
    }
    
    // Log response for debug
    try {
        file_put_contents(__DIR__ . '/../me_sync_debug.log', date('[Y-m-d H:i:s] ') . "RMA Sync ID: $me_id - GenResponse: " . json_encode($genRes) . " - TrackResponse: " . json_encode($res) . "\n", FILE_APPEND);
    } catch(Exception $e) {}
    
    if ($shipment && is_array($shipment) && !isset($shipment['error'])) {
        if (!empty($track)) {
            $status = $shipment['status'] ?? 'shipped';
            $pdo->prepare("UPDATE rma_tickets SET tracking_code = ?, status = 'shipped', me_status = ? WHERE id = ?")->execute([$track, $status, $tid]);
            
            // --- Automação do PDF da Reversa ---
            $ticket = $pdo->query("SELECT * FROM rma_tickets WHERE id = $tid")->fetch();
            if ($ticket && $ticket['preferred_action'] === 'logistica_reversa' && !empty($ticket['phone'])) {
                $printRes = $me->printLabel([$me_id]);
                if (isset($printRes['url']) && !empty($printRes['url'])) {
                    require_once __DIR__ . '/../includes/notifications.php';
                    $notif = new NotificationService($pdo, true);
                    $notif->reverseLabelGenerated($ticket['phone'], $ticket['customer_name'], $tid, $printRes['url'], $ticket['user_id'] ?? 0);
                }
            }
            // ------------------------------------
            
            $msg = '<div class="alert alert-success">✅ Sincronizado com sucesso! Rastreio: <strong>' . $track . '</strong></div>';
        } else {
            $status = $shipment['status'] ?? 'pending';
            $pdo->prepare("UPDATE rma_tickets SET me_status = ? WHERE id = ?")->execute([$status, $tid]);
            $detail = (strpos($protocol, 'ORD-') !== false) ? "O Melhor Envio ainda não liberou o código definitivo (apenas ID interno $protocol)." : "Aguardando liberação da transportadora.";
            $msg = '<div class="alert alert-warning">⏳ <strong>Status: ' . strtoupper($status) . '</strong><br>' . $detail . '</div>';
        }
    } else {
        $errorDetail = '';
        if (is_array($res)) {
            if (isset($res['error'])) {
                $errorDetail = is_array($res['error']) ? json_encode($res['error'], JSON_UNESCAPED_UNICODE) : $res['error'];
            } elseif (isset($res['message'])) {
                $errorDetail = $res['message'];
            } elseif (isset($shipment['error'])) {
                $errorDetail = $shipment['error'];
            } else {
                $errorDetail = json_encode($res, JSON_UNESCAPED_UNICODE);
            }
        } elseif (is_string($res)) {
            $errorDetail = $res;
        }
        if (empty($errorDetail) || $errorDetail === '[]' || $errorDetail === 'null') {
            $errorDetail = 'Aguardando processamento ou pagamento no site.';
        }
        $msg = '<div class="alert alert-error">❌ <strong>Erro na Sincronização</strong><br>' . $errorDetail . '</div>';
    }
}

// Bulk Actions
if (isset($_POST['bulk_action']) && !empty($_POST['selected_ids'])) {
    $idsArr = $_POST['selected_ids'];
    $ids = implode(',', array_map('intval', $idsArr));
    $action = $_POST['bulk_action'];
    
    if ($action === 'delete') {
        $pdo->query("DELETE FROM rma_tickets WHERE id IN ($ids)");
        $msg = '<div class="alert alert-success">🗑️ Ocorrências excluídas com sucesso!</div>';
    } elseif ($action === 'resolved') {
        $pdo->query("UPDATE rma_tickets SET status = 'resolved', resolved_at = NOW() WHERE id IN ($ids)");
        $msg = '<div class="alert alert-success">✅ Ocorrências marcadas como resolvidas!</div>';
    } elseif ($action === 'pending') {
        $pdo->query("UPDATE rma_tickets SET status = 'pending' WHERE id IN ($ids)");
        $msg = '<div class="alert alert-success">⏳ Ocorrências marcadas como pendentes!</div>';
    } elseif ($action === 'notify_auto') {
        $pdo->query("UPDATE rma_tickets SET wa_notify_tracking = 1 WHERE id IN ($ids)");
        $msg = '<div class="alert alert-success">🟢 Notificação automática de rastreio HABILITADA para os itens selecionados!</div>';
    } elseif ($action === 'notify_manual') {
        $pdo->query("UPDATE rma_tickets SET wa_notify_tracking = 0 WHERE id IN ($ids)");
        $msg = '<div class="alert alert-success">⚪ Notificação automática de rastreio DESABILITADA para os itens selecionados!</div>';
    } elseif ($action === 'sync_notify_selected') {
        $count = 0;
        $notified = 0;
        $errors = 0;
        
        require_once __DIR__ . '/../includes/notifications.php';
        $notif = new NotificationService($pdo, true);
        
        foreach ($idsArr as $tid) {
            $ticket = $pdo->query("SELECT * FROM rma_tickets WHERE id = $tid")->fetch();
            if ($ticket && !empty($ticket['me_order_id'])) {
                try {
                    $trackData = $me->tracking([$ticket['me_order_id']]);
                    $shipment = $trackData[$ticket['me_order_id']] ?? null;
                    $track = '';
                    if ($shipment && is_array($shipment) && !isset($shipment['error'])) {
                        $track = $shipment['tracking'] ?? '';
                    }
                    // Fallback para buscar dados detalhados se o rastreio for vazio
                    if (empty($track)) {
                        $orderDetail = $me->getOrder($ticket['me_order_id']);
                        if ($orderDetail && is_array($orderDetail) && !isset($orderDetail['error'])) {
                            $track = $orderDetail['tracking'] ?? '';
                            $shipment = $orderDetail;
                        }
                    }
                    if ($shipment) {
                        $newStatus = $shipment['status'] ?? '';
                        if ($newStatus) {
                            $oldStatus = $ticket['me_status'] ?? '';
                            $currentTrack = !empty($ticket['tracking_code']) ? $ticket['tracking_code'] : $track;
                            
                            if (!empty($currentTrack) && empty($ticket['tracking_code'])) {
                                $pdo->prepare("UPDATE rma_tickets SET tracking_code = ?, status = 'shipped', me_status = ? WHERE id = ?")->execute([$currentTrack, $newStatus, $tid]);
                            } else {
                                $pdo->prepare("UPDATE rma_tickets SET me_status = ? WHERE id = ?")->execute([$newStatus, $tid]);
                            }
                            $count++;
                            
                            // Send WhatsApp notification immediately for the current status (manual trigger)
                            if (!empty($ticket['phone']) && !empty($currentTrack)) {
                                $sent = $notif->trackingUpdate($ticket['phone'], $ticket['customer_name'], $currentTrack, $newStatus, $ticket['user_id'] ?? 0);
                                if ($sent) {
                                    $notified++;
                                } else {
                                    $errors++;
                                }
                            }
                            
                            // Auto resolve / receive if delivered
                            if ($newStatus === 'delivered' && $ticket['status'] !== 'resolved' && $ticket['status'] !== 'received') {
                                if ($ticket['preferred_action'] === 'logistica_reversa') {
                                    $pdo->prepare("UPDATE rma_tickets SET status = 'received' WHERE id = ?")->execute([$tid]);
                                } else {
                                    $pdo->prepare("UPDATE rma_tickets SET status = 'resolved', resolved_at = NOW() WHERE id = ?")->execute([$tid]);
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errors++;
                }
            }
        }
        $msg = '<div class="alert alert-success">✅ Sincronizados: ' . $count . ' pacote(s). Clientes notificados via WhatsApp: ' . $notified . ($errors > 0 ? " ($errors falhas)" : "") . '</div>';
    } elseif ($action === 'whatsapp') {
        $count = 0;
        $errors = 0;
        foreach ($idsArr as $rid) {
            try {
                $ticket = $pdo->query("SELECT * FROM rma_tickets WHERE id = $rid")->fetch();
                if ($ticket && !empty($ticket['phone'])) {
                    $track = !empty($ticket['tracking_code']) ? $ticket['tracking_code'] : ($ticket['me_order_id'] ?? '');
                    $notif->rmaShipped($ticket['phone'], $ticket['customer_name'], $rid, $track);
                    $count++;
                }
            } catch (Exception $e) { $errors++; }
        }
        $msg = '<div class="alert alert-success">🔔 ' . $count . ' notificações enviadas via WhatsApp!' . ($errors > 0 ? " ($errors falhas)" : "") . '</div>';
    }
}

// Sync Tracking Status (Melhor Envio) manually
if (isset($_GET['sync_me'])) {
    $tid = (int)$_GET['sync_me'];
    $ticket = $pdo->query("SELECT * FROM rma_tickets WHERE id = $tid")->fetch();
    if ($ticket && !empty($ticket['me_order_id'])) {
        $trackData = $me->tracking([$ticket['me_order_id']]);
        $shipment = $trackData[$ticket['me_order_id']] ?? null;
        $track = '';
        if ($shipment && is_array($shipment) && !isset($shipment['error'])) {
            $track = $shipment['tracking'] ?? '';
        }
        // Fallback para buscar dados detalhados se o rastreio for vazio
        if (empty($track)) {
            $orderDetail = $me->getOrder($ticket['me_order_id']);
            if ($orderDetail && is_array($orderDetail) && !isset($orderDetail['error'])) {
                $track = $orderDetail['tracking'] ?? '';
                $shipment = $orderDetail;
            }
        }
        if ($shipment) {
            $newStatus = $shipment['status'] ?? '';
            if ($newStatus) {
                $oldStatus = $ticket['me_status'] ?? '';
                $currentTrack = !empty($ticket['tracking_code']) ? $ticket['tracking_code'] : $track;
                
                if (!empty($currentTrack) && empty($ticket['tracking_code'])) {
                    $pdo->prepare("UPDATE rma_tickets SET tracking_code = ?, status = 'shipped', me_status = ? WHERE id = ?")->execute([$currentTrack, $newStatus, $tid]);
                } else {
                    $pdo->prepare("UPDATE rma_tickets SET me_status = ? WHERE id = ?")->execute([$newStatus, $tid]);
                }
                
                // Dispara WhatsApp via Evolution API se habilitado
                if ($newStatus !== $oldStatus && !empty($ticket['phone']) && !empty($currentTrack) && !empty($ticket['wa_notify_tracking'])) {
                    require_once __DIR__ . '/../includes/notifications.php';
                    $notif = new NotificationService($pdo, true);
                    $notif->trackingUpdate($ticket['phone'], $ticket['customer_name'], $currentTrack, $newStatus, $ticket['user_id'] ?? 0);
                }
                
                // Completa o RMA automaticamente
                if ($newStatus === 'delivered' && $ticket['status'] !== 'resolved' && $ticket['status'] !== 'received') {
                    if ($ticket['preferred_action'] === 'logistica_reversa') {
                        $pdo->prepare("UPDATE rma_tickets SET status = 'received' WHERE id = ?")->execute([$tid]);
                    } else {
                        $pdo->prepare("UPDATE rma_tickets SET status = 'resolved', resolved_at = NOW() WHERE id = ?")->execute([$tid]);
                    }
                }
                
                $msg = '<div class="alert alert-success">✅ Status sincronizado: ' . getMeStatusTranslation($newStatus) . '</div>';
            } else {
                $msg = '<div class="alert alert-warning">⚠️ API retornou status vazio. Tente novamente em instantes.</div>';
            }
        } else {
            $msg = '<div class="alert alert-error">❌ Não foi possível obter dados de rastreio para este ID.</div>';
        }
    } else {
        $msg = '<div class="alert alert-error">❌ Este RMA não possui um ID do Melhor Envio vinculado. Sincronização automática indisponível.</div>';
    }
}

// Mark Status (Received/Resolved/Pending)
if (isset($_GET['mark_status']) && isset($_GET['id'])) {
    $st = $_GET['mark_status'];
    $tid = (int)$_GET['id'];
    if ($st === 'resolved') {
        $pdo->query("UPDATE rma_tickets SET status = 'resolved', resolved_at = NOW() WHERE id = $tid");
        
        // Notificar Cliente
        $ticket = $pdo->query("SELECT customer_name, phone FROM rma_tickets WHERE id = $tid")->fetch();
        if ($ticket) {
            $notif->rmaResolved($ticket['phone'], $ticket['customer_name'], $tid);
        }
    }
    elseif ($st === 'received') $pdo->query("UPDATE rma_tickets SET status = 'received' WHERE id = $tid");
    elseif ($st === 'pending') $pdo->query("UPDATE rma_tickets SET status = 'pending' WHERE id = $tid");
    header("Location: rma.php");
    exit;
}

// Handle Resolve with Refund
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_rma'])) {
    $tid = (int)$_POST['resolve_rma_id'];
    $refundAmount = (float)str_replace(',', '.', $_POST['refund_amount'] ?? '0');
    $refundDesc = trim($_POST['refund_desc'] ?? '');

    try {
        $pdo->beginTransaction();
        
        // 1. Mark as resolved and save refund details
        $stmt = $pdo->prepare("UPDATE rma_tickets SET status = 'resolved', resolved_at = NOW(), refund_price = ?, refund_method = ? WHERE id = ?");
        $stmt->execute([$refundAmount, 'Estorno de RMA', $tid]);

        // 2. Notificar Cliente do RMA resolvido
        $ticket = $pdo->query("SELECT customer_name, phone FROM rma_tickets WHERE id = $tid")->fetch();
        if ($ticket) {
            $notif->rmaResolved($ticket['phone'], $ticket['customer_name'], $tid);
        }

        // 3. Process Refund if applicable
        if ($refundAmount > 0 && $ticket && !empty($ticket['phone'])) {
            $cleanPhone = preg_replace('/\D/', '', $ticket['phone']);
            // Find user by phone
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE REPLACE(REPLACE(REPLACE(phone,'+',''),'-',''),' ','') LIKE ? LIMIT 1");
            $userStmt->execute(['%' . substr($cleanPhone, -9)]);
            $user = $userStmt->fetch();

            if ($user) {
                $uid = $user['id'];
                $desc = empty($refundDesc) ? "Estorno ref. RMA #$tid (Garantia/Devolução)" : $refundDesc;
                
                // Add as payment (credit)
                $stmt = $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$uid, $refundAmount, 'Estorno de RMA', $desc]);

                // Sync dynamic current_debt for the user
                $pdo->prepare("UPDATE users u SET current_debt = (
                    (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                    (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
                ) WHERE id = ?")->execute([$uid]);
                
                $msg = '<div class="alert alert-success">✅ Ocorrência #'.$tid.' resolvida e estorno de R$ '.number_format($refundAmount, 2, ',', '.').' creditado para o cliente!</div>';
            } else {
                $msg = '<div class="alert alert-warning">✅ Ocorrência #'.$tid.' resolvida, porém o cliente não foi encontrado no Financeiro pelo celular informado. Estorno cancelado.</div>';
            }
        } else {
            $msg = '<div class="alert alert-success">✅ Ocorrência #'.$tid.' resolvida!</div>';
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = '<div class="alert alert-error">❌ Erro ao resolver RMA: '.$e->getMessage().'</div>';
    }
}

// AJAX: Send WhatsApp Notification manually
if (isset($_GET['ajax_send_wa']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $tid = (int)$_GET['id'];
    $ticket = $pdo->query("SELECT * FROM rma_tickets WHERE id = $tid")->fetch();
    if ($ticket && !empty($ticket['phone'])) {
        $track = !empty($ticket['tracking_code']) ? $ticket['tracking_code'] : ($ticket['me_order_id'] ?? '');
        
        if (($ticket['preferred_action'] ?? '') === 'logistica_reversa') {
            // Fetch reverse label URL
            $labelUrl = '';
            if (!empty($ticket['me_order_id']) && ($ticket['carrier'] ?? 'melhorenvio') === 'melhorenvio') {
                $printRes = $me->printLabel([$ticket['me_order_id']]);
                if (isset($printRes['url']) && !empty($printRes['url'])) {
                    $labelUrl = $printRes['url'];
                }
            }
            $res = $notif->reverseReminder($ticket['phone'], $ticket['customer_name'], $tid, $labelUrl, $ticket['user_id'] ?? 0);
            
            $msgFallback = "Olá *{$ticket['customer_name']}*! 🔄\n\nPassando para lembrar de realizar o envio do seu produto referente ao RMA *#$tid* via Logística Reversa.\n";
            if ($labelUrl) {
                $msgFallback .= "📄 Etiqueta de envio: $labelUrl\n";
            } else {
                $msgFallback .= "📄 Etiqueta disponível no seu painel.\n";
            }
            
            // Puxa e anexa a agência mais próxima no fallback manual
            $agencyText = $notif->getClosestAgencyStringByRma($tid);
            if (!empty($agencyText)) {
                $msgFallback .= $agencyText;
            }
            
            $msgFallback .= "\nPrecisamos receber a peça antiga de volta para dar andamento ao seu caso. Qualquer dúvida, estamos à disposição! 📦";
        } else {
            $res = $notif->rmaShipped($ticket['phone'], $ticket['customer_name'], $tid, $track);
            
            $msgFallback = "Olá *{$ticket['customer_name']}*! 🛠️\n\nSua peça de reposição/garantia *#$tid* foi enviada!\n";
            if ($track) {
                $msgFallback .= "📦 Rastreio: *$track*\n🔍 https://www.melhorrastreio.com.br/rastreio/$track\n";
            }
            $msgFallback .= "\nAguardamos seu feedback após o recebimento na *Fight Arcade*! 🚀";
        }
        
        $fallbackUrl = NotificationService::waLink($ticket['phone'], $msgFallback);
        echo json_encode(['success' => $res, 'fallback_url' => $fallbackUrl]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ticket ou telefone não encontrado']);
    }
    exit;
}

// AJAX: Send Personalized VIP Message (RMA)
if (isset($_GET['ajax_personalized_msg_rma']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $tid = (int)$_GET['id'];
    $rawMsg = $_GET['msg'] ?? '';
    
    $ticket = $pdo->query("SELECT * FROM rma_tickets WHERE id = $tid")->fetch();
    if (!$ticket || empty($ticket['phone'])) {
        echo json_encode(['success' => false, 'error' => 'Ticket ou telefone não encontrado']);
        exit;
    }
    
    // Check if we need to replace {link_etiqueta} or {rastreio}
    $tracking = !empty($ticket['tracking_code']) ? $ticket['tracking_code'] : '';
    $labelUrl = '';
    if (!empty($ticket['me_order_id']) && ($ticket['carrier'] ?? 'melhorenvio') === 'melhorenvio') {
        $printRes = $me->printLabel([$ticket['me_order_id']]);
        if (isset($printRes['url']) && !empty($printRes['url'])) {
            $labelUrl = $printRes['url'];
        }
    }
    
    // Puxa a agência mais próxima
    $agencyText = $notif->getClosestAgencyStringByRma($tid);
    
    // Replace placeholders
    $finalMsg = str_replace(
        ['{nome}', '{ticket}', '{produto}', '{rastreio}', '{link_etiqueta}', '{agencia}'],
        [
            $ticket['customer_name'],
            $tid,
            $ticket['product_name'] ?? 'Produto',
            $tracking,
            $labelUrl ?: '(etiqueta disponível no painel)',
            $agencyText ?: ''
        ],
        $rawMsg
    );
    
    $res = $notif->send($ticket['phone'], $finalMsg);
    
    if (!$res) {
        // Build fallback WhatsApp link
        $fallbackUrl = NotificationService::waLink($ticket['phone'], $finalMsg);
        echo json_encode(['success' => false, 'error' => 'API offline. Use o WhatsApp Web.', 'fallback_url' => $fallbackUrl]);
    } else {
        echo json_encode(['success' => true]);
    }
    exit;
}

// Fetch for Edit or Clone
$edit_data = null;
if (isset($_GET['edit']) || isset($_GET['clone'])) {
    $tid = (int)($_GET['edit'] ?? $_GET['clone']);
    $stmt = $pdo->prepare("SELECT * FROM rma_tickets WHERE id = ?");
    $stmt->execute([$tid]);
    $edit_data = $stmt->fetch();
    // If cloning, we don't want the ID or old shipping info
    if (isset($_GET['clone']) && $edit_data) {
        unset($edit_data['id']);
        unset($edit_data['me_order_id']);
        unset($edit_data['tracking_code']);
        $edit_data['status'] = 'pending';
        $edit_data['request_date'] = date('Y-m-d');
    }
} elseif (isset($_GET['customer_id'])) {
    $cid = (int)$_GET['customer_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$cid]);
    $user = $stmt->fetch();
    if ($user) {
        $edit_data = [
            'customer_name' => $user['name'],
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? '',
            'document' => $user['document'] ?? '',
            'zipcode' => $user['zipcode'] ?? '',
            'address' => $user['address'] ?? '',
            'number' => $user['number'] ?? '',
            'complement' => $user['complement'] ?? '',
            'neighborhood' => $user['neighborhood'] ?? '',
            'city' => $user['city'] ?? '',
            'state' => $user['state'] ?? '',
            'request_date' => date('Y-m-d'),
            'status' => 'pending',
            'wa_notify_tracking' => 1
        ];
    }
}


// Cleanup: Se houver rastreio começando com ORD- (erro de captura anterior), limpar para permitir novo sync
$pdo->query("UPDATE rma_tickets SET tracking_code = NULL WHERE tracking_code LIKE 'ORD-%'");

$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM rma_tickets")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM rma_tickets WHERE status = 'pending'")->fetchColumn(),
    'resolved' => $pdo->query("SELECT COUNT(*) FROM rma_tickets WHERE status = 'resolved'")->fetchColumn(),
    'this_month' => $pdo->query("SELECT COUNT(*) FROM rma_tickets WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn(),
];

// Top defective products
$top_products = $pdo->query("SELECT product_name, COUNT(*) as qty FROM rma_tickets WHERE type = 'garantia' GROUP BY product_name ORDER BY qty DESC LIMIT 5")->fetchAll();

// Most common issues
$top_issues = $pdo->query("SELECT issue_type, COUNT(*) as qty FROM rma_tickets GROUP BY issue_type ORDER BY qty DESC LIMIT 5")->fetchAll();

$tickets = $pdo->query("SELECT * FROM rma_tickets ORDER BY CASE WHEN status IN ('pending','shipped') THEN 1 ELSE 2 END, created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMA & Pós-Venda | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --bg: #0b0e14; --surface: #141820; --border: #252d3d; --primary: #f1c40f; --text: #e8eaf0; --muted: #5a6478; --radius: 12px; --red: #e74c3c; --green: #2ecc71; --blue: #3498db; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        .glass-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.5rem; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-box { background: var(--surface); border-radius: 10px; padding: 1.2rem; border-left: 4px solid var(--primary); }
        .stat-val { font-size: 1.8rem; font-weight: 800; margin: 5px 0; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.75rem; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; font-weight: 700; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; background: #0d1017; border: 1px solid var(--border); color: #fff; border-radius: 8px; outline: none; }
        .form-group input:focus { border-color: var(--primary); }
        
        .ai-box { background: rgba(241, 196, 15, 0.05); border: 1px dashed var(--primary); border-radius: 10px; padding: 1.2rem; margin-bottom: 2rem; }
        .ai-box h4 { margin: 0 0 8px; font-size: 0.9rem; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-pending { background: rgba(243, 156, 18, 0.1); color: #f39c12; }
        .status-shipped { background: rgba(52, 152, 219, 0.1); color: #3498db; }
        .status-resolved { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; font-size: 0.7rem; color: var(--muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 12px; border-bottom: 1px solid #1a1e26; vertical-align: middle; }
        
        .btn-premium { background: var(--primary); color: #000; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.2s; }
        .btn-premium:hover { transform: scale(1.02); background: #d4ac0d; }
        
        /* New WhatsApp Button Style */
        .btn-wa-notif { 
            background: #25d366; 
            color: #fff; 
            border: none; 
            padding: 6px 10px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            transition: 0.3s; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            box-shadow: 0 0 10px rgba(37, 211, 102, 0.2);
        }
        .btn-wa-notif:hover { background: #128c7e; transform: scale(1.1); box-shadow: 0 0 15px rgba(37, 211, 102, 0.4); }
        .btn-wa-notif.loading { background: #555; cursor: wait; }
        .btn-wa-notif.success { background: #27ae60; }
        .btn-wa-notif.error { background: #e74c3c; }

        .btn-wa-vip { background: #9b59b6; }
        .btn-wa-vip:hover { background: #8e44ad; }

        /* Modal Personalized Msg */
        .modal-vip { display: none; justify-content: center; align-items: center; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); }
        .modal-vip-content { background: #1a1e2a; padding: 2rem; border: 1px solid #444; width: 550px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); color: #fff; position: relative; }
        .close-vip { position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; color: #666; transition: 0.2s; z-index: 1; }
        .close-vip:hover { color: #fff; }
        .vip-option { display: flex; align-items: flex-start; gap: 12px; padding: 12px; border: 1px solid #333; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: 0.2s; background: #111; text-align: left; }
        .vip-option:hover { border-color: #9b59b6; background: #1c1f26; }
        .vip-option input[type="radio"] { margin-top: 4px; width: 18px; height: 18px; flex-shrink: 0; cursor: pointer; }
        .vip-text-content { flex: 1; }
        .vip-option strong { display: block; color: #9b59b6; margin-bottom: 3px; }
        .vip-option span { font-size: 0.8rem; color: #888; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h1 style="margin:0;"><i class="fas fa-hand-holding-heart" style="color:var(--primary)"></i> RMA & Pós-Venda</h1>
                <p style="color:var(--muted); margin:5px 0 0;">Gestão de ocorrências e devoluções.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </div>

        <?php echo $msg; ?>

        <div class="stat-grid">
            <div class="stat-box"><div>Total</div><div class="stat-val"><?php echo $stats['total']; ?></div></div>
            <div class="stat-box" style="border-color:var(--red)"><div>Pendentes</div><div class="stat-val"><?php echo $stats['pending']; ?></div></div>
            <div class="stat-box" style="border-color:var(--green)"><div>Resolvidos</div><div class="stat-val"><?php echo $stats['resolved']; ?></div></div>
            <div class="stat-box" style="border-color:var(--blue)"><div>Neste Mês</div><div class="stat-val"><?php echo $stats['this_month']; ?></div></div>
        </div>

        <!-- RMA Report Section -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:2rem;">
            <div class="glass-card" style="margin-bottom:0;">
                <h4 style="color:var(--red); margin-bottom:1rem;"><i class="fas fa-exclamation-triangle"></i> Produtos com Mais Defeitos</h4>
                <table style="font-size:0.85rem;">
                    <thead>
                        <tr><th>Produto</th><th style="text-align:right;">Ocorrências</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($top_products as $tp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tp['product_name']); ?></td>
                            <td style="text-align:right; font-weight:bold; color:var(--red);"><?php echo $tp['qty']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($top_products)): ?><tr><td colspan="2" style="text-align:center; color:var(--muted);">Nenhum dado</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="glass-card" style="margin-bottom:0;">
                <h4 style="color:var(--blue); margin-bottom:1rem;"><i class="fas fa-chart-pie"></i> Problemas Recorrentes</h4>
                <table style="font-size:0.85rem;">
                    <thead>
                        <tr><th>Tipo de Problema</th><th style="text-align:right;">Frequência</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($top_issues as $ti): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ti['issue_type'] ?: 'Outro'); ?></td>
                            <td style="text-align:right; font-weight:bold; color:var(--blue);"><?php echo $ti['qty']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($top_issues)): ?><tr><td colspan="2" style="text-align:center; color:var(--muted);">Nenhum dado</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card">
            <h3><?php echo $edit_data ? '📝 Editar Ocorrência #'.$edit_data['id'] : '➕ Nova Ocorrência'; ?></h3>
            
            <?php if (!$edit_data): ?>
            <div class="ai-box">
                <h4><i class="fas fa-magic"></i> Assistente IA (Opcional)</h4>
                <p style="font-size:0.8rem; color:var(--muted); margin-bottom:12px;">Cole a mensagem do cliente para extrair endereço.</p>
                <div style="display:flex; gap:10px;">
                    <textarea id="ai_text" rows="1" style="flex:1; background:#000; border:1px solid #333; color:#fff; padding:10px; border-radius:6px;" placeholder="Ex: Meu CEP é 00000..."></textarea>
                    <button type="button" onclick="runAI()" class="btn btn-sm" style="background:var(--primary); color:#000; font-weight:800; padding:0 20px;">EXTRAIR</button>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="rmaForm">
                <?php if ($edit_data && isset($edit_data['id'])): ?>
                    <input type="hidden" name="rma_id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group"><label>Tipo de Ticket</label><select name="type">
                        <option value="garantia" <?php echo ($edit_data && $edit_data['type']=='garantia') ? 'selected' : ''; ?>>Garantia</option>
                        <option value="devolucao" <?php echo ($edit_data && $edit_data['type']=='devolucao') ? 'selected' : ''; ?>>Devolução</option>
                        <option value="promessa" <?php echo ($edit_data && $edit_data['type']=='promessa') ? 'selected' : ''; ?>>🎁 Promessa / Brinde</option>
                    </select></div>
                    <div class="form-group"><label>Nome do Cliente</label><input type="text" name="customer_name" id="f_name" required value="<?php echo htmlspecialchars($edit_data['customer_name'] ?? ''); ?>"></div>
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <div style="display:flex; gap:5px;">
                            <input type="text" name="document" id="f_doc" required value="<?php echo htmlspecialchars($edit_data['document'] ?? ''); ?>" style="flex:1;">
                            <button type="button" onclick="generateCPF('f_doc')" class="btn btn-sm" style="background:#444; color:#fff; white-space:nowrap; padding:0 10px;">🎲 Gerar</button>
                        </div>
                    </div>
                    <div class="form-group"><label>WhatsApp/Telefone</label><input type="text" name="phone" id="f_phone" value="<?php echo htmlspecialchars($edit_data['phone'] ?? ''); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>CEP</label><input type="text" name="zipcode" id="f_zip" required onblur="fetchCep()" value="<?php echo htmlspecialchars($edit_data['zipcode'] ?? ''); ?>"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Endereço</label><input type="text" name="address" id="f_addr" required value="<?php echo htmlspecialchars($edit_data['address'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Número</label><input type="text" name="number" id="f_num" required value="<?php echo htmlspecialchars($edit_data['number'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Complemento</label><input type="text" name="complement" id="f_complement" value="<?php echo htmlspecialchars($edit_data['complement'] ?? ''); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Bairro</label><input type="text" name="neighborhood" id="f_bairro" value="<?php echo htmlspecialchars($edit_data['neighborhood'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Cidade</label><input type="text" name="city" id="f_city" value="<?php echo htmlspecialchars($edit_data['city'] ?? ''); ?>"></div>
                    <div class="form-group"><label>UF</label><input type="text" name="state" id="f_uf" value="<?php echo htmlspecialchars($edit_data['state'] ?? ''); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Marketplace / Origem</label><input type="text" name="marketplace" placeholder="Ex: Shopee Deletrics, Mercado Livre..." value="<?php echo htmlspecialchars($edit_data['marketplace'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Data da Solicitação</label><input type="date" name="request_date" value="<?php echo $edit_data['request_date'] ?? date('Y-m-d'); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Produto / Peça</label><input type="text" name="product_name" required value="<?php echo htmlspecialchars($edit_data['product_name'] ?? ''); ?>"></div>
                    <div class="form-group">
                        <label>Ação / Logística</label>
                        <select name="preferred_action" id="f_action" onchange="toggleShippingArea()">
                            <option value="enviar_peca" <?php echo ($edit_data && $edit_data['preferred_action']=='enviar_peca') ? 'selected' : ''; ?>>📦 Enviar Peça (Frete)</option>
                            <option value="logistica_reversa" <?php echo ($edit_data && $edit_data['preferred_action']=='logistica_reversa') ? 'selected' : ''; ?>>🔄 Logística Reversa (Devolução)</option>
                            <option value="trazer_loja" <?php echo ($edit_data && $edit_data['preferred_action']=='trazer_loja') ? 'selected' : ''; ?>>🏪 Trazer à Loja / Retirada</option>
                            <option value="outros" <?php echo ($edit_data && $edit_data['preferred_action']=='outros') ? 'selected' : ''; ?>>❓ Outro / Manual</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; gap:10px; padding-top:25px;">
                        <input type="checkbox" name="wa_notify_tracking" id="f_wa_notify" value="1" <?php echo (!isset($edit_data) || ($edit_data && $edit_data['wa_notify_tracking'])) ? 'checked' : ''; ?> style="width:20px; height:20px; cursor:pointer;">
                        <label for="f_wa_notify" style="cursor:pointer; font-weight:bold; color:#00e676;">🔔 Notificar Rastreio via WhatsApp (Auto)</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Motivo do RMA</label>
                    <select name="issue_type">
                        <?php $it = $edit_data['issue_type'] ?? ''; ?>
                        <option value="Defeito de Fábrica" <?php echo $it=='Defeito de Fábrica' ? 'selected' : ''; ?>>Defeito de Fábrica</option>
                        <option value="Falta de Item" <?php echo $it=='Falta de Item' ? 'selected' : ''; ?>>Falta de Item</option>
                        <option value="Item Errado" <?php echo $it=='Item Errado' ? 'selected' : ''; ?>>Item Errado</option>
                        <option value="Arrependimento" <?php echo $it=='Arrependimento' ? 'selected' : ''; ?>>Arrependimento</option>
                        <option value="Brinde / Promessa" <?php echo $it=='Brinde / Promessa' ? 'selected' : ''; ?>>Brinde / Promessa</option>
                    </select>
                </div>
                <div class="form-group" style="margin-top:10px;">
                    <label>Rastreio (Opcional - Adicionar Manualmente)</label>
                    <input type="text" name="tracking_code" placeholder="Ex: LGI-ME2628... ou SY123456789BR" value="<?php echo htmlspecialchars($edit_data['tracking_code'] ?? ''); ?>">
                </div>
                <div class="form-group"><label>Observações Detalhadas</label><textarea name="issue_desc" rows="2"><?php echo htmlspecialchars($edit_data['issue_desc'] ?? ''); ?></textarea></div>
                
                <div id="shippingArea" style="background:rgba(0,0,0,0.15); padding:1.5rem; border-radius:10px; margin-top:1rem; border:1px solid var(--border); <?php echo ($edit_data && !in_array($edit_data['preferred_action'], ['enviar_peca', 'logistica_reversa'])) ? 'display:none;' : ''; ?>">
                    <label style="display:block; margin-bottom:12px; font-weight:800; font-size:0.75rem; color:var(--primary);"><i class="fas fa-truck"></i> Melhor Envio (Opcional)</label>
                    <div class="form-grid" style="grid-template-columns: repeat(5, 1fr);">
                        <div class="form-group"><label>Peso</label><input type="text" name="weight" id="f_wgt" value="0.3"></div>
                        <div class="form-group"><label>Altura</label><input type="text" name="height" id="f_h" value="4"></div>
                        <div class="form-group"><label>Larg.</label><input type="text" name="width" id="f_w" value="11"></div>
                        <div class="form-group"><label>Comp.</label><input type="text" name="length" id="f_l" value="16"></div>
                        <div class="form-group"><label>Valor</label><input type="text" name="value" id="f_val" value="50.00"></div>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <button type="button" onclick="quoteRma()" class="btn btn-sm" style="flex:1; background:var(--blue); color:#fff; font-weight:800; padding:10px; border-radius:6px;">COTAR MELHOR ENVIO</button>
                        <?php if($llm->isConfigured()): ?>
                            <button type="button" onclick="quoteLlmRma()" class="btn btn-sm" style="flex:1; background:#FF6600; color:#fff; font-weight:800; padding:10px; border-radius:6px;">COTAR LALAMOVE 🏍️</button>
                        <?php endif; ?>
                        <button type="button" onclick="quoteUberRma()" class="btn btn-sm" style="flex:1; background:#000; color:#fff; font-weight:800; padding:10px; border-radius:6px;">COTAR UBER 🚙</button>
                    </div>
                    <div id="quoteArea" style="margin-top:1rem; display:none;">
                        <div id="quoteResults"></div>
                        <input type="hidden" name="selected_service" id="selected_service">
                        <input type="hidden" name="llm_quotation_id" id="llm_quotation_id">
                        <input type="hidden" name="llm_stops_json" id="llm_stops_json">
                        <input type="hidden" name="llm_total_value" id="llm_total_value">
                        
                        <div id="llm_extra_options" style="display:none; flex-direction:column; gap:10px; margin-top:15px; border-top:1px solid #333; padding-top:15px;">
                            <label style="display:flex;align-items:center;gap:8px;color:#bbb;font-size:0.9rem;cursor:pointer">
                                <span style="flex:1">Forma de Pagamento:</span>
                                <select name="llm_payment_method" style="background:#222;color:#fff;border:1px solid #444;padding:5px;border-radius:4px;">
                                    <option value="WALLET">Carteira Fight Arcade (Pré-pago)</option>
                                    <option value="CASH">Dinheiro - Receber no Local (Cliente Paga)</option>
                                </select>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;color:#bbb;font-size:0.9rem;cursor:pointer">
                                <input type="checkbox" name="llm_notify_sms" value="1" checked>
                                Avisar cliente via SMS (Lalamove) sobre o envio
                            </label>
                        </div>
                    </div>
                </div>

                <div style="text-align:right; margin-top:2rem; display:flex; gap:10px; justify-content:flex-end;">
                    <?php if ($edit_data && isset($edit_data['id'])): ?>
                        <a href="rma.php" class="btn btn-secondary">CANCELAR</a>
                        <button type="submit" name="update_rma" class="btn-premium">💾 ATUALIZAR OCORRÊNCIA</button>
                    <?php else: ?>
                        <button type="submit" name="create_rma" class="btn-premium">💾 SALVAR OCORRÊNCIA</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="glass-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3>📋 Histórico Recente</h3>
                <form method="POST" id="bulkForm" style="display:flex; gap:10px; align-items:center;">
                    <select name="bulk_action" style="background:#222; color:#fff; border:1px solid #444; border-radius:4px; padding:5px;">
                        <option value="">Ações em Massa</option>
                        <option value="resolved">Marcar Resolvido</option>
                        <option value="pending">Marcar Pendente</option>
                        <option value="whatsapp">🔔 Notificar Envio / Reversa (Inicial)</option>
                        <option value="notify_auto">🟢 Habilitar Notificação Automática</option>
                        <option value="notify_manual">⚪ Desabilitar Notificação Automática</option>
                        <option value="sync_notify_selected">📦 Sincronizar e Disparar Movimentação</option>
                        <option value="delete">Excluir Selecionados</option>
                    </select>
                    <button type="submit" class="btn btn-sm" style="background:var(--primary); color:#000;">Aplicar</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr>
                        <th><input type="checkbox" onclick="toggleAll(this)"></th>
                        <th>ID</th><th>Cliente</th><th>Produto</th><th>Logística</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach($tickets as $t): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $t['id']; ?>"></td>
                            <td><a href="?edit=<?php echo $t['id']; ?>" style="color:var(--primary); font-weight:bold;">#<?php echo $t['id']; ?></a></td>
                            <td>
                                <?php if (!empty($t['user_id'])): ?>
                                    <a href="customer-details.php?id=<?php echo $t['user_id']; ?>" style="color:#fff; text-decoration:none;">
                                        <strong><?php echo htmlspecialchars($t['customer_name']); ?></strong>
                                    </a>
                                <?php else: ?>
                                    <a href="customers.php?q=<?php echo urlencode($t['customer_name']); ?>" style="color:#fff; text-decoration:none;">
                                        <strong><?php echo htmlspecialchars($t['customer_name']); ?></strong>
                                    </a>
                                <?php endif; ?><br>
                                <small style="color:var(--primary)"><?php echo $t['marketplace'] ?: 'Venda Direta'; ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($t['product_name']); ?><br>
                                <small style="color:var(--muted)"><?php echo $t['request_date'] ? date('d/m/Y', strtotime($t['request_date'])) : 'N/A'; ?></small>
                            </td>
                            <td>
                                <?php
                                $badgeBg = '#222';
                                $badgeText = 'Manual';
                                if ($t['preferred_action'] === 'logistica_reversa') {
                                    $badgeBg = '#c0392b'; // Vermelho
                                    $badgeText = 'Reversa';
                                } elseif ($t['preferred_action'] === 'enviar_peca') {
                                    $badgeBg = '#27ae60'; // Verde
                                    $badgeText = 'Frete';
                                } elseif ($t['preferred_action'] === 'trazer_loja') {
                                    $badgeText = 'Retirada';
                                }
                                ?>
                                <span class="badge" style="background:<?php echo $badgeBg; ?>;"><?php echo $badgeText; ?></span>
                            </td>
                            <td>
                                <select onchange="handleStatusChange(this, <?php echo $t['id']; ?>, '<?php echo $t['status']; ?>')" 
                                        class="badge" style="background:rgba(0,0,0,0.3); color:inherit; border:1px solid rgba(255,255,255,0.1); cursor:pointer;">
                                    <option value="pending" <?php echo $t['status']=='pending' ? 'selected' : ''; ?>>PENDENTE</option>
                                    <option value="shipped" <?php echo $t['status']=='shipped' ? 'selected' : ''; ?>>ENVIADO</option>
                                    <option value="received" <?php echo $t['status']=='received' ? 'selected' : ''; ?>>RECEBIDO</option>
                                    <option value="resolved" <?php echo $t['status']=='resolved' ? 'selected' : ''; ?>>RESOLVIDO (Aprovar)</option>
                                </select>
                                <?php if($t['tracking_code']): ?>
                                    <div style="display:flex; align-items:center; gap:5px; margin-top:5px;">
                                        <a href="https://www.melhorrastreio.com.br/rastreio/<?php echo $t['tracking_code']; ?>" target="_blank" style="text-decoration:none; flex:1;">
                                            <div style="font-size:0.7rem; background:#111; padding:2px 5px; border-radius:4px; color:var(--primary); font-family:monospace; border:1px solid #333; text-align:center;">
                                                📦 <?php echo $t['tracking_code']; ?>
                                                <?php if($t['me_status']): ?>
                                                    <br><span style="color:<?php echo getMeStatusColor($t['me_status']); ?>; font-size:0.65rem; font-weight:bold; display:inline-block; margin-top:2px;"><?php echo strtoupper(getMeStatusTranslation($t['me_status'])); ?></span>
                                                <?php endif; ?>
                                                <br>
                                                <span style="font-size:0.55rem; color:<?php echo $t['wa_notify_tracking'] ? '#00e676' : '#888'; ?>; font-weight:bold; display:inline-block; margin-top:1px;">
                                                    ● Notificação: <?php echo $t['wa_notify_tracking'] ? 'AUTO' : 'MANUAL'; ?>
                                                </span>
                                            </div>
                                        </a>
                                        <a href="?sync_me=<?php echo $t['id']; ?>" class="btn-wa-notif" style="background:#e67e22;" title="Sincronizar Status Melhor Envio">
                                            <i class="fas fa-sync-alt"></i>
                                        </a>
                                        <button type="button" onclick="sendWaNotify(<?php echo $t['id']; ?>, this)" class="btn-wa-notif" title="Enviar Rastreio via WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </button>
                                        <button type="button" onclick="openVipRmaModal(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['customer_name']); ?>')" class="btn-wa-notif btn-wa-vip" style="background:#9b59b6;" title="Atendimento VIP (WhatsApp)">
                                            <i class="fas fa-comment-dots"></i> VIP
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="display:flex; gap:5px; flex-wrap:wrap;">
                                <a href="?edit=<?php echo $t['id']; ?>" class="btn btn-sm" style="background:var(--blue); color:#fff;" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="?clone=<?php echo $t['id']; ?>" class="btn btn-sm" style="background:var(--muted); color:#fff;" title="Clonar"><i class="fas fa-copy"></i></a>
                                
                                <?php if($t['me_order_id']): ?>
                                    <?php if($t['carrier'] === 'lalamove'): ?>
                                        <button type="button" onclick="checkLlmStatus('<?php echo $t['me_order_id']; ?>', <?php echo $t['id']; ?>)" class="btn btn-sm" style="background:#FF6600; color:#fff;" title="Status Lalamove"><i class="fas fa-sync-alt"></i> STATUS</button>
                                    <?php else: // Melhor Envio ?>
                                        <?php if(!$t['tracking_code']): ?>
                                            <a href="?sync_rma=1&id=<?php echo $t['id']; ?>&me_id=<?php echo $t['me_order_id']; ?>" class="btn btn-sm" style="background:#e67e22; color:#fff; font-size:0.65rem; padding:4px 8px;" title="No Carrinho (Sincronizar)">
                                                <i class="fas fa-shopping-cart"></i> SINC
                                            </a>
                                        <?php else: ?>
                                            <button type="button" onclick="printLabel('<?php echo $t['me_order_id']; ?>')" class="btn btn-sm" style="background:var(--primary); color:#000;" title="Etiqueta Melhor Envio"><i class="fas fa-shipping-fast"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="rma-print.php?id=<?php echo $t['id']; ?>&type=label" target="_blank" class="btn btn-sm" style="background:#555; color:#fff;" title="Etiqueta Manual"><i class="fas fa-print"></i></a>
                                <a href="rma-print.php?id=<?php echo $t['id']; ?>&type=declaration" target="_blank" class="btn btn-sm" style="background:#555; color:#fff;" title="Declaração de Conteúdo"><i class="fas fa-file-invoice"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL VIP MSG RMA -->
    <div id="vipModal" class="modal-vip" onclick="if(event.target == this) closeVipModal()">
        <div class="modal-vip-content">
            <span class="close-vip" onclick="closeVipModal()">&times;</span>
            <h2 style="margin-bottom:1rem; color:#9b59b6; display:flex; align-items:center; gap:10px;">✨ Atendimento VIP (RMA)</h2>
            <p id="vip_target_name" style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;"></p>
            
            <input type="hidden" id="vip_rma_id">

            <label class="vip-option">
                <input type="radio" name="viptpl" value="Olá {nome}! 👋 Recebemos seu {produto} (RMA #{ticket}) aqui na Fight Arcade. Nossos técnicos já iniciaram a análise e te daremos um retorno em breve! 🕹️" onclick="document.getElementById('vip_custom_box').style.display='none'" checked>
                <div class="vip-text-content">
                    <strong>🔬 Recebimento / Início de Análise</strong>
                    <span>Confirma a chegada do item para assistência.</span>
                </div>
            </label>
            
            <label class="vip-option">
                <input type="radio" name="viptpl" value="Oi {nome}! 👋 Boas notícias! Seu {produto} (RMA #{ticket}) já foi revisado e os testes técnicos foram concluídos com sucesso. ✅🛠️" onclick="document.getElementById('vip_custom_box').style.display='none'">
                <div class="vip-text-content">
                    <strong>✅ Concluído / Revisado</strong>
                    <span>Informa que o reparo/análise terminou.</span>
                </div>
            </label>
            
            <label class="vip-option">
                <input type="radio" name="viptpl" value="Olá {nome}! 👋 Estamos aguardando a chegada de uma peça específica para concluir seu RMA #{ticket} ({produto}). Manteremos você informado sobre o prazo! 🕹️⏳" onclick="document.getElementById('vip_custom_box').style.display='none'">
                <div class="vip-text-content">
                    <strong>⏳ Aguardando Peça / Prazo</strong>
                    <span>Mantém o cliente informado sobre esperas técnicas.</span>
                </div>
            </label>

            <label class="vip-option">
                <input type="radio" name="viptpl" value="Olá {nome}! 🔄 Passando para te lembrar de postar o seu {produto} (RMA #{ticket}) de volta para nós. Por favor, imprima a etiqueta de Logística Reversa e faça a postagem nos Correios/Transportadora. Precisamos receber o produto antigo para dar andamento ao seu caso/reembolso. Link da etiqueta: {link_etiqueta} {agencia} Qualquer dúvida, estamos à disposição! 📦" onclick="document.getElementById('vip_custom_box').style.display='none'">
                <div class="vip-text-content">
                    <strong>🔄 Cobrar Envio da Reversa</strong>
                    <span>Lembra o cliente de postar o produto via Logística Reversa.</span>
                </div>
            </label>

            <label class="vip-option">
                <input type="radio" name="viptpl" value="custom" onclick="document.getElementById('vip_custom_box').style.display='block'">
                <div class="vip-text-content">
                    <strong>✍️ Mensagem Livre</strong>
                    <span>Escreva o que desejar para este cliente.</span>
                </div>
            </label>

            <div id="vip_custom_box" style="display:none; margin-top:10px;">
                <div id="vip_saved_templates_container" style="margin-bottom:12px; display:none;">
                    <label style="font-size:0.75rem; color:#f1c40f; font-weight:bold; display:block; margin-bottom:5px;">⭐ SEUS MODELOS SALVOS:</label>
                    <select id="vip_saved_templates" style="width:100%; background:#000; border:1px solid #f1c40f; color:#fff; padding:8px; border-radius:6px;" onchange="applySavedTemplate(this.value)">
                        <option value="">-- Escolher um favorito --</option>
                    </select>
                </div>
                <textarea id="vip_custom_msg" placeholder="Use {nome}, {ticket} ou {produto}..." rows="3" style="width:100%; background:#111; border:1px solid #444; color:#fff; border-radius:8px; padding:10px;"></textarea>
                
                <div style="margin-top:10px; background:rgba(155, 89, 182, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(155, 89, 182, 0.3);">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="save_as_template" onchange="document.getElementById('template_title_box').style.display = this.checked ? 'block' : 'none'">
                        ⭐ Salvar esta mensagem como favorito
                    </label>
                    <div id="template_title_box" style="display:none; margin-top:8px;">
                        <input type="text" id="template_title" placeholder="Nome do modelo (Ex: Atraso Fornecedor)" style="width:100%; background:#000; border:1px solid #9b59b6; color:#fff; padding:5px; border-radius:4px; font-size:0.8rem;">
                    </div>
                </div>
            </div>

            <div style="margin-top:2rem; display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeVipModal()">Cancelar</button>
                <button type="button" class="btn" id="btnSendVip" style="background:#9b59b6; color:#fff; flex:1;" onclick="sendVipMsg()">🚀 ENVIAR AGORA</button>
            </div>
        </div>
    </div>

    <!-- MODAL RESOLVER RMA & ESTORNO -->
    <div id="resolveModal" class="modal-vip" onclick="if(event.target == this) closeResolveModal()">
        <div class="modal-vip-content" style="max-width:450px;">
            <span class="close-vip" onclick="closeResolveModal()" style="float:right; cursor:pointer; font-size:1.5rem;">&times;</span>
            <h2 style="margin-bottom:1rem; color:#2ecc71; display:flex; align-items:center; gap:10px;"><i class="fas fa-check-circle"></i> Aprovar / Resolver RMA</h2>
            <p style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;">Você está marcando a ocorrência <strong id="res_ticket_lbl"></strong> como resolvida. Caso este RMA gere um reembolso ou estorno em saldo para o cliente, preencha abaixo.</p>
            
            <form method="POST">
                <input type="hidden" name="resolve_rma_id" id="res_rma_id">
                
                <div class="form-group" style="background:rgba(255,255,255,0.05); padding:15px; border-radius:8px; border:1px solid #333;">
                    <label style="color:#f1c40f; display:flex; align-items:center; gap:8px;"><i class="fas fa-wallet"></i> Valor a Estornar (R$)</label>
                    <input type="text" name="refund_amount" placeholder="Ex: 150,00 (Deixe 0 para não estornar)" style="background:#000; color:#2ecc71; font-weight:bold; font-size:1.2rem; margin-bottom:10px;">
                    
                    <label style="color:#aaa;">Motivo / Observação do Estorno</label>
                    <input type="text" name="refund_desc" placeholder="Ex: Estorno de peça defeituosa" style="background:#000;">
                    
                    <small style="color:#888; display:block; margin-top:10px;">O sistema tentará localizar o cliente pelo celular do RMA e abaterá o saldo no módulo Financeiro.</small>
                </div>

                <div style="margin-top:2rem; display:flex; gap:10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeResolveModal()">Cancelar</button>
                    <button type="submit" name="resolve_rma" class="btn" style="background:#2ecc71; color:#000; flex:1; font-weight:bold;">CONFIRMAR RESOLUÇÃO</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function handleStatusChange(selectElement, id, oldStatus) {
        if (selectElement.value === 'resolved') {
            document.getElementById('res_rma_id').value = id;
            document.getElementById('res_ticket_lbl').innerText = '#' + id;
            document.getElementById('resolveModal').style.display = 'flex';
            
            // Revert visual change temporarily until confirmed in modal
            selectElement.value = oldStatus; 
        } else {
            location.href = '?mark_status=' + selectElement.value + '&id=' + id;
        }
    }

    function closeResolveModal() {
        document.getElementById('resolveModal').style.display = 'none';
    }

    function toggleShippingArea() {
        const action = document.getElementById('f_action').value;
        const area = document.getElementById('shippingArea');
        area.style.display = (action === 'enviar_peca' || action === 'logistica_reversa') ? 'block' : 'none';
    }
    function runAI() {
        const txt = document.getElementById('ai_text').value;
        if(!txt) return;
        const fd = new FormData(); fd.append('ajax_ai', '1'); fd.append('text', txt);
        fetch('rma.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=>{
            if(d.name) document.getElementById('f_name').value = d.name;
            if(d.document) document.getElementById('f_doc').value = d.document;
            if(d.phone) document.getElementById('f_phone').value = d.phone;
            if(d.number) document.getElementById('f_num').value = d.number;
            if(d.complement) document.getElementById('f_complement').value = d.complement;
            if(d.marketplace) {
                const mktSelect = document.querySelector('select[name="marketplace"]');
                if(mktSelect) mktSelect.value = d.marketplace;
            }
            if(d.zipcode) { document.getElementById('f_zip').value = d.zipcode; fetchCep(); }
            alert('🤖 Dados extraídos e Marketplace identificado!');
        });
    }
    function fetchCep() {
        let zipField = document.getElementById('f_zip');
        let cep = zipField.value.replace(/\D/g, '');
        if (cep.length === 8) {
            zipField.style.borderColor = 'var(--primary)';
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(res => res.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('f_addr').value = data.logradouro;
                        document.getElementById('f_bairro').value = data.bairro;
                        document.getElementById('f_city').value = data.localidade;
                        document.getElementById('f_uf').value = data.uf;
                        zipField.style.borderColor = 'var(--border)';
                    } else {
                        zipField.style.borderColor = 'var(--red)';
                    }
                })
                .catch(() => { zipField.style.borderColor = 'var(--red)'; });
        }
    }
    function toggleAll(el) {
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = el.checked);
    }
    function quoteRma() {
        const zip = document.getElementById('f_zip').value;
        const action = document.getElementById('f_action').value;
        if(!zip) return alert('CEP obrigatório!');
        document.getElementById('quoteArea').style.display = 'block';
        const params = new URLSearchParams({ ajax_quote_rma: '1', zipcode: zip, weight: document.getElementById('f_wgt').value, height: document.getElementById('f_h').value, width: document.getElementById('f_w').value, length: document.getElementById('f_l').value, value: document.getElementById('f_val').value, action: action });
        fetch('rma.php?' + params.toString()).then(r=>r.json()).then(data => {
            let html = '<p style="font-size:0.8rem; color:var(--blue); font-weight:800; margin-bottom:10px;">Opções Melhor Envio:</p>';
            (data.quotes || []).forEach(q => {
                const price = parseFloat(q.final_price || q.custom_price || q.price || 0);
                html += `<div style="padding:12px; border:1px solid #333; background:#1a1e2a; margin-bottom:6px; cursor:pointer; border-radius:8px; display:flex; justify-content:space-between; align-items:center;" onclick="selectService(this, ${q.id})">
                    <div>
                        <strong>${q.name}</strong><br>
                        <small style="color:var(--muted)">${q.company.name}</small>
                    </div>
                    <div style="font-weight:bold; color:var(--blue);">R$ ${price.toFixed(2)}</div>
                </div>`;
            });
            document.getElementById('quoteResults').innerHTML = html || 'Nenhuma cotação encontrada.';
        });
    }
    
    function generateCPF(fieldId) {
        const n = () => Math.floor(Math.random() * 9);
        const mod = (n, m) => Math.round(n - (Math.floor(n / m) * m));
        let n1 = n(), n2 = n(), n3 = n(), n4 = n(), n5 = n(), n6 = n(), n7 = n(), n8 = n(), n9 = n();
        let d1 = n9 * 2 + n8 * 3 + n7 * 4 + n6 * 5 + n5 * 6 + n4 * 7 + n3 * 8 + n2 * 9 + n1 * 10;
        d1 = 11 - (mod(d1, 11));
        if (d1 >= 10) d1 = 0;
        let d2 = d1 * 2 + n9 * 3 + n8 * 4 + n7 * 5 + n6 * 6 + n5 * 7 + n4 * 8 + n3 * 9 + n2 * 10 + n1 * 11;
        d2 = 11 - (mod(d2, 11));
        if (d2 >= 10) d2 = 0;
        const cpf = `${n1}${n2}${n3}${n4}${n5}${n6}${n7}${n8}${n9}${d1}${d2}`;
        document.getElementById(fieldId).value = cpf;
    }

    function selectService(el, id) {
        document.querySelectorAll('#quoteResults div').forEach(r => r.style.borderColor = '#333');
        el.style.borderColor = 'var(--primary)';
        document.getElementById('selected_service').value = id;
        
        // Update button text to be clear for both Create and Update modes
        const btnCreate = document.querySelector('button[name="create_rma"]');
        const btnUpdate = document.querySelector('button[name="update_rma"]');
        if(btnCreate) btnCreate.innerHTML = '💾 SALVAR E GERAR ETIQUETA';
        if(btnUpdate) btnUpdate.innerHTML = '💾 ATUALIZAR E GERAR ETIQUETA';
    }
    function printLabel(id) {
        fetch('melhorenvio.php?print_label=' + id).then(r=>r.json()).then(d=>{
            if(d.url) window.open(d.url, '_blank');
            else alert('Erro ao gerar link de impressão. Verifique se o pedido já foi pago e sincronizado.');
        });
    }

    // --- VIP RMA MODAL ---
    function openVipRmaModal(id, name) {
        document.getElementById('vip_rma_id').value = id;
        document.getElementById('vip_target_name').innerHTML = `Suporte Técnico para: <strong>${name}</strong> (RMA #${id})`;
        document.getElementById('vipModal').style.display = 'flex';
        loadSavedTemplates();
    }

    function loadSavedTemplates() {
        fetch('ajax_message_templates.php?action=list&category=rma')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('vip_saved_templates');
                const container = document.getElementById('vip_saved_templates_container');
                if (data.length > 0) {
                    sel.innerHTML = '<option value="">-- Selecionar Favorito --</option>';
                    data.forEach(t => {
                        sel.innerHTML += `<option value="${encodeURIComponent(t.message)}">${t.title}</option>`;
                    });
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                }
            });
    }

    function applySavedTemplate(val) {
        if(val) document.getElementById('vip_custom_msg').value = decodeURIComponent(val);
    }

    function closeVipModal() {
        document.getElementById('vipModal').style.display = 'none';
        document.getElementById('save_as_template').checked = false;
        document.getElementById('template_title_box').style.display = 'none';
        document.getElementById('template_title').value = '';
    }

    function sendVipMsg() {
        const id = document.getElementById('vip_rma_id').value;
        const btn = document.getElementById('btnSendVip');
        let msgValue = document.querySelector('input[name="viptpl"]:checked').value;
        let finalMsg = msgValue;
        
        if(msgValue === 'custom') {
            finalMsg = document.getElementById('vip_custom_msg').value;
            if(!finalMsg) return alert('Escreva a mensagem personalizada!');

            // Save as template if checked
            if (document.getElementById('save_as_template').checked) {
                const title = document.getElementById('template_title').value || 'Modelo Sem Título';
                const fd = new FormData();
                fd.append('category', 'rma');
                fd.append('title', title);
                fd.append('message', finalMsg);
                fetch('ajax_message_templates.php?action=save', { method: 'POST', body: fd });
            }
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ENVIANDO...';

        fetch(`rma.php?ajax_personalized_msg_rma=1&id=${id}&msg=${encodeURIComponent(finalMsg)}`)
            .then(r => {
                if (!r.ok) throw new Error('Server returned ' + r.status);
                return r.json();
            })
            .then(data => {
                if(data.success) {
                    btn.style.background = '#27ae60';
                    btn.innerHTML = '✅ ENVIADO COM SUCESSO!';
                    setTimeout(() => {
                        closeVipModal();
                        btn.disabled = false;
                        btn.style.background = '#9b59b6';
                        btn.innerHTML = '🚀 ENVIAR AGORA';
                    }, 2000);
                } else {
                    if (data.fallback_url) {
                        if (confirm('⚠️ ' + (data.error || 'API offline') + '\n\nDeseja enviar manualmente via WhatsApp Web?')) {
                            window.open(data.fallback_url, '_blank');
                        }
                    } else {
                        alert('⚠️ ' + (data.error || 'Erro ao enviar mensagem.'));
                    }
                    btn.disabled = false;
                    btn.style.background = '#9b59b6';
                    btn.innerHTML = '🚀 TENTAR NOVAMENTE';
                }
            })
            .catch(err => {
                console.error('VIP send error:', err);
                alert('❌ Erro de conexão: ' + err.message);
                btn.disabled = false;
                btn.style.background = '#9b59b6';
                btn.innerHTML = '🚀 ENVIAR AGORA';
            });
    }

    function quoteLlmRma() {
        const zip = document.getElementById('f_zip').value;
        const num = document.getElementById('f_num').value;
        if(!zip) return alert('CEP obrigatório!');
        
        document.getElementById('quoteArea').style.display = 'block';
        document.getElementById('quoteResults').innerHTML = '⏳ Cotando Lalamove...';
        
        // Step 1: Geocode
        fetch(`lalamove.php?ajax_geocode=1&cep=${zip}&number=${num}`)
            .then(r => r.json())
            .then(d => {
                if(d.error) throw new Error(d.error);
                // Step 2: Quote
                return fetch(`lalamove.php?ajax_quote=1&lat=${d.lat}&lng=${d.lng}&address=${encodeURIComponent(d.formatted_address)}`);
            })
            .then(r => r.json())
            .then(data => {
                let html = '<p style="font-size:0.8rem; color:var(--primary); font-weight:800; margin-bottom:10px;">Opções Lalamove:</p>';
                (data.quotes || []).forEach((q, idx) => {
                    const hasError = !!q.error;
                    const price = parseFloat(q.total || 0).toLocaleString('pt-BR', {minimumFractionDigits:2});
                    const icon = {LALAGO:'🏍️', HATCHBACK:'🚗', CAR:'🚙', VAN:'🚐', UV_FIORINO:'🚐', TRUCK330:'🚛', TRUCK3_5T:'🚛'}[q.serviceType] || '📦';
                    html += `
                        <div style="padding:12px; border:1px solid ${hasError?'#333':'#444'}; background:#222; margin-bottom:6px; cursor:${hasError?'not-allowed':'pointer'}; border-radius:8px; display:flex; justify-content:space-between; align-items:center;" 
                             onclick="${!hasError ? `selectLlmRma(this, ${idx})` : ''}">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="font-size:1.5rem">${icon}</div>
                                <div>
                                    <strong style="color:${hasError?'#888':'#fff'}">${q.label}</strong><br>
                                    <small style="color:${hasError?'#e74c3c':'#888'}">${hasError ? q.error : 'Express - Entrega hoje'}</small>
                                </div>
                            </div>
                            <div style="font-weight:bold; color:#2ecc71;">${hasError ? 'N/D' : 'R$ '+price}</div>
                        </div>`;
                });
                document.getElementById('quoteResults').innerHTML = html || 'Nenhuma opção Lalamove.';
                window.llmRmaQuotes = data.quotes;
            })
            .catch(err => {
                document.getElementById('quoteResults').innerHTML = `<div style="color:#e74c3c">Erro: ${err.message}</div>`;
            });
    }

    function selectLlmRma(el, idx) {
        document.querySelectorAll('#quoteResults > div').forEach(r => r.style.borderColor = '#444');
        el.style.borderColor = '#FF6600';
        const q = window.llmRmaQuotes[idx];
        document.getElementById('llm_quotation_id').value = q.quotationId;
        document.getElementById('llm_stops_json').value = JSON.stringify(q.stops);
        document.getElementById('llm_total_value').value = q.total;
        document.getElementById('selected_service').value = ''; // Clear ME selection
        document.getElementById('llm_extra_options').style.display = 'flex';
        
        const btnCreate = document.querySelector('button[name="create_rma"]');
        const btnUpdate = document.querySelector('button[name="update_rma"]');
        if(btnCreate) btnCreate.innerHTML = '💾 SALVAR E CHAMAR MOTOBOY';
        if(btnUpdate) btnUpdate.innerHTML = '💾 ATUALIZAR E CHAMAR MOTOBOY';
    }

    function checkLlmStatus(id, rmaId) {
        fetch(`lalamove.php?ajax_status=1&order_id=${id}`)
            .then(r => r.json())
            .then(data => {
                alert(`Status Lalamove: ${data.status}\n${data.driverName ? 'Motorista: ' + data.driverName : ''}`);
            });
    }

    function quoteUberRma() {
        const rmaId = "<?php echo $edit_data['id'] ?? ''; ?>";
        if (!rmaId) return alert('Salve a ocorrência primeiro para cotar Uber.');
        
        document.getElementById('quoteArea').style.display = 'block';
        document.getElementById('quoteResults').innerHTML = '<div style="color:var(--primary)"><i class="fas fa-spinner fa-spin"></i> Consultando Uber...</div>';
        
        fetch(`../api/uber_api_proxy.php?action=quote&type=rma&id=${rmaId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error);
                let html = '<label style="display:block; margin-bottom:10px; font-size:0.8rem; color:#888;">OPÇÕES UBER 🚙</label>';
                data.quotes.forEach(q => {
                    html += `
                        <div style="padding:12px; border:1px solid #444; background:#000; margin-bottom:6px; cursor:pointer; border-radius:8px; display:flex; justify-content:space-between; align-items:center;" onclick="alert('Uber selecionado: ${q.label}')">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="font-size:1.5rem">🚙</div>
                                <div>
                                    <strong style="color:#fff">${q.label}</strong><br>
                                    <small style="color:#888">${q.estimate}</small>
                                </div>
                            </div>
                            <div style="font-weight:bold; color:#00ff88;">R$ ${q.price.toFixed(2)}</div>
                        </div>`;
                });
                document.getElementById('quoteResults').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('quoteResults').innerHTML = `<div style="color:#e74c3c">Erro Uber: ${err.message}</div>`;
            });
    }

    function sendWaNotify(id, btn) {
        if(!confirm('Deseja enviar o rastreio para este cliente via WhatsApp?')) return;
        
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.classList.add('loading');
        btn.disabled = true;

        fetch('rma.php?ajax_send_wa=1&id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    btn.classList.remove('loading');
                    btn.classList.add('success');
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                        btn.classList.remove('success');
                        btn.disabled = false;
                    }, 3000);
                } else {
                    btn.innerHTML = '<i class="fas fa-times"></i>';
                    btn.classList.remove('loading');
                    btn.classList.add('error');
                    
                    if (data.fallback_url) {
                        if (confirm("⚠️ O envio via API automática falhou ou está offline.\nDeseja abrir o WhatsApp com o link de rastreio para enviar manualmente ao cliente?")) {
                            window.open(data.fallback_url, '_blank');
                        }
                    } else {
                        alert('Erro ao enviar: ' + (data.error || 'Verifique se o telefone está correto (DDD+9 dígitos) e se o WhatsApp está conectado.'));
                    }
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                        btn.classList.remove('error');
                        btn.disabled = false;
                    }, 4000);
                }
            })
            .catch(err => {
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                alert('Erro de conexão com o servidor.');
                btn.disabled = false;
            });
    }

    window.onload = toggleShippingArea;
        function toggleAll(source) {
        const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
        checkboxes.forEach(cb => { cb.checked = source.checked; });
    }
</script>
</body>
</html>
<?php
} catch (Throwable $e) {
    header("Location: emergency_fix.php?fatal_error=" . urlencode($e->getMessage()) . "&file=rma.php");
    exit;
}
?>
