<?php
/**
 * login.php — Helena Flores (Login do Cliente / Entrar na Conta)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$error = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'my-orders.php';

// Auto-migration for users columns
try {
    $cols = [
        'city' => "VARCHAR(100) DEFAULT 'São Paulo'",
        'state' => "VARCHAR(10) DEFAULT 'SP'",
        'phone' => "VARCHAR(50) DEFAULT ''",
        'address' => "VARCHAR(255) DEFAULT ''",
        'number' => "VARCHAR(50) DEFAULT ''",
        'neighborhood' => "VARCHAR(100) DEFAULT ''",
        'zipcode' => "VARCHAR(20) DEFAULT ''",
        'document' => "VARCHAR(50) DEFAULT ''"
    ];
    foreach ($cols as $c => $def) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN $c $def"); } catch (Exception $e) {}
    }
} catch (Exception $e) {}

// Process Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login_input'] ?? $_POST['phone'] ?? $_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($loginInput)) {
        $error = 'Por favor, informe seu E-mail ou Telefone.';
    } else {
        $cleanPhone = preg_replace('/\D/', '', $loginInput);

        // Search user by email OR phone
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ? OR phone = ?");
        $stmt->execute([$loginInput, $loginInput, $cleanPhone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verify password if set
            $authOk = false;
            if (!empty($password) && !empty($user['password'])) {
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    $authOk = true;
                } else {
                    $error = 'Senha incorreta. Por favor, tente novamente.';
                }
            } else {
                // Quick login via phone or lead
                $authOk = true;
            }

            if ($authOk) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'] ?? 'customer';

                header("Location: " . $baseUrl . "/" . ltrim($redirect, '/'));
                exit;
            }
        } else {
            // User not found - create quick customer account automatically
            $name = trim($_POST['name'] ?? 'Cliente Helena Flores');
            $dummyEmail = !empty($cleanPhone) ? $cleanPhone . '@helenaflores.com.br' : 'user_' . time() . '@helenaflores.com.br';
            $hashPass = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : password_hash('123456', PASSWORD_DEFAULT);

            try {
                $stmtIns = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
                $stmtIns->execute([$name, $dummyEmail, $cleanPhone ?: $loginInput, $hashPass]);
                $newId = $pdo->lastInsertId();

                $_SESSION['user_id'] = $newId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $dummyEmail;
                $_SESSION['user_role'] = 'customer';

                header("Location: " . $baseUrl . "/" . ltrim($redirect, '/'));
                exit;
            } catch (Exception $eIns) {
                $error = 'Erro ao criar conta. Tente cadastrar-se na página de cadastro.';
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
    <title>Entrar na Conta | Helena Flores</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .login-card {
            max-width: 440px; margin: 3rem auto; background: #FFFFFF; border: 1px solid #EEEEEE;
            border-radius: 16px; padding: 2.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-weight: 700; font-size: 0.9rem; color: #444; margin-bottom: 6px; }
        .form-control {
            width: 100%; height: 46px; border-radius: 10px; border: 1px solid #DDD; padding: 0 14px;
            font-size: 0.95rem; box-sizing: border-box; transition: border-color 0.2s ease;
        }
        .form-control:focus { border-color: var(--gf-magenta); outline: none; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div style="max-width:1240px; margin: 0 auto; padding: 0 20px; flex:1;">
        
        <div class="login-card">
            <h1 style="font-size:1.6rem; font-weight:800; color:var(--gf-magenta-dark); text-align:center; margin-bottom:0.5rem;">
                🔑 Acesse sua Conta
            </h1>
            <p style="text-align:center; color:#666; font-size:0.9rem; margin-bottom:1.8rem;">
                Acompanhe seus pedidos e gerencie suas entregas com facilidade.
            </p>

            <?php if ($error): ?>
                <div style="background:#FFEBEE; color:#C2185B; padding:12px; border-radius:8px; margin-bottom:1.2rem; font-weight:bold; font-size:0.9rem; text-align:center;">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                <div class="form-group">
                    <label>E-mail ou Telefone (WhatsApp)</label>
                    <input type="text" name="login_input" class="form-control" placeholder="Ex: (11) 98672-7872 ou seu@email.com" required>
                </div>

                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="password" class="form-control" placeholder="Sua senha de acesso">
                </div>

                <button type="submit" class="gf-btn-buy" style="width:100%; height:48px; border-radius:24px; font-size:1.05rem; font-weight:bold; margin-top:10px; border:none; cursor:pointer;">
                    ENTRAR NA CONTA 🌸
                </button>
            </form>

            <div style="text-align:center; margin-top:1.8rem; border-top:1px dashed #EEE; padding-top:1.2rem;">
                <p style="color:#666; font-size:0.9rem; margin-bottom:10px;">Ainda não possui uma conta na Helena Flores?</p>
                <a href="<?php echo $baseUrl; ?>/register.php?redirect=<?php echo urlencode($redirect); ?>" 
                   class="gf-btn-primary" style="display:inline-block; text-decoration:none; padding:10px 24px; border-radius:20px; font-size:0.9rem;">
                    📝 Criar Minha Conta Agora
                </a>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>