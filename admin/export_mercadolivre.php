<?php
/**
 * Exportador Mercado Livre v1 - Standardized XML Implementation
 * Uses the same robust logic as Shopee Export (Template Cloning + XML Injection)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

isAdmin();
set_time_limit(300);

// Disable error display for binary output stability
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Diagnóstico de dependências
if (!class_exists('ZipArchive')) {
    die("<h1>Erro de Sistema</h1><p>A extensão 'ZipArchive' não está habilitada no PHP deste servidor.</p>");
}

// Helper para localizar o template mais recente em múltiplos diretórios
function findMLTemplate($dirs)
{
    $allFiles = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir))
            continue;
        $files = glob($dir . '*.xlsx');
        foreach ($files as $file) {
            if (stripos(basename($file), 'Anunciar') !== false || stripos(basename($file), 'mercado') !== false) {
                $allFiles[$file] = filemtime($file);
            }
        }
    }
    if (empty($allFiles))
        return null;
    arsort($allFiles); // Ordena por data (mais recente primeiro)
    return key($allFiles);
}

// Diretórios onde o template pode estar
$templateDirs = [
    __DIR__ . '/../modeloplanilhasshopeemercadolivre/',
    __DIR__ . '/' // Busca também na pasta admin
];

$templatePath = findMLTemplate($templateDirs);

if (!$templatePath) {
    die("<h1>Erro no Template</h1><p>Nenhum template do Mercado Livre encontrado. Por favor, suba um arquivo Excel com 'Anunciar' no nome para a pasta <b>modeloplanilhasshopeemercadolivre</b> ou <b>admin</b>.</p>");
}

// Busca produtos selecionados
$selectedIds = [];
if (isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
    $decoded = json_decode($_POST['selected_ids'], true);
    if (is_array($decoded))
        $selectedIds = array_map('intval', $decoded);
}

if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $sql = "SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders) AND (p.allow_export = 1 OR p.allow_export IS NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($selectedIds);
} else {
    $sql = "SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1 AND (p.allow_export = 1 OR p.allow_export IS NULL)";
    $stmt = $pdo->query($sql);
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    die('<h1>❌ Nenhum produto encontrado!</h1><p><a href="products.php">Voltar</a></p>');
}

// CLONA o template para arquivo temporário
$tempDir = sys_get_temp_dir();
$tempFile = tempnam($tempDir, 'ml_export_');
if (!@copy($templatePath, $tempFile)) {
    $tempFile = __DIR__ . '/temp_ml_' . time() . '.xlsx';
    if (!@copy($templatePath, $tempFile)) {
        die("<h1>Erro de Permissão</h1><p>Não foi possível criar o arquivo temporário.</p>");
    }
}

// Abre a CÓPIA
$zip = new ZipArchive();
if ($zip->open($tempFile) !== TRUE) {
    @unlink($tempFile);
    die("<h1>Erro no Template</h1><p>Não foi possível abrir o arquivo clonado.</p>");
}

// 1. Identifica a sheet alvo
$workbookXml = $zip->getFromName('xl/workbook.xml');
if (!$workbookXml) {
    $zip->close();
    @unlink($tempFile);
    die("<h1>Erro no Template</h1><p>O arquivo Excel selecionado está corrompido ou não é um XLSX válido.</p>");
}

$workbookDoc = new DOMDocument();
$workbookDoc->loadXML($workbookXml);

$sheets = $workbookDoc->getElementsByTagName('sheet');
$targetRelId = null;

// Tenta achar aba "Outros", depois "Modelo" ou usa a primeira
foreach ($sheets as $sheet) {
    $name = $sheet->getAttribute('name');
    if (stripos($name, 'Outros') !== false) {
        $targetRelId = $sheet->getAttribute('r:id');
        break;
    }
}

if (!$targetRelId) {
    foreach ($sheets as $sheet) {
        $name = $sheet->getAttribute('name');
        if (stripos($name, 'Modelo') !== false || stripos($name, 'Planilha') !== false) {
            $targetRelId = $sheet->getAttribute('r:id');
            break;
        }
    }
}

if (!$targetRelId && $sheets->length > 0)
    $targetRelId = $sheets->item(0)->getAttribute('r:id');

if (!$targetRelId) {
    $zip->close();
    @unlink($tempFile);
    die("<h1>Erro no Template</h1><p>Não foi possível localizar as abas internas do arquivo Excel.</p>");
}

// 2. Resolve o arquivo XML alvo
$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$relsDoc = new DOMDocument();
$relsDoc->loadXML($relsXml);
$relationships = $relsDoc->getElementsByTagName('Relationship');
$targetFile = null;

foreach ($relationships as $rel) {
    if ($rel->getAttribute('Id') == $targetRelId) {
        $targetFile = $rel->getAttribute('Target');
        break;
    }
}

if ($targetFile) {
    $targetFile = ltrim($targetFile, '/');
    if (strpos($targetFile, 'xl/') !== 0)
        $targetFile = 'xl/' . $targetFile;
} else {
    $targetFile = 'xl/worksheets/sheet1.xml';
}

$sheetXml = $zip->getFromName($targetFile);
if (!$sheetXml) {
    $zip->close();
    @unlink($tempFile);
    die("<h1>Erro no Template</h1><p>Não foi possível ler a aba de dados ($targetFile). Verifique o modelo do Mercado Livre.</p>");
}

// --- SMART SCANNER: Decodifica Shared Strings para mapeamento dinâmico ---
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

$zip->close();

// 3. Parse e Inserção
$doc = new DOMDocument();
if (!@$doc->loadXML($sheetXml)) {
    die("<h1>Erro Interno</h1><p>Falha ao processar a estrutura XML da planilha ($targetFile).</p>");
}
$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$sheetData = $doc->getElementsByTagNameNS($ns, 'sheetData')->item(0);

if (!$sheetData) {
    die("<h1>Erro no Template</h1><p>A estrutura de dados (sheetData) não foi encontrada na aba selecionada.</p>");
}

// SCANEIA Cabeçalhos para mapeamento automático
$mapping = [];
$headerRow = null;
$rows = $doc->getElementsByTagName('row');
for ($i = 0; $i < min(15, $rows->length); $i++) {
    $row = $rows->item($i);
    $cells = $row->getElementsByTagName('c');
    $foundKeys = 0;
    $tempMapping = [];
    foreach ($cells as $cell) {
        $val = "";
        if ($vNode = $cell->getElementsByTagName('v')->item(0)) {
            $val = $vNode->nodeValue;
            if ($cell->getAttribute('t') == 's' && isset($ssItems[$val]))
                $val = $ssItems[$val];
        }
        $col = preg_replace('/[0-9]/', '', $cell->getAttribute('r'));

        // Critérios de busca (Keywords do ML)
        if (stripos($val, 'Título') !== false) {
            $tempMapping['Título'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'SKU') !== false || stripos($val, 'Código do produto') !== false) {
            $tempMapping['SKU'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Preço') !== false) {
            $tempMapping['Preço'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Estoque') !== false || stripos($val, 'Quantidade') !== false) {
            $tempMapping['Estoque'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Código universal') !== false || stripos($val, 'EAN') !== false || stripos($val, 'GTIN') !== false) {
            $tempMapping['EAN'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Foto') !== false) {
            $tempMapping['Fotos'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Descrição') !== false) {
            $tempMapping['Descrição'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Marca') !== false) {
            $tempMapping['Marca'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Modelo') !== false) {
            $tempMapping['Modelo'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Condição') !== false) {
            $tempMapping['Condição'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Tipo de anúncio') !== false || stripos($val, 'publicidade') !== false) {
            $tempMapping['TipoAnuncio'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Forma de envio') !== false) {
            $tempMapping['Envio'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Custo de envio') !== false) {
            $tempMapping['Custo'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Garantia') !== false && stripos($val, 'Tipo') !== false) {
            $tempMapping['GarantiaTipo'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Garantia') !== false && stripos($val, 'Tempo') !== false) {
            $tempMapping['GarantiaTempo'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Garantia') !== false && stripos($val, 'Unidade') !== false) {
            $tempMapping['GarantiaUnid'] = $col;
            $foundKeys++;
        }
        if (stripos($val, 'Retirar') !== false) {
            $tempMapping['Retira'] = $col;
            $foundKeys++;
        }
    }
    if ($foundKeys >= 5) { // Se achou pelo menos 5 mapeamentos, essa é a linha de cabeçalho
        $mapping = $tempMapping;
        $headerRow = (int) $row->getAttribute('r');
        break;
    }
}

// Fallback se o scanner falhar (usa mapeamento da última planilha vitoriosa)
if (empty($mapping)) {
    $mapping = [
        'Título' => 'B',
        'Condição' => 'D',
        'EAN' => 'E',
        'Fotos' => 'F',
        'SKU' => 'G',
        'Estoque' => 'H',
        'Preço' => 'I',
        'Descrição' => 'J',
        'TipoAnuncio' => 'K',
        'Envio' => 'M',
        'Custo' => 'N',
        'Retira' => 'O',
        'GarantiaTipo' => 'P',
        'GarantiaTempo' => 'Q',
        'GarantiaUnid' => 'R',
        'Marca' => 'S',
        'Modelo' => 'T'
    ];
    $startRow = 11;
} else {
    $startRow = $headerRow + 1;
}

// Define linha inicial (Mercado Livre padrão 'Outros' costuma começar após os cabeçalhos)
// Baseado no probe anterior, as linhas 1-10 tem cabeçalhos. Vamos começar na 11.
// $startRow = 11; // This is now dynamic
$columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI'];

$rowNum = $startRow;

foreach ($products as $product) {
    // Sanitização e Preparação de Dados
    $nome = cleanText($product['name']);
    $descricao = cleanText(strip_tags($product['description']));
    if (empty(trim($descricao)))
        $descricao = $nome . ' - Produto de alta qualidade Fight Arcade. Envio imediato.';

    $price = (float) str_replace(',', '.', (string) $product['price']);
    if ($price < 9.90)
        $price = 9.90;

    $stock = (int) $product['stock_qty'];
    if ($stock < 5)
        $stock = 10;

    $weight = (float) ($product['weight_kg'] ?? 0.100);
    if ($weight < 0.050)
        $weight = 0.050;

    $ean = preg_replace('/[^0-9]/', '', $product['ean'] ?? '');

    // Função inline para validar EAN
    $isValidEan = function ($code) {
        if (strlen($code) != 13)
            return false;
        $sum = 0;
        for ($i = 0; $i < 12; $i++)
            $sum += (int) $code[$i] * ($i % 2 == 0 ? 1 : 3);
        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit == (int) $code[12];
    };

    if (strlen($ean) != 13 || !$isValidEan($ean))
        $ean = generateEan($product['id']);

    // Fotos
    $allImgs = getGalleryImages($pdo, $product['id']);
    array_unshift($allImgs, getFullUrl($product['image_path']));
    $photoStream = implode(', ', array_filter($allImgs));

    // Mapeamento Dinâmico de Dados vs Colunas
    $productData = [
        'Título' => $nome,
        'Condição' => 'Novo',
        'EAN' => $ean,
        'Fotos' => $photoStream,
        'SKU' => $product['sku'],
        'Estoque' => $stock,
        'Preço' => $price,
        'Descrição' => $descricao,
        'TipoAnuncio' => 'Clássico',
        'Envio' => 'Mercado Envios',
        'Custo' => 'Por conta do comprador',
        'Retira' => 'Não aceito',
        'GarantiaTipo' => 'Garantia do vendedor',
        'GarantiaTempo' => '30',
        'GarantiaUnid' => 'dias',
        'Marca' => 'FightArcade',
        'Modelo' => $product['sku']
    ];

    $rowElement = $doc->createElementNS($ns, 'row');
    $rowElement->setAttribute('r', $rowNum);

    // Injeta apenas nas colunas descobertas pelo scanner
    foreach ($mapping as $key => $colLetter) {
        if (!isset($productData[$key]))
            continue;

        $value = $productData[$key];
        $colRef = $colLetter . $rowNum;

        $cell = $doc->createElementNS($ns, 'c');
        $cell->setAttribute('r', $colRef);

        if ($value !== '' && is_numeric($value)) {
            $cell->setAttribute('t', 'n');
            $v = $doc->createElementNS($ns, 'v', $value);
            $cell->appendChild($v);
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $is = $doc->createElementNS($ns, 'is');
            $t = $doc->createElementNS($ns, 't');
            $t->appendChild($doc->createTextNode((string) $value));
            $is->appendChild($t);
            $cell->appendChild($is);
        }
        $rowElement->appendChild($cell);
    }
    $sheetData->appendChild($rowElement);
    $rowNum++;
}

// Re-Zip
$newZip = new ZipArchive();
if ($newZip->open($tempFile) === TRUE) {
    if ($newZip->locateName($targetFile) !== false)
        $newZip->deleteName($targetFile);
    $newZip->addFromString($targetFile, $doc->saveXML());
    $newZip->close();
}

// Output
$filename = 'mercado_livre_export_' . date('Y-m-d_H-i') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: max-age=0');
readfile($tempFile);
unlink($tempFile);
exit;

// ===== HELPERS =====
function cleanText($str)
{
    if (!$str)
        return '';
    $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
    return mb_substr($str, 0, 5000);
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
    for ($i = 0; $i < 8; $i++)
        $imgs[$i] = isset($gal[$i]) ? getFullUrl($gal[$i]) : '';
    return $imgs;
}
function generateEan($id)
{
    $baseEan = '7890000' . str_pad($id, 5, '0', STR_PAD_LEFT);
    $sum = 0;
    for ($j = 0; $j < 12; $j++)
        $sum += (int) $baseEan[$j] * ($j % 2 == 0 ? 1 : 3);
    $checkDigit = (10 - ($sum % 10)) % 10;
    return $baseEan . $checkDigit;
}