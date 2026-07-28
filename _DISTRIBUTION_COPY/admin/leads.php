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
        $pdo->query("DELETE FROM users WHERE id IN ($ids) AND role != 'admin'");
        echo "<div class='alert alert-success'>🗑️ Leads selecionados foram excluídos!</div>";
    }
}

// Get Leads
$sql = "
    SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
    (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_interaction
    FROM users u
    WHERE u.role != 'admin' AND u.is_lead = 1
    ORDER BY u.id DESC
";
$leads = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Leads (Acessos) | Admin</title>
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
            <h1>📢 Leads (Interessados)</h1>
            <p style="color:var(--text-muted);">Usuários que entraram pelo Gatekeeper mas ainda não completaram o cadastro.</p>
        </div>

        <form method="POST" onsubmit="return confirm('Excluir os leads selecionados?');">
            <div style="margin-bottom:1rem; text-align:right;">
                <button type="submit" name="bulk_delete" class="btn btn-danger">🗑️ Excluir Selecionados</button>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th>ID</th>
                            <th>Nome / Nick</th>
                            <th>Whats / Celular</th>
                            <th>Origem</th>
                            <th>Data de Acesso</th>
                            <th>Conversão</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:2rem;">Nenhum lead encontrado ainda.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($leads as $l): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?php echo $l['id']; ?>"></td>
                                <td>#<?php echo $l['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($l['name']); ?></strong></td>
                                <td>
                                    <a href="https://api.whatsapp.com/send?phone=<?php echo preg_replace('/\D/','',$l['phone']); ?>" target="_blank" style="color:var(--success)">
                                        <?php echo htmlspecialchars($l['phone']); ?> 🟢
                                    </a>
                                </td>
                                <td><small><?php echo htmlspecialchars($l['source'] ?? 'Site'); ?></small></td>
                                <td><?php echo date('d/m/Y', strtotime($l['created_at'])); ?></td>
                                <td>
                                    <?php if ($l['total_orders'] > 0): ?>
                                        <span class="badge" style="background:var(--success); color:#000; padding:2px 6px; border-radius:4px; font-size:0.7rem;">COMPROU</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#444; color:#fff; padding:2px 6px; border-radius:4px; font-size:0.7rem;">SÓ ACESSOU</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="lead-edit.php?id=<?php echo $l['id']; ?>" class="btn-sm" style="background:#333; color:#fff; padding:5px 8px; font-size:0.7rem;">Editar/Ver</a>
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