<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
// Integrações
require_once __DIR__ . '/includes/payment_mercadopago.php'; // Integration Logic 

session_start();

// Validations
if (empty($_SESSION['cart']) || empty($_SESSION['checkout'])) {
    header("Location: checkout.php");
    exit;
}

// 1. Calculate Grand Total
$total_products = 0;
$items_display = [];

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
    $products_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Index Map
    $productsMap = [];
    foreach ($products_db as $p)
        $productsMap[$p['id']] = $p;

    foreach ($keys as $k) {
        $parts = explode('-', $k); // [ID, VAR_ID]
        $pid = $parts[0];
        if (!isset($productsMap[$pid]))
            continue;
        $p = $productsMap[$pid];

        $qty = $_SESSION['cart'][$k];
        $vid = $parts[1] ?? null;

        // Handle Variation
        if ($vid) {
            $vStmt = $pdo->prepare("SELECT * FROM product_variations WHERE id = ?");
            $vStmt->execute([$vid]);
            $varData = $vStmt->fetch();
            if ($varData) {
                $p['name'] .= " ({$varData['type']}: {$varData['value']})";
                if ($varData['price'] > 0)
                    $p['price'] = $varData['price'];
            }
        }

        $price = ($p['price_wholesale'] > 0 && $_SESSION['is_wholesale'] && $qty >= $p['min_wholesale_qty'])
            ? $p['price_wholesale'] : $p['price'];

        $total_products += ($price * $qty);
        $items_display[] = ['id' => $p['id'], 'name' => $p['name'], 'qty' => $qty, 'price' => $price];
    }
}

// Parse Shipping Info
// Format: "Correios SEDEX|45.90"
$ship_data = explode('|', $_SESSION['checkout']['shipping_method']);
$ship_name = $ship_data[0];
$ship_cost = floatval($ship_data[1]);

$grand_total = $total_products + $ship_cost;

