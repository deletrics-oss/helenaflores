<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modules_shipping.php';

session_start();

// 1. Validate Login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php"); // Gatekeeper will catch them
    exit;
}

// 1.5 Validate Lead Status (Redirect to Complete Registration)
if (isset($_SESSION['is_lead']) && $_SESSION['is_lead']) {
    $msg = urlencode("Por favor, complete seu cadastro com endereço para finalizar o pedido.");
    header("Location: " . BASE_URL . "/register.php?msg=" . $msg);
    exit;
}

// 2. Validate Cart
if (empty($_SESSION['cart'])) {
    header("Location: " . BASE_URL . "/cart.php");
    exit;
}

// 3. Prepare Cart Items for Calculation
$cart_items = [];
$total_products = 0;

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
        $vid = $parts[1] ?? null;

        if (!isset($productsMap[$pid]))
            continue;
        $p = $productsMap[$pid];

        $qty = $_SESSION['cart'][$k];

        // Handle Variation
        if ($vid) {
            $vStmt = $pdo->prepare("SELECT * FROM product_variations WHERE id = ?");
            $vStmt->execute([$vid]);
            $varData = $vStmt->fetch();
            if ($varData) {
                // APPEND VARIATION TO NAME FOR ORDER PROCESSING
                $p['name'] .= " ({$varData['type']}: {$varData['value']})";
                if ($varData['sku'])
                    $p['sku'] = $varData['sku'];
                if ($varData['price'] > 0)
                    $p['price'] = $varData['price'];
            }
        }

        // Price Select (Wholesale Logic)
        $price = $p['price'];
        if (isset($_SESSION['is_wholesale']) && $_SESSION['is_wholesale'] && $p['price_wholesale'] > 0 && $qty >= $p['min_wholesale_qty']) {
            $price = $p['price_wholesale'];
        }

        $p['qty'] = $qty;
        $total_products += ($price * $qty);
        $cart_items[] = $p;
    }
}

