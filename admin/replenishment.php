<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// 1. Fetch Replenishment Data (Sales 30d + Stock)
$sql = "SELECT 
            p.id, 
            p.name, 
            p.stock_qty as stock, 
            p.sku,
            p.image_path,
            COALESCE(SUM(oi.quantity), 0) as sales_30d
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND o.status != 'cancelled'
        WHERE p.active = 1
        GROUP BY p.id
        ORDER BY sales_30d DESC, p.stock_qty ASC";

$stmt = $pdo->query($sql);
$inventory = $stmt->fetchAll();

// Thresholds for alerts
$CRITICAL_DAYS = 7;
$WARNING_DAYS = 14;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>IA Predictor | Reposição Inteligente</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --critical: #ff4d4d;
            --warning: #f1c40f;
            --safe: #00ff88;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .stat-val { font-size: 2rem; font-weight: bold; color: var(--primary); }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .badge-critical { background: var(--critical); color: #fff; }
        .badge-warning { background: var(--warning); color: #000; }
        .badge-safe { background: var(--safe); color: #000; }
        
        .product-row img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h1 style="color:var(--primary);">🧠 IA Predictor: Reposição</h1>
                <p style="color:#888;">Análise de vendas dos últimos 30 dias vs Estoque Atual</p>
            </div>
            <a href="inventory_scanner.php" class="btn">📱 Abrir Scanner Mobile</a>
        </div>

        <div class="dashboard-grid">
            <?php
            $critical_count = 0;
            $warning_count = 0;
            foreach($inventory as $item) {
                $avg_daily = $item['sales_30d'] / 30;
                $days_left = ($avg_daily > 0) ? ($item['stock'] / $avg_daily) : 999;
                if($days_left <= $CRITICAL_DAYS) $critical_count++;
                elseif($days_left <= $WARNING_DAYS) $warning_count++;
            }
            ?>
            <div class="stat-card">
                <div class="stat-val" style="color:var(--critical);"><?php echo $critical_count; ?></div>
                <div style="color:#888;">Produtos em Alerta Crítico</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color:var(--warning);"><?php echo $warning_count; ?></div>
                <div style="color:#888;">Atenção (Reposição Próxima)</div>
            </div>
            <div class="stat-card">
                <div class="stat-val"><?php echo count($inventory); ?></div>
                <div style="color:#888;">Total de Itens Ativos</div>
            </div>
        </div>

        <div class="table-responsive">
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Vendas (30d)</th>
                        <th>Estoque Atual</th>
                        <th>Projeção (Dias)</th>
                        <th>Status / Sugestão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($inventory as $p): 
                        $avg_daily = $p['sales_30d'] / 30;
                        $days_left = ($avg_daily > 0) ? ($p['stock'] / $avg_daily) : 999;
                        
                        $status_class = 'badge-safe';
                        $status_text = 'Estoque Seguro';
                        $suggestion = 'Manter';

                        if($days_left <= $CRITICAL_DAYS) {
                            $status_class = 'badge-critical';
                            $status_text = 'Crítico';
                            $suggestion = 'Comprar Imediato: ' . ceil($avg_daily * 30);
                        } elseif ($days_left <= $WARNING_DAYS) {
                            $status_class = 'badge-warning';
                            $status_text = 'Atenção';
                            $suggestion = 'Planejar Compra';
                        }
                    ?>
                    <tr class="product-row">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <img src="../assets/uploads/<?php echo $p['image_path'] ?: 'no-img.png'; ?>" onerror="this.src='../assets/no-img.png'">
                                <div>
                                    <strong style="display:block;"><?php echo htmlspecialchars($p['name']); ?></strong>
                                    <small style="color:#666;"><?php echo $p['sku']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo (int)$p['sales_30d']; ?> un</td>
                        <td style="font-weight:bold;"><?php echo $p['stock']; ?></td>
                        <td><?php echo ($days_left > 365) ? '∞' : round($days_left, 1); ?> dias</td>
                        <td>
                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span><br>
                            <small style="color:#aaa;"><?php echo $suggestion; ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
