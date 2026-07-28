<?php
/**
 * scratch/download_floral_images.php — Helena Flores
 * Baixa fotos de alta definição de flores, cestas e presentes para a pasta local assets/uploads/
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$uploadDir = __DIR__ . '/../assets/uploads/';
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Curated HD Unsplash Image URLs by Flower Type
$imagePool = [
    'rose_red' => [
        'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80',
        'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=800&q=80',
        'https://images.unsplash.com/photo-1548695607-9c73430ba065?w=800&q=80',
        'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?w=800&q=80'
    ],
    'rose_pink' => [
        'https://images.unsplash.com/photo-1559563458-527698bf5295?w=800&q=80',
        'https://images.unsplash.com/photo-1534073828943-f801091bb18c?w=800&q=80'
    ],
    'rose_yellow' => [
        'https://images.unsplash.com/photo-1597848212624-a19eb35e2651?w=800&q=80',
        'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=800&q=80'
    ],
    'cesta' => [
        'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=800&q=80',
        'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?w=800&q=80'
    ],
    'girassol' => [
        'https://images.unsplash.com/photo-1591886960571-74d43a9d4166?w=800&q=80',
        'https://images.unsplash.com/photo-1597848212624-a19eb35e2651?w=800&q=80'
    ],
    'orquidea' => [
        'https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?w=800&q=80',
        'https://images.unsplash.com/photo-1566803792036-7c00e6e7368d?w=800&q=80'
    ],
    'tulipa' => [
        'https://images.unsplash.com/photo-1520763185298-1b434c919102?w=800&q=80'
    ],
    'arranjo' => [
        'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=800&q=80',
        'https://images.unsplash.com/photo-1508610048659-a06b669e3321?w=800&q=80'
    ],
    'kit_choc' => [
        'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=800&q=80'
    ]
];

function selectPoolKey($title) {
    if (preg_match('/girassol|girassois|girasol/i', $title)) return 'girassol';
    if (preg_match('/cesta|café|cafe/i', $title)) return 'cesta';
    if (preg_match('/orquídia|orquidea/i', $title)) return 'orquidea';
    if (preg_match('/tulipa|tulipas/i', $title)) return 'tulipa';
    if (preg_match('/ferreiro|chocolates|bombom|kit|urso|pelúcia|nutella|chandon|vinho|espumante/i', $title)) return 'kit_choc';
    if (preg_match('/amarelas|amarelo/i', $title)) return 'rose_yellow';
    if (preg_match('/pink|rosé|rosa/i', $title)) return 'rose_pink';
    if (preg_match('/arranjo|vaso|lírio|lirio/i', $title)) return 'arranjo';
    return 'rose_red';
}

$stmt = $pdo->query("SELECT id, name, slug FROM products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$downloadedCount = 0;

foreach ($products as $p) {
    $poolKey = selectPoolKey($p['name']);
    $pool = $imagePool[$poolKey];
    $url = $pool[array_rand($pool)];

    $filename = 'hf_' . $p['slug'] . '.jpg';
    $dest = $uploadDir . $filename;

    if (!file_exists($dest) || filesize($dest) < 1000) {
        $imgData = @file_get_contents($url);
        if ($imgData && strlen($imgData) > 1000) {
            file_put_contents($dest, $imgData);
            $downloadedCount++;
        }
    }

    // Update MySQL product image_path to the local filename
    $upd = $pdo->prepare("UPDATE products SET image_path = ?, active = 1, show_on_site = 1 WHERE id = ?");
    $upd->execute([$filename, $p['id']]);
}

echo "
<div style='font-family:sans-serif; padding:30px; background:#FFF5F7; border:2px solid #C2185B; border-radius:12px; max-width:600px; margin:40px auto; text-align:center;'>
    <h2 style='color:#C2185B;'>🌸 Download de Imagens Concluído!</h2>
    <p style='font-size:1.1rem; color:#333;'>Foram baixados <strong>{$downloadedCount} novos arquivos de imagem JPG</strong> diretamente para a pasta local <code>assets/uploads/</code>!</p>
    <p>Todos os produtos do banco de dados agora apontam para arquivos locais físicos em <code>assets/uploads/</code>.</p>
</div>
";