// 4. Handle POST (Shipping Calculation or Order Submit)
$shipping_options = [];
$selected_shipping = null;
$error = '';
$user_zip = $_POST['zipcode'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Calculate Shipping
    if (isset($_POST['action']) && $_POST['action'] === 'calc_shipping') {
        $user_zip = preg_replace('/\D/', '', $_POST['zipcode']);
        if (strlen($user_zip) === 8) {
            $shipping_options = calculateShippingOptions($user_zip, $cart_items);
        } else {
            $error = 'CEP Inválido';
        }
    }

    // B. Proceed to Payment (Step 2)
    if (isset($_POST['action']) && $_POST['action'] === 'select_shipping') {
        // Save shipping info to session and redirect to Payment Page
        $_SESSION['checkout'] = [
            'shipping_method' => $_POST['shipping_method'], // 'name|price'
            'address' => [
                'zip' => $_POST['zipcode'],
                'street' => $_POST['street'],
                'number' => $_POST['number'],
                'district' => $_POST['district'],
                'complement' => $_POST['complement'] ?? '',
                'document' => $_POST['document'] ?? '' // CPF/CNPJ
            ]
        ];
        header("Location: checkout_payment.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frete e Entrega | Fight Arcade</title>
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

        .shipping-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #444;
            margin-bottom: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .shipping-option:hover {
            border-color: var(--primary);
            background: #2a2a2a;
        }

        .shipping-option input {
            margin-right: 1rem;
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container" style="padding-top:2rem; max-width:800px;">

        <div class="checkout-step">
            <div class="step active">
                <h3>1. Entrega</h3>
            </div>
            <div class="step">
                <h3>2. Pagamento</h3>
            </div>
        </div>

        <h1>Onde vamos entregar?</h1>

        <div style="background:var(--bg-card); padding:2rem; border-radius:12px; margin-top:1rem;">

            <!-- Step 1: ZIP Search -->
            <form method="POST">
                <input type="hidden" name="action" value="calc_shipping">
                <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1; min-width:200px;">
                        <input type="text" name="zipcode" id="zipcode"
                            value="<?php echo htmlspecialchars($user_zip); ?>" placeholder="Digite seu CEP"
                            maxlength="9" required style="width:100%; height:45px;">
                    </div>
                    <div style="flex:1; min-width:200px;">
                        <button type="submit" class="btn" style="width:100%; height:45px;">Calcular Frete 🚚</button>
                    </div>
                </div>
                <div style="font-size:0.9rem; color:#888;">
                    <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank"
                        style="color:#aaa;">Não sei meu CEP</a>
                </div>
            </form>

            <?php if ($error): ?>
                <div style="padding:1rem; background:rgba(255,0,0,0.1); color:red; border-radius:6px; margin:1rem 0;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($shipping_options)): ?>
                <hr style="border-color:#333; margin:2rem 0;">

                <form method="POST">
                    <input type="hidden" name="action" value="select_shipping">
                    <input type="hidden" name="zipcode" value="<?php echo htmlspecialchars($user_zip); ?>">

                    <h3>Endereço Completo</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                        <!-- ADDRESS SECTION -->
                        <div class="checkout-box">
                            <h2>📍 Endereço de Entrega</h2>

                            <?php
                            // Fetch User Addresses
                            $addresses = [];
                            if (isset($_SESSION['user_id'])) {
                                $addresses = $pdo->query("SELECT * FROM user_addresses WHERE user_id = {$_SESSION['user_id']} ORDER BY is_default DESC")->fetchAll(PDO::FETCH_ASSOC);
                            }
                            ?>

                            <?php if (!empty($addresses)): ?>
                                <div style="margin-bottom:1.5rem;">
                                    <label style="display:block; margin-bottom:10px; font-weight:bold;">Escolha um
                                        endereço:</label>
                                    <div
                                        style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:10px;">
                                        <?php foreach ($addresses as $addr): ?>
                                            <label class="addr-option">
                                                <input type="radio" name="selected_address" value="<?php echo $addr['id']; ?>"
                                                    data-zip="<?php echo $addr['zipcode']; ?>"
                                                    data-addr="<?php echo htmlspecialchars(json_encode($addr)); ?>"
                                                    onchange="fillAddress(this)" style="margin-top:5px;">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($addr['name']); ?></strong>
                                                    <div style="font-size:0.85rem; color:#ccc;">
                                                        <?php echo htmlspecialchars($addr['address']); ?>,
                                                        <?php echo htmlspecialchars($addr['number']); ?><br>
                                                        <?php echo htmlspecialchars($addr['city']); ?>/<?php echo htmlspecialchars($addr['state']); ?>
                                                        - <?php echo htmlspecialchars($addr['zipcode']); ?>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                        <label class="addr-option">
                                            <input type="radio" name="selected_address" value="new"
                                                onchange="toggleNewAddress(true)">
                                            <strong>📝 Outro Endereço</strong>
                                        </label>
                                    </div>
                                    <a href="my-addresses.php" target="_blank"
                                        style="font-size:0.8rem; margin-top:5px; display:inline-block;">Gerenciar Endereços
                                        ↗</a>
                                </div>
                            <?php endif; ?>

                            <div id="new-addr-form" style="<?php echo !empty($addresses) ? 'display:none;' : ''; ?>">
                                <div class="form-row">
                                    <div style="flex:1;">
                                        <label>CEP *</label>
                                        <div style="display:flex; gap:5px;">
                                            <input type="text" name="zipcode" id="address_zipcode"
                                                value="<?php echo htmlspecialchars($user_zip); ?>" placeholder="00000-000"
                                                maxlength="9" onblur="fetchCep(this.value)">
                                            <button type="button"
                                                onclick="document.querySelector('form[name=shipping_form] button[value=calc_shipping]').click();"
                                                class="btn-sm"
                                                style="background:var(--primary); color:#000; border:none;">Calcular</button>
                                        </div>
                                    </div>
                                    <div style="flex:2;">
                                        <label>Rua *</label>
                                        <input type="text" name="street" id="address_street"
                                            value="<?php echo htmlspecialchars($_SESSION['checkout']['address']['street'] ?? ''); ?>"
                                            required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div style="flex:1;">
                                        <label>Número *</label>
                                        <input type="text" name="number" id="address_number"
                                            value="<?php echo htmlspecialchars($_SESSION['checkout']['address']['number'] ?? ''); ?>"
                                            required>
                                    </div>
                                    <div style="flex:2;">
                                        <label>Complemento</label>
                                        <input type="text" name="complement" id="address_complement"
                                            value="<?php echo htmlspecialchars($_SESSION['checkout']['address']['complement'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div style="flex:2;">
                                        <label>Bairro *</label>
                                        <input type="text" name="district" id="address_district"
                                            value="<?php echo htmlspecialchars($_SESSION['checkout']['address']['district'] ?? ''); ?>"
                                            required>
                                    </div>
                                    <div style="flex:2;">
                                        <label>Cidade *</label>
                                        <input type="text" name="city" id="address_city"
                                            value="<?php echo htmlspecialchars($_SESSION['checkout']['address']['city'] ?? ''); ?>"
                                            required> <!-- readonly removed for flexibility -->
                                    </div>
                                    <div style="flex:1;">
                                        <label>UF *</label>
                                        <input type="text" name="state" id="address_state"
                                            value="<?php echo htmlspecialchars($_SESSION['checkout']['address']['state'] ?? ''); ?>"
                                            required maxlength="2">
                                    </div>
                                </div>
                                <div style="margin-bottom:1rem;">
                                    <label>CPF ou CNPJ (Para Nota Fiscal) *</label>
                                    <input type="text" name="document" id="document"
                                        placeholder="CPF ou CNPJ (Para Nota Fiscal)" required
                                        style="width:100%; padding:0.8rem; background:#111; border:1px solid #444; color:#fff; border-radius:4px;">
                                </div>
                            </div>
                        </div>

                        <h3>Escolha o Frete</h3>
                        <div class="shipping-list">
                            <?php foreach ($shipping_options as $opt): ?>
                                <label class="shipping-option">
                                    <div style="display:flex; align-items:center;">
                                        <input type="radio" name="shipping_method"
                                            value="<?php echo $opt['name'] . '|' . $opt['price']; ?>" required>
                                        <div>
                                            <strong
                                                style="display:block; font-size:1.1rem;"><?php echo $opt['icon'] . ' ' . $opt['name']; ?></strong>
                                            <small><?php echo ($opt['days'] == 0) ? 'Entrega Hoje/Imediata' : 'Até ' . $opt['days'] . ' dias úteis'; ?></small>
                                        </div>
                                    </div>
                                    <div style="font-weight:bold; color:var(--primary); font-size:1.2rem;">
                                        <?php echo ($opt['price'] > 0) ? 'R$ ' . number_format($opt['price'], 2, ',', '.') : 'Grátis'; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div style="text-align:right; margin-top:2rem;">
                            <button type="submit" class="btn" style="padding:1rem 3rem;">Ir para Pagamento ➡️</button>
                        </div>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function buscarCep() {
            const cep = document.getElementById('zipcode').value.replace(/\D/g, '');
            if (cep.length !== 8) { alert('Digite um CEP válido com 8 dígitos'); return; }

            // Visual feedback
            const btn = document.querySelector('button[onclick="buscarCep()"]');
            if (btn) btn.innerText = '⌛ Buscando...';

            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(r => r.json())
                .then(data => {
                    if (btn) btn.innerText = '🔍 Buscar Endereço';

                    if (data.erro) { return; }

                    if (document.getElementById('street')) {
                        document.getElementById('street').value = data.logradouro;
                        document.getElementById('district').value = data.bairro;
                        // Focus number
                        document.getElementById('number').focus();
                    }
                })
                .catch(e => {
                    // Fail silently
                });
        }

        // Auto-mask CEP
        document.getElementById('zipcode').addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5, 8);
            e.target.value = v;
        });

        // Auto-Trigger if we have ZIP and Address fields are present (Page Reloaded)
        window.addEventListener('load', function () {
            if (document.getElementById('zipcode').value.length >= 8 && document.getElementById('street')) {
                buscarCep();
            }
        });
    </script>

</body>

</html>