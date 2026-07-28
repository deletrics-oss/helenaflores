<?php
require_once 'includes/db.php';
require_once 'includes/lalamove.php';

$llm = new LalamoveAPI($pdo);

// Reflection to access private request method
$reflector = new ReflectionClass('LalamoveAPI');
$method = $reflector->getMethod('request');
$method->setAccessible(true);

$res = $method->invoke($llm, 'GET', '/v3/cities/BR_SAO/services', []);

header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT);
