<?php
/**
 * admin/ajax_ai_knowledge_gen.php — Fight Arcade
 * Gera conhecimento estruturado a partir de texto bruto (WhatsApp) usando Gemini
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/ai_sdr.php';
isAdmin();

header('Content-Type: application/json');

$rawText = $_POST['raw_text'] ?? '';
if (empty($rawText)) {
    echo json_encode(['success' => false, 'error' => 'Texto vazio']);
    exit;
}

$ai = new AIService($pdo);
if (!$ai->isActive()) {
    echo json_encode(['success' => false, 'error' => 'API da IA não configurada']);
    exit;
}

$prompt = "Aja como um especialista em treinamento de IA. Recebi o seguinte texto de uma conversa de suporte/venda:\n\n"
        . "\"$rawText\"\n\n"
        . "Extraia as informações e formate-as rigorosamente como um JSON com os seguintes campos:\n"
        . "- title: Um título curto e claro (gatilho).\n"
        . "- content: A resposta técnica ou comercial explicada.\n"
        . "- category: Uma destas: suporte, pos-venda, venda, estampas, drivers.\n"
        . "- bot_role: Uma destas: suporte, vendas.\n"
        . "- tags: Palavras-chave separadas por vírgula.\n"
        . "- ai_instructions: Uma instrução curta sobre como a IA deve agir neste caso específico.\n\n"
        . "Responda APENAS o JSON puro, sem blocos de código ou explicações.";

$response = $ai->generateResponse($prompt, "admin_gen", true);

if ($response) {
    // Remove potential markdown blocks if AI ignored "puro" instructions
    $response = trim(str_replace(['```json', '```'], '', $response));
    $data = json_decode($response, true);
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'error' => 'IA não gerou um JSON válido: ' . $response]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Falha na comunicação com a IA']);
}
