<?php
// catalogo/fabrica/machines.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Add / Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $cost_per_hour = floatval($_POST['cost_per_hour'] ?? 0);

    if ($action === 'save') {
        if (!empty($name)) {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE factory_machines SET name = ?, status = ?, cost_per_hour = ? WHERE id = ?");
                $stmt->execute([$name, $status, $cost_per_hour, $id]);
                $msg = 'Máquina atualizada com sucesso!';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO factory_machines (name, status, cost_per_hour) VALUES (?, ?, ?)");
                $stmt->execute([$name, $status, $cost_per_hour]);
                $msg = 'Máquina cadastrada com sucesso!';
            }
        } else {
            $err = 'O nome da máquina é obrigatório.';
        }
    }
}

// Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_machines WHERE id = ?");
    $stmt->execute([$id]);
    $msg = 'Máquina excluída com sucesso!';
}

// Get editing item
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM factory_machines WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all machines
$machines = $pdo->query("SELECT * FROM factory_machines ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h2><i class="fas fa-microchip" style="color:var(--primary);"></i> Controle de Máquinas / Equipamentos</h2>
    <?php if(!$edit_item): ?>
    <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Máquina</a>
    <?php endif; ?>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: <?php echo (isset($_GET['add']) || $edit_item) ? '1fr 350px' : '1fr'; ?>; gap:2rem;">
    
    <!-- List Column -->
    <div>
        <div class="card">
            <h3>Equipamentos Cadastrados</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Equipamento</th>
                            <th>Status</th>
                            <th>Custo por Hora (R$)</th>
                            <th>Cadastrado Em</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($machines)): ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Nenhum equipamento cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach($machines as $m): 
                                $status_badge = 'badge-success';
                                $status_label = 'Ativo';
                                if($m['status'] === 'maintenance') {
                                    $status_badge = 'badge-warning';
                                    $status_label = 'Manutenção';
                                } elseif($m['status'] === 'inactive') {
                                    $status_badge = 'badge-danger';
                                    $status_label = 'Inativo';
                                }
                            ?>
                                <tr>
                                    <td>#<?php echo $m['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                                    <td style="font-weight:bold;">R$ <?php echo number_format($m['cost_per_hour'], 2, ',', '.'); ?>/h</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                    <td style="text-align:center;">
                                        <a href="?edit=<?php echo $m['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $m['id']; ?>" onclick="return confirm('Deseja realmente excluir esta máquina?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form Column -->
    <?php if(isset($_GET['add']) || $edit_item): ?>
    <div>
        <div class="card">
            <h3><?php echo $edit_item ? 'Editar Equipamento' : 'Novo Equipamento'; ?></h3>
            <form method="POST" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">

                <div class="form-group">
                    <label>Nome do Equipamento</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="Ex: Impressora 3D, Router CNC...">
                </div>

                <div class="form-group">
                    <label>Status Operacional</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($edit_item && $edit_item['status'] === 'active') ? 'selected' : ''; ?>>Ativo / Operando</option>
                        <option value="maintenance" <?php echo ($edit_item && $edit_item['status'] === 'maintenance') ? 'selected' : ''; ?>>Manutenção</option>
                        <option value="inactive" <?php echo ($edit_item && $edit_item['status'] === 'inactive') ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Custo Estimado por Hora (R$)</label>
                    <input type="number" step="0.01" name="cost_per_hour" class="form-control" value="<?php echo $edit_item['cost_per_hour'] ?? '0.00'; ?>" placeholder="Custo elétrico/depreciação por hora">
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Salvar</button>
                    <a href="machines.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
