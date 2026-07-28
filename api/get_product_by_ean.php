<?php
// api/get_product_by_ean.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$ean = $_GET['ean'] ?? '';

if (empty($ean)) {
    echo json_encode(['success' => false, 'error' => 'EAN não fornecido']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.stock, p.ean, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.ean = ? 
    LIMIT 1
");
$stmt->execute([$ean]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    echo json_encode(['success' => true, 'product' => $product, 'source' => 'db']);
} else {
    // Tenta identificar com o Gemini se não estiver no banco
    require_once __DIR__ . '/../includes/ai_sdr.php';
    $ai = new AIService($pdo);
    $aiInfo = $ai->identifyProductByEAN($ean);

    if ($aiInfo && isset($aiInfo['name'])) {
        echo json_encode([
            'success' => true, 
            'product' => [
                'id' => null,
                'name' => '✨ IA: ' . $aiInfo['name'],
                'category_name' => $aiInfo['category'] ?? 'Nova Categoria',
                'description' => $aiInfo['description'] ?? '',
                'stock' => 0,
                'ean' => $ean
            ],
            'source' => 'gemini'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Produto não encontrado e IA não conseguiu identificar']);
    }
}
?>
