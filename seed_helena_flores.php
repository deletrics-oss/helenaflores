<?php
/**
 * seed_helena_flores.php — Helena Flores
 * Script de 1-Clique para popular e inicializar o catálogo completo Helena Flores no banco de dados.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<div style='font-family: sans-serif; padding: 25px; max-width: 850px; margin: 20px auto; background: #FFFDFB; border: 2px solid #8B263E; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>";
echo "<h1 style='color: #8B263E; font-family: Georgia, serif;'>🌹 Helena Flores — Semeador de Catálogo & Banners</h1>";

try {
    // 1. Atualizar ou Inserir Configurações do Site
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (site_name, whatsapp_phone, admin_email, banner_title, banner_subtitle) 
                    VALUES ('Helena Flores', '5511986727872', 'contato@helenafloresjardins.com.br', 'Helena Flores - Atelier & Floricultura', 'Há mais de 11 anos cultivando emoções nos Jardins em São Paulo')");
    } else {
        $pdo->exec("UPDATE settings SET 
                    site_name = 'Helena Flores', 
                    whatsapp_phone = '5511986727872',
                    banner_title = 'Helena Flores - Atelier & Floricultura',
                    banner_subtitle = 'Há mais de 11 anos cultivando emoções nos Jardins em São Paulo'
                    WHERE id = 1");
    }
    echo "<p style='color: green;'>✅ Configurações da loja atualizadas!</p>";

    // 2. Criar Banners Grandes no Carrossel da Home
    $pdo->exec("CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        title VARCHAR(100),
        subtitle VARCHAR(255),
        link_url VARCHAR(255),
        display_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $defaultBanners = [
        [
            'image_path' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=1600&q=80',
            'title' => 'Helena Flores - Atelier nos Jardins',
            'subtitle' => 'Buquês de Rosas Colombianas, Cestas Personalizadas e Arranjos de Luxo em SP',
            'link_url' => '#produtos'
        ],
        [
            'image_path' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=1600&q=80',
            'title' => 'Rosas Colombianas Premium',
            'subtitle' => 'Seleção exclusiva com 12 a 24 rosas de haste longa e embalagem para presente',
            'link_url' => '?cat=1'
        ],
        [
            'image_path' => 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=1600&q=80',
            'title' => 'Cestas & Kits Especiais de Presente',
            'subtitle' => 'Chocolates nobres, pelúcias carinhosas e vinhos selecionados',
            'link_url' => '?cat=2'
        ]
    ];

    $pdo->exec("TRUNCATE TABLE banners");
    foreach ($defaultBanners as $b) {
        $insB = $pdo->prepare("INSERT INTO banners (image_path, title, subtitle, link_url, active) VALUES (?, ?, ?, ?, 1)");
        $insB->execute([$b['image_path'], $b['title'], $b['subtitle'], $b['link_url']]);
    }
    echo "<p style='color: green;'>✅ Banners Grandes Ativados no Carrossel da Home!</p>";

    // 3. Criar Categorias
    $categories = [
        ['name' => 'Rosas Colombianas', 'slug' => 'rosas-colombianas', 'sort' => 1],
        ['name' => 'Cestas Personalizadas', 'slug' => 'cestas-personalizadas', 'sort' => 2],
        ['name' => 'Buquês de Luxo', 'slug' => 'buques-de-luxo', 'sort' => 3],
        ['name' => 'Arranjos & Vasos', 'slug' => 'arranjos-vasos', 'sort' => 4],
        ['name' => 'KITS & Presentes', 'slug' => 'kits-presentes', 'sort' => 5],
        ['name' => 'Orquídeas & Plantas', 'slug' => 'orquideas-plantas', 'sort' => 6],
    ];

    $catMap = [];
    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$cat['slug']]);
        $existing = $stmt->fetchColumn();

        if (!$existing) {
            $ins = $pdo->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)");
            $ins->execute([$cat['name'], $cat['slug'], $cat['sort']]);
            $catMap[$cat['slug']] = $pdo->lastInsertId();
        } else {
            $catMap[$cat['slug']] = $existing;
        }
    }

    // 4. Cadastrar Produtos Reais do Catálogo Helena Flores (Incluindo novos Cód 73, 95 e 61)
    $products = [
        [
            'cat' => 'buques-de-luxo',
            'name' => 'Buquê Premium com Mix de Flores (Cód 73)',
            'slug' => 'buque-premium-mix-de-flores-cod-73',
            'desc' => '4 Rosas Colombianas, 3 galhos de lírios coloridos, 10 astromélias, 4 gérberas coloridas e folhagens nobres em embalagem especial de presente.',
            'sku' => 'HF-BUQ-73',
            'price' => 580.00,
            'image' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'arranjos-vasos',
            'name' => 'Arranjo de Astromélia coloridas (Cód 95)',
            'slug' => 'arranjo-de-astromelia-coloridas-cod-95',
            'desc' => '20 galhos de astromélias coloridas selecionadas, folhagem verde e vaso de vidro transparente (cerca de 45cm de altura).',
            'sku' => 'HF-ARR-95',
            'price' => 280.00,
            'image' => 'https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'buques-de-luxo',
            'name' => 'Buquê de Girassol e Astromélias (Cód 61)',
            'slug' => 'buque-de-girassol-e-astromelias-cod-61',
            'desc' => 'Um buquê encantador com 6 vibrantes girassóis frescos e 4 delicadas astromélias brancas envoltos em papel kraft especial.',
            'sku' => 'HF-BUQ-61',
            'price' => 200.00,
            'image' => 'https://images.unsplash.com/photo-1597848212624-a19eb35e2651?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'rosas-colombianas',
            'name' => 'Buquê com 12 Colombianas',
            'slug' => 'buque-com-12-colombianas',
            'desc' => 'Buquê clássico e sofisticado com 12 Rosas Colombianas Vermelhas de haste longa, embalagem especial de presente e acabamento em laço de cetim.',
            'sku' => 'HF-BUQ-12COL',
            'price' => 300.00,
            'image' => 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'rosas-colombianas',
            'name' => 'Buquê com rosas colombianas',
            'slug' => 'buque-com-rosas-colombianas-misto',
            'desc' => '12 Rosas colombianas nobres, folhagem verde refrescante de eucalipto e delicadas gypsofilas (mosquitinho). Embalagem premium.',
            'sku' => 'HF-BUQ-12MIS',
            'price' => 320.00,
            'image' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'cestas-personalizadas',
            'name' => 'Cesta com Chambinho do Amor',
            'slug' => 'cesta-com-chambinho-do-amor',
            'desc' => 'Cesta encantadora de vime contendo Ursinho de Pelúcia macio, rosas colombianas vermelhas frescas e caixa de deliciosos chocolates.',
            'sku' => 'HF-CST-CHAMB',
            'price' => 350.00,
            'image' => 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'cestas-personalizadas',
            'name' => 'Cesta com Rosa e Urso',
            'slug' => 'cesta-com-rosa-e-urso',
            'desc' => 'Arranjo com Rosa Colombiana aveludada, Urso carinhoso pequeno, caixa de bombons Ferrero Rocher e mimo.',
            'sku' => 'HF-CST-URSO',
            'price' => 320.00,
            'image' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=800&q=80',
            'featured' => 1
        ]
    ];

    $count = 0;
    foreach ($products as $p) {
        $catId = $catMap[$p['cat']] ?? null;

        $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
        $stmt->execute([$p['slug']]);
        if (!$stmt->fetchColumn()) {
            $ins = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, sku, price, image_path, featured, active, stock_qty) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 50)");
            $ins->execute([$catId, $p['name'], $p['slug'], $p['desc'], $p['sku'], $p['price'], $p['image'], $p['featured']]);
            $count++;
        }
    }

    echo "<p style='color: green;'>✅ Total de <strong>{$count}</strong> novos produtos de rosas, arranjos e girassóis cadastrados!</p>";
    echo "<hr style='border:1px dashed #DDD;'>";
    echo "<p style='font-size:1.1rem;'>🎉 <strong>Catálogo e Banners de Helena Flores 100% Ativos!</strong></p>";
    echo "<a href='index.php' style='display: inline-block; padding: 14px 28px; background: #8B263E; color: white; border-radius: 30px; text-decoration: none; font-weight: bold;'>🌸 Acessar o Site Helena Flores Agora →</a>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao popular banco: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
