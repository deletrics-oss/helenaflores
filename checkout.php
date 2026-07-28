<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modules_shipping.php';

// session_start is already handled by config.php

// 1. Validate Login
if (!isset($_SESSION['user_id'])) {
    // Use absolute URL for redirect to avoid path issues on mobile
    header("Location: " . BASE_URL . "/index.php?redirect=" . urlencode(BASE_URL . "/checkout.php"));
    exit;
}

// 2. Validate Cart
if (empty($_SESSION['cart'])) {
    header("Location: " . BASE_URL . "/cart.php");
    exit;
}

// FETCH USER ADDRESSES FIRST
$addresses = $pdo->query("SELECT * FROM user_addresses WHERE user_id = {$_SESSION['user_id']} ORDER BY is_default DESC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Calculation Logic (Products)
$cart_items = [];
$total_products = 0;
$keys = array_keys($_SESSION['cart']);

if (!empty($keys)) {
    $pids = [];
    foreach ($keys as $k)
        $pids[] = explode('-', $k)[0];
    $pids = array_unique($pids);
    $idsStr = implode(',', $pids);

    $products_db = $pdo->query("SELECT * FROM products WHERE id IN ($idsStr)")->fetchAll(PDO::FETCH_ASSOC);
    $productsMap = array_column($products_db, null, 'id');

    foreach ($keys as $k) {
        $parts = explode('-', $k);
        $pid = $parts[0];
        $vid = $parts[1] ?? null;

        if (!isset($productsMap[$pid]))
            continue;
        $p = $productsMap[$pid];
        $qty = $_SESSION['cart'][$k];

        // Variation
        if ($vid) {
            $vStmt = $pdo->prepare("SELECT * FROM product_variations WHERE id = ?");
            $vStmt->execute([$vid]);
            $var = $vStmt->fetch();
            if ($var) {
                $p['name'] .= " ({$var['type']}: {$var['value']})";
                if ($var['price'] > 0)
                    $p['price'] = $var['price'];
            }
        }

        // Wholesale
        if (isset($_SESSION['is_wholesale']) && $_SESSION['is_wholesale'] && $p['price_wholesale'] > 0 && $qty >= $p['min_wholesale_qty']) {
            $p['price'] = $p['price_wholesale'];
        }

        $p['qty'] = $qty;
        $total_products += ($p['price'] * $qty);
        $cart_items[] = $p;
    }
}

// 4. Handle Shipping Calculation (Captured from Main Form or Hidden Form)
$shipping_options = [];
$error = '';
$user_zip = '';

// Variables to persist form fields (New Address)
$f_zip = $_POST['zipcode'] ?? '';
$f_street = $_POST['street'] ?? '';
$f_number = $_POST['number'] ?? '';
$f_complement = $_POST['complement'] ?? '';
$f_district = $_POST['district'] ?? '';
$f_city = $_POST['city'] ?? '';
$f_state = $_POST['state'] ?? '';
$f_document = $_POST['document'] ?? '';

if (isset($_POST['action']) && $_POST['action'] === 'calc_shipping') {
    $user_zip = preg_replace('/\D/', '', $_POST['zipcode']);
}
// B. If not, auto-calc using Default Address (if exists)
elseif (!empty($addresses)) {
    // Determine which address to use. 
    $defaultAddr = $addresses[0]; // Ordered by is_default DESC
    $user_zip = preg_replace('/\D/', '', $defaultAddr['zipcode']);

    // If we're not explicitly calculating a NEW one, and we have a selected ID that matches a card,
    // we should use THAT card's ZIP if available.
    if (isset($_POST['selected_addr_id']) && $_POST['selected_addr_id'] !== 'new') {
        foreach ($addresses as $a) {
            if ($a['id'] == $_POST['selected_addr_id']) {
                $user_zip = preg_replace('/\D/', '', $a['zipcode']);
                break;
            }
        }
    }
}

// Perform Calc if we have a ZIP
if (strlen($user_zip) === 8) {
    try {
        $shipping_options = calculateShippingOptions($user_zip, $cart_items);
    } catch (Exception $e) {
        $error = "Erro ao calcular frete: " . $e->getMessage();
    }
}

// 5. Handle Final Submission
if (isset($_POST['action']) && $_POST['action'] === 'select_shipping') {
    if (!isset($_POST['shipping_method'])) {
        $error = "Selecione uma opção de frete.";
    } else {
        $_SESSION['checkout'] = [
            'shipping_method' => $_POST['shipping_method'],
            'address' => [
                'zip' => $_POST['zipcode'],
                'street' => $_POST['street'],
                'number' => $_POST['number'],
                'complement' => $_POST['complement'] ?? '',
                'district' => $_POST['district'],
                'city' => $_POST['city'],
                'state' => $_POST['state'],
                'document' => $_POST['document'] ?? ''
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
    <title>Entrega | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .checkout-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            padding-top: 30px;
        }

        .col-main {
            flex: 2;
        }

        .col-side {
            flex: 1;
        }

        @media (max-width: 768px) {
            .checkout-container {
                flex-direction: column;
            }
            .col-main, .col-side {
                width: 100%;
                min-width: 100% !important;
            }
            .addr-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Steps */
        .step-indicator {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .step-badge {
            background: #222;
            color: #888;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
        }

        .step-badge.active {
            background: var(--primary);
            color: #000;
        }

        /* Modern Grid */
        .addr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .addr-card {
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            position: relative;
            transition: 0.2s;
        }

        .addr-card:hover {
            border-color: #666;
            background: #222;
        }

        .addr-card.active {
            border-color: var(--primary);
            background: rgba(0, 255, 136, 0.05);
        }

        .check-mark {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #555;
        }

        .addr-card.active .check-mark {
            background: var(--primary);
            border-color: var(--primary);
        }

        .addr-card.active .check-mark::after {
            content: '✓';
            color: #000;
            font-weight: bold;
            position: absolute;
            top: 1px;
            left: 5px;
            font-size: 14px;
        }

        /* Form */
        .new-addr-form {
            background: #151515;
            border: 1px solid #333;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            display: none;
        }

        .new-addr-form.open {
            display: block;
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Shipping Compact */
        .shipping-grid {
            display: grid;
            gap: 10px;
            margin-top: 10px;
        }

        /* Shipping Compact & Perfect Alignment */
        .shipping-grid {
            display: grid;
            gap: 12px;
            margin-top: 15px;
        }

        .ship-opt {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
        }

        .ship-opt:hover {
            border-color: #555;
            background: #202020;
        }

        .ship-opt.active {
            border-color: var(--primary);
            background: rgba(0, 255, 136, 0.05);
        }

        /* Custom Checkmark for Shipping */
        .ship-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #555;
            position: relative;
            flex-shrink: 0;
            margin-right: 15px;
        }

        .ship-opt input:checked+.ship-check {
            background: var(--primary);
            border-color: var(--primary);
        }

        .ship-opt input:checked+.ship-check::after {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            width: 8px;
            height: 8px;
            background: #000;
            border-radius: 50%;
        }

        .ship-main {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
            padding-right: 15px;
        }

        .ship-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .ship-name-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ship-name {
            font-size: 1rem;
            font-weight: bold;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ship-days {
            font-size: 0.8rem;
            color: #888;
            background: #222;
            padding: 2px 8px;
            border-radius: 4px;
            white-space: nowrap;
        }

        .ship-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--primary);
            flex-shrink: 0;
            text-align: right;
        }

        /* ===== MOBILE FIXES ===== */
        @media (max-width: 768px) {
            .checkout-container {
                flex-direction: column;
                padding-top: 15px;
                padding-bottom: 100px; /* Espaço para o botão fixo */
            }
            .col-main, .col-side {
                width: 100% !important;
                min-width: 100% !important;
            }
            .addr-grid {
                grid-template-columns: 1fr;
            }
            /* Form rows empilhados */
            .form-row,
            div[style*="display:flex"][style*="gap:10px"] {
                flex-direction: column !important;
            }
            .form-row input,
            .form-row select {
                width: 100% !important;
                flex: unset !important;
            }
            /* Sidebar de resumo colapsável */
            .col-side > div {
                position: relative !important;
                top: auto !important;
            }
            /* Botão Pagar fixo no fundo */
            .mobile-pay-fixed {
                display: block !important;
            }
            /* Esconder o botão inline no mobile */
            .desktop-pay-btn {
                display: none !important;
            }
            /* Step badges menores */
            .step-indicator {
                gap: 6px;
            }
            .step-badge {
                padding: 8px 14px;
                font-size: 0.85rem;
            }
            /* Shipping options mais tocáveis */
            .ship-opt {
                padding: 14px 16px;
            }
            .ship-name {
                font-size: 0.9rem;
            }
            /* Show mobile pay button */
            .mobile-pay-fixed {
                display: block !important;
            }
            body {
                padding-bottom: 80px !important;
            }
        }

        /* Botão mobile fixo (escondido no desktop) */
        .mobile-pay-fixed {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 999;
            background: linear-gradient(135deg, var(--primary), #00cc66);
            color: #000;
            border: none;
            padding: 18px;
            font-size: 1.2rem;
            font-weight: 900;
            cursor: pointer;
            text-align: center;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
            letter-spacing: 1px;
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container checkout-container">

        <div class="col-main">
            <div class="step-indicator">
                <div class="step-badge active">1. Entrega</div>
                <div class="step-badge">2. Pagamento</div>
            </div>

            <h2 style="margin-bottom:20px;">📍 Onde vamos entregar?</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- ADDRESS FORM SUBMISSION -->
            <form method="POST" id="checkoutForm">
                <input type="hidden" name="action" value="select_shipping">

                <!-- ADDRESS SELECTION -->
                <div class="addr-grid">
                    <?php
                    $activeId = 0;
                    // If we calculated using a ZIP, try to match it to an address ID to highlight it
                    // Or simply default to first if standard load
                    if (!empty($addresses))
                        $activeId = $addresses[0]['id'];

                    // If user just switched address via JS submitting form, maybe we should preserve selection?
                    if (isset($_POST['selected_addr_id']))
                        $activeId = $_POST['selected_addr_id'];
                    ?>

                    <?php foreach ($addresses as $addr): ?>
                        <div class="addr-card <?php echo ($addr['id'] == $activeId && $activeId != 'new') ? 'active' : ''; ?>"
                            onclick="chooseAddress(<?php echo $addr['id']; ?>, '<?php echo $addr['zipcode']; ?>')">
                            <div class="check-mark"></div>
                            <strong style="display:block; font-size:1.1rem; color:white; margin-bottom:5px;">
                                <?php echo $addr['name']; ?>
                            </strong>
                            <div style="font-size:0.9rem; color:#aaa; line-height:1.5;">
                                <?php echo $addr['address']; ?>, <?php echo $addr['number']; ?><br>
                                <?php echo $addr['neighborhood']; ?> -
                                <?php echo $addr['city']; ?>/<?php echo $addr['state']; ?>
                            </div>

                            <!-- Hidden Data for this card -->
                            <textarea id="data-<?php echo $addr['id']; ?>" style="display:none;">
                                                    <?php echo json_encode($addr); ?>
                                                </textarea>
                        </div>
                    <?php endforeach; ?>

                    <!-- NEW ADDRESS CARD -->
                    <div class="addr-card <?php echo ($activeId == 'new' || empty($addresses)) ? 'active' : ''; ?>"
                        onclick="chooseNewAddress()">
                        <div class="check-mark"></div>
                        <strong style="display:block; font-size:1.1rem; color:white; margin-bottom:5px;">✨ Outro
                            Endereço</strong>
                        <div style="font-size:0.9rem; color:#aaa;">
                            Enviar para um local diferente ou novo.
                        </div>
                    </div>
                </div>

                <!-- HIDDEN INPUTS FOR CHOSEN ADDRESS -->
                <input type="hidden" name="selected_addr_id" id="selected_addr_id" value="<?php echo $activeId; ?>">

                <div id="formFields"
                    class="new-addr-form <?php echo ($activeId == 'new' || empty($addresses)) ? 'open' : ''; ?>">
                    <h3 style="margin-bottom:15px; color:var(--primary);">Dados do Endereço</h3>
                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                        <input type="text" name="zipcode" id="f_zip" value="<?php echo $user_zip; ?>"
                            placeholder="CEP (00000-000)" onblur="fetchCep(this.value)" style="flex:1;">
                        <button type="button" class="btn-sm" onclick="recalcShipping()"
                            title="Recalcular Frete com este CEP">🔄 Calcular</button>
                        <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank"
                            style="padding:10px; color:#888;">?</a>
                    </div>

                    <div class="form-row" style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="text" name="street" id="f_street"
                            value="<?php echo htmlspecialchars($f_street); ?>" placeholder="Rua" style="flex:2;"
                            required>
                        <input type="text" name="number" id="f_number"
                            value="<?php echo htmlspecialchars($f_number); ?>" placeholder="Número" style="flex:1;"
                            required>
                    </div>
                    <div class="form-row" style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="text" name="complement" id="f_comp"
                            value="<?php echo htmlspecialchars($f_complement); ?>" placeholder="Complemento"
                            style="flex:1;">
                        <input type="text" name="district" id="f_district"
                            value="<?php echo htmlspecialchars($f_district); ?>" placeholder="Bairro" style="flex:1;"
                            required>
                    </div>
                    <div class="form-row" style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="text" name="city" id="f_city" value="<?php echo htmlspecialchars($f_city); ?>"
                            placeholder="Cidade" style="flex:2;" required>
                        <input type="text" name="state" id="f_state" value="<?php echo htmlspecialchars($f_state); ?>"
                            placeholder="UF" style="flex:1;" required maxlength="2">
                    </div>
                    <input type="text" name="document" id="f_doc" value="<?php echo htmlspecialchars($f_document); ?>"
                        placeholder="CPF/CNPJ para Nota Fiscal">
                </div>

                <!-- SHIPPING OPTIONS -->
                <?php if (!empty($shipping_options)): ?>
                    <h3 style="margin:25px 0 15px 0; font-size:1.2rem; color:var(--primary);">🚚 Opções de Envio</h3>
                    <div id="shipOptions" class="shipping-grid">
                        <?php foreach ($shipping_options as $opt): ?>
                            <label class="ship-opt" onclick="updateShipActive(this)">
                                <div class="ship-main">
                                    <input type="radio" name="shipping_method" style="display:none;"
                                        value="<?php echo $opt['name'] . '|' . $opt['price']; ?>" required>
                                    <div class="ship-check"></div>
                                    <div class="ship-info">
                                        <div class="ship-name-row">
                                            <span class="ship-name"><?php echo $opt['icon'] . ' ' . $opt['name']; ?></span>
                                            <span
                                                class="ship-days"><?php echo $opt['days'] == 0 ? 'Rápida' : $opt['days'] . ' dias'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="ship-price">
                                    <?php echo $opt['price'] > 0 ? 'R$ ' . number_format($opt['price'], 2, ',', '.') : 'Grátis'; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div style="text-align:right; margin-top:25px;" class="desktop-pay-btn">
                        <button type="submit" class="btn"
                            onclick="document.querySelector('input[name=\'action\']').value='select_shipping';"
                            style="padding:15px 40px; font-size:1.2rem; border-radius:8px; box-shadow: 0 4px 15px rgba(0,255,136,0.2);">Pagar
                            ➡️</button>
                    </div>

                <?php else: ?>
                    <div
                        style="text-align:center; padding:40px; color:#666; background:#111; border-radius:12px; margin-top:30px;">
                        <?php if ($activeId == 'new' || empty($addresses)): ?>
                            👆 Digite o CEP e clique em "Calcular" para ver o frete.
                        <?php else: ?>
                            ⌛ Calculando opções de entrega...
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </form>

            <!-- MOBILE FIXED PAY BUTTON -->
            <?php if (!empty($shipping_options)): ?>
            <button type="button" class="mobile-pay-fixed" onclick="document.querySelector('input[name=\'action\']').value='select_shipping'; document.getElementById('checkoutForm').submit();">
                💳 PAGAR AGORA ➡️
            </button>
            <?php else: ?>
            <button type="button" class="mobile-pay-fixed" style="background: linear-gradient(135deg, var(--primary), #ff8c00);" onclick="document.querySelector('input[name=\'action\']').value='calc_shipping'; document.getElementById('checkoutForm').submit();">
                📦 CALCULAR FRETE ➡️
            </button>
            <?php endif; ?>
        </div>

        <!-- SIDEBAR -->
        <div class="col-side">
            <div style="background:#222; padding:20px; border-radius:12px; position:sticky; top:20px;">
                <h3>Resumo</h3>
                <div style="margin-top:15px; display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($cart_items as $item): ?>
                        <div
                            style="display:flex; justify-content:space-between; font-size:0.9rem; border-bottom:1px solid #333; padding-bottom:5px;">
                            <span><?php echo $item['qty']; ?>x
                                <?php echo mb_strimwidth($item['name'], 0, 20, '...'); ?></span>
                            <span>R$ <?php echo number_format($item['price'] * $item['qty'], 2, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div
                    style="border-top:1px solid #444; margin-top:15px; padding-top:15px; font-size:1.2rem; font-weight:bold; display:flex; justify-content:space-between;">
                    <span>Total:</span>
                    <span>R$ <?php echo number_format($total_products, 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- HIDDEN CALC FORM -->
    <form id="calcForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="calc_shipping">
        <input type="hidden" name="zipcode" id="calc_zip">
        <input type="hidden" name="selected_addr_id" id="calc_addr_id">
    </form>

    <script>
        // Toggle active class on shipping options
        function updateShipActive(label) {
            document.querySelectorAll('.ship-opt').forEach(opt => opt.classList.remove('active'));
            label.classList.add('active');
            const radio = label.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }

        // FILL FORM ON LOAD IF ACTIVE ID IS SET
        document.addEventListener('DOMContentLoaded', function () {
            // If we have an active address (not new), populate the visible form fields
            // (even though they are hidden, they'll be submitted if user switches to new, but mostly to keep state)
            const activeId = document.getElementById('selected_addr_id').value;
            if (activeId && activeId !== 'new') {
                try {
                    const json = document.getElementById('data-' + activeId).value;
                    fillFields(JSON.parse(json));
                } catch (e) { }
            }
        });

        function chooseAddress(id, zip) {
            // Update UI
            document.querySelectorAll('.addr-card').forEach(c => c.classList.remove('active'));
            // Find the card clicked? No, we need to target specific element?
            // Actually the onclick is on the element itself, so we can use 'this' if we passed it,
            // but we passed ID. Let's iterate.
            // Simplified: Reload page with calc logic for this ID??
            // Yes, to be safe and accurate with shipping.

            document.getElementById('calc_zip').value = zip;
            document.getElementById('calc_addr_id').value = id;
            document.getElementById('calcForm').submit(); // RELOAD TO CALC SHIPPING
        }

        function chooseNewAddress() {
            if (document.getElementById('selected_addr_id').value === 'new') {
                const form = document.getElementById('formFields');
                if (!form.classList.contains('open')) form.classList.add('open');
                return;
            }
            document.getElementById('selected_addr_id').value = 'new';
            document.querySelectorAll('.addr-card').forEach(c => c.classList.remove('active'));
            // Hightlight new card? Hard to select without 'this'.
            // Let's just Open the Form.

            const form = document.getElementById('formFields');
            form.classList.add('open');

            // Clear fields
            document.getElementById('f_zip').value = '';
            document.getElementById('f_street').value = '';
            document.getElementById('f_number').value = '';
            document.getElementById('f_comp').value = '';
            document.getElementById('f_district').value = '';
            document.getElementById('f_city').value = '';
            document.getElementById('f_state').value = '';

            // Hide shipping options until calculated
            const ship = document.getElementById('shipOptions');
            if (ship) ship.style.display = 'none';
        }

        function fillFields(data) {
            document.getElementById('f_zip').value = data.zipcode;
            document.getElementById('f_street').value = data.address;
            document.getElementById('f_number').value = data.number;
            document.getElementById('f_comp').value = data.complement;
            document.getElementById('f_district').value = data.neighborhood;
            document.getElementById('f_city').value = data.city;
            document.getElementById('f_state').value = data.state;
        }

        function recalcShipping() {
            const zip = document.getElementById('f_zip').value;
            if (zip.length < 8) {
                alert('Digite um CEP válido.');
                return;
            }

            const form = document.getElementById('checkoutForm');
            const actionInput = form.querySelector('input[name="action"]');
            actionInput.value = 'calc_shipping';
            form.submit(); // RELOAD FULL FORM TO PERSIST FIELDS
        }
        function
            fetchCep(cep) {
            cep = cep.replace(/\D/g, ''); if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`).then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('f_street').value = data.logradouro;
                            document.getElementById('f_district').value = data.bairro;
                            document.getElementById('f_city').value = data.localidade;
                            document.getElementById('f_state').value = data.uf;
                            document.getElementById('f_number').focus();
                        }
                    });
            }
        }
    </script>

</body>

</html>