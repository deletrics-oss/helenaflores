<?php
/**
 * api/ai_retention_msg.php — Fight Arcade
 * Usa Gemini para gerar abordagens de vendas personalizadas
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai_sdr.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? '';
$days = $data['days'] ?? 0;
$products = $data['products'] ?? '';

$ai = new AIService($pdo);

$prompt = "Aja como um SDR (Sales Development Representative) de elite da Fight Arcade, uma loja de peças de arcade e informática de alta performance.
O cliente se chama {$name}.
Sua última compra foi há {$days} dias.
Ele comprou anteriormente: {$products}.

Objetivo: Escreva uma mensagem curta, amigável e persuasiva para WhatsApp (máximo 300 caracteres).
- Se comprou há pouco tempo (< 30 dias), foque em pós-venda e satisfação.
- Se comprou há mais tempo (30-90 dias), foque em novidades no estoque e convite para voltar.
- Se sumiu há muito tempo (> 90 dias), ofereça um cupom imaginário 'VOLTA10' de 10% de desconto.
Use emojis e um tom de parceiro/entusiasta de tecnologia. Não seja robótico.";

try {
    $message = $ai->callGemini($prompt);
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
