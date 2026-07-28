<?php
/**
 * Script para analisar a estrutura do template Shopee
 */
$templatePath = __DIR__ . '/modeloplanilhasshopeemercadolivre/Shopee_mass_upload_2026-02-05_basic_template.xlsx';

if (!file_exists($templatePath)) {
    die("Template não encontrado: $templatePath");
}

$zip = new ZipArchive();
if ($zip->open($templatePath) !== TRUE) {
    die("Não foi possível abrir o arquivo XLSX");
}

echo "<h1>Análise do Template Shopee</h1>";

// Lista todos os arquivos no XLSX
echo "<h2>Arquivos no XLSX:</h2>";
echo "<ul>";
for ($i = 0; $i < $zip->numFiles; $i++) {
    echo "<li>" . $zip->getNameIndex($i) . "</li>";
}
echo "</ul>";

// Lê o sharedStrings (texto do Excel)
$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ssDoc = new DOMDocument();
    $ssDoc->loadXML($ssXml);
    $texts = $ssDoc->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't');
    foreach ($texts as $t) {
        $sharedStrings[] = $t->textContent;
    }
}

echo "<h2>Strings Compartilhadas (Headers do Template):</h2>";
echo "<pre>";
foreach (array_slice($sharedStrings, 0, 50) as $i => $str) {
    echo "[$i] " . htmlspecialchars($str) . "\n";
}
echo "</pre>";

// Lê a primeira sheet
$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
if ($sheetXml) {
    echo "<h2>Estrutura da Sheet1:</h2>";
    $doc = new DOMDocument();
    $doc->loadXML($sheetXml);

    $rows = $doc->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
    echo "<p>Total de linhas: " . $rows->length . "</p>";

    echo "<h3>Primeiras 10 linhas:</h3>";
    echo "<table border='1'>";
    $count = 0;
    foreach ($rows as $row) {
        if ($count >= 10)
            break;
        echo "<tr>";
        $cells = $row->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
        foreach ($cells as $cell) {
            $v = $cell->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'v');
            $value = $v->length > 0 ? $v->item(0)->textContent : '';

            // Se for string compartilhada (t="s"), busca o índice
            if ($cell->getAttribute('t') === 's' && isset($sharedStrings[(int) $value])) {
                $value = $sharedStrings[(int) $value];
            }

            echo "<td>" . htmlspecialchars(substr($value, 0, 50)) . "</td>";
        }
        echo "</tr>";
        $count++;
    }
    echo "</table>";
}

$zip->close();

echo "<h2>Conclusão</h2>";
echo "<p>Use essas informações para ajustar o exportador.</p>";
?>