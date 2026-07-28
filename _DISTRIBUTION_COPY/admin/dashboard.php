<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// --- 1. FINANCIAL METRICS ---
$fin = [
    'revenue_total' => $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status IN ('paid', 'shipped')")->fetchColumn() ?: 0,
    'revenue_month' => $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status IN ('paid', 'shipped') AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetchColumn() ?: 0,
    'pending_debt' => $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'pending'")->fetchColumn() ?: 0,
    'orders_count' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
];

// --- 2. INVENTORY & PRODUCTS ---
$prodParams = [
    'total' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM products WHERE active = 0")->fetchColumn(),
    'total_stock' => $pdo->query("SELECT SUM(stock_qty) FROM products")->fetchColumn() ?: 0,
    // Est. Value (Products * Price)
    'stock_value' => $pdo->query("SELECT SUM(price * stock_qty) FROM products")->fetchColumn() ?: 0,
    // Variations Stock Value (approx)
    'var_stock_value' => $pdo->query("SELECT SUM(v.price * v.stock_qty) FROM product_variations v")->fetchColumn() ?: 0,
];
$total_inventory_value = $prodParams['stock_value'] + $prodParams['var_stock_value'];

// --- 3. SEO & DATA QUALITY ---
$seo = [
    'missing_img' => $pdo->query("SELECT COUNT(*) FROM products WHERE image_path IS NULL OR image_path = ''")->fetchColumn(),
    'missing_desc' => $pdo->query("SELECT COUNT(*) FROM products WHERE description IS NULL OR description = ''")->fetchColumn(),
    // Assuming you have seo_description column from update_db_full.php, if not this might fail or return 0 if handled. 
    // To be safe we wrap in try/catch or just use description
    'poor_seo' => 0
];
try {
    $seo['poor_seo'] = $pdo->query("SELECT COUNT(*) FROM products WHERE seo_description IS NULL OR LENGTH(seo_description) < 50")->fetchColumn();
} catch (Exception $e) { /* ignore if col doesnt exist */
}


// --- 4. CUSTOMERS & LEADS ---
$crm = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin' AND (is_lead = 0 OR is_lead IS NULL)")->fetchColumn(),
    // New users last 30 days
    'new_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND (is_lead = 0 OR is_lead IS NULL)")->fetchColumn(),
    // Real Leads from Gatekeeper
    'leads_game' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead = 1")->fetchColumn(),
    // Newsletter Leads
    'leads_news' => 0
];
try {
    $crm['leads_news'] = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
} catch (Exception $e) {
}


// --- 5. RECENT ACTIVITY ---
$recent_orders = $pdo->query("SELECT o.*, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5")->fetchAll();
$top_debtors = $pdo->query("SELECT o.*, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = 'pending' ORDER BY o.total_amount DESC LIMIT 5")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Premium | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --card-bg: #1a1e26;
            --success-glow: rgba(46, 204, 113, 0.15);
            --danger-glow: rgba(231, 76, 60, 0.15);
            --warn-glow: rgba(241, 196, 15, 0.15);
        }

        body {
            background: #0f131a;
        }

        .dash-grid-main {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media(max-width: 1100px) {
            .dash-grid-main {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 600px) {
            .dash-grid-main {
                grid-template-columns: 1fr;
            }
        }

        .dash-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #2c3e50;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dash-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            border-color: var(--primary);
        }

        .d-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 2rem;
            opacity: 0.1;
        }

        .d-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .d-val {
            font-size: 1.8rem;
            font-weight: 800;
            color: #ecf0f1;
        }

        .d-trend {
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .d-trend.pos {
            color: #2ecc71;
        }

        .d-trend.neg {
            color: #e74c3c;
        }

        /* Sections */
        .section-title {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tips-box {
            background: rgba(241, 196, 15, 0.05);
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 2rem;
        }

        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .progress-bar {
            background: #333;
            height: 6px;
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
        }

        /* Table Styles override */
        table {
            font-size: 0.9rem;
        }

        th {
            background: #252a33;
        }

        tr:hover td {
            background: #1e2229;
        }

        .debt-list tr td {
            color: #e74c3c;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <h1 style="margin-bottom: 0.5rem;">Visão Geral do Império <span style="font-size:1rem; color:#666;">v3.0</span>
        </h1>
        <p style="color:#777; margin-bottom: 2rem;">Bem-vindo, Administrador. Aqui estão os números do seu negócio hoje.
        </p>

        <!-- 1. MAIN KPI -->
        <div class="dash-grid-main">
            <div class="dash-card">
                <i class="fas fa-wallet d-icon" style="color:#2ecc71"></i>
                <div class="d-label">Receita (Mês Atual)</div>
                <div class="d-val">R$ <?php echo number_format($fin['revenue_month'], 2, ',', '.'); ?></div>
                <div class="d-trend pos"><i class="fas fa-arrow-up"></i> Fluxo de Caixa</div>
            </div>

            <div class="dash-card">
                <i class="fas fa-exclamation-circle d-icon" style="color:#e74c3c"></i>
                <div class="d-label">A Receber (Dívidas)</div>
                <div class="d-val" style="color:#e74c3c">R$
                    <?php echo number_format($fin['pending_debt'], 2, ',', '.'); ?>
                </div>
                <div class="d-trend"><?php echo $stats['pending'] ?? 0; ?> pedidos pendentes</div>
            </div>

            <div class="dash-card">
                <i class="fas fa-users d-icon" style="color:#3498db"></i>
                <div class="d-label">Clientes Cadastrados</div>
                <div class="d-val"><?php echo $crm['total_users']; ?></div>
                <div class="d-trend pos">+<?php echo $crm['new_users']; ?> novos (30d)</div>
            </div>

            <div class="dash-card">
                <i class="fas fa-bullseye d-icon" style="color:#00e676"></i>
                <div class="d-label">Leads (Gatekeeper)</div>
                <div class="d-val" style="color:#00e676"><?php echo $crm['leads_game']; ?></div>
                <div class="d-trend"><a href="leads.php" style="color:#3498db; text-decoration:underline;">Ver
                        interessados</a></div>
            </div>

            <div class="dash-card">
                <i class="fas fa-boxes d-icon" style="color:#f1c40f"></i>
                <div class="d-label">Ativos em Estoque</div>
                <div class="d-val" style="font-size:1.4rem;">R$
                    <?php echo number_format($total_inventory_value, 2, ',', '.'); ?>
                </div>
                <div class="d-trend" style="color:#aaa;"><?php echo $prodParams['total_stock']; ?> itens físicos</div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem;">

            <!-- LEFT COLUMN -->
            <div>
                <!-- SEO & HEALTH -->
                <div class="section-title"><i class="fas fa-heartbeat"></i> Saúde do Catálogo & SEO</div>
                <div class="health-grid">
                    <div class="dash-card"
                        style="border-color: <?php echo $seo['missing_img'] > 0 ? '#e74c3c' : '#2ecc71'; ?>">
                        <div class="d-label">Produtos sem Foto</div>
                        <div class="d-val"><?php echo $seo['missing_img']; ?></div>
                        <?php if ($seo['missing_img'] > 0): ?>
                            <small style="color:#e74c3c">Crítico para conversão!</small>
                        <?php else: ?>
                            <small style="color:#2ecc71">Tudo certo!</small>
                        <?php endif; ?>
                    </div>

                    <div class="dash-card">
                        <div class="d-label">Qualidade SEO</div>
                        <div class="d-val"><?php echo $seo['poor_seo']; ?> <span style="font-size:1rem; color:#666;">/
                                <?php echo $prodParams['total']; ?></span></div>
                        <small style="color:#777">Produtos com descrições curtas</small>
                        <div class="progress-bar">
                            <?php
                            $seo_score = $prodParams['total'] > 0 ? 100 - ($seo['poor_seo'] / $prodParams['total'] * 100) : 0;
                            ?>
                            <div class="progress-fill" style="width: <?php echo $seo_score; ?>%"></div>
                        </div>
                    </div>

                    <div class="dash-card">
                        <div class="d-label">Leads (Newsletter)</div>
                        <div class="d-val"><?php echo $crm['leads']; ?></div>
                        <small style="color:#3498db">Potenciais compradores</small>
                    </div>
                </div>

                <!-- ORDERS -->
                <div class="section-title"><i class="fas fa-clock"></i> Últimos Pedidos</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $o): ?>
                                <tr>
                                    <td>#<?php echo $o['id']; ?></td>
                                    <td><?php echo htmlspecialchars($o['user_name']); ?></td>
                                    <td>R$ <?php echo number_format($o['total_amount'], 2, ',', '.'); ?></td>
                                    <td><span
                                            class="status-badge status-<?php echo $o['status']; ?>"><?php echo $o['status']; ?></span>
                                    </td>
                                    <td style="text-align:right;"><a href="orders.php?id=<?php echo $o['id']; ?>"
                                            class="btn-sm"><i class="fas fa-eye"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div>
                <!-- ALERTS -->
                <?php if ($fin['pending_debt'] > 0): ?>
                    <div class="section-title"><i class="fas fa-hand-holding-usd"></i> Maiores Devedores (Pendentes)</div>
                    <div class="dash-card" style="padding:0; overflow:hidden;">
                        <table class="debt-list" style="margin:0; width:100%;">
                            <?php foreach ($top_debtors as $d): ?>
                                <tr style="border-bottom:1px solid #333;">
                                    <td style="padding:10px 15px; color:#fff;">
                                        <strong><?php echo htmlspecialchars($d['user_name']); ?></strong><br>
                                        <small
                                            style="color:#666;"><?php echo date('d/m', strtotime($d['created_at'])); ?></small>
                                    </td>
                                    <td style="padding:10px 15px; text-align:right; font-weight:bold;">
                                        R$ <?php echo number_format($d['total_amount'], 2, ',', '.'); ?>
                                    </td>
                                    <td style="width:40px;">
                                        <a href="https://api.whatsapp.com/send?phone=55<?php echo preg_replace('/\D/', '', $d['phone'] ?? ''); ?>&text=Ol%C3%A1%2C%20vi%20seu%20pedido%20pendente%20no%20site..."
                                            target="_blank" style="color:#2ecc71"><i class="fab fa-whatsapp"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <div style="padding:10px; text-align:center; background:#222;">
                            <a href="orders.php?status=pending" style="font-size:0.8rem; color:#aaa;">Ver todos os
                                pendentes</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="section-title" style="margin-top:2rem;"><i class="fas fa-rocket"></i> Ações Rápidas</div>
                <div style="display:grid; gap:10px;">
                    <a href="product-edit.php" class="btn" style="text-align:left;"><i class="fas fa-plus-circle"
                            style="margin-right:10px;"></i> Add Produto</a>
                    <a href="pos.php" class="btn" style="text-align:left; background:#e67e22;"><i
                            class="fas fa-cash-register" style="margin-right:10px;"></i> Abrir PDV</a>
                    <a href="customers.php" class="btn btn-secondary" style="text-align:left;"><i
                            class="fas fa-user-plus" style="margin-right:10px;"></i> Gerenciar Clientes</a>
                    <a href="../sitemap.xml" target="_blank" class="btn btn-secondary" style="text-align:left;"><i
                            class="fas fa-sitemap" style="margin-right:10px;"></i> Ver Sitemap XML</a>
                </div>

                <div class="tips-box" style="margin-top:2rem;">
                    <strong>💡 Dica Pro:</strong>
                    <p style="font-size:0.85rem; margin-top:5px; color:#ccc;">Produtos com boas descrições e títulos
                        otimizados vendem até 3x mais. Verifique a aba de "Saúde do Catálogo".</p>
                </div>
            </div>

        </div>
    </div>

</body>

</html>