<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "TABLES IN DATABASE:\n";
$q = $pdo->query("SHOW TABLES");
while($r = $q->fetch()) {
    echo "- " . current($r) . "\n";
}

echo "\nCOUNT USERS IS_LEAD=1:\n";
$count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead = 1")->fetchColumn();
echo "- $count\n";

echo "\nLAST 5 USERS:\n";
$q = $pdo->query("SELECT id, name, is_lead, created_at FROM users ORDER BY id DESC LIMIT 5");
while($r = $q->fetch()) {
    echo "- #{$r['id']}: {$r['name']} (Lead: {$r['is_lead']}) - {$r['created_at']}\n";
}
