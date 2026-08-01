<?php
/**
 * register.php — Helena Flores (Cadastro de Cliente Completo)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$error = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'my-orders.php';

// Auto-migration for users columns
try {
    $cols = [
        'city'         => "VARCHAR(100) DEFAULT 'São Paulo'",
        'state'        => "VARCHAR(10) DEFAULT 'SP'",
        'phone'        => "VARCHAR(50) DEFAULT ''",
        'address'      => "VARCHAR(255) DEFAULT ''",
        'number'       => "VARCHAR(50) DEFAULT ''",
        'neighborhood' => "VARCHAR(100) DEFAULT ''",
        'zipcode'      => "VARCHAR(20) DEFAULT ''",
        'document'     => "VARCHAR(50) DEFAULT ''",
        'source'       => "VARCHAR(50) DEFAULT 'Registro Direto'",
        'is_lead'      => "TINYINT(1) DEFAULT 0"
    ];
    foreach ($cols as $c => $def) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN $c $def"); } catch (Exception $e) {}
    }
} catch (Exception $e) {}

// Process Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $doc = trim($_POST['document'] ?? '');

    $zip = trim($_POST['zipcode'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $number = trim($_POST['number'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? 'São Paulo');
    $state = trim($_POST['state'] ?? 'SP');

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Por favor, preencha todos os campos obrigatórios (*).';
    } else {
        $cleanPhone = preg_replace('/\D/', '', $phone);

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_lead = 0");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Este e-mail já está cadastrado. Por favor, faça login ou use outro e-mail.';
        } else {
            $hashPass = password_hash($password, PASSWORD_DEFAULT);

            try {
                $sql = "INSERT INTO users (name, email, password, phone, document, zipcode, address, number, neighborhood, city, state, role, source, is_lead) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'Site Direct', 0)";
                $stmtIns = $pdo->prepare($sql);
                $stmtIns->execute([$name, $email, $hashPass, $cleanPhone, $doc, $zip, $address, $number, $neighborhood, $city, $state]);
                $newId = $pdo->lastInsertId();

                // Set Session Logged In
                $_SESSION['user_id'] = $newId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'customer';

                header("Location: " . $baseUrl . "/" . ltrim($redirect, '/'));
                exit;
            } catch (Exception $e) {
                // Fallback insert with essential columns if schema differs
                try {
                    $stmtIns = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'customer')");
                    $stmtIns->execute([$name, $email, $hashPass, $cleanPhone]);
                    $newId = $pdo->lastInsertId();

                    $_SESSION['user_id'] = $newId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'customer';

                    header("Location: " . $baseUrl . "/" . ltrim($redirect, '/'));
                    exit;
                } catch (Exception $eFb) {
                    $error = 'Erro ao realizar cadastro: ' . $eFb->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Cliente | Helena Flores</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .register-card {
            max-width: 650px; margin: 3rem auto; background: #FFFFFF; border: 1px solid #EEEEEE;
            border-radius: 16px; padding: 2.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-weight: 700; font-size: 0.88rem; color: #444; margin-bottom: 6px; }
        .form-control {
            width: 100%; height: 45px; border-radius: 10px; border: 1px solid #DDD; padding: 0 14px;
            font-size: 0.95rem; box-sizing: border-box; transition: border-color 0.2s ease;
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
        
        <div class="register-card">
            <h1 style="font-size:1.6rem; font-weight:800; color:var(--gf-magenta-dark); text-align:center; margin-bottom:0.5rem;">
                📝 Criar Conta de Cliente
            </h1>
            <p style="text-align:center; color:#666; font-size:0.9rem; margin-bottom:1.8rem;">
                Cadastre-se para acompanhar seus pedidos e agilizar suas compras na Helena Flores.
            </p>

            <?php if ($error): ?>
                <div style="background:#FFEBEE; color:#C2185B; padding:12px; border-radius:8px; margin-bottom:1.2rem; font-weight:bold; font-size:0.9rem; text-align:center;">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                <h3 style="font-size:1.05rem; font-weight:800; color:var(--gf-magenta); margin-bottom:1rem; border-bottom:1px dashed #EEE; padding-bottom:6px;">
                    1. Dados Pessoais
                </h3>

                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Maria Silva" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" class="form-control" placeholder="seu@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>WhatsApp / Telefone *</label>
                        <input type="text" name="phone" class="form-control" placeholder="(11) 98672-7872" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>CPF / CNPJ (Opcional)</label>
                        <input type="text" name="document" class="form-control" placeholder="123.456.789-00">
                    </div>
                    <div class="form-group">
                        <label>Criar Senha de Acesso *</label>
                        <input type="password" name="password" class="form-control" placeholder="Crie sua senha" required>
                    </div>
                </div>

                <h3 style="font-size:1.05rem; font-weight:800; color:var(--gf-magenta); margin-top:1rem; margin-bottom:1rem; border-bottom:1px dashed #EEE; padding-bottom:6px;">
                    2. Endereço Principal de Entrega
                </h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="zipcode" class="form-control" placeholder="01420-001">
                    </div>
                    <div class="form-group">
                        <label>Cidade / Estado</label>
                        <input type="text" name="city" class="form-control" value="São Paulo / SP">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Endereço / Rua</label>
                        <input type="text" name="address" class="form-control" placeholder="Ex: Alameda Jaú">
                    </div>
                    <div class="form-group">
                        <label>Número & Bairro</label>
                        <input type="text" name="number" class="form-control" placeholder="1777 - Jardins">
                    </div>
                </div>

                <button type="submit" class="gf-btn-buy" style="width:100%; height:48px; border-radius:24px; font-size:1.05rem; font-weight:bold; margin-top:15px; border:none; cursor:pointer;">
                    CONCLUIR MEU CADASTRO 🌸
                </button>
            </form>

            <div style="text-align:center; margin-top:1.8rem; border-top:1px dashed #EEE; padding-top:1.2rem;">
                <p style="color:#666; font-size:0.9rem; margin-bottom:10px;">Já possui uma conta?</p>
                <a href="<?php echo $baseUrl; ?>/login.php?redirect=<?php echo urlencode($redirect); ?>" 
                   style="color:var(--gf-magenta); font-weight:bold; text-decoration:none; font-size:0.95rem;">
                    🔑 Fazer Login na Minha Conta
                </a>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>