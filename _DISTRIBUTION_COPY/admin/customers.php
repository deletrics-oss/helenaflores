<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// HANDLE BULK DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $selected_ids = $_POST['selected_ids'] ?? [];
    if (!empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        // Delete Users (also cascades to orders/payments usually if foreign keys set, but we forced delete)
        $pdo->query("DELETE FROM users WHERE id IN ($ids) AND role != 'admin'");
        echo "<div class='alert alert-success'>🗑️ Clientes selecionados foram excluídos!</div>";
    }
}

// Get Users with financial stats
$sql = "
    SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
    (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = u.id AND status = 'paid') as total_spent,
    (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = u.id AND status = 'pending') as current_debt
    FROM users u
    WHERE u.role != 'admin' AND (u.is_lead = 0 OR u.is_lead IS NULL)
    ORDER BY current_debt DESC, total_spent DESC
";
$users = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Clientes | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByName('selected_ids[]');
            for (var i = 0, n = checkboxes.length; i < n; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <h1>Financeiro Clientes</h1>
            <a href="customer-create.php" class="btn">Novo Cliente</a>
        </div>

        <form method="POST"
            onsubmit="return confirm('Tem certeza que deseja excluir os clientes selecionados? Isso apagará também o histórico financeiro deles!');">
            <div style="margin-bottom:1rem; text-align:right;">
                <button type="submit" name="bulk_delete" class="btn btn-danger">🗑️ Excluir Selecionados</button>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th>Nome / Empresa</th>
                            <th>CNPJ / CPF</th>
                            <th>Contato</th>
                            <th>Pedidos</th>
                            <th>Total Gasto (Pago)</th>
                            <th>Em Aberto (Dívida)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?php echo $u['id']; ?>"></td>
                                <td>
                                    <a href="customer-details.php?id=<?php echo $u['id']; ?>"
                                        style="color:var(--primary); font-weight:bold; text-decoration:underline;">
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($u['document']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($u['phone']); ?>
                                </td>
                                <td>
                                    <?php echo $u['total_orders']; ?>
                                </td>
                                <td style="color:var(--success)">R$
                                    <?php echo number_format($u['total_spent'], 2, ',', '.'); ?>
                                </td>
                                <td
                                    style="<?php echo $u['current_debt'] > 0 ? 'color:var(--danger); font-weight:bold;' : 'color:var(--text-muted)'; ?>">
                                    R$
                                    <?php echo number_format($u['current_debt'], 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

</body>

</html>