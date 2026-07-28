<?php
require 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL DEFAULT NULL");
    echo "Coluna last_login adicionada com sucesso.";
} catch (Exception $e) {
    echo "Coluna last_login ja deve existir ou erro: " . $e->getMessage();
}
