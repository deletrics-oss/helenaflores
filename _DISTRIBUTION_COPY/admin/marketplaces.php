<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/user_auth.php';
isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Integrações Marketplaces | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .mk-card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s;
        }

        .mk-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .mk-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .mk-title {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .mk-desc {
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 1.5rem;
        }

        .btn-mk {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <h2 style="border-bottom: 1px solid #333; padding-bottom: 1rem;">🚀 Central de Integrações</h2>

        <div
            style="background:#1a1a1a; padding:1rem; border-radius:8px; margin:1.5rem 0; border-left:4px solid var(--primary);">
            <p style="margin:0; color:#aaa;">
                💡 <strong>Dica:</strong> Vá em <a href="products.php" style="color:var(--primary);">Produtos</a>,
                selecione os itens que deseja exportar, clique em "🚀 Integrações" e escolha a plataforma.
                <br>Se nenhum produto estiver selecionado, exportaremos <strong>todos</strong> os produtos ativos.
            </p>
        </div>

        <div
            style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:2rem; margin-top:2rem;">

            <!-- SHOPEE -->
            <div class="mk-card">
                <span class="mk-icon">🛍️</span>
                <h3 class="mk-title">Shopee</h3>
                <p class="mk-desc">Exporte seus produtos para a planilha de carga em massa da Shopee.</p>
                <form method="POST" action="export_shopee.php" id="formShopee">
                    <input type="hidden" name="selected_ids" id="shopeeIds" value="">
                    <button type="submit" class="btn btn-mk" style="background: #ee4d2d;">⬇️ Baixar Planilha
                        (XLSX)</button>
                </form>
                <small style="display:block; margin-top:10px; color:#666;">Compatível com Template Oficial V.2</small>
            </div>

            <!-- MERCADO LIVRE -->
            <div class="mk-card">
                <span class="mk-icon">🤝</span>
                <h3 class="mk-title">Mercado Livre</h3>
                <p class="mk-desc">Exporte dados para o Mercado Livre (Formato Massivo/Excel).</p>
                <form method="POST" action="export_mercadolivre.php" id="formML">
                    <input type="hidden" name="selected_ids" id="mlIds" value="">
                    <button type="submit" class="btn btn-mk" style="background: #ffe600; color:#000;">⬇️ Baixar Planilha
                        (CSV)</button>
                </form>
                <small style="display:block; margin-top:10px; color:#666;">Campos: Título, Preço, Fotos, EAN</small>
            </div>

            <!-- ANÁLISE / TOOLS -->
            <div class="mk-card">
                <span class="mk-icon">📊</span>
                <h3 class="mk-title">Relatórios & Ajustes</h3>
                <p class="mk-desc">Verifique produtos com cadastro incompleto (Sem EAN, Sem Peso).</p>
                <a href="products.php?filter=incomplete" class="btn btn-mk" style="background: #333;">🔍 Ver Produtos Incompletos</a>
            </div>

            <!-- CATEGORIAS -->
            <div class="mk-card">
                <span class="mk-icon">📂</span>
                <h3 class="mk-title">Mapeamento de Categorias</h3>
                <p class="mk-desc">Configure os IDs das categorias para cada marketplace.</p>
                <a href="marketplace_categories.php" class="btn btn-mk" style="background: #9b59b6;">⚙️ Gerenciar Categorias</a>
                <small style="display:block; margin-top:10px; color:#666;">Evita erros ao criar novas categorias</small>
            </div>

    <script>
        // Retrieve selected IDs from sessionStorage (set by products.php)
        window.addEventListener('DOMContentLoaded', function () {
            const selected = sessionStorage.getItem('selected_product_ids');
            if (selected) {
                const ids = JSON.parse(selected);
                document.getElementById('shopeeIds').value = JSON.stringify(ids);
                document.getElementById('mlIds').value = JSON.stringify(ids);
            }
        });
    </script>
</body>

</html>