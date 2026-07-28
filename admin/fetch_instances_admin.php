<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';
$notif = new NotificationService($pdo);
$cfg = $notif->getConfig();
$url = rtrim($cfg['notif_waapi_url'] ?? '', '/');
$key = $cfg['notif_waapi_key'] ?? '';

echo "URL: $url\n";
echo "KEY: " . substr($key, 0, 5) . "...\n";

$ch = curl_init("$url/instance/fetchInstances");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $key]);
$res = curl_exec($ch);
echo "INSTANCES:\n$res\n";
