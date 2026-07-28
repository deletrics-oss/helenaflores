<?php
require_once 'includes/db.php';
$stmt = $pdo->query("DESCRIBE products");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
