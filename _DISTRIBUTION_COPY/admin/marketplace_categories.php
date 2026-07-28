<?php
/**
 * Gerenciador de Mapeamento de Categorias para Marketplaces
 * 
 * Permite mapear categorias internas para IDs do Mercado Livre e Shopee
 */
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/user_auth.php';
isAdmin();

$msg = '';

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $categoryId = (int) $_POST['category_id'];
        $mlId = trim($_POST['ml_id'] ?? '');
        $mlName = trim($_POST['ml_name'] ?? '');
        $shopeeId = trim($_POST['shopee_id'] ?? '');
        $shopeeName = trim($_POST['shopee_name'] ?? '');

        // Salva ML
        if ($mlId) {
            $stmt = $pdo->prepare("INSERT INTO marketplace_categories (category_id, marketplace, marketplace_category_id, marketplace_category_name) 
                                   VALUES (?, 'mercadolivre', ?, ?) 
                                   ON DUPLICATE KEY UPDATE marketplace_category_id = VALUES(marketplace_category_id), 
                                                           marketplace_category_name = VALUES(marketplace_category_name)");
            $stmt->execute([$categoryId, $mlId, $mlName]);
        }

        // Salva Shopee
        if ($shopeeId) {
            $stmt = $pdo->prepare("INSERT INTO marketplace_categories (category_id, marketplace, marketplace_category_id, marketplace_category_name) 
                                   VALUES (?, 'shopee', ?, ?) 
                                   ON DUPLICATE KEY UPDATE marketplace_category_id = VALUES(marketplace_category_id), 
                                                           marketplace_category_name = VALUES(marketplace_category_name)");
            $stmt->execute([$categoryId, $shopeeId, $shopeeName]);
        }

        $msg = '✅ Mapeamento salvo com sucesso!';
    }
}

// Busca categorias com mapeamentos
$categories = $pdo->query("
    SELECT c.id, c.name,
           ml.marketplace_category_id as ml_id, ml.marketplace_category_name as ml_name,
           sh.marketplace_category_id as shopee_id, sh.marketplace_category_name as shopee_name
    FROM categories c
    LEFT JOIN marketplace_categories ml ON c.id = ml.category_id AND ml.marketplace = 'mercadolivre'
    LEFT JOIN marketplace_categories sh ON c.id = sh.category_id AND sh.marketplace = 'shopee'
    ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Mapeamento de Categorias | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .mapping-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .mapping-table th,
        .mapping-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        .mapping-table th {
            background: #1a1a1a;
        }

        .mapping-table input {
            width: 100%;
            padding: 8px;
            background: #222;
            border: 1px solid #444;
            color: #fff;
            border-radius: 4px;
        }

        .btn-save {
            background: #27ae60;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .msg {
            background: #27ae60;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .help-box {
            background: #2a2a2a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #f39c12;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <h2>📂 Mapeamento de Categorias para Marketplaces</h2>

        <?php if ($msg): ?>
            <div class="msg">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="help-box">
            <h4>💡 Como encontrar os IDs:</h4>
            <p><b>Mercado Livre:</b> Na planilha de anúncios massivos, a categoria aparece como código (ex: MLB1039).
            </p>
            <p><b>Shopee:</b> No template Shopee, veja a aba de categorias ou use o ID numérico da Central do Vendedor.
            </p>
            <p>⚠️ <b>Importante:</b> Ao criar uma nova categoria no sistema, volte aqui e adicione o mapeamento!</p>
        </div>

        <table class="mapping-table">
            <thead>
                <tr>
                    <th>Categoria Interna</th>
                    <th>🤝 Mercado Livre (ID)</th>
                    <th>🤝 ML Nome (Ref.)</th>
                    <th>🛍️ Shopee (ID)</th>
                    <th>🛍️ Shopee Nome (Ref.)</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <tr>
                            <td><strong>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </strong></td>
                            <td><input type="text" name="ml_id" value="<?= htmlspecialchars($cat['ml_id'] ?? '') ?>"
                                    placeholder="Ex: MLB1039"></td>
                            <td><input type="text" name="ml_name" value="<?= htmlspecialchars($cat['ml_name'] ?? '') ?>"
                                    placeholder="Ex: Eletrônicos > Games"></td>
                            <td><input type="text" name="shopee_id" value="<?= htmlspecialchars($cat['shopee_id'] ?? '') ?>"
                                    placeholder="Ex: 100636"></td>
                            <td><input type="text" name="shopee_name"
                                    value="<?= htmlspecialchars($cat['shopee_name'] ?? '') ?>"
                                    placeholder="Ex: Jogos > Acessórios"></td>
                            <td><button type="submit" class="btn-save">💾 Salvar</button></td>
                        </tr>
                    </form>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 2rem;">
            <a href="marketplaces.php" class="btn">← Voltar para Integrações</a>
        </div>
    </div>
</body>

</html>