<?php
// DIAGNOSTIC SCRIPT - Fight Arcade
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>🔍 Diagnóstico do Site</h1>";
echo "<hr>";

// 1. Test Config
echo "<h2>1. Config.php</h2>";
try {
    require_once __DIR__ . '/config.php';
    echo "✅ Config carregado com sucesso. BASE_URL = " . BASE_URL . "<br>";
} catch (Exception $e) {
    echo "❌ Erro no Config: " . $e->getMessage() . "<br>";
}

// 2. Test DB
echo "<h2>2. Banco de Dados</h2>";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "✅ Conexão com banco OK<br>";

    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    $cnt = $stmt->fetch()['cnt'];
    echo "✅ Tabela users: $cnt registros<br>";

    // Check columns
    $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    echo "ℹ️ Colunas da tabela users: " . implode(', ', $cols) . "<br>";

} catch (Exception $e) {
    echo "❌ Erro no DB: " . $e->getMessage() . "<br>";
}

// 3. Test Session
echo "<h2>3. Sessão</h2>";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
echo "✅ Session OK. ID: " . session_id() . "<br>";
echo "ℹ️ user_id: " . ($_SESSION['user_id'] ?? 'não definido') . "<br>";

// 4. Test Header
echo "<h2>4. Header Public</h2>";
try {
    // Capture output
    ob_start();
    include __DIR__ . '/includes/header_public.php';
    $headerOutput = ob_get_clean();
    echo "✅ Header carregado (" . strlen($headerOutput) . " bytes)<br>";
} catch (Exception $e) {
    echo "❌ Erro no Header: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "❌ FATAL no Header: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

// 5. Test Products Query
echo "<h2>5. Query de Produtos</h2>";
try {
    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1 LIMIT 5";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();
    echo "✅ Query OK. " . count($products) . " produtos encontrados<br>";
} catch (Exception $e) {
    echo "❌ Erro na query: " . $e->getMessage() . "<br>";
}

// 6. Test Banners
echo "<h2>6. Banners</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM banners WHERE active = 1");
    $cnt = $stmt->fetch()['cnt'];
    echo "✅ Banners ativos: $cnt<br>";
} catch (Exception $e) {
    echo "❌ Erro em banners: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>🎯 Resultado</h2>";
echo "<p>Se todos os testes passaram, o problema pode ser no output buffer ou em algum include específico.</p>";
echo "<p>Tente limpar a sessão: <a href='logout.php'>Logout</a></p>";
echo "<p><a href='index.php'>Tentar Index.php</a></p>";
