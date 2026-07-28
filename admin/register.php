<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $document = trim($_POST['document']);
    $phone = trim($_POST['phone']);

    // Address
    $zip = $_POST['zipcode'] ?? '';
    $addr = $_POST['address'] ?? '';
    $num = $_POST['number'] ?? '';
    $bairro = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $uf = $_POST['state'] ?? '';

    if ($name && $email && $password && $document) {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'Este email já está cadastrado.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Updated Query with Address Fields
            $sql = "INSERT INTO users (name, email, password, document, phone, zipcode, address, number, neighborhood, city, state, role) 
                    VALUES (:name, :email, :pass, :doc, :phone, :zip, :addr, :num, :bairro, :city, :uf, 'customer')";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':pass' => $hash,
                    ':doc' => $document,
                    ':phone' => $phone,
                    ':zip' => $zip,
                    ':addr' => $addr,
                    ':num' => $num,
                    ':bairro' => $bairro,
                    ':city' => $city,
                    ':uf' => $uf
                ]);
                $success = 'Cadastro realizado com sucesso! Faça login.';
            } catch (PDOException $e) {
                // If column doesn't exist yet, this might fail, prompting user to run update_db_address
                $error = 'Erro ao cadastrar. Verifique se o banco foi atualizado.';
            }
        }
    } else {
        $error = 'Preencha todos os campos obrigatórios.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container">
        <div class="auth-box" style="max-width:600px">
            <h2>Criar Conta</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?> <a href="login.php"
                        style="font-weight:bold;text-decoration:underline">Entrar agora</a>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Nome / Empresa *</label>
                        <input type="text" name="name" required placeholder="Nome Completo">
                    </div>
                    <div class="form-group">
                        <label>CPF / CNPJ *</label>
                        <input type="text" name="document" required placeholder="000.000.000-00">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>WhatsApp *</label>
                        <input type="text" name="phone" required placeholder="(11) 99999-9999">
                    </div>
                </div>

                <label>Senha *</label>
                <input type="password" name="password" required>

                <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid var(--border);">
                <p style="margin-bottom:1rem; color:var(--primary); font-weight:bold;">Endereço de Entrega (Para
                    Transportadora)</p>

                <div class="form-group">
                    <label>CEP *</label>
                    <input type="text" name="zipcode" required placeholder="00000-000">
                </div>

                <div style="display:grid; grid-template-columns: 3fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Rua *</label>
                        <input type="text" name="address" required>
                    </div>
                    <div class="form-group">
                        <label>Número *</label>
                        <input type="text" name="number" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Bairro *</label>
                    <input type="text" name="neighborhood" required>
                </div>

                <div style="display:grid; grid-template-columns: 3fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Cidade *</label>
                        <input type="text" name="city" required>
                    </div>
                    <div class="form-group">
                        <label>UF *</label>
                        <input type="text" name="state" required maxlength="2" placeholder="SP">
                    </div>
                </div>

                <button type="submit" class="btn" style="width:100%; margin-top:1rem;">Cadastrar</button>
            </form>

            <p style="margin-top: 1rem; text-align: center; color: var(--text-muted);">
                Já tem conta? <a href="<?php echo BASE_URL; ?>/login.php" style="color: var(--primary);">Fazer login</a>
            </p>
        </div>
    </div>

    <footer>
        <div class="container">
            &copy; <?php echo date('Y'); ?> Fight Arcade.
        </div>
    </footer>

</body>

</html>