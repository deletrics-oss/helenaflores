<?php
// admin/login.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();

// Debug helper
ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Check Database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $valid = false;

    if ($user && in_array($user['role'], ['admin', 'manager', 'factory'])) {
        // Try checks in order of likelihood

        // A. Plain Text (Legacy/Emergency)
        if ($user['password'] === $password) {
            $valid = true;
        }
        // B. MD5 (Common legacy)
        elseif (md5($password) === $user['password']) {
            $valid = true;
        }
        // C. Bcrypt/Argon (Modern Security)
        elseif (password_verify($password, $user['password'])) {
            $valid = true;
        }
    }

    if ($valid) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['admin_id'] = $user['id']; // Used by new POS/Stock modules
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Credenciais inválidas ou sem permissão.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Login Admin | Fight Arcade</title>
    <style>
        body {
            background: #0f131a;
            font-family: sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .login-box {
            background: #1a1f26;
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid #333;
            width: 100%;
            max-width: 350px;
            text-align: center;
            color: white;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            background: #0f131a;
            border: 1px solid #333;
            color: white;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 10px;
            background: #FFC107;
            color: black;
            border: none;
            font-weight: bold;
            cursor: pointer;
            border-radius: 4px;
        }

        button:hover {
            background: #e0a800;
        }

        .logo {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: #FFC107;
            font-weight: bold;
        }

        .error {
            color: #ff6b6b;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="logo">ADMIN ACCESS</div>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Email" required autofocus>
            <input type="password" name="password" placeholder="Senha" required>
            <button type="submit">ENTRAR</button>
        </form>
    </div>
</body>

</html>