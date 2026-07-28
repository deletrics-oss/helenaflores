<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';

checkUser(); // Ensure user is logged 
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Load User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $document = trim($_POST['document']);

    // Address fields
    $zip = $_POST['zipcode'] ?? '';
    $addr = $_POST['address'] ?? '';
    $num = $_POST['number'] ?? '';
    $bairro = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $uf = $_POST['state'] ?? '';

    // Handle Password Change
    $pass = $_POST['password'] ?? '';
    $pass_confirm = $_POST['password_confirm'] ?? '';

    try {
        if ($name && $document && $phone) {
            $sql = "UPDATE users SET name=?, document=?, phone=?, zipcode=?, address=?, number=?, neighborhood=?, city=?, state=? WHERE id=?";
            $pdo->prepare($sql)->execute([$name, $document, $phone, $zip, $addr, $num, $bairro, $city, $uf, $user_id]);

            if (!empty($pass)) {
                if ($pass === $pass_confirm) {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user_id]);
                    $success = "Perfil e senha atualizados com sucesso!";
                } else {
                    $error = "As senhas não coincidem.";
                }
            } else {
                $success = "Perfil atualizado com sucesso!";
            }

            // Refresh local data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } else {
            $error = "Preencha os campos obrigatórios.";
        }
    } catch (Exception $e) {
        $error = "Erro ao atualizar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Conta | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=1.3">
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
        <div
            style="max-width: 800px; margin: 0 auto; background: var(--bg-card); padding: 2rem; border-radius: 12px; border: 1px solid var(--border);">
            <h1 style="color: var(--primary); margin-bottom: 2rem; text-align: center;">👤 Meu Perfil</h1>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom:1.5rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom:1.5rem;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom: 2rem;">
                    <div>
                        <label>Nome Completo / Razão Social</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div>
                        <label>CPF / CNPJ</label>
                        <input type="text" name="document" value="<?php echo htmlspecialchars($user['document']); ?>"
                            required>
                    </div>
                    <div>
                        <label>WhatsApp</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                            required>
                    </div>
                    <div>
                        <label>Email (Não alterável)</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled
                            style="opacity:0.6;">
                    </div>
                </div>

                <div
                    style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #333; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                    <h3 style="color: var(--primary); font-size: 1.1rem; margin:0;">🏠 Endereço de Entrega</h3>
                    <a href="my-addresses.php" class="btn-sm"
                        style="background:var(--bg-card); border:1px solid var(--primary); color:var(--primary);">Gerenciar
                        Múltiplos Endereços</a>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 2fr 1fr; gap:1rem; margin-bottom: 1rem;">
                    <div>
                        <label>CEP</label>
                        <input type="text" name="zipcode" value="<?php echo htmlspecialchars($user['zipcode']); ?>">
                    </div>
                    <div>
                        <label>Rua</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>">
                    </div>
                    <div>
                        <label>Número</label>
                        <input type="text" name="number" value="<?php echo htmlspecialchars($user['number']); ?>">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem; margin-bottom: 2rem;">
                    <div>
                        <label>Bairro</label>
                        <input type="text" name="neighborhood"
                            value="<?php echo htmlspecialchars($user['neighborhood']); ?>">
                    </div>
                    <div>
                        <label>Cidade</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($user['city']); ?>">
                    </div>
                    <div>
                        <label>UF</label>
                        <input type="text" name="state" value="<?php echo htmlspecialchars($user['state']); ?>"
                            maxlength="2">
                    </div>
                </div>

                <h3
                    style="color: var(--primary); font-size: 1.1rem; border-bottom: 1px solid #333; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                    🔒 Alterar Senha <small style="color:#666; font-weight:normal;">(Deixe em branco para não
                        alterar)</small></h3>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom: 2rem;">
                    <div>
                        <label>Nova Senha</label>
                        <input type="password" name="password">
                    </div>
                    <div>
                        <label>Confirmar Nova Senha</label>
                        <input type="password" name="password_confirm">
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="btn" style="padding: 1rem 3rem;">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>

</html>