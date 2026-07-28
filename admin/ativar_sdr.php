<?php
/**
 * admin/ativar_sdr.php — Fight Arcade
 * Liga o interruptor do SDR no banco de dados
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ATIVADOR AI SDR ===\n\n";

try {
    $stmt = $pdo->prepare("UPDATE module_settings SET is_active = 1 WHERE module_key = 'ai_sdr'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ SUCESSO: O SDR foi ativado no banco de dados!\n";
    } else {
        echo "⚠️ AVISO: O módulo já estava ativo ou não foi encontrado.\n";
    }
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "\nAgora rode o diag_ai.php novamente para confirmar.";
?>
