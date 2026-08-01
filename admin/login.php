<?php
/**
 * admin/login.php — Helena Flores Admin (Login Administrativo com Master Credentials)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Master / Emergency Admin Bypass (Permite Login Instantâneo)
    if (($email === 'admin@helenaflores.com.br' || $email === 'admin@fightarcade.com.br' || $email === 'admin') && ($password === 'admin' || $password === '123456' || $password === 'admin123')) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminUser) {
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            try {
                $pdo->exec("INSERT INTO users (name, email, password, role) VALUES ('Administrador', 'admin@helenaflores.com.br', '$hash', 'admin')");
                $adminId = $pdo->lastInsertId();
                $adminName = 'Administrador';
            } catch (Exception $e) {
                $adminId = 1;
                $adminName = 'Administrador';
            }
        } else {
            $adminId = $adminUser['id'];
            $adminName = $adminUser['name'];
        }

        $_SESSION['user_id'] = $adminId;
        $_SESSION['user_name'] = $adminName;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['admin_id'] = $adminId;

        header("Location: dashboard.php");
        exit;
    }

    // Standard Database Authentication
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $valid = false;

    if ($user && in_array($user['role'], ['admin', 'manager', 'factory', 'vendedor', 'cadastro'])) {
        if ($user['password'] === $password) {
            $valid = true;
        } elseif (md5($password) === $user['password']) {
            $valid = true;
        } elseif (password_verify($password, $user['password'])) {
            $valid = true;
        }
    }

    if ($valid) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['admin_id'] = $user['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Credenciais inválidas. Use o usuário máster: admin | Senha: admin";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo | Helena Flores</title>
    <style>
        body {
            background: #0B0E14; font-family: 'Inter', sans-serif; display: flex; align-items: center;
            justify-content: center; height: 100vh; margin: 0; color: #FFF;
        }
        .login-box {
            background: #161B22; padding: 2.5rem; border-radius: 16px; border: 1px solid #30363D;
            width: 100%; max-width: 380px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .login-box h2 { color: #FFECB3; font-size: 1.5rem; margin-bottom: 0.5rem; }
        .input-group { margin-bottom: 1.2rem; text-align: left; }
        .input-group label { display: block; font-size: 0.85rem; color: #8B949E; margin-bottom: 6px; font-weight: bold; }
        .input-group input {
            width: 100%; height: 44px; background: #0D1117; border: 1px solid #30363D; color: #FFF;
            border-radius: 8px; padding: 0 12px; font-size: 0.95rem; box-sizing: border-box;
        }
        .input-group input:focus { border-color: #C2185B; outline: none; }
        .btn-submit {
            width: 100%; height: 46px; background: #C2185B; color: #FFF; border: none; border-radius: 23px;
            font-size: 1rem; font-weight: bold; cursor: pointer; margin-top: 10px; transition: background 0.2s ease;
        }
        .btn-submit:hover { background: #E91E63; }
        .master-hint {
            background: #21262D; border: 1px solid #30363D; padding: 10px; border-radius: 8px;
            font-size: 0.8rem; color: #FFD54F; margin-top: 1.5rem; text-align: center;
        }
    </style>
</head>
<body>

    <div class="login-box">
        <h2>🌹 ADMIN PAINEL</h2>
        <p style="color:#8B949E; font-size:0.85rem; margin-bottom:1.5rem;">Helena Flores — Gestão de Catálogo & Vendas</p>

        <?php if ($error): ?>
            <div style="background:#7F1D1D; color:#FECACA; padding:10px; border-radius:6px; margin-bottom:1.2rem; font-size:0.85rem; font-weight:bold;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>E-mail ou Usuário Admin</label>
                <input type="text" name="email" value="admin@helenaflores.com.br" required>
            </div>
            <div class="input-group">
                <label>Senha</label>
                <input type="password" name="password" value="admin" required>
            </div>
            <button type="submit" class="btn-submit">ENTRAR NO PAINEL 🚀</button>
        </form>

        <div class="master-hint">
            🔑 <strong>Acesso Máster Liberado:</strong><br>
            Usuário: <code>admin</code> ou <code>admin@helenaflores.com.br</code><br>
            Senha: <code>admin</code>
        </div>
    </div>

</body>
</html>