// 2. Handle Final Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pay_method = $_POST['payment_method']; // mercadopago, nupay, manual

    try {
        $pdo->beginTransaction();

        // A. Update User Info (Address & Document for NFE)
        // Check if we need to update user
        $addr = $_SESSION['checkout']['address'];
        $doc = $addr['document'] ?? '';

        $sqlUpdateUser = "UPDATE users SET 
            document = :doc,
            address = :st, number = :nb, neighborhood = :dt, complement = :cp, zipcode = :zp
            WHERE id = :uid";

        $stmtUser = $pdo->prepare($sqlUpdateUser);
        $stmtUser->execute([
            ':doc' => $doc,
            ':st' => $addr['street'],
            ':nb' => $addr['number'],
            ':dt' => $addr['district'],
            ':cp' => $addr['complement'],
            ':zp' => $addr['zip'],
            ':uid' => $_SESSION['user_id']
        ]);

        // B. Save Order
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_method, payment_method, created_at) VALUES (:uid, :total, 'pending', :ship, :pay, NOW())");
        $stmt->execute([
            ':uid' => $_SESSION['user_id'],
            ':total' => $grand_total,
            ':ship' => "$ship_name (R$ $ship_cost)",
            ':pay' => $pay_method
        ]);
        $order_id = $pdo->lastInsertId();

        // B. Save Items
        // B. Save Items
        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items_display as $item) {
            $subtotal = $item['price'] * $item['qty'];
            $stmt_item->execute([
                $order_id,
                $item['id'],
                $item['name'], // Includes "(Color: ...)"
                $item['qty'],
                $item['price'],
                $subtotal
            ]);
        }

        // --- FINANCIAL LEDGER: Pending orders do NOT count as debt yet ---
        // (Administrator must change status to 'Processing' or 'Paid' to trigger debt/payment)

        $pdo->commit();

        // C. Clear Cart & Notify Admin
        require_once __DIR__ . '/includes/notifications.php';
        $notif = new NotificationService($pdo);
        $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $uStmt->execute([$_SESSION['user_id']]);
        $uName = $uStmt->fetchColumn();
        $notif->newOrder($order_id, $uName, $grand_total);

        unset($_SESSION['cart']);
        unset($_SESSION['checkout']);

        // D. Redirect Logic based on Payment
        if ($pay_method === 'mercadopago') {
            // 1. Get Config
            $stmtMod = $pdo->prepare("SELECT * FROM module_settings WHERE module_key = 'payment_mercadopago'");
            $stmtMod->execute();
            $modMP = $stmtMod->fetch(PDO::FETCH_ASSOC);

            $mpUrl = null;
            if ($modMP && $modMP['is_active'] == 1) {
                $keys = json_decode($modMP['settings_json'], true);
                $accessToken = $keys['access_token'] ?? '';

                // 2. Create Preference
                $payerInfo = $_SESSION['checkout']['address'] ?? [];
                // We need phone and name from user session/db actually
                // Fetch user data for better payer info
                $stmtU = $pdo->prepare("SELECT name, email, phone, document FROM users WHERE id = ?");
                $stmtU->execute([$_SESSION['user_id']]);
                $uData = $stmtU->fetch();

                if ($uData) {
                    $payerInfo = array_merge($payerInfo, $uData);
                }

                $mpUrl = createMercadoPagoPreference($accessToken, $order_id, $items_display, $payerInfo, $ship_cost);
            }

            if ($mpUrl && strpos($mpUrl, 'ERROR:') === 0) {
                // Show API Error to User
                die("<div style='background:red; color:white; padding:2rem; text-align:center;'>
                        <h1>Erro no Pagamento</h1>
                        <p>" . htmlspecialchars(str_replace('ERROR:', '', $mpUrl)) . "</p>
                        <p>Verifique o Access Token no Admin.</p>
                        <a href='checkout_payment.php' style='color:#fff;'>Tentar Novamente</a>
                      </div>");
            }

            if ($mpUrl) {
                header("Location: " . $mpUrl);
            } else {
                // Fallback if API fails or not configured
                header("Location: order-success.php?id=$order_id&provider=mercadopago&status=manual_check");
            }
        } elseif ($pay_method === 'nupay') {
            header("Location: order-success.php?id=$order_id&provider=nupay");
        } else {
            header("Location: order-success.php?id=$order_id");
        }
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Pagamento | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .checkout-step {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .step {
            flex: 1;
            padding: 1rem;
            background: #222;
            border-radius: 8px;
            opacity: 0.5;
        }

        .step.active {
            background: var(--bg-card);
            border: 1px solid var(--primary);
            opacity: 1;
        }

        .summary-box {
            background: #222;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .pay-opt {
            display: block;
            padding: 1.5rem;
            background: #333;
            margin-bottom: 1rem;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .pay-opt:hover {
            background: #444;
        }

        .pay-opt input:checked+div {
            color: var(--primary);
        }

        .pay-opt.selected {
            border-color: var(--primary);
        }

        @media (max-width: 768px) {
            .payment-grid {
                grid-template-columns: 1fr !important;
            }
            .checkout-step {
                gap: 0.5rem;
            }
            .step h3 {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container" style="padding-top:2rem; max-width:900px;">

        <div class="checkout-step">
            <div class="step">
                <h3>1. Entrega ✅</h3>
            </div>
            <div class="step active">
                <h3>2. Pagamento</h3>
            </div>
        </div>

        <div class="payment-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem;">

            <!-- Left: Payment Methods -->
            <div>
                <h2>Como você prefere pagar?</h2>
                <form method="POST" id="pay-form">

                    <label class="pay-opt">
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <input type="radio" name="payment_method" value="mercadopago" checked>
                            <img src="https://logospng.org/download/mercado-pago/logo-mercado-pago-icone-1024.png"
                                width="40">
                            <div>
                                <strong>Mercado Pago (PIX / Cartão)</strong><br>
                                <small>Aprovacão Imediata</small>
                            </div>
                        </div>
                    </label>

                    <label class="pay-opt">
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <input type="radio" name="payment_method" value="nupay">
                            <img src="https://logodownload.org/wp-content/uploads/2019/08/nubank-logo-3.png" width="40">
                            <div>
                                <strong>NuPay (Nubank)</strong><br>
                                <small>Débito/Crédito direto no app</small>
                            </div>
                        </div>
                    </label>

                    <label class="pay-opt">
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <input type="radio" name="payment_method" value="manual">
                            <div style="font-size:1.5rem;">💬</div>
                            <div>
                                <strong>Combinar no WhatsApp</strong><br>
                                <small>Finalize o pedido e fale com um atendente</small>
                            </div>
                        </div>
                    </label>

                    <button type="submit" class="btn btn-success"
                        style="width:100%; padding:1.2rem; font-size:1.2rem; margin-top:1rem;">
                        Pagar R$
                        <?php echo number_format($grand_total, 2, ',', '.'); ?>
                    </button>
                </form>
            </div>

            <!-- Right: Order Summary -->
            <div>
                <div class="summary-box">
                    <h3>Resumo do Pedido</h3>
                    <?php foreach ($items_display as $i): ?>
                        <div
                            style="display:flex; justify-content:space-between; border-bottom:1px solid #444; padding:0.5rem 0;">
                            <span>
                                <?php echo $i['qty']; ?>x
                                <?php echo htmlspecialchars($i['name']); ?>
                            </span>
                            <span>R$
                                <?php echo number_format($i['price'] * $i['qty'], 2, ',', '.'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>

                    <div style="display:flex; justify-content:space-between; padding:1rem 0; color:#aaa;">
                        <span>Subtotal</span>
                        <span>R$
                            <?php echo number_format($total_products, 2, ',', '.'); ?>
                        </span>
                    </div>

                    <div style="display:flex; justify-content:space-between; padding:0 0 1rem 0; color:#aaa;">
                        <span>Frete (
                            <?php echo $ship_name; ?>)
                        </span>
                        <span>R$
                            <?php echo number_format($ship_cost, 2, ',', '.'); ?>
                        </span>
                    </div>

                    <div
                        style="display:flex; justify-content:space-between; padding-top:1rem; border-top:1px solid #555; font-size:1.3rem; color:white; font-weight:bold;">
                        <span>Total</span>
                        <span>R$
                            <?php echo number_format($grand_total, 2, ',', '.'); ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Style selection highlight
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function () {
                document.querySelectorAll('.pay-opt').forEach(opt => opt.classList.remove('selected'));
                this.closest('.pay-opt').classList.add('selected');
            });
        });
        // Trigger initial select
        document.querySelector('input[checked]').closest('.pay-opt').classList.add('selected');
    </script>

</body>

</html>