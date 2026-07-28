<?php
/**
 * Exportador para Treinamento de Bots/IA
 * Formatos: TXT, CSV, PDF (HTML View)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

isAdmin();

// Get IDs if selected
$ids = [];
if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
} elseif (isset($_GET['ids'])) {
    $ids = array_map('intval', explode(',', $_GET['ids']));
}

// Format
$format = $_GET['format'] ?? 'txt'; // txt, csv, pdf

// Fetch Products
$sql = "SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1";
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql .= " AND p.id IN ($placeholders)";
}
$sql .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
if (!empty($ids)) {
    $stmt->execute($ids);
} else {
    $stmt->execute();
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    die("Nenhum produto encontrado.");
}

// Clean Description Function
function cleanDesc($html)
{
    $text = strip_tags($html);
    $text = html_entity_decode($text);
    $text = trim(preg_replace('/\s+/', ' ', $text)); // Remove extra spaces/newlines
    return $text;
}

// DOMAIN DEFINITION
define('EXPORT_DOMAIN', 'https://www.fightarcade.com.br');

function getLink($p)
{
    // Fix: Ensure we don't double the /catalogo if BASE_URL has it
    $path = '/product.php?id=' . $p['id'];
    if (strpos(BASE_URL, 'http') === 0) {
        return BASE_URL . $path;
    }
    // If BASE_URL is relative (e.g. /catalogo), prepend Domain
    return EXPORT_DOMAIN . BASE_URL . $path;
}

function getImageLink($p)
{
    if (!empty($p['image_path'])) {
        $path = '/assets/uploads/' . $p['image_path'];
        if (strpos(BASE_URL, 'http') === 0) {
            // If BASE_URL is already absolute
            return BASE_URL . $path;
        }
        return EXPORT_DOMAIN . BASE_URL . $path;
    }
    return '';
}

// OUTPUT HANDLERS

if ($format === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="produtos_bot_' . date('Y-m-d') . '.txt"');

    foreach ($products as $p) {
        echo "Nome: " . $p['name'] . "\n";
        echo "Link: " . getLink($p) . "\n";
        echo "Imagem: " . getImageLink($p) . "\n";
        echo "Preço: R$ " . number_format($p['price'], 2, ',', '.') . "\n";
        echo "Descrição: " . cleanDesc($p['description']) . "\n";
        echo "--------------------------------------------------\n\n";
    }
    exit;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="produtos_bot_' . date('Y-m-d') . '.csv"');

    // BOM for Excel
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Nome', 'Link', 'Imagem', 'Preço', 'Categoria', 'Descrição'], ';');

    foreach ($products as $p) {
        fputcsv($out, [
            $p['id'],
            $p['name'],
            getLink($p),
            getImageLink($p),
            number_format($p['price'], 2, ',', '.'),
            $p['cat_name'],
            cleanDesc($p['description'])
        ], ';');
    }
    fclose($out);
    exit;
}

if ($format === 'pdf') {
    // HTML View for "Save as PDF"
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="UTF-8">
        <title>Exportação Bot - Fight Arcade</title>
        <style>
            body {
                font-family: monospace;
                font-size: 12px;
                color: #000;
            }

            .item {
                margin-bottom: 20px;
                border-bottom: 1px dashed #ccc;
                padding-bottom: 15px;
                break-inside: avoid;
            }

            .label {
                font-weight: bold;
            }

            .no-print {
                background: #eee;
                padding: 10px;
                text-align: center;
                margin-bottom: 20px;
                text-family: sans-serif;
            }

            a {
                color: #000;
                text-decoration: none;
            }

            @media print {
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>

    <body onload="window.print()">
        <div class="no-print">
            <button onclick="window.print()" style="font-size:16px; padding:5px 15px; cursor:pointer;">💾 SALVAR COMO
                PDF</button>
            <br><small>Escolha "Salvar como PDF" na janela de impressão.</small>
        </div>

        <?php foreach ($products as $p): ?>
            <div class="item">
                <div><span class="label">ID:</span> <?php echo $p['id']; ?></div>
                <div><span class="label">Nome:</span> <?php echo htmlspecialchars($p['name']); ?></div>
                <div><span class="label">Categoria:</span> <?php echo htmlspecialchars($p['cat_name']); ?></div>
                <div><span class="label">Preço:</span> R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></div>
                <div><span class="label">Link:</span> <a href="<?php echo getLink($p); ?>"><?php echo getLink($p); ?></a></div>
                <div><span class="label">Imagem:</span> <a
                        href="<?php echo getImageLink($p); ?>"><?php echo getImageLink($p); ?></a></div>
                <div style="margin-top:5px;">
                    <span class="label">Descrição:</span><br>
                    <?php echo htmlspecialchars(cleanDesc($p['description'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </body>

    </html>
    <?php
    exit;
}
?>