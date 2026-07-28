<?php
// catalogo/reset_password.php
require_once 'config.php';
require_once 'includes/db.php';

$new_pass = 'admin123';
$hash = password_hash($new_pass, PASSWORD_DEFAULT);
$user = 'admin';

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$user]);

    if ($stmt->rowCount() > 0) {
        $sql = "UPDATE users SET password = :hash WHERE email = :email";
        $stmt_up = $pdo->prepare($sql);
        $stmt_up->execute([':hash' => $hash, ':email' => $user]);
        echo "<h1>Senha Atualizada!</h1>";
        echo "<p>Usuário: <strong>$user</strong></p>";
        echo "<p>Nova Senha: <strong>$new_pass</strong></p>";
        echo "<p><a href='login.php'>Clique aqui para entrar</a></p>";
    } else {
        // Create if not exists
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('Administrador', :email, :hash, 'admin')";
        $stmt_in = $pdo->prepare($sql);
        $stmt_in->execute([':email' => $user, ':hash' => $hash]);
        echo "<h1>Usuário Criado!</h1>";
        echo "<p>Usuário: <strong>$user</strong></p>";
        echo "<p>Senha: <strong>$new_pass</strong></p>";
        echo "<p><a href='login.php'>Clique aqui para entrar</a></p>";
    }
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
