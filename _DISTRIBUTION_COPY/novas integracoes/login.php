<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);

    if (empty($phone) || empty($name)) {
        $error = 'Por favor, preencha seu nome e telefone.';
    } else {
        // Basic phone number validation (can be enhanced with regex or external API)
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            $error = 'Número de telefone inválido.';
        } else {
            // Check if user exists or create new user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = :phone");
            $stmt->execute([':phone' => $phone]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                header('Location: index.php');
                exit();
            } else {
                // Create new user
                $stmt = $pdo->prepare("INSERT INTO users (name, phone) VALUES (:name, :phone)");
                if ($stmt->execute([':name' => $name, ':phone' => $phone])) {
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['user_name'] = $name;
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Erro ao criar usuário. Tente novamente.';
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
    <title>Login | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/login_modal.css">
</head>

<body>
    <div class="login-overlay">
        <div class="login-modal">
            <h2>Entrar ou Cadastrar</h2>
            <?php if ($error): ?>
                <p class="error-message"><?php echo $error; ?></p>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="name">Nome:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="phone">Telefone:</label>
                    <input type="tel" id="phone" name="phone" placeholder="(XX) XXXXX-XXXX" required>
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>
    </div>
</body>

</html>
