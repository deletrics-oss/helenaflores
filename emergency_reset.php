<?php
// catalogo/emergency_reset.php
require_once 'config.php';
require_once 'includes/db.php';

// Force display errors to debug HTTP 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Recuperador de Acesso Admin</h1>";

$email = 'admin@fightarcade.com';
$pass = 'admin123';

// 1. Check if user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "<p>Usuário '$email' encontrado. Atualizando senha...</p>";
    // Update to plain text for simplicity now, or password_hash($pass, PASSWORD_DEFAULT)
    // Using plain text to guarantee it works with the simple login.php logic
    $sql = "UPDATE users SET password = ?, role = 'admin' WHERE email = ?";
    $pdo->prepare($sql)->execute([$pass, $email]);
} else {
    echo "<p>Usuário não encontrado. Criando novo...</p>";
    $sql = "INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute(['Super Admin', $email, $pass, 'admin', '99999999999']);
}

echo "<h2>SUCESSO!</h2>";
echo "<p>Use os dados abaixo para logar:</p>";
echo "<ul><li>Email: <strong>$email</strong></li><li>Senha: <strong>$pass</strong></li></ul>";
echo "<p><a href='admin/login.php'>Ir para Login do Admin</a></p>";
?>