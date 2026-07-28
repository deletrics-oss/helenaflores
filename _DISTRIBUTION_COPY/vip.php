<?php
// catalogo/vip.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
session_start();

// Security: Only logged in VIP users
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user status to confirm VIP (Double check session mainly for cleaner implementation, 
// here assuming session variable or re-fetching. Let's re-fetch to be safe or assuming session is enough if we trust login.)
// Better: Re-fetch is_vip 
$stmt_u = $pdo->prepare("SELECT is_vip FROM users WHERE id = ?");
$stmt_u->execute([$_SESSION['user_id']]);
$u_data = $stmt_u->fetch();

if (!$u_data || $u_data['is_vip'] != 1) {
    // Not VIP? Redirect to normal store
    header("Location: index.php?msg=only_vip");
    exit;
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Query ONLY VIP products
$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.active = 1 AND p.is_vip = 1";
$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE :q1 OR p.description LIKE :q2)";
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo VIP | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        body {
            border-top: 5px solid gold;
        }

        .hero-vip {
            background: linear-gradient(to right, #000, #333);
            padding: 3rem;
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid gold;
        }

        .hero-vip h1 {
            color: gold;
            font-size: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .product-card {
            border-color: gold;
        }

        .product-price {
            color: gold;
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="hero-vip">
        <h1>👑 Área Exclusiva VIP</h1>
        <p>Produtos selecionados para clientes especiais.</p>
    </div>

    <div class="container">

        <?php if (count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $p): ?>
                    <a href="product.php?id=<?php echo $p['id']; ?>" class="product-card"
                        style="display:block; text-decoration:none;">
                        <div class="product-img">
                            <?php if ($p['image_path']): ?>
                                <img src="<?php echo BASE_URL; ?>/assets/uploads/<?php echo $p['image_path']; ?>">
                            <?php else: ?>
                                <div style="color:#666;">Sem imagem</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-title">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </div>
                            <div class="product-sku">SKU:
                                <?php echo htmlspecialchars($p['sku']); ?>
                            </div>

                            <div class="prices">
                                <div class="price-tag">
                                    <span class="price-label">Valor VIP</span>
                                    <span class="price-value">R$
                                        <?php echo number_format($p['price'], 2, ',', '.'); ?>
                                    </span>
                                </div>
                            </div>

                            <form action="cart.php" method="POST" style="margin-top:1rem;" onclick="event.stopPropagation();">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button class="btn" style="width:100%; background:gold; color:black;">Adicionar ao Pedido
                                    VIP</button>
                            </form>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:4rem; color:#666;">
                <h2>Nenhum produto exclusivo disponível no momento.</h2>
            </div>
        <?php endif; ?>

    </div>

    <footer>
        <div class="container">
            &copy;
            <?php echo date('Y'); ?> Fight Arcade VIP.
        </div>
    </footer>

</body>

</html>