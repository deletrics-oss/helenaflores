<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h1>Diagnóstico de Sistema</h1>";
echo "<p>BASE_URL definida como: <strong>" . BASE_URL . "</strong></p>";
echo "<p>Caminho absoluto: <strong>" . __DIR__ . "</strong></p>";

echo "<h2>Testando Banco de Dados...</h2>";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p style='color:green'>✅ Conexão bem sucedida!</p>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tabelas encontradas:</h3><ul>";
    foreach ($tables as $t) {
        echo "<li>$t</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>