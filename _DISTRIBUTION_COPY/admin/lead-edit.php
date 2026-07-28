<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$id = $_GET['id'] ?? 0;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $company = $_POST['company_name'];
    $doc = $_POST['document'];

    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, company_name = ?, document = ? WHERE id = ?");
    if ($stmt->execute([$name, $email, $phone, $company, $doc, $id])) {
        $msg = '<div class="alert success">✅ Dados atualizados com sucesso!</div>';
    } else {
        $msg = '<div class="alert error">Erro ao atualizar.</div>';
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user)
    die("Usuário não encontrado.");
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Editar Lead | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem; max-width:600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h1>✏️ Editar Lead</h1>
            <a href="leads.php" class="btn btn-secondary">Voltar</a>
        </div>

        <?php echo $msg; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:5px;">Nome Completo</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>"
                        style="width:100%; padding:10px; border-radius:5px; border:1px solid #444; background:#222; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:5px;">Empresa</label>
                    <input type="text" name="company_name"
                        value="<?php echo htmlspecialchars($user['company_name']); ?>"
                        style="width:100%; padding:10px; border-radius:5px; border:1px solid #444; background:#222; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:5px;">E-mail</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                        style="width:100%; padding:10px; border-radius:5px; border:1px solid #444; background:#222; color:#fff;">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label style="display:block; margin-bottom:5px;">Telefone / WhatsApp</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                            style="width:100%; padding:10px; border-radius:5px; border:1px solid #444; background:#222; color:#fff;">
                    </div>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label style="display:block; margin-bottom:5px;">CPF / CNPJ</label>
                        <input type="text" name="document" value="<?php echo htmlspecialchars($user['document']); ?>"
                            style="width:100%; padding:10px; border-radius:5px; border:1px solid #444; background:#222; color:#fff;">
                    </div>
                </div>

                <button type="submit" class="btn" style="width:100%; margin-top:1rem;">💾 Salvar Alterações</button>
            </form>
        </div>
    </div>
</body>

</html>