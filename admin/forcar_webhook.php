<?php
/**
 * admin/forcar_webhook.php — Fight Arcade
 * Grava o Webhook na Evolution API na marra
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== FORÇADOR DE WEBHOOK ===\n\n";

$notif = new NotificationService($pdo);
$res = $notif->setWebhookEvolution();

if ($res['success']) {
    echo "✅ SUCESSO!\n";
    echo "   URL Gravada: " . $res['url'] . "\n";
    echo "   Método usado: " . $res['path'] . "\n";
} else {
    echo "❌ FALHA!\n";
    echo "   Erro: " . $res['error'] . "\n";
    echo "   Resposta da API: " . $res['api_response'] . "\n";
}

echo "\nAgora mande um 'Oi' no WhatsApp.";
?>
