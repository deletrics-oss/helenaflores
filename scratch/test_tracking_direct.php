<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/melhorenvio.php';

$me_id = 'a1c3b57d-053d-4515-a5e6-6be2c731058c';
$me = new MelhorEnvioAPI($pdo);
$res = $me->tracking([$me_id]);

print_r($res);
