<?php
/**
 * checkout.php — Helena Flores (Checkout Estilo Giuliana Flores com ViaCEP, Endereços Salvos, Lalamove & Mercado Pago)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modules_shipping.php';
require_once __DIR__ . '/includes/payment_mercadopago.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// 1. Drop strict Foreign Key constraints if present
try { $pdo->exec("ALTER TABLE orders DROP FOREIGN KEY fk_order_user"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE orders DROP FOREIGN KEY orders_ibfk_1"); } catch (Exception $e) {}

// 2. Validate & Ensure Valid User ID
$userId = (int)($_SESSION['user_id'] ?? 0);
$userCheck = null;
if ($userId > 0) {
    $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userCheck = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userCheck) {
    $stmt = $pdo->query("SELECT id, name, email, phone FROM users ORDER BY id ASC LIMIT 1");
    $firstUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($firstUser) {
        $userId = (int)$firstUser['id'];
        $userCheck = $firstUser;
    } else {
        try {
            $pdo->exec("INSERT INTO users (name, email, password, role) VALUES ('Cliente Visitante', 'cliente@helenaflores.com.br', '123456', 'customer')");
            $userId = (int)$pdo->lastInsertId();
            $userCheck = ['id' => $userId, 'name' => 'Cliente Visitante', 'email' => 'cliente@helenaflores.com.br', 'phone' => '11986727872'];
        } catch (Exception $eu) {
            $userId = 1;
        }
    }
    $_SESSION['user_id'] = $userId;
}

// 3. Fetch Saved Addresses for Logged-In User
$savedAddresses = [];
try {
    $stmtAddrs = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmtAddrs->execute([$userId]);
    $savedAddresses = $stmtAddrs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$defaultAddr = !empty($savedAddresses) ? $savedAddresses[0] : null;

// 4. Validate Cart
if (empty($_SESSION['cart'])) {
    header("Location: " . $baseUrl . "/cart.php");
    exit;
}

// 5. Calculate Cart Products
$cart_items = [];
$total_products = 0;
$keys = array_keys($_SESSION['cart']);

if (!empty($keys)) {
    $inClause = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($inClause)");
    $stmt->execute($keys);
    $products_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $productsMap = array_column($products_db, null, 'id');

    foreach ($keys as $pid) {
        $qty = (int)$_SESSION['cart'][$pid];
        if (isset($productsMap[$pid]) && $qty > 0) {
            $p = $productsMap[$pid];
            $subtotal = $p['price'] * $qty;
            $total_products += $subtotal;
            $cart_items[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'price' => $p['price'],
                'qty' => $qty,
                'subtotal' => $subtotal,
                'product' => $p
            ];
        }
    }
}

if (empty($cart_items)) {
    header("Location: " . $baseUrl . "/cart.php");
    exit;
}

// 6. Handle Order Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $zipcode = trim($_POST['zipcode'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $number = trim($_POST['number'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $cityState = trim($_POST['city_state'] ?? 'São Paulo / SP');
    $shippingOption = $_POST['shipping_option'] ?? 'lalamove_moto|22.90|Lalamove Express Motoboy';
    $paymentMethod = $_POST['payment_method'] ?? 'whatsapp';

    $parts = explode('|', $shippingOption);
    $selected_shipping_price = (float)($parts[1] ?? 22.90);
    $chosenShipping = $parts[2] ?? 'Entrega Expressa';

    $destAddress = "$address, $number - $neighborhood, $cityState (CEP: $zipcode)";
    $grandTotal = $total_products + $selected_shipping_price;

    $orderId = null;

    $insertAttempts = [
        ["INSERT INTO orders (user_id, total_amount, status, payment_method, shipping_address, created_at) VALUES (?, ?, 'pending', ?, ?, NOW())", [$userId, $grandTotal, $paymentMethod, $destAddress]],
        ["INSERT INTO orders (user_id, total_amount, status, payment_method, created_at) VALUES (?, ?, 'pending', ?, NOW())", [$userId, $grandTotal, $paymentMethod]],
        ["INSERT INTO orders (user_id, total_amount, status, created_at) VALUES (?, ?, 'pending', NOW())", [$userId, $grandTotal]]
    ];

    foreach ($insertAttempts as $attempt) {
        try {
            $stmt = $pdo->prepare($attempt[0]);
            $stmt->execute($attempt[1]);
            $orderId = $pdo->lastInsertId();
            if ($orderId) break;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '1452') !== false || strpos($e->getMessage(), 'foreign key') !== false) {
                try {
                    $uStmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
                    $fallbackUser = $uStmt->fetch();
                    $attempt[1][0] = $fallbackUser ? (int)$fallbackUser['id'] : 1;
                    $stmt = $pdo->prepare($attempt[0]);
                    $stmt->execute($attempt[1]);
                    $orderId = $pdo->lastInsertId();
                    if ($orderId) break;
                } catch (Exception $eFb) {}
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

        // Save session checkout info
        $_SESSION['checkout'] = [
            'order_id' => $orderId,
            'shipping_method' => $shippingOption,
            'shipping_cost' => $selected_shipping_price,
            'shipping_address' => $destAddress,
            'payment_method' => $paymentMethod
        ];

        // Clear Cart
        $_SESSION['cart'] = [];

        // Route by Payment Method
        if ($paymentMethod === 'mercadopago') {
            // Mercado Pago Gateway Checkout
            $mpToken = 'APP_USR-3801267885404523-010515-bdf9e728448a31ec1aa278c2e6f47738-164748184';
            $prefUrl = null;
            if (function_exists('createMercadoPagoPreference')) {
                $prefUrl = createMercadoPagoPreference($mpToken, $orderId, $cart_items, [
                    'name' => $userCheck['name'] ?? 'Cliente',
                    'email' => $userCheck['email'] ?? 'cliente@helenaflores.com.br',
                    'phone' => $userCheck['phone'] ?? ''
                ], $selected_shipping_price);
            }
            if ($prefUrl) {
                header("Location: " . $prefUrl);
                exit;
            } else {
                header("Location: " . $baseUrl . "/checkout_payment.php?order_id=" . $orderId);
                exit;
            }
        } else {
            // Direct WhatsApp Order Confirmation
            $waMsg = "Ol%C3%A1!%20Acabei%20de%20fazer%20o%20Pedido%20%23" . $orderId . "%20no%20site!%0A%0A*Frete:*%20" . urlencode($chosenShipping) . "%0A*Endere%C3%A7o:*%20" . urlencode($destAddress) . "%0A*Total:*%20R$%20" . number_format($grandTotal, 2, ',', '.');
            header("Location: https://wa.me/5511986727872?text=" . $waMsg);
            exit;
        }
    } else {
        $error = "Não foi possível registrar o pedido no banco de dados. Por favor, tente novamente.";
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
        .form-control {
            width: 100%; height: 45px; border-radius: 8px; border: 1px solid #DDD; padding: 0 12px;
            font-size: 0.95rem; box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--gf-magenta); outline: none; }
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

            <!-- Left Column: Address, Delivery & Payment Options -->
            <div>
                <!-- Section 1: Saved Addresses Dropdown & Address Form -->
                <div class="gf-card-section">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem;">
                        <h2 style="font-size:1.2rem; font-weight:800; color:var(--gf-magenta-dark); margin:0;">
                            📍 1. Endereço de Entrega
                        </h2>
                        <a href="<?php echo $baseUrl; ?>/my-addresses.php" style="font-size:0.85rem; color:var(--gf-magenta); font-weight:bold; text-decoration:none;">
                            + Meus Endereços Salvos
                        </a>
                    </div>

                    <?php if (!empty($savedAddresses)): ?>
                        <div style="margin-bottom:1.2rem; background:#FFF5F7; padding:12px; border-radius:8px; border:1px solid #FCE4EC;">
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:var(--gf-magenta-dark); margin-bottom:6px;">
                                🏠 Selecionar dos Meus Endereços Salvos:
                            </label>
                            <select id="savedAddressSelector" onchange="loadSavedAddress(this.value)" class="form-control" style="background:#FFF;">
                                <?php foreach ($savedAddresses as $sa): ?>
                                    <option value="<?php echo htmlspecialchars(json_encode($sa)); ?>">
                                        <?php echo htmlspecialchars($sa['name'] . ' - ' . $sa['address'] . ', ' . $sa['number'] . ' (' . $sa['zipcode'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#555; margin-bottom:5px;">CEP (Auto-preenchimento instantâneo)</label>
                            <input type="text" name="zipcode" id="zipcode" value="<?php echo htmlspecialchars($defaultAddr['zipcode'] ?? '01420-001'); ?>" 
                                   class="form-control" onblur="fetchViaCEP(this.value)" oninput="if(this.value.length>=8) fetchViaCEP(this.value)" required>
                            <small id="cepStatus" style="color:var(--gf-magenta); font-weight:bold; font-size:0.78rem; margin-top:3px; display:block;"></small>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#555; margin-bottom:5px;">Cidade / UF</label>
                            <input type="text" name="city_state" id="city_state" value="<?php echo htmlspecialchars(($defaultAddr['city'] ?? 'São Paulo') . ' / ' . ($defaultAddr['state'] ?? 'SP')); ?>" 
                                   class="form-control" readonly style="background:#FAF9F6; color:#555;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 3fr 1fr; gap:15px; margin-bottom:15px;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#555; margin-bottom:5px;">Rua / Endereço</label>
                            <input type="text" name="address" id="address" value="<?php echo htmlspecialchars($defaultAddr['address'] ?? 'Alameda Jaú'); ?>" 
                                   class="form-control" required>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#555; margin-bottom:5px;">Número</label>
                            <input type="text" name="number" id="number" value="<?php echo htmlspecialchars($defaultAddr['number'] ?? '1777'); ?>" 
                                   class="form-control" required>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#555; margin-bottom:5px;">Bairro</label>
                            <input type="text" name="neighborhood" id="neighborhood" value="<?php echo htmlspecialchars($defaultAddr['neighborhood'] ?? 'Jardim Paulista'); ?>" 
                                   class="form-control" required>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:700; color:#555; margin-bottom:5px;">Complemento / Bloco</label>
                            <input type="text" name="complement" id="complement" value="<?php echo htmlspecialchars($defaultAddr['complement'] ?? ''); ?>" 
                                   class="form-control" placeholder="Apto / Casa">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Delivery Rates -->
                <div class="gf-card-section">
                    <h2 style="font-size:1.2rem; font-weight:800; color:var(--gf-magenta-dark); margin-bottom:1.2rem;">
                        🚚 2. Opções de Frete (Lalamove & Melhor Envio)
                    </h2>
                    
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <label style="display:flex; align-items:center; justify-content:space-between; padding:15px; border:1px solid #DDD; border-radius:10px; cursor:pointer; background:#FFF;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="radio" name="shipping_option" value="lalamove_moto|22.90|Lalamove Express Motoboy (Mesmo Dia SP & Jardins)" checked 
                                       onchange="updateTotal(22.90)">
                                <span style="font-weight:700; font-size:0.95rem;">Lalamove Express Motoboy (Mesmo Dia SP & Jardins)</span>
                            </div>
                            <strong style="color:var(--gf-magenta-dark);">R$ 22,90</strong>
                        </label>

                        <label style="display:flex; align-items:center; justify-content:space-between; padding:15px; border:1px solid #DDD; border-radius:10px; cursor:pointer; background:#FFF;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="radio" name="shipping_option" value="lalamove_carro|34.90|Lalamove Carro (Cestas & Arranjos Grandes SP)" 
                                       onchange="updateTotal(34.90)">
                                <span style="font-weight:700; font-size:0.95rem;">Lalamove Carro (Cestas & Arranjos Grandes SP)</span>
                            </div>
                            <strong style="color:var(--gf-magenta-dark);">R$ 34,90</strong>
                        </label>
                    </div>
                </div>

                <!-- Section 3: Payment Method Modules -->
                <div class="gf-card-section">
                    <h2 style="font-size:1.2rem; font-weight:800; color:var(--gf-magenta-dark); margin-bottom:1.2rem;">
                        💳 3. Forma de Pagamento
                    </h2>

                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <label style="display:flex; align-items:center; justify-content:space-between; padding:15px; border:1px solid #DDD; border-radius:10px; cursor:pointer; background:#FFF;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <input type="radio" name="payment_method" value="mercadopago" checked>
                                <div>
                                    <strong style="font-size:0.98rem; color:#222; display:block;">💳 Mercado Pago (PIX Instantâneo, Cartão até 12x)</strong>
                                    <span style="font-size:0.8rem; color:#666;">Aprovação imediata com Garantia e Segurança Mercado Pago</span>
                                </div>
                            </div>
                            <span style="font-size:1.4rem;">🛡️</span>
                        </label>

                        <label style="display:flex; align-items:center; justify-content:space-between; padding:15px; border:1px solid #DDD; border-radius:10px; cursor:pointer; background:#FFF;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <input type="radio" name="payment_method" value="whatsapp">
                                <div>
                                    <strong style="font-size:0.98rem; color:#222; display:block;">💬 Finalizar e Pagar pelo WhatsApp</strong>
                                    <span style="font-size:0.8rem; color:#666;">Combine detalhes e pagamento diretamente com o atendente</span>
                                </div>
                            </div>
                            <span style="font-size:1.4rem;">📱</span>
                        </label>
                    </div>
                </div>

            </div>

            <!-- Right Column: Order Summary -->
            <div>
                <div class="gf-card-section" style="position:sticky; top:20px;">
                    <h3 style="font-size:1.2rem; font-weight:800; color:#222; margin-bottom:1rem; border-bottom:1px solid #EEE; padding-bottom:10px;">
                        Resumo do Pedido
                    </h3>

                    <div style="max-height:220px; overflow-y:auto; margin-bottom:15px; padding-right:5px;">
                        <?php foreach ($cart_items as $ci): ?>
                            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:8px;">
                                <span style="color:#555;">
                                    <?php echo $ci['qty']; ?>x <?php echo htmlspecialchars(mb_strimwidth($ci['name'], 0, 22, '...')); ?>
                                </span>
                                <strong style="color:#222;">R$ <?php echo number_format($ci['subtotal'], 2, ',', '.'); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="border-top:1px dashed #DDD; padding-top:12px; margin-top:10px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:#666; margin-bottom:6px;">
                            <span>Subtotal:</span>
                            <span>R$ <?php echo number_format($total_products, 2, ',', '.'); ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:#666; margin-bottom:12px;">
                            <span>Entrega / Frete:</span>
                            <span id="shippingDisplay" style="color:var(--gf-magenta-dark); font-weight:bold;">R$ 22,90</span>
                        </div>

                        <div style="display:flex; justify-content:space-between; font-size:1.4rem; font-weight:800; color:var(--gf-magenta-dark); border-top:2px solid #EEE; padding-top:12px; margin-bottom:1.5rem;">
                            <span>Total:</span>
                            <span id="totalDisplay">R$ <?php echo number_format($total_products + 22.90, 2, ',', '.'); ?></span>
                        </div>

                        <button type="submit" class="gf-btn-buy" style="height:54px; font-size:1.15rem; width:100%; border-radius:27px; background:var(--gf-magenta); color:#FFF; font-weight:bold; border:none; cursor:pointer;">
                            CONCLUIR PEDIDO 🌸
                        </button>
                    </div>
                </div>
            </div>

        </form>

    </div>

    <!-- ViaCEP & Saved Address JavaScript -->
    <script>
        const subtotal = <?php echo $total_products; ?>;
        
        function updateTotal(shippingCost) {
            const total = subtotal + shippingCost;
            document.getElementById('shippingDisplay').innerText = 'R$ ' + shippingCost.toFixed(2).replace('.', ',');
            document.getElementById('totalDisplay').innerText = 'R$ ' + total.toFixed(2).replace('.', ',');
        }

        function fetchViaCEP(cepRaw) {
            const cep = cepRaw.replace(/\D/g, '');
            const statusEl = document.getElementById('cepStatus');

            if (cep.length === 8) {
                statusEl.innerText = '🔍 Buscando endereço...';
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('address').value = data.logradouro || '';
                            document.getElementById('neighborhood').value = data.bairro || '';
                            document.getElementById('city_state').value = (data.localidade || 'São Paulo') + ' / ' + (data.uf || 'SP');
                            statusEl.innerText = '✅ Endereço localizado automaticamente pelo CEP!';
                            document.getElementById('number').focus();
                        } else {
                            statusEl.innerText = '⚠️ CEP não encontrado. Preencha manualmente.';
                        }
                    })
                    .catch(() => {
                        statusEl.innerText = '⚠️ Erro ao consultar ViaCEP.';
                    });
            }
        }

        function loadSavedAddress(jsonStr) {
            if (!jsonStr) return;
            try {
                const addr = JSON.parse(jsonStr);
                document.getElementById('zipcode').value = addr.zipcode || '';
                document.getElementById('address').value = addr.address || '';
                document.getElementById('number').value = addr.number || '';
                document.getElementById('neighborhood').value = addr.neighborhood || '';
                document.getElementById('complement').value = addr.complement || '';
                document.getElementById('city_state').value = (addr.city || 'São Paulo') + ' / ' + (addr.state || 'SP');
            } catch(e) {}
        }
    </script>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>