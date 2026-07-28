<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// Ensure database columns exist before querying
try { $pdo->exec("ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE product_variations ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE order_items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

// --- 1. FINANCIAL SUMMARY ---
$sql_fin = "
    SELECT 
        IFNULL(SUM(oi.quantity * oi.unit_price), 0) as revenue,
        IFNULL(SUM(oi.quantity * oi.cost_price), 0) as total_cost,
        COUNT(DISTINCT o.id) as total_orders
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
";
$fin = $pdo->prepare($sql_fin);
$fin->execute([$start_date, $end_date]);
$summary = $fin->fetch();

// --- 2. PRODUCT PERFORMANCE ---
$sql_prod = "
    SELECT 
        p.name,
        SUM(oi.quantity) as total_qty,
        IFNULL(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
        IFNULL(SUM(oi.quantity * (oi.unit_price - oi.cost_price)), 0) as total_profit
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_qty DESC
";
$products_sold = $pdo->prepare($sql_prod);
$products_sold->execute([$start_date, $end_date]);
$products = $products_sold->fetchAll();

// --- 3. CUSTOMER GROWTH ---
$sql_cust = "
    SELECT 
        COUNT(CASE WHEN is_lead = 0 THEN 1 END) as new_customers,
        COUNT(CASE WHEN is_lead = 1 THEN 1 END) as new_leads
    FROM users 
    WHERE role != 'admin' AND DATE(created_at) BETWEEN ? AND ?
";
$cust = $pdo->prepare($sql_cust);
$cust->execute([$start_date, $end_date]);
$growth = $cust->fetch();

// Safety check for empty values
if (!$summary) { $summary = ['revenue'=>0, 'total_cost'=>0, 'total_orders'=>0]; }
if (!$growth) { $growth = ['new_customers'=>0, 'new_leads'=>0]; }

// --- 4. DAILY SALES CHART DATA ---
$sql_daily = "
    SELECT DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as amount
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
";
$daily_stmt = $pdo->prepare($sql_daily);
$daily_stmt->execute([$start_date, $end_date]);
$daily_sales = $daily_stmt->fetchAll();

// --- 5. WEEKLY GROWTH GOAL ---
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));
$sql_weekly = "SELECT COUNT(*) FROM users WHERE is_lead = 0 AND role != 'admin' AND DATE(created_at) >= ?";
$weekly_stmt = $pdo->prepare($sql_weekly);
$weekly_stmt->execute([$seven_days_ago]);
$new_customers_week = (int)$weekly_stmt->fetchColumn();

$weekly_goal = 10; // Meta sugerida: 10 novos clientes por semana
$goal_percent = min(100, ($new_customers_week / $weekly_goal) * 100);

// --- 6. TICKET MÉDIO ---
$avgTicket = ($summary['total_orders'] > 0) ? ($summary['revenue'] / $summary['total_orders']) : 0;

// --- 7. TAXA DE RECOMPRA ---
try {
    $repurchase = $pdo->query("SELECT COUNT(*) FROM (SELECT user_id FROM orders GROUP BY user_id HAVING COUNT(*) >= 2) t")->fetchColumn();
    $totalBuyers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders")->fetchColumn();
    $repurchaseRate = ($totalBuyers > 0) ? ($repurchase / $totalBuyers) * 100 : 0;
} catch(Exception $e) { $repurchaseRate = 0; $repurchase = 0; $totalBuyers = 0; }

// --- 8. DEVEDORES (AGING) ---
try {
    $debtorsData = $pdo->query("
        SELECT o.id, o.total_amount, o.created_at, DATEDIFF(NOW(), o.created_at) as days_pending, 
               u.name, u.phone, u.city, u.state
        FROM orders o JOIN users u ON o.user_id = u.id 
        WHERE o.status = 'pending' 
        ORDER BY o.created_at ASC LIMIT 20
    ")->fetchAll();
    $totalDebt = $pdo->query("SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE status = 'pending'")->fetchColumn();
    $debtCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
} catch(Exception $e) { $debtorsData = []; $totalDebt = 0; $debtCount = 0; }

// --- 9. TOP CIDADES ---
try {
    $topCities = $pdo->prepare("
        SELECT u.city, u.state, COUNT(o.id) as orders_count, SUM(o.total_amount) as total_revenue
        FROM orders o JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND u.city IS NOT NULL AND u.city != ''
        GROUP BY u.city, u.state
        ORDER BY total_revenue DESC LIMIT 10
    ");
    $topCities->execute([$start_date, $end_date]);
    $citiesData = $topCities->fetchAll();
} catch(Exception $e) { $citiesData = []; }

// --- 11. PREDIÇÃO PREDITIVA DE ESTOQUE (IA) ---
try {
    $sql_predict = "
        SELECT 
            p.id, p.name, p.stock,
            (SELECT COALESCE(SUM(oi.quantity), 0) / 30 
             FROM order_items oi 
             JOIN orders o2 ON o2.id = oi.order_id 
             WHERE oi.product_id = p.id AND o2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ) as velocity
        FROM products p 
        WHERE p.active = 1 AND p.stock IS NOT NULL
        HAVING velocity > 0
        ORDER BY velocity DESC LIMIT 15
    ";
    $predictData = $pdo->query($sql_predict)->fetchAll();
} catch(Exception $e) { $predictData = []; }

// --- 12. CUSTOMER PURCHASES DETAILS ---
try {
    $sql_cust_purchases = "
        SELECT 
            u.id as user_id,
            u.name as customer_name,
            u.phone as customer_phone,
            p.name as product_name,
            SUM(oi.quantity) as total_qty,
            SUM(oi.quantity * oi.unit_price) as total_amount
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN users u ON u.id = o.user_id
        JOIN products p ON p.id = oi.product_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY u.id, p.id
        ORDER BY u.name ASC, total_qty DESC
    ";
    $cust_purchases_stmt = $pdo->prepare($sql_cust_purchases);
    $cust_purchases_stmt->execute([$start_date, $end_date]);
    $customer_purchases = $cust_purchases_stmt->fetchAll();
} catch (Exception $e) { $customer_purchases = []; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatórios e Inteligência | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --card-bg: rgba(26, 30, 42, 0.7);
            --accent-blue: #3498db;
            --accent-green: #2ecc71;
            --accent-purple: #9b59b6;
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body { background: #0b0e14; color: #e1e1e1; font-family: 'Inter', system-ui, sans-serif; }

        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 2.5rem; }
        
        .report-card { 
            background: var(--card-bg); 
            backdrop-filter: blur(10px);
            padding: 2rem; 
            border-radius: 20px; 
            border: 1px solid var(--glass-border); 
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .report-card:hover { transform: translateY(-5px); }
        .report-card::after {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%);
            pointer-events: none;
        }

        .report-card h3 { font-size: 0.75rem; color: #888; text-transform: uppercase; margin-bottom: 1rem; letter-spacing: 2px; font-weight: 800; }
        .report-card .value { font-size: 2.2rem; font-weight: 900; margin: 0.5rem 0; }
        .report-card .sub-value { font-size: 0.85rem; color: #666; font-weight: 500; }
        
        /* Specific Card Accents */
        .card-revenue { border-bottom: 4px solid var(--accent-blue); }
        .card-profit { border-bottom: 4px solid var(--accent-green); }
        .card-growth { border-bottom: 4px solid var(--accent-purple); }
        .card-daily { border-bottom: 4px solid #f1c40f; }

        .profit-positive { color: var(--accent-green) !important; text-shadow: 0 0 10px rgba(46, 204, 113, 0.2); }
        .profit-negative { color: #e74c3c !important; }

        .filter-bar { 
            background: var(--card-bg); 
            backdrop-filter: blur(10px);
            padding: 1.5rem 2rem; 
            border-radius: 15px; 
            border: 1px solid var(--glass-border); 
            margin-bottom: 2.5rem; 
            display: flex; 
            gap: 25px; 
            align-items: flex-end;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        .filter-bar input { 
            background: rgba(0,0,0,0.3); 
            border: 1px solid #333; 
            color: #fff; 
            padding: 10px 15px; 
            border-radius: 8px;
            outline: none;
        }
        .filter-bar input:focus { border-color: var(--primary); }

        .table-section { 
            background: var(--card-bg); 
            backdrop-filter: blur(10px);
            border-radius: 20px; 
            border: 1px solid var(--glass-border); 
            padding: 2rem; 
            margin-bottom: 2.5rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .table-section h2 { margin-bottom: 2rem; font-size: 1.4rem; color: #fff; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        thead th { color: #5a6478; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; padding: 10px; text-align: left; }
        tbody tr { background: rgba(255,255,255,0.02); transition: 0.2s; }
        tbody tr:hover { background: rgba(255,255,255,0.05); }
        tbody td { padding: 15px 10px; border: none; }
        tbody td:first-child { border-radius: 10px 0 0 10px; }
        tbody td:last-child { border-radius: 0 10px 10px 0; }

        .chart-simple { display: flex; align-items: flex-end; gap: 6px; height: 120px; margin-top: 15px; }
        .chart-bar { 
            background: linear-gradient(to top, var(--primary), #fff); 
            flex: 1; 
            min-width: 12px; 
            border-radius: 4px 4px 0 0; 
            position: relative; 
            opacity: 0.7;
            transition: 0.3s;
        }
        .chart-bar:hover { opacity: 1; transform: scaleX(1.1); }

        .stat-badge { background: rgba(52, 152, 219, 0.15); color: var(--accent-blue); padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:3rem;">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:3rem;">
            <div>
                <h1 style="font-size:2.5rem; font-weight:900; background: linear-gradient(to right, #fff, #888); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Inteligência de Negócio</h1>
                <p style="color:#5a6478; margin-top:5px; font-weight:500;">Monitoramento em tempo real de faturamento e performance.</p>
            </div>
            <div style="text-align:right;">
                <div style="background:rgba(255,255,255,0.05); padding:10px 20px; border-radius:30px; border:1px solid rgba(255,255,255,0.1);">
                    <span style="color:#888; font-size:0.8rem; font-weight:700;">CALENDÁRIO:</span> 
                    <span style="color:var(--primary); font-weight:800; margin-left:10px;"><?php echo date('d/m/y', strtotime($start_date)); ?> — <?php echo date('d/m/y', strtotime($end_date)); ?></span>
                </div>
            </div>
        </div>

        <form method="GET" class="filter-bar">
            <div>
                <label>DATA INICIAL</label>
                <input type="date" name="start" value="<?php echo $start_date; ?>">
            </div>
            <div>
                <label>DATA FINAL</label>
                <input type="date" name="end" value="<?php echo $end_date; ?>">
            </div>
            <button type="submit" class="btn" style="background:var(--primary); color:#000; font-weight:800; padding:12px 25px; border-radius:10px;">⚡ ATUALIZAR DASHBOARD</button>
            <div style="flex:1; text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                <a href="?start=<?php echo date('Y-m-d'); ?>&end=<?php echo date('Y-m-d'); ?>" class="btn-sm" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:10px 15px; text-decoration:none; border-radius:8px;">Hoje</a>
                <a href="?start=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end=<?php echo date('Y-m-d'); ?>" class="btn-sm" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:10px 15px; text-decoration:none; border-radius:8px;">7 Dias</a>
                <a href="?start=<?php echo date('Y-m-01'); ?>&end=<?php echo date('Y-m-d'); ?>" class="btn-sm" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:10px 15px; text-decoration:none; border-radius:8px;">Este Mês</a>
            </div>
        </form>

        <div class="report-grid">
            <div class="report-card card-revenue">
                <h3>💰 Faturamento Bruto</h3>
                <div class="value"><span class="finance-value">R$ <?php echo number_format($summary['revenue'] ?? 0, 2, ',', '.'); ?></span></div>
                <div class="sub-value">Total de <span style="color:#fff"><?php echo $summary['total_orders']; ?></span> pedidos aprovados</div>
            </div>
            <div class="report-card card-profit">
                <h3>💵 Lucro Líquido</h3>
                <?php 
                $profit = ($summary['revenue'] ?? 0) - ($summary['total_cost'] ?? 0); 
                $profit_class = ($profit >= 0) ? 'profit-positive' : 'profit-negative';
                ?>
                <div class="value <?php echo $profit_class; ?>"><span class="finance-value">R$ <?php echo number_format($profit, 2, ',', '.'); ?></span></div>
                <div class="sub-value">Margem operacional de <span style="color:#fff"><?php echo ($summary['revenue'] > 0) ? round(($profit / $summary['revenue']) * 100) : 0; ?>%</span></div>
            </div>
            <div class="report-card card-growth">
                <h3>👥 Novos Clientes</h3>
                <div class="value" style="color:var(--accent-purple);"><?php echo $growth['new_customers']; ?></div>
                <div class="sub-value">Captamos <span style="color:#fff">+<?php echo $growth['new_leads']; ?></span> novos leads qualificados</div>
            </div>

            <div class="report-card" style="border-bottom: 4px solid #f39c12;">
                <h3>🎯 Meta da Semana</h3>
                <div class="value" style="color:#f39c12;"><?php echo $new_customers_week; ?> <span style="font-size:0.9rem; color:#666; font-weight:500;">/ <?php echo $weekly_goal; ?></span></div>
                <div style="margin-top:10px;">
                    <div style="height:6px; background:rgba(255,255,255,0.05); border-radius:3px; overflow:hidden;">
                        <div style="height:100%; width:<?php echo $goal_percent; ?>%; background:#f39c12; box-shadow: 0 0 10px rgba(243, 156, 18, 0.3);"></div>
                    </div>
                    <div style="font-size:0.75rem; color:#888; margin-top:8px;">
                        <?php if($new_customers_week >= $weekly_goal): ?>
                            🏆 <strong>META BATIDA!</strong> Fantástico!
                        <?php else: ?>
                            Faltam <strong><?php echo ($weekly_goal - $new_customers_week); ?></strong> clientes para o objetivo.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="report-card card-daily">
                <h3>📦 Volume Diário</h3>
                <div class="chart-simple">
                    <?php 
                    $max_val = 1;
                    foreach($daily_sales as $ds) if($ds['count'] > $max_val) $max_val = $ds['count'];
                    foreach($daily_sales as $ds): 
                        $h = ($ds['count'] / $max_val) * 100;
                    ?>
                        <div class="chart-bar" style="height:<?php echo $h; ?>%" title="<?php echo date('d/m', strtotime($ds['date'])); ?>: <?php echo $ds['count']; ?> vendas (<span class="finance-value">R$ <?php echo number_format($ds['amount'],0,',','.'); ?></span>)"></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- NEW: Ticket Médio -->
            <div class="report-card" style="border-bottom:4px solid #e67e22;">
                <h3>🎫 Ticket Médio</h3>
                <div class="value" style="color:#e67e22;"><span class="finance-value">R$ <?php echo number_format($avgTicket, 2, ',', '.'); ?></span></div>
                <div class="sub-value">Valor médio por pedido no período</div>
            </div>

            <!-- NEW: Recompra -->
            <div class="report-card" style="border-bottom:4px solid #1abc9c;">
                <h3>🔁 Taxa de Recompra</h3>
                <div class="value" style="color:#1abc9c;"><?php echo round($repurchaseRate, 1); ?>%</div>
                <div class="sub-value"><?php echo $repurchase; ?> de <?php echo $totalBuyers; ?> clientes compraram 2+ vezes</div>
            </div>

            <!-- NEW: Dívida Total -->
            <div class="report-card" style="border-bottom:4px solid #e74c3c;">
                <h3>⚠️ Dívidas Pendentes</h3>
                <div class="value" style="color:#e74c3c;"><span class="finance-value">R$ <?php echo number_format($totalDebt, 2, ',', '.'); ?></span></div>
                <div class="sub-value"><?php echo $debtCount; ?> pedido(s) aguardando pagamento</div>
            </div>
        </div>

        <!-- PREDIÇÃO PREDITIVA (ESTOQUE INTELIGENTE) -->
        <div class="table-section" style="border-color:#00ff88; background:rgba(0, 255, 136, 0.05);">
            <h2 style="color:#00ff88;"><i class="fas fa-brain"></i> 🤖 IA Predictor: Previsão de Ruptura & Giro</h2>
            <p style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;">Baseado na velocidade de venda dos últimos 30 dias.</p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>PRODUTO</th>
                            <th style="text-align:center;">GIRO (DIA)</th>
                            <th style="text-align:center;">ESTOQUE ATUAL</th>
                            <th style="text-align:center;">PREVISÃO DE FIM</th>
                            <th style="text-align:right;">SUGESTÃO DE COMPRA (30D)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($predictData as $p): 
                            $days_left = ($p['velocity'] > 0) ? floor($p['stock'] / $p['velocity']) : 999;
                            $restock = ceil(($p['velocity'] * 30) - $p['stock']);
                            if($restock < 0) $restock = 0;
                            
                            $status_color = "#00ff88";
                            if($days_left <= 3) $status_color = "#ff4444";
                            else if($days_left <= 7) $status_color = "#f39c12";
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                <td style="text-align:center;"><?php echo number_format($p['velocity'], 2); ?>/dia</td>
                                <td style="text-align:center; font-weight:bold;"><?php echo $p['stock']; ?> un</td>
                                <td style="text-align:center;">
                                    <span style="color:<?php echo $status_color; ?>; font-weight:bold;">
                                        <?php echo ($days_left > 60) ? '60+ dias' : $days_left . ' dias'; ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?php if($restock > 0): ?>
                                        <span style="background:rgba(0,255,136,0.1); color:#00ff88; padding:4px 10px; border-radius:10px; font-weight:bold;">+ <?php echo $restock; ?> un</span>
                                    <?php else: ?>
                                        <span style="color:#666;">Estoque Ok</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-section">
            <h2><i class="fas fa-trophy" style="color:#f1c40f;"></i> Desempenho por Produto (Ranking)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>PRODUTO</th>
                            <th style="text-align:center;">VENDAS</th>
                            <th>FATURAMENTO</th>
                            <th>LUCRO ESTIMADO</th>
                            <th style="text-align:right;">EFICIÊNCIA (ROI)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($products)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:4rem; color:#5a6478; font-style:italic;">Nenhum dado de venda disponível para este período.</td></tr>
                        <?php endif; ?>
                        <?php foreach($products as $p): 
                            $roi = ($p['total_revenue'] - $p['total_profit'] > 0) ? ($p['total_profit'] / ($p['total_revenue'] - $p['total_profit'])) * 100 : 0;
                        ?>
                            <tr>
                                <td><span style="color:#fff; font-weight:700;"><?php echo htmlspecialchars($p['name']); ?></span></td>
                                <td style="text-align:center;"><span style="background:rgba(255,255,255,0.05); padding:5px 12px; border-radius:15px; font-weight:800; color:var(--primary);"><?php echo $p['total_qty']; ?></span></td>
                                <td><span class="finance-value">R$ <?php echo number_format($p['total_revenue'], 2, ',', '.'); ?></span></td>
                                <td class="profit-positive" style="font-weight:700;"><span class="finance-value">R$ <?php echo number_format($p['total_profit'], 2, ',', '.'); ?></span></td>
                                <td style="text-align:right;"><span class="stat-badge"><?php echo round($roi); ?>% ROI</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-section" style="border-color:#3498db;">
            <h2>🎯 Conversão de Leads (Win-back)</h2>
            <div style="display:flex; gap:20px; align-items:center;">
                <div style="flex:1; background:#000; padding:1.5rem; border-radius:8px; text-align:center;">
                    <div style="font-size:2rem; font-weight:bold; color:#3498db;">
                        <?php 
                        $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
                        $buying_users = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders")->fetchColumn();
                        $conversion = ($total_users > 0) ? ($buying_users / $total_users) * 100 : 0;
                        echo round($conversion, 1);
                        ?>%
                    </div>
                    <div style="color:#666; font-size:0.8rem; text-transform:uppercase;">Taxa de Conversão Total</div>
                </div>
                <div style="flex:2; color:#ccc;">
                    <p>Atualmente você tem <strong><?php echo ($total_users - $buying_users); ?></strong> clientes potenciais (leads) que ainda não realizaram a primeira compra.</p>
                    <p style="font-size:0.9rem; margin-top:10px; color:#888;">💡 Use a ferramenta de <strong>Marketing em Massa</strong> no menu Clientes para disparar ofertas exclusivas para estes contatos e aumentar seu lucro!</p>
                    <a href="customers.php?filter=leads" class="btn" style="margin-top:1rem; background:#3498db; color:#fff;">Ver Leads agora</a>
                </div>
            </div>
        </div>

        <div class="table-section" style="border-color:var(--accent-purple);">
            <h2><i class="fas fa-crown" style="color:var(--accent-purple);"></i> Melhores Clientes (Ranking de Faturamento)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>CLIENTE</th>
                            <th style="text-align:center;">PEDIDOS</th>
                            <th style="text-align:right;">TOTAL COMPRADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sql_top_cust = "
                            SELECT u.name, COUNT(o.id) as orders_count, SUM(o.total_amount) as total_spent
                            FROM users u
                            JOIN orders o ON o.user_id = u.id
                            WHERE DATE(o.created_at) BETWEEN ? AND ?
                            GROUP BY u.id
                            ORDER BY total_spent DESC
                            LIMIT 10
                        ";
                        $top_cust = $pdo->prepare($sql_top_cust);
                        $top_cust->execute([$start_date, $end_date]);
                        foreach($top_cust->fetchAll() as $tc):
                        ?>
                            <tr>
                                <td><span style="color:#fff; font-weight:700;"><?php echo htmlspecialchars($tc['name']); ?></span></td>
                                <td style="text-align:center;"><?php echo $tc['orders_count']; ?></td>
                                <td style="text-align:right; font-weight:800; color:var(--accent-green);"><span class="finance-value">R$ <?php echo number_format($tc['total_spent'], 2, ',', '.'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-section" style="border-color:var(--accent-blue);">
            <h2><i class="fas fa-shopping-bag" style="color:var(--accent-blue);"></i> Itens e Peças Compradas por Cliente</h2>
            <p style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;">Lista detalhada de quais produtos e quantidades cada cliente adquiriu no período selecionado.</p>
            
            <div style="margin-bottom:1.5rem; display:flex; gap:10px;">
                <input type="text" id="customer-purchases-search" placeholder="🔍 Buscar por cliente ou produto (ex: Robson, Comando...)" style="width:100%; max-width:400px; background:rgba(0,0,0,0.3); border:1px solid #333; color:#fff; padding:10px 15px; border-radius:8px; outline:none;" onkeyup="filterCustomerPurchases()">
            </div>

            <div class="table-responsive">
                <table id="customer-purchases-table">
                    <thead>
                        <tr>
                            <th>CLIENTE</th>
                            <th>CONTATO</th>
                            <th>PRODUTO / PEÇA</th>
                            <th style="text-align:center;">QTD COMPRADA</th>
                            <th style="text-align:right;">TOTAL GASTO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($customer_purchases)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:4rem; color:#5a6478; font-style:italic;">Nenhum item comprado neste período.</td></tr>
                        <?php endif; ?>
                        <?php foreach($customer_purchases as $cp): ?>
                            <tr>
                                <td><span style="color:#fff; font-weight:700;"><?php echo htmlspecialchars($cp['customer_name']); ?></span></td>
                                <td><span style="color:#888;"><?php echo htmlspecialchars($cp['customer_phone'] ?: 'Sem telefone'); ?></span></td>
                                <td><span style="color:#fff;"><?php echo htmlspecialchars($cp['product_name']); ?></span></td>
                                <td style="text-align:center;"><span style="background:rgba(52, 152, 219, 0.15); padding:5px 12px; border-radius:15px; font-weight:800; color:var(--accent-blue);"><?php echo $cp['total_qty']; ?></span></td>
                                <td style="text-align:right; font-weight:800; color:var(--accent-green);"><span class="finance-value">R$ <?php echo number_format($cp['total_amount'], 2, ',', '.'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            function filterCustomerPurchases() {
                const input = document.getElementById('customer-purchases-search');
                const filter = input.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                const rows = document.querySelectorAll('#customer-purchases-table tbody tr');
                
                rows.forEach(row => {
                    if (row.cells.length === 1) return; // Ignora linha de vazio
                    const text = row.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                    if (text.includes(filter)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        </script>

        <!-- DEVEDORES AGING REPORT -->
        <?php if (!empty($debtorsData)): ?>
        <div class="table-section" style="border-color:#e74c3c;">
            <h2><i class="fas fa-exclamation-triangle" style="color:#e74c3c;"></i> Relatório de Devedores (Aging)</h2>
            <div style="display:flex; gap:15px; margin-bottom:1.5rem; flex-wrap:wrap;">
                <div style="background:rgba(231,76,60,.1); padding:12px 20px; border-radius:10px; border:1px solid rgba(231,76,60,.3);">
                    <div style="font-size:1.5rem; font-weight:900; color:#e74c3c;"><span class="finance-value">R$ <?php echo number_format($totalDebt, 2, ',', '.'); ?></span></div>
                    <div style="font-size:0.7rem; color:#888; text-transform:uppercase;">Total em Débito</div>
                </div>
                <div style="background:rgba(243,156,18,.1); padding:12px 20px; border-radius:10px; border:1px solid rgba(243,156,18,.3);">
                    <?php 
                    $debt7 = 0; $debt30 = 0; $debt60 = 0;
                    foreach($debtorsData as $d) {
                        if ($d['days_pending'] <= 7) $debt7 += $d['total_amount'];
                        elseif ($d['days_pending'] <= 30) $debt30 += $d['total_amount'];
                        else $debt60 += $d['total_amount'];
                    }
                    ?>
                    <div style="font-size:0.8rem; color:#f39c12;"><strong>0-7d:</strong> <span class="finance-value">R$ <?php echo number_format($debt7,2,',','.'); ?></span></div>
                    <div style="font-size:0.8rem; color:#e67e22;"><strong>8-30d:</strong> <span class="finance-value">R$ <?php echo number_format($debt30,2,',','.'); ?></span></div>
                    <div style="font-size:0.8rem; color:#e74c3c;"><strong>31d+:</strong> <span class="finance-value">R$ <?php echo number_format($debt60,2,',','.'); ?></span></div>
                    <div style="font-size:0.65rem; color:#888; text-transform:uppercase; margin-top:3px;">Aging por Período</div>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>PEDIDO</th>
                            <th>CLIENTE</th>
                            <th>CIDADE</th>
                            <th>VALOR</th>
                            <th>DIAS PENDENTE</th>
                            <th>RISCO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($debtorsData as $d): 
                            $risk = 'low';
                            $riskColor = '#2ecc71';
                            $riskLabel = 'Baixo';
                            if ($d['days_pending'] >= 30) { $risk = 'high'; $riskColor = '#e74c3c'; $riskLabel = 'URGENTE'; }
                            elseif ($d['days_pending'] >= 7) { $risk = 'medium'; $riskColor = '#f39c12'; $riskLabel = 'Atenção'; }
                        ?>
                            <tr>
                                <td><a href="orders.php?f_status=pending" style="color:#fff;font-weight:700">#<?php echo $d['id']; ?></a></td>
                                <td>
                                    <span style="color:#fff;font-weight:600"><?php echo htmlspecialchars($d['name']); ?></span>
                                    <?php if($d['phone']): ?><br><small style="color:#666"><?php echo $d['phone']; ?></small><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(($d['city'] ?? '') . '/' . ($d['state'] ?? '')); ?></td>
                                <td style="font-weight:800;color:#e74c3c"><span class="finance-value">R$ <?php echo number_format($d['total_amount'],2,',','.'); ?></span></td>
                                <td><span style="font-weight:800;color:<?php echo $riskColor; ?>"><?php echo $d['days_pending']; ?> dias</span></td>
                                <td><span style="background:<?php echo $riskColor; ?>;color:#000;padding:3px 10px;border-radius:12px;font-size:0.7rem;font-weight:800"><?php echo $riskLabel; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:1rem;">
                <a href="orders.php?export_debtors=1" class="btn" style="background:#e74c3c;color:#fff;font-size:0.85rem;">📥 Exportar CSV de Devedores</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- TOP CIDADES -->
        <?php if (!empty($citiesData)): ?>
        <div class="table-section" style="border-color:#3498db;">
            <h2><i class="fas fa-map-marker-alt" style="color:#3498db;"></i> Top Cidades por Faturamento</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>CIDADE/UF</th>
                            <th style="text-align:center;">PEDIDOS</th>
                            <th style="text-align:right;">FATURAMENTO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach($citiesData as $c): ?>
                            <tr>
                                <td style="color:#f1c40f;font-weight:900"><?php echo $rank++; ?>º</td>
                                <td><span style="color:#fff;font-weight:700"><?php echo htmlspecialchars($c['city']); ?></span> <small style="color:#666">/<?php echo $c['state']; ?></small></td>
                                <td style="text-align:center"><?php echo $c['orders_count']; ?></td>
                                <td style="text-align:right;font-weight:800;color:var(--accent-green)"><span class="finance-value">R$ <?php echo number_format($c['total_revenue'],2,',','.'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ESTOQUE CRÍTICO -->
        <?php if (!empty($lowStock)): ?>
        <div class="table-section" style="border-color:#e67e22;">
            <h2><i class="fas fa-box-open" style="color:#e67e22;"></i> Estoque Crítico (≤ 3 unidades)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>PRODUTO</th>
                            <th style="text-align:center;">ESTOQUE</th>
                            <th style="text-align:right;">PREÇO</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lowStock as $ls): ?>
                            <tr>
                                <td><a href="edit-product.php?id=<?php echo $ls['id']; ?>" style="color:#fff;font-weight:700"><?php echo htmlspecialchars($ls['name']); ?></a></td>
                                <td style="text-align:center;font-weight:900;color:<?php echo $ls['stock'] == 0 ? '#e74c3c' : '#f39c12'; ?>"><?php echo $ls['stock']; ?></td>
                                <td style="text-align:right"><span class="finance-value">R$ <?php echo number_format($ls['price'],2,',','.'); ?></span></td>
                                <td>
                                    <?php if ($ls['stock'] == 0): ?>
                                        <span style="background:#e74c3c;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.7rem;font-weight:800">ESGOTADO</span>
                                    <?php else: ?>
                                        <span style="background:#f39c12;color:#000;padding:3px 10px;border-radius:12px;font-size:0.7rem;font-weight:800">BAIXO</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
