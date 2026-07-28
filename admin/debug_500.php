<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Completo do Sistema</h1>";

$files = [
    '../includes/lalamove.php',
    '../includes/notifications.php',
    'lalamove.php',
];

foreach ($files as $file) {
    echo "Testando include do <b>$file</b>...<br>";
    try {
        require_once __DIR__ . '/' . $file;
        echo "<span style='color:green'>[OK] $file carregado sem erros fatais!</span><br><br>";
    } catch (Throwable $e) {
        echo "<span style='color:red'>[ERRO FATAL] em $file: " . $e->getMessage() . " na linha " . $e->getLine() . "</span><br><br>";
    }
}

echo "<hr><h3>Verificação de Sintaxe Direta:</h3>";
// Simula o carregamento da classe para ver se a função duplicada ainda existe
if (class_exists('NotificationService')) {
    $reflector = new ReflectionClass('NotificationService');
    if ($reflector->hasMethod('lalamoveOnTheWay')) {
        echo "<span style='color:green'>[OK] O método lalamoveOnTheWay existe e a classe foi lida corretamente sem duplicidade!</span><br>";
    }
}

echo "<br><b>Se tudo acima estiver verde, a fundação do seu sistema voltou ao normal!</b> Pode abrir a página orders.php.";
