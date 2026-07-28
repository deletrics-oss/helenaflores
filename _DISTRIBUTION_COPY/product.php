<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
session_start();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.active = 1 AND (p.show_on_site = 1 OR p.show_on_site IS NULL) AND (c.show_on_site = 1 OR c.show_on_site IS NULL OR p.category_id IS NULL)");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

if (!$product) {
    die("Produto não encontrado.");
}

// 2. SEO ENGINE INTEGRATION
require_once __DIR__ . '/includes/seo_engine.php';

$seo_data = [
    'type' => 'product',
    'product_info' => $product,
    'seo_title' => $product['seo_title'] ?? $product['name'] . ' | Fight Arcade',
    'seo_description' => $product['seo_description'] ?? mb_strimwidth(strip_tags($product['description']), 0, 160, "..."),
    'image_path' => $product['image_path']
];
$seo_tags = generate_powerful_seo($seo_data);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <?php echo $seo_tags; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=2.2">
    <?php include __DIR__ . '/includes/theme_injector.php'; ?>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container" style="margin-top: 3rem;">

        <div style="margin-bottom:2rem;">
            <a href="index.php" class="btn btn-secondary">← Voltar para Catálogo</a>
        </div>

        <div class="product-single-grid">

            <!-- Image Section -->
            <div>
                <!-- Main Image Stage -->
                <div class="main-image-stage">

                    <?php if ($product['is_vip']): ?>
                        <div
                            style="position:absolute; top:10px; right:10px; background:gold; color:black; padding:5px 10px; font-weight:bold; border-radius:4px; z-index:10;">
                            👑 VIP</div>
                    <?php endif; ?>

                    <?php
                    $mainImg = $product['image_path'] ? BASE_URL . '/assets/uploads/' . $product['image_path'] : 'assets/no-image.png';
                    // Fix for imported images that might be full URLs
                    if (strpos($product['image_path'], 'http') === 0) {
                        $mainImg = $product['image_path'];
                    }
                    ?>

                    <img id="mainImage" src="<?php echo $mainImg; ?>" onerror="this.src='assets/no-img.png'"
                        onclick="openLightbox()"
                        style="max-width:100%; max-height:100%; object-fit:contain; display:block; cursor:zoom-in;">
                </div>

                <!-- Gallery -->
                <?php
                $gallery = [];
                try {
                    // Removido sort_order pois pode não existir no banco do cliente
                    $stmtG = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id ASC");
                    $stmtG->execute([$id]);
                    $gallery = $stmtG->fetchAll();

                    // Merge Variation Images as well (If any)
                    $stmtV = $pdo->prepare("SELECT image_path FROM product_variations WHERE product_id = ? AND image_path IS NOT NULL AND image_path != ''");
                    $stmtV->execute([$id]);
                    $vImgs = $stmtV->fetchAll();
                    foreach ($vImgs as $vi) {
                        // Evita duplicar se a imagem da variação for o mesmo path de uma imagem da galeria
                        $exists = false;
                        foreach ($gallery as $g) {
                            if ($g['image_path'] == $vi['image_path'])
                                $exists = true;
                        }
                        if (!$exists)
                            $gallery[] = ['image_path' => $vi['image_path']];
                    }

                } catch (Exception $e) {
                    // Table might be missing, ignore gallery
                }

                if ($gallery || $product['image_path']): // Check if there's a main image or gallery items
                    ?>
                    <!-- Gallery Thumbnails -->
                    <div class="gallery-thumbs"
                        style="display:flex; gap:10px; margin-top:15px; overflow-x:auto; padding-bottom:10px;">
                        <!-- Main Thumb -->
                        <img src="<?php echo $mainImg; ?>" onclick="changeImage(this.src)"
                            style="width:70px; height:70px; object-fit:cover; border:1px solid #444; border-radius:4px; cursor:pointer; opacity:1; transition:0.2s;"
                            onmouseover="this.style.border='1px solid var(--accent)'"
                            onmouseout="this.style.border='1px solid #444'">

                        <!-- Gallery Items -->
                        <?php foreach ($gallery as $img):
                            $gPath = (strpos($img['image_path'], 'http') === 0) ? $img['image_path'] : BASE_URL . '/assets/uploads/' . $img['image_path'];
                            ?>
                            <img src="<?php echo $gPath; ?>" onclick="changeImage(this.src)"
                                style="width:70px; height:70px; object-fit:cover; border:1px solid #444; border-radius:4px; cursor:pointer; opacity:0.7; transition:0.2s;"
                                onmouseover="this.style.opacity=1; this.style.border='1px solid var(--accent)'"
                                onmouseout="this.style.opacity=0.7; this.style.border='1px solid #444'">
                        <?php endforeach; ?>
                    </div>

                    <script>
                        function changeImage(src) {
                            document.getElementById('mainImage').src = src;
                        }

                        function openLightbox() {
                            const src = document.getElementById('mainImage').src;
                            document.getElementById('lightboxImg').src = src;
                            document.getElementById('lightboxModal').style.display = 'flex';
                            document.body.style.overflow = 'hidden';
                        }
                        function closeLightbox() {
                            document.getElementById('lightboxModal').style.display = 'none';
                            document.body.style.overflow = 'auto';
                        }
                    </script>
                <?php endif; ?>

                <!-- Video Embed -->
                <?php if (!empty($product['video_url'])):
                    // Simple YouTube ID extractor
                    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $product['video_url'], $matches);
                    $ytID = $matches[1] ?? null;
                    ?>
                    <?php if ($ytID): ?>
                        <div style="margin-top:2rem;">
                            <iframe width="100%" height="315" src="https://www.youtube.com/embed/<?php echo $ytID; ?>"
                                title="Video" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen style="border-radius:8px;"></iframe>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Info Section -->
            <div class="product-details-box">
                <div style="color:var(--accent); font-weight:bold; text-transform:uppercase; margin-bottom:0.5rem;">
                    <?php echo htmlspecialchars($product['category_name'] ?? 'Geral'); ?>
                </div>

                <h1 style="font-size:2.5rem; margin-bottom:1rem; line-height:1.2; text-shadow:none;">
                    <?php echo htmlspecialchars($product['name']); ?>
                </h1>

                <!-- Price Logic MOVED TO TOP -->
                <?php
                $price = $product['price'];
                $price_cash = $price * 0.95;
                ?>
                <div style="margin-bottom:1.5rem;">
                    <div style="display:flex; flex-direction:column; gap:5px;">
                        <span style="color:#888; text-decoration:line-through; font-size:1rem;">
                            R$
                            <?php echo number_format($price, 2, ',', '.'); ?>
                        </span>
                        <div style="display:flex; align-items:baseline; gap:10px;">
                            <div style="font-size:3rem; font-weight:800; color:var(--text-main); letter-spacing:-1px;">
                                R$
                                <?php echo number_format($price_cash, 2, ',', '.'); ?>
                            </div>
                            <span style="color:var(--primary); font-weight:bold; font-size:1.1rem;">via Pix (5%
                                OFF)</span>
                        </div>
                        <div style="color:#ccc; font-size:1rem;">
                            ou <strong>R$
                                <?php echo number_format($price, 2, ',', '.'); ?>
                            </strong> em até 12x
                        </div>
                    </div>
                </div>

                <!-- Add to Cart Form (Variations + Installments) -->
                <form action="cart.php" method="POST"
                    style="background:#111; padding:1.5rem; border-radius:8px; border:1px solid #333; margin-top:1rem;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="variation_id" id="variation_id" value="">

                    <?php
                    // FETCH VARIATIONS
                    $vars = [];
                    try {
                        $vStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY type, value");
                        $vStmt->execute([$product['id']]);
                        $vars = $vStmt->fetchAll();
                    } catch (Exception $e) {
                    }

                    if ($vars):
                        $groupedVars = [];
                        foreach ($vars as $v) {
                            $groupedVars[$v['type']][] = $v;
                        }
                        ?>
                        <style>
                            .var-btn {
                                background: #222;
                                border: 2px solid #333;
                                color: #fff;
                                padding: 5px;
                                border-radius: 8px;
                                cursor: pointer;
                                min-width: 60px;
                                text-align: center;
                                transition: all 0.2s ease;
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                gap: 5px;
                                font-size: 0.8rem;
                            }

                            .var-btn:hover {
                                border-color: var(--primary);
                                background: #2a2a2a;
                            }

                            .var-btn.active {
                                border-color: var(--primary) !important;
                                background: rgba(241, 196, 15, 0.1) !important;
                                box-shadow: 0 0 10px rgba(241, 196, 15, 0.2);
                            }

                            .var-img-thumb {
                                width: 50px;
                                height: 50px;
                                object-fit: cover;
                                border-radius: 4px;
                            }
                        </style>

                        <div style="margin-bottom:1.5rem; border-bottom:1px solid #333; padding-bottom:1rem;">
                            <?php foreach ($groupedVars as $type => $options): ?>
                                <label
                                    style="display:block; font-size:1rem; color:var(--primary); margin-bottom:10px; font-weight:bold;">
                                    🎨 Escolha:
                                    <?php echo htmlspecialchars($type); ?>
                                </label>
                                <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                    <?php foreach ($options as $opt):
                                        $priceExtra = ($opt['price'] > 0) ? "data-price='{$opt['price']}'" : "";
                                        $priceWhole = $opt['price_wholesale'] ?? 0;
                                        $priceWholeAttr = ($priceWhole > 0) ? "data-price-whole='{$priceWhole}'" : "";
                                        $imgVar = $opt['image_path'] ?? '';
                                        $fullImgPath = (strpos($imgVar, 'http') === 0) ? $imgVar : BASE_URL . "/assets/uploads/{$imgVar}";
                                        $imgAttr = ($imgVar) ? "data-image='{$fullImgPath}'" : "";
                                        $label = htmlspecialchars($opt['value']);
                                        ?>
                                        <button type="button" class="var-btn"
                                            onclick="selectVariation(this, '<?php echo $type; ?>', '<?php echo $opt['id']; ?>')"
                                            <?php echo $priceExtra; ?>             <?php echo $priceWholeAttr; ?>             <?php echo $imgAttr; ?>>
                                            <?php if ($imgVar): ?>
                                                <img src="<?php echo $fullImgPath; ?>" class="var-img-thumb"
                                                    alt="<?php echo $label; ?>">
                                            <?php endif; ?>
                                            <span><?php echo $label; ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="var_<?php echo md5($type); ?>" required>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Installments (Compact) inside Form -->
                    <div style="margin-bottom:1.5rem;">
                        <details>
                            <summary style="font-weight:bold; color:#aaa; font-size:0.9rem; cursor:pointer;">
                                💳 Ver Parcelamento (até 12x) ▾
                            </summary>
                            <div
                                style="margin-top:10px; background:#1a1a1a; padding:10px; border-radius:6px; border:1px solid #333; max-height:150px; overflow-y:auto;">
                                <table style="width:100%; font-size:0.85rem; color:#ccc; border-collapse:collapse;">
                                    <?php for ($i = 1; $i <= 12; $i++):
                                        $total = ($i <= 3) ? $price : $price * 1.10;
                                        $installment = $total / $i;
                                        $label = ($i <= 3) ? '<span style="color:var(--primary);">sem juros</span>' : '';
                                        ?>
                                        <tr style="border-bottom:1px solid #222;">
                                            <td style="padding:4px 0;">
                                                <?php echo $i; ?>x R$
                                                <?php echo number_format($installment, 2, ',', '.'); ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <?php echo $label; ?>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </table>
                            </div>
                        </details>
                    </div>

                    <!-- Wholesale Tag inside Form -->
                    <?php
                    $s_file = __DIR__ . '/includes/site_settings.json';
                    $site_config = file_exists($s_file) ? json_decode(file_get_contents($s_file), true) : [];
                    if (($site_config['enable_wholesale'] ?? 1) && $product['price_wholesale'] > 0): ?>
                        <div
                            style="margin-top:1rem; padding:10px; background:rgba(76, 201, 240, 0.1); border:1px solid #4cc9f0; border-radius:6px;">
                            <div style="font-size:0.75rem; text-transform:uppercase; color:#4cc9f0; font-weight:bold;">🏭
                                Atacado (
                                <?php echo $product['min_wholesale_qty']; ?>+ un)
                            </div>
                            <div style="font-size:1.4rem; font-weight:bold; color:var(--text-main);">R$
                                <?php echo number_format($product['price_wholesale'], 2, ',', '.'); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex; gap:15px; margin-top:1.5rem; align-items:center;">
                        <input type="number" name="quantity" value="1" min="1" id="qtyInput"
                            style="width:80px; padding:10px; border-radius:6px; border:1px solid #444; background:#222; color:#fff; text-align:center;">
                        <button type="submit" class="btn" style="flex-grow:1; padding:12px;">
                            🛒 Adicionar ao Carrinho
                        </button>
                    </div>
                </form>

                <script>
                    let currentRetailPrice = <?php echo $product['price']; ?>;
                    let currentWholesalePrice = <?php echo (($site_config['enable_wholesale'] ?? 1) && $product['price_wholesale'] > 0) ? $product['price_wholesale'] : $product['price']; ?>;
                    const minWholesaleQty = <?php echo (($site_config['enable_wholesale'] ?? 1) && $product['min_wholesale_qty'] > 0) ? $product['min_wholesale_qty'] : 999999; ?>;

                    function selectVariation(btn, type, id) {
                        // Visual Selection (using CSS class)
                        let siblings = btn.parentElement.querySelectorAll('.var-btn');
                        siblings.forEach(sib => sib.classList.remove('active'));
                        btn.classList.add('active');

                        // Update Hidden Input
                        document.getElementById('variation_id').value = id;

                        // 1. Image Update
                        let newImg = btn.getAttribute('data-image');
                        if (newImg) {
                            document.getElementById('mainImage').src = newImg;
                        }

                        // 2. Price Update (Retail)
                        let newPrice = btn.getAttribute('data-price');
                        if (newPrice) {
                            currentRetailPrice = parseFloat(newPrice);
                            // Update Display immediately if Qty is low
                            updatePriceDisplay();
                        }

                        // 3. Price Update (Wholesale)
                        let newWhole = btn.getAttribute('data-price-whole');
                        if (newWhole) {
                            currentWholesalePrice = parseFloat(newWhole);
                        } else {
                            // Reset to product default if variation doesn't have wholesale price?
                            // Or keep it proportional? For now, if not set, use Retail (or original wholesale logic)
                            // Ideally we fallback to original product wholesale if variation doesn't specify.
                            // But if variation changes the product significantly, we should use the variation's logic.
                        }
                        updatePriceDisplay();
                    }

                    // Dynamic Price Update Logic
                    const qtyInput = document.getElementById('qtyInput');
                    const mainPriceDisplay = document.querySelector('div[style*="font-size:3rem"]');

                    function updatePriceDisplay() {
                        let qty = parseInt(qtyInput.value);
                        let displayPrice = currentRetailPrice;
                        let color = '#fff';

                        if (qty >= minWholesaleQty) {
                            displayPrice = currentWholesalePrice;
                            color = '#4cc9f0';
                        }

                        mainPriceDisplay.innerText = 'R$ ' + displayPrice.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                        mainPriceDisplay.style.color = color;
                    }

                    qtyInput.addEventListener('input', updatePriceDisplay);
                </script>

                <!-- Description (Moved to Bottom) -->
                <div style="margin-top:2rem;">
                    <details open
                        style="background:#1a1a1a; border:1px solid #333; border-radius:8px; overflow:hidden;">
                        <summary
                            style="padding:15px; cursor:pointer; font-weight:bold; color:var(--primary); background:#222; list-style:none; display:flex; align-items:center; justify-content:space-between;">
                            <span>📝 Descrição / Detalhes</span>
                            <span style="font-size:0.8rem; color:#666;">▼</span>
                        </summary>
                        <div style="padding:20px; color:#ccc; line-height:1.6; font-size:1rem;"
                            class="description-content">
                            <?php echo $product['description'] ? nl2br(htmlspecialchars($product['description'])) : '<em>Sem descrição.</em>'; ?>
                        </div>
                    </details>
                </div>

            </div>
        </div>
    </div>

    <div style="height:50px;"></div>

    <!-- REVIEWS SECTION -->
    <div class="container reviews-section">
        <h3>⭐ Avaliações dos Clientes</h3>

        <?php
        // Fetch Reviews
        $stmtR = $pdo->prepare("SELECT * FROM product_reviews WHERE product_id = ? AND approved = 1 ORDER BY created_at DESC");
        $stmtR->execute([$id]);
        $reviews = $stmtR->fetchAll();
        ?>

        <?php if (count($reviews) > 0): ?>
            <?php foreach ($reviews as $r): ?>
                <div class="review-card">
                    <div class="review-header">
                        <strong>
                            <?php echo htmlspecialchars($r['user_name']); ?>
                        </strong>
                        <div class="review-stars">
                            <?php echo str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']); ?>
                        </div>
                    </div>
                    <div class="review-body">
                        <?php echo nl2br(htmlspecialchars($r['comment'])); ?>
                    </div>
                    <div class="review-date">
                        <?php echo date('d/m/Y', strtotime($r['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#666; font-style:italic; padding:1rem 0;">Seja o primeiro a avaliar este produto.</p>
        <?php endif; ?>

        <!-- Add Review Form -->
        <div class="review-form-box">
            <h4>Deixe sua avaliação</h4>
            <form action="save_review.php" method="POST">
                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                <div style="margin-bottom:10px;">
                    <label>Seu Nome</label>
                    <input type="text" name="user_name" required placeholder="Ex: João"
                        style="background:#222; border:1px solid #444;">
                </div>
                <div style="margin-bottom:10px;">
                    <label>Nota</label>
                    <select name="rating" style="background:#222; border:1px solid #444;">
                        <option value="5">⭐⭐⭐⭐⭐ 5 Estrelas</option>
                        <option value="4">⭐⭐⭐⭐ 4 Estrelas</option>
                        <option value="3">⭐⭐⭐ 3 Estrelas</option>
                        <option value="2">⭐⭐ 2 Estrelas</option>
                        <option value="1">⭐ 1 Estrela</option>
                    </select>
                </div>
                <div style="margin-bottom:10px;">
                    <label>Comentário</label>
                    <textarea name="comment" required rows="3" placeholder="O que achou do produto?"
                        style="background:#222; border:1px solid #444;"></textarea>
                </div>
                <button type="submit" class="btn">Enviar Avaliação</button>
            </form>
        </div>
    </div>

    <!-- RELATED PRODUCTS -->
    <div class="container" style="margin-top:4rem;">
        <h2 style="border-left:4px solid var(--primary); padding-left:1rem; margin-bottom:2rem;">🔄 Produtos
            Relacionados</h2>

        <?php
        // Fetch Related (Same Category, Not Current)
        $stmtRel = $pdo->prepare("SELECT p.* FROM products p JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? AND p.id != ? AND p.active = 1 AND (p.show_on_site = 1 OR p.show_on_site IS NULL) AND (c.show_on_site = 1 OR c.show_on_site IS NULL) ORDER BY RAND() LIMIT 4");
        $stmtRel->execute([$product['category_id'], $id]);
        $related = $stmtRel->fetchAll();
        ?>

        <div class="products-grid">
            <?php foreach ($related as $rp): ?>
                <a href="product.php?id=<?php echo $rp['id']; ?>" class="product-card">
                    <div class="product-img">
                        <img src="<?php echo BASE_URL; ?>/assets/uploads/<?php echo $rp['image_path']; ?>"
                            alt="<?php echo htmlspecialchars($rp['name']); ?>">
                    </div>
                    <div class="product-info">
                        <div class="product-title">
                            <?php echo htmlspecialchars($rp['name']); ?>
                        </div>
                        <div class="price-value">R$
                            <?php echo number_format($rp['price'], 2, ',', '.'); ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div id="lightboxModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center; cursor:zoom-out;"
        onclick="closeLightbox()">
        <span
            style="position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer;"
            onclick="closeLightbox()">&times;</span>
        <img id="lightboxImg" src=""
            style="max-width:95%; max-height:95%; object-fit:contain; border-radius:8px; box-shadow:0 0 30px rgba(0,0,0,0.5);">
    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>

</html>