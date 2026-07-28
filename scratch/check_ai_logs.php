<?php
require_once 'includes/db.php';
echo "ÚLTIMOS LOGS DE IA:\n";
$stmt = $pdo->query("SELECT * FROM ai_logs ORDER BY created_at DESC LIMIT 10");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$logs) {
    echo "Nenhum log encontrado na tabela ai_logs.\n";
} else {
    print_r($logs);
}
