<?php
/**
 * webhook_receipt.php — Recebimento de Comprovantes via WhatsApp
 * 
 * ESTRATÉGIA:
 * 1. Você recebe o comprovante PIX do cliente no seu WhatsApp pessoal
 * 2. Você ENCAMINHA a imagem para o número da Evolution API
 *    - Com legenda "#123" (número do pedido) → associa automaticamente
 *    - Sem legenda → salva como "não associado" (você vincula no painel)
 * 3. O sistema salva a imagem e registra na tabela payment_receipts
 * 4. Na tela de pedidos, o comprovante aparece como thumbnail
 *
 * CONFIGURAÇÃO NA EVOLUTION:
 *   Webhook URL: https://fightarcade.com.br/catalogo/webhook_receipt.php
 *   Events: MESSAGES_UPSERT (ou messages.upsert)
 *
 * SEGURANÇA:
 *   - Só aceita mensagens do número admin cadastrado
 *   - Só processa imagens (ignora texto, áudio, vídeo)
 *   - Valida extensão e tamanho
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        source ENUM('upload','whatsapp') DEFAULT 'whatsapp',
        notes TEXT DEFAULT NULL,
        sender_phone VARCHAR(30) DEFAULT NULL,
        received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order (order_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Ensure upload directory exists
$uploadDir = __DIR__ . '/assets/receipts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Load notification config
$notif = new NotificationService($pdo);
$cfg = $notif->getConfig();
$adminPhone = preg_replace('/\D/', '', $cfg['notif_admin_phone'] ?? '');

// Evolution API config
$evoUrl  = rtrim($cfg['notif_waapi_url'] ?? '', '/');
$evoKey  = $cfg['notif_waapi_key'] ?? '';
$evoInst = $cfg['notif_waapi_instance'] ?? 'default';

// ===== RECEIVE WEBHOOK =====
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log raw for debugging (temporary)
$logFile = __DIR__ . '/assets/receipts/webhook_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " | " . substr($json, 0, 2000) . "\n", FILE_APPEND);

if (!$data) {
    http_response_code(200);
    echo json_encode(['status' => 'no_data']);
    exit;
}

// ===== PARSE MESSAGE =====
// Evolution API v2 message structure
$message = $data['data']['message'] ?? $data['data'] ?? null;
$key     = $data['data']['key'] ?? [];
$sender  = preg_replace('/\D/', '', $key['remoteJid'] ?? '');

// Security: Only accept from admin phone
$senderClean = preg_replace('/^55/', '', $sender);
$adminClean  = preg_replace('/^55/', '', $adminPhone);

if ($senderClean !== $adminClean && $sender !== $adminPhone) {
    // Not from admin — ignore silently
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'not_admin']);
    exit;
}

// ===== CHECK FOR IMAGE =====
$imageMessage = $message['imageMessage'] ?? null;
$documentMessage = $message['documentMessage'] ?? null;

if (!$imageMessage && !$documentMessage) {
    // Not an image — ignore (text, audio, video, etc.)
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'no_image']);
    exit;
}

// ===== EXTRACT CAPTION (order number) =====
$caption = '';
if ($imageMessage) {
    $caption = $imageMessage['caption'] ?? '';
} elseif ($documentMessage) {
    $caption = $documentMessage['caption'] ?? $documentMessage['fileName'] ?? '';
}

// Try to find order number in caption: "#123", "pedido 123", "123"
$orderId = null;
if (preg_match('/#?(\d{1,6})/', $caption, $matches)) {
    $candidateId = (int)$matches[1];
    // Verify order exists
    $checkOrder = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
    $checkOrder->execute([$candidateId]);
    if ($checkOrder->fetch()) {
        $orderId = $candidateId;
    }
}

// ===== DOWNLOAD IMAGE FROM EVOLUTION =====
$mediaUrl = null;
$mediaKey = null;

// Evolution v2: get media URL from the message
if ($imageMessage) {
    // Try direct URL first (some versions provide it)
    $mediaUrl = $imageMessage['url'] ?? null;
    $mediaKey = $imageMessage['mediaKey'] ?? null;
    $mimetype = $imageMessage['mimetype'] ?? 'image/jpeg';
} elseif ($documentMessage) {
    $mediaUrl = $documentMessage['url'] ?? null;
    $mediaKey = $documentMessage['mediaKey'] ?? null;
    $mimetype = $documentMessage['mimetype'] ?? 'application/pdf';
}

// If no direct URL, use Evolution API to download
$messageId = $key['id'] ?? '';
$imageData = null;

if ($mediaUrl) {
    // Direct download
    $ch = curl_init($mediaUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $imageData = curl_exec($ch);
    curl_close($ch);
} elseif ($evoUrl && $evoKey && $messageId) {
    // Download via Evolution API endpoint
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
        $imageData = base64_decode($resData['base64']);
    }
}

if (!$imageData || strlen($imageData) < 1000) {
    // Failed to download image
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | FAIL: could not download image\n", FILE_APPEND);
    
    // Notify admin
    $notif->notifyAdmin("⚠️ Recebi uma imagem via WhatsApp mas não consegui baixar.\nCaption: " . ($caption ?: '(sem legenda)'));
    
    http_response_code(200);
    echo json_encode(['status' => 'error', 'reason' => 'download_failed']);
    exit;
}

// ===== SAVE FILE =====
$ext = 'jpg';
if (strpos($mimetype, 'png') !== false) $ext = 'png';
elseif (strpos($mimetype, 'webp') !== false) $ext = 'webp';
elseif (strpos($mimetype, 'pdf') !== false) $ext = 'pdf';

$filename = "wa_receipt_" . ($orderId ?: 'pending') . "_" . time() . "." . $ext;
$filePath = $uploadDir . $filename;
$relPath  = 'assets/receipts/' . $filename;

file_put_contents($filePath, $imageData);

// ===== GET USER ID FROM ORDER =====
$userId = null;
if ($orderId) {
    $userId = $pdo->query("SELECT user_id FROM orders WHERE id = $orderId")->fetchColumn() ?: null;
}

// ===== SAVE TO DATABASE =====
$stmt = $pdo->prepare("INSERT INTO payment_receipts (order_id, user_id, file_path, source, notes, sender_phone) VALUES (?, ?, ?, 'whatsapp', ?, ?)");
$notes = $caption ?: 'Recebido via WhatsApp';
$stmt->execute([$orderId, $userId, $relPath, $notes, $sender]);

$receiptId = $pdo->lastInsertId();

// ===== CONFIRM TO ADMIN =====
if ($orderId) {
    $orderInfo = $pdo->query("SELECT o.total_amount, u.name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $orderId")->fetch();
    $msg = "✅ *COMPROVANTE RECEBIDO*\n\n"
         . "📎 Pedido: *#$orderId*\n"
         . "👤 Cliente: " . ($orderInfo['name'] ?? '?') . "\n"
         . "💰 Valor: R$ " . number_format($orderInfo['total_amount'] ?? 0, 2, ',', '.') . "\n"
         . "📝 Legenda: $caption\n\n"
         . "O comprovante já foi vinculado ao pedido automaticamente! 🎉";
} else {
    $msg = "📎 *COMPROVANTE RECEBIDO (não associado)*\n\n"
         . "📝 Legenda: " . ($caption ?: '(sem legenda)') . "\n"
         . "ID: #$receiptId\n\n"
         . "⚠️ Não encontrei o número do pedido na legenda.\n"
         . "Para associar, envie novamente com: *#NUMERO_DO_PEDIDO*\n"
         . "Ou vincule manualmente no painel de Pedidos.";
}
$notif->notifyAdmin($msg);

http_response_code(200);
echo json_encode([
    'status'     => 'success',
    'receipt_id' => $receiptId,
    'order_id'   => $orderId,
    'file'       => $relPath,
]);
