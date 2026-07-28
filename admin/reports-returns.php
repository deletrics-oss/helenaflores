<?php
/**
 * admin/reports-returns.php — Relatório de Retornos & Devoluções
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');
$search_customer = $_GET['search_customer'] ?? '';
$search_product = $_GET['search_product'] ?? '';
$refund_method = $_GET['refund_method'] ?? '';

// Build Query
$sql = "SELECT * FROM rma_tickets WHERE (type = 'devolucao' OR refund_price > 0) AND DATE(created_at) BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if (!empty($search_customer)) {
    $sql .= " AND (customer_name LIKE ? OR phone LIKE ? OR document LIKE ?)";
    $params[] = "%$search_customer%";
    $params[] = "%$search_customer%";
    $params[] = "%$search_customer%";
}

if (!empty($search_product)) {
    $sql .= " AND product_name LIKE ?";
    $params[] = "%$search_product%";
}

if (!empty($refund_method)) {
    $sql .= " AND refund_method = ?";
    $params[] = $refund_method;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export CSV mode
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_devolucoes_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['Data', 'Ocorrência ID', 'Cliente', 'Documento', 'Telefone', 'Produto', 'Quantidade', 'Preço Unitário (R$)', 'Total Reembolso (R$)', 'Forma Reembolso', 'Motivo']);
    foreach ($items as $item) {
        $subtotal = $item['refund_price'] * $item['qty_returned'];
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($item['created_at'])),
            $item['id'],
            $item['customer_name'],
            $item['document'],
            $item['phone'],
            $item['product_name'],
            $item['qty_returned'],
            number_format($item['refund_price'], 2, ',', ''),
            number_format($subtotal, 2, ',', ''),
            $item['refund_method'] ?: 'Não definido',
            $item['issue_desc']
        ]);
    }
    fclose($output);
    exit;
}

// Calculate KPIs
$total_refunded = 0;
$total_items = 0;
foreach ($items as $item) {
    $total_refunded += $item['refund_price'] * $item['qty_returned'];
    $total_items += $item['qty_returned'];
}
$transaction_count = count($items);
$avg_refund = $transaction_count > 0 ? ($total_refunded / $transaction_count) : 0;

// Fetch unique refund methods for filter dropdown
try {
    $methods_list = $pdo->query("SELECT DISTINCT refund_method FROM rma_tickets WHERE refund_method IS NOT NULL AND refund_method != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $methods_list = ['Saldo', 'PIX', 'Dinheiro', 'Estorno de RMA'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Retornos & Devoluções | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --card-bg: rgba(26, 30, 42, 0.75);
            --accent-red: #e74c3c;
            --accent-green: #2ecc71;
            --accent-yellow: #f1c40f;
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-muted: #5a6478;
        }

        body { 
            background: #0b0e14; 
            color: #e1e1e1; 
            font-family: 'Inter', system-ui, sans-serif; 
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2.5rem;
        }

        .report-header h1 {
            font-size: 2.5rem;
            font-weight: 900;
            margin: 0;
            background: linear-gradient(to right, #fff, #888);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 2.5rem;
        }

        .report-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 1.8rem;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 35px rgba(0,0,0,0.35);
            position: relative;
            overflow: hidden;
            transition: transform 0.25s ease, border-color 0.25s ease;
        }
        .report-card:hover {
            transform: translateY(-3px);
            border-color: rgba(241, 196, 15, 0.3);
        }
        .report-card.card-total { border-bottom: 4px solid var(--accent-red); }
        .report-card.card-items { border-bottom: 4px solid #3498db; }
        .report-card.card-avg { border-bottom: 4px solid var(--accent-green); }
        .report-card.card-count { border-bottom: 4px solid var(--accent-yellow); }

        .report-card h3 {
            font-size: 0.75rem;
            color: #888;
            text-transform: uppercase;
            margin: 0 0 0.8rem 0;
            letter-spacing: 1.5px;
            font-weight: 800;
        }
        .report-card .value {
            font-size: 2rem;
            font-weight: 900;
            margin: 0;
        }
        .report-card .sub-value {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.4rem;
            font-weight: 500;
        }

        .filter-bar {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid var(--glass-border);
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.65rem;
            color: #888;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .filter-group input, .filter-group select {
            background: rgba(0,0,0,0.4);
            border: 1px solid #2d3848;
            color: #fff;
            padding: 9px 12px;
            border-radius: 8px;
            outline: none;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        .filter-group input:focus, .filter-group select:focus {
            border-color: var(--accent-yellow);
        }

        .btn-filter {
            background: var(--accent-yellow);
            color: #000;
            font-weight: 800;
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, background-color 0.2s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-filter:hover {
            background: #d4ac0d;
            transform: scale(1.02);
        }

        .btn-secondary-custom {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            font-weight: 700;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-secondary-custom:hover {
            background: rgba(255,255,255,0.12);
        }

        .table-section {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            padding: 1.8rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .table-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .table-section-header h2 {
            margin: 0;
            font-size: 1.3rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        thead th {
            color: #5a6478;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 1.5px;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #1f2836;
        }
        tbody tr {
            background: rgba(255,255,255,0.02);
            transition: background 0.2s, transform 0.2s;
        }
        tbody tr:hover {
            background: rgba(255,255,255,0.04);
            transform: scale(1.002);
        }
        tbody td {
            padding: 12px;
            border: none;
            font-size: 0.88rem;
            vertical-align: middle;
        }
        tbody td:first-child { border-radius: 8px 0 0 8px; }
        tbody td:last-child { border-radius: 0 8px 8px 0; }

        .method-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-saldo { background: rgba(52, 152, 219, 0.15); color: #3498db; }
        .badge-pix { background: rgba(46, 204, 113, 0.15); color: #2ecc71; }
        .badge-dinheiro { background: rgba(241, 196, 15, 0.15); color: #f1c40f; }
        .badge-estorno { background: rgba(155, 89, 182, 0.15); color: #9b59b6; }
        .badge-default { background: rgba(255,255,255,0.08); color: #ccc; }

        .empty-row-text {
            text-align: center;
            padding: 4rem !important;
            color: var(--text-muted);
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top: 3rem;">
        
        <div class="report-header">
            <div>
                <h1>Auditoria de Retornos & Devoluções</h1>
                <p style="color:#5a6478; margin-top:5px; font-weight:500;">Conferência de valores de atacado/varejo e formas de estorno registradas.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="pos-reverso.php" class="btn-secondary-custom" style="border-color: var(--accent-red); color: var(--accent-red);"><i class="fas fa-undo"></i> PDV Reverso</a>
                <a href="rma.php" class="btn-secondary-custom" style="border-color: #9b59b6; color: #9b59b6;"><i class="fas fa-hand-holding-heart"></i> Painel RMA</a>
            </div>
        </div>

        <!-- FILTROS -->
        <form method="GET" class="filter-bar">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Data Inicial</label>
                    <input type="date" name="start" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="filter-group">
                    <label>Data Final</label>
                    <input type="date" name="end" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="filter-group">
                    <label>Cliente</label>
                    <input type="text" name="search_customer" placeholder="Nome, Whats ou CPF..." value="<?php echo htmlspecialchars($search_customer); ?>">
                </div>
                <div class="filter-group">
                    <label>Produto</label>
                    <input type="text" name="search_product" placeholder="Nome da peça ou SKU..." value="<?php echo htmlspecialchars($search_product); ?>">
                </div>
                <div class="filter-group">
                    <label>Forma Reembolso</label>
                    <select name="refund_method">
                        <option value="">-- Todos --</option>
                        <?php foreach($methods_list as $method): ?>
                            <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $refund_method === $method ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($method); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-filter"><i class="fas fa-sync-alt"></i> Filtrar</button>
                    <a href="reports-returns.php" class="btn-secondary-custom" title="Limpar Filtros"><i class="fas fa-eraser"></i></a>
                </div>
            </div>
        </form>

        <!-- KPIS -->
        <div class="report-grid">
            <div class="report-card card-total">
                <h3>💰 Total Devolvido</h3>
                <div class="value finance-value">R$ <?php echo number_format($total_refunded, 2, ',', '.'); ?></div>
                <div class="sub-value">Soma dos preços unitários registrados</div>
            </div>
            <div class="report-card card-items">
                <h3>📦 Peças Retornadas</h3>
                <div class="value"><?php echo $total_items; ?> <span style="font-size: 1rem; color: #5a6478;">unidades</span></div>
                <div class="sub-value">Total de itens devolvidos ao estoque</div>
            </div>
            <div class="report-card card-avg">
                <h3>🎫 Reembolso Médio</h3>
                <div class="value finance-value">R$ <?php echo number_format($avg_refund, 2, ',', '.'); ?></div>
                <div class="sub-value">Média por transação/ocorrência</div>
            </div>
            <div class="report-card card-count">
                <h3>🔄 Ocorrências</h3>
                <div class="value"><?php echo $transaction_count; ?> <span style="font-size: 1rem; color: #5a6478;">registros</span></div>
                <div class="sub-value">No período selecionado</div>
            </div>
        </div>

        <!-- LISTA DETALHADA -->
        <div class="table-section">
            <div class="table-section-header">
                <h2><i class="fas fa-list-ul" style="color: var(--accent-yellow);"></i> Itens Retornados e Valores Aplicados</h2>
                <?php if ($transaction_count > 0): ?>
                    <?php 
                    $export_url = "reports-returns.php?export=1&start=" . urlencode($start_date) . "&end=" . urlencode($end_date) . "&search_customer=" . urlencode($search_customer) . "&search_product=" . urlencode($search_product) . "&refund_method=" . urlencode($refund_method);
                    ?>
                    <a href="<?php echo $export_url; ?>" class="btn-secondary-custom" style="border-color: var(--accent-green); color: var(--accent-green);"><i class="fas fa-file-excel"></i> Exportar CSV</a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>ID Ocorrência</th>
                            <th>Cliente</th>
                            <th>Produto / Peça</th>
                            <th style="text-align: center;">Qtd</th>
                            <th style="text-align: right;">Preço Unit.</th>
                            <th style="text-align: right;">Subtotal</th>
                            <th style="text-align: center;">Reembolso</th>
                            <th>Motivo / Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="9" class="empty-row-text">Nenhuma devolução ou retorno registrado no filtro selecionado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): 
                                $sub = $item['refund_price'] * $item['qty_returned'];
                                $method_class = 'badge-default';
                                $m_lower = strtolower($item['refund_method'] ?? '');
                                if (strpos($m_lower, 'saldo') !== false || strpos($m_lower, 'crédito') !== false) $method_class = 'badge-saldo';
                                elseif (strpos($m_lower, 'pix') !== false) $method_class = 'badge-pix';
                                elseif (strpos($m_lower, 'dinheiro') !== false) $method_class = 'badge-dinheiro';
                                elseif (strpos($m_lower, 'estorno') !== false) $method_class = 'badge-estorno';
                            ?>
                                <tr>
                                    <td><strong><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></strong></td>
                                    <td>
                                        <a href="rma.php?edit=<?php echo $item['id']; ?>" style="color:var(--accent-yellow); font-weight:bold; text-decoration:none;">
                                            #<?php echo $item['id']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="color:#fff; font-weight:600;"><?php echo htmlspecialchars($item['customer_name']); ?></span>
                                        <?php if ($item['phone']): ?>
                                            <br><small style="color:#666; font-size:0.75rem;"><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($item['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="color:#fff;"><?php echo htmlspecialchars($item['product_name']); ?></span></td>
                                    <td style="text-align: center;"><span style="background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:12px; font-weight:bold;"><?php echo $item['qty_returned']; ?></span></td>
                                    <td style="text-align: right; font-weight:600;"><span class="finance-value">R$ <?php echo number_format($item['refund_price'], 2, ',', '.'); ?></span></td>
                                    <td style="text-align: right; font-weight:800; color:var(--accent-green);"><span class="finance-value">R$ <?php echo number_format($sub, 2, ',', '.'); ?></span></td>
                                    <td style="text-align: center;">
                                        <span class="method-badge <?php echo $method_class; ?>">
                                            <?php echo htmlspecialchars($item['refund_method'] ?: 'Não definido'); ?>
                                        </span>
                                    </td>
                                    <td style="color:#aaa; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($item['issue_desc'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($item['issue_desc'] ?? ''); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
