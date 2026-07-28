<?php
/**
 * admin/teste_real.php — Fight Arcade
 * Teste de fogo com modelos 2.5 e 3.1
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== TESTE DE CONEXÃO GEMINI (FUTURE MODELS) ===\n\n";

// Pega a chave do banco
$stmt = $pdo->prepare("SELECT settings_json FROM module_settings WHERE module_key = 'ai_sdr'");
$stmt->execute();
$mod = $stmt->fetch();
$settings = json_decode($mod['settings_json'], true);
$apiKey = $settings['gemini_key'] ?? '';

if (!$apiKey) {
    echo "❌ ERRO: Chave API não encontrada!";
    exit;
}

// Modelos que vimos na sua lista
$modelosParaTestar = ['gemini-2.5-flash', 'gemini-3.1-flash-lite-preview', 'gemini-2.0-flash-lite'];

foreach ($modelosParaTestar as $model) {
    echo "[TESTANDO MODELO: $model]\n";
    
    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => 'Olá! Responda apenas OK.']]]
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 10
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200) {
        $data = json_decode($response, true);
        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem texto';
        echo "✅ SUCESSO! Resposta: $aiText\n\n";
    } else {
        echo "❌ FALHOU (Status $status): " . substr($response, 0, 200) . "...\n\n";
    }
}

echo "=== FIM DO TESTE ===";
?>
