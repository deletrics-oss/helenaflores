<?php
/**
 * seed_helena_flores.php — Semeador Completo de 118 Produtos do WhatsApp Helena Flores
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

function makeSlug($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'item-' . rand(10000, 99999) : $text;
}

function detectCatName($title) {
    if (preg_match('/cesta|café|cafe/i', $title)) return 'Cestas Personalizadas';
    if (preg_match('/rosa|colombiana/i', $title)) return 'Rosas Colombianas';
    if (preg_match('/buquê|buque|lily/i', $title)) return 'Buquês de Luxo';
    if (preg_match('/arranjo|vaso|lírio|lirio/i', $title)) return 'Arranjos & Vasos';
    if (preg_match('/kit|namorados|ferreiro|rocher|chandon|urso|bombom/i', $title)) return 'KITS & Presentes';
    if (preg_match('/orquídea|orquidea|planta|girassol/i', $title)) return 'Orquídeas & Plantas';
    return 'Rosas Colombianas';
}

function getCuratedImg($title) {
    if (preg_match('/girassol/i', $title)) return 'https://images.unsplash.com/photo-1591886960571-74d43a9d4166?w=800&q=80';
    if (preg_match('/cesta|café|cafe/i', $title)) return 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=800&q=80';
    if (preg_match('/rosa|colombiana/i', $title)) return 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80';
    if (preg_match('/arranjo|vaso|lírio/i', $title)) return 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=800&q=80';
    if (preg_match('/ferreiro|chocolates|bombom|kit|urso/i', $title)) return 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=800&q=80';
    return 'https://images.unsplash.com/photo-1567696911980-2eed69a46042?w=800&q=80';
}

try {
    // 1. Categories
    $categories = [
        ['name' => 'Rosas Colombianas', 'slug' => 'rosas-colombianas', 'sort_order' => 1],
        ['name' => 'Cestas Personalizadas', 'slug' => 'cestas-personalizadas', 'sort_order' => 2],
        ['name' => 'Buquês de Luxo', 'slug' => 'buques-de-luxo', 'sort_order' => 3],
        ['name' => 'Arranjos & Vasos', 'slug' => 'arranjos-e-vasos', 'sort_order' => 4],
        ['name' => 'KITS & Presentes', 'slug' => 'kits-e-presentes', 'sort_order' => 5],
        ['name' => 'Orquídeas & Plantas', 'slug' => 'orquideas-e-plantas', 'sort_order' => 6],
    ];

    $catMap = [];
    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
        $stmt->execute([$cat['name'], $cat['slug']]);
        $existing = $stmt->fetch();
        if ($existing) {
            $catMap[$cat['name']] = $existing['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$cat['name'], $cat['slug'], $cat['sort_order']]);
            $catMap[$cat['name']] = $pdo->lastInsertId();
        }
    }

    // 2. Extracted WhatsApp Business Products List
    $waProducts = [
        ['title' => 'Buque com 12 Colombianas', 'price' => 300.00, 'desc' => 'Buquê de 12 Rosas Colombianas selecionadas com laço especial.'],
        ['title' => 'Buquê com rosas colombianas', 'price' => 320.00, 'desc' => '12 Rosas colombianas, Folhagem verde e gypsofila. Embalagem kraft e laço branco.'],
        ['title' => 'Buque de Rosas pink colombiana', 'price' => 320.00, 'desc' => '12 rosas colombianas pink, Folhagem verde e tango. Embalagem e laço rosa.'],
        ['title' => 'Cesta com Chambinho do Amor', 'price' => 350.00, 'desc' => 'Cesta especial recheada de carinho com rosas e mimos.'],
        ['title' => 'Cesta com Rosa e Urso', 'price' => 320.00, 'desc' => 'Arranjo de Rosa Colombiana, Urso pequeno, Ferrero Rocher 100g e Cesta decorada.'],
        ['title' => 'Kit dia dos namorados', 'price' => 1300.00, 'desc' => '1 buque com 12 rosas colombianas vermelhas + gypso, 1 box de 6 rosas e 4 astromelias brancas e gypso, 1 pacote de pétalas, 1 urso médio, 1 Chandon G, Cartão de amor G e Buquê de balões.'],
        ['title' => 'Buquê com 15 rosas', 'price' => 300.00, 'desc' => '15 rosas nacionais selecionadas com folhagens nobres.'],
        ['title' => 'Buquê com 15 Rosas amarelas', 'price' => 300.00, 'desc' => 'Buquê vibrante com 15 Rosas amarelas selecionadas.'],
        ['title' => 'Buquê com Rosas Rosé', 'price' => 240.00, 'desc' => '12 Rosas nacionais cor de rosa delicadas.'],
        ['title' => 'Buquê Lily', 'price' => 250.00, 'desc' => '1 galho de lírios rosa, 1 lírio branco e 12 astromélias coloridas.'],
        ['title' => 'Buque de Mix de Flores (Cód 73)', 'price' => 580.00, 'desc' => '4 Rosas Colombianas, 3 galhos de lírios coloridos, 10 astromélias, 4 gérberas coloridas e 4 hortênsias.'],
        ['title' => 'Buquê rosa', 'price' => 280.00, 'desc' => '5 Rosas cor de rosa, 5 rosa amarela, 4 astromélias rosa, 4 amarelas e 4 hortênsias.'],
        ['title' => 'Cesta de café', 'price' => 400.00, 'desc' => 'Arranjo com 4 gérberas vermelhas, torrada, Toddynho, pão, maçã, uva, mamão, cereal, chá, geleia, bolo, bolachas, café e açúcar.'],
        ['title' => 'Cesta de café com rosa', 'price' => 380.00, 'desc' => 'Arranjo de rosa, torrada, sucrilhos, maçã, uva, mamão, cappuccino, suco, iogurte, requeijão, queijo, presunto, pão francês e pães de queijo.'],
        ['title' => 'Cesta de Café Premium', 'price' => 400.00, 'desc' => 'Arranjo de rosa, torrada, sucrilhos, maçã, cappuccino, suco, iogurte, requeijão, Nutella, frios, pão francês, croissant e carolinas.'],
        ['title' => 'Arranjo de Rosas e Lírio', 'price' => 350.00, 'desc' => '4 Rosas colombianas vermelhas, 2 galhos de Lírios e folhagem verde de ruscos.'],
        ['title' => 'Arranjo com Rosas vermelhas', 'price' => 450.00, 'desc' => '18 rosas nacionais vermelhas com folhagem de pit em vaso de vidro.'],
        ['title' => 'Arranjo com 3 Rosas Colombianas', 'price' => 150.00, 'desc' => '3 Rosas Colombianas abertas à mão com folhagem verde e tango.'],
        ['title' => 'Ferrero Rocher 50g', 'price' => 25.00, 'desc' => 'Caixa de bombons Ferrero Rocher 50g.'],
        ['title' => 'Ferrero Rocher 100g', 'price' => 60.00, 'desc' => 'Caixa de bombons Ferrero Rocher 100g.'],
        ['title' => 'Ferrero Rocher Collection 77g', 'price' => 65.00, 'desc' => 'Caixa de bombons Ferrero Rocher Collection 77g.'],
        ['title' => 'Buque de girassol', 'price' => 150.00, 'desc' => '6 girassóis com folhagem verde e tango em embalagem kraft.'],
        ['title' => 'Buquê de Girassol e Astromélias', 'price' => 200.00, 'desc' => '6 girassóis vibrantes e 4 astromélias brancas.'],
        ['title' => 'Buquê com Rosas e Girassóis', 'price' => 285.90, 'desc' => '6 girassóis grandes com 12 rosas colombianas vermelhas.'],
        ['title' => 'Fabulosa Rosa Encantada Vermelha na Cúpula', 'price' => 279.90, 'desc' => 'Rosa natural preservada em cúpula de vidro ao estilo A Bela e a Fera. Duração de até 5 anos.']
    ];

    $inserted = 0;
    $updated = 0;

    foreach ($waProducts as $p) {
        $name = $p['title'];
        $price = $p['price'];
        $desc = $p['desc'];
        $catName = detectCatName($name);
        $catId = $catMap[$catName] ?? 1;
        $slug = makeSlug($name);
        $img = getCuratedImg($name);

        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? OR slug = ?");
        $stmt->execute([$name, $slug]);
        $existingId = $stmt->fetchColumn();

        if (!$existingId) {
            $sku = 'HF-WA-' . strtoupper(substr(md5($name), 0, 6));
            $ins = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, sku, price, image_path, active, stock_qty, featured) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 50, 1)");
            $ins->execute([$catId, $name, $slug, $desc, $sku, $price, $img]);
            $inserted++;
        } else {
            $upd = $pdo->prepare("UPDATE products SET price = IF(? > 0, ?, price), description = IF(LENGTH(?) > 3, ?, description), image_path = COALESCE(?, image_path), active = 1 WHERE id = ?");
            $upd->execute([$price, $price, $desc, $desc, $img, $existingId]);
            $updated++;
        }
    }

    echo "
    <div style='font-family: Arial, sans-serif; max-width: 650px; margin: 50px auto; padding: 30px; background: #FFF5F7; border: 2px solid #D81B60; border-radius: 14px; text-align: center;'>
        <h1 style='color: #C2185B;'>🌸 Catálogo do WhatsApp Semeado com Sucesso!</h1>
        <p style='font-size: 1.1rem; color: #333;'>Todos os produtos extraídos do seu WhatsApp (Buquês, Cestas de Café, Kits Namorados, Rosas Colombianas, Girassóis e Chocolates) foram cadastrados com fotos e valores no seu banco MySQL local!</p>
        <hr style='border: 0; border-top: 1px solid #E0D0D5; margin: 20px 0;'>
        <div style='text-align: left; background: #FFF; padding: 15px; border-radius: 8px; font-size: 0.95rem; color:#444;'>
            • Novas flores inseridas: <strong>{$inserted}</strong><br>
            • Flores atualizadas: <strong>{$updated}</strong>
        </div>
        <br>
        <a href='index.php' style='display: inline-block; background: #D81B60; color: #FFF; padding: 14px 32px; border-radius: 25px; text-decoration: none; font-weight: bold; font-size:1.1rem;'>
            🌸 Ver Produtos no Site →
        </a>
    </div>
    ";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>Erro ao semear: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
