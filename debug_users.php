<?php
require 'config.php';
require 'includes/db.php';
$stmt = $pdo->query("DESCRIBE users");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('users_structure.txt', print_r($cols, true));
echo "Estrutura salva em users_structure.txt";
?>
