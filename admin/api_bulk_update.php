<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/user_auth.php';
isAdmin();

header('Content-Type: application/json');

// Get JSON Input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'JSON inválido ou vazio']);
    exit;
}

$updatedCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    foreach ($data as $item) {
        if (!isset($item['id']))
            continue;

        $id = (int) $item['id'];
        $fields = [];
        $params = [];

        // Map allowed fields dynamically
        // Numerical/Float fields
        $floats = ['price', 'price_wholesale', 'weight_kg', 'length_cm', 'width_cm', 'height_cm'];
        foreach ($floats as $f) {
            if (isset($item[$f])) {
                $fields[] = "$f = :$f";
                $params[":$f"] = (float) $item[$f];
            }
        }

        // String fields
        $strings = ['name', 'sku', 'ean', 'ncm', 'brand', 'gtin', 'mpn', 'description', 'seo_title', 'seo_description'];
        foreach ($strings as $s) {
            if (isset($item[$s])) {
                $fields[] = "$s = :$s";
                $params[":$s"] = $item[$s];
            }
        }

        // Integer keys (if any specific, though dimensions are usually int, float covers them safely in prepared statements for MySQL)

        if (empty($fields))
            continue;

        $params[':id'] = $id;
        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $updatedCount++;
        } else {
            $errors[] = "Falha ao atualizar ID $id";
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Sucesso! $updatedCount produtos atualizados.", 'errors' => $errors]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erro no servidor: ' . $e->getMessage()]);
}
