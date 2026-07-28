<?php
/**
 * Exportador Shopee v4 - XML Robust Implementation
 * Preserves template structure by appending to the correct sheet XML
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

isAdmin();
set_time_limit(300);

// Pasta de templates
$templateDir = __DIR__ . '/../modeloplanilhasshopeemercadolivre/';

// Helper para localizar o template mais recente em múltiplos diretórios
function findShopeeTemplate($dirs)
{
    $allFiles = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir))
            continue;
        $files = glob($dir . '*.xlsx');
        foreach ($files as $file) {
            $base = basename($file);
            if (stripos($base, 'Shopee') !== false || (stripos($base, 'basic') !== false && stripos($base, 'template') !== false)) {
                $allFiles[$file] = filemtime($file);
            }
        }
    }
    if (empty($allFiles))
        return null;
    arsort($allFiles);
    return key($allFiles);
}

$templateDirs = [$templateDir, __DIR__ . '/'];
$templatePath = findShopeeTemplate($templateDirs);

if (!$templatePath) {
    die('<h1>Erro no Template</h1><p>Nenhum template Shopee encontrado.</p>');
}

// Busca produtos
$selectedIds = [];
if (isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
    $decoded = json_decode($_POST['selected_ids'], true);
    if (is_array($decoded))
        $selectedIds = array_map('intval', $decoded);
}

if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1 AND p.id IN ($placeholders) AND (p.allow_export = 1 OR p.allow_export IS NULL)");
    $stmt->execute($selectedIds);
} else {
    $stmt = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1 AND (p.allow_export = 1 OR p.allow_export IS NULL)");
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    die('<h1>❌ Nenhum produto encontrado!</h1><p><a href="products.php">Voltar</a></p>');
}

// CLONA o template
$tempFile = tempnam(sys_get_temp_dir(), 'shopee_export_');
copy($templatePath, $tempFile);

// Abre a CÓPIA
$zip = new ZipArchive();
if ($zip->open($tempFile) !== TRUE) {
    die('Erro ao abrir template');
}

// 1. Identifica a sheet
$workbookXml = $zip->getFromName('xl/workbook.xml');
$workbookDoc = new DOMDocument();
$workbookDoc->loadXML($workbookXml);
$sheets = $workbookDoc->getElementsByTagName('sheet');
$targetRelId = null;

foreach ($sheets as $sheet) {
    if (stripos($sheet->getAttribute('name'), 'Modelo') !== false || stripos($sheet->getAttribute('name'), 'Basic') !== false) {
        $targetRelId = $sheet->getAttribute('r:id');
        break;
    }
}
if (!$targetRelId)
    $targetRelId = $sheets->item(0)->getAttribute('r:id');

// 2. Resolve
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

// --- SMART SCANNER ---
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
$zip->close();

$doc = new DOMDocument();
$doc->loadXML($sheetXml);
$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$sheetData = $doc->getElementsByTagNameNS($ns, 'sheetData')->item(0);

// SCANEIA Cabeçalhos
$mapping = [];
$headerRow = 3; // Fallback
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

        if (stripos($val, 'Nome') !== false) {
            $tempMapping['Nome'] = $col;
            $found++;
        }
        if (stripos($val, 'Descrição') !== false) {
            $tempMapping['Descrição'] = $col;
            $found++;
        }
        if (stripos($val, 'SKU') !== false) {
            $tempMapping['SKU'] = $col;
            $found++;
        }
        if (stripos($val, 'Preço') !== false) {
            $tempMapping['Preço'] = $col;
            $found++;
        }
        if (stripos($val, 'Estoque') !== false) {
            $tempMapping['Estoque'] = $col;
            $found++;
        }
        if (stripos($val, 'GTIN') !== false || stripos($val, 'EAN') !== false) {
            $tempMapping['EAN'] = $col;
            $found++;
        }
        if (stripos($val, 'Peso') !== false) {
            $tempMapping['Peso'] = $col;
            $found++;
        }
        if (stripos($val, 'Capa') !== false || stripos($val, 'Imagem 1') !== false) {
            $tempMapping['Capa'] = $col;
            $found++;
        }
        if (stripos($val, 'NCM') !== false) {
            $tempMapping['NCM'] = $col;
            $found++;
        }
        if (stripos($val, 'Comprimento') !== false) {
            $tempMapping['L'] = $col;
            $found++;
        }
        if (stripos($val, 'Largura') !== false) {
            $tempMapping['W'] = $col;
            $found++;
        }
        if (stripos($val, 'Altura') !== false) {
            $tempMapping['H'] = $col;
            $found++;
        }
        if (stripos($val, 'Postagem') !== false) {
            $tempMapping['Postagem'] = $col;
            $found++;
        }
    }
    if ($found >= 5) {
        $mapping = $tempMapping;
        $headerRow = (int) $row->getAttribute('r');
        break;
    }
}

$startRow = ($headerRow >= 3) ? $headerRow + 1 : 7;
if ($startRow < 7)
    $startRow = 7; // Shopee usually starts at 7

$rowNum = $startRow;

foreach ($products as $product) {
    // 1. Preço: Mínimo R$ 9.90
    $priceRaw = str_replace(',', '.', (string) $product['price']);
    $price = (float) $priceRaw;
    if ($price < 9.90)
        $price = 9.90;

    // 2. Estoque: Mínimo 10
    $stock = (int) $product['stock_qty'];
    if ($stock < 10)
        $stock = 10;

    // 3. Peso: Mínimo 0.050kg
    $weight = (float) ($product['weight_kg'] ?? 0.050);
    if ($weight < 0.050)
        $weight = 0.050;

    // 4. EAN
    $ean = preg_replace('/[^0-9]/', '', $product['ean'] ?? '');
    $isValidEan = function ($code) {
        if (strlen($code) != 13)
            return false;
        $sum = 0;
        for ($i = 0; $i < 12; $i++)
            $sum += (int) $code[$i] * ($i % 2 == 0 ? 1 : 3);
        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit == (int) $code[12];
    };
    if (strlen($ean) != 13 || !$isValidEan($ean)) {
        $baseEan = '7890000' . str_pad($product['id'], 5, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($j = 0; $j < 12; $j++)
            $sum += (int) $baseEan[$j] * ($j % 2 == 0 ? 1 : 3);
        $checkDigit = (10 - ($sum % 10)) % 10;
        $ean = $baseEan . $checkDigit;
    }

    $ncm = preg_replace('/[^0-9]/', '', $product['ncm'] ?? '85365000');
    $coverImg = getFullUrl($product['image_path']);
    $imgs = getGalleryImages($pdo, $product['id']);

    $nome = cleanText($product['name']);
    $descricao = cleanText(strip_tags($product['description']));
    if (empty(trim($descricao)))
        $descricao = $nome . ' - Produto Fight Arcade.';

    // Medidas
    $L = (int) (($product['length_cm'] ?? 0) > 0 ? $product['length_cm'] : 20);
    $W = (int) (($product['width_cm'] ?? 0) > 0 ? $product['width_cm'] : 15);
    $H = (int) (($product['height_cm'] ?? 0) > 0 ? $product['height_cm'] : 10);

    $productData = [
        'Nome' => $nome,
        'Descrição' => $descricao,
        'SKU' => $product['sku'],
        'Preço' => $price,
        'Estoque' => $stock,
        'EAN' => $ean,
        'Capa' => $coverImg,
        'Peso' => $weight,
        'L' => $L,
        'W' => $W,
        'H' => $H,
        'Postagem' => 2,
        'NCM' => $ncm
    ];

    $rowElement = $doc->createElementNS($ns, 'row');
    $rowElement->setAttribute('r', $rowNum);

    foreach ($mapping as $key => $colLetter) {
        if (!isset($productData[$key]))
            continue;
        $value = $productData[$key];
        $cell = $doc->createElementNS($ns, 'c');
        $cell->setAttribute('r', $colLetter . $rowNum);

        if ($value !== '' && is_numeric($value)) {
            $cell->setAttribute('t', 'n');
            $cell->appendChild($doc->createElementNS($ns, 'v', $value));
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $is = $doc->createElementNS($ns, 'is');
            $tNode = $doc->createElementNS($ns, 't');
            $tNode->appendChild($doc->createTextNode((string) $value));
            $is->appendChild($tNode);
            $cell->appendChild($is);
        }
        $rowElement->appendChild($cell);

        // Se for "Capa", injetar as outras 8 fotos nas colunas seguintes (S-Z padrão) se não houver mapeamento explícito
        if ($key == 'Capa') {
            $colIdx = ord($colLetter) - ord('A');
            for ($imgIdx = 0; $imgIdx < 8; $imgIdx++) {
                if (isset($imgs[$imgIdx])) {
                    $nextCol = chr(ord('A') + $colIdx + 1 + $imgIdx);
                    // Lógica para colunas AA, AB...
                    if ($colIdx + 1 + $imgIdx >= 26) {
                        $nextCol = 'A' . chr(ord('A') + ($colIdx + 1 + $imgIdx) % 26);
                    }

                    $imgCell = $doc->createElementNS($ns, 'c');
                    $imgCell->setAttribute('r', $nextCol . $rowNum);
                    $imgCell->setAttribute('t', 'inlineStr');
                    $imgIs = $doc->createElementNS($ns, 'is');
                    $imgT = $doc->createElementNS($ns, 't');
                    $imgT->appendChild($doc->createTextNode($imgs[$imgIdx]));
                    $imgIs->appendChild($imgT);
                    $imgCell->appendChild($imgIs);
                    $rowElement->appendChild($imgCell);
                }
            }
        }
    }
    $sheetData->appendChild($rowElement);
    $rowNum++;
}

// 5. Salva XML de volta no ZIP (No arquivo correto!)
$newZip = new ZipArchive();
if ($newZip->open($tempFile) === TRUE) {
    // Remove a versão antiga do XML (evita duplicar entrada no ZIP)
    $newZip->deleteName($targetFile);

    // Regrava a sheet correta
    $newZip->addFromString($targetFile, $doc->saveXML());
    $newZip->close();
} else {
    die("Erro ao reabrir ZIP para salvar.");
}

// Download
$filename = 'shopee_export_' . date('Y-m-d_H-i') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: max-age=0');

readfile($tempFile);
unlink($tempFile);
exit;

// ===== FUNÇÕES =====

function findLatestTemplate($dir, $prefix)
{
    if (!is_dir($dir))
        return null;
    $files = glob($dir . '*.xlsx');
    if (empty($files))
        return null;
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach ($files as $file) {
        if (stripos(basename($file), $prefix) !== false)
            return $file;
    }
    return $files[0];
}

function showTemplateError()
{
    echo '<h1>Erro no Template</h1><p>Verifique a pasta modeloplanilhasshopeemercadolivre</p>';
}

function getFullUrl($path)
{
    if (!$path)
        return '';
    if (strpos($path, 'http') === 0)
        return $path;
    return 'https://www.fightarcade.com.br/catalogo/assets/uploads/' . $path;
}

function getGalleryImages($pdo, $productId)
{
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? LIMIT 8");
    $stmt->execute([$productId]);
    $gal = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $imgs = [];
    for ($i = 0; $i < 8; $i++) {
        $imgs[$i] = isset($gal[$i]) ? getFullUrl($gal[$i]) : '';
    }
    return $imgs;
}

function cleanText($str)
{
    if (!$str)
        return '';
    $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
    return mb_substr($str, 0, 5000);
}
?>