<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$settings = [
    'notif_provider' => 'waapi', // Evolution API
    'notif_waapi_url' => 'http://206.183.130.29:8084',
    'notif_waapi_key' => 'chatbot_premium_key_2026',
    'notif_waapi_instance' => 'FightArcade',
    'notif_site_url' => 'https://www.fightarcade.com.br/catalogo'
];

echo "<h2>Configurando Notificações (WhatsApp)...</h2><ul>";
foreach ($settings as $key => $val) {
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
        ->execute([$key, $val, $val]);
    echo "<li>$key: Configurado</li>";
}
echo "</ul>";
echo "<h3>✅ Evolution API configurada!</h3>";
echo "<p><strong>Próximo Passo:</strong> Você precisa acessar o painel da Evolution API em <code>http://206.183.130.29:8084/manager/</code>, criar uma instância chamada <strong>FightArcade</strong> e escanear o QR Code.</p>";
echo "<p><a href='admin/notifications.php'>Ir para Teste de Conectividade</a></p>";
?>
