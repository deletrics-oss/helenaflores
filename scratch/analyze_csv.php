<?php
$csvPath = 'c:/Users/ILHA/Documents/GitHub/helenaflores/_catalogo referencia/produtos400.csv';

if (!file_exists($csvPath)) {
    echo "Arquivo CSV não encontrado: $csvPath\n";
    exit(1);
}

$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle);

echo "Header columns: " . implode(', ', $header) . "\n";

$total = 0;
$withPrice = 0;
$withImgUrl = 0;
$withImgData = 0;
$sampleProducts = [];

while (($row = fgetcsv($handle)) !== false) {
    if (empty($row[1])) continue; // name is empty
    $total++;

    $index = $row[0] ?? '';
    $name = $row[1] ?? '';
    $priceRaw = $row[2] ?? '';
    $priceVal = $row[3] ?? '';
    $currency = $row[4] ?? '';
    $description = $row[5] ?? '';
    $productLink = $row[6] ?? '';
    $imageUrl = $row[7] ?? '';
    $imageData = $row[8] ?? '';

    if (!empty($priceVal) || !empty($priceRaw)) $withPrice++;
    if (!empty($imageUrl)) $withImgUrl++;
    if (!empty($imageData)) $withImgData++;

    if (count($sampleProducts) < 10) {
        $sampleProducts[] = [
            'index' => $index,
            'name' => $name,
            'price' => $priceVal ?: $priceRaw,
            'desc' => mb_strimwidth($description, 0, 40, '...'),
            'has_url' => !empty($imageUrl),
            'has_data' => !empty($imageData)
        ];
    }
}

fclose($handle);

echo "\n--- RESUMO DE PRODUTOS NO CSV ---\n";
echo "Total de produtos válidos: $total\n";
echo "Produtos com Preço: $withPrice\n";
echo "Produtos com URL de imagem: $withImgUrl\n";
echo "Produtos com Dados da imagem (Base64): $withImgData\n";

echo "\n--- AMOSTRA DE PRODUTOS ---\n";
foreach ($sampleProducts as $p) {
    echo "#{$p['index']} - {$p['name']} (Preço: {$p['price']}) - Img URL: " . ($p['has_url'] ? 'SIM' : 'NÃO') . " | Base64: " . ($p['has_data'] ? 'SIM' : 'NÃO') . "\n";
}
