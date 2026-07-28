<?php
require_once __DIR__ . '/includes/db.php';
echo "<h1>📋 Verificador de Produtos</h1>";

try {
    $stmt = $pdo->query("SELECT id, name, active, category_id FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        echo "❌ A tabela de produtos está VAZIA.";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background:#eee;'><th>ID</th><th>Nome</th><th>Status (Active)</th><th>Categoria ID</th></tr>";
        foreach ($products as $p) {
            $status = $p['active'] ? "✅ Ativo (1)" : "❌ Inativo (0)";
            echo "<tr><td>{$p['id']}</td><td>{$p['name']}</td><td>$status</td><td>{$p['category_id']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao ler produtos: " . $e->getMessage();
}
?>