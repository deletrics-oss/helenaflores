<?php
/**
 * cart.php — Helena Flores (Página do Carrinho - Estilo Giuliana Flores)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modules_shipping.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// Initialize Cart Session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Add / Remove / Update Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pId = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 1);
        if ($pId > 0 && $qty > 0) {
            $_SESSION['cart'][$pId] = ($_SESSION['cart'][$pId] ?? 0) + $qty;
        }
        header("Location: cart.php");
        exit;
    } elseif ($action === 'update') {
        $pId = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 1);
        if ($pId > 0) {
            if ($qty <= 0) {
                unset($_SESSION['cart'][$pId]);
            } else {
                $_SESSION['cart'][$pId] = $qty;
            }
        }
        header("Location: cart.php");
        exit;
    } elseif ($action === 'remove') {
        $pId = (int)($_POST['product_id'] ?? 0);
        if ($pId > 0) {
            unset($_SESSION['cart'][$pId]);
        }
        header("Location: cart.php");
        exit;
    }
}

// Fetch Products in Cart
$cartItems = [];
$totalAmount = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $inClause = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($inClause)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $p) {
        $qty = $_SESSION['cart'][$p['id']] ?? 1;
        $subtotal = $p['price'] * $qty;
        $totalAmount += $subtotal;
        $cartItems[] = [
            'product' => $p,
            'qty' => $qty,
            'subtotal' => $subtotal
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Seu Carrinho | Helena Flores</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .gf-cart-table {
            width: 100%; border-collapse: collapse; margin-bottom: 2rem; background: #FFF;
            border-radius: 12px; overflow: hidden; border: 1px solid #EEE; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .gf-cart-table th {
            background: #FFF5F7; color: var(--gf-magenta-dark); font-weight: 700; text-align: left; padding: 14px; font-size: 0.9rem;
        }
        .gf-cart-table td {
            padding: 16px 14px; border-bottom: 1px solid #EEE; vertical-align: middle;
        }
        .gf-cart-summary-box {
            background: #FFF; border: 1px solid #EEE; border-radius: 12px; padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        @media (max-width: 768px) {
            .gf-cart-table, .gf-cart-table tbody, .gf-cart-table tr, .gf-cart-table td {
                display: block; width: 100%;
            }
            .gf-cart-table<thead> { display: none; }
            .gf-cart-table td { text-align: right; position: relative; padding-left: 50%; border-bottom: 1px solid #F0F0F0; }
            .gf-cart-table td::before { content: attr(data-label); position: absolute; left: 14px; font-weight: 700; color: #555; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div style="max-width:1240px; margin: 2rem auto; padding: 0 20px; flex:1;">
        
        <h1 style="font-size:1.8rem; font-weight:800; color:var(--gf-magenta-dark); margin-bottom:1.5rem;">
            🛒 Seu Carrinho de Compras
        </h1>

        <?php if (!empty($cartItems)): ?>
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;" class="gf-cart-grid">
                
                <!-- Left: Items Table -->
                <div>
                    <table class="gf-cart-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Preço Unitário</th>
                                <th>Qtd</th>
                                <th>Subtotal</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <?php 
                                $p = $item['product'];
                                $img = $p['image_path'] ? (strpos($p['image_path'], 'http') === 0 ? $p['image_path'] : $baseUrl . '/assets/uploads/' . $p['image_path']) : 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=600&q=80';
                                ?>
                                <tr>
                                    <td data-label="Produto">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                                 style="width:60px; height:60px; object-fit:cover; border-radius:8px; border:1px solid #EEE;">
                                            <div>
                                                <a href="product.php?id=<?php echo $p['id']; ?>" style="color:#222; font-weight:700; text-decoration:none; font-size:0.95rem;">
                                                    <?php echo htmlspecialchars($p['name']); ?>
                                                </a>
                                                <div style="font-size:0.75rem; color:#888;">Cód: <?php echo htmlspecialchars($p['sku'] ?: $p['id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Preço">
                                        R$ <?php echo number_format($p['price'], 2, ',', '.'); ?>
                                    </td>
                                    <td data-label="Qtd">
                                        <form action="cart.php" method="POST" style="display:inline-flex; align-items:center; gap:4px;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                            <input type="number" name="qty" value="<?php echo $item['qty']; ?>" min="1" max="99" 
                                                   style="width:55px; height:36px; border-radius:6px; border:1px solid #DDD; text-align:center; font-weight:bold;"
                                                   onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td data-label="Subtotal" style="font-weight:700; color:var(--gf-magenta-dark);">
                                        R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                                    </td>
                                    <td data-label="Ação">
                                        <form action="cart.php" method="POST">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" style="background:none; border:none; color:#D32F2F; cursor:pointer; font-size:1.2rem;" title="Remover">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <a href="index.php" style="color:var(--gf-magenta); font-weight:700; text-decoration:none; font-size:0.9rem;">
                        ← Continuar Comprando Mais Flores
                    </a>
                </div>

                <!-- Right: Summary Box -->
                <div>
                    <div class="gf-cart-summary-box">
                        <h3 style="font-size:1.2rem; font-weight:800; color:#222; margin-bottom:1rem; border-bottom:1px solid #EEE; padding-bottom:10px;">
                            Resumo do Pedido
                        </h3>

                        <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:0.95rem; color:#555;">
                            <span>Subtotal dos Produtos:</span>
                            <strong>R$ <?php echo number_format($totalAmount, 2, ',', '.'); ?></strong>
                        </div>

                        <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-size:0.95rem; color:#555;">
                            <span>Entrega / Frete:</span>
                            <span style="color:#2E7D32; font-weight:700;">A calcular no checkout</span>
                        </div>

                        <div style="border-top:2px solid #F0F0F0; padding-top:12px; margin-top:12px; display:flex; justify-content:space-between; align-items:baseline; margin-bottom:1.5rem;">
                            <span style="font-size:1.1rem; font-weight:800;">Total:</span>
                            <span style="font-size:1.8rem; font-weight:800; color:var(--gf-magenta-dark);">
                                R$ <?php echo number_format($totalAmount, 2, ',', '.'); ?>
                            </span>
                        </div>

                        <a href="checkout.php" class="gf-btn-primary" style="width:100%; height:50px; border-radius:25px; font-size:1.1rem; text-decoration:none; margin-bottom:10px;">
                            FINALIZAR COMPRA ➡️
                        </a>

                        <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20finalizar%20meu%20pedido%20do%20carrinho%20(Total:%20R$%20<?php echo number_format($totalAmount, 2, ',', '.'); ?>)" 
                           target="_blank" class="gf-btn-whatsapp" style="width:100%; height:45px; border-radius:25px; font-size:0.9rem; text-decoration:none; justify-content:center;">
                            💬 Finalizar via WhatsApp
                        </a>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <div style="text-align:center; padding: 4rem; background:#FFF; border-radius:14px; border:1px solid #EEE;">
                <p style="font-size:1.3rem; color:#666; margin-bottom:1.5rem;">Seu carrinho de compras está vazio.</p>
                <a href="index.php" class="gf-btn-primary" style="text-decoration:none; padding:14px 32px;">
                    🌸 Explorar Produtos & Buquês →
                </a>
            </div>
        <?php endif; ?>

    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>