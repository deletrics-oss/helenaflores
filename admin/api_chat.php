<?php
/**
 * admin/api_chat.php
 * Bridge entre o Painel e a Evolution API para o Chat em tempo real
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/notifications.php';
isAdmin();

$notif = new NotificationService($pdo);
$cfg = $notif->getConfig();

$url  = rtrim($cfg['notif_waapi_url']      ?? '', '/');
$key  = $cfg['notif_waapi_key']             ?? '';
$inst = $cfg['notif_waapi_instance']        ?? 'default';

if (!$url || !$key) {
    echo json_encode(['error' => 'Evolution API não configurada']);
    exit;
}

$action = $_GET['action'] ?? '';

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Garante tabela de labels para contatos
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_labels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        remote_jid VARCHAR(100) NOT NULL,
        label VARCHAR(50) NOT NULL DEFAULT 'novo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_jid (remote_jid)
    )");
} catch(Exception $e) {}

// AJAX: Salvar label de contato
if ($action === 'set_label') {
    header('Content-Type: application/json');
    $jid = $_GET['jid'] ?? '';
    $label = $_GET['label'] ?? 'novo';
    if ($jid) {
        $stmt = $pdo->prepare("INSERT INTO contact_labels (remote_jid, label) VALUES (?, ?) ON DUPLICATE KEY UPDATE label = ?");
        $stmt->execute([$jid, $label, $label]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'JID vazio']);
    }
    exit;
}

// AJAX: Buscar foto de perfil do WhatsApp via Evolution API
if ($action === 'get_profile_pic') {
    header('Content-Type: application/json');
    $jid = $_GET['jid'] ?? '';
    if (!$jid) {
        echo json_encode(['profilePictureUrl' => '']);
        exit;
    }
    
    try {
        $number = explode('@', $jid)[0];
        $endpoint = "$url/chat/fetchProfilePictureUrl/$inst";
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "apikey: $key",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode(['number' => $number]),
            CURLOPT_TIMEOUT => 5
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($result, true);
        $picUrl = $data['profilePictureUrl'] ?? ($data['picture'] ?? '');
        echo json_encode(['profilePictureUrl' => $picUrl]);
    } catch(Exception $e) {
        echo json_encode(['profilePictureUrl' => '']);
    }
    exit;
}

if ($action === 'list_chats') {
    try {
        // Parte 1: Todos que mandaram mensagem (conhecidos ou não)
        $stmt = $pdo->query("
            SELECT w.remote_jid, MAX(w.created_at) as last_msg, 
                   u.id, u.name, u.is_lead, u.sdr_bot_status, IFNULL(u.current_debt, 0) as current_debt,
                   cl.label as contact_label
            FROM whatsapp_messages w
            LEFT JOIN users u ON w.remote_jid = CONCAT(u.phone, '@s.whatsapp.net') OR w.remote_jid = CONCAT('55', u.phone, '@s.whatsapp.net')
            LEFT JOIN contact_labels cl ON cl.remote_jid = w.remote_jid
            GROUP BY w.remote_jid, u.id, u.name, u.is_lead, u.sdr_bot_status, u.current_debt, cl.label
            ORDER BY last_msg DESC
            LIMIT 50
        ");
        $part1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parte 2: Usuários cadastrados que NUNCA mandaram mensagem  
        $stmt2 = $pdo->query("
            SELECT CONCAT(IF(LENGTH(phone)=11 AND phone NOT LIKE '55%', CONCAT('55', phone), phone), '@s.whatsapp.net') as remote_jid,
                   created_at as last_msg, id, name, is_lead, sdr_bot_status, IFNULL(current_debt, 0) as current_debt,
                   NULL as contact_label
            FROM users
            WHERE phone IS NOT NULL AND phone != '' AND role != 'admin'
              AND CONCAT(IF(LENGTH(phone)=11 AND phone NOT LIKE '55%', CONCAT('55', phone), phone), '@s.whatsapp.net') NOT IN (SELECT DISTINCT remote_jid FROM whatsapp_messages WHERE remote_jid IS NOT NULL)
            ORDER BY created_at DESC
            LIMIT 30
        ");
        $part2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $allRaw = array_merge($part1, $part2);
        
        $chats = [];
        foreach ($allRaw as $c) {
            $num = explode('@', $c['remote_jid'])[0];
            $isKnown = !empty($c['id']);
            $uid = $c['id'] ?? 0;
            $phoneDigits = substr(preg_replace('/\D/', '', $num), -8);

            if (!$isKnown) {
                try {
                    // Busca se existe no RMA
                    $rma = $pdo->query("SELECT customer_name, document, phone FROM rma_tickets WHERE phone LIKE '%$phoneDigits%' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    if ($rma) {
                        $cleanPhone = preg_replace('/\D/', '', $num);
                        // Auto-cadastra
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, document, password, role, is_lead, sdr_bot_status) VALUES (?, ?, ?, ?, ?, 'customer', 1, 'paused')");
                        $stmt->execute([
                            $rma['customer_name'] ?: 'Desconhecido (RMA)',
                            uniqid() . '@whatsapp.lead',
                            $cleanPhone,
                            preg_replace('/\D/', '', $rma['document'] ?? ''),
                            password_hash(uniqid(), PASSWORD_DEFAULT)
                        ]);
                        $uid = $pdo->lastInsertId();
                        $isKnown = true;
                        $c['name'] = $rma['customer_name'];
                        $c['is_lead'] = 1;
                        $c['sdr_bot_status'] = 'paused';
                    }
                } catch(Exception $e) {
                    error_log("Auto-Lead Error: " . $e->getMessage());
                }
            }
            
            $pushName = "Desconhecido (Novo)";
            if ($isKnown) {
                $tipo = ($c['is_lead'] == 1) ? 'Lead' : 'Cliente';
                $pushName = ($c['name'] ?? 'Desconhecido') . " ($tipo)";
            }
            
            // Calcula flags
            $hasOrders = false;
            $hasRma = false;
            $hasDebt = false;
            if ($uid > 0) {
                try {
                    $hasOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE user_id = $uid")->fetchColumn() > 0;
                    $hasDebt = ((float)($c['current_debt'] ?? 0) > 0) || ($pdo->query("SELECT (COALESCE(SUM(total_amount),0) - (SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE user_id = $uid)) FROM orders WHERE user_id = $uid AND status != 'canceled'")->fetchColumn() > 0);
                } catch(Exception $e) {}
            }
            
            try {
                $hasRma = $pdo->query("SELECT COUNT(*) FROM rma_tickets WHERE phone LIKE '%$phoneDigits%'")->fetchColumn() > 0;
            } catch(Exception $e) {}
            
            $chats[] = [
                'remoteJid'    => $c['remote_jid'],
                'pushName'     => $pushName,
                'userId'       => $uid,
                'botStatus'    => $c['sdr_bot_status'] ?? 'paused',
                'hasOrders'    => $hasOrders,
                'hasRma'       => $hasRma,
                'hasDebt'      => $hasDebt,
                'contactLabel' => $c['contact_label'] ?? ''
            ];
        }
        
        echo json_encode($chats);
    } catch (Exception $e) {
        echo json_encode(['error' => 'DB_ERROR', 'details' => $e->getMessage(), 'code' => 500]);
    }
    exit;
}

if ($action === 'get_messages') {
    $remoteJid = $_GET['remoteJid'] ?? '';
    if (!$remoteJid) exit;
    
    // Busca no Histórico Local
    $stmt = $pdo->prepare("
        SELECT from_me as fromMe, message_text as text, UNIX_TIMESTAMP(created_at) as messageTimestamp 
        FROM whatsapp_messages 
        WHERE remote_jid = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute([$remoteJid]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formata igual ao que o Frontend espera
    $formatted = [];
    foreach ($msgs as $m) {
        $formatted[] = [
            'fromMe' => (bool)$m['fromMe'],
            'messageTimestamp' => $m['messageTimestamp'],
            'textMessage' => ['text' => $m['text']]
        ];
    }
    
    echo json_encode($formatted);
    exit;
}

if ($action === 'send_message') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['number'] ?? '';
    $text = $data['text'] ?? '';
    
    if (!$phone || !$text) exit;
    
    $res = $notif->send($phone, $text);
    
    // Histórico local já é salvo automaticamente pelo $notif->send()
    // NÃO inserir aqui novamente para evitar mensagens duplicadas no CRM
    
    echo json_encode(['success' => $res]);
    exit;
}

if ($action === 'toggle_bot') {
    $uid = (int)($_GET['uid'] ?? 0);
    $status = $_GET['status'] ?? 'active';
    if ($uid > 0 && in_array($status, ['active', 'paused'])) {
        $pdo->prepare("UPDATE users SET sdr_bot_status = ? WHERE id = ?")->execute([$status, $uid]);
        echo json_encode(['success' => true, 'status' => $status]);
    } else {
        echo json_encode(['error' => 'Invalid parameters']);
    }
    exit;
}

if ($action === 'get_quick_data') {
    $type = $_GET['type'] ?? '';
    $uid = (int)($_GET['uid'] ?? 0);
    $phone = $_GET['phone'] ?? '';
    
    try {
        if ($type === 'catalogo') {
            $stmt = $pdo->query("SELECT id, name, slug, price FROM products WHERE active = 1 AND show_on_site = 1 ORDER BY name ASC LIMIT 100");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'suporte') {
            // Busca vídeos e links de suporte da base de conhecimento (AI Knowledge)
            $stmt = $pdo->query("SELECT id, title, video_url, link_url FROM ai_knowledge WHERE (video_url IS NOT NULL OR link_url IS NOT NULL) AND video_url != '' ORDER BY title ASC LIMIT 50");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'pedidos') {
            $phoneClean = substr($phone, -10);
            if ($uid > 0) {
                $stmt = $pdo->prepare("SELECT id, status, total_amount, tracking_code, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$uid]);
            } else {
                $stmt = $pdo->prepare("SELECT o.id, o.status, o.total_amount, o.tracking_code, o.created_at FROM orders o JOIN users u ON o.user_id = u.id WHERE u.phone LIKE ? ORDER BY o.created_at DESC LIMIT 20");
                $stmt->execute(["%$phoneClean%"]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'rma') {
            $phoneClean = substr($phone, -10);
            $stmt = $pdo->prepare("SELECT id, status, issue_type as problem_description, tracking_code, created_at FROM rma_tickets WHERE phone LIKE ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute(["%$phoneClean%"]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'financeiro') {
            $phoneClean = substr($phone, -10);
            if ($uid > 0) {
                $stmt = $pdo->prepare("SELECT id, total_amount, status as payment_status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$uid]);
            } else {
                $stmt = $pdo->prepare("SELECT o.id, o.total_amount, o.status as payment_status, o.created_at FROM orders o JOIN users u ON o.user_id = u.id WHERE u.phone LIKE ? ORDER BY o.created_at DESC LIMIT 20");
                $stmt->execute(["%$phoneClean%"]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } else {
            echo json_encode(['error' => 'Tipo inválido']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
