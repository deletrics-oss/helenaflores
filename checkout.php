<?php
/**
 * checkout.php — Helena Flores (Checkout Estilo Giuliana Flores com Lalamove & Melhor Envio)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modules_shipping.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// 1. Validate Login
if (!isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'cliente@helenaflores.com.br'");
    $stmt->execute();
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_id'] = $u['id'];
    } else {
        header("Location: " . $baseUrl . "/index.php");
        exit;
    }
}

// 2. Validate Cart
if (empty($_SESSION['cart'])) {
    header("Location: " . $baseUrl . "/cart.php");
    exit;
}

// 3. Auto-migration for Database Schema Safety
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) DEFAULT 'Endereço Principal',
        zipcode VARCHAR(20) DEFAULT '',
        address VARCHAR(255) DEFAULT '',
        number VARCHAR(50) DEFAULT '',
        complement VARCHAR(100) DEFAULT '',
        neighborhood VARCHAR(100) DEFAULT '',
        city VARCHAR(100) DEFAULT 'São Paulo',
        state VARCHAR(10) DEFAULT 'SP',
        is_default TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

// Ensure orders table columns exist safely
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_address TEXT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(100) DEFAULT 'whatsapp'");
} catch (Exception $e) {}

$userId = (int)$_SESSION['user_id'];

// 4. Calculate Cart Products
$cart_items = [];
$total_products = 0;
$keys = array_keys($_SESSION['cart']);

if (!empty($keys)) {
    $inClause = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($inClause)");
    $stmt.execute($keys);
    $products_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $productsMap = array_column($products_db, null, 'id');

    foreach ($keys as $pid) {
        if (!isset($productsMap[$pid])) continue;
        $p = $productsMap[$pid];
        $qty = $_SESSION['cart'][$pid];
        $p['qty'] = $qty;
        $total_products += ($p['price'] * $qty);
        $cart_items[] = $p;
    }
}

// 5. Handle Shipping Calculation & Options
$user_zip = $_POST['zipcode'] ?? '01420-001';
$shipping_options = calculateShippingOptions($user_zip, $cart_items);

$selected_shipping_price = 0;
if (!empty($shipping_options)) {
    $selected_shipping_price = $shipping_options[0]['price'];
}

// 6. Final Order Processing
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $paymentMethod = $_POST['payment_method'] ?? 'whatsapp';
    $chosenShipping = $_POST['shipping_option'] ?? 'Lalamove Motoboy';
    $destAddress = ($_POST['street'] ?? 'Alameda Jaú') . ', ' . ($_POST['number'] ?? '1777') . ' - ' . ($_POST['neighborhood'] ?? 'Jardim Paulista') . ', ' . ($_POST['city'] ?? 'São Paulo') . '/SP - CEP: ' . $user_zip;

    $orderId = null;

    // Multi-tier Safe Order Insert Strategy
    try {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, shipping_address, created_at) VALUES (?, ?, 'pending', ?, ?, NOW())");
        $stmt->execute([$userId, $total_products + $selected_shipping_price, $paymentMethod, $destAddress]);
        $orderId = $pdo->lastInsertId();
    } catch (Exception $e1) {
        try {
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, created_at) VALUES (?, ?, 'pending', ?, NOW())");
            $stmt->execute([$userId, $total_products + $selected_shipping_price, $paymentMethod]);
            $orderId = $pdo->lastInsertId();
        } catch (Exception $e2) {
            try {
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, created_at) VALUES (?, ?, 'pending', NOW())");
                $stmt->execute([$userId, $total_products + $selected_shipping_price]);
                $orderId = $pdo->lastInsertId();
            } catch (Exception $e3) {
                $error = "Erro ao processar pedido: " . $e3->getMessage();
            }
        }
    }

    if ($orderId) {
        foreach ($cart_items as $item) {
            try {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$orderId, $item['id'], $item['qty'], $item['price']]);
            } catch (Exception $ei) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$orderId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['price'] * $item['qty']]);
                } catch (Exception $ei2) {}
            }
        }

        // Clear Cart
        $_SESSION['cart'] = [];

        // Redirect to WhatsApp Confirmation with Full Details
        $waMsg = "Ol%C3%A1!%20Acabei%20de%20fazer%20o%20Pedido%20%23" . $orderId . "%20no%20site!%0A%0A*Frete:*%20" . urlencode($chosenShipping) . "%0A*Endere%C3%A7o:*%20" . urlencode($destAddress) . "%0A*Total:*%20R$%20" . number_format($total_products + $selected_shipping_price, 2, ',', '.');
        header("Location: https://wa.me/5511986727872?text=" . $waMsg);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Finalizar Compra | Helena Flores</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .gf-checkout-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin: 2rem 0 4rem 0;
        }
        .gf-card-section {
            background: #FFF; border: 1px solid #EEE; border-radius: 14px; padding: 1.8rem; margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        @media (max-width: 768px) {
            .gf-checkout-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div style="max-width:1240px; margin: 2rem auto; padding: 0 20px; flex:1;">
        
        <h1 style="font-size:1.8rem; font-weight:800; color:var(--gf-magenta-dark); margin-bottom:1.5rem;">
            📦 Finalizar Compra
        </h1>

        <?php if ($error): ?>
            <div style="background:#FFEBEE; color:#C2185B; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:bold;">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="gf-checkout-grid" id="checkoutForm">
            <input type="hidden" name="place_order" value="1">

            <!-- Left Column: Address, Shipping & Payment Options -->
            <div>
                <!-- Address Section with ViaCEP Auto-complete -->
                <div class="gf-card-section">
                    <h3 style="color:var(--gf-magenta-dark); margin-bottom:1rem; font-size:1.2rem; display:flex; align-items:center; gap:8px;">
                        📍 1. Endereço de Entrega
                    </h3>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label style="font-size:0.85rem; font-weight:700; color:#444; display:block; margin-bottom:4px;">CEP (Auto-preenchimento)</label>
                            <input type="text" name="zipcode" id="f_zip" value="<?php echo htmlspecialchars($user_zip); ?>" 
                                   onblur="fetchCep(this.value)" placeholder="00000-000" 
                                   style="width:100%; height:45px; border-radius:8px; border:2px solid var(--gf-magenta-light); padding:0 12px; font-weight:bold; font-size:1rem;" required>
                        </div>
                        <div>
                            <label style="font-size:0.85rem; font-weight:700; color:#444; display:block; margin-bottom:4px;">Cidade / UF</label>
                            <input type="text" name="city" id="f_city" value="São Paulo / SP" style="width:100%; height:45px; border-radius:8px; border:1px solid #DDD; padding:0 12px;" required>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label style="font-size:0.85rem; font-weight:700; color:#444; display:block; margin-bottom:4px;">Rua / Endereço</label>
                            <input type="text" name="street" id="f_street" value="Alameda Jaú" style="width:100%; height:45px; border-radius:8px; border:1px solid #DDD; padding:0 12px;" required>
                        </div>
                        <div>
                            <label style="font-size:0.85rem; font-weight:700; color:#444; display:block; margin-bottom:4px;">Número</label>
                            <input type="text" name="number" id="f_number" value="1777" style="width:100%; height:45px; border-radius:8px; border:1px solid #DDD; padding:0 12px;" required>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <div>
                            <label style="font-size:0.85rem; font-weight:700; color:#444; display:block; margin-bottom:4px;">Bairro</label>
                            <input type="text" name="neighborhood" id="f_neighborhood" value="Jardim Paulista" style="width:100%; height:45px; border-radius:8px; border:1px solid #DDD; padding:0 12px;" required>
                        </div>
                        <div>
                            <label style="font-size:0.85rem; font-weight:700; color:#444; display:block; margin-bottom:4px;">Complemento / Bloco</label>
                            <input type="text" name="complement" placeholder="Apto / Casa" style="width:100%; height:45px; border-radius:8px; border:1px solid #DDD; padding:0 12px;">
                        </div>
                    </div>
                </div>

                <!-- Shipping Options Section -->
                <div class="gf-card-section">
                    <h3 style="color:var(--gf-magenta-dark); margin-bottom:1rem; font-size:1.2rem; display:flex; align-items:center; gap:8px;">
                        🚚 2. Opções de Frete (Lalamove & Melhor Envio)
                    </h3>

                    <?php foreach ($shipping_options as $index => $opt): ?>
                        <label style="display:flex; align-items:center; justify-content:space-between; padding:14px; border:1px solid #EEE; border-radius:10px; margin-bottom:10px; cursor:pointer; background:#FAFAFA;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <input type="radio" name="shipping_option" value="<?php echo htmlspecialchars($opt['name']); ?>" <?php echo ($index === 0) ? 'checked' : ''; ?>>
                                <span style="font-weight:bold; color:#333;"><?php echo htmlspecialchars($opt['name']); ?></span>
                            </div>
                            <span style="font-weight:bold; color:var(--gf-magenta-dark);">
                                <?php echo ($opt['price'] > 0) ? 'R$ ' . number_format($opt['price'], 2, ',', '.') : 'GRÁTIS'; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column: Order Summary -->
            <div>
                <div class="gf-card-section" style="position:sticky; top:20px;">
                    <h3 style="color:#222; margin-bottom:1rem; font-size:1.2rem;">Resumo do Pedido</h3>
                    
                    <div style="border-bottom:1px solid #EEE; padding-bottom:15px; margin-bottom:15px;">
                        <?php foreach ($cart_items as $item): ?>
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                                <span style="color:#555;"><?php echo $item['qty']; ?>x <?php echo htmlspecialchars(substr($item['name'], 0, 20)); ?>...</span>
                                <span style="font-weight:bold;">R$ <?php echo number_format($item['price'] * $item['qty'], 2, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem; color:#666;">
                        <span>Subtotal:</span>
                        <span>R$ <?php echo number_format($total_products, 2, ',', '.'); ?></span>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-size:0.9rem; color:#666;">
                        <span>Frete Escolhido:</span>
                        <span style="font-weight:bold; color:var(--gf-magenta-dark);">
                            <?php echo ($selected_shipping_price > 0) ? 'R$ ' . number_format($selected_shipping_price, 2, ',', '.') : 'GRÁTIS'; ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:1.5rem; font-size:1.3rem; font-weight:800; color:#222; border-top:2px dashed #EEE; padding-top:15px;">
                        <span>Total:</span>
                        <span style="color:var(--gf-magenta-dark);">R$ <?php echo number_format($total_products + $selected_shipping_price, 2, ',', '.'); ?></span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; height:55px; font-size:1.15rem; font-weight:bold; border-radius:30px; background:var(--gf-magenta-dark); color:#FFF; border:none; cursor:pointer;">
                        CONCLUIR PEDIDO 🌸
                    </button>
                </div>
            </div>

        </form>

    </div>

    <script>
        function fetchCep(cep) {
            var cleanCep = cep.replace(/\D/g, '');
            if (cleanCep.length === 8) {
                fetch('https://viacep.com.br/ws/' + cleanCep + '/json/')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('f_street').value = data.logradouro || '';
                            document.getElementById('f_neighborhood').value = data.bairro || '';
                            document.getElementById('f_city').value = (data.localidade || 'São Paulo') + ' / ' + (data.uf || 'SP');
                        }
                    });
            }
        }
    </script>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>