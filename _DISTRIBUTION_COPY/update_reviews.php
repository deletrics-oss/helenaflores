<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando Banco de Dados para Avaliações...</h1>";

try {
    // 1. Add 'approved' column to product_reviews if not exists
    $check = $pdo->query("SHOW COLUMNS FROM product_reviews LIKE 'approved'");
    if ($check->rowCount() == 0) {
        $pdo->query("ALTER TABLE product_reviews ADD COLUMN approved TINYINT(1) DEFAULT 0");
        echo "<p style='color:green;'>✅ Coluna 'approved' criada na tabela de avaliações.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Coluna 'approved' já existe.</p>";
    }

    echo "<p>Tudo pronto!</p>";
    echo "<a href='admin/reviews.php'>Ir para Admin de Avaliações</a>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
}
?>