<?php
require_once 'includes/db.php';
$stmt = $pdo->prepare("SELECT is_active, settings_json FROM module_settings WHERE module_key = 'ai_sdr'");
$stmt->execute();
$mod = $stmt->fetch();
$res = [
    'active' => $mod['is_active'],
    'json' => json_decode($mod['settings_json'], true)
];
file_put_contents('scratch/verify_result.json', json_encode($res, JSON_PRETTY_PRINT));
echo "Verificação concluída. Resultado em scratch/verify_result.json";
