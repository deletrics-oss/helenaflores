<?php
/**
 * seed_helena_flores.php — Helena Flores
 * Script para popular automaticamente o banco de dados com dados e produtos reais do catálogo.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<div style='font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;'>";
echo "<h1 style='color: #8B263E;'>🌹 Helena Flores — Semeador de Dados do Catálogo</h1>";

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
    echo "<p style='color: green;'>✅ Configurações da loja atualizadas com sucesso!</p>";

    // 2. Criar Categorias
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
    echo "<p style='color: green;'>✅ Categorias de Flores & Presentes verificadas/criadas!</p>";

    // 3. Cadastrar Produtos Reais do Catálogo Helena Flores
    $products = [
        [
            'cat' => 'rosas-colombianas',
            'name' => 'Buquê com 12 Colombianas',
            'slug' => 'buque-com-12-colombianas',
            'desc' => 'Buquê clássico e sofisticado com 12 Rosas Colombianas Vermelhas de haste longa, embalagem especial de presente e acabamento em laço de cetim.',
            'sku' => 'HF-BUQ-12COL',
            'price' => 300.00,
            'image' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'rosas-colombianas',
            'name' => 'Buquê com rosas colombianas',
            'slug' => 'buque-com-rosas-colombianas-misto',
            'desc' => '12 Rosas colombianas nobres, folhagem verde refrescante de eucalipto e delicadas gypsofilas (mosquitinho). Embalagem premium em tom nude.',
            'sku' => 'HF-BUQ-12MIS',
            'price' => 320.00,
            'image' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'rosas-colombianas',
            'name' => 'Buquê de Rosas pink colombiana',
            'slug' => 'buque-de-rosas-pink-colombiana',
            'desc' => '12 rosas colombianas na deslumbrante cor Pink, folhagem verde e tango. Embalagem floral elegante com laço rosa premium.',
            'sku' => 'HF-BUQ-12PNK',
            'price' => 320.00,
            'image' => 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'cestas-personalizadas',
            'name' => 'Cesta com Chambinho do Amor',
            'slug' => 'cesta-com-chambinho-do-amor',
            'desc' => 'Cesta encantadora de vime contendo Ursinho de Pelúcia macio, rosas colombianas vermelhas frescas e caixa de deliciosos chocolates selecionados.',
            'sku' => 'HF-CST-CHAMB',
            'price' => 350.00,
            'image' => 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'cestas-personalizadas',
            'name' => 'Cesta com Rosa e Urso',
            'slug' => 'cesta-com-rosa-e-urso',
            'desc' => 'Arranjo com Rosa Colombiana aveludada, Urso carinhoso pequeno, caixa de bombons Ferrero Rocher e cartão personalizado de presente.',
            'sku' => 'HF-CST-URSO',
            'price' => 320.00,
            'image' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'kits-presentes',
            'name' => 'Kit Dia dos Namorados & Romântico',
            'slug' => 'kit-dia-dos-namorados-romantico',
            'desc' => 'Kit romântico completo com arranjo especial de 18 rosas colombianas vermelhas em vaso de vidro, caixa Ferrero Rocher 12 un e vinho/espumante selecionado.',
            'sku' => 'HF-KIT-ROMAN',
            'price' => 380.00,
            'image' => 'https://images.unsplash.com/photo-1533616688419-b7a585564566?w=800&q=80',
            'featured' => 1
        ],
        [
            'cat' => 'orquideas-plantas',
            'name' => 'Orquídea Phalaenopsis Premium em Vaso',
            'slug' => 'orquidea-phalaenopsis-premium',
            'desc' => 'Orquídea Phalaenopsis nobre com duas hastes floridas em charmoso vaso de cerâmica artesanal e acabamento com musgo natural.',
            'sku' => 'HF-ORQ-PHAL',
            'price' => 290.00,
            'image' => 'https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?w=800&q=80',
            'featured' => 0
        ],
        [
            'cat' => 'arranjos-vasos',
            'name' => 'Arranjo de Flores do Campo e Eucalipto',
            'slug' => 'arranjo-flores-do-campo',
            'desc' => 'Delicado arranjo composto por mix de flores do campo coloridas, eucalipto perfumado e gypsofilas montado em vaso cylindro de vidro transparente.',
            'sku' => 'HF-ARR-CAMPO',
            'price' => 260.00,
            'image' => 'https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=800&q=80',
            'featured' => 0
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

    echo "<p style='color: green;'>✅ Total de <strong>{$count}</strong> novos produtos do catálogo Helena Flores cadastrados no banco!</p>";
    echo "<hr>";
    echo "<p>🎉 <strong>Catálogo Helena Flores pronto para operação!</strong></p>";
    echo "<a href='index.php' style='display: inline-block; padding: 12px 24px; background: #8B263E; color: white; border-radius: 6px; text-decoration: none; font-weight: bold;'>Acessar o Site Helena Flores →</a>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao popular banco: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
