<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';
$notif = new NotificationService($pdo);
$cfg = $notif->getConfig();

$url  = rtrim($cfg['notif_waapi_url'] ?? '', '/');
$key  = $cfg['notif_waapi_key'] ?? '';
$inst = $cfg['notif_waapi_instance'] ?? 'default';

echo "URL: $url\nInstance: $inst\nKey: $key\n";

$ch = curl_init("$url/chat/findChats/$inst");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $key]);
$res = curl_exec($ch);
curl_close($ch);
echo "CHATS:\n";
echo substr($res, 0, 500) . "\n";
