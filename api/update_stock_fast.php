<?php
// api/update_stock_fast.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
    exit;
}

$id = (int)$data['id'];
$type = $data['type']; // entrada / saida
$qty = (int)$data['qty'];
$gps = $data['gps'] ?? '';

try {
    $pdo->beginTransaction();

    // 1. Pega estoque atual
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    // 2. Calcula novo estoque
    $newStock = ($type === 'entrada') ? ($current + $qty) : ($current - $qty);
    if ($newStock < 0) $newStock = 0;

    // 3. Atualiza Produto
    $stmtUpd = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmtUpd->execute([$newStock, $id]);

    // 4. Grava Log (Audit Trail)
    $stmtLog = $pdo->prepare("INSERT INTO stock_logs (product_id, type, quantity, location_gps, notes) VALUES (?, ?, ?, ?, ?)");
    $stmtLog->execute([$id, $type, $qty, $gps, 'Atualização via Mobile Scanner']);

    $pdo->commit();
    echo json_encode(['success' => true, 'new_stock' => $newStock]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
