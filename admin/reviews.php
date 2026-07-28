<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/db.php';
isAdmin();

// Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($_GET['action'] == 'approve') {
        $pdo->query("UPDATE product_reviews SET approved = 1 WHERE id = $id");
    } elseif ($_GET['action'] == 'reject') {
        $pdo->query("UPDATE product_reviews SET approved = 0 WHERE id = $id");
    } elseif ($_GET['action'] == 'delete') {
        $pdo->query("DELETE FROM product_reviews WHERE id = $id");
    }
    header("Location: reviews.php");
    exit;
}

try {
    $reviews = $pdo->query("SELECT r.*, p.name as product_name FROM product_reviews r LEFT JOIN products p ON r.product_id = p.id ORDER BY r.created_at DESC")->fetchAll();
} catch (Exception $e) {
    // Table probably missing
    $error = "A tabela de avaliações não existe ou está com problemas.";
    $reviews = [];
}
?> <?php if (isset($error)): ?>
    <div style="background:red; color:white; padding:1rem; text-align:center; margin-top:50px;">
        <h3>⚠️ Erro no Banco de Dados</h3>
        <p><?php echo $error; ?></p>
        <a href="../update_db_full.php" class="btn" style="background:white; color:red; font-weight:bold;">🛠️ Corrigir
            Banco de Dados Agora</a>
    </div>
    <?php exit; endif; ?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Avaliações | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:1000px; margin:0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                <h2>⭐ Avaliações de Produtos</h2>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Cliente</th>
                        <th>Nota</th>
                        <th>Comentário</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td>
                                <?php echo date('d/m/y H:i', strtotime($r['created_at'])); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($r['product_name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($r['user_name']); ?>
                            </td>
                            <td><span style="color:gold;">
                                    <?php echo str_repeat('★', $r['rating']); ?>
                                </span></td>
                            <td><small>
                                    <?php echo htmlspecialchars(substr($r['comment'], 0, 50)); ?>...
                                </small></td>
                            <td>
                                <?php if ($r['approved']): ?>
                                    <span class="badge badge-success">Aprovado</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$r['approved']): ?>
                                    <a href="?action=approve&id=<?php echo $r['id']; ?>" class="btn-sm btn-primary">✅</a>
                                <?php else: ?>
                                    <a href="?action=reject&id=<?php echo $r['id']; ?>" class="btn-sm btn-secondary">🚫</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?php echo $r['id']; ?>" class="btn-sm btn-danger"
                                    onclick="return confirm('Excluir?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>