<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->query('SELECT id, me_order_id FROM rma_tickets WHERE me_order_id IS NOT NULL ORDER BY id DESC LIMIT 10');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($rows);
