<?php
/**
 * product.php — Helena Flores (Página do Produto - Estilo Giuliana Flores com Preview HD & Zoom)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modules_shipping.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch Product
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: index.php");
    exit;
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$img = get_product_image_url($product['image_path'], $product['name']);
$oldPrice = $product['price'] * 1.20;
$installment = $product['price'] / 3;

// Fetch Related Products
$relStmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND active = 1 LIMIT 4");
$relStmt->execute([$product['category_id'], $id]);
$related = $relStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($product['name']); ?> | Helena Flores</title>
    <meta name="description" content="<?php echo htmlspecialchars(mb_strimwidth($product['description'] ?? '', 0, 150, '...')); ?>">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .gf-product-detail-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin: 2rem 0 4rem 0;
        }
        .gf-product-gallery {
            background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 16px; padding: 20px; text-align: center;
            position: relative; cursor: zoom-in; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .gf-product-gallery img {
            max-width: 100%; height: 440px; object-fit: contain; border-radius: 12px; transition: transform 0.3s ease;
        }
        .gf-product-gallery:hover img {
            transform: scale(1.04);
        }
        .gf-zoom-badge {
            position: absolute; bottom: 15px; right: 15px; background: rgba(0,0,0,0.65); color: #FFF;
            padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; pointer-events: none;
            backdrop-filter: blur(4px); display: flex; align-items: center; gap: 6px;
        }
        .gf-product-buybox {
            background: #FFFFFF; border: 1px solid #EEEEEE; border-radius: 16px; padding: 2.2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04); display: flex; flex-direction: column;
        }
        .gf-shipping-calculator {
            background: #FFF8F9; border: 1px solid #FCE4EC; border-radius: 12px; padding: 1.2rem; margin: 1.5rem 0;
        }
        /* Lightbox Modal Style */
        .gf-lightbox-modal {
            display: none; position: fixed; z-index: 9999; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); backdrop-filter: blur(6px); align-items: center; justify-content: center;
        }
        .gf-lightbox-modal img {
            max-width: 90%; max-height: 90vh; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .gf-lightbox-close {
            position: absolute; top: 20px; right: 25px; color: #FFF; font-size: 2.5rem; cursor: pointer; font-weight: bold;
        }
        @media (max-width: 768px) {
            .gf-product-detail-grid { grid-template-columns: 1fr !important; gap: 20px !important; }
            .gf-product-gallery img { height: 300px !important; }
            .gf-product-buybox { padding: 1.2rem !important; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div style="max-width:1240px; margin: 2rem auto; padding: 0 20px; flex:1;">
        
        <!-- Breadcrumb -->
        <div style="font-size:0.85rem; color:#888; margin-bottom:1.5rem;">
            <a href="index.php" style="color:#666; text-decoration:none;">Home</a> &gt; 
            <a href="index.php?cat=<?php echo $product['category_id']; ?>" style="color:#666; text-decoration:none;"><?php echo htmlspecialchars($product['category_name'] ?? 'Flores'); ?></a> &gt; 
            <strong style="color:var(--gf-magenta);"><?php echo htmlspecialchars($product['name']); ?></strong>
        </div>

        <div class="gf-product-detail-grid">
            
            <!-- Left Column: HD Image Gallery with Click Zoom -->
            <div class="gf-product-gallery" onclick="openLightbox('<?php echo $img; ?>')">
                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div class="gf-zoom-badge">
                    🔍 Clique para ampliar (Preview HD)
                </div>
            </div>

            <!-- Right Column: Buy Box -->
            <div class="gf-product-buybox">
                <span style="font-size:0.8rem; color:#888; text-transform:uppercase; font-weight:700; letter-spacing:1px;">
                    Cód: <?php echo htmlspecialchars($product['sku'] ?: $product['id']); ?> • <?php echo htmlspecialchars($product['category_name'] ?? 'Helena Flores'); ?>
                </span>
                
                <h1 style="font-size:1.8rem; font-weight:800; color:#222; margin:10px 0 15px 0; line-height:1.2;">
                    <?php echo htmlspecialchars($product['name']); ?>
                </h1>

                <div style="margin-bottom:1.5rem;">
                    <span class="gf-old-price" style="font-size:1.1rem;">R$ <?php echo number_format($oldPrice, 2, ',', '.'); ?></span>
                    <span class="gf-price-val" style="font-size:2.2rem;">R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></span>
                    <div style="font-size:0.9rem; color:#555; font-weight:600; margin-top:4px;">
                        💳 em até <strong>3x de R$ <?php echo number_format($installment, 2, ',', '.'); ?></strong> sem juros no cartão
                    </div>
                </div>

                <p style="color:#555; line-height:1.6; font-size:0.95rem; margin-bottom:1.5rem;">
                    <?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?>
                </p>

                <!-- Actions: Add to Cart & WhatsApp Order -->
                <form action="cart.php" method="POST" style="margin-bottom:1.2rem;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div style="display:flex; gap:12px;">
                        <input type="number" name="qty" value="1" min="1" max="99" 
                               style="width:70px; height:50px; border-radius:10px; border:1px solid #DDD; text-align:center; font-size:1.1rem; font-weight:bold;">
                        <button type="submit" class="gf-btn-buy" style="height:50px; font-size:1.1rem; border-radius:25px;">
                            🛒 ADICIONAR AO CARRINHO
                        </button>
                    </div>
                </form>

                <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20comprar%20o%20produto%20<?php echo urlencode($product['name']); ?>%20(C%C3%B3d:%20<?php echo $product['id']; ?>)" 
                   target="_blank" class="gf-btn-whatsapp" style="height:50px; font-size:1.05rem; border-radius:25px; text-decoration:none;">
                    💬 Pedir Imediatamente pelo WhatsApp (11) 98672-7872
                </a>

                <!-- Shipping Calculator (Lalamove) -->
                <div class="gf-shipping-calculator">
                    <div style="font-weight:700; color:var(--gf-magenta-dark); font-size:0.95rem; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                        🚚 Calcular Frete & Entrega Rápida
                    </div>
                    <div style="font-size:0.8rem; color:#666; margin-bottom:10px;">
                        Entregamos via Lalamove / Motoboy no mesmo dia em São Paulo e Jardins.
                    </div>
                    <form action="checkout.php" method="POST" style="display:flex; gap:8px;">
                        <input type="text" name="zipcode" placeholder="Digite seu CEP (ex: 01420-001)" 
                               style="flex:1; height:42px; border-radius:8px; border:1px solid #DDD; padding:0 12px; font-size:0.9rem;" required>
                        <button type="submit" class="btn" style="height:42px; background:var(--gf-magenta-dark); color:#FFF; border-radius:8px; padding:0 18px; font-weight:bold; border:none;">
                            OK
                        </button>
                    </form>
                </div>

            </div>

        </div>

        <!-- Related Products Section -->
        <?php if (!empty($related)): ?>
            <div style="margin-top:4rem; border-top:2px dashed #EEE; padding-top:3rem;">
                <h2 class="gf-section-title" style="margin-bottom:1.5rem;">🌸 Produtos Relacionados que Você Vai Amar</h2>
                <div class="gf-product-grid">
                    <?php foreach ($related as $r): ?>
                        <?php 
                        $rImg = get_product_image_url($r['image_path'], $r['name']);
                        $rOld = $r['price'] * 1.20;
                        ?>
                        <div class="gf-product-card">
                            <a href="product.php?id=<?php echo $r['id']; ?>" class="gf-product-img">
                                <img src="<?php echo $rImg; ?>" alt="<?php echo htmlspecialchars($r['name']); ?>" loading="lazy">
                            </a>
                            <div class="gf-product-body">
                                <a href="product.php?id=<?php echo $r['id']; ?>" class="gf-product-title" style="text-decoration:none;">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </a>
                                <div class="gf-price-container" style="margin-top:10px;">
                                    <span class="gf-old-price">R$ <?php echo number_format($rOld, 2, ',', '.'); ?></span>
                                    <span class="gf-price-val">R$ <?php echo number_format($r['price'], 2, ',', '.'); ?></span>
                                </div>
                                <div class="gf-card-actions" style="margin-top:10px;">
                                    <a href="product.php?id=<?php echo $r['id']; ?>" class="gf-btn-buy" style="height:38px; font-size:0.9rem; border-radius:20px; text-decoration:none;">
                                        Ver Produto
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Lightbox Modal -->
    <div id="lightboxModal" class="gf-lightbox-modal" onclick="closeLightbox()">
        <span class="gf-lightbox-close">&times;</span>
        <img id="lightboxImg" src="" alt="Ampliação HD">
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('lightboxModal').style.display = 'flex';
        }
        function closeLightbox() {
            document.getElementById('lightboxModal').style.display = 'none';
        }
    </script>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>