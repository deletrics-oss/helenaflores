<?php
require 'config.php';
require 'includes/db.php';
$res = $pdo->query("SELECT * FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "Colunas da tabela 'users':\n";
print_r(array_keys($res));
?>
