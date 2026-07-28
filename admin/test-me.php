<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/modules_shipping.php';

echo "<h1>Teste de Diagnóstico Melhor Envio (Debug)</h1>";

// 1. Mock Items
$items = [
    [
        'qty' => 1,
        'weight_kg' => 0.5,
        'length_cm' => 20,
        'width_cm' => 15,
        'height_cm' => 10,
        'price' => 100.00
    ]
];

// 2. Mock Zip (CEP SP)
$zip = '01311-000'; // Av Paulista

echo "<h3>1. Simulando Frete para: $zip</h3>";
echo "<pre>";
print_r($items);
echo "</pre>";

// 3. Run Calculation
$start = microtime(true);
$options = calculateShippingOptions($zip, $items);
$end = microtime(true);

echo "<h3>2. Resultado (Tempo: " . round($end - $start, 2) . "s)</h3>";

if (empty($options)) {
    echo "<h2 style='color:red'>FALHA: Nenhuma opção retornada.</h2>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Preço</th><th>Prazo</th></tr>";
    foreach ($options as $opt) {
        echo "<tr>";
        echo "<td>{$opt['id']}</td>";
        echo "<td>{$opt['icon']} {$opt['name']}</td>";
        echo "<td>R$ {$opt['price']}</td>";
        echo "<td>{$opt['days']} dias</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>3. Log de Debug (Ultimas linhas)</h3>";
if (file_exists(__DIR__ . '/../debug_me.log')) {
    echo "<pre>" . htmlspecialchars(file_get_contents(__DIR__ . '/../debug_me.log')) . "</pre>";
} else {
    echo "Nenhum log encontrado.";
}
?>