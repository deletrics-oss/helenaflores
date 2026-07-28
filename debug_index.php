<?php
// index.php content copied below:
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// FORCE DEBUG AFTER INCLUDES (config.php turns it off)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();

// --- GATEKEEPER LOGIC ---
$is_logged_in = isset($_SESSION['user_id']);

if (!$is_logged_in && isset($_COOKIE['b2b_access'])) {
    $phone = $_COOKIE['b2b_access'];
    if (preg_match('/^\d+$/', $phone)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = :phone");
        $stmt->execute([':phone' => $phone]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['is_wholesale'] = true;
            $_SESSION['is_lead'] = ($user['is_lead'] ?? 0);
            $is_logged_in = true;

            // Evita loop infinito: só redireciona se NÃO estiver vindo de um auth/success
            // DEBUG: Comentei para evitar redirect no teste
            // if (!isset($_GET['auth']) && !isset($_GET['access'])) {
            //     header("Location: index.php?auth=restored");
            //     exit;
            // }
        }
    }
}

// Filter Logic
$cat_id = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build Query
$sql = "SELECT p.*, c.name as category_name,
(SELECT GROUP_CONCAT(image_path) FROM product_images WHERE product_id = p.id) as gallery_csv,
(SELECT GROUP_CONCAT(image_path) FROM product_variations WHERE product_id = p.id AND image_path IS NOT NULL) as
var_images_csv,
(SELECT GROUP_CONCAT(DISTINCT value SEPARATOR ', ') FROM product_variations WHERE product_id = p.id AND type='Cor') as
colors_csv
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
WHERE p.active = 1 AND p.is_vip = 0";
$params = [];

if ($cat_id) {
    $sql .= " AND p.category_id = :cat";
    $params[':cat'] = $cat_id;
}

// SEARCH FIX: Using unique parameters for each LIKE clause
if ($search) {
    if ($is_logged_in) {
        $sql .= " AND (p.name LIKE :q1 OR p.description LIKE :q2 OR p.sku LIKE :q3 OR p.ean LIKE :q4)";
        $params[':q4'] = "%$search%";
    } else {
        $sql .= " AND (p.name LIKE :q1 OR p.description LIKE :q2 OR p.sku LIKE :q3)";
    }
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
    $params[':q3'] = "%$search%";
}

$sql .= " ORDER BY p.featured DESC, p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch Categories for Sidebar
$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Catálogo | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=2.2">
    <?php include __DIR__ . '/includes/theme_injector.php'; ?>
    <?php if (!$is_logged_in): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/gatekeeper.css">
    <?php endif; ?>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <!-- Dynamic Hero Slider -->
    <!-- Hero Slider -->
    <!-- Hero Slider -->
    <?php
    $banner_slides = [];
    try {
        // Query simplificada: mais novos primeiro, sem depender de display_order agora
        $banner_slides = $pdo->query("SELECT * FROM banners WHERE active = 1 ORDER BY id DESC")->fetchAll();
    } catch (Exception $e) {
        $banner_slides = [];
    }

    // Fallback Manual if DB empty
    if (empty($banner_slides)) {
        $banner_slides = [
            [
                'image_path' => 'assets/banners/banner1.png',
                'title' => 'Bem-vindo à Fight Arcade',
                'subtitle' => 'Confira nossas novidades e produtos exclusivos.',
                'link_url' => '?cat=1'
            ]
        ];
    }
    ?>

    <?php
    // Load Settings
    $s_file = __DIR__ . '/includes/site_settings.json';
    $site_config = file_exists($s_file) ? json_decode(file_get_contents($s_file), true) : [];
    $slider_speed = $site_config['slider_speed'] ?? 4000;
    $slider_height = $site_config['slider_height'] ?? 380;
    $slider_overlay = $site_config['slider_overlay'] ?? 0.8;
    ?>

    <style>
        /* Dynamic Slider Style */
        .hero-slider {
            height:
                <?php echo $slider_height; ?>
                px !important;
        }

        @media (max-width: 768px) {
            .hero-slider {
                height: 320px !important;
            }

            /* Keep mobile balanced */
        }
    </style>

    <div class="hero-slider">
        <?php foreach ($banner_slides as $index => $slide):
            $img = $slide['image_path'] ?? $slide['image'];
            // Fix Path if needed (ensure it has BASE_URL)
            if (strpos($img, 'http') === false) {
                $img = BASE_URL . '/' . ltrim($img, '/');
            }

            $ttl = $slide['title'];
            $sub = $slide['subtitle'] ?? $slide['desc'];
            $lnk = $slide['link_url'] ?? $slide['link'];
            ?>
            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>"
                style="background-image: linear-gradient(to right, rgba(0,0,0,<?php echo $slider_overlay; ?>) 0%, rgba(0,0,0,<?php echo $slider_overlay * 0.25; ?>) 60%, transparent 100%), url('<?php echo $img; ?>'); background-size: cover; background-position: center;">
                <div class="hero-content">
                    <h1><?php echo htmlspecialchars($ttl); ?></h1>
                    <p><?php echo htmlspecialchars($sub); ?></p>
                    <a href="<?php echo $lnk; ?>" class="btn btn-hero">Ver Produtos</a>
                </div>
            </div>
        <?php endforeach; ?>

        <script>
            let currentSlide = 0;
            const slides = document.querySelectorAll('.slide');
            const speed = <?php echo (int) $slider_speed; ?>;

            if (slides.length > 1) {
                setInterval(() => {
                    slides[currentSlide].classList.remove('active');
                    currentSlide = (currentSlide + 1) % slides.length;
                    slides[currentSlide].classList.add('active');
                }, speed);

            }
        </script>
    </div>

    <div class="container" id="produtos" style="margin-top: 2rem;">

        <!-- Search & Filter -->
        <div class="filters"
            style="background:var(--bg-card); padding:1rem; border-radius:8px; display:flex; gap:1rem; margin-bottom:2rem; flex-wrap:wrap; border:1px solid var(--border);">
            <form style="flex:1; display:flex; gap:0.5rem;" method="GET">
                <input type="text" name="q" placeholder="Buscar produto..."
                    value="<?php echo htmlspecialchars($search); ?>" style="margin:0;">
                <button type="submit" class="btn">Buscar</button>
            </form>

            <div style="display:flex; gap:0.5rem; overflow-x:auto;">
                <a href="index.php" class="btn btn-secondary <?php echo !$cat_id ? 'active' : ''; ?>">Todos</a>
                <?php foreach ($cats as $c): ?>
                    <a href="?cat=<?php echo $c['id']; ?>"
                        class="btn btn-secondary <?php echo $cat_id == $c['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($c['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="products-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $p):
                    $gallery = !empty($p['gallery_csv']) ? explode(',', $p['gallery_csv']) : [];
                    // Add main image to gallery if valid
                    if ($p['image_path'])
                        array_unshift($gallery, $p['image_path']);
                    $gallery = array_unique($gallery);
                    $galleryJson = json_encode(array_map(function ($g) {
                        return BASE_URL . '/assets/uploads/' . $g;
                    }, $gallery));
                    ?>
                    <a href="product.php?id=<?php echo $p['id']; ?>" class="product-card">
                        <div class="product-img" onmouseenter='startSlide(this, <?php echo $galleryJson; ?>)'
                            onmouseleave='stopSlide(this, "<?php echo BASE_URL . '/assets/uploads/' . $p['image_path']; ?>")'>

                            <?php if ($p['image_path']): ?>
                                <img src="<?php echo BASE_URL; ?>/assets/uploads/<?php echo $p['image_path']; ?>"
                                    alt="<?php echo htmlspecialchars($p['name']); ?>" style="transition:0.3s;">
                            <?php else: ?>
                                <div style="color:#666;">Sem imagem</div>
                            <?php endif; ?>

                            <!-- Variation Badge on Image (Optional) -->
                            <?php if ($p['colors_csv']): ?>
                                <div
                                    style="position:absolute; bottom:5px; left:5px; background:rgba(0,0,0,0.7); padding:2px 6px; font-size:0.7rem; color:#fff; border-radius:4px;">
                                    🎨 <?php echo htmlspecialchars($p['colors_csv']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($p['category_name'] ?? 'Geral'); ?></div>
                            <div class="product-title"><?php echo htmlspecialchars($p['name']); ?></div>

                            <?php
                            // Merge Gallery + Variation Images for Hover
                            $galleryArr = [];
                            if (!empty($p['gallery_csv'])) {
                                $galleryArr = array_merge($galleryArr, explode(',', $p['gallery_csv']));
                            }
                            if (!empty($p['var_images_csv'])) {
                                $galleryArr = array_merge($galleryArr, explode(',', $p['var_images_csv']));
                            }
                            // Filter empty
                            $galleryArr = array_filter($galleryArr);
                            // Add Base URL
                            $galleryFull = array_map(function ($img) {
                                return BASE_URL . '/assets/uploads/' . trim($img);
                            }, $galleryArr);
                            $galleryJson = htmlspecialchars(json_encode(array_values($galleryFull)), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="prices">
                                <div class="price-tag">
                                    <span class="price-label">Varejo</span>
                                    <span class="price-value">R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></span>
                                </div>
                                <?php if (($site_config['enable_wholesale'] ?? 1) && $is_logged_in && isset($p['price_wholesale']) && $p['price_wholesale'] > 0): ?>
                                    <div class="price-tag">
                                        <span class="price-label">Atacado</span>
                                        <span class="price-value" style="color:var(--accent)">R$
                                            <?php echo number_format($p['price_wholesale'], 2, ',', '.'); ?></span>
                                    </div>
                                    <div class="wholesale-badge">Mínimo <?php echo $p['min_wholesale_qty']; ?> peças</div>
                                <?php endif; ?>
                            </div>

                            <form action="cart.php" method="POST" style="margin-top:1rem;" onclick="event.stopPropagation();">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button class="btn" style="width:100%">Adicionar ao Carrinho</button>
                            </form>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Nenhum produto encontrado.</p>
            <?php endif; ?>
        </div>

        <script>
            let slideIntervals = new Map();

            function startSlide(container, images) {
                if (!images || images.length <= 1) return;
                const img = container.querySelector('img');
                if (!img) return;

                let idx = 0;
                // Preload keys
                let interval = setInterval(() => {
                    idx = (idx + 1) % images.length;
                    img.src = images[idx];
                }, 1000); // Swap every 1s

                slideIntervals.set(container, interval);
            }

            function stopSlide(container, originalSrc) {
                if (slideIntervals.has(container)) {
                    clearInterval(slideIntervals.get(container));
                    slideIntervals.delete(container);
                }
                const img = container.querySelector('img');
                if (img) img.src = originalSrc;
            }
        </script>

    </div>

    <footer>
        <div class="container">
            <?php
            $set_file = __DIR__ . '/includes/site_settings.json';
            $settings = file_exists($set_file) ? json_decode(file_get_contents($set_file), true) : [];
            ?>

            <div
                style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:2rem; text-align:left; margin-bottom:2rem;">
                <div>
                    <h3 style="color:var(--primary); margin-bottom:1rem;">Fight Arcade</h3>
                    <p>Sua loja especializada em peças Arcade.</p>
                </div>
                <div>
                    <h4 style="color:#fff; margin-bottom:1rem;">Atendimento</h4>
                    <?php if (!empty($settings['whatsapp'])): ?>
                        <p>📱 WhatsApp: <?php echo $settings['whatsapp']; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($settings['hours'])): ?>
                        <p>🕒 <?php echo $settings['hours']; ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h4 style="color:#fff; margin-bottom:1rem;">Endereço</h4>
                    <?php if (!empty($settings['address'])): ?>
                        <p>📍 <?php echo $settings['address']; ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="border-top:1px solid #333; padding-top:1rem; text-align:center;">
                &copy; <?php echo date('Y'); ?> Fight Arcade. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <?php if (!$is_logged_in): ?>
        <div id="gatekeeper-overlay">
            <div class="gatekeeper-modal" style="position:relative;">
                <button
                    onclick="document.getElementById('gatekeeper-overlay').style.display='none'; document.body.classList.remove('modal-open');"
                    style="position:absolute; top:10px; right:15px; background:none; border:none; color:#666; font-size:1.5rem; cursor:pointer;">&times;</button>
                <h3>Bem-vindo à Fight Arcade</h3>
                <p>Identifique-se para acessar o catálogo
                    completo<?php echo ($site_config['enable_wholesale'] ?? 1) ? ' e ver preços de atacado' : ''; ?>.</p>

                <form id="gatekeeper-form" action="login.php" method="POST">
                    <!-- Standard POST submission to avoid JS/AJAX race conditions on mobile -->
                    <div class="gk-input-group">
                        <label>Seu Nome / Empresa</label>
                        <input type="text" id="gk_name" name="name" required placeholder="Ex: João Silva">
                    </div>
                    <div class="gk-input-group">
                        <label>Whatsapp / Telefone</label>
                        <input type="tel" id="gk_phone" name="phone" required placeholder="(11) 99999-9999" maxlength="15">
                    </div>

                    <div class="gk-input-group">
                        <label>Como nos conheceu?</label>
                        <select name="source"
                            style="width:100%; padding:0.8rem; background:var(--bg-card); border:1px solid var(--border); color:var(--text-main); border-radius:4px;">
                            <option value="" style="color:#000;">Selecione...</option>
                            <option value="Instagram" style="color:#000;">Instagram</option>
                            <option value="Facebook" style="color:#000;">Facebook</option>
                            <option value="TikTok" style="color:#000;">TikTok</option>
                            <option value="Kwai" style="color:#000;">Kwai</option>
                            <option value="Google" style="color:#000;">Google</option>
                            <option value="WhatsApp" style="color:#000;">WhatsApp</option>
                            <option value="Indicação" style="color:#000;">Indicação</option>
                            <option value="Outro" style="color:#000;">Outro</option>
                        </select>
                    </div>

                    <!-- Display PHP Error if present -->
                    <?php if (isset($_GET['bg_error'])): ?>
                        <div id="gk-error" style="display:block;"><?php echo htmlspecialchars($_GET['bg_error']); ?></div>
                    <?php endif; ?>

                    <button type="submit" class="btn-enter" id="gk-btn">ACESSAR SISTEMA</button>
                </form>

                <div class="transparency-note">
                    <small>Ambiente Seguro &bull; Cadastro Simplificado</small>
                </div>
            </div>
        </div>

        <script>
            document.body.classList.add('modal-open');
            // Máscara de Telefone Simples
            document.getElementById('gk_phone').addEventListener('input', function (e) {
                var x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
                e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
            });
            // Auto Submit Handler (Optional visual feedback)
            document.getElementById('gatekeeper-form').addEventListener('submit', function (e) {
                const btn = document.getElementById('gk-btn');
                btn.innerText = 'Entrando...';
                btn.disabled = true;
                // No preventDefault() - let it submit naturally
            });
        </script>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>

</html>
