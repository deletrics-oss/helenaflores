<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h2>EXECUTING LEADS RESTORATION...</h2>";

// 1. Mark users with NO orders as Leads (unless they are admins)
$sql = "
    UPDATE users 
    SET is_lead = 1 
    WHERE role != 'admin' 
    AND NOT EXISTS (SELECT 1 FROM orders WHERE user_id = users.id)
";
$affected = $pdo->exec($sql);

echo "SUCCESS! 🚀<br>";
echo "Total Leads restored: <strong>$affected</strong><br>";

echo "<br><a href='admin/leads.php'>Clique aqui para voltar aos Leads</a>";
