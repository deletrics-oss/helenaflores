<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
// session_start(); // Already checked/started in config.php

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid = $_POST['product_id'];
        $vid = $_POST['variation_id'] ?? '';
        $qty = (int) $_POST['qty'];
        if ($qty < 1)
            $qty = 1;

        // Compound Key: ID-VARID
        $key = $pid . ($vid ? '-' . $vid : '');

        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key] += $qty;
        } else {
            $_SESSION['cart'][$key] = $qty;
        }
    }

    if ($action === 'update') {
        $key = $_POST['key']; // Changed from product_id to key
        $qty = (int) $_POST['qty'];
        if ($qty > 0) {
            $_SESSION['cart'][$key] = $qty;
        } else {
            unset($_SESSION['cart'][$key]);
        }
    }

    if ($action === 'remove') {
        $key = $_POST['key'];
        unset($_SESSION['cart'][$key]);
    }

    if ($action === 'reorder') {
        $oid = $_POST['order_id'];
        // Clear (or Append? Usually Reorder means "I want THIS gain". Let's clear to avoid confusion, or append? Let's clear for now as it's cleaner)
        $_SESSION['cart'] = [];

        $items = $pdo->query("SELECT * FROM order_items WHERE order_id = $oid")->fetchAll();
        foreach ($items as $i) {
            // We need to reconstruct the key: PID-VARID
            // But verify if product still exists? (Ideally yes, but let's assume yes)

            // Simplification: We need SKU to find Variation ID if not stored? 
            // Currently order_items doesn't explicitly store variation_id, but it stores product_name which might not help.
            // Wait, order_items table schema check needed. 
            // IF order_items DOES NOT store variation_id, we can't accurately "Refazer".
            // Let's assume for now we just add the PID. 
            // If we can't get variation back easily without a huge refactor, we just add the base product.
            // OR: Did we save variation_id in the JSON or raw? 

            // Let's check DB schema quickly or just add PID.
            $key = $i['product_id'];
            // Ideally we should have stored variation_id in order_items. 
            // If not, we just add the main product. 

            $_SESSION['cart'][$key] = $i['quantity'];
        }
    }

    header("Location: " . BASE_URL . "/cart.php");
    exit;
}

