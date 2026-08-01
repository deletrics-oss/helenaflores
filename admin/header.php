<?php
// catalogo/admin/header.php — Helena Flores Admin Header (Com Tema Claro/Escuro & Menu Completo)
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
        :root {
            --bg-main: #0E131F;
            --bg-card: #161C2E;
            --bg-input: #0E131F;
            --border-color: #28324A;
            --text-primary: #E0E6ED;
            --text-secondary: #A0AEC0;
            --accent-pink: #C2185B;
            --accent-pink-hover: #E91E63;
        }

        body.light-theme {
            --bg-main: #F4F6F9;
            --bg-card: #FFFFFF;
            --bg-input: #FFFFFF;
            --border-color: #E2E8F0;
            --text-primary: #1A202C;
            --text-secondary: #4A5568;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: var(--bg-main); color: var(--text-primary); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; min-height: 100vh; 
            display: flex; flex-direction: column; transition: background 0.3s ease, color 0.3s ease;
        }
        
        .admin-topbar {
            background: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 0 25px; height: 65px;
            display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .admin-logo {
            font-size: 1.3rem; font-weight: 800; color: var(--text-primary); text-decoration: none; display: flex; align-items: center; gap: 8px;
        }
        .admin-logo span { color: var(--accent-pink); }

        .admin-nav-links { display: flex; align-items: center; gap: 14px; list-style: none; }
        .admin-nav-links a {
            color: var(--text-secondary); text-decoration: none; font-size: 0.88rem; font-weight: 600; padding: 8px 12px; border-radius: 8px;
            transition: all 0.2s ease; display: flex; align-items: center; gap: 6px;
        }
        .admin-nav-links a:hover, .admin-nav-links a.active {
            color: var(--text-primary); background: rgba(194, 24, 91, 0.1);
        }
        
        .admin-nav-links .highlight-pink {
            background: var(--accent-pink); color: #FFF !important; font-weight: bold; border-radius: 20px; padding: 7px 16px;
        }
        .admin-nav-links .highlight-pink:hover { background: var(--accent-pink-hover); }

        /* Dropdown Styles */
        .admin-dropdown { position: relative; }
        .admin-dropdown-menu {
            position: absolute; top: 100%; left: 0; background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 10px 0; min-width: 230px; display: none; box-shadow: 0 10px 25px rgba(0,0,0,0.3); z-index: 1001;
        }
        .admin-dropdown:hover .admin-dropdown-menu { display: block; }
        .admin-dropdown-menu a {
            padding: 10px 18px; border-radius: 0; color: var(--text-secondary); font-size: 0.85rem; width: 100%; display: block;
        }
        .admin-dropdown-menu a:hover { background: rgba(194, 24, 91, 0.1); color: var(--accent-pink); }

        .theme-toggle-btn {
            background: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-primary);
            padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 6px;
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
            <li><a href="products.php">🌸 Produtos</a></li>
            <li><a href="categories.php">📂 Categorias</a></li>
            <li><a href="purchase_pos.php">🛒 PDV Compra</a></li>
            <li><a href="pos.php">🏧 Frente Loja (PDV)</a></li>
            <li><a href="suppliers.php">🏭 Fornecedores</a></li>
            <li><a href="customers.php">👥 Clientes</a></li>

            <li class="admin-dropdown">
                <a href="#">🛠️ Ferramentas ▾</a>
                <div class="admin-dropdown-menu">
                    <a href="notifications.php">📱 Evolution API & Z-API Whats</a>
                    <a href="manage-admins.php">🔑 Gestão Equipe & Permissões</a>
                    <a href="banners.php">🖼️ Banners Carrossel</a>
                    <a href="stock-entry.php">➕ Entrada Estoque</a>
                    <a href="melhorenvio.php">📦 Melhor Envio</a>
                    <a href="lalamove.php">🏍️ Lalamove Express</a>
                    <a href="reports.php">📈 Relatórios Vendas</a>
                    <a href="settings.php">⚙️ Configurações Loja</a>
                </div>
            </li>
        </ul>

        <div style="display:flex; align-items:center; gap:12px;">
            <button onclick="toggleAdminTheme()" class="theme-toggle-btn" id="themeToggleBtn" title="Alternar Tema Claro / Escuro">
                🌙 Escuro
            </button>
            <a href="../" target="_blank" class="admin-nav-links highlight-pink" style="color:#FFF;">
                🌐 Ver Loja
            </a>
            <a href="logout.php" style="color:var(--text-secondary); text-decoration:none; font-size:0.85rem; font-weight:bold;">
                Sair 🚪
            </a>
        </div>
    </header>

    <script>
        function toggleAdminTheme() {
            document.body.classList.toggle('light-theme');
            const isLight = document.body.classList.contains('light-theme');
            localStorage.setItem('admin_theme', isLight ? 'light' : 'dark');
            document.getElementById('themeToggleBtn').innerText = isLight ? '☀️ Claro' : '🌙 Escuro';
        }

        // Restore saved theme
        if (localStorage.getItem('admin_theme') === 'light') {
            document.body.classList.add('light-theme');
            document.getElementById('themeToggleBtn').innerText = '☀️ Claro';
        }
    </script>