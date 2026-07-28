<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/lalamove.php';

$llm = new LalamoveAPI($pdo);
$res = $llm->getCities();

header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT);
