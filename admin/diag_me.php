<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/melhorenvio.php';

$me_id = $_GET['id'] ?? '';
if (!$me_id) die('Pass ?id=...');

$me = new MelhorEnvioAPI($pdo);
$res = $me->tracking([$me_id]);

header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT);
