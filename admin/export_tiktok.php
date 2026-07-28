<?php
/**
 * Exportador TikTok Shop v2 - Template Based (XML Implementation)
 * Lógica: Clona o template XLSX, abre o XML da planilha e injeta os dados nas colunas mapeadas.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

isAdmin();
set_time_limit(300);

// Debug (Desativar em produção se gerar lixo no arquivo)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Pastas de templates
$templateDir = __DIR__ . '/../modeloplanilhasshopeemercadolivre/';
$tiktokAltDir = __DIR__ . '/../TikTokPlanilha/';

// Helper para localizar o template TikTok
function findTikTokTemplate($dirs)
{
    if (!is_array($dirs))
        $dirs = [$dirs];
    $candidates = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir))
            continue;
        $files = glob($dir . '*.xlsx');
        foreach ($files as $file) {
            if (stripos(basename($file), 'TikTok') !== false) {
                $candidates[$file] = filemtime($file);
            }
        }
    }
    if (empty($candidates))
        return null;
    arsort($candidates);
    return key($candidates);
}

$templatePath = findTikTokTemplate([$templateDir, $tiktokAltDir]);

if (!$templatePath) {
    die('<h1>Erro: Template não encontrado</h1><p>Por favor, vá em "Integrações > TikTok > Gerenciar Template" e suba o modelo oficial.</p>');
}

// Busca produtos selecionados
$selectedIds = [];
if (isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
    $rawIds = $_POST['selected_ids'];
    if (is_array($rawIds)) {
        $selectedIds = array_map('intval', $rawIds);
    } elseif (is_string($rawIds)) {
        $decoded = json_decode($rawIds, true);
        if (is_array($decoded))
            $selectedIds = array_map('intval', $decoded);
    }
}

if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders)");
    $stmt->execute($selectedIds);
} else {
    $stmt = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1 AND (p.allow_export = 1 OR p.allow_export IS NULL)");
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    die('<h1>Nenhum produto encontrado!</h1>');
}

// CLONA o template para um arquivo temporário
$tempFile = tempnam(sys_get_temp_dir(), 'tiktok_export_');
copy($templatePath, $tempFile);

// Abre o XLSX (ZIP)
$zip = new ZipArchive();
if ($zip->open($tempFile) !== TRUE) {
    die('Erro ao abrir o arquivo de template.');
}

// 1. Identifica a Planilha (Sheet) correta
$workbookXml = $zip->getFromName('xl/workbook.xml');
$workbookDoc = new DOMDocument();
$workbookDoc->loadXML($workbookXml);
$sheets = $workbookDoc->getElementsByTagName('sheet');
$targetRelId = null;

// Tenta achar pelo nome "Template" ou "Modelo"
foreach ($sheets as $sheet) {
    $name = $sheet->getAttribute('name');
    if (stripos($name, 'Template') !== false || stripos($name, 'Modelo') !== false) {
        $targetRelId = $sheet->getAttribute('r:id');
        break;
    }
}
// Fallback: Pega a primeira
if (!$targetRelId)
    $targetRelId = $sheets->item(0)->getAttribute('r:id');

// 2. Descobre o arquivo XML da sheet (ex: sheet1.xml)
$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$relsDoc = new DOMDocument();
$relsDoc->loadXML($relsXml);
$targetFile = null;
foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
    if ($rel->getAttribute('Id') == $targetRelId) {
        $targetFile = $rel->getAttribute('Target');
        break;
    }
}
$targetFile = ltrim($targetFile, '/');
if (strpos($targetFile, 'xl/') !== 0)
    $targetFile = 'xl/' . $targetFile;

// --- SMART SCANNER (Mapeamento de Colunas) ---
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
$ssItems = [];
if ($sharedStringsXml) {
    $ssDoc = new DOMDocument();
    @$ssDoc->loadXML($sharedStringsXml);
    foreach ($ssDoc->getElementsByTagName('si') as $node) {
        $t = $node->getElementsByTagName('t')->item(0);
        $ssItems[] = $t ? trim($t->nodeValue) : "";
    }
}

$sheetXml = $zip->getFromName($targetFile);
$doc = new DOMDocument();
$doc->loadXML($sheetXml);
$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$sheetData = $doc->getElementsByTagNameNS($ns, 'sheetData')->item(0);

// Faz o Scan dos cabeçalhos (primeiras 15 linhas)
$mapping = [];
$headerRow = 1;
$rows = $doc->getElementsByTagName('row');

for ($i = 0; $i < min(15, $rows->length); $i++) {
    $row = $rows->item($i);
    $cells = $row->getElementsByTagName('c');
    $tempMapping = [];
    $found = 0;
    foreach ($cells as $cell) {
        $val = "";
        if ($vNode = $cell->getElementsByTagName('v')->item(0)) {
            $val = $vNode->nodeValue;
            if ($cell->getAttribute('t') == 's' && isset($ssItems[$val]))
                $val = $ssItems[$val];
        }
        $col = preg_replace('/[0-9]/', '', $cell->getAttribute('r'));

        // Match Keywords
        if (stripos($val, 'Product Name') !== false || stripos($val, 'Nome do Produto') !== false) {
            $tempMapping['Nome'] = $col;
            $found++;
        }
        if (stripos($val, 'Description') !== false || stripos($val, 'Descrição') !== false) {
            $tempMapping['Descrição'] = $col;
            $found++;
        }
        if (stripos($val, 'SKU') !== false) {
            $tempMapping['SKU'] = $col;
            $found++;
        }
        if (stripos($val, 'Price') !== false || stripos($val, 'Preço') !== false) {
            $tempMapping['Preço'] = $col;
            $found++;
        }
        if (stripos($val, 'Stock') !== false || stripos($val, 'Estoque') !== false || stripos($val, 'Quantity') !== false) {
            $tempMapping['Estoque'] = $col;
            $found++;
        }
        if (stripos($val, 'Main Image') !== false || stripos($val, 'Imagem Principal') !== false) {
            $tempMapping['Capa'] = $col;
            $found++;
        }
        if (stripos($val, 'Package Weight') !== false || stripos($val, 'Peso') !== false) {
            $tempMapping['Peso'] = $col;
            $found++;
        }
        if (stripos($val, 'Category') !== false || stripos($val, 'Categoria') !== false) {
            $tempMapping['Categoria'] = $col;
            $found++;
        }
        if (stripos($val, 'Brand') !== false || stripos($val, 'Marca') !== false) {
            $tempMapping['Marca'] = $col;
            $found++;
        }
    }

    // Se achou pelo menos 3 colunas, assume que é o cabeçalho
    if ($found >= 3) {
        $mapping = $tempMapping;
        $headerRow = (int) $row->getAttribute('r');
        break;
    }
}

// Começa a preencher DEPOIS do cabeçalho
$startRow = $headerRow + 1;
if ($startRow < 3)
    $startRow = 4; // Um fallback seguro para templates TikTok que geralmente tem header na linha 3

$rowNum = $startRow;

// Helpers
function getAbsoluteUrl($path)
{
    if (!$path)
        return '';
    if (strpos($path, 'http') === 0)
        return $path;
    $baseUrl = rtrim(BASE_URL, '/');
    if (strpos($baseUrl, 'http') !== 0)
        $baseUrl = 'https://www.fightarcade.com.br' . $baseUrl;
    return $baseUrl . '/assets/uploads/' . $path;
}

function cleanStr($str)
{
    if (!$str)
        return '';
    $str = strip_tags($str);
    $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
    return mb_substr($str, 0, 5000);
}

// Preenchimento
foreach ($products as $p) {
    // Dados processados
    $price = number_format($p['price'], 2, '.', '');
    $weight = ($p['weight_kg'] && $p['weight_kg'] > 0) ? $p['weight_kg'] : '0.1';
    $mainImg = getAbsoluteUrl($p['image_path']);
    $desc = cleanStr($p['description']);
    if (empty($desc))
        $desc = $p['name'];

    // Mapeamento de dados para colunas
    $data = [
        'Nome' => cleanStr($p['name']),
        'Descrição' => $desc,
        'SKU' => $p['sku'],
        'Preço' => $price,
        'Estoque' => ($p['stock_qty'] ?? 10),
        'Capa' => $mainImg,
        'Peso' => $weight,
        'Categoria' => $p['cat_name'] ?? 'Games',
        'Marca' => 'Fight Arcade'
    ];

    // Cria a linha (Row)
    $rowElement = $doc->createElementNS($ns, 'row');
    $rowElement->setAttribute('r', $rowNum);

    foreach ($mapping as $key => $colLetter) {
        if (!isset($data[$key]))
            continue;

        $val = $data[$key];
        $cell = $doc->createElementNS($ns, 'c');
        $cell->setAttribute('r', $colLetter . $rowNum);

        // Numérico ou String?
        if (is_numeric($val) && $key !== 'SKU' && $key !== 'Nome') { // SKU pode ser numérico mas queremos tratar como texto as vezes, mas Excel ok
            $cell->setAttribute('t', 'n');
            $cell->appendChild($doc->createElementNS($ns, 'v', $val));
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $is = $doc->createElementNS($ns, 'is');
            $tNode = $doc->createElementNS($ns, 't');
            $tNode->appendChild($doc->createTextNode($val));
            $is->appendChild($tNode);
            $cell->appendChild($is);
        }
        $rowElement->appendChild($cell);

        // Se for Capa, tentar adicionar imagens extras nas colunas seguintes?
        // O TikTok as vezes pede imagens em colunas separadas ou separadas por pipe.
        // Por enquanto vamos focar na principal para garantir robustez.
    }

    $sheetData->appendChild($rowElement);
    $rowNum++;
}

// Salva e fecha
$zip->deleteName($targetFile);
$zip->addFromString($targetFile, $doc->saveXML());
$zip->close();

// Download
$filename = 'tiktok_export_' . date('Y-m-d_H-i') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: max-age=0');

readfile($tempFile);
unlink($tempFile);
exit;
?>