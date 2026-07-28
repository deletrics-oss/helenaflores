<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $category = $_GET['category'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM custom_message_templates WHERE category = ? ORDER BY title ASC");
    $stmt->execute([$category]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $title = $_POST['title'] ?? 'Sem Título';
    $message = $_POST['message'] ?? '';

    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO custom_message_templates (category, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$category, $title, $message]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Mensagem vazia']);
    }
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM custom_message_templates WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}
