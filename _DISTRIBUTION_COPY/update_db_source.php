<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<h2>Atualizando Banco de Dados: Coluna 'Source' (Origem)</h2>";

try {
    // Check if column exists
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'source'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN source VARCHAR(50) DEFAULT 'Indefinido' AFTER phone");
        echo "<p style='color:green'>✅ Coluna 'source' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color:orange'>ℹ️ Coluna 'source' já existe.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red'>Erro: " . $e->getMessage() . "</p>";
}
?>