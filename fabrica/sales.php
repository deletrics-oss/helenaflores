<?php
// catalogo/fabrica/sales.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Update Order Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    $sale_id = intval($_POST['sale_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'pending');
    $tracking_code = trim($_POST['tracking_code'] ?? '');
    $invoice_number = trim($_POST['invoice_number'] ?? '');

    if ($sale_id > 0) {
        try {
            // Check previous status for stock restoration
            $prev_stmt = $pdo->prepare("SELECT status FROM factory_sales WHERE id = ?");
            $prev_stmt->execute([$sale_id]);
            $prev_status = $prev_stmt->fetchColumn();

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE factory_sales SET status = ?, tracking_code = ?, invoice_number = ? WHERE id = ?");
            $stmt->execute([$status, $tracking_code, $invoice_number, $sale_id]);

            // If changing to canceled and was not canceled before, restore stock
            if ($status === 'canceled' && $prev_status !== 'canceled') {
                $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM factory_sale_items WHERE sale_id = ?");
                $items_stmt->execute([$sale_id]);
                $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

                $restore_stmt = $pdo->prepare("UPDATE factory_products SET stock_qty = stock_qty + ? WHERE id = ?");
                foreach ($items as $item) {
                    $restore_stmt->execute([$item['quantity'], $item['product_id']]);
                }
            } 
            // If changing from canceled to something else, deduct stock again
            elseif ($prev_status === 'canceled' && $status !== 'canceled') {
                $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM factory_sale_items WHERE sale_id = ?");
                $items_stmt->execute([$sale_id]);
                $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

                $deduct_stmt = $pdo->prepare("UPDATE factory_products SET stock_qty = stock_qty - ? WHERE id = ?");
                foreach ($items as $item) {
                    $deduct_stmt->execute([$item['quantity'], $item['product_id']]);
                }
            }

            $pdo->commit();
            $msg = "Pedido B2B #$sale_id atualizado com sucesso!";
        } catch(Exception $e) {
            $pdo->rollBack();
            $err = "Erro ao atualizar pedido: " . $e->getMessage();
        }
    }
}

