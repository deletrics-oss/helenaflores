<?php
/**
 * api/import_whatsapp_catalog.php — Helena Flores
 * Endpoint local para receber o JSON extraído diretamente do DevTools do WhatsApp Web
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../robot_scraper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data) || !is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Nenhum produto enviado ou JSON inválido.']);
    exit;
}

$uploadDir = __DIR__ . '/../assets/uploads/';
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

$inserted = 0;
$updated = 0;
$itemsProcessed = [];

foreach ($data as $item) {
    $name = trim($item['title'] ?? $item['name'] ?? '');
    if (empty($name)) continue;

    $priceStr = $item['price'] ?? '0';
    $cleanPrice = preg_replace('/[^\d,\.]/', '', $priceStr);
    $price = floatval(str_replace(',', '.', str_replace('.', '', $cleanPrice)));
    if ($price == 0 && is_numeric($cleanPrice)) $price = floatval($cleanPrice);

    $desc = trim($item['description'] ?? '');
    $categoryName = trim($item['category'] ?? 'Rosas Colombianas');
    $imgUrl = trim($item['image'] ?? '');

    // Resolve Category
    $catSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $categoryName), '-'));
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
    $stmt->execute([$categoryName, $catSlug]);
    $catId = $stmt->fetchColumn();

    if (!$catId) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$categoryName, $catSlug ?: 'cat-' . time()]);
        $catId = $pdo->lastInsertId();
    }

    // Process Image
    $localImg = null;
    if (!empty($imgUrl) && strpos($imgUrl, 'http') === 0) {
        $ext = 'jpg';
        $filename = 'wa_' . md5($name) . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $filename;
        $imgData = @file_get_contents($imgUrl);
        if ($imgData && strlen($imgData) > 500) {
            file_put_contents($dest, $imgData);
            $localImg = $filename;
        } else {
            $localImg = $imgUrl;
        }
    }

    // Check duplicate in DB
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? OR slug = ?");
    $stmt->execute([$name, $slug]);
    $existingId = $stmt->fetchColumn();

    if (!$existingId) {
        $sku = 'WA-' . strtoupper(substr(md5($name), 0, 6));
        $ins = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, sku, price, image_path, active, stock_qty, featured) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 50, 1)");
        $ins->execute([$catId, $name, $slug, $desc, $sku, $price, $localImg]);
        $inserted++;
    } else {
        $upd = $pdo->prepare("UPDATE products SET price = IF(? > 0, ?, price), image_path = COALESCE(?, image_path), description = IF(LENGTH(?) > 3, ?, description), active = 1 WHERE id = ?");
        $upd->execute([$price, $price, $localImg, $desc, $desc, $existingId]);
        $updated++;
    }

    $itemsProcessed[] = [
        'name' => $name,
        'price' => $price,
        'category' => $categoryName
    ];
}

echo json_encode([
    'success' => true,
    'total' => count($itemsProcessed),
    'inserted' => $inserted,
    'updated' => $updated,
    'items' => $itemsProcessed
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
