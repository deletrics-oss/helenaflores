<?php
/**
 * seed_helena_flores.php — Semeador de Produtos, Banners e Cliente de Teste Helena Flores
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // 1. Ensure Table Structure & Categories
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
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$cat['name']]);
        $existing = $stmt->fetch();
        if ($existing) {
            $catMap[$cat['name']] = $existing['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$cat['name'], $cat['slug'], $cat['sort_order']]);
            $catMap[$cat['name']] = $pdo->lastInsertId();
        }
    }

    // 2. Real Helena Flores & WhatsApp Catalog Products
    $products = [
        [
            'name' => 'Caixa Surpresa com Rosas Colombianas Glam',
            'sku' => 'HF-001',
            'category' => 'Rosas Colombianas',
            'price' => 389.90,
            'description' => 'Caixa exclusiva cartonada com 18 Rosas Colombianas vermelhas selecionadas e acabamento de cetim de luxo.',
            'image' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Buquê Partitura Flores do Campo',
            'sku' => 'HF-002',
            'category' => 'Buquês de Luxo',
            'price' => 99.90,
            'description' => 'Buquê artesanal com margaridas, gérberas e astromélias envolto em embalagem kraft de partitura vintage.',
            'image' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Admiração de Astromélias Coloridas no Vaso',
            'sku' => 'HF-003',
            'category' => 'Arranjos & Vasos',
            'price' => 169.90,
            'description' => 'Arranjo alegre de astromélias coloridas em vaso de vidro transparente com laço de cetim.',
            'image' => 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Buquê de Girassol e Rosas Vermelhas',
            'sku' => 'HF-004',
            'category' => 'Buquês de Luxo',
            'price' => 285.90,
            'description' => 'Combinação vibrante de 6 girassóis grandes com 12 rosas vermelhas colombianas.',
            'image' => 'https://images.unsplash.com/photo-1591886960571-74d43a9d4166?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Luxuosas Astromélias Coloridas no Vaso',
            'sku' => 'HF-005',
            'category' => 'Arranjos & Vasos',
            'price' => 299.90,
            'description' => 'Arranjo com mais de 25 galhos de astromélias selecionadas em vaso cilíndrico de cristal.',
            'image' => 'https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Coração de Bombom e Stitch',
            'sku' => 'HF-006',
            'category' => 'KITS & Presentes',
            'price' => 198.90,
            'description' => 'Caixa em formato de coração recheada com bombons variados e pelúcia oficial Stitch.',
            'image' => 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=800&q=80',
            'featured' => 0
        ],
        [
            'name' => 'Fabulosa Rosa Encantada Vermelha na Cúpula',
            'sku' => 'HF-007',
            'category' => 'Rosas Colombianas',
            'price' => 279.90,
            'description' => 'Rosa natural preservada em cúpula de vidro ao estilo A Bela e a Fera. Duração de até 5 anos.',
            'image' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Buquê Premium com Mix de Flores (Cód 73)',
            'sku' => 'COD-73',
            'category' => 'Buquês de Luxo',
            'price' => 580.00,
            'description' => '4 Rosas Colombianas, 3 galhos de lírios, 10 astromélias, 4 gérberas coloridas e folhagens nobres.',
            'image' => 'https://images.unsplash.com/photo-1567696911980-2eed69a46042?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Arranjo de Astromélia coloridas (Cód 95)',
            'sku' => 'COD-95',
            'category' => 'Arranjos & Vasos',
            'price' => 280.00,
            'description' => '20 galhos de astromélias coloridas em vaso de vidro com laço especial.',
            'image' => 'https://images.unsplash.com/photo-1508610048659-a06b669e3321?w=800&q=80',
            'featured' => 1
        ],
        [
            'name' => 'Buquê de Girassol e Astromélias (Cód 61)',
            'sku' => 'COD-61',
            'category' => 'Buquês de Luxo',
            'price' => 200.00,
            'description' => '6 girassóis vibrantes e 4 astromélias brancas envoltos em papel de presente refinado.',
            'image' => 'https://images.unsplash.com/photo-1597848212624-a19eb35e2651?w=800&q=80',
            'featured' => 1
        ],
    ];

    $productIds = [];
    foreach ($products as $p) {
        $catId = $catMap[$p['category']] ?? 1;
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? OR sku = ?");
        $stmt->execute([$p['name'], $p['sku']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, price = ?, description = ?, image_path = ?, featured = ?, active = 1 WHERE id = ?");
            $stmt->execute([$catId, $p['price'], $p['description'], $p['image'], $p['featured'], $existing['id']]);
            $productIds[] = $existing['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (category_id, name, sku, price, description, image_path, featured, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$catId, $p['name'], $p['sku'], $p['price'], $p['description'], $p['image'], $p['featured']]);
            $productIds[] = $pdo->lastInsertId();
        }
    }

    // 3. Seed Fake Customer for DB Testing
    $fakeEmail = 'cliente@helenaflores.com.br';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$fakeEmail]);
    $fakeUser = $stmt->fetch();

    if (!$fakeUser) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            'Cliente Helena Flores',
            $fakeEmail,
            '5511986727872',
            password_hash('cliente123', PASSWORD_DEFAULT),
            'Alameda Jaú, 1777, Jardim Paulista, São Paulo/SP'
        ]);
        $fakeUserId = $pdo->lastInsertId();
    } else {
        $fakeUserId = $fakeUser['id'];
    }

    // 4. Seed Test Order for Tag & Order Table Testing
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders");
    $orderCnt = $stmt->fetch()['cnt'];

    if ($orderCnt == 0 && !empty($productIds)) {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, shipping_address, created_at) VALUES (?, ?, 'pending', 'whatsapp', ?, NOW())");
        $stmt->execute([
            $fakeUserId,
            389.90,
            'Alameda Jaú, 1777, Jardim Paulista, São Paulo/SP'
        ]);
        $orderId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, 1, 389.90)");
        $stmt->execute([$orderId, $productIds[0]]);
    }

    // 5. Seed Banners
    $banners = [
        [
            'title' => 'Rosas Colombianas Selecionadas',
            'subtitle' => 'Há mais de 11 anos cultivando emoções nos Jardins em São Paulo.',
            'image' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=1600&q=80',
            'link_url' => '#produtos',
            'active' => 1
        ],
        [
            'title' => 'Cestas de Café & Presentes de Luxo',
            'subtitle' => 'Entregas expressas no mesmo dia para surpreender quem você ama.',
            'image' => 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=1600&q=80',
            'link_url' => '#produtos',
            'active' => 1
        ]
    ];

    try {
        foreach ($banners as $idx => $b) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO banners (title, subtitle, image_path, link_url, display_order, active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$b['title'], $b['subtitle'], $b['image'], $b['link_url'], $idx + 1, $b['active']]);
        }
    } catch (Exception $e) {}

    echo "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 30px; background: #FFF5F7; border: 2px solid #D81B60; border-radius: 12px; text-align: center;'>
        <h1 style='color: #C2185B;'>🌸 Catálogo Helena Flores Semeado!</h1>
        <p style='font-size: 1.1rem; color: #333;'>Todos os produtos do WhatsApp Business, categorias, banners e um cliente de teste foram cadastrados com sucesso!</p>
        <hr style='border: 0; border-top: 1px solid #E0D0D5; margin: 20px 0;'>
        <div style='text-align: left; background: #FFF; padding: 15px; border-radius: 8px; font-size: 0.9rem;'>
            <strong>📌 Cliente de Teste:</strong><br>
            • Email: <code>cliente@helenaflores.com.br</code><br>
            • Senha: <code>cliente123</code><br>
            • WhatsApp: <code>(11) 98672-7872</code>
        </div>
        <br>
        <a href='index.php' style='display: inline-block; background: #D81B60; color: #FFF; padding: 12px 28px; border-radius: 25px; text-decoration: none; font-weight: bold;'>
            🌸 Ver Loja Helena Flores →
        </a>
    </div>
    ";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>Erro ao semear: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
