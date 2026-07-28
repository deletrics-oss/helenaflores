<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN wa_notify_tracking TINYINT(1) DEFAULT 1");
    echo "Column wa_notify_tracking added.\n";
} catch (Exception $e) {
    echo "Column wa_notify_tracking might already exist: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN last_tracking_status VARCHAR(50) DEFAULT NULL");
    echo "Column last_tracking_status added.\n";
} catch (Exception $e) {
    echo "Column last_tracking_status might already exist: " . $e->getMessage() . "\n";
}
