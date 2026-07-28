<?php
/**
 * Verificador de EANs - Mostra quais estão válidos/inválidos
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

isAdmin();

// Função para validar EAN-13
function isValidEan($code)
{
    $code = preg_replace('/[^0-9]/', '', $code);
    if (strlen($code) != 13)
        return false;

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $code[$i] * ($i % 2 == 0 ? 1 : 3);
    }
    $checkDigit = (10 - ($sum % 10)) % 10;
    return $checkDigit == (int) $code[12];
}

// Função para calcular dígito verificador correto
function calcCheckDigit($code)
{
    $code = preg_replace('/[^0-9]/', '', $code);
    if (strlen($code) < 12)
        return null;
    $base = substr($code, 0, 12);

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $base[$i] * ($i % 2 == 0 ? 1 : 3);
    }
    return (10 - ($sum % 10)) % 10;
}

// Busca todos os produtos
$stmt = $pdo->query("SELECT id, name, sku, ean, gtin FROM products WHERE active = 1 ORDER BY id");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$validos = 0;
$invalidos = 0;
$vazios = 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Verificador de EANs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #1a1a2e;
            color: #eee;
        }

        h1 {
            color: #00d9ff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        th {
            background: #16213e;
            color: #00d9ff;
        }

        tr:hover {
            background: #16213e;
        }

        .valido {
            color: #00ff88;
        }

        .invalido {
            color: #ff4444;
        }

        .vazio {
            color: #888;
        }

        .fix {
            background: #2a2a4a;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
        }

        .summary {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }

        .summary div {
            padding: 15px 25px;
            border-radius: 8px;
        }

        .summary .ok {
            background: #0d4d3d;
            border-left: 4px solid #00ff88;
        }

        .summary .err {
            background: #4d0d0d;
            border-left: 4px solid #ff4444;
        }

        .summary .empty {
            background: #3d3d0d;
            border-left: 4px solid #ffcc00;
        }

        a {
            color: #00d9ff;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #00d9ff;
            color: #000;
            border-radius: 5px;
            text-decoration: none;
            margin: 10px 5px;
        }
    </style>
</head>

<body>
    <h1>🔍 Verificador de EANs</h1>

    <table>
        <tr>
            <th>ID</th>
            <th>Produto</th>
            <th>SKU</th>
            <th>EAN Atual</th>
            <th>Status</th>
            <th>EAN Correto</th>
        </tr>
        <?php foreach ($products as $p):
            $ean = preg_replace('/[^0-9]/', '', $p['ean'] ?? $p['gtin'] ?? '');

            if (empty($ean)) {
                $status = 'vazio';
                $statusText = '⚠️ VAZIO';
                $fix = 'Gerar: 7890000' . str_pad($p['id'], 5, '0', STR_PAD_LEFT);
                $vazios++;
            } elseif (strlen($ean) != 13) {
                $status = 'invalido';
                $statusText = '❌ TAMANHO ERRADO (' . strlen($ean) . ' dígitos)';
                $fix = 'Precisa ter 13 dígitos';
                $invalidos++;
            } elseif (!isValidEan($ean)) {
                $status = 'invalido';
                $correctDigit = calcCheckDigit($ean);
                $correctEan = substr($ean, 0, 12) . $correctDigit;
                $statusText = '❌ DÍGITO VERIFICADOR ERRADO';
                $fix = $correctEan;
                $invalidos++;
            } else {
                $status = 'valido';
                $statusText = '✅ VÁLIDO';
                $fix = '-';
                $validos++;
            }
            ?>
            <tr>
                <td>
                    <?= $p['id'] ?>
                </td>
                <td>
                    <?= htmlspecialchars(mb_substr($p['name'], 0, 40)) ?>
                </td>
                <td>
                    <?= htmlspecialchars($p['sku']) ?>
                </td>
                <td><code><?= $ean ?: '-' ?></code></td>
                <td class="<?= $status ?>">
                    <?= $statusText ?>
                </td>
                <td class="fix">
                    <?= $fix ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="summary">
        <div class="ok">✅ Válidos: <strong>
                <?= $validos ?>
            </strong></div>
        <div class="err">❌ Inválidos: <strong>
                <?= $invalidos ?>
            </strong></div>
        <div class="empty">⚠️ Vazios: <strong>
                <?= $vazios ?>
            </strong></div>
    </div>

    <h2>📋 O que fazer?</h2>
    <ul>
        <li><strong>EANs inválidos:</strong> O código atual JÁ CORRIGE automaticamente no export</li>
        <li><strong>EANs vazios:</strong> O código atual JÁ GERA automaticamente no export</li>
        <li><strong>Você NÃO precisa corrigir manualmente</strong> - o export faz isso por você!</li>
    </ul>

    <a href="products.php" class="btn">← Voltar para Produtos</a>
    <a href="export_shopee_csv.php" class="btn">🚀 Exportar CSV</a>
    <a href="export_shopee_all.php" class="btn">🚀 Exportar XLSX</a>
</body>

</html>