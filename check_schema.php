<?php
require_once 'config.php';
require_once 'includes/db.php';

$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
