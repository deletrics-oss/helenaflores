<?php
require_once __DIR__ . '/includes/db.php';

try {
    echo "<h1>🛠️ Corretor de Banco de Dados</h1>";

    // 1. last_login
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL DEFAULT NULL");
        echo "✅ Coluna 'last_login' adicionada.<br>";
    } catch (Exception $e) {
        echo "ℹ️ Coluna 'last_login' já existe ou erro ignorável.<br>";
    }

    // 2. complement
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN complement VARCHAR(255) NULL DEFAULT NULL");
        echo "✅ Coluna 'complement' adicionada.<br>";
    } catch (Exception $e) {
        echo "ℹ️ Coluna 'complement' já existe ou erro ignorável.<br>";
    }

    // 3. reference
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN reference VARCHAR(255) NULL DEFAULT NULL");
        echo "✅ Coluna 'reference' adicionada.<br>";
    } catch (Exception $e) {
        echo "ℹ️ Coluna 'reference' já existe ou erro ignorável.<br>";
    }

    // 4. neighborhood (Neighborhood might be neighborhood or bairro in different versions)
    // The previous turned mentioned neighborhood being used in SQL.
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN neighborhood VARCHAR(255) NULL DEFAULT NULL");
        echo "✅ Coluna 'neighborhood' adicionada.<br>";
    } catch (Exception $e) {
        echo "ℹ️ Coluna 'neighborhood' já existe ou erro ignorável.<br>";
    }

    echo "<h2>🎉 Banco de dados atualizado com sucesso!</h2>";
    echo "<p><a href='index.php'>Voltar ao Início</a></p>";

} catch (Exception $e) {
    echo "❌ Erro Crítico: " . $e->getMessage();
}
