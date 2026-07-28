<?php
// catalogo/fabrica/cashbook.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Add Cash Entry Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_entry') {
    $type = trim($_POST['type'] ?? 'income');
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($amount > 0 && !empty($description)) {
        $stmt = $pdo->prepare("INSERT INTO factory_cashbook (type, amount, description) VALUES (?, ?, ?)");
        $stmt->execute([$type, $amount, $description]);
        $msg = 'Lançamento financeiro realizado com sucesso!';
    } else {
        $err = 'Valor e descrição são obrigatórios.';
    }
}

// Delete Entry Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_cashbook WHERE id = ?");
    $stmt->execute([$id]);
    $msg = 'Lançamento removido.';
}

// Fetch totals
$total_income = $pdo->query("SELECT SUM(amount) FROM factory_cashbook WHERE type = 'income'")->fetchColumn() ?: 0;
$total_expense = $pdo->query("SELECT SUM(amount) FROM factory_cashbook WHERE type = 'expense'")->fetchColumn() ?: 0;
$net_balance = $total_income - $total_expense;

// Fetch all entries
$entries = $pdo->query("SELECT * FROM factory_cashbook ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h2><i class="fas fa-wallet" style="color:var(--primary);"></i> Livro Caixa da Fábrica</h2>
    <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Lançamento</a>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
<?php endif; ?>

<!-- Financial KPI Cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <div class="card" style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Receitas Totais</div>
            <div style="font-size:1.8rem; font-weight:900; color:var(--primary); margin-top:5px;">R$ <?php echo number_format($total_income, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-arrow-circle-up" style="font-size:2.5rem; color:rgba(0, 230, 118, 0.2);"></i>
    </div>
    <div class="card" style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Despesas Totais</div>
            <div style="font-size:1.8rem; font-weight:900; color:#ff6b6b; margin-top:5px;">R$ <?php echo number_format($total_expense, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-arrow-circle-down" style="font-size:2.5rem; color:rgba(239, 68, 68, 0.2);"></i>
    </div>
    <div class="card" style="display:flex; align-items:center; justify-content:space-between; border-left:4px solid <?php echo $net_balance >= 0 ? 'var(--primary)' : 'var(--danger)'; ?>;">
        <div>
            <div style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Saldo Líquido (Lucro)</div>
            <div style="font-size:1.8rem; font-weight:900; color:<?php echo $net_balance >= 0 ? 'var(--primary)' : 'var(--danger)'; ?>; margin-top:5px;">R$ <?php echo number_format($net_balance, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-balance-scale" style="font-size:2.5rem; color:rgba(255,255,255,0.05);"></i>
    </div>
</div>

<div style="display:grid; grid-template-columns: <?php echo isset($_GET['add']) ? '1fr 350px' : '1fr'; ?>; gap:2rem;">
    
    <!-- Entries list -->
    <div>
        <div class="card">
            <h3>Fluxo de Caixa Recente</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data/Hora</th>
                            <th>Descrição do Lançamento</th>
                            <th>Tipo</th>
                            <th style="text-align:right;">Valor</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($entries)): ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Nenhum lançamento no caixa.</td></tr>
                        <?php else: ?>
                            <?php foreach($entries as $e): 
                                $type_badge = $e['type'] === 'income' ? 'badge-success' : 'badge-danger';
                                $type_label = $e['type'] === 'income' ? 'Entrada' : 'Saída';
                                $amount_color = $e['type'] === 'income' ? 'var(--primary)' : '#ff6b6b';
                                $amount_prefix = $e['type'] === 'income' ? '+' : '-';
                            ?>
                                <tr>
                                    <td>#<?php echo $e['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($e['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($e['description']); ?></strong></td>
                                    <td><span class="badge <?php echo $type_badge; ?>"><?php echo $type_label; ?></span></td>
                                    <td style="text-align:right; font-weight:bold; color:<?php echo $amount_color; ?>;">
                                        <?php echo $amount_prefix; ?> R$ <?php echo number_format($e['amount'], 2, ',', '.'); ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="?delete=<?php echo $e['id']; ?>" onclick="return confirm('Excluir este lançamento?')" style="color:var(--danger);"><i class="fas fa-trash"></i></a>
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
    <?php if(isset($_GET['add'])): ?>
    <div>
        <div class="card">
            <h3>Novo Lançamento</h3>
            <form method="POST" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save_entry">

                <div class="form-group">
                    <label>Tipo de Lançamento</label>
                    <select name="type" class="form-control">
                        <option value="income">Entrada (Receita B2B)</option>
                        <option value="expense">Saída (Despesa Insumo / Custos)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Valor (R$)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <input type="text" name="description" class="form-control" placeholder="Ex: Compra de botões atacado, Conta de Luz..." required>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Confirmar</button>
                    <a href="cashbook.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
