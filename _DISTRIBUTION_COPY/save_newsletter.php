<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = isset($_POST['phone']) ? preg_replace('/\D/', '', $_POST['phone']) : null;

    if ($email) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO newsletter_subscribers (email, phone) VALUES (?, ?)");
            $stmt->execute([$email, $phone]);
            echo json_encode(['success' => true, 'message' => 'Cadastro realizado com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    }
}
?>