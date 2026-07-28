<?php
// catalogo/fabrica/login.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

if (isset($_SESSION['factory_user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM factory_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['factory_user_id'] = $user['id'];
            $_SESSION['factory_user_name'] = $user['name'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Usuário ou senha incorretos.';
        }
    } else {
        $error = 'Por favor, preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Fábrica ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap');
        :root {
            --bg: #0b0f16;
            --surface: #121824;
            --border: #222e44;
            --primary: #00e676;
            --text: #e2e8f0;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { background: var(--surface); border: 1px solid var(--border); width: 100%; max-width: 400px; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
        .logo { font-size: 2rem; font-weight: 800; text-align: center; margin-bottom: 2rem; display: flex; align-items: center; justify-content: center; gap: 10px; color: #fff; }
        .logo span { color: var(--primary); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .form-control { width: 100%; padding: 12px; background: #080b10; border: 1px solid var(--border); border-radius: 8px; color: #fff; outline: none; transition: border-color 0.2s; font-size: 0.95rem; }
        .form-control:focus { border-color: var(--primary); }
        .btn-submit { width: 100%; padding: 12px; background: var(--primary); color: #000; border: none; border-radius: 8px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: opacity 0.2s; margin-top: 1rem; }
        .btn-submit:hover { opacity: 0.9; }
        .error-box { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ff6b6b; padding: 10px; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; font-size: 0.9rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <i class="fas fa-industry" style="color:var(--primary);"></i> Fábrica<span>ERP</span>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-box"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Usuário</label>
                <input type="text" name="username" class="form-control" placeholder="Digite seu usuário..." required autofocus>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="password" class="form-control" placeholder="Digite sua senha..." required>
            </div>
            <button type="submit" class="btn-submit">ENTRAR NO SISTEMA</button>
        </form>
    </div>
</body>
</html>
