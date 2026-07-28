<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/db.php';
isAdmin();

$templateDir = __DIR__ . '/../modeloplanilhasshopeemercadolivre/';
$adminDir = __DIR__ . '/';

// --- HANDLE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['tiktok_template'])) {
    $file = $_FILES['tiktok_template'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (strtolower($ext) !== 'xlsx') {
        $error = "Apenas arquivos .xlsx são permitidos.";
    } else {
        $newName = 'TikTok-Template-' . date('d-m-His') . '.xlsx';
        $target = $templateDir . $newName;

        if (!is_dir($templateDir))
            mkdir($templateDir, 0777, true);

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $msg = "Template TikTok enviado com sucesso: $newName";
        } else {
            $error = "Erro ao mover o arquivo para a pasta de modelos.";
        }
    }
}

// --- SCANNER LOGIC (Adapted for TikTok) ---
function getTikTokTemplateMapping($path)
{
    if (!file_exists($path))
        return null;
    $zip = new ZipArchive();
    if ($zip->open($path) !== TRUE)
        return null;

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    if (!$workbookXml) {
        $zip->close();
        return null;
    }

    $workbookDoc = new DOMDocument();
    $workbookDoc->loadXML($workbookXml);
    $sheets = $workbookDoc->getElementsByTagName('sheet');
    $targetRelId = null;
    $sheetName = "";

    // TikTok template logic - scan for "Template", "Modelo", or just grab first
    foreach ($sheets as $sheet) {
        $name = $sheet->getAttribute('name');
        if (stripos($name, 'Template') !== false || stripos($name, 'Modelo') !== false) {
            $targetRelId = $sheet->getAttribute('r:id');
            $sheetName = $name;
            break;
        }
    }
    if (!$targetRelId) {
        $targetRelId = $sheets->item(0)->getAttribute('r:id');
        $sheetName = $sheets->item(0)->getAttribute('name');
    }

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
    $mapping = [];
    $rows = $doc->getElementsByTagName('row');

    // Scan first 15 rows for headers
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

            // TikTok Mapping Logic
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
        }
        if ($found >= 3) {
            $mapping = $tempMapping;
            break;
        }
    }
    $zip->close();
    return ['mapping' => $mapping, 'sheet' => $sheetName];
}

// List Templates
$allTemplates = [];
$dirs = [$templateDir];
foreach ($dirs as $dir) {
    if (!is_dir($dir))
        continue;
    foreach (glob($dir . '*.xlsx') as $file) {
        $base = basename($file);
        if (stripos($base, 'TikTok') !== false) {
            $allTemplates[] = [
                'path' => $file,
                'name' => $base,
                'date' => filemtime($file),
                'dir' => basename(dirname($file))
            ];
        }
    }
}
usort($allTemplates, function ($a, $b) {
    return $b['date'] - $a['date'];
});

$inspectPath = $_GET['inspect'] ?? ($allTemplates[0]['path'] ?? null);
$inspection = $inspectPath ? getTikTokTemplateMapping($inspectPath) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gestor de Templates TikTok | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .template-card {
            background: #000;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .mapping-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 1rem;
        }

        .mapping-item {
            background: #1a1a1a;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #00f2ea;
        }

        .mapping-item small {
            color: #888;
            display: block;
        }

        .mapping-item b {
            color: #ffffff;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .status-active {
            background: #00f2ea;
            color: #000;
        }

        .tiktok-logo-color {
            color: #00f2ea;
            text-shadow: 2px 2px 0px #ff0050;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:1000px; margin:0 auto; border: 1px solid #333;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 class="tiktok-logo-color">🎵 Gestor de Templates TikTok</h2>
                <a href="products.php" class="btn-sm"
                    style="background:#000; border:1px solid #333; color:#fff; text-decoration:none;">🚀 Ir para
                    Exportação</a>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <!-- Upload Section -->
                <div class="template-card">
                    <h4 style="margin-bottom:1rem; color:#fff;">📤 Subir Novo Template TikTok</h4>
                    <p style="font-size:0.9em; color:#888;">Baixe o modelo oficial no TikTok Seller Center e suba aqui.
                    </p>

                    <?php if (isset($msg))
                        echo "<p style='color:#00f2ea; font-weight:bold; margin:10px 0;'>$msg</p>"; ?>
                    <?php if (isset($error))
                        echo "<p style='color:#ff0050; font-weight:bold; margin:10px 0;'>$error</p>"; ?>

                    <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;">
                        <input type="file" name="tiktok_template" accept=".xlsx" required
                            style="margin-bottom:1rem; color:#fff;">
                        <button type="submit" class="btn"
                            style="width:100%; background: linear-gradient(45deg, #00f2ea, #ff0050); border:none; color:#fff; font-weight:bold;">⬆️
                            Enviar e Analisar</button>
                    </form>
                </div>

                <!-- Active Template Info -->
                <div class="template-card" style="border-color: #00f2ea;">
                    <h4 style="color:#00f2ea; margin-bottom:0.5rem;">🛠️ Template Ativo (Análise)</h4>
                    <?php if ($inspectPath): ?>
                        <p style="font-size:0.85em; margin-bottom:10px; color:#fff;"><b>Arquivo:</b>
                            <?php echo basename($inspectPath); ?>
                        </p>
                        <p style="font-size:0.85em; color:#fff;"><b>Aba Detectada:</b> <span
                                class="status-badge status-active">
                                <?php echo $inspection['sheet'] ?? 'Não encontrada'; ?>
                            </span></p>

                        <div class="mapping-grid">
                            <?php if ($inspection && !empty($inspection['mapping'])): ?>
                                <?php foreach ($inspection['mapping'] as $key => $col): ?>
                                    <div class="mapping-item">
                                        <small>
                                            <?php echo $key; ?>
                                        </small>
                                        <b>Coluna
                                            <?php echo $col; ?>
                                        </b>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#ff0050;">Nenhum cabeçalho reconhecido. Use um modelo oficial.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#888;">Nenhum template disponível.</p>
                    <?php endif; ?>
                </div>
            </div>

            <h3 style="color:#fff;">📂 Histórico de Templates</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome do Arquivo</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTemplates as $index => $t): ?>
                        <tr>
                            <td style="font-size:0.9em; color:#ccc;">
                                <?php echo htmlspecialchars($t['name']); ?>
                            </td>
                            <td style="color:#888;">
                                <?php echo date('d/m/Y H:i', $t['date']); ?>
                            </td>
                            <td>
                                <?php if ($index === 0): ?>
                                    <span class="status-badge status-active">ATIVO ✨</span>
                                <?php else: ?>
                                    <span style="color:#555; font-size:0.8em;">Histórico</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?inspect=<?php echo urlencode($t['path']); ?>" class="btn-sm"
                                    style="background:#333; color:#fff; text-decoration:none;">🔍 Analisar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>