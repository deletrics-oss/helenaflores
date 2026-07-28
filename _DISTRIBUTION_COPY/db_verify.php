<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Users Table Structure</h1>";
try {
    $q = $pdo->query("DESCRIBE users");
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        foreach ($row as $v)
            echo "<td>$v</td>";
        echo "</tr>";
    }
    echo "</table>";

    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<p>Total Users: $count</p>";

    $leads = $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead = 1")->fetchColumn();
    echo "<p>Leads: $leads</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
