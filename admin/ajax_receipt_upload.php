<?php
/**
 * AJAX Receipt Upload — Fight Arcade Admin
 * Handles payment receipt image upload and association with orders
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

header('Content-Type: application/json');

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        source ENUM('upload','whatsapp') DEFAULT 'upload',
        notes TEXT DEFAULT NULL,
        received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order (order_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Ensure upload directory exists
$uploadDir = __DIR__ . '/../assets/receipts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ============ UPLOAD ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!$orderId) {
        echo json_encode(['success' => false, 'error' => 'ID do pedido não informado.']);
        exit;
    }

    $file = $_FILES['receipt'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Formato inválido. Envie JPG, PNG, WEBP ou PDF.']);
        exit;
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 10MB.']);
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = "receipt_{$orderId}_" . time() . "." . strtolower($ext);
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'error' => 'Falha ao salvar arquivo.']);
        exit;
    }

    // Get user_id from order
    $userId = $pdo->query("SELECT user_id FROM orders WHERE id = $orderId")->fetchColumn() ?: null;

    $stmt = $pdo->prepare("INSERT INTO payment_receipts (order_id, user_id, file_path, source, notes) VALUES (?, ?, ?, 'upload', ?)");
    $stmt->execute([$orderId, $userId, 'assets/receipts/' . $filename, $notes]);

    echo json_encode([
        'success' => true,
        'receipt_id' => $pdo->lastInsertId(),
        'file_path' => 'assets/receipts/' . $filename,
        'message' => 'Comprovante anexado com sucesso!'
    ]);
    exit;
}

// ============ LIST RECEIPTS FOR ORDER ============
if (isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    $receipts = $pdo->prepare("SELECT * FROM payment_receipts WHERE order_id = ? ORDER BY received_at DESC");
    $receipts->execute([$orderId]);
    echo json_encode($receipts->fetchAll());
    exit;
}

// ============ DELETE RECEIPT ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_receipt'])) {
    $receiptId = (int)$_POST['receipt_id'];
    $receipt = $pdo->query("SELECT file_path FROM payment_receipts WHERE id = $receiptId")->fetch();
    if ($receipt) {
        $fullPath = __DIR__ . '/../' . $receipt['file_path'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        $pdo->exec("DELETE FROM payment_receipts WHERE id = $receiptId");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Comprovante não encontrado.']);
    }
    exit;
}

// ============ LIST UNASSOCIATED RECEIPTS ============
if (isset($_GET['unassociated'])) {
    $receipts = $pdo->query("SELECT * FROM payment_receipts WHERE order_id IS NULL ORDER BY received_at DESC")->fetchAll();
    echo json_encode($receipts);
    exit;
}

echo json_encode(['error' => 'Ação não reconhecida.']);
