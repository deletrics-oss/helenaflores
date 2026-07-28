<?php
/**
 * Corrige TODOS os EANs no banco de dados
 * Acesse via navegador para executar a correção
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

isAdmin();

// Função para calcular dígito verificador EAN-13
function calcCheckDigit($base12)
{
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $base12[$i] * ($i % 2 == 0 ? 1 : 3);
    }
    return (10 - ($sum % 10)) % 10;
}

// Função para gerar EAN válido baseado no ID
function generateValidEan($id)
{
    $base = '7890000' . str_pad($id, 5, '0', STR_PAD_LEFT);
    return $base . calcCheckDigit($base);
}

// Função para corrigir EAN existente
function fixEan($ean, $id)
{
    $ean = preg_replace('/[^0-9]/', '', $ean);

    // Se vazio ou tamanho errado, gera novo
    if (empty($ean) || strlen($ean) != 13) {
        return generateValidEan($id);
    }

    // Se 13 dígitos, corrige o dígito verificador
    $base = substr($ean, 0, 12);
    $correctDigit = calcCheckDigit($base);
    return $base . $correctDigit;
}

$corrigidos = 0;
$erros = [];

// Só executa se confirmado
if (isset($_GET['executar']) && $_GET['executar'] === 'sim') {
    // Busca todos os produtos
    $stmt = $pdo->query("SELECT id, ean, gtin FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $p) {
        $eanAtual = $p['ean'] ?? $p['gtin'] ?? '';
        $eanCorrigido = fixEan($eanAtual, $p['id']);

        // Atualiza no banco
        try {
            $update = $pdo->prepare("UPDATE products SET ean = ? WHERE id = ?");
            $update->execute([$eanCorrigido, $p['id']]);
            $corrigidos++;
        } catch (Exception $e) {
            $erros[] = "Produto #{$p['id']}: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Corrigir EANs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a2e;
            color: #eee;
        }

        h1 {
            color: #00d9ff;
        }

        .success {
            background: #0d4d3d;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #00ff88;
        }

        .warning {
            background: #4d4d0d;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ffcc00;
        }

        .error {
            background: #4d0d0d;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #ff4444;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #00d9ff;
            color: #000;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            margin: 10px 5px;
        }

        .btn:hover {
            background: #00b8d4;
        }

        .btn-danger {
            background: #ff4444;
        }

        .btn-danger:hover {
            background: #cc3333;
        }

        a {
            color: #00d9ff;
        }
    </style>
</head>

<body>
    <h1>🔧 Corrigir EANs no Banco de Dados</h1>

    <?php if (isset($_GET['executar']) && $_GET['executar'] === 'sim'): ?>

        <?php if ($corrigidos > 0): ?>
            <div class="success">
                <h2>✅ Correção Concluída!</h2>
                <p><strong>
                        <?= $corrigidos ?> produtos
                    </strong> tiveram seus EANs corrigidos.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($erros)): ?>
            <?php foreach ($erros as $err): ?>
                <div class="error">
                    <?= $err ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>🎯 Próximos Passos:</h2>
        <ol>
            <li><a href="verificar_ean.php">Verificar EANs</a> - Confirme que todos estão ✅</li>
            <li><a href="export_shopee_csv.php">Exportar CSV</a> - Exporte os produtos</li>
            <li>Suba na Shopee</li>
        </ol>

        <a href="verificar_ean.php" class="btn">📋 Verificar EANs</a>
        <a href="export_shopee_csv.php" class="btn">🚀 Exportar CSV</a>
        <a href="products.php" class="btn">← Voltar</a>

    <?php else: ?>

        <div class="warning">
            <h2>⚠️ Atenção!</h2>
            <p>Este script vai <strong>corrigir TODOS os EANs</strong> no banco de dados:</p>
            <ul>
                <li>EANs com dígito verificador errado → Corrigidos</li>
                <li>EANs vazios → Gerados automaticamente</li>
                <li>EANs com tamanho errado → Gerados novos</li>
            </ul>
            <p>Formato: <code>789 0000 XXXXX D</code> (onde XXXXX = ID do produto, D = dígito verificador)</p>
        </div>

        <a href="?executar=sim" class="btn btn-danger">🔧 CORRIGIR TODOS OS EANS</a>
        <a href="verificar_ean.php" class="btn">📋 Ver Status Atual</a>
        <a href="products.php" class="btn">← Cancelar</a>

    <?php endif; ?>
</body>

</html>