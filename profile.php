<?php
/**
 * profile.php — Helena Flores (Perfil do Cliente & Meus Dados)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// 1. Check Authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $baseUrl . "/login.php?redirect=profile.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Load User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: " . $baseUrl . "/login.php");
    exit;
}

// Process Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $doc = trim($_POST['document'] ?? '');

    $zip = trim($_POST['zipcode'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $num = trim($_POST['number'] ?? '');
    $bairro = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');

    $pass = trim($_POST['password'] ?? '');

    try {
        if (!empty($name) && !empty($phone)) {
            $cleanPhone = preg_replace('/\D/', '', $phone);
            $sql = "UPDATE users SET name=?, document=?, phone=?, zipcode=?, address=?, number=?, neighborhood=?, city=?, state=? WHERE id=?";
            $pdo->prepare($sql)->execute([$name, $doc, $cleanPhone, $zip, $addr, $num, $bairro, $city, $state, $user_id]);

            if (!empty($pass)) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user_id]);
                $success = "Seus dados e sua senha foram atualizados com sucesso!";
            } else {
                $success = "Seus dados foram atualizados com sucesso!";
            }

            $_SESSION['user_name'] = $name;

            // Refresh user data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Nome e Telefone são campos obrigatórios.";
        }
    } catch (Exception $e) {
        $error = "Erro ao atualizar perfil: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil | Helena Flores</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .profile-card {
            max-width: 750px; margin: 2rem auto; background: #FFFFFF; border: 1px solid #EEEEEE;
            border-radius: 16px; padding: 2.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-weight: 700; font-size: 0.88rem; color: #444; margin-bottom: 6px; }
        .form-control {
            width: 100%; height: 45px; border-radius: 10px; border: 1px solid #DDD; padding: 0 14px;
            font-size: 0.95rem; box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--gf-magenta); outline: none; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div style="max-width:1240px; margin: 0 auto; padding: 0 20px; flex:1;">
        
        <div class="profile-card">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:15px; border-bottom:1px solid #EEE; padding-bottom:1rem;">
                <h1 style="font-size:1.6rem; font-weight:800; color:var(--gf-magenta-dark); margin:0;">
                    👤 Meu Perfil & Dados Pessoais
                </h1>
                <div style="display:flex; gap:10px;">
                    <a href="<?php echo $baseUrl; ?>/my-addresses.php" class="gf-btn-primary" style="padding:8px 16px; font-size:0.85rem;">
                        🏠 Meus Endereços
                    </a>
                    <a href="<?php echo $baseUrl; ?>/my-orders.php" style="color:var(--gf-magenta); font-weight:bold; text-decoration:none; font-size:0.85rem; border:1px solid #FCE4EC; padding:8px 16px; border-radius:20px; background:#FFF8F9;">
                        📦 Meus Pedidos
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div style="background:#E8F5E9; color:#2E7D32; padding:12px 18px; border-radius:10px; margin-bottom:1.5rem; font-weight:bold;">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background:#FFEBEE; color:#C2185B; padding:12px 18px; border-radius:10px; margin-bottom:1.5rem; font-weight:bold;">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>E-mail (Cadastrado)</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly style="background:#FAF9F6; color:#777;">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp / Telefone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>CPF / CNPJ</label>
                        <input type="text" name="document" class="form-control" value="<?php echo htmlspecialchars($user['document'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nova Senha (deixe em branco para manter a atual)</label>
                        <input type="password" name="password" class="form-control" placeholder="Nova senha de acesso">
                    </div>
                </div>

                <h3 style="font-size:1.05rem; font-weight:800; color:var(--gf-magenta); margin-top:1.5rem; margin-bottom:1rem; border-bottom:1px dashed #EEE; padding-bottom:6px;">
                    Endereço de Cobrança / Principal
                </h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="zipcode" id="zipcode" class="form-control" value="<?php echo htmlspecialchars($user['zipcode'] ?? ''); ?>" onblur="fetchViaCEP(this.value)">
                    </div>
                    <div class="form-group">
                        <label>Cidade / UF</label>
                        <input type="text" name="city" id="city" class="form-control" value="<?php echo htmlspecialchars($user['city'] ?? 'São Paulo'); ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Endereço / Rua</label>
                        <input type="text" name="address" id="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número & Bairro</label>
                        <input type="text" name="number" id="number" class="form-control" value="<?php echo htmlspecialchars($user['number'] ?? ''); ?>">
                    </div>
                </div>

                <button type="submit" class="gf-btn-buy" style="width:100%; height:48px; border-radius:24px; font-size:1.05rem; font-weight:bold; border:none; cursor:pointer; margin-top:15px;">
                    SALVAR ALTERAÇÕES 🌸
                </button>
            </form>

        </div>

    </div>

    <script>
        function fetchViaCEP(cepRaw) {
            const cep = cepRaw.replace(/\D/g, '');
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('address').value = data.logradouro || '';
                            document.getElementById('city').value = (data.localidade || 'São Paulo') + ' / ' + (data.uf || 'SP');
                        }
                    });
            }
        }
    </script>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>