<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/db.php';
isAdmin();

$templateDir = __DIR__ . '/../modeloplanilhasshopeemercadolivre/';
$adminDir = __DIR__ . '/';

// --- HANDLE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['shopee_template'])) {
    $file = $_FILES['shopee_template'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (strtolower($ext) !== 'xlsx') {
        $error = "Apenas arquivos .xlsx são permitidos.";
    } else {
        $newName = 'Shopee-Template-' . date('d-m-His') . '.xlsx';
        $target = $templateDir . $newName;

        if (!is_dir($templateDir))
            mkdir($templateDir, 0777, true);

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $msg = "Template Shopee enviado com sucesso: $newName";
        } else {
            $error = "Erro ao mover o arquivo para a pasta de modelos.";
        }
    }
}

// --- SCANNER LOGIC (Adapted for Shopee) ---
function getShopeeTemplateMapping($path)
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

    // Shopee templates usually have "Modelo" or "Basic" as sheet name
    foreach ($sheets as $sheet) {
        $name = $sheet->getAttribute('name');
        if (stripos($name, 'Modelo') !== false || stripos($name, 'Basic') !== false || stripos($name, 'Sheet1') !== false) {
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

    // Shopee headers are usually on row 3-4, but let's scan first 15 rows
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

            if (stripos($val, 'Nome do Produto') !== false || stripos($val, 'Product Name') !== false) {
                $tempMapping['Nome'] = $col;
                $found++;
            }
            if (stripos($val, 'Descrição') !== false || stripos($val, 'Description') !== false) {
                $tempMapping['Descrição'] = $col;
                $found++;
            }
            if (stripos($val, 'SKU') !== false) {
                $tempMapping['SKU'] = $col;
                $found++;
            }
            if (stripos($val, 'Preço') !== false || stripos($val, 'Price') !== false) {
                $tempMapping['Preço'] = $col;
                $found++;
            }
            if (stripos($val, 'Estoque') !== false || stripos($val, 'Stock') !== false) {
                $tempMapping['Estoque'] = $col;
                $found++;
            }
            if (stripos($val, 'GTIN') !== false || stripos($val, 'EAN') !== false) {
                $tempMapping['EAN'] = $col;
                $found++;
            }
            if (stripos($val, 'Peso') !== false || stripos($val, 'Weight') !== false) {
                $tempMapping['Peso'] = $col;
                $found++;
            }
            if (stripos($val, 'Imagem de capa') !== false || stripos($val, 'Cover Photo') !== false) {
                $tempMapping['Capa'] = $col;
                $found++;
            }
            if (stripos($val, 'NCM') !== false) {
                $tempMapping['NCM'] = $col;
                $found++;
            }
        }
        if ($found >= 4) {
            $mapping = $tempMapping;
            break;
        }
    }

    $zip->close();
    return ['mapping' => $mapping, 'sheet' => $sheetName];
}

// List all templates (ML and Shopee logic)
$allTemplates = [];
$dirs = [$templateDir, $adminDir];
foreach ($dirs as $dir) {
    if (!is_dir($dir))
        continue;
    foreach (glob($dir . '*.xlsx') as $file) {
        $base = basename($file);
        if (stripos($base, 'Shopee') !== false || (stripos($base, 'basic') !== false && stripos($base, 'template') !== false)) {
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
$inspection = $inspectPath ? getShopeeTemplateMapping($inspectPath) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gestor de Templates Shopee | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .template-card {
            background: #1a1a1a;
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
            background: #000;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #ee4d2d;
        }

        .mapping-item small {
            color: #888;
            display: block;
        }

        .mapping-item b {
            color: #ee4d2d;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .status-active {
            background: #27ae60;
            color: white;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:1000px; margin:0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2>🟠 Gestor de Templates Shopee</h2>
                <a href="products.php" class="btn-sm" style="background:#ee4d2d; color:#fff; text-decoration:none;">🚀
                    Ir para Exportação</a>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <!-- Upload Section -->
                <div class="template-card">
                    <h4 style="margin-bottom:1rem;">📤 Subir Novo Template Shopee</h4>
                    <p style="font-size:0.9em; color:#888;">Baixe o modelo oficial na Shopee (Central do Vendedor) e
                        suba
                        aqui.</p>

                    <?php if (isset($msg))
                        echo "<p style='color:#27ae60; font-weight:bold; margin:10px 0;'>$msg</p>"; ?>
                    <?php if (isset($error))
                        echo "<p style='color:#e74c3c; font-weight:bold; margin:10px 0;'>$error</p>"; ?>

                    <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;">
                        <input type="file" name="shopee_template" accept=".xlsx" required style="margin-bottom:1rem;">
                        <button type="submit" class="btn"
                            style="width:100%; background:#ee4d2d; border-color:#ee4d2d;">⬆️ Enviar e Analisar</button>
                    </form>
                </div>

                <!-- Active Template Info -->
                <div class="template-card" style="border-color: #ee4d2d;">
                    <h4 style="color:#ee4d2d; margin-bottom:0.5rem;">🛠️ Template Ativo (Análise)</h4>
                    <?php if ($inspectPath): ?>
                        <p style="font-size:0.85em; margin-bottom:10px;"><b>Arquivo:</b>
                            <?php echo basename($inspectPath); ?>
                        </p>
                        <p style="font-size:0.85em;"><b>Aba Detectada:</b> <span class="status-badge status-active">
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
                                <p style="color:#e74c3c;">Nenhum cabeçalho reconhecido. Verifique se o arquivo é um modelo
                                    oficial da Shopee.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p>Nenhum template disponível para análise.</p>
                    <?php endif; ?>
                </div>
            </div>

            <h3>📂 Histórico de Templates Shopee</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome do Arquivo</th>
                        <th>Data</th>
                        <th>Pasta</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTemplates as $index => $t): ?>
                        <tr>
                            <td style="font-size:0.9em;">
                                <?php echo htmlspecialchars($t['name']); ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', $t['date']); ?>
                            </td>
                            <td><small style="color:#666;">
                                    <?php echo $t['dir']; ?>
                                </small></td>
                            <td>
                                <?php if ($index === 0): ?>
                                    <span class="status-badge status-active">ATIVO ✨</span>
                                <?php else: ?>
                                    <span style="color:#555; font-size:0.8em;">Histórico</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?inspect=<?php echo urlencode($t['path']); ?>" class="btn-sm"
                                    style="background:#3498db; color:#fff; text-decoration:none;">🔍 Analisar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:2rem; background:#111; padding:1.5rem; border-radius:8px; border:1px solid #333;">
                <h4 style="color:#ee4d2d;">💡 Como funciona o "Subidor" Shopee?</h4>
                <p style="font-size:0.9em; line-height:1.6; color:#aaa; margin-top:10px;">
                    A Shopee costuma atualizar o template de "Novo Produto" frequentemente. <br>
                    Com este gestor, você pode subir o modelo mais novo e o sistema irá mapear automaticamente as
                    colunas
                    necessárias. <br>
                    Isso evita erros de "Coluna Inválida" na Central do Vendedor.
                </p>
            </div>
        </div>
    </div>
</body>

</html>