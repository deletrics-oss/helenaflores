<?php
/**
 * index.php — Helena Flores (Loja & Catálogo Giuliana Style Completo)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
ob_start();

$is_logged_in = isset($_SESSION['user_id']);
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// Filter Logic
$cat_id = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build Query
$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1";
$params = [];

if ($cat_id) {
    $sql .= " AND p.category_id = :cat";
    $params[':cat'] = $cat_id;
}

if ($search) {
    $sql .= " AND (p.name LIKE :q1 OR p.description LIKE :q2 OR p.sku LIKE :q3)";
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
    $params[':q3'] = "%$search%";
}

$sql .= " ORDER BY p.featured DESC, p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// BANNERS CAROUSEL LOGIC
$banner_slides = [];
try {
    $banner_slides = $pdo->query("SELECT * FROM banners WHERE active = 1 ORDER BY display_order ASC, id DESC")->fetchAll();
} catch (Exception $e) {}

if (empty($banner_slides)) {
    $banner_slides = [
        [
            'image_path' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=1600&q=80',
            'title' => 'Rosas Colombianas Selecionadas',
            'subtitle' => 'Há mais de 11 anos cultivando emoções e carinho nos Jardins em São Paulo.',
            'link_url' => '#produtos'
        ],
        [
            'image_path' => 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?w=1600&q=80',
            'title' => 'Cestas de Café & Presentes de Luxo',
            'subtitle' => 'Entregas expressas no mesmo dia para surpreender quem você ama.',
            'link_url' => '#produtos'
        ],
        [
            'image_path' => 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?w=1600&q=80',
            'title' => 'Arranjos e Buquês Exclusivos',
            'subtitle' => 'Flores frescas colhidas diariamente com garantia de durabilidade.',
            'link_url' => '#produtos'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Helena Flores | Floricultura Online em São Paulo - Jardins</title>
    <meta name="description" content="Buquês de Rosas Colombianas, Cestas Personalizadas e Arranjos de Luxo com entrega no mesmo dia em SP. (11) 98672-7872">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .gf-section-header {
            display: flex; justify-content: space-between; align-items: baseline;
            border-bottom: 2px solid #F0F0F0; padding-bottom: 8px; margin-bottom: 20px; margin-top: 30px;
        }
        .gf-section-title {
            font-size: 1.4rem; font-weight: 800; color: #222; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .gf-section-sublinks {
            display: flex; gap: 15px; font-size: 0.82rem;
        }
        .gf-section-sublinks a { color: var(--gf-magenta); font-weight: 600; text-decoration: underline; }
        
        .gf-banner-grid-combo {
            display: grid; grid-template-columns: 320px 1fr; gap: 20px; margin-bottom: 40px;
        }
        .gf-side-banner {
            background: linear-gradient(135deg, #C2185B 0%, #8B263E 100%);
            border-radius: 14px; color: white; padding: 30px 20px; display: flex; flex-direction: column;
            justify-content: space-between; text-align: center; box-shadow: 0 6px 20px rgba(194,24,91,0.25);
            background-size: cover; background-position: center; position: relative; overflow: hidden;
            min-height: 380px;
        }
        .gf-side-banner-overlay {
            position: absolute; inset: 0; background: rgba(139,38,62,0.75); z-index: 1;
        }
        .gf-side-banner-content {
            position: relative; z-index: 2; display: flex; flex-direction: column; height: 100%; justify-content: space-between;
        }
        @media (max-width: 900px) {
            .gf-banner-grid-combo { grid-template-columns: 1fr !important; }
            .gf-side-banner { min-height: 220px; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <!-- Large Giuliana Style Hero Slider -->
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
                 style="background-image: linear-gradient(90deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.3) 100%), url('<?php echo $img; ?>');">
                <div class="gf-slide-card">
                    <span class="gf-slide-tag">🌹 Entregas no Mesmo Dia em Jardins & SP</span>
                    <h2><?php echo htmlspecialchars($ttl); ?></h2>
                    <p><?php echo htmlspecialchars($sub); ?></p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="<?php echo $lnk; ?>" class="gf-btn-primary">
                            🌸 Ver Ofertas de Hoje
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
    </div>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.gf-slide');
        if (slides.length > 1) {
            setInterval(() => {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }, 4000);
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
                    $img = $p['image_path'] ? (strpos($p['image_path'], 'http') === 0 ? $p['image_path'] : $baseUrl . '/assets/uploads/' . $p['image_path']) : 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=600&q=80';
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
                    $img = $p['image_path'] ? (strpos($p['image_path'], 'http') === 0 ? $p['image_path'] : $baseUrl . '/assets/uploads/' . $p['image_path']) : 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=600&q=80';
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

    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>