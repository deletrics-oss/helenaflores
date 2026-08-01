<?php
// catalogo/admin/header.php — Helena Flores Admin Header (Design Moderno, Limpo e Organizado)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo | Helena Flores</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0E131F; color: #E0E6ED; font-family: 'Inter', system-ui, -apple-system, sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Modern Admin Top Header */
        .admin-topbar {
            background: #161C2E; border-bottom: 1px solid #28324A; padding: 0 25px; height: 65px;
            display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .admin-logo {
            font-size: 1.3rem; font-weight: 800; color: #FFF; text-decoration: none; display: flex; align-items: center; gap: 8px;
        }
        .admin-logo span { color: #E91E63; }

        .admin-nav-links { display: flex; align-items: center; gap: 18px; list-style: none; }
        .admin-nav-links a {
            color: #A0AEC0; text-decoration: none; font-size: 0.9rem; font-weight: 600; padding: 8px 12px; border-radius: 8px;
            transition: all 0.2s ease; display: flex; align-items: center; gap: 6px;
        }
        .admin-nav-links a:hover, .admin-nav-links a.active {
            color: #FFF; background: #242D45;
        }
        
        .admin-nav-links .highlight-pink {
            background: #C2185B; color: #FFF !important; font-weight: bold; border-radius: 20px; padding: 7px 16px;
        }
        .admin-nav-links .highlight-pink:hover { background: #E91E63; }

        /* Dropdown Styles */
        .admin-dropdown { position: relative; }
        .admin-dropdown-menu {
            position: absolute; top: 100%; left: 0; background: #1B2238; border: 1px solid #2D3748;
            border-radius: 10px; padding: 10px 0; min-width: 220px; display: none; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1001;
        }
        .admin-dropdown:hover .admin-dropdown-menu { display: block; }
        .admin-dropdown-menu a {
            padding: 10px 18px; border-radius: 0; color: #CBD5E0; font-size: 0.85rem; width: 100%; display: block;
        }
        .admin-dropdown-menu a:hover { background: #2D3748; color: #FFF; }

        .admin-user-btn {
            display: flex; align-items: center; gap: 10px; background: #242D45; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; color: #FFF; text-decoration: none;
        }
    </style>
</head>
<body>

    <header class="admin-topbar">
        <a href="dashboard.php" class="admin-logo">
            🌹 Helena <span>Flores Admin</span>
        </a>

        <ul class="admin-nav-links">
            <li><a href="dashboard.php">📊 Painel</a></li>
            <li><a href="orders.php">📦 Pedidos</a></li>
            <li><a href="products.php" class="active">🌸 Produtos</a></li>
            <li><a href="categories.php">📂 Categorias</a></li>
            <li><a href="purchase_pos.php">🛒 PDV Compra</a></li>
            <li><a href="customers.php">👥 Clientes</a></li>

            <li class="admin-dropdown">
                <a href="#">🛠️ Ferramentas ▾</a>
                <div class="admin-dropdown-menu">
                    <a href="banners.php">🖼️ Banners Carrossel</a>
                    <a href="stock-entry.php">➕ Entrada Estoque</a>
                    <a href="melhorenvio.php">📦 Melhor Envio</a>
                    <a href="lalamove.php">🏍️ Lalamove Express</a>
                    <a href="reports.php">📈 Relatórios Vendas</a>
                    <a href="settings.php">⚙️ Configurações Loja</a>
                </div>
            </li>

            <li class="admin-dropdown">
                <a href="#">🛒 Marketplaces ▾</a>
                <div class="admin-dropdown-menu">
                    <a href="export_shopee_csv.php">🟠 Shopee CSV</a>
                    <a href="export_mercadolivre.php">🟡 Mercado Livre</a>
                    <a href="verificar_ean.php">🔍 Verificar EANs</a>
                </div>
            </li>
        </ul>

        <div style="display:flex; align-items:center; gap:12px;">
            <a href="../" target="_blank" class="admin-nav-links highlight-pink" style="color:#FFF;">
                🌐 Ver Loja
            </a>
            <a href="logout.php" style="color:#A0AEC0; text-decoration:none; font-size:0.85rem; font-weight:bold;">
                Sair 🚪
            </a>
        </div>
    </header>