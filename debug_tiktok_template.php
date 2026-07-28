<?php
/**
 * Script para analisar a estrutura do NOVO template TikTok
 */
$templatePath = __DIR__ . '/TikTokPlanilha/Tiktoksellercenter_batchupload_20260224_template.xlsx';

if (!file_exists($templatePath)) {
    die("Template não encontrado: $templatePath");
}

$zip = new ZipArchive();
if ($zip->open($templatePath) !== TRUE) {
    die("Não foi possível abrir o arquivo XLSX");
}

echo "<h1>Análise do Template TikTok (Novo)</h1>";

// Lê o sharedStrings
$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ssDoc = new DOMDocument();
    @$ssDoc->loadXML($ssXml);
    $texts = $ssDoc->getElementsByTagName('t');
    foreach ($texts as $t) {
        $sharedStrings[] = $t->textContent;
    }
}

// Tenta encontrar a sheet correta (Template/Modelo)
$workbookXml = $zip->getFromName('xl/workbook.xml');
$workbookDoc = new DOMDocument();
$workbookDoc->loadXML($workbookXml);
$sheets = $workbookDoc->getElementsByTagName('sheet');
$targetFile = 'xl/worksheets/sheet1.xml'; // fallback

foreach ($sheets as $sheet) {
    $name = $sheet->getAttribute('name');
    if (stripos($name, 'Template') !== false || stripos($name, 'Modelo') !== false) {
        $relId = $sheet->getAttribute('r:id');
        // Busca o path do arquivo no _rels
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $relsDoc = new DOMDocument();
        $relsDoc->loadXML($relsXml);
        foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
            if ($rel->getAttribute('Id') == $relId) {
                $targetFile = 'xl/' . ltrim($rel->getAttribute('Target'), '/');
                break;
            }
        }
        echo "<h3>Aba detectada: $name ($targetFile)</h3>";
        break;
    }
}

$sheetXml = $zip->getFromName($targetFile);
if ($sheetXml) {
    $doc = new DOMDocument();
    $doc->loadXML($sheetXml);
    $rows = $doc->getElementsByTagName('row');

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; background: #eee;'>";
    // Analisar as primeiras 15 linhas para achar os headers
    for ($i = 0; $i < min(15, $rows->length); $i++) {
        echo "<tr>";
        $row = $rows->item($i);
        $cells = $row->getElementsByTagName('c');
        foreach ($cells as $cell) {
            $value = "";
            if ($vNode = $cell->getElementsByTagName('v')->item(0)) {
                $value = $vNode->nodeValue;
                if ($cell->getAttribute('t') == 's' && isset($sharedStrings[$value])) {
                    $value = $sharedStrings[$value];
                }
            }
            $col = preg_replace('/[0-9]/', '', $cell->getAttribute('r'));
            echo "<td><b>$col</b>: " . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

$zip->close();
?>