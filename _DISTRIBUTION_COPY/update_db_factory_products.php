<?php
// catalogo/update_db_factory_products.php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando Produtos de Fábrica...</h1>";

try {
    // Add is_manufactured column
    $sql = "ALTER TABLE products ADD COLUMN is_manufactured TINYINT(1) NOT NULL DEFAULT 0";
    $pdo->exec($sql);

    echo "<p>✅ Coluna 'is_manufactured' (Fabricação Própria) adicionada.</p>";
    echo "<h2>Pronto! Agora você pode marcar o que é feito na fábrica.</h2>";

} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "<p>⚠️ Coluna já existe.</p>";
    } else {
        die("Erro: " . $e->getMessage());
    }
}