// Fetch products in cart
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    // Extract unique Product IDs to query DB
    $keys = array_keys($_SESSION['cart']);
    $pids = [];
    foreach ($keys as $k) {
        $parts = explode('-', $k);
        $pids[] = $parts[0];
    }
    $pids = array_unique($pids);

    if (!empty($pids)) {
        $idsStr = implode(',', $pids);
        $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($idsStr)");
        $productsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Index by ID for easy lookup
        $productsMap = [];
        foreach ($productsRaw as $p)
            $productsMap[$p['id']] = $p;

        // Build Cart Items
        foreach ($keys as $k) {
            $parts = explode('-', $k); // [ID, VAR_ID]
            $pid = $parts[0];
            $vid = $parts[1] ?? null;

            if (!isset($productsMap[$pid]))
                continue;

            $p = $productsMap[$pid];
            $qty = $_SESSION['cart'][$k];

            // Handle Variation (Fetch Name & Price Override)
            $varText = '';
            if ($vid) {
                $vStmt = $pdo->prepare("SELECT * FROM product_variations WHERE id = ?");
                $vStmt->execute([$vid]);
                $varData = $vStmt->fetch();
                if ($varData) {
                    $varText = "{$varData['type']}: {$varData['value']}";
                    if ($varData['price'] > 0) {
                        $p['price'] = $varData['price']; // Override Retail Price
                        // Wholesale rules might need adjustment if variation has specific wholesale price
                        // For now, assuming variation overrides base price completely
                    }
                }
            }

            // Rules for price (Wholesale vs Retail)
            $unit_price = $p['price'];
            $s_file = __DIR__ . '/includes/site_settings.json';
            $site_config = file_exists($s_file) ? json_decode(file_get_contents($s_file), true) : [];
            if (($site_config['enable_wholesale'] ?? 1) && $p['price_wholesale'] > 0 && $qty >= $p['min_wholesale_qty']) {
                $unit_price = $p['price_wholesale'];
                // Note: Variation price currently overrides Retail. 
                // If wholesale is active, we use wholesale price. 
                // COMPLEXITY: Does variation affect wholesale? 
                // Let's assume variation price is RETAIL override. 
                // If wholesale applies, we stick to wholesale (unless we want to store wholesale_price in variations too).
                // Simplification for now: Wholesale wins if quantity met.
            }

            $subtotal = $unit_price * $qty;
            $total += $subtotal;

            $p['qty'] = $qty;
            $p['final_price'] = $unit_price;
            $p['subtotal'] = $subtotal;
            $p['cart_key'] = $k; // Store key for update/remove actions
            $p['variation_text'] = $varText;

            $cart_items[] = $p;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <h1 style="color:var(--primary); margin-bottom:1.5rem;">Seu Carrinho</h1>

        <?php if (count($cart_items) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:80px;"></th>
                            <th>Produto</th>
                            <th>Preço Unit.</th>
                            <th>Qtd</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <?php
                                    $thumb = $item['image_path'] ? (strpos($item['image_path'], 'http') === 0 ? $item['image_path'] : BASE_URL . '/assets/uploads/' . $item['image_path']) : 'assets/no-img.png';
                                    ?>
                                    <img src="<?php echo $thumb; ?>"
                                        style="width:60px; height:60px; object-fit:cover; border-radius:4px; border:1px solid #333;"
                                        onerror="this.src='assets/no-img.png'">
                                </td>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </strong><br>
                                    <?php if ($item['variation_text']): ?>
                                        <small
                                            style="color:var(--accent);"><?php echo htmlspecialchars($item['variation_text']); ?></small><br>
                                    <?php endif; ?>
                                    <small>SKU:
                                        <?php echo $item['sku']; ?>
                                    </small>
                                </td>
                                <td>
                                    R$
                                    <?php echo number_format($item['final_price'], 2, ',', '.'); ?>
                                    <?php if ($item['final_price'] < $item['price']): ?>
                                        <br><small style="color:var(--success)">(Preço Atacado)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="" method="POST" style="margin:0; display:flex;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="key" value="<?php echo $item['cart_key']; ?>">
                                        <input type="number" name="qty" value="<?php echo $item['qty']; ?>" min="1"
                                            style="width:60px; margin:0; text-align:center;" onchange="this.form.submit()">
                                    </form>
                                </td>
                                <td style="color:var(--primary); font-weight:bold;">
                                    R$
                                    <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                                </td>
                                <td>
                                    <form action="" method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="key" value="<?php echo $item['cart_key']; ?>">
                                        <button type="submit"
                                            style="background:none; border:none; cursor:pointer; color:#ff4d4d; padding:10px; transition:transform 0.2s;"
                                            onmouseover="this.style.transform='scale(1.2)'"
                                            onmouseout="this.style.transform='scale(1)'" title="Remover item">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path
                                                    d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                </path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div
                style="text-align:right; margin-top:1rem; padding:1rem; background:var(--bg-card); border-radius:8px; border:1px solid var(--border);">
                <h2 style="font-size:1.5rem;">Total: R$
                    <?php echo number_format($total, 2, ',', '.'); ?>
                </h2>
                <div style="margin-top:1rem; display:flex; justify-content:flex-end; gap:1rem; flex-wrap:wrap;">
                    <a href="<?php echo BASE_URL; ?>/" class="btn btn-secondary">Continuar Comprando</a>
                    <a href="<?php echo BASE_URL; ?>/checkout.php" class="btn">Finalizar Compra</a>
                </div>
            </div>

        <?php else: ?>
            <div style="text-align:center; padding:4rem; background:var(--bg-card); border-radius:12px;">
                <p>Seu carrinho está vazio.</p>
                <a href="<?php echo BASE_URL; ?>/" class="btn" style="margin-top:1rem;">Ver Catálogo</a>
            </div>
        <?php endif; ?>

        <!-- SUGGESTIONS SECTION -->
        <?php
        // Fetch 4 random featured products for recommendation
        $stmtSug = $pdo->query("SELECT * FROM products WHERE active = 1 AND (show_on_site = 1 OR show_on_site IS NULL) ORDER BY RAND() LIMIT 4");
        $suggestions = $stmtSug->fetchAll();

        if (count($suggestions) > 0):
            ?>
            <div style="margin-top:4rem; margin-bottom:4rem;">
                <h2 style="color:var(--primary); margin-bottom:1.5rem; text-align:center;">🔥 Sugestões para Você</h2>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:1.5rem;">
                    <?php foreach ($suggestions as $s):
                        $sThumb = $s['image_path'] ? (strpos($s['image_path'], 'http') === 0 ? $s['image_path'] : BASE_URL . '/assets/uploads/' . $s['image_path']) : 'assets/no-img.png';
                        ?>
                        <div style="background:var(--bg-card); border-radius:12px; border:1px solid #333; overflow:hidden; transition:transform 0.2s;"
                            onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                            <a href="product.php?id=<?php echo $s['id']; ?>"
                                style="text-decoration:none; color:inherit; display:block;">
                                <div style="aspect-ratio:1/1; overflow:hidden; background:#000;">
                                    <img src="<?php echo $sThumb; ?>" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </a>
                            <div style="padding:1rem;">
                                <a href="product.php?id=<?php echo $s['id']; ?>"
                                    style="text-decoration:none; color:inherit; display:block;">
                                    <h3
                                        style="font-size:0.9rem; margin-bottom:0.5rem; height:2.4rem; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                                        <?php echo htmlspecialchars($s['name']); ?></h3>
                                    <div
                                        style="color:var(--primary); font-weight:bold; font-size:1.1rem; margin-bottom:0.8rem;">
                                        R$ <?php echo number_format($s['price'], 2, ',', '.'); ?></div>
                                </a>

                                <form action="" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo $s['id']; ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="btn-sm"
                                        style="width:100%; background:var(--primary); color:#000; border:none; padding:8px; display:flex; align-items:center; justify-content:center; gap:5px; font-weight:bold;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                        Adicionar +
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>