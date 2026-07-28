<?php
/**
 * admin/debug_errors.php — Fight Arcade
 * Script de varredura profunda para diagnosticar Erros 500.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Varredura de Erros Fight Arcade</h1>";
echo "<p>Testando carregamento real das classes e dependências...</p>";

$includes = [
    '../config.php',
    '../includes/db.php',
    '../includes/user_auth.php',
    '../includes/notifications.php',
    '../includes/lalamove.php'
];

foreach ($includes as $inc) {
    echo "<li>Testando include: <b>$inc</b>... ";
    try {
        require_once __DIR__ . '/' . $inc;
        echo "<span style='color:green'>[OK]</span></li>";
    } catch (Throwable $e) {
        echo "<span style='color:red'>[ERRO] " . $e->getMessage() . "</span> na linha " . $e->getLine() . " de " . $e->getFile() . "</li>";
    }
}

echo "<h3>Testando Páginas Principais (Modo Simulação):</h3>";

$pages = ['orders.php', 'rma.php', 'payment_accounts.php', 'customers.php', 'notifications.php', 'lalamove.php'];

foreach ($pages as $p) {
    echo "<li>Página: <b>$p</b>... ";
    // Usamos shell_exec para testar a execução isolada e capturar o erro
    $cmd = "php " . escapeshellarg(__DIR__ . '/' . $p);
    $out = shell_exec($cmd . " 2>&1");
    
    if (strpos($out, 'Fatal error') !== false || strpos($out, 'Parse error') !== false) {
        echo "<span style='color:red'>[CRASH]</span><pre style='background:#fee; padding:10px;'>" . htmlspecialchars(substr($out, 0, 500)) . "</pre></li>";
    } else {
        echo "<span style='color:green'>[PARECE OK]</span></li>";
    }
}

echo "<hr>";
echo "<a href='emergency_fix.php' style='background:#f1c40f; color:#000; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:5px;'>REEXECUTAR REPARO DE BANCO 🚀</a>";
echo " <a href='dashboard.php' style='background:#333; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>IR PARA O PAINEL</a>";
