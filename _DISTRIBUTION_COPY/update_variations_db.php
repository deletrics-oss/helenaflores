<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando Banco de Dados para Variações Avançadas...</h1>";

try {
    // 1. Add 'price_wholesale' column
    $check = $pdo->query("SHOW COLUMNS FROM product_variations LIKE 'price_wholesale'");
    if ($check->rowCount() == 0) {
        $pdo->query("ALTER TABLE product_variations ADD COLUMN price_wholesale DECIMAL(10,2) DEFAULT NULL AFTER price");
        echo "<p style='color:green;'>✅ Coluna 'price_wholesale' (Preço Atacado) criada.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Coluna 'price_wholesale' já existe.</p>";
    }

    // 2. Add 'image_path' column
    $check2 = $pdo->query("SHOW COLUMNS FROM product_variations LIKE 'image_path'");
    if ($check2->rowCount() == 0) {
        $pdo->query("ALTER TABLE product_variations ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER value");
        echo "<p style='color:green;'>✅ Coluna 'image_path' (Foto da Variação) criada.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Coluna 'image_path' já existe.</p>";
    }

    echo "<p>Banco atualizado com sucesso!</p>";
    echo "<a href='admin/products.php'>Voltar para Produtos</a>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
}
?>