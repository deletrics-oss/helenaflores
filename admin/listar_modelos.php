<?php
/**
 * admin/listar_modelos.php — Fight Arcade
 * Pergunta ao Google quais modelos a chave pode usar
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== LISTA DE MODELOS GEMINI DISPONÍVEIS ===\n\n";

// Pega a chave do banco
$stmt = $pdo->prepare("SELECT settings_json FROM module_settings WHERE module_key = 'ai_sdr'");
$stmt->execute();
$mod = $stmt->fetch();
$settings = json_decode($mod['settings_json'], true);
$apiKey = $settings['gemini_key'] ?? '';

if (!$apiKey) {
    echo "❌ ERRO: Chave API não encontrada no banco de dados.";
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status === 200) {
    $data = json_decode($response, true);
    if (isset($data['models'])) {
        foreach ($data['models'] as $m) {
            $name = str_replace('models/', '', $m['name']);
            echo "• $name (" . implode(', ', $m['supportedGenerationMethods']) . ")\n";
        }
    } else {
        echo "⚠️ Nenhum modelo listado. Resposta: " . $response;
    }
} else {
    echo "❌ ERRO na API (Status $status): " . $response;
}

echo "\n\n=== FIM DA LISTA ===";
?>
