<?php
/**
 * webhook_evolution.php — Helena Flores
 * Handler Evolution API — Integração WhatsApp Business & Painel de Pedidos (v3.2)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/ai_sdr.php';

// ── Bootstrapping ──────────────────────────────────────────────
$notif      = new NotificationService($pdo);
$ai         = new AIService($pdo);
$cfg        = $notif->getConfig();
$adminPhone = $cfg['notif_admin_phone'] ?? WHATSAPP_ADMIN;

// ── Recebe payload ─────────────────────────────────────────────
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Debug Log
if ($json) {
    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] PAYLOAD: " . $json . "\n", FILE_APPEND);
}

if (!$data) {
    http_response_code(200); 
    exit;
}

// ── Extrai campos do payload ───────────────────────────────────
$msgData   = $data['data'] ?? [];
$key       = $msgData['key'] ?? ($data['key'] ?? []);
$messageId = $key['id'] ?? '';
$fromMe    = (bool) ($key['fromMe'] ?? false);
$inst      = $data['instance'] ?? 'default';

// 1. Ignora mensagens enviadas pelo próprio bot
if ($fromMe) {
    http_response_code(200);
    exit;
}

// Extrai o número do remetente
$senderJid = $key['remoteJid'] ?? '';
$sender    = preg_replace('/\D/', '', $senderJid);
$pushName  = $msgData['pushName'] ?? $data['pushName'] ?? 'Cliente WhatsApp';

// 2. Ignora grupos
if (strpos($senderJid, '@g.us') !== false) {
    http_response_code(200);
    exit;
}

// ── Extrai texto ───────────────────────────────────────────────
$text = '';
if (isset($msgData['message'])) {
    $m    = $msgData['message'];
    $text = $m['conversation']
         ?? $m['extendedTextMessage']['text']
         ?? $m['imageMessage']['caption']
         ?? $m['listResponseMessage']['singleSelectReply']['selectedRowId']
         ?? $m['buttonsResponseMessage']['selectedButtonId']
         ?? '';
} elseif (isset($data['message'])) {
    $m    = $data['message'];
    $text = $m['conversation'] ?? $m['extendedTextMessage']['text'] ?? '';
}

$text = trim($text);
if (empty($text)) {
    http_response_code(200);
    exit;
}

// ── Salva Mensagem no Histórico Geral da Central de Atendimento ──
try {
    $stmt = $pdo->prepare("INSERT INTO whatsapp_messages (remote_jid, from_me, message_text) VALUES (?, 0, ?)");
    $stmt->execute([$senderJid, $text]);
} catch (Exception $e) {}

// ── Normalização de Telefones ──────────────────────────────────
function normalizePhone(string $phone): string {
    $p = preg_replace('/\D/', '', $phone);
    if (strpos($p, '55') === 0 && strlen($p) >= 12) {
        $p = substr($p, 2);
    }
    return $p;
}

$normalizedSender = normalizePhone($sender);
$normalizedAdmin  = normalizePhone($adminPhone);
$isAdmin = ($normalizedSender === $normalizedAdmin || (!empty($adminPhone) && strpos($sender, $adminPhone) !== false));

// =========================================================
// 1. DETECÇÃO E CRIAÇÃO AUTOMÁTICA DE PEDIDO NO PAINEL DO SITE
// =========================================================
$isOrderAttempt = (
    preg_match('/(?:pedido|quero|buque|buquê|cesta|orquidea|orquídea|comprar|encomendar|valor|entrega|gostaria de pedir)/i', $text) ||
    isset($msgData['message']['orderMessage']) ||
    strpos($text, 'HELENA-ORDER') !== false
);

if ($isOrderAttempt && !$isAdmin) {
    try {
        // Find or create customer
        $uStmt = $pdo->prepare("SELECT id, name FROM users WHERE phone LIKE ? LIMIT 1");
        $uStmt->execute(["%$normalizedSender%"]);
        $user = $uStmt->fetch();

        if (!$user) {
            $insU = $pdo->prepare("INSERT INTO users (name, phone, role, is_lead) VALUES (?, ?, 'customer', 1)");
            $insU->execute([$pushName, $sender]);
            $userId = $pdo->lastInsertId();
        } else {
            $userId = $user['id'];
        }

        // Tenta identificar qual produto o cliente está pedindo
        $matchedProducts = [];
        $totalAmount = 0.00;

        $prods = $pdo->query("SELECT id, name, price FROM products WHERE active = 1")->fetchAll();
        foreach ($prods as $p) {
            // Se o nome do produto ou palavra-chave estiver na mensagem
            $firstWords = explode(' ', strtolower($p['name']))[0];
            if (mb_stripos($text, $p['name']) !== false || (strlen($firstWords) > 3 && mb_stripos($text, $firstWords) !== false)) {
                $matchedProducts[] = $p;
                $totalAmount += floatval($p['price']);
            }
        }

        // Se nenhum produto específico bateu exatamente, usa valor estimado base de buquê/pedido
        if (empty($matchedProducts)) {
            $matchedProducts[] = [
                'id' => null,
                'name' => 'Encomenda Personalizada WhatsApp (Helena Flores)',
                'price' => 300.00
            ];
            $totalAmount = 300.00;
        }

        // Insere Pedido no Banco de Dados (Painel do Site)
        $insO = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, admin_notes, created_at) VALUES (?, ?, 'pending', 'whatsapp', ?, NOW())");
        $insO->execute([$userId, $totalAmount, "Pedido recebido via WhatsApp Business: " . $text]);
        $orderId = $pdo->lastInsertId();

        // Insere Itens do Pedido
        foreach ($matchedProducts as $mp) {
            $insI = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, 1, ?, ?)");
            $insI->execute([$orderId, $mp['id'], $mp['name'], $mp['price'], $mp['price']]);
        }

        // Notifica o Administrador no Painel
        $notifMsg = "🌹 *NOVO PEDIDO NO PAINEL! (Helena Flores)*\n\n" .
                    "📦 *Pedido #{$orderId}*\n" .
                    "👤 *Cliente:* {$pushName} ({$sender})\n" .
                    "💰 *Total:* R$ " . number_format($totalAmount, 2, ',', '.') . "\n" .
                    "💬 *Mensagem:* {$text}\n\n" .
                    "👉 Acesse o painel para gerenciar e confirmar a entrega!";
        
        $notif->notifyAdmin($notifMsg);

        // Resposta Automática de Confirmação para o Cliente no WhatsApp
        $autoReply = "🌹 *Helena Flores — Jardins*\n\n" .
                     "Olá, {$pushName}! Recebemos o seu pedido com sucesso! ✨\n\n" .
                     "📋 *Pedido #{$orderId}*\n" .
                     "💰 *Valor Total:* R$ " . number_format($totalAmount, 2, ',', '.') . "\n\n" .
                     "Um de nossos floristas já visualizou sua solicitação no painel do site e responderá em instantes para combinar os detalhes da entrega! 🌸";

        $notif->send($senderJid, $autoReply);

        http_response_code(200);
        exit;

    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] ORDER ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// =========================================================
// 2. COMANDOS DO ADMIN OU ATENDIMENTO IA SDR
// =========================================================
if ($isAdmin && strpos($text, '!') === 0) {
    $cmd = strtolower(trim($text));
    switch ($cmd) {
        case '!pedidos':
            $today = date('Y-m-d');
            $count = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today'")->fetchColumn();
            $sum   = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)='$today'")->fetchColumn();
            $notif->notifyAdmin("🛒 *HELENA FLORES - HOJE*\nPedidos: *{$count}*\nTotal: *R$ " . number_format((float) $sum, 2, ',', '.') . "*");
            break;
        case '!ajuda':
            $notif->notifyAdmin("🤖 *PAINEL HELENA FLORES*\n!pedidos | !estoque | !ajuda");
            break;
    }
    http_response_code(200);
    exit;
}

// Resposta com IA SDR se ativa
if ($ai->isActive() && !$isAdmin) {
    $aiReply = $ai->generateResponse($text, $senderJid, false, $inst);
    if ($aiReply) {
        $notif->send($senderJid, $aiReply);
    }
}

http_response_code(200);
exit;
