<?php
// catalogo/admin/manage-admins.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$msg = '';

// 1. ADD NEW ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // Check email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $msg = '<div class="alert alert-error">E-mail já cadastrado!</div>';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $role = $_POST['role'];
        if (!in_array($role, ['admin', 'factory']))
            $role = 'admin'; // Safety fallback

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt->execute([$name, $email, $hash, $role])) {
            $msg = '<div class="alert alert-success">✅ Usuário criado com sucesso!</div>';
        } else {
            $msg = '<div class="alert alert-error">Erro ao criar administrador.</div>';
        }
    }
}

// 2. DELETE ADMIN
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Prevent self-delete
    if ($id == $_SESSION['user_id']) {
        $msg = '<div class="alert alert-error">❌ Você não pode se excluir!</div>';
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$id]);
        $msg = '<div class="alert alert-success">🗑️ Administrador removido.</div>';
    }
}

// 3. LIST ADMINS
$admins = $pdo->query("SELECT * FROM users WHERE role IN ('admin', 'factory', 'manager') ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Admins | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <h1>🛡️ Gerenciar Administradores</h1>
            <a href="dashboard.php" class="btn btn-secondary">Voltar</a>
        </div>

        <?php echo $msg; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem;">

            <!-- LIST -->
            <div>
                <div class="auth-box" style="margin:0; width:100%; max-width:100%;">
                    <h2>Admins Atuais</h2>
                    <table style="width:100%; margin-top:1rem;">
                        <thead>
                            <tr>
                                <th style="text-align:left;">Nome</th>
                                <th style="text-align:left;">Email</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $a): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($a['name']); ?>
                                        <?php if ($a['id'] == $_SESSION['user_id'])
                                            echo '(Você)'; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($a['email']); ?>
                                        <span
                                            style="font-size:0.8rem; background:#333; padding:2px 5px; border-radius:4px; margin-left:5px;">
                                            <?php echo strtoupper($a['role']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($a['id'] != $_SESSION['user_id']): ?>
                                            <a href="?delete=<?php echo $a['id']; ?>" class="btn-sm btn-danger"
                                                onclick="return confirm('Tem certeza?')">Remover</a>
                                        <?php else: ?>
                                            <span style="opacity:0.5;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CREATE -->
            <div>
                <div class="auth-box" style="margin:0; width:100%; max-width:100%; border:1px solid var(--primary);">
                    <h2>Novo Admin</h2>
                    <form method="POST">
                        <label>Nome</label>
                        <input type="text" name="name" required placeholder="Nome do Responsável">

                        <label>Email de Login</label>
                        <input type="email" name="email" required placeholder="fabrica@fightarcade.com.br">

                        <label>Senha</label>
                        <input type="password" name="password" required placeholder="Senha forte">

                        <label>Permissão</label>
                        <select name="role" required>
                            <option value="admin">Administrador Completo (Acesso Total)</option>
                            <option value="manager">Gerente de Loja (Produtos, VIPs, Pedidos)</option>
                            <?php if (file_exists(__DIR__ . '/../fabrica/dashboard.php')): ?>
                                <option value="factory">Gerente de Fábrica (Só Produção)</option>
                            <?php endif; ?>
                        </select>

                        <button type="submit" name="create_admin" value="1" class="btn"
                            style="width:100%; margin-top:1rem;">Criar Usuário</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</body>

</html>