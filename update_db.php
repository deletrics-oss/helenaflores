<?php
// catalogo/update_db.php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando Banco de Dados...</h1>";

try {
    // 1. Adicionar shipping_method na tabela orders
    try {
        $pdo->query("ALTER TABLE orders ADD COLUMN shipping_method VARCHAR(50) DEFAULT 'A Combinar'");
        echo "<p>✅ Coluna 'shipping_method' adicionada em 'orders'.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Duplicate column") !== false) {
            echo "<p>ℹ️ Coluna 'shipping_method' já existia.</p>";
        } else {
            echo "<p>❌ Erro em orders: " . $e->getMessage() . "</p>";
        }
    }

    // 2. Adicionar campos de endereço na tabela users (O QUE FALTA PARA O ERRO SUMIR)
    $cols = [
        "zipcode VARCHAR(10) DEFAULT NULL",
        "address VARCHAR(255) DEFAULT NULL",
        "number VARCHAR(20) DEFAULT NULL",
        "neighborhood VARCHAR(100) DEFAULT NULL",
        "city VARCHAR(100) DEFAULT NULL",
        "state VARCHAR(2) DEFAULT NULL"
    ];

    foreach ($cols as $col) {
        try {
            $pdo->query("ALTER TABLE users ADD COLUMN $col");
            echo "<p>✅ Coluna de endereço adicionada: " . explode(' ', $col)[0] . "</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Duplicate column") !== false) {
                echo "<p>ℹ️ Coluna " . explode(' ', $col)[0] . " já existia.</p>";
            } else {
                echo "<p>❌ Erro em users: " . $e->getMessage() . "</p>";
            }
        }
    }

    echo "<h2>Tudo Pronto! Pode voltar para o Admin.</h2>";
    echo "<p>Apague este arquivo do servidor depois.</p>";

} catch (Exception $e) {
    die("Erro Geral: " . $e->getMessage());
}
