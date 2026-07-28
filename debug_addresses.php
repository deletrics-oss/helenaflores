<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Debug</h1>";

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>";
    print_r($tables);
    echo "</pre>";

    if (in_array('user_addresses', $tables)) {
        echo "<h2 style='color:green'>Table 'user_addresses' EXISTS.</h2>";
        $desc = $pdo->query("DESCRIBE user_addresses")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($desc);
        echo "</pre>";
    } else {
        echo "<h2 style='color:red'>Table 'user_addresses' DOES NOT EXIST.</h2>";
        echo "<p>Attempting to create it...</p>";

        $sql = "CREATE TABLE `user_addresses` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `name` varchar(50) NOT NULL,
          `recipient_name` varchar(100) NOT NULL,
          `zipcode` varchar(10) NOT NULL,
          `address` varchar(255) NOT NULL,
          `number` varchar(20) NOT NULL,
          `complement` varchar(100) DEFAULT NULL,
          `neighborhood` varchar(100) NOT NULL,
          `city` varchar(100) NOT NULL,
          `state` varchar(2) NOT NULL,
          `is_default` tinyint(1) DEFAULT 0,
          `created_at` timestamp NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
        echo "<h2 style='color:green'>Table 'user_addresses' CREATED SUCCESSFULLY.</h2>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>