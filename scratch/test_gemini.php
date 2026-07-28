<?php
require_once 'includes/db.php';
require_once 'includes/ai_sdr.php';

$ai = new AIService($pdo);

echo "--- TESTE DE CONEXÃO GEMINI ---\n";
if (!$ai->isActive()) {
    echo "❌ Erro: O módulo não está ativo ou a chave API está vazia no banco de dados.\n";
    
    // Debug: Check database content
    $stmt = $pdo->prepare("SELECT is_active, settings_json FROM module_settings WHERE module_key = 'ai_sdr'");
    $stmt->execute();
    $mod = $stmt->fetch();
    if ($mod) {
        echo "Status no DB: " . ($mod['is_active'] ? 'Ativo' : 'Inativo') . "\n";
        $keys = json_decode($mod['settings_json'], true);
        echo "Chave presente: " . (!empty($keys['gemini_key']) ? 'SIM (Começa com ' . substr($keys['gemini_key'], 0, 8) . ')' : 'NÃO') . "\n";
    } else {
        echo "Módulo 'ai_sdr' não encontrado na tabela module_settings.\n";
    }
    exit;
}

echo "✅ Módulo Ativo. Enviando pergunta de teste para o Google...\n";

$response = $ai->generateResponse("Diga 'Fight Arcade Conectado!' se você estiver me ouvindo.", "TEST_ID", true);

if ($response) {
    echo "🚀 RESPOSTA DA IA: " . $response . "\n";
    echo "--- TESTE CONCLUÍDO COM SUCESSO ---";
} else {
    echo "❌ Erro: A API do Gemini não respondeu. Verifique se a chave é válida e se tem créditos/cota disponível.\n";
}
