<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin(); // Security Check

header('Content-Type: application/json');

// Get Input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Normalize Input: Can be {updates: [...]} or just [...]
$items = $data['updates'] ?? $data;

if (!is_array($items)) {
    echo json_encode(['error' => 'Invalid Format. Expected array of objects.']);
    exit;
}

$updatedCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    foreach ($items as $item) {
        if (!isset($item['id']))
            continue;

        $id = (int) $item['id'];
        $fields = [];
        $params = [];

        // Dynamic Update Builder
        if (isset($item['price'])) {
            $fields[] = "price = ?";
            $params[] = (float) $item['price'];
        }
        if (isset($item['price_wholesale'])) {
            $fields[] = "price_wholesale = ?";
            $params[] = (float) $item['price_wholesale'];
        }
        // Can add more fields later (e.g. stock)

        if (!empty($fields)) {
            $params[] = $id; // ID for WHERE clause
            $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $updatedCount++;
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'updated' => $updatedCount, 'message' => "$updatedCount produtos atualizados com sucesso!"]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
