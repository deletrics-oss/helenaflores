<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->query("SELECT id, name, slug FROM products WHERE name LIKE '%botão%' OR name LIKE '%botao%' LIMIT 20");
while($r = $stmt->fetch()) {
    echo "ID: {$r['id']} | Nome: {$r['name']} | Slug: {$r['slug']}\n";
}
?>
