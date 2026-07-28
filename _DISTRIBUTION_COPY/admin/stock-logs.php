<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$where = "WHERE 1=1";
$params = [];

if (!empty($_GET['product'])) {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%" . $_GET['product'] . "%";
    $params[] = "%" . $_GET['product'] . "%";
}

if (!empty($_GET['type'])) {
    $where .= " AND m.type = ?";
    $params[] = $_GET['type'];
}

$sql = "SELECT m.*, p.name as prod_name, p.sku as prod_sku, v.value as var_val, v.sku as var_sku, e.invoice_number
        FROM stock_movements m
        JOIN products p ON m.product_id = p.id
        LEFT JOIN product_variations v ON m.variation_id = v.id
        LEFT JOIN stock_entries e ON m.entry_id = e.id
        $where
        ORDER BY m.created_at DESC
        LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Daily Stats
$stats_stmt = $pdo->query("SELECT 
    SUM(CASE WHEN type='in' THEN qty ELSE 0 END) as total_in,
    SUM(CASE WHEN type='out' THEN qty ELSE 0 END) as total_out
    FROM stock_movements WHERE DATE(created_at) = CURDATE()");
$daily = $stats_stmt->fetch();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Estoque | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-card); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border); position: relative; overflow: hidden; }
        .stat-card::after { content: ''; position: absolute; bottom: 0; left: 0; height: 4px; width: 100%; opacity: 0.5; }
        .stat-in::after { background: #00e676; }
        .stat-out::after { background: #ff5252; }
        .stat-balance::after { background: var(--primary); }
        .stat-val { font-size: 2rem; font-weight: 800; margin-top: 0.5rem; }
        .log-table { width: 100%; background: var(--bg-card); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        .log-table th { background: #000; padding: 15px; color: var(--primary); text-align: left; }
        .log-table td { padding: 15px; border-bottom: 1px solid #222; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .badge-in { background: rgba(0,230,118,0.1); color: #00e676; border: 1px solid #00e676; }
        .badge-out { background: rgba(255,82,82,0.1); color: #ff5252; border: 1px solid #ff5252; }
        .filter-bar { background: var(--bg-card); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; gap: 1rem; align-items: flex-end; }
    </style>
</head>
<body class="admin-body">
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>📦 Movimentação de Estoque Inteligente</h1>

        <div class="stats-grid">
            <div class="stat-card stat-in">
                <small>Entradas de Hoje</small>
                <div class="stat-val"><?php echo (int)($daily['total_in'] ?? 0); ?> <span style="font-size:1rem; opacity:0.5;">un</span></div>
            </div>
            <div class="stat-card stat-out">
                <small>Saídas de Hoje</small>
                <div class="stat-val"><?php echo (int)($daily['total_out'] ?? 0); ?> <span style="font-size:1rem; opacity:0.5;">un</span></div>
            </div>
            <div class="stat-card stat-balance">
                <small>Balanço Diário</small>
                <div class="stat-val"><?php echo (int)($daily['total_in'] - $daily['total_out']); ?> <span style="font-size:1rem; opacity:0.5;">un</span></div>
            </div>
        </div>

        <form class="filter-bar">
            <div style="flex:1;">
                <label>Filtrar Produto (Nome/SKU)</label>
                <input type="text" name="product" value="<?php echo htmlspecialchars($_GET['product'] ?? ''); ?>" placeholder="Digite para buscar...">
            </div>
            <div>
                <label>Tipo</label>
                <select name="type">
                    <option value="">Todos</option>
                    <option value="in" <?php echo ($_GET['type'] ?? '') === 'in' ? 'selected' : ''; ?>>Entradas</option>
                    <option value="out" <?php echo ($_GET['type'] ?? '') === 'out' ? 'selected' : ''; ?>>Saídas</option>
                </select>
            </div>
            <button class="btn" type="submit">🔍 Filtrar</button>
            <a href="stock-logs.php" class="btn btn-secondary">🧹 Limpar</a>
        </form>

        <table class="log-table">
            <thead>
                <tr>
                    <th>Data / Hora</th>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th>Qtd</th>
                    <th>Origem / Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:3rem; opacity:0.5;">Não há registros para este filtro.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td style="font-size:0.9rem; opacity:0.8;">
                            <?php echo date('d/m/Y H:i', strtotime($l['created_at'])); ?>
                        </td>
                        <td>
                            <div style="font-weight:bold;"><?php echo htmlspecialchars($l['prod_name']); ?></div>
                            <?php if ($l['var_val']): ?>
                                <small style="background:#333; padding:2px 5px; border-radius:4px;"><?php echo htmlspecialchars($l['var_val']); ?></small>
                            <?php endif; ?>
                            <div style="font-size:0.8rem; opacity:0.5; margin-top:2px;">SKU: <?php echo htmlspecialchars($l['var_sku'] ?: $l['prod_sku']); ?></div>
                        </td>
                        <td>
                            <?php if ($l['type'] === 'in'): ?>
                                <span class="badge badge-in">↑ Entrada</span>
                            <?php else: ?>
                                <span class="badge badge-out">↓ Saída</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family:monospace; font-size:1.1rem; font-weight:bold;">
                            <?php echo $l['qty']; ?>
                        </td>
                        <td>
                            <?php if ($l['invoice_number']): ?>
                                <span style="color:#00e676;">🏭 Compra NFE #<?php echo $l['invoice_number']; ?></span>
                            <?php else: ?>
                                <span style="opacity:0.7;"><?php echo htmlspecialchars($l['reason']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>