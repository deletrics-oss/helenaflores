<?php
// catalogo/fabrica/production.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Add / Edit Production Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_production') {
    $id = intval($_POST['id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $machine_id = !empty($_POST['machine_id']) ? intval($_POST['machine_id']) : null;
    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');

    if ($product_id > 0 && $quantity > 0) {
        try {
            // Fetch product's base total cost
            $prod_stmt = $pdo->prepare("SELECT name, total_cost FROM factory_products WHERE id = ?");
            $prod_stmt->execute([$product_id]);
            $product = $prod_stmt->fetch(PDO::FETCH_ASSOC);
            
            $base_cost = $product['total_cost'] ?? 0;
            $cost_applied = $base_cost * $quantity;

            // Fetch previous status if editing
            $prev_status = 'pending';
            if ($id > 0) {
                $prev_stmt = $pdo->prepare("SELECT status FROM factory_production_orders WHERE id = ?");
                $prev_stmt->execute([$id]);
                $prev_status = $prev_stmt->fetchColumn() ?: 'pending';
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE factory_production_orders SET product_id = ?, quantity = ?, employee_id = ?, machine_id = ?, status = ?, notes = ? WHERE id = ?");
                $stmt->execute([$product_id, $quantity, $employee_id, $machine_id, $status, $notes, $id]);
                
                // If status changed to completed, update stock and log cashbook expense
                if ($status === 'completed' && $prev_status !== 'completed') {
                    // Update stock
                    $stock_stmt = $pdo->prepare("UPDATE factory_products SET stock_qty = stock_qty + ? WHERE id = ?");
                    $stock_stmt->execute([$quantity, $product_id]);

                    // Add expense entry
                    $desc = "Custo de Produção OP #$id - Produto: {$product['name']} (x$quantity)";
                    $cash_stmt = $pdo->prepare("INSERT INTO factory_cashbook (type, amount, description) VALUES ('expense', ?, ?)");
                    $cash_stmt->execute([$cost_applied, $desc]);

                    // Set completed_at timestamp
                    $pdo->prepare("UPDATE factory_production_orders SET completed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
                }
                
                $msg = 'Ordem de Produção atualizada!';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO factory_production_orders (product_id, quantity, employee_id, machine_id, cost_applied, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $quantity, $employee_id, $machine_id, $cost_applied, $status, $notes]);
                $new_id = $pdo->lastInsertId();

                if ($status === 'completed') {
                    // Update stock
                    $stock_stmt = $pdo->prepare("UPDATE factory_products SET stock_qty = stock_qty + ? WHERE id = ?");
                    $stock_stmt->execute([$quantity, $product_id]);

                    // Add expense entry
                    $desc = "Custo de Produção OP #$new_id - Produto: {$product['name']} (x$quantity)";
                    $cash_stmt = $pdo->prepare("INSERT INTO factory_cashbook (type, amount, description) VALUES ('expense', ?, ?)");
                    $cash_stmt->execute([$cost_applied, $desc]);

                    // Set completed_at timestamp
                    $pdo->prepare("UPDATE factory_production_orders SET completed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$new_id]);
                }

                $msg = 'Ordem de Produção criada com sucesso!';
            }

            $pdo->commit();
        } catch(Exception $e) {
            $pdo->rollBack();
            $err = "Erro ao processar: " . $e->getMessage();
        }
    } else {
        $err = 'Produto e quantidade são obrigatórios.';
    }
}

// Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_production_orders WHERE id = ?");
    $stmt->execute([$id]);
    $msg = 'Ordem de Produção excluída.';
}

// Get editing item
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM factory_production_orders WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all production orders
$production_orders = $pdo->query("
    SELECT po.*, p.name as product_name, e.name as employee_name, m.name as machine_name 
    FROM factory_production_orders po
    JOIN factory_products p ON po.product_id = p.id
    LEFT JOIN factory_employees e ON po.employee_id = e.id
    LEFT JOIN factory_machines m ON po.machine_id = m.id
    ORDER BY po.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch products, employees, machines for dropdowns
$products = $pdo->query("SELECT id, name FROM factory_products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT id, name FROM factory_employees WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$machines = $pdo->query("SELECT id, name FROM factory_machines WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h2><i class="fas fa-tools" style="color:var(--primary);"></i> Ordens de Produção (OP)</h2>
    <?php if(!$edit_item): ?>
    <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Ordem de Produção</a>
    <?php endif; ?>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: <?php echo (isset($_GET['add']) || $edit_item) ? '1fr 350px' : '1fr'; ?>; gap:2rem;">
    
    <!-- List -->
    <div>
        <div class="card">
            <h3>Fila de Produção</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>OP</th>
                            <th>Produto</th>
                            <th>Qtd</th>
                            <th>Responsável</th>
                            <th>Maquinário</th>
                            <th>Custo Produção</th>
                            <th>Criado Em</th>
                            <th>Concluído Em</th>
                            <th>Status</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($production_orders)): ?>
                            <tr><td colspan="10" style="text-align:center; color:var(--text-muted);">Nenhuma ordem de produção na fila.</td></tr>
                        <?php else: ?>
                            <?php foreach($production_orders as $po): 
                                $status_badge = 'badge-warning';
                                $status_label = 'Pendente';
                                if($po['status'] === 'in_production') { $status_badge = 'badge-info'; $status_label = 'Produzindo'; }
                                elseif($po['status'] === 'completed') { $status_badge = 'badge-success'; $status_label = 'Concluído'; }
                                elseif($po['status'] === 'canceled') { $status_badge = 'badge-danger'; $status_label = 'Cancelado'; }
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $po['id']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($po['product_name']); ?></strong></td>
                                    <td><strong><?php echo $po['quantity']; ?> un</strong></td>
                                    <td><?php echo htmlspecialchars($po['employee_name'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($po['machine_name'] ?: '-'); ?></td>
                                    <td style="font-weight:bold; color:#ff6b6b;">R$ <?php echo number_format($po['cost_applied'], 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($po['created_at'])); ?></td>
                                    <td><?php echo $po['completed_at'] ? date('d/m/Y H:i', strtotime($po['completed_at'])) : '-'; ?></td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                                    <td style="text-align:center;">
                                        <a href="?edit=<?php echo $po['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $po['id']; ?>" onclick="return confirm('Excluir ordem de produção?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form -->
    <?php if(isset($_GET['add']) || $edit_item): ?>
    <div>
        <div class="card">
            <h3><?php echo $edit_item ? 'Editar OP' : 'Nova OP'; ?></h3>
            <form method="POST" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save_production">
                <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">

                <div class="form-group">
                    <label>Produto a Fabricar</label>
                    <select name="product_id" class="form-control" required>
                        <option value="">Selecione o Produto...</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($edit_item && $edit_item['product_id'] == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantidade de Peças</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="<?php echo $edit_item['quantity'] ?? '1'; ?>" required>
                </div>

                <div class="form-group">
                    <label>Funcionário Responsável</label>
                    <select name="employee_id" class="form-control">
                        <option value="">Nenhum...</option>
                        <?php foreach($employees as $e): ?>
                            <option value="<?php echo $e['id']; ?>" <?php echo ($edit_item && $edit_item['employee_id'] == $e['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Maquinário Utilizado</label>
                    <select name="machine_id" class="form-control">
                        <option value="">Nenhum...</option>
                        <?php foreach($machines as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo ($edit_item && $edit_item['machine_id'] == $m['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fase de Produção</label>
                    <select name="status" class="form-control">
                        <option value="pending" <?php echo ($edit_item && $edit_item['status'] === 'pending') ? 'selected' : ''; ?>>Pendente</option>
                        <option value="in_production" <?php echo ($edit_item && $edit_item['status'] === 'in_production') ? 'selected' : ''; ?>>Em Produção</option>
                        <option value="completed" <?php echo ($edit_item && $edit_item['status'] === 'completed') ? 'selected' : ''; ?>>Concluído</option>
                        <option value="canceled" <?php echo ($edit_item && $edit_item['status'] === 'canceled') ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Observações / Roteiro da Produção</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($edit_item['notes'] ?? ''); ?></textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Salvar</button>
                    <a href="production.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
