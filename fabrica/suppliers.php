<?php
// catalogo/fabrica/suppliers.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Add / Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $contact_info = trim($_POST['contact_info'] ?? '');

    if ($action === 'save') {
        if (!empty($name)) {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE factory_suppliers SET name = ?, cnpj = ?, contact_info = ? WHERE id = ?");
                $stmt->execute([$name, $cnpj, $contact_info, $id]);
                $msg = 'Fornecedor atualizado com sucesso!';
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO factory_suppliers (name, cnpj, contact_info) VALUES (?, ?, ?)");
                $stmt->execute([$name, $cnpj, $contact_info]);
                $msg = 'Fornecedor adicionado com sucesso!';
            }
        } else {
            $err = 'O nome do fornecedor é obrigatório.';
        }
    }
}

// Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $msg = 'Fornecedor removido com sucesso!';
}

// Get editing item
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM factory_suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all suppliers
$suppliers = $pdo->query("SELECT * FROM factory_suppliers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h2><i class="fas fa-truck-loading" style="color:var(--primary);"></i> Gestão de Fornecedores (Fábrica)</h2>
    <?php if(!$edit_item): ?>
    <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Fornecedor</a>
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
            <h3>Lista de Fornecedores</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome / Razão Social</th>
                            <th>CNPJ</th>
                            <th>Informações de Contato</th>
                            <th>Cadastrado Em</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($suppliers)): ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Nenhum fornecedor cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach($suppliers as $s): ?>
                                <tr>
                                    <td>#<?php echo $s['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['cnpj'] ?: '-'); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($s['contact_info'] ?: '-')); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></td>
                                    <td style="text-align:center;">
                                        <a href="?edit=<?php echo $s['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $s['id']; ?>" onclick="return confirm('Deseja realmente excluir este fornecedor?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
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
            <h3><?php echo $edit_item ? 'Editar Fornecedor' : 'Novo Fornecedor'; ?></h3>
            <form method="POST" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">

                <div class="form-group">
                    <label>Nome / Razão Social</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>CNPJ</label>
                    <input type="text" name="cnpj" class="form-control" value="<?php echo htmlspecialchars($edit_item['cnpj'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Informações de Contato / Observações</label>
                    <textarea name="contact_info" class="form-control" rows="5" placeholder="Telefone, E-mail, Vendedor, Endereço..."><?php echo htmlspecialchars($edit_item['contact_info'] ?? ''); ?></textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Salvar</button>
                    <a href="suppliers.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
