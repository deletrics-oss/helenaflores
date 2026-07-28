<?php
// fix_users_table.php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h1>Atualizando Tabela de Usuários</h1>";
echo "<pre>";

function addColumnIfNotExists($pdo, $table, $column, $definition)
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            echo "✅ Coluna '$column' adicionada em '$table'.\n";
        } else {
            echo "ℹ️ Coluna '$column' já existe em '$table'.\n";
        }
    } catch (PDOException $e) {
        echo "❌ Erro ao adicionar '$column': " . $e->getMessage() . "\n";
    }
}

// Add Address Fields to Users Table
addColumnIfNotExists($pdo, 'users', 'document', 'VARCHAR(20) NULL AFTER phone');
addColumnIfNotExists($pdo, 'users', 'zip_code', 'VARCHAR(10) NULL AFTER document');
addColumnIfNotExists($pdo, 'users', 'street', 'VARCHAR(255) NULL AFTER zip_code');
addColumnIfNotExists($pdo, 'users', 'number', 'VARCHAR(20) NULL AFTER street');
addColumnIfNotExists($pdo, 'users', 'district', 'VARCHAR(100) NULL AFTER number');
addColumnIfNotExists($pdo, 'users', 'complement', 'VARCHAR(100) NULL AFTER district');
addColumnIfNotExists($pdo, 'users', 'city', 'VARCHAR(100) NULL AFTER complement');
addColumnIfNotExists($pdo, 'users', 'state', 'VARCHAR(2) NULL AFTER city');

echo "\n--- Concluído! ---\n";
echo "</pre>";
echo "<p><a href='index.php'>Voltar para a Loja</a></p>";
?>