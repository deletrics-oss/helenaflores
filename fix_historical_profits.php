<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Restaurando Preços de Custo em Pedidos Antigos...</h1>";

$count = 0;
// Buscar itens que estão com custo zero
$items = $pdo->query("SELECT oi.id, oi.product_id, p.cost_price 
                      FROM order_items oi 
                      JOIN products p ON oi.product_id = p.id 
                      WHERE oi.cost_price IS NULL OR oi.cost_price = 0")->fetchAll();

foreach ($items as $i) {
    if ($i['cost_price'] > 0) {
        $stmt = $pdo->prepare("UPDATE order_items SET cost_price = ? WHERE id = ?");
        $stmt->execute([$i['cost_price'], $i['id']]);
        $count++;
    }
}

echo "✅ Sucesso! $count itens de pedidos foram atualizados com o custo atual dos produtos.";
