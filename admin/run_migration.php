<?php
/**
 * Execute Migration - Adiciona campo shopee_category_id
 * Acesse via: https://seusite.com/admin/run_migration.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

isAdmin(); // Proteção: só admin pode executar

$messages = [];
$errors = [];

// Verifica se já foi executado
try {
    $check = $pdo->query("SHOW COLUMNS FROM categories LIKE 'shopee_category_id'");
    $exists = $check->rowCount() > 0;

    if ($exists) {
        $messages[] = "⚠️ Campo 'shopee_category_id' já existe na tabela categories.";
    } else {
        // 1. Adiciona coluna shopee_category_id
        $pdo->exec("ALTER TABLE categories ADD COLUMN shopee_category_id VARCHAR(50) DEFAULT NULL AFTER name");
        $messages[] = "✅ Campo 'shopee_category_id' adicionado com sucesso!";

        // 2. Define categoria padrão para todas existentes
        $pdo->exec("UPDATE categories SET shopee_category_id = '121101' WHERE shopee_category_id IS NULL");
        $messages[] = "✅ Categoria padrão '121101' aplicada a todas as categorias.";
    }

    // Mostra categorias atuais
    $categories = $pdo->query("SELECT id, name, shopee_category_id FROM categories ORDER BY id")->fetchAll();

} catch (PDOException $e) {
    $errors[] = "❌ Erro: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - Shopee Category Mapping</title>
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
            background: #0d4d4d;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #00ff88;
        }

        .warning {
            background: #4d4d0d;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #ffcc00;
        }

        .error {
            background: #4d0d0d;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #ff4444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 12px;
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

        a {
            color: #00d9ff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #00d9ff;
            color: #000;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }

        .btn:hover {
            background: #00b8d4;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <h1>🔧 Migration: Shopee Category Mapping</h1>

    <?php foreach ($messages as $msg): ?>
        <div class="<?= strpos($msg, '✅') !== false ? 'success' : 'warning' ?>">
            <?= $msg ?>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errors as $err): ?>
        <div class="error">
            <?= $err ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($categories)): ?>
        <h2>📋 Categorias Atuais</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Shopee Category ID</th>
            </tr>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <?= $cat['id'] ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($cat['name']) ?>
                    </td>
                    <td>
                        <?= $cat['shopee_category_id'] ?? '<em>não definido</em>' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>🎯 Próximos Passos</h2>
    <p>Para alterar a categoria Shopee de uma categoria específica, edite manualmente no phpMyAdmin ou no painel de
        categorias.</p>

    <h3>IDs de Categorias Shopee Comuns:</h3>
    <ul>
        <li><strong>121101</strong> - Eletrônicos > Acessórios</li>
        <li><strong>120039</strong> - Eletrônicos > Games</li>
        <li><strong>120038</strong> - Eletrônicos > Computadores</li>
        <li><strong>125001</strong> - Hobbies > Colecionáveis</li>
    </ul>

    <a href="products.php" class="btn">← Voltar para Produtos</a>
    <a href="export_shopee_all.php" class="btn">🚀 Exportar Shopee</a>
</body>

</html>