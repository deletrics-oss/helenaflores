<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'notif_site_url'");
echo "SITE_URL: " . $stmt->fetchColumn() . "\n";
