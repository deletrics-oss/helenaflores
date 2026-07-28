<?php
require 'config.php';
require 'includes/db.php';
$stmt = $pdo->query("SELECT o.id, o.user_id, u.name, o.created_at, o.total_amount FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 5");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
