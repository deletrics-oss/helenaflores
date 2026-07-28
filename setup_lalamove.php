<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$settings = [
    'llm_api_key' => 'pk_prod_c362e472423ae5fce03a684e677171a0',
    'llm_api_secret' => 'sk_prod_TZsvpAv0VIaAFJ5op/AAxTAQu/alIG95STVyAgXXAIWgWy2gnA7sLlN5L3NcYUxD',
    'llm_sandbox' => '0', // Produção
    'llm_store_name' => 'Fight Arcade',
    'llm_store_phone' => '+5511984343166',
    'llm_store_address' => 'Rua Cristiano Osorio, 143, Vila Esperança, São Paulo, SP',
    'llm_store_lat' => '-23.543598',
    'llm_store_lng' => '-46.574902'
];

echo "<h2>Configurando Lalamove...</h2><ul>";
foreach ($settings as $key => $val) {
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
        ->execute([$key, $val, $val]);
    echo "<li>$key: Configurado</li>";
}
echo "</ul>";
echo "<h3>✅ Lalamove configurado em modo Produção com sucesso!</h3>";
echo "<p><a href='admin/lalamove.php'>Voltar para o Painel Lalamove</a></p>";
?>
