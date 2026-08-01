<?php
/**
 * index.php — Helena Flores (Loja Principal Estilo Giuliana Flores)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// Params
$catId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Query Builder
$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1";
$params = [];

if ($catId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catId;
}

if (!empty($query)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%{$query}%";
    $params[] = "%{$query}%";
}

$sql .= " ORDER BY p.featured DESC, p.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
}

// Banners for Hero Slider
$banners = [];
try {
    $banners = $pdo->query("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC, id DESC")->fetchAll();
} catch (Exception $e) {}

// Fallback Banner Slides if table empty
$banner_slides = !empty($banners) ? $banners : [
    [
        'image' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=1600&q=80',
        'title' => 'Buquês Exclusivos de Rosas Colombianas',
        'subtitle' => 'Entregas Rápidas no Mesmo Dia nos Jardins & São Paulo',
        'link' => '#produtos'
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=1600&q=80',
        'title' => 'Cestas de Café da Manhã Gourmet',
        'subtitle' => 'Surpreenda com Frutas Frescas, Chocolates & Rosas',
        'link' => '?cat=2'
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=1600&q=80',
        'title' => 'Arranjos Especiais com Lírios & Astromélias',
        'subtitle' => 'A Beleza Única das Flores Naturais Selecionadas',
        'link' => '?cat=4'
    ]
];

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Helena Flores | Floricultura & Cestas nos Jardins SP</title>
    <meta name="description" content="Floricultura Helena Flores nos Jardins, SP. Envie Buquês de Rosas Colombianas, Cestas de Café da Manhã, Orquídeas e Kits Especiais com Entrega no Mesmo Dia!">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .gf-hero-slider {
            position: relative; width: 100%; height: 380px; overflow: hidden; background: #FFF8F9;
        }
        .gf-slide {
            position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; transition: opacity 0.8s ease-in-out;
            background-size: cover; background-position: center; display: flex; align-items: center; justify-content: flex-start;
            padding: 0 5%;
        }
        .gf-slide.active { opacity: 1; z-index: 2; }
        .gf-slide-card {
            background: rgba(255, 255, 255, 0.95); padding: 2.2rem; border-radius: 20px; max-width: 520px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(216, 27, 96, 0.15); backdrop-filter: blur(5px);
        }
        .gf-slide-card h2 {
            font-size: 1.9rem; font-weight: 800; color: var(--gf-magenta-dark); margin-bottom: 8px; line-height: 1.25;
        }
        .gf-slide-card p {
            font-size: 0.95rem; color: #444; margin-bottom: 18px; line-height: 1.5;
        }
        .gf-slide-tag {
            display: inline-block; background: var(--gf-magenta-light); color: var(--gf-magenta-dark); font-weight: 700;
            font-size: 0.78rem; padding: 4px 12px; border-radius: 20px; margin-bottom: 10px; text-transform: uppercase;
        }
        @media (max-width: 768px) {
            .gf-hero-slider { height: 300px; }
            .gf-slide-card { padding: 1.2rem; max-width: 100%; }
            .gf-slide-card h2 { font-size: 1.3rem; }
            .gf-slide-card p { font-size: 0.85rem; margin-bottom: 12px; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <!-- Large Giuliana Style Hero Slider (10 to 15 Items Carousel) -->
    <div class="gf-hero-slider">
        <?php foreach ($banner_slides as $index => $slide):
            $img = $slide['image_path'] ?? $slide['image'];
            if (strpos($img, 'http') === false) {
                $img = $baseUrl . '/' . ltrim($img, '/');
            }
            $ttl = $slide['title'] ?? 'Helena Flores';
            $sub = $slide['subtitle'] ?? 'Atelier & Floricultura nos Jardins';
            $lnk = $slide['link_url'] ?? '#produtos';
            ?>
            <div class="gf-slide <?php echo $index === 0 ? 'active' : ''; ?>"
                 style="background-image: linear-gradient(90deg, rgba(255,255,255,0.92) 0%, rgba(255,255,255,0.3) 100%), url('<?php echo $img; ?>');">
                <div class="gf-slide-card">
                    <span class="gf-slide-tag">🌹 Entregas no Mesmo Dia em Jardins & SP</span>
                    <h2><?php echo htmlspecialchars($ttl); ?></h2>
                    <p><?php echo htmlspecialchars($sub); ?></p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="<?php echo $lnk; ?>" class="gf-btn-primary">
                            🌸 Ver Oferta
                        </a>
                        <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20fazer%20um%20pedido%20pelo%20WhatsApp" 
                           target="_blank" 
                           class="gf-btn-whatsapp">
                            💬 Pedir via WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Carousel Navigation Controls -->
        <button onclick="prevSlide()" style="position:absolute; left:15px; top:50%; transform:translateY(-50%); z-index:10; background:rgba(255,255,255,0.8); border:none; width:40px; height:40px; border-radius:50%; font-size:1.2rem; cursor:pointer; box-shadow:0 4px 10px rgba(0,0,0,0.15);">❮</button>
        <button onclick="nextSlide()" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); z-index:10; background:rgba(255,255,255,0.8); border:none; width:40px; height:40px; border-radius:50%; font-size:1.2rem; cursor:pointer; box-shadow:0 4px 10px rgba(0,0,0,0.15);">❯</button>
    </div>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.gf-slide');
        
        function showSlide(index) {
            slides[currentSlide].classList.remove('active');
            currentSlide = (index + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
        }

        function nextSlide() { showSlide(currentSlide + 1); }
        function prevSlide() { showSlide(currentSlide - 1); }

        if (slides.length > 1) {
            setInterval(nextSlide, 3500);
        }
    </script>

    <!-- Main Content Container -->
    <div style="max-width:1240px; margin: 2rem auto; padding: 0 20px; flex:1;" id="produtos">

        <!-- GIULIANA STYLE CIRCULAR CATEGORIES (STORY CIRCLES) -->
        <div class="gf-circles-container">
            <a href="?cat=1" class="gf-circle-item">
                <div class="gf-circle-img">
                    <img src="https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=300&q=80" alt="Rosas Encantadas">
                </div>
                <span class="gf-circle-label">Rosas Encantadas</span>
            </a>
            <a href="?cat=3" class="gf-circle-item">
                <div class="gf-circle-img">
                    <img src="https://images.unsplash.com/photo-1591886960571-74d43a9d4166?w=300&q=80" alt="Girassol">
                </div>
                <span class="gf-circle-label">Girassóis</span>
            </a>
            <a href="?cat=4" class="gf-circle-item">
                <div class="gf-circle-img">
                    <img src="https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=300&q=80" alt="Arranjos">
                </div>
                <span class="gf-circle-label">Arranjos</span>
            </a>
            <a href="?cat=3" class="gf-circle-item">
                <div class="gf-circle-img">
                    <img src="https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=300&q=80" alt="Buquês de Rosas">
                </div>
                <span class="gf-circle-label">Buquês de Rosas</span>
            </a>
            <a href="?cat=5" class="gf-circle-item">
                <div class="gf-circle-img">
                    <img src="https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=300&q=80" alt="Aniversário">
                </div>
                <span class="gf-circle-label">Aniversário</span>
            </a>
            <a href="?cat=2" class="gf-circle-item">
                <div class="gf-circle-img">
                    <img src="https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=300&q=80" alt="Cestas">
                </div>
                <span class="gf-circle-label">Cestas</span>
            </a>
            <a href="?cat=6" class="gf-circle-item">
                <div class="gf-circle-img">
                    <img src="https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=300&q=80" alt="Orquídeas">
                </div>
                <span class="gf-circle-label">Orquídeas</span>
            </a>
        </div>

        <!-- SECTION 1: OS MAIS VENDIDOS -->
        <div class="gf-section-header">
            <h2 class="gf-section-title">🔥 OS MAIS VENDIDOS</h2>
            <div class="gf-section-sublinks">
                <a href="?cat=1">Rosas Colombianas</a>
                <a href="?cat=2">Cestas</a>
                <a href="?cat=3">Buquês de Luxo</a>
            </div>
        </div>

        <div class="gf-product-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach (array_slice($products, 0, 4) as $p): ?>
                    <?php 
                    $img = get_product_image_url($p['image_path'], $p['name']);
                    $oldPrice = $p['price'] * 1.20;
                    $installment = $p['price'] / 3;
                    ?>
                    <div class="gf-product-card">
                        <span class="gf-badge-discount">-15% OFF</span>
                        <a href="product.php?id=<?php echo $p['id']; ?>" class="gf-product-img">
                            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                        </a>
                        <div class="gf-product-body">
                            <span class="gf-product-code">Cód: <?php echo htmlspecialchars($p['sku'] ?: $p['id']); ?></span>
                            <a href="product.php?id=<?php echo $p['id']; ?>" class="gf-product-title" style="text-decoration:none;">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </a>
                            <div class="gf-product-desc">
                                <?php echo htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 75, '...')); ?>
                            </div>

                            <div class="gf-price-container">
                                <div>
                                    <span class="gf-old-price">R$ <?php echo number_format($oldPrice, 2, ',', '.'); ?></span>
                                    <span class="gf-price-val">R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></span>
                                </div>
                                <div style="font-size:0.75rem; color:#666; font-weight:600; margin-top:2px;">
                                    3x de <strong>R$ <?php echo number_format($installment, 2, ',', '.'); ?></strong> sem juros
                                </div>
                            </div>

                            <div class="gf-card-actions" style="display:flex; gap:8px; align-items:center;">
                                <form action="cart.php" method="POST" style="flex:1;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button class="gf-btn-buy" type="submit">Comprar 🛒</button>
                                </form>
                                <button onclick="addCartAjax(event, <?php echo $p['id']; ?>)" type="button" title="Adicionar 1 ao Carrinho" 
                                        style="width:42px; height:42px; border-radius:50%; background:#25D366; color:#FFF; border:none; font-size:1.5rem; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(37,211,102,0.3); transition:transform 0.2s ease;">
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- WHATSAPP ORDER PROMO STRIP BANNER -->
        <div class="gf-promo-banner-strip">
            <div>
                <span style="background:rgba(255,255,255,0.25); padding:4px 12px; border-radius:15px; font-weight:700; font-size:0.8rem;">
                    💬 ATENDIMENTO PERSONALIZADO
                </span>
                <h3 style="margin-top:8px;">Faça seu pedido diretamente pelo WhatsApp Business!</h3>
                <p>Nossa equipe nos Jardins monta o seu buquê ou cesta em tempo real com envio imediato.</p>
            </div>
            <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20fazer%20um%20pedido%20personalizado" 
               target="_blank" 
               class="gf-btn-whatsapp" 
               style="font-size:1.1rem; padding:14px 30px; box-shadow:0 4px 15px rgba(0,0,0,0.2);">
                💬 Chamar no WhatsApp (11) 98672-7872
            </a>
        </div>

        <!-- TIPOS DE FLORES PASTEL TILES (GIULIANA STYLE) -->
        <div class="gf-section-header">
            <h2 class="gf-section-title">🌸 TIPOS DE FLORES</h2>
        </div>
        <div class="gf-flower-types-grid">
            <a href="?q=rosas" class="gf-flower-tile" style="background:#FFEEDD;">
                <span class="tile-title">Rosas</span>
                <span style="font-size:2rem;">🌹</span>
            </a>
            <a href="?q=margaridas" class="gf-flower-tile" style="background:#E0F7FA;">
                <span class="tile-title">Margaridas</span>
                <span style="font-size:2rem;">🌼</span>
            </a>
            <a href="?q=orquídeas" class="gf-flower-tile" style="background:#F3E5F5;">
                <span class="tile-title">Orquídeas</span>
                <span style="font-size:2rem;">🪻</span>
            </a>
            <a href="?q=secas" class="gf-flower-tile" style="background:#EDE7F6;">
                <span class="tile-title">Flores Secas</span>
                <span style="font-size:2rem;">🌾</span>
            </a>
            <a href="?q=plantas" class="gf-flower-tile" style="background:#E8F5E9;">
                <span class="tile-title">Plantas</span>
                <span style="font-size:2rem;">🪴</span>
            </a>
            <a href="?q=campo" class="gf-flower-tile" style="background:#FCE4EC;">
                <span class="tile-title">Flores do Campo</span>
                <span style="font-size:2rem;">💐</span>
            </a>
            <a href="?q=lírios" class="gf-flower-tile" style="background:#FFF3E0;">
                <span class="tile-title">Lírios</span>
                <span style="font-size:2rem;">🌺</span>
            </a>
            <a href="?q=astromélias" class="gf-flower-tile" style="background:#FFEBEE;">
                <span class="tile-title">Astromélias</span>
                <span style="font-size:2rem;">🌸</span>
            </a>
            <a href="?q=girassóis" class="gf-flower-tile" style="background:#FFFDE7;">
                <span class="tile-title">Girassóis</span>
                <span style="font-size:2rem;">🌻</span>
            </a>
        </div>

        <!-- SECTION 2: COMBO PROMO BANNER + PRODUCTS (GIULIANA STYLE) -->
        <div class="gf-section-header">
            <h2 class="gf-section-title">💐 BUQUÊS DE FLORES E ARRANJOS EXCLUSIVOS</h2>
            <div class="gf-section-sublinks">
                <a href="?cat=1">Rosas Vermelhas</a>
                <a href="?cat=4">Flores do Campo</a>
                <a href="?cat=6">Orquídeas</a>
            </div>
        </div>

        <div class="gf-banner-grid-combo">
            <!-- Side Banner Card (Giuliana Style) -->
            <div class="gf-side-banner" style="background-image: url('https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=800&q=80');">
                <div class="gf-side-banner-overlay"></div>
                <div class="gf-side-banner-content">
                    <div>
                        <span style="color:#FFECB3; font-size:0.8rem; font-weight:800; text-transform:uppercase; letter-spacing:1px;">PARA SURPREENDER</span>
                        <h2 style="font-family:'Playfair Display', serif; font-size:2.2rem; margin:10px 0; color:#FFF;">Buquês de Flores</h2>
                        <p style="font-size:0.9rem; color:#FCE4EC;">Flores frescas montadas à mão por floristas artesanais nos Jardins.</p>
                    </div>
                    <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Quero%20montar%20um%20buqu%C3%AA%20personalizado" 
                       target="_blank" 
                       style="background:#FFC107; color:#000; font-weight:800; padding:12px; border-radius:25px; text-decoration:none; text-transform:uppercase; font-size:0.9rem; display:block; margin-top:20px; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
                       CONFIRA →
                    </a>
                </div>
            </div>

            <!-- 3 Products Grid Next to Banner -->
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:18px;">
                <?php foreach (array_slice($products, 4, 6) as $p): ?>
                    <?php 
                    $img = get_product_image_url($p['image_path'], $p['name']);
                    $oldPrice = $p['price'] * 1.25;
                    $installment = $p['price'] / 3;
                    ?>
                    <div class="gf-product-card">
                        <span class="gf-badge-discount">-20% OFF</span>
                        <a href="product.php?id=<?php echo $p['id']; ?>" class="gf-product-img">
                            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                        </a>
                        <div class="gf-product-body">
                            <span class="gf-product-code">Cód: <?php echo htmlspecialchars($p['sku'] ?: $p['id']); ?></span>
                            <a href="product.php?id=<?php echo $p['id']; ?>" class="gf-product-title" style="text-decoration:none;">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </a>
                            <div class="gf-product-desc">
                                <?php echo htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 75, '...')); ?>
                            </div>

                            <div class="gf-price-container">
                                <div>
                                    <span class="gf-old-price">R$ <?php echo number_format($oldPrice, 2, ',', '.'); ?></span>
                                    <span class="gf-price-val">R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></span>
                                </div>
                                <div style="font-size:0.75rem; color:#666; font-weight:600; margin-top:2px;">
                                    3x de <strong>R$ <?php echo number_format($installment, 2, ',', '.'); ?></strong> sem juros
                                </div>
                            </div>

                            <div class="gf-card-actions" style="display:flex; gap:8px; align-items:center;">
                                <form action="cart.php" method="POST" style="flex:1;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button class="gf-btn-buy" type="submit">Comprar 🛒</button>
                                </form>
                                <form action="cart.php" method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" title="Adicionar 1 ao Carrinho" 
                                            style="width:42px; height:42px; border-radius:50%; background:#25D366; color:#FFF; border:none; font-size:1.5rem; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(37,211,102,0.3);">
                                        +
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- FULL PRODUCT CATALOG GRID -->
        <div class="gf-section-header" style="margin-top:3rem;">
            <h2 class="gf-section-title">🌹 TODOS OS PRODUTOS & PRESENTES</h2>
        </div>
        <div class="gf-product-grid">
            <?php foreach (array_slice($products, 10) as $p): ?>
                <?php 
                $img = get_product_image_url($p['image_path'], $p['name']);
                $oldPrice = $p['price'] * 1.15;
                $installment = $p['price'] / 3;
                ?>
                <div class="gf-product-card">
                    <a href="product.php?id=<?php echo $p['id']; ?>" class="gf-product-img">
                        <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                    </a>
                    <div class="gf-product-body">
                        <span class="gf-product-code">Cód: <?php echo htmlspecialchars($p['sku'] ?: $p['id']); ?></span>
                        <a href="product.php?id=<?php echo $p['id']; ?>" class="gf-product-title" style="text-decoration:none;">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </a>
                        <div class="gf-product-desc">
                            <?php echo htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 75, '...')); ?>
                        </div>

                        <div class="gf-price-container">
                            <div>
                                <span class="gf-old-price">R$ <?php echo number_format($oldPrice, 2, ',', '.'); ?></span>
                                <span class="gf-price-val">R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></span>
                            </div>
                            <div style="font-size:0.75rem; color:#666; font-weight:600; margin-top:2px;">
                                3x de <strong>R$ <?php echo number_format($installment, 2, ',', '.'); ?></strong> sem juros
                            </div>
                        </div>

                        <div class="gf-card-actions">
                            <form action="cart.php" method="POST" style="flex:1;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="qty" value="1">
                                <button class="gf-btn-buy">Comprar 🛒</button>
                            </form>
                            <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20pedir%20o%20<?php echo urlencode($p['name']); ?>%20(R$%20<?php echo number_format($p['price'], 2, ',', '.'); ?>)" 
                               target="_blank" class="gf-btn-wa-icon" title="Pedir no WhatsApp">
                                💬
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Toast Notification Container & Ajax Add to Cart Script -->
    <div id="cartToast" style="position:fixed; bottom:30px; right:30px; background:#C2185B; color:#FFF; padding:15px 25px; border-radius:30px; font-weight:800; font-size:1rem; box-shadow:0 10px 25px rgba(194,24,91,0.4); display:none; z-index:999999;">
        🌸 Produto adicionado ao Carrinho!
    </div>

    <script>
        function addCartAjax(event, productId) {
            if (event) event.preventDefault();
            
            fetch(`cart.php?action=ajax_add&product_id=${productId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const toast = document.getElementById('cartToast');
                        toast.style.display = 'block';
                        toast.innerText = `🌸 Produto adicionado ao Carrinho! (${data.total_count})`;
                        
                        setTimeout(() => {
                            toast.style.display = 'none';
                        }, 2500);

                        // Update Cart Header Buttons
                        const cartBtns = document.querySelectorAll('a[href*="cart.php"]');
                        cartBtns.forEach(btn => {
                            if (btn.innerText.includes('Carrinho')) {
                                btn.innerText = `🛒 Carrinho (${data.total_count})`;
                            }
                        });
                    }
                })
                .catch(() => {
                    window.location.href = `cart.php?action=add&product_id=${productId}`;
                });
        }
    </script>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>