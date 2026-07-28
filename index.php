<?php
/**
 * index.php — Helena Flores (Loja & Catálogo Oficial)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
ob_start();

$is_logged_in = isset($_SESSION['user_id']);

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

// Fetch Categories for Filter Bar
$cats = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();

// BANNERS CAROUSEL LOGIC
$banner_slides = [];
try {
    $banner_slides = $pdo->query("SELECT * FROM banners WHERE active = 1 ORDER BY display_order ASC, id DESC")->fetchAll();
} catch (Exception $e) {}

// Auto-scan assets/banners/ folder if DB is empty or has local files
if (empty($banner_slides)) {
    $localBannerFiles = glob(__DIR__ . '/assets/banners/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
    if (!empty($localBannerFiles)) {
        foreach ($localBannerFiles as $idx => $file) {
            $banner_slides[] = [
                'image_path' => 'assets/banners/' . basename($file),
                'title' => 'Helena Flores — Jardins',
                'subtitle' => 'Buquês de Rosas Colombianas, Cestas Personalizadas e Arranjos de Luxo em SP',
                'link_url' => '#produtos'
            ];
        }
    }
}

// Fallback Banner Slide
if (empty($banner_slides)) {
    $banner_slides = [
        [
            'image_path' => 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=1600&q=80',
            'title' => 'Helena Flores — Atelier nos Jardins',
            'subtitle' => 'Há mais de 11 anos cultivando emoções em São Paulo.',
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
    <title>Helena Flores | Atelier & Floricultura nos Jardins - São Paulo</title>
    <meta name="description" content="Há mais de 11 anos cultivando emoções. Buquês de Rosas Colombianas, Cestas Personalizadas e Arranjos de Luxo em SP. (11) 98672-7872">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/slider.css?v=<?php echo time(); ?>">
    <style>
        .hero-slider-helena {
            position: relative;
            width: 100%;
            height: 420px;
            overflow: hidden;
            background: #1B3B2B;
        }
        .hero-slide {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hero-slide.active { opacity: 1; z-index: 1; }
        .hero-overlay-content {
            background: rgba(27, 59, 43, 0.85);
            backdrop-filter: blur(8px);
            padding: 2.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
            max-width: 800px;
            margin: 0 15px;
            color: #FFF;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        @media (max-width: 768px) {
            .hero-slider-helena { height: 320px !important; }
            .hero-overlay-content { padding: 1.2rem !important; }
            .hero-overlay-content h1 { font-size: 1.6rem !important; }
            .hero-overlay-content p { font-size: 0.9rem !important; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <!-- Large Banner Hero Carousel -->
    <div class="hero-slider-helena">
        <?php foreach ($banner_slides as $index => $slide):
            $img = $slide['image_path'] ?? $slide['image'];
            if (strpos($img, 'http') === false) {
                $img = BASE_URL . '/' . ltrim($img, '/');
            }
            $ttl = $slide['title'] ?? 'Helena Flores';
            $sub = $slide['subtitle'] ?? 'Atelier & Floricultura nos Jardins';
            $lnk = $slide['link_url'] ?? '#produtos';
            ?>
            <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>"
                 style="background-image: linear-gradient(135deg, rgba(0,0,0,0.4) 0%, rgba(27,59,43,0.5) 100%), url('<?php echo $img; ?>');">
                <div class="hero-overlay-content">
                    <span style="color: #E8C3C8; text-transform: uppercase; letter-spacing: 2px; font-weight: 600; font-size: 0.8rem;">
                        Floricultura & Atelier de Presentes em São Paulo • Jardins
                    </span>
                    <h1 style="font-family: 'Playfair Display', Georgia, serif; font-size: 2.5rem; margin: 10px 0; color: #FFF;">
                        <?php echo htmlspecialchars($ttl); ?>
                    </h1>
                    <p style="font-size: 1.1rem; color: #F9F5EC; font-weight: 300; margin-bottom: 1.5rem; line-height: 1.5;">
                        <?php echo htmlspecialchars($sub); ?>
                    </p>
                    <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
                        <a href="<?php echo $lnk; ?>" class="btn" style="background:#C5A059; color:#FFF; border-radius:30px; padding:10px 26px; font-weight:600; text-decoration:none;">
                            🌸 Ver Produtos
                        </a>
                        <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20fazer%20um%20pedido" 
                           target="_blank" 
                           class="btn" 
                           style="background:transparent; border:2px solid #FFF; color:#FFF; border-radius:30px; padding:10px 26px; font-weight:600; text-decoration:none;">
                            💬 Pedir no WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.hero-slide');
        if (slides.length > 1) {
            setInterval(() => {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }, 4500);
        }
    </script>

    <!-- Main Container -->
    <div class="container" id="produtos" style="margin-top: 2.5rem; margin-bottom: 4rem;">

        <!-- Search & Category Filters -->
        <div style="background: #FFF; padding: 1.2rem; border-radius: 14px; border: 1px solid #EFECE6; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 2rem;">
            <form method="GET" style="display:flex; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                <input type="text" name="q" placeholder="Buscar por rosa, buquê, cesta..." value="<?php echo htmlspecialchars($search); ?>" 
                       style="flex:1; height:45px; border-radius:8px; border:1px solid #DDD; padding:0 15px; margin:0;">
                <button type="submit" class="btn" style="background:#8B263E; color:#FFF; height:45px; border-radius:8px; padding:0 25px;">
                    Buscar
                </button>
            </form>

            <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:4px;">
                <a href="index.php" class="btn <?php echo !$cat_id ? '' : 'btn-secondary'; ?>" 
                   style="background: <?php echo !$cat_id ? '#8B263E' : '#F9F5EC'; ?>; color: <?php echo !$cat_id ? '#FFF' : '#2C2C2C'; ?>; border-radius:20px; padding:6px 18px; font-size:0.85rem; text-decoration:none; white-space:nowrap;">
                   Todas as Flores
                </a>
                <?php foreach ($cats as $c): ?>
                    <a href="?cat=<?php echo $c['id']; ?>" 
                       style="background: <?php echo $cat_id == $c['id'] ? '#8B263E' : '#F9F5EC'; ?>; color: <?php echo $cat_id == $c['id'] ? '#FFF' : '#2C2C2C'; ?>; border-radius:20px; padding:6px 18px; font-size:0.85rem; text-decoration:none; white-space:nowrap;">
                        <?php echo htmlspecialchars($c['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="products-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $p): ?>
                    <?php 
                    $img = $p['image_path'] ? (strpos($p['image_path'], 'http') === 0 ? $p['image_path'] : BASE_URL . '/assets/uploads/' . $p['image_path']) : 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=600&q=80';
                    ?>
                    <div class="product-card">
                        <a href="product.php?id=<?php echo $p['id']; ?>" class="product-img">
                            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                        </a>
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($p['category_name'] ?? 'Helena Flores'); ?></div>
                            <a href="product.php?id=<?php echo $p['id']; ?>" class="product-title" style="text-decoration:none;">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </a>
                            <div style="font-size:0.85rem; color:#6B6B6B; margin-bottom:12px; height:38px; overflow:hidden;">
                                <?php echo htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 75, '...')); ?>
                            </div>
                            <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center;">
                                <div class="price-value">
                                    R$ <?php echo number_format($p['price'], 2, ',', '.'); ?>
                                </div>
                            </div>
                            
                            <div style="display:flex; gap:8px; margin-top:12px;">
                                <form action="cart.php" method="POST" style="flex:1;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button class="btn-add-cart" style="margin:0;">🛒 Adicionar</button>
                                </form>
                                <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20pedir%20o%20<?php echo urlencode($p['name']); ?>%20(R$%20<?php echo number_format($p['price'], 2, ',', '.'); ?>)" 
                                   target="_blank" 
                                   style="padding:12px 14px; background:#25D366; color:#FFF; border-radius:10px; text-decoration:none; font-weight:600; font-size:0.85rem; display:inline-flex; align-items:center;">
                                    💬
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align:center; padding: 4rem; background:#FFF; border-radius:14px;">
                    <p style="font-size:1.2rem; color:#6B6B6B;">Nenhum produto encontrado nesta busca.</p>
                    <a href="seed_helena_flores.php" class="btn" style="background:#8B263E; color:#FFF; border-radius:30px; margin-top:1rem; display:inline-block; text-decoration:none; padding:12px 28px; font-weight:bold;">
                        🌹 Clique aqui para Inicializar o Catálogo Helena Flores
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>