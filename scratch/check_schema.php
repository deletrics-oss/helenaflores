<?php
require_once 'includes/db.php';
$stmt = $pdo->query("DESCRIBE products");
print_r($stmt->fetchAll());
$stmt = $pdo->query("DESCRIBE orders");
print_r($stmt->fetchAll());
$stmt = $pdo->query("DESCRIBE order_items");
print_r($stmt->fetchAll());
