<?php
/**
 * webhook_factory.php — Recebimento de Relatos de Defeitos e Imagens via WhatsApp (Evolution API)
 *
 * CONFIGURAÇÃO NA EVOLUTION API:
 *   Apontar Webhook URL para: https://www.fightarcade.com.br/catalogo/webhook_factory.php
 *   Events: MESSAGES_UPSERT
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

// Cria tabela de defeitos se ela não existir
try {
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
} catch (Exception $e) {}

// Garante que o diretório de uploads de defeitos exista
$uploadDir = __DIR__ . '/assets/uploads/defects/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Carrega as configurações de notificação
$notif = new NotificationService($pdo);
$cfg = $notif->getConfig();

$evoUrl  = rtrim($cfg['notif_waapi_url'] ?? '', '/');
$evoKey  = $cfg['notif_waapi_key'] ?? '';
$evoInst = $cfg['notif_waapi_instance'] ?? 'default';

// ===== RECEBER PAYLOAD =====
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Registra log para análise
$logFile = __DIR__ . '/assets/uploads/defects/webhook_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " | " . substr($json, 0, 1500) . "\n", FILE_APPEND);

if (!$data) {
    http_response_code(200);
    echo json_encode(['status' => 'no_payload']);
    exit;
}

// ===== PARSE MENSAGEM =====
$message = $data['data']['message'] ?? $data['data'] ?? null;
$key     = $data['data']['key'] ?? [];
$senderJid = $key['remoteJid'] ?? '';
$sender  = preg_replace('/\D/', '', $senderJid);

// Prevenção de loops
$fromMe = (bool)($key['fromMe'] ?? false);
if ($fromMe) {
    http_response_code(200);
    exit;
}

// ===== VERIFICA SE É UMA IMAGEM =====
$imageMessage = $message['imageMessage'] ?? null;
$documentMessage = $message['documentMessage'] ?? null;

if (!$imageMessage && !$documentMessage) {
    // Ignora silenciosamente mensagens que não sejam imagem ou arquivo
    http_response_code(200);
    echo json_encode(['status' => 'ignored_no_media']);
    exit;
}

// ===== EXTRAI LEGENDA E TEXTO =====
$caption = '';
$mimetype = 'image/jpeg';
if ($imageMessage) {
    $caption = $imageMessage['caption'] ?? '';
    $mimetype = $imageMessage['mimetype'] ?? 'image/jpeg';
} elseif ($documentMessage) {
    $caption = $documentMessage['caption'] ?? $documentMessage['fileName'] ?? '';
    $mimetype = $documentMessage['mimetype'] ?? 'application/octet-stream';
}

$captionLower = mb_strtolower($caption);

// Verifica se contém termos de problema ou defeito
$isDefectReport = false;
$keywords = ['defeito', 'problema', 'quebrado', 'quebra', 'danificado', 'erro', 'defeito_op', 'bug', 'conserto', 'falha'];
foreach ($keywords as $kw) {
    if (strpos($captionLower, $kw) !== false || strpos($captionLower, '#' . $kw) !== false) {
        $isDefectReport = true;
        break;
    }
}

// Se não tiver palavras-chave e não tiver tag do tipo #op, ignora (deixa para webhook de comprovantes se aplicável)
$hasOpTag = (bool)preg_match('/#(?:op|po|prod|pedido)?\s*(\d+)/i', $caption);
if (!$isDefectReport && !$hasOpTag) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored_not_a_defect']);
    exit;
}

// ===== IDENTIFICA ORDEM DE PRODUÇÃO (OP) OU PRODUTO =====
$production_order_id = null;
$product_id = null;

if (preg_match('/#(?:op|po|pedido)?\s*(\d+)/i', $caption, $matches)) {
    $opCandidate = (int)$matches[1];
    // Verifica se a OP existe na fábrica
    $check = $pdo->prepare("SELECT id, product_id FROM factory_production_orders WHERE id = ?");
    $check->execute([$opCandidate]);
    $op = $check->fetch(PDO::FETCH_ASSOC);
    if ($op) {
        $production_order_id = $op['id'];
        $product_id = $op['product_id'];
    }
}

// Se não achou OP, tenta pesquisar SKU ou nome de produto na legenda
if (!$product_id) {
    $products = $pdo->query("SELECT id, name, sku FROM factory_products")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $p) {
        if (!empty($p['sku']) && strpos($captionLower, mb_strtolower($p['sku'])) !== false) {
            $product_id = $p['id'];
            break;
        }
        if (strpos($captionLower, mb_strtolower($p['name'])) !== false) {
            $product_id = $p['id'];
            break;
        }
    }
}

// ===== DOWNLOAD DA MÍDIA =====
$mediaUrl = $imageMessage['url'] ?? $documentMessage['url'] ?? null;
$messageId = $key['id'] ?? '';
$mediaData = null;

if ($mediaUrl) {
    // Download direto via cURL
    $ch = curl_init($mediaUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $mediaData = curl_exec($ch);
    curl_close($ch);
} elseif ($evoUrl && $evoKey && $messageId) {
    // Download via Endpoint da Evolution API
    $downloadUrl = "$evoUrl/chat/getBase64FromMediaMessage/$evoInst";
    $ch = curl_init($downloadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'apikey: ' . $evoKey],
        CURLOPT_POSTFIELDS => json_encode([
            'message' => ['key' => $key],
            'convertToMp4' => false,
        ]),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $resData = json_decode($res, true);
    if (!empty($resData['base64'])) {
        $mediaData = base64_decode($resData['base64']);
    }
}

if (!$mediaData || strlen($mediaData) < 1000) {
    http_response_code(200);
    echo json_encode(['status' => 'error', 'reason' => 'download_failed']);
    exit;
}

// ===== SALVAR ARQUIVO =====
$ext = 'jpg';
if (strpos($mimetype, 'png') !== false) $ext = 'png';
elseif (strpos($mimetype, 'webp') !== false) $ext = 'webp';
elseif (strpos($mimetype, 'pdf') !== false) $ext = 'pdf';

$filename = "defect_" . ($production_order_id ? "op{$production_order_id}_" : "unknown_") . time() . "." . $ext;
$filePath = $uploadDir . $filename;
$relPath  = 'assets/uploads/defects/' . $filename;

file_put_contents($filePath, $mediaData);

// ===== SALVAR NO BANCO DE DADOS =====
$stmt = $pdo->prepare("INSERT INTO factory_defects (production_order_id, product_id, file_path, description, sender_phone, status) VALUES (?, ?, ?, ?, ?, 'pending')");
$desc = !empty($caption) ? $caption : 'Relatado via WhatsApp';
$stmt->execute([$production_order_id, $product_id, $relPath, $desc, $sender]);
$defectId = $pdo->lastInsertId();

// ===== ENVIAR FEEDBACK DE CONFIRMAÇÃO =====
$responseMsg = "⚠️ *RELATO DE DEFEITO RECEBIDO*\n\n"
             . "Registramos o seu alerta no sistema da fábrica! 🛠️\n"
             . "📝 *ID do Relato:* #$defectId\n"
             . "💬 *Descrição:* $desc\n";

if ($production_order_id) {
    $responseMsg .= "⚙️ *Ordem de Produção vinculada:* #$production_order_id\n";
}
if ($product_id) {
    $pName = $pdo->query("SELECT name FROM factory_products WHERE id = $product_id")->fetchColumn();
    $responseMsg .= "📦 *Item:* $pName\n";
}

$responseMsg .= "\nA equipe já foi notificada e está analisando o problema. Obrigado!";

// Envia a resposta de volta ao remetente
$notif->send($senderJid, $responseMsg);

// Notifica também o administrador geral
$adminAlert = "🚨 *NOVO DEFEITO RELATADO VIA WHATSAPP*\n"
            . "Remetente: $sender\n"
            . "Descrição: $desc\n"
            . ($production_order_id ? "OP Vinculada: #$production_order_id\n" : "")
            . "Acesse o painel da fábrica para ver a imagem e resolver o ticket.";
$notif->notifyAdmin($adminAlert);

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'defect_id' => $defectId,
    'file_path' => $relPath,
    'op_id' => $production_order_id
]);
exit;
