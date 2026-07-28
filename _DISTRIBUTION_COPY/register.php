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
    $comp = $_POST['complement'] ?? '';
    $ref = $_POST['reference'] ?? '';
    $bairro = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $uf = $_POST['state'] ?? '';

    if ($name && $email && $password && $document && $phone) {
        // Sanitize Phone
        $phone_clean = preg_replace('/\D/', '', $phone);

        // Check if email or phone (customer match) exists
        $stmtEx = $pdo->prepare("SELECT id, is_lead FROM users WHERE email = :email OR phone = :phone");
        $stmtEx->execute([':email' => $email, ':phone' => $phone_clean]);
        $existing = $stmtEx->fetch();

        if ($existing && $existing['is_lead'] == 0) {
            $error = 'Este email ou telefone já está cadastrado como cliente.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $source = $_POST['source'] ?? 'Registro Direto';

            if ($existing && $existing['is_lead'] == 1) {
                // UPDATE Lead to Customer
                $sql = "UPDATE users SET name = :name, email = :email, password = :pass, document = :doc, zipcode = :zip, 
                        address = :addr, number = :num, complement = :comp, reference = :ref, neighborhood = :bairro, city = :city, state = :uf, 
                        role = 'customer', is_lead = 0, source = :source WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':pass' => $hash,
                    ':doc' => $document,
                    ':zip' => $zip,
                    ':addr' => $addr,
                    ':num' => $num,
                    ':comp' => $comp,
                    ':ref' => $ref,
                    ':bairro' => $bairro,
                    ':city' => $city,
                    ':uf' => $uf,
                    ':source' => $source,
                    ':id' => $existing['id']
                ]);

                // Update Session
                $_SESSION['is_lead'] = 0;
                $_SESSION['user_name'] = $name;
            } else {
                // NEW Customer
                $sql = "INSERT INTO users (name, email, password, document, phone, zipcode, address, number, complement, reference, neighborhood, city, state, role, source, is_lead) 
                        VALUES (:name, :email, :pass, :doc, :phone, :zip, :addr, :num, :comp, :ref, :bairro, :city, :uf, 'customer', :source, 0)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':pass' => $hash,
                    ':doc' => $document,
                    ':phone' => $phone_clean,
                    ':zip' => $zip,
                    ':addr' => $addr,
                    ':num' => $num,
                    ':comp' => $comp,
                    ':ref' => $ref,
                    ':bairro' => $bairro,
                    ':city' => $city,
                    ':uf' => $uf,
                    ':source' => $source
                ]);
            }
            $success = 'Cadastro realizado com sucesso! Faça login.';
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
    <style>
        /* Fix for select options visibility on some browsers */
        select option {
            background-color: #1a1a1a;
            /* Dark background */
            color: #ffffff;
            /* Light text */
        }

        select option:checked {
            background-color: var(--primary, #ffb703);
            color: #000;
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container">
        <div class="auth-box" style="max-width:600px">
            <h2>Criar Conta</h2>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-error"
                    style="border-color:var(--primary); background:rgba(255,183,3,0.1); color:var(--primary);">
                    ℹ️ <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

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
                        <input type="text" name="name" required placeholder="Nome Completo"
                            value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
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
                        <input type="text" name="phone" required placeholder="(11) 99999-9999"
                            value="<?php echo isset($_SESSION['phone']) ? htmlspecialchars($_SESSION['phone']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label>Como nos conheceu?</label>
                    <select name="source"
                        style="width:100%; padding:0.8rem; background:var(--bg-input); border:1px solid var(--border); color:var(--text-main); border-radius:4px;">
                        <option value="">Selecione...</option>
                        <option value="Instagram">Instagram</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Google">Google</option>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Indicação">Indicação</option>
                        <option value="Outro">Outro</option>
                    </select>
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

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complement" placeholder="Apt, Bloco (Opcional)">
                    </div>
                    <div class="form-group">
                        <label>Ponto de Referência</label>
                        <input type="text" name="reference" placeholder="Ex: Próximo ao mercado">
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

    <!-- ViaCEP Script -->
    <script>
        document.querySelector('input[name="zipcode"]').addEventListener('blur', function (e) {
            let cep = e.target.value.replace(/\D/g, '');
            if (cep.length === 8) {
                // Show loading state
                document.querySelector('input[name="address"]').value = 'Buscando...';

                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            document.querySelector('input[name="address"]').value = data.logradouro;
                            document.querySelector('input[name="neighborhood"]').value = data.bairro;
                            document.querySelector('input[name="city"]').value = data.localidade;
                            document.querySelector('input[name="state"]').value = data.uf;
                            // Focus on number
                            document.querySelector('input[name="number"]').focus();
                        } else {
                            alert('CEP não encontrado.');
                            document.querySelector('input[name="address"]').value = '';
                        }
                    })
                    .catch(() => alert('Erro ao buscar CEP.'));
            }
        });

        // Mask CEP
        document.querySelector('input[name="zipcode"]').addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5, 8);
            e.target.value = v;
        });

        // Mask CPF/CNPJ
        document.querySelector('input[name="document"]').addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length <= 11) {
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                v = v.replace(/^(\d{2})(\d)/, '$1.$2');
                v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
                v = v.replace(/(\d{4})(\d)/, '$1-$2');
            }
            e.target.value = v;
        });
    </script>

</body>

</html>