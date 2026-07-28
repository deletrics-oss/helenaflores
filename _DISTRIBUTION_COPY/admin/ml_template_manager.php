<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/db.php';
isAdmin();

$templateDir = __DIR__ . '/../modeloplanilhasshopeemercadolivre/';
$adminDir = __DIR__ . '/';

// --- HANDLE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ml_template'])) {
    $file = $_FILES['ml_template'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (strtolower($ext) !== 'xlsx') {
        $error = "Apenas arquivos .xlsx são permitidos.";
    } else {
        $newName = 'Anunciar-' . date('d-m-His') . '.xlsx';
        $target = $templateDir . $newName;

        if (!is_dir($templateDir))
            mkdir($templateDir, 0777, true);

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $msg = "Template enviado com sucesso: $newName";
        } else {
            $error = "Erro ao mover o arquivo para a pasta de modelos.";
        }
    }
}

// --- SCANNER LOGIC (Shared from export_mercadolivre) ---
function getTemplateMapping($path)
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

    foreach ($sheets as $sheet) {
        if (stripos($sheet->getAttribute('name'), 'Outros') !== false) {
            $targetRelId = $sheet->getAttribute('r:id');
            $sheetName = $sheet->getAttribute('name');
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

            if (stripos($val, 'Título') !== false) {
                $tempMapping['Título'] = $col;
                $found++;
            }
            if (stripos($val, 'SKU') !== false || stripos($val, 'Código do produto') !== false) {
                $tempMapping['SKU'] = $col;
                $found++;
            }
            if (stripos($val, 'Preço') !== false) {
                $tempMapping['Preço'] = $col;
                $found++;
            }
            if (stripos($val, 'Estoque') !== false || stripos($val, 'Quantidade') !== false) {
                $tempMapping['Estoque'] = $col;
                $found++;
            }
            if (stripos($val, 'Código universal') !== false || stripos($val, 'EAN') !== false) {
                $tempMapping['EAN'] = $col;
                $found++;
            }
            if (stripos($val, 'Foto') !== false) {
                $tempMapping['Fotos'] = $col;
                $found++;
            }
            if (stripos($val, 'Marca') !== false) {
                $tempMapping['Marca'] = $col;
                $found++;
            }
            if (stripos($val, 'Modelo') !== false) {
                $tempMapping['Modelo'] = $col;
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

// List all templates
$allTemplates = [];
$dirs = [$templateDir, $adminDir];
foreach ($dirs as $dir) {
    if (!is_dir($dir))
        continue;
    foreach (glob($dir . 'Anunciar*.xlsx') as $file) {
        $allTemplates[] = [
            'path' => $file,
            'name' => basename($file),
            'date' => filemtime($file),
            'dir' => basename(dirname($file))
        ];
    }
}
usort($allTemplates, function ($a, $b) {
    return $b['date'] - $a['date']; });

$inspectPath = $_GET['inspect'] ?? ($allTemplates[0]['path'] ?? null);
$inspection = $inspectPath ? getTemplateMapping($inspectPath) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gestor de Templates ML | Admin</title>
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
            border-left: 3px solid var(--primary);
        }

        .mapping-item small {
            color: #888;
            display: block;
        }

        .mapping-item b {
            color: var(--primary);
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
                <h2>🟡 Gestor de Templates Mercado Livre</h2>
                <a href="products.php" class="btn-sm"
                    style="background:var(--primary); color:#000; text-decoration:none;">🚀 Ir para Exportação</a>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <!-- Upload Section -->
                <div class="template-card">
                    <h4 style="margin-bottom:1rem;">📤 Subir Novo Template</h4>
                    <p style="font-size:0.9em; color:#888;">Baixe o modelo oficial no Mercado Livre e suba aqui. O
                        sistema irá automatizar o preenchimento.</p>

                    <?php if (isset($msg))
                        echo "<p style='color:#27ae60; font-weight:bold; margin:10px 0;'>$msg</p>"; ?>
                    <?php if (isset($error))
                        echo "<p style='color:#e74c3c; font-weight:bold; margin:10px 0;'>$error</p>"; ?>

                    <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;">
                        <input type="file" name="ml_template" accept=".xlsx" required style="margin-bottom:1rem;">
                        <button type="submit" class="btn" style="width:100%;">⬆️ Enviar e Analisar</button>
                    </form>
                </div>

                <!-- Active Template Info -->
                <div class="template-card" style="border-color: var(--primary-glow);">
                    <h4 style="color:var(--primary); margin-bottom:0.5rem;">🛠️ Template Ativo (Análise)</h4>
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
                                    oficial do Mercado Livre.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p>Nenhum template disponível para análise.</p>
                    <?php endif; ?>
                </div>
            </div>

            <h3>📂 Histórico de Templates</h3>
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
                <h4 style="color:var(--primary);">💡 Como funciona o "Subidor"?</h4>
                <p style="font-size:0.9em; line-height:1.6; color:#aaa; margin-top:10px;">
                    O Mercado Livre gera planilhas com colunas em ordens diferentes dependendo da categoria. <br>
                    Nosso sistema utiliza um <b>Smart Scanner</b>: ao subir o modelo, nós lemos os cabeçalhos internos
                    do Excel. <br>
                    Isso garante que, mesmo que o Mercado Livre mude a posição das colunas "Preço" ou "SKU", nós sempre
                    injetaremos o dado no lugar correto.
                </p>
            </div>
        </div>
    </div>
</body>

</html>