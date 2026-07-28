<?php
// catalogo/fabrica/vehicles.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Add / Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $plate = trim($_POST['plate'] ?? '');
    $driver = trim($_POST['driver'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($action === 'save') {
        if (!empty($name)) {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE factory_vehicles SET name = ?, plate = ?, driver = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $plate, $driver, $status, $id]);
                $msg = 'Veículo atualizado com sucesso!';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO factory_vehicles (name, plate, driver, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $plate, $driver, $status]);
                $msg = 'Veículo adicionado à frota!';
            }
        } else {
            $err = 'O nome/modelo do veículo é obrigatório.';
        }
    }
}

// Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_vehicles WHERE id = ?");
    $stmt->execute([$id]);
    $msg = 'Veículo removido da frota.';
}

// Get editing item
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM factory_vehicles WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all vehicles
$vehicles = $pdo->query("SELECT * FROM factory_vehicles ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h2><i class="fas fa-truck" style="color:var(--primary);"></i> Frota de Veículos (Logística da Fábrica)</h2>
    <?php if(!$edit_item): ?>
    <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Veículo</a>
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
            <h3>Veículos Cadastrados</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Veículo / Modelo</th>
                            <th>Placa</th>
                            <th>Motorista Padrão</th>
                            <th>Status</th>
                            <th>Cadastrado Em</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($vehicles)): ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">Nenhum veículo cadastrado na frota.</td></tr>
                        <?php else: ?>
                            <?php foreach($vehicles as $v): 
                                $status_badge = 'badge-success';
                                $status_label = 'Operando';
                                if($v['status'] === 'maintenance') {
                                    $status_badge = 'badge-warning';
                                    $status_label = 'Manutenção';
                                } elseif($v['status'] === 'inactive') {
                                    $status_badge = 'badge-danger';
                                    $status_label = 'Indisponível';
                                }
                            ?>
                                <tr>
                                    <td>#<?php echo $v['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($v['name']); ?></strong></td>
                                    <td><span style="font-family:monospace; font-weight:bold;"><?php echo htmlspecialchars($v['plate'] ?: '-'); ?></span></td>
                                    <td><?php echo htmlspecialchars($v['driver'] ?: '-'); ?></td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($v['created_at'])); ?></td>
                                    <td style="text-align:center;">
                                        <a href="?edit=<?php echo $v['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $v['id']; ?>" onclick="return confirm('Excluir este veículo?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
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
            <h3><?php echo $edit_item ? 'Editar Veículo' : 'Novo Veículo'; ?></h3>
            <form method="POST" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">

                <div class="form-group">
                    <label>Modelo / Nome do Veículo</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="Ex: Fiorino Branca, Moby Cargo...">
                </div>

                <div class="form-group">
                    <label>Placa</label>
                    <input type="text" name="plate" class="form-control" value="<?php echo htmlspecialchars($edit_item['plate'] ?? ''); ?>" placeholder="Ex: ABC-1234">
                </div>

                <div class="form-group">
                    <label>Motorista Padrão</label>
                    <input type="text" name="driver" class="form-control" value="<?php echo htmlspecialchars($edit_item['driver'] ?? ''); ?>" placeholder="Nome do motorista principal...">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($edit_item && $edit_item['status'] === 'active') ? 'selected' : ''; ?>>Operando / Disponível</option>
                        <option value="maintenance" <?php echo ($edit_item && $edit_item['status'] === 'maintenance') ? 'selected' : ''; ?>>Manutenção</option>
                        <option value="inactive" <?php echo ($edit_item && $edit_item['status'] === 'inactive') ? 'selected' : ''; ?>>Indisponível</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Salvar</button>
                    <a href="vehicles.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
