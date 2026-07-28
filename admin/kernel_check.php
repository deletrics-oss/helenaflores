<?php
/**
 * kernel_check.php — Fight Arcade
 * Diagnóstico de baixo nível para encontrar Erros 500 invisíveis.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnóstico de Kernel Fight Arcade</h1>";
echo "<pre>";

// 1. Verificar PHP
echo "PHP Version: " . phpversion() . "\n";
echo "PDO Loaded: " . (extension_loaded('pdo') ? '✅' : '❌') . "\n";
echo "CURL Loaded: " . (extension_loaded('curl') ? '✅' : '❌') . "\n";

// 2. Tentar carregar Config
echo "\nCarregando config.php... ";
try {
    require_once __DIR__ . '/../config.php';
    echo "✅ OK\n";
} catch (Throwable $e) {
    echo "❌ FALHA: " . $e->getMessage() . "\n";
}

// 3. Tentar carregar DB
echo "Carregando includes/db.php... ";
try {
    require_once __DIR__ . '/../includes/db.php';
    echo "✅ OK\n";
} catch (Throwable $e) {
    echo "❌ FALHA: " . $e->getMessage() . "\n";
}

// 4. Testar Conexão PDO
if (isset($pdo)) {
    echo "Testando Conexão PDO... ";
    try {
        $pdo->query("SELECT 1");
        echo "✅ CONECTADO\n";
    } catch (Throwable $e) {
        echo "❌ ERRO DE CONEXÃO: " . $e->getMessage() . "\n";
    }
}

// 5. Testar Inclusão de Classes Críticas
$classes = [
    'LalamoveAPI' => '../includes/lalamove.php',
    'NotificationService' => '../includes/notifications.php',
    'MelhorEnvioAPI' => '../includes/melhorenvio.php'
];

foreach ($classes as $class => $file) {
    echo "Testando $class ($file)... ";
    try {
        require_once __DIR__ . '/' . $file;
        if (class_exists($class)) {
            echo "✅ CLASSE OK\n";
            // Tentar instanciar
            new $class($pdo);
            echo "   -> Instanciação: ✅ OK\n";
        } else {
            echo "❌ CLASSE NÃO ENCONTRADA NO ARQUIVO\n";
        }
    } catch (Throwable $e) {
        echo "❌ CRASH: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

// 6. Verificar Tabelas Específicas
$tables = ['orders', 'order_items', 'users', 'rma_tickets', 'notification_log'];
foreach ($tables as $t) {
    echo "Verificando Tabela '$t'... ";
    try {
        $pdo->query("SELECT * FROM $t LIMIT 1");
        echo "✅ OK\n";
    } catch (Throwable $e) {
        echo "❌ ERRO: " . $e->getMessage() . "\n";
    }
}

echo "\n--- FIM DO DIAGNÓSTICO ---";
echo "</pre>";
