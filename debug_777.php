<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h2>DATABASE TABLES:</h2>";
$q = $pdo->query("SHOW TABLES");
$tables = [];
while($r = $q->fetch()) {
    $t = current($r);
    $tables[] = $t;
    echo "- $t<br>";
}

echo "<h3>USERS WITH is_lead=1:</h3>";
$count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead = 1")->fetchColumn();
echo "Count: $count<br>";
if ($count > 0) {
    $last = $pdo->query("SELECT id, name, phone, created_at FROM users WHERE is_lead = 1 ORDER BY id DESC LIMIT 10")->fetchAll();
    echo "<pre>"; print_r($last); echo "</pre>";
}

if (in_array('leads', $tables)) {
    echo "<h3>OLD LEADS TABLE FOUND!</h3>";
    $count = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    echo "Count: $count<br>";
    $last = $pdo->query("SELECT * FROM leads ORDER BY id DESC LIMIT 10")->fetchAll();
    echo "<pre>"; print_r($last); echo "</pre>";
}