// Fetch sales with client details
$filter_status = $_GET['status'] ?? '';
$sql = "SELECT s.*, c.name as client_name, c.phone as client_phone FROM factory_sales s JOIN factory_clients c ON s.client_id = c.id";
if (!empty($filter_status)) {
    $sql .= " WHERE s.status = " . $pdo->quote($filter_status);
}
$sql .= " ORDER BY s.id DESC";
$sales = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get detail modal items if requested
$view_items = [];
$view_order = null;
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    $stmt = $pdo->prepare("SELECT s.*, c.name as client_name, c.phone as client_phone, c.address, c.city, c.state, c.zipcode FROM factory_sales s JOIN factory_clients c ON s.client_id = c.id WHERE s.id = ?");
    $stmt->execute([$view_id]);
    $view_order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($view_order) {
        $stmt_items = $pdo->prepare("SELECT * FROM factory_sale_items WHERE sale_id = ?");
        $stmt_items->execute([$view_id]);
        $view_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h2><i class="fas fa-box-open" style="color:var(--primary);"></i> Vendas e Pedidos B2B (Fábrica)</h2>
    <div style="display:flex; gap:10px;">
        <a href="sales.php" class="btn btn-secondary btn-sm <?php echo empty($filter_status) ? 'btn-primary' : ''; ?>">Todos</a>
        <a href="?status=pending" class="btn btn-secondary btn-sm <?php echo $filter_status === 'pending' ? 'btn-primary' : ''; ?>">Pendentes</a>
        <a href="?status=paid" class="btn btn-secondary btn-sm <?php echo $filter_status === 'paid' ? 'btn-primary' : ''; ?>">Pagos</a>
        <a href="?status=shipped" class="btn btn-secondary btn-sm <?php echo $filter_status === 'shipped' ? 'btn-primary' : ''; ?>">Enviados</a>
        <a href="?status=canceled" class="btn btn-secondary btn-sm <?php echo $filter_status === 'canceled' ? 'btn-primary' : ''; ?>">Cancelados</a>
    </div>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: <?php echo $view_order ? '1fr 420px' : '1fr'; ?>; gap:2rem;">
    
    <!-- List -->
    <div>
        <div class="card">
            <h3>Histórico de Pedidos B2B</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Total (R$)</th>
                            <th>Faturamento</th>
                            <th>Logística</th>
                            <th>Nota Fiscal</th>
                            <th>Status</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($sales)): ?>
                            <tr><td colspan="9" style="text-align:center; color:var(--text-muted);">Nenhum pedido encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach($sales as $s): 
                                $status_badge = 'badge-warning';
                                $status_label = 'Pendente';
                                if($s['status'] === 'paid') { $status_badge = 'badge-success'; $status_label = 'Pago'; }
                                elseif($s['status'] === 'shipped') { $status_badge = 'badge-info'; $status_label = 'Enviado'; }
                                elseif($s['status'] === 'canceled') { $status_badge = 'badge-danger'; $status_label = 'Cancelado'; }
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $s['id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($s['client_name']); ?></strong>
                                        <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($s['client_phone']); ?></div>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></td>
                                    <td style="font-weight:bold; color:var(--primary);">R$ <?php echo number_format($s['total_amount'], 2, ',', '.'); ?></td>
                                    <td><span style="text-transform:uppercase; font-size:0.8rem; font-weight:bold;"><?php echo htmlspecialchars($s['payment_method']); ?></span></td>
                                    <td>
                                        <span style="font-size:0.8rem; font-weight:bold; color:var(--text-muted);"><?php echo htmlspecialchars($s['shipping_method'] ?: 'Retirar'); ?></span>
                                        <?php if(!empty($s['tracking_code'])): ?>
                                            <div style="font-size:0.7rem; font-family:monospace; color:var(--blue);"><?php echo htmlspecialchars($s['tracking_code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($s['invoice_number']) ? '🧾 NF: ' . htmlspecialchars($s['invoice_number']) : '<span style="color:var(--text-muted);">Pendente</span>'; ?>
                                    </td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                                    <td style="text-align:center;">
                                        <a href="?view=<?php echo $s['id']; ?><?php echo !empty($filter_status) ? '&status=' . $filter_status : ''; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> Detalhes</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Details Sidebar -->
    <?php if($view_order): ?>
    <div>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:10px; margin-bottom:1.5rem;">
                <h3>Pedido #<?php echo $view_order['id']; ?></h3>
                <a href="sales.php<?php echo !empty($filter_status) ? '?status=' . $filter_status : ''; ?>" style="color:var(--text-muted);"><i class="fas fa-times"></i></a>
            </div>

            <!-- Client Info -->
            <div style="margin-bottom:1.5rem;">
                <h4 style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:8px;">Destinatário B2B</h4>
                <div style="font-weight:bold; font-size:1.05rem;"><?php echo htmlspecialchars($view_order['client_name']); ?></div>
                <div style="font-size:0.85rem; color:var(--text-muted); margin-top:5px;">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($view_order['client_phone']); ?><br>
                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($view_order['address']); ?>, <?php echo htmlspecialchars($view_order['city']); ?> - <?php echo htmlspecialchars($view_order['state']); ?>
                </div>
            </div>

            <!-- Products List -->
            <div style="margin-bottom:1.5rem;">
                <h4 style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:8px;">Itens Faturados</h4>
                <div style="background:#080b10; border:1px solid var(--border); border-radius:8px; padding:10px;">
                    <?php foreach($view_items as $item): ?>
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; padding:6px 0; border-bottom:1px solid rgba(255,255,255,0.03);">
                            <div style="flex:1;">
                                <span style="font-weight:bold;"><?php echo $item['quantity']; ?>x</span> <?php echo htmlspecialchars($item['product_name']); ?>
                            </div>
                            <div style="font-weight:bold; color:var(--primary); margin-left:15px;">
                                R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-top:10px; color:var(--text-muted);">
                        Frete: <span>R$ <?php echo number_format($view_order['shipping_cost'], 2, ',', '.'); ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:var(--danger);">
                        Desconto: <span>- R$ <?php echo number_format($view_order['discount_amount'], 2, ',', '.'); ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:1.1rem; font-weight:800; color:var(--primary); margin-top:8px; border-top:1px dashed var(--border); padding-top:8px;">
                        Total Geral: <span>R$ <?php echo number_format($view_order['total_amount'], 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <form method="POST" style="border-top:1px solid var(--border); padding-top:1.5rem;">
                <input type="hidden" name="action" value="update_order">
                <input type="hidden" name="sale_id" value="<?php echo $view_order['id']; ?>">

                <h4 style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:1rem;">Atualizar Status & Envio</h4>
                
                <div class="form-group">
                    <label>Status do Pedido</label>
                    <select name="status" class="form-control">
                        <option value="pending" <?php echo $view_order['status'] === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="paid" <?php echo $view_order['status'] === 'paid' ? 'selected' : ''; ?>>Pago (Faturado)</option>
                        <option value="shipped" <?php echo $view_order['status'] === 'shipped' ? 'selected' : ''; ?>>Enviado</option>
                        <option value="canceled" <?php echo $view_order['status'] === 'canceled' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Número da Nota Fiscal (NF-e)</label>
                    <input type="text" name="invoice_number" class="form-control" value="<?php echo htmlspecialchars($view_order['invoice_number'] ?? ''); ?>" placeholder="Digite a numeração da nota...">
                </div>

                <div class="form-group">
                    <label>Código de Rastreio / Detalhe Transportadora</label>
                    <input type="text" name="tracking_code" class="form-control" value="<?php echo htmlspecialchars($view_order['tracking_code'] ?? ''); ?>" placeholder="Código de envio Correios / ME / Lalamove">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Salvar Atualizações</button>
            </form>

            <!-- Quick logistics link integration -->
            <?php if($view_order['shipping_method'] === 'lalamove'): ?>
                <a href="../admin/lalamove.php" target="_blank" class="btn btn-secondary" style="width:100%; background:#FF6600; color:#fff; font-weight:bold; margin-top:10px; display:inline-flex; align-items:center; justify-content:center; gap:8px;"><i class="fas fa-shipping-fast"></i> Cotar / Agendar no Lalamove</a>
            <?php elseif($view_order['shipping_method'] === 'melhorenvio'): ?>
                <a href="../admin/melhorenvio.php" target="_blank" class="btn btn-secondary" style="width:100%; background:#e74c3c; color:#fff; font-weight:bold; margin-top:10px; display:inline-flex; align-items:center; justify-content:center; gap:8px;"><i class="fas fa-mail-bulk"></i> Gerar Etiqueta Melhor Envio</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
