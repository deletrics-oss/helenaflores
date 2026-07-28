<?php
// catalogo/fabrica/employees.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Add / Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $cost_per_hour = floatval($_POST['cost_per_hour'] ?? 0);

    if ($action === 'save') {
        if (!empty($name)) {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE factory_employees SET name = ?, role = ?, status = ?, cost_per_hour = ? WHERE id = ?");
                $stmt->execute([$name, $role, $status, $cost_per_hour, $id]);
                $msg = 'Funcionário atualizado com sucesso!';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO factory_employees (name, role, status, cost_per_hour) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $role, $status, $cost_per_hour]);
                $msg = 'Funcionário contratado/cadastrado!';
            }
        } else {
            $err = 'O nome do funcionário é obrigatório.';
        }
    }
}

// Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_employees WHERE id = ?");
    $stmt->execute([$id]);
    $msg = 'Funcionário removido do cadastro!';
}

// Get editing item
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM factory_employees WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all employees
$employees = $pdo->query("SELECT * FROM factory_employees ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h2><i class="fas fa-users-cog" style="color:var(--primary);"></i> Controle de Equipe / Funcionários</h2>
    <?php if(!$edit_item): ?>
    <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Funcionário</a>
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
            <h3>Quadro de Funcionários</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Colaborador</th>
                            <th>Cargo / Função</th>
                            <th>Status</th>
                            <th>Custo por Hora (R$)</th>
                            <th>Contratado Em</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($employees)): ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">Nenhum colaborador cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach($employees as $e): 
                                $status_badge = $e['status'] === 'active' ? 'badge-success' : 'badge-danger';
                                $status_label = $e['status'] === 'active' ? 'Ativo' : 'Desligado';
                            ?>
                                <tr>
                                    <td>#<?php echo $e['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($e['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($e['role'] ?: 'Operacional'); ?></td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                                    <td style="font-weight:bold;">R$ <?php echo number_format($e['cost_per_hour'], 2, ',', '.'); ?>/h</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($e['created_at'])); ?></td>
                                    <td style="text-align:center;">
                                        <a href="?edit=<?php echo $e['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $e['id']; ?>" onclick="return confirm('Deseja realmente excluir este funcionário?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
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
            <h3><?php echo $edit_item ? 'Editar Colaborador' : 'Novo Colaborador'; ?></h3>
            <form method="POST" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">

                <div class="form-group">
                    <label>Nome do Colaborador</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="Nome completo...">
                </div>

                <div class="form-group">
                    <label>Cargo / Função</label>
                    <input type="text" name="role" class="form-control" value="<?php echo htmlspecialchars($edit_item['role'] ?? ''); ?>" placeholder="Ex: Montador, Pintor, Gerente...">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($edit_item && $edit_item['status'] === 'active') ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inactive" <?php echo ($edit_item && $edit_item['status'] === 'inactive') ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Custo por Hora (R$)</label>
                    <input type="number" step="0.01" name="cost_per_hour" class="form-control" value="<?php echo $edit_item['cost_per_hour'] ?? '0.00'; ?>" placeholder="Ex: 15.50">
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Salvar</button>
                    <a href="employees.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
