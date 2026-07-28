<?php
// catalogo/update_db_address.php
require_once 'config.php';
require_once 'includes/db.php';

try {
    // Adicionar colunas de endereço
    $sql = "ALTER TABLE users 
            ADD COLUMN zipcode VARCHAR(10) DEFAULT NULL,
            ADD COLUMN address VARCHAR(255) DEFAULT NULL,
            ADD COLUMN number VARCHAR(20) DEFAULT NULL,
            ADD COLUMN neighborhood VARCHAR(100) DEFAULT NULL,
            ADD COLUMN city VARCHAR(100) DEFAULT NULL,
            ADD COLUMN state VARCHAR(2) DEFAULT NULL";

    $pdo->query($sql);
    echo "<h1>Sucesso!</h1><p>Campos de endereço (CEP, Rua, etc) adicionados aos clientes.</p>";
    echo "<p>Pode apagar este arquivo da Hostinger agora.</p>";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "<h1>Tudo certo!</h1><p>As colunas já existem.</p>";
    } else {
        die("Erro: " . $e->getMessage());
    }
}
