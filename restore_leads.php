<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h2>LEADS RESTORATION DEBUG</h2>";

// 1. Check all tables again
echo "<h3>ALL TABLES:</h3>";
$q = $pdo->query("SHOW TABLES");
while($r = $q->fetch()) {
    echo "- " . current($r) . "<br>";
}

// 2. Check if any user should be a lead
// Leads are users with is_lead=1. 
// If everyone is 0, maybe we can find them by role or by lack of orders?
echo "<h3>USERS STATUS:</h3>";
$total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$leads = $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead = 1")->fetchColumn();
$customers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead = 0")->fetchColumn();

echo "Total Users: $total<br>";
echo "Leads (is_lead=1): $leads<br>";
echo "Customers (is_lead=0): $customers<br>";

// 3. Look for "hidden" leads
// A lead might be someone who has never placed an order?
$hidden = $pdo->query("
    SELECT COUNT(*) FROM users u 
    WHERE u.role != 'admin' 
    AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)
    AND u.is_lead = 0
")->fetchColumn();

echo "Users with NO orders and is_lead=0: $hidden<br>";

if ($hidden > 0 && $leads == 0) {
    echo "<h4>POTENTIAL FIX: Converting users with no orders back to leads...</h4>";
    // Uncomment this to fix:
    // $pdo->exec("UPDATE users SET is_lead = 1 WHERE role != 'admin' AND NOT EXISTS (SELECT 1 FROM orders WHERE user_id = users.id)");
}

echo "<h3>LAST 20 USERS:</h3>";
$q = $pdo->query("SELECT id, name, email, phone, is_lead, role, created_at FROM users ORDER BY id DESC LIMIT 20");
echo "<table border='1'><tr><th>ID</th><th>Nome</th><th>Email</th><th>Lead?</th><th>Role</th><th>Data</th></tr>";
while($r = $q->fetch()) {
    echo "<tr><td>{$r['id']}</td><td>{$r['name']}</td><td>{$r['email']}</td><td>{$r['is_lead']}</td><td>{$r['role']}</td><td>{$r['created_at']}</td></tr>";
}
echo "</table>";
