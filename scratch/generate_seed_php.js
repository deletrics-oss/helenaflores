import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const JSON_PATH = path.resolve(__dirname, '../scratch/produtos400_processed.json');
const SEED_PHP_PATH = path.resolve(__dirname, '../seed_helena_flores.php');
const IMPORT_PHP_PATH = path.resolve(__dirname, '../import_produtos400.php');

const products = JSON.parse(fs.readFileSync(JSON_PATH, 'utf-8'));

console.log(`Carregados ${products.length} produtos para gerar scripts PHP...`);

// Categories mapping
const categories = [
    'Rosas Colombianas',
    'Cestas Personalizadas',
    'Buquês de Luxo',
    'Arranjos & Vasos',
    'KITS & Presentes',
    'Orquídeas & Plantas',
    'Girassóis & Flores'
];

let phpCode = `<?php
/**
 * seed_helena_flores.php — Sincronizador em Massa dos 399 Produtos do Catálogo Helena Flores
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<div style='font-family:sans-serif; padding:20px; background:#FFF8F9; border-radius:12px; border:1px solid #FCE4EC;'>";
echo "<h2 style='color:#C2185B;'>🌸 Sincronizador de Catálogo — Helena Flores (399 Produtos)</h2>";

try {
    // 1. Garantir Categorias no Banco
    $catMap = [];
    $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE name=VALUES(name)");
    
    $catList = [
        'Rosas Colombianas' => 'rosas-colombianas',
        'Cestas Personalizadas' => 'cestas-personalizadas',
        'Buquês de Luxo' => 'buques-de-luxo',
        'Arranjos & Vasos' => 'arranjos-e-vasos',
        'KITS & Presentes' => 'kits-e-presentes',
        'Orquídeas & Plantas' => 'orquideas-e-plantas',
        'Girassóis & Flores' => 'girassois-e-flores'
    ];

    foreach ($catList as $catName => $catSlug) {
        $stmtCat->execute([$catName, $catSlug]);
        $stmtGet = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmtGet->execute([$catName]);
        $catMap[$catName] = $stmtGet->fetchColumn();
    }

    echo "<p style='color:#2E7D32;'>✅ Categorias sincronizadas no banco de dados.</p>";

    // 2. Array Completo dos 399 Produtos
    $productsData = `;

phpCode += JSON.stringify(products, null, 4);

phpCode += `;

    $inserted = 0;
    $updated = 0;

    $stmtInsert = $pdo->prepare("INSERT INTO products 
        (category_id, name, slug, description, sku, price, image_path, active, stock_qty, featured) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 999, ?) 
        ON DUPLICATE KEY UPDATE 
            category_id = VALUES(category_id),
            price = VALUES(price),
            description = VALUES(description),
            image_path = VALUES(image_path),
            active = 1");

    foreach ($productsData as $idx => $p) {
        $catName = $p['category'] ?? 'Rosas Colombianas';
        $catId = $catMap[$catName] ?? $catMap['Rosas Colombianas'];
        $name = trim($p['name']);
        $slug = $p['slug'];
        $desc = trim($p['description']);
        $price = floatval($p['price']);
        $imagePath = $p['image_path'];
        $sku = 'HF-WA-' . strtoupper(substr(md5($name), 0, 6));
        $featured = ($idx < 20) ? 1 : 0;

        $stmtInsert->execute([
            $catId,
            $name,
            $slug,
            $desc,
            $sku,
            $price,
            $imagePath,
            $featured
        ]);

        if ($stmtInsert->rowCount() == 1) {
            $inserted++;
        } else {
            $updated++;
        }
    }

    echo "<div style='background:#E8F5E9; color:#2E7D32; padding:15px; border-radius:8px; margin-top:15px;'>";
    echo "🎉 <strong>SUCESSO TOTAL! " . count($productsData) . " Produtos Sincronizados com Fotos Oficiais!</strong><br>";
    echo "• Novos produtos cadastrados: <strong>{$inserted}</strong><br>";
    echo "• Produtos atualizados: <strong>{$updated}</strong>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background:#FFEBEE; color:#C2185B; padding:15px; border-radius:8px; margin-top:15px;'>";
    echo "❌ <strong>Erro ao semear:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div>";
`;

fs.writeFileSync(SEED_PHP_PATH, phpCode, 'utf-8');
fs.writeFileSync(IMPORT_PHP_PATH, phpCode, 'utf-8');

console.log(`✅ Gerado seed_helena_flores.php e import_produtos400.php com sucesso (${products.length} produtos)!`);
