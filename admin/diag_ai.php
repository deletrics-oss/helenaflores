<?php
/**
 * admin/diag_ai.php — Fight Arcade
 * Script de diagnóstico para o SDR — v1.4 (Deep Debug)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

$notif = new NotificationService($pdo);
$cfg = $notif->getConfig();

header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO AI SDR v1.4 (Deep Debug) ===\n\n";

// 1. Verificar Módulo
echo "[1] Módulo no banco...\n";
$stmt = $pdo->prepare("SELECT is_active, settings_json FROM module_settings WHERE module_key = 'ai_sdr'");
$stmt->execute();
$mod = $stmt->fetch();
if ($mod) {
    echo "✅ Status: " . ($mod['is_active'] ? 'ATIVO' : 'DESATIVADO') . "\n";
}

// 2. Webhook
echo "\n[2] Webhook...\n";
$baseUrl = defined('BASE_URL') ? BASE_URL : ($cfg['notif_site_url'] ?? 'https://fightarcade.com.br/catalogo');
if (strpos($baseUrl, 'http') === false) { $baseUrl = 'https://' . ltrim($baseUrl, '/'); }
$webhookUrl = rtrim($baseUrl, '/') . '/webhook_evolution.php';
echo "   URL: $webhookUrl\n";

// 3. Debug Log
echo "\n[3] Chegada de Mensagens (webhook_debug.log)...\n";
$logFile = __DIR__ . '/../webhook_debug.log';
if (file_exists($logFile)) {
    $lines = explode("\n", file_get_contents($logFile));
    $last = array_slice(array_filter($lines), -1);
    echo "   ✅ Última entrada: " . (isset($last[0]) ? substr($last[0], 0, 150) . "..." : "Vazio") . "\n";
} else {
    echo "   ❌ Nenhuma mensagem chegou ainda.\n";
}

// 4. Detalhes do Erro Gemini
echo "\n[4] Histórico de Erros Gemini (ai_logs):\n";
$logs = $pdo->query("SELECT created_at, response, message FROM ai_logs WHERE type = 'error' ORDER BY id DESC LIMIT 3")->fetchAll();
if (!$logs) {
    echo "   Nenhum erro registrado.\n";
} else {
    foreach($logs as $l) {
        echo "   [{$l['created_at']}]\n";
        echo "   MENSAGEM: " . substr($l['message'], 0, 50) . "...\n";
        echo "   RESPOSTA DO GOOGLE: {$l['response']}\n\n";
    }
}

echo "=== FIM DO DIAGNÓSTICO ===\n";
?>
