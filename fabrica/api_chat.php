<?php
/**
 * fabrica/api_chat.php
 * Bridge entre o Painel da Fábrica e a Evolution API para o Chat em tempo real (B2B)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

// Authentication Check
if (!isset($_SESSION['factory_user_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// --- SELF-HEALING AUTOMATIC SCHEMA MIGRATIONS ---
try {
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS neighborhood VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS number VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS complement VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS is_vip TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS is_lead TINYINT(1) DEFAULT 0");

    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(10,3) DEFAULT 0.000");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS length_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS width_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS height_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL");

    $pdo->exec("ALTER TABLE factory_employees ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_production_orders ADD COLUMN IF NOT EXISTS notification_phone VARCHAR(30) DEFAULT NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_defects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        production_order_id INT DEFAULT NULL,
        product_id INT DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        sender_phone VARCHAR(30) DEFAULT NULL,
        status ENUM('pending', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_text TEXT NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Garante tabela de labels para contatos
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_labels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        remote_jid VARCHAR(100) NOT NULL,
        label VARCHAR(50) NOT NULL DEFAULT 'novo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_jid (remote_jid)
    )");
} catch(Exception $e) {}

$notif = new NotificationService($pdo, true);
$cfg = $notif->getConfig();

$url  = rtrim($cfg['notif_waapi_url'] ?? '', '/');
$key  = $cfg['notif_waapi_key'] ?? '';
$inst = $cfg['notif_waapi_instance'] ?? 'default';

if (!$url || !$key) {
    echo json_encode(['error' => 'Evolution API não configurada']);
    exit;
}

$action = $_GET['action'] ?? '';

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// AJAX: Salvar label de contato (novo, andamento, pendente, etc)
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

// AJAX: Buscar foto de perfil do WhatsApp
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
        // Parte 1: Clientes ou remetentes conhecidos que mandaram mensagem no WhatsApp
        $stmt = $pdo->query("
            SELECT w.remote_jid, MAX(w.created_at) as last_msg, 
                   u.id, u.name, u.is_lead, IFNULL(u.is_vip, 0) as is_vip,
                   cl.label as contact_label
            FROM whatsapp_messages w
            LEFT JOIN factory_clients u ON w.remote_jid = CONCAT(u.phone, '@s.whatsapp.net') OR w.remote_jid = CONCAT('55', u.phone, '@s.whatsapp.net')
            LEFT JOIN contact_labels cl ON cl.remote_jid = w.remote_jid
            GROUP BY w.remote_jid, u.id, u.name, u.is_lead, u.is_vip, cl.label
            ORDER BY last_msg DESC
            LIMIT 50
        ");
        $part1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parte 2: Clientes B2B da fábrica que AINDA não iniciaram mensagens
        $stmt2 = $pdo->query("
            SELECT CONCAT(IF(LENGTH(phone)=11 AND phone NOT LIKE '55%', CONCAT('55', phone), phone), '@s.whatsapp.net') as remote_jid,
                   created_at as last_msg, id, name, is_lead, is_vip,
                   NULL as contact_label
            FROM factory_clients
            WHERE phone IS NOT NULL AND phone != ''
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
            
            $pushName = "Novo B2B (" . $num . ")";
            if ($isKnown) {
                $tipo = $c['is_lead'] == 1 ? 'Lead' : 'Cliente';
                $pushName = $c['name'] . " ($tipo)";
            }
            
            // Flags de ordem, defeito ou dívida
            $hasOrders = false;
            $hasRma = false;
            $hasDebt = false;
            
            if ($uid > 0) {
                try {
                    $hasOrders = $pdo->query("SELECT COUNT(*) FROM factory_sales WHERE client_id = $uid")->fetchColumn() > 0;
                    
                    // Verifica se tem saldo devedor/pedido pendente
                    $hasDebt = $pdo->query("SELECT COUNT(*) FROM factory_sales WHERE client_id = $uid AND status = 'pending'")->fetchColumn() > 0;
                } catch(Exception $e) {}
            }
            
            // Defeitos relatados
            $phoneDigits = substr(preg_replace('/\D/', '', $num), -8);
            try {
                $hasRma = $pdo->query("SELECT COUNT(*) FROM factory_defects WHERE sender_phone LIKE '%$phoneDigits%'")->fetchColumn() > 0;
            } catch(Exception $e) {}
            
            $chats[] = [
                'remoteJid'    => $c['remote_jid'],
                'pushName'     => $pushName,
                'userId'       => $uid,
                'botStatus'    => 'paused', // Factory chat é 100% humano
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
    
    // Salvar no histórico local
    if ($res) {
        $remoteJid = $phone . '@s.whatsapp.net';
        try {
            $stmt = $pdo->prepare("INSERT INTO whatsapp_messages (remote_jid, from_me, message_text) VALUES (?, 1, ?)");
            $stmt->execute([$remoteJid, $text]);
        } catch(Exception $e) {}
    }
    
    echo json_encode(['success' => $res]);
    exit;
}

if ($action === 'get_quick_data') {
    $type = $_GET['type'] ?? '';
    $uid = (int)($_GET['uid'] ?? 0);
    $phone = $_GET['phone'] ?? '';
    
    try {
        if ($type === 'catalogo') {
            $stmt = $pdo->query("SELECT id, name, sku, sale_price as price FROM factory_products ORDER BY name ASC LIMIT 100");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'suporte') {
            // Busca vídeos e links de suporte cadastrados
            $stmt = $pdo->query("SELECT id, title, video_url, link_url FROM ai_knowledge WHERE (video_url IS NOT NULL OR link_url IS NOT NULL) AND video_url != '' ORDER BY title ASC LIMIT 50");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'pedidos') {
            $phoneClean = substr($phone, -10);
            if ($uid > 0) {
                $stmt = $pdo->prepare("SELECT id, status, total_amount, tracking_code, created_at FROM factory_sales WHERE client_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$uid]);
            } else {
                $stmt = $pdo->prepare("SELECT s.id, s.status, s.total_amount, s.tracking_code, s.created_at FROM factory_sales s JOIN factory_clients u ON s.client_id = u.id WHERE u.phone LIKE ? ORDER BY s.created_at DESC LIMIT 20");
                $stmt->execute(["%$phoneClean%"]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'rma') {
            $phoneClean = substr($phone, -10);
            $stmt = $pdo->prepare("SELECT id, status, description as problem_description, created_at FROM factory_defects WHERE sender_phone LIKE ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute(["%$phoneClean%"]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($type === 'financeiro') {
            $phoneClean = substr($phone, -10);
            if ($uid > 0) {
                $stmt = $pdo->prepare("SELECT id, total_amount, status as payment_status, created_at FROM factory_sales WHERE client_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$uid]);
            } else {
                $stmt = $pdo->prepare("SELECT s.id, s.total_amount, s.status as payment_status, s.created_at FROM factory_sales s JOIN factory_clients u ON s.client_id = u.id WHERE u.phone LIKE ? ORDER BY s.created_at DESC LIMIT 20");
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
