<?php
// catalogo/fabrica/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Authentication Check
if (!isset($_SESSION['factory_user_id'])) {
    header("Location: login.php");
    exit;
}

$active_page = basename($_SERVER['PHP_SELF']);
function is_active($page, $current) {
    return $page === $current ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fábrica ERP | Painel Operacional</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap');
        :root {
            --bg: #0b0f16;
            --surface: #121824;
            --border: #222e44;
            --primary: #00e676;
            --accent: #f1c40f;
            --text: #e2e8f0;
            --text-muted: #64748b;
            --danger: #ef4444;
            --blue: #3b82f6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }
        a { text-decoration: none; color: inherit; }
        header { background: #080b10; border-bottom: 1px solid var(--border); padding: 1rem 0; position: sticky; top: 0; z-index: 100; }
        .container { width: 92%; max-width: 1300px; margin: 0 auto; }
        .nav-wrapper { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px; }
        .logo span { color: var(--primary); }
        .nav-links { display: flex; align-items: center; gap: 1.5rem; list-style: none; }
        .nav-links a { font-size: 0.95rem; font-weight: 600; color: var(--text); opacity: 0.85; transition: all 0.2s; padding: 6px 12px; border-radius: 6px; }
        .nav-links a:hover, .nav-links a.active { opacity: 1; color: var(--primary); background: rgba(0, 230, 118, 0.08); }
        .btn-logout { color: var(--danger) !important; }
        .btn-logout:hover { background: rgba(239, 68, 68, 0.08) !important; }
        .main-content { flex: 1; padding: 2rem 0; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(0, 230, 118, 0.1); border: 1px solid var(--primary); color: #00ff88; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #ff6b6b; }
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px; border-bottom: 2px solid var(--border); background: #080b10; color: var(--text-muted); font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        tr:hover td { background: rgba(255, 255, 255, 0.01); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 0.9rem; transition: all 0.2s; text-decoration: none; }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; border-radius: 6px; }
        .btn-primary { background: var(--primary); color: #000; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary { background: var(--border); color: var(--text); }
        .btn-secondary:hover { background: #2b3952; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { opacity: 0.9; }
        .form-control { width: 100%; padding: 10px; background: #080b10; border: 1px solid var(--border); border-radius: 8px; color: #fff; outline: none; transition: border-color 0.2s; font-size: 0.9rem; }
        .form-control:focus { border-color: var(--primary); }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted); }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .badge-success { background: rgba(0, 230, 118, 0.15); color: var(--primary); }
        .badge-warning { background: rgba(241, 196, 15, 0.15); color: var(--accent); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: var(--blue); }
    </style>
</head>
<body>
<header>
    <div class="container nav-wrapper">
        <div class="logo">
            <a href="dashboard.php" style="display:flex; align-items:center; gap:8px;"><i class="fas fa-industry" style="color:var(--primary);"></i> Fábrica<span>ERP</span></a>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="<?php echo is_active('dashboard.php', $active_page); ?>"><i class="fas fa-chart-line"></i> Painel</a>
            <a href="pdv.php" class="<?php echo is_active('pdv.php', $active_page); ?>" style="color:var(--primary); font-weight:bold;"><i class="fas fa-shopping-cart"></i> PDV</a>
            <a href="sales.php" class="<?php echo is_active('sales.php', $active_page); ?>"><i class="fas fa-box-open"></i> Vendas</a>
            <a href="production.php" class="<?php echo is_active('production.php', $active_page); ?>"><i class="fas fa-tools"></i> Produção</a>
            <a href="products.php" class="<?php echo is_active('products.php', $active_page); ?>"><i class="fas fa-tags"></i> Produtos</a>
            <a href="clients.php" class="<?php echo is_active('clients.php', $active_page); ?>"><i class="fas fa-users"></i> Clientes</a>
            <a href="suppliers.php" class="<?php echo is_active('suppliers.php', $active_page); ?>"><i class="fas fa-truck-loading"></i> Fornecedores</a>
            <a href="cashbook.php" class="<?php echo is_active('cashbook.php', $active_page); ?>"><i class="fas fa-wallet"></i> Caixa</a>
            <a href="vehicles.php" class="<?php echo is_active('vehicles.php', $active_page); ?>"><i class="fas fa-truck"></i> Veículos</a>
            <a href="defects.php" class="<?php echo is_active('defects.php', $active_page); ?>"><i class="fas fa-exclamation-triangle" style="color:#ff6b6b;"></i> Defeitos</a>
            <a href="chat.php" class="<?php echo is_active('chat.php', $active_page); ?>"><i class="fas fa-comments" style="color:#00e676;"></i> Chat</a>
            <a href="whatsapp.php" class="<?php echo is_active('whatsapp.php', $active_page); ?>"><i class="fab fa-whatsapp" style="color:#00e676;"></i> Conexão Zap</a>
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </nav>
    </div>
</header>
<div class="container main-content">
