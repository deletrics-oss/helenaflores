<?php
require 'config.php';
require 'includes/db.php';
$res = $pdo->query("SELECT * FROM orders LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "Colunas encontradas na tabela 'orders':\n";
print_r(array_keys($res));
?>
