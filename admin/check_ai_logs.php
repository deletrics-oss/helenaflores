<?php
require_once __DIR__ . '/../includes/db.php';
echo "<pre>ÚLTIMOS LOGS DE IA:\n";
$stmt = $pdo->query("SELECT * FROM ai_logs ORDER BY created_at DESC LIMIT 20");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$logs) {
    echo "Nenhum log encontrado na tabela ai_logs.";
} else {
    print_r($logs);
}
echo "</pre>";
