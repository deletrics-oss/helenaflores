<?php
// catalogo/admin/customer-create.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $doc = $_POST['document'];
    // Address
    $zip = $_POST['zipcode'] ?? '';
    $addr = $_POST['address'] ?? '';
    $num = $_POST['number'] ?? '';
    $bairro = $_POST['neighborhood'] ?? '';
    $state = $_POST['state'] ?? '';
    $is_vip = isset($_POST['is_vip']) ? 1 : 0;

    // Password logic
    $raw_pass = $_POST['password'];
    if (empty($raw_pass)) {
        // Generate random password if empty
        $raw_pass = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    }
    $password = password_hash($raw_pass, PASSWORD_DEFAULT);

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $msg = '<div class="alert alert-error">E-mail já cadastrado!</div>';
    } else {
        try {
            $sql = "INSERT INTO users (name, email, password, document, phone, zipcode, address, number, neighborhood, city, state, role, is_vip) 
                    VALUES (:name, :email, :pass, :doc, :phone, :zip, :addr, :num, :bairro, :city, :state, 'customer', :vip)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':pass' => $password,
                ':doc' => $doc,
                ':phone' => $phone,
                ':zip' => $zip,
                ':addr' => $addr,
                ':num' => $num,
                ':bairro' => $bairro,
                ':city' => $city,
                ':state' => $state,
                ':vip' => $is_vip
            ]);

            $msg = '<div class="alert alert-success">✅ Cliente cadastrado com sucesso!<br>Senha gerada: <strong>' . $raw_pass . '</strong></div>';
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-error">Erro ao salvar: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Novo Cliente | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        // ViaCEP Integration (Same as register.php)
        function buscarCep(cep) {
            if (cep.length == 9) { // 00000-000
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.erro) {
                            document.querySelector('[name=address]').value = data.logradouro;
                            document.querySelector('[name=neighborhood]').value = data.bairro;
                            document.querySelector('[name=city]').value = data.localidade;
                            document.querySelector('[name=state]').value = data.uf;
                            document.querySelector('[name=number]').focus();
                        }
                    });
            }
        }
    </script>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:800px; margin:0 auto;">
            <h2 style="text-align:left; border-bottom:1px solid #333; padding-bottom:1rem; margin-bottom:1rem;">
                Cadastrar Novo Cliente
            </h2>

            <?php echo $msg; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <!-- Col 1: Personal Data -->
                    <div>
                        <h4 style="color:var(--primary); margin-bottom:1rem;">Dados Pessoais</h4>

                        <label>Nome Completo</label>
                        <input type="text" name="name" required>

                        <label>Email</label>
                        <input type="email" name="email" required>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <div>
                                <label>CPF/CNPJ</label>
                                <input type="text" name="document">
                            </div>
                            <div>
                                <label>WhatsApp/Telefone</label>
                                <input type="text" name="phone">
                            </div>
                        </div>

                        <label>Senha Inicial (Deixe em branco para gerar aleatória)</label>
                        <input type="text" name="password" placeholder="Gerar Automática">
                    </div>

                    <!-- Col 2: Address Data -->
                    <div>
                        <h4 style="color:var(--primary); margin-bottom:1rem;">Endereço</h4>

                        <label>CEP</label>
                        <input type="text" name="zipcode" onblur="buscarCep(this.value)" placeholder="00000-000">

                        <label>Rua / Logradouro</label>
                        <input type="text" name="address">

                        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                            <div>
                                <label>Número</label>
                                <input type="text" name="number">
                            </div>
                            <div>
                                <label>Bairro</label>
                                <input type="text" name="neighborhood">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 3fr 1fr; gap:10px;">
                            <div>
                                <label>Cidade</label>
                                <input type="text" name="city">
                            </div>
                            <div class="form-group">
                                <label>UF</label>
                                <input type="text" name="state" maxlength="2">
                            </div>
                        </div>

                        <div
                            style="margin: 1rem 0; padding:1rem; border:1px solid #333; background:#1a1a1a; border-radius:6px;">
                            <label style="display:flex; align-items:center; cursor:pointer;">
                                <input type="checkbox" name="is_vip" value="1" style="width:auto; margin:0 10px 0 0;">
                                <span style="color: gold; font-weight:bold;">👑 Cliente VIP (Acesso direto ao Catálogo
                                    Exclusivo)</span>
                            </label>
                        </div>

                        <div style="margin-top:2rem; text-align:right;">
                            <a href="customers.php" class="btn btn-secondary" style="margin-right:10px;">Voltar</a>
                            <button type="submit" class="btn">Salvar Cliente</button>
                        </div>
            </form>
        </div>
    </div>
</body>

</html>