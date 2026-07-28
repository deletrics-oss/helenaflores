<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();
if (empty($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// AJAX: Search Products
if (isset($_GET['search'])) {
    header('Content-Type: application/json');
    $q = "%" . $_GET['search'] . "%";
    $sql = "(SELECT id, NULL as var_id, name, price, stock_qty, image_path, 'product' as type FROM products WHERE (name LIKE ? OR sku LIKE ?) AND active = 1)
            UNION
            (SELECT p.id, v.id as var_id, CONCAT(p.name, ' - ', v.type, ': ', v.value) as name, COALESCE(v.price, p.price) as price, v.stock_qty, COALESCE(v.image_path, p.image_path) as image_path, 'variation' as type 
             FROM product_variations v JOIN products p ON v.product_id = p.id 
             WHERE (p.name LIKE ? OR v.sku LIKE ? OR v.value LIKE ?) AND p.active = 1)
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q, $q, $q, $q, $q]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// AJAX: Search Customers
if (isset($_GET['search_customer'])) {
    header('Content-Type: application/json');
    $q = "%" . $_GET['search_customer'] . "%";
    $stmt = $pdo->prepare("SELECT id, name, phone, document FROM users WHERE role != 'admin' AND (is_lead = 0 OR is_lead IS NULL) AND (name LIKE ? OR phone LIKE ? OR document LIKE ?) LIMIT 10");
    $stmt->execute([$q, $q, $q]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// AJAX: Quick Customer Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_customer'])) {
    header('Content-Type: application/json');
    $name = $_POST['cname'];
    $phone = $_POST['cphone'];
    $pass = password_hash(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6), PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, phone, password, role, is_lead, email) VALUES (?, ?, ?, 'customer', 0, ?)");
        $email = 'pdv_' . time() . '@temp.com';
        $stmt->execute([$name, $phone, $pass, $email]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_json'], true);
    $subtotal = (float) $_POST['subtotal'];
    $discount = (float) $_POST['discount'];
    $shipping_cost = (float) ($_POST['shipping_cost'] ?? 0);
    $total = (float) $_POST['total'];
    $payment = $_POST['payment_method'];
    $status = $_POST['order_status'] ?? 'pending';
    $customer_id = (int) $_POST['customer_id'];

    if (!empty($cart) && $customer_id > 0) {
        $pdo->beginTransaction();
        try {
            try {
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_method, discount_amount, shipping_cost, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$customer_id, $total, $status, 'PDV - ' . $payment, $discount, $shipping_cost]);
            } catch (PDOException $col_err) {
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_method, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$customer_id, $total, $status, 'PDV - ' . $payment]);
            }
            $order_id = $pdo->lastInsertId();

            try {
                $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variation_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
            } catch (PDOException $e) {
                $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            }

            foreach ($cart as $item) {
                $pid = $item['pid'];
                $vid = !empty($item['vid']) ? $item['vid'] : null;
                $qty = (int) $item['qty'];
                $price = (float) $item['price'];

                // Fetch current cost price for historical record
                $cost_price = 0;
                if ($vid) {
                    $v_stmt = $pdo->prepare("SELECT cost_price FROM product_variations WHERE id = ?");
                    $v_stmt->execute([$vid]);
                    $cost_price = (float)$v_stmt->fetchColumn();
                } else {
                    $p_stmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
                    $p_stmt->execute([$pid]);
                    $cost_price = (float)$p_stmt->fetchColumn();
                }

                try {
                    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variation_id, product_name, quantity, unit_price, subtotal, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $item_stmt->execute([$order_id, $pid, $vid, $item['name'], $qty, $price, $price * $qty, $cost_price]);
                } catch (PDOException $e) {
                    $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$order_id, $pid, $item['name'], $qty, $price, $price * $qty, $cost_price]);
                }

                if ($vid) {
                    $pdo->prepare("UPDATE product_variations SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $vid]);
                } else {
                    $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $pid]);
                }
            }

            // --- FINANCIAL LEDGER INTEGRATION ---
            if ($customer_id > 0) {
                // 1. If already paid, register payment
                if ($status === 'paid') {
                    $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, ?, ?)")
                        ->execute([$customer_id, $total, $payment, "Pagamento integral do Pedido PDV #$order_id"]);
                }

                // 2. Sync dynamic current_debt for the user
                $pdo->prepare("UPDATE users u SET current_debt = (
                    (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                    (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
                ) WHERE id = ?")->execute([$customer_id]);
            }

            $pdo->commit();
            header("Location: pos.php?success=1&sale_id=" . $order_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro: " . $e->getMessage();
        }
    } else {
        $error = "Selecione um cliente e adicione itens ao carrinho.";
    }
}

// Initial products
$initialProducts = $pdo->query("(SELECT id, NULL as var_id, name, price, stock_qty, image_path, 'product' as type FROM products WHERE active = 1 AND stock_qty > 0)
UNION (SELECT p.id, v.id as var_id, CONCAT(p.name, ' - ', v.type, ': ', v.value) as name, COALESCE(v.price, p.price) as price, v.stock_qty, COALESCE(v.image_path, p.image_path) as image_path, 'variation' as type FROM product_variations v JOIN products p ON v.product_id = p.id WHERE p.active = 1 AND v.stock_qty > 0) LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$saleSuccess = isset($_GET['success']) ? true : false;
$saleId = isset($_GET['sale_id']) ? (int) $_GET['sale_id'] : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>PDV - Frente de Loja | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root{--primary:#f1c40f;--bg-pdv:#0f131a;--bg-card:#1a1e26;--text:#ecf0f1}
        *{box-sizing:border-box}
        body{background:var(--bg-pdv);color:var(--text);font-family:'Segoe UI',sans-serif;margin:0;display:flex;height:100vh;overflow:hidden}
        .pos-container{flex:1;display:flex;flex-direction:column;padding:20px}
        .top-bar{display:flex;gap:15px;align-items:center;margin-bottom:15px}
        .search-box{position:relative;flex:1}
        #search-input{width:100%;padding:12px 45px 12px 15px;background:var(--bg-card);border:2px solid #333;border-radius:10px;color:#fff;font-size:1.1rem}
        .search-box i{position:absolute;right:15px;top:50%;transform:translateY(-50%);color:#666}
        .results-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;overflow-y:auto;flex:1}
        .product-item{background:var(--bg-card);border-radius:8px;padding:8px;text-align:center;cursor:pointer;transition:transform .2s,border-color .2s;border:2px solid transparent}
        .product-item:hover{transform:translateY(-3px);border-color:var(--primary)}
        .product-item img{width:100%;height:100px;object-fit:cover;border-radius:5px;margin-bottom:6px}
        .product-item .p-name{font-weight:bold;font-size:.8rem;height:2.2rem;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;margin-bottom:4px}
        .product-item .p-price{color:var(--primary);font-weight:bold;font-size:1rem}
        .cart-panel{width:420px;background:var(--bg-card);border-left:2px solid #333;display:flex;flex-direction:column}
        .cart-header{padding:15px;border-bottom:1px solid #333;display:flex;justify-content:space-between;align-items:center}
        .cart-items{flex:1;overflow-y:auto;padding:10px}
        .cart-item{display:flex;gap:8px;padding:8px;background:#252a33;border-radius:6px;margin-bottom:6px;align-items:center}
        .cart-item img{width:45px;height:45px;object-fit:cover;border-radius:4px}
        .cart-item-info{flex:1}
        .cart-item-name{font-size:.8rem;font-weight:bold}
        .cart-item-price{color:var(--primary);font-size:.8rem}
        .cart-qty-ctrl{display:flex;align-items:center;gap:6px}
        .cart-qty-ctrl button{background:#333;color:white;border:none;width:22px;height:22px;border-radius:3px;cursor:pointer}
        .cart-footer{padding:15px;background:#0b0e13;border-top:2px solid var(--primary)}
        .total-row{display:flex;justify-content:space-between;font-size:1.3rem;font-weight:bold;margin-bottom:8px}
        .payment-methods{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px}
        .pay-btn{background:#252a33;border:1px solid #444;color:#fff;padding:8px;border-radius:6px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:3px;font-size:.75rem}
        .pay-btn.active{border-color:var(--primary);background:rgba(241,196,15,.1)}
        .btn-checkout{background:var(--primary);color:#000;width:100%;padding:12px;border:none;border-radius:8px;font-weight:bold;font-size:1.1rem;cursor:pointer}
        .btn-checkout:disabled{opacity:.5;cursor:not-allowed}
        .customer-section{background:#252a33;border-radius:8px;padding:10px;margin-bottom:10px}
        .customer-section input{width:100%;padding:8px;background:#111;border:1px solid #444;color:#fff;border-radius:4px;margin-bottom:5px}
        .customer-result{padding:6px 8px;cursor:pointer;border-bottom:1px solid #333;font-size:.85rem}
        .customer-result:hover{background:#333}
        .discount-row{display:flex;gap:10px;align-items:center;margin-bottom:8px}
        .discount-row input{width:120px;padding:6px;background:#111;border:1px solid #444;color:#fff;border-radius:4px;text-align:right}
        .status-toggle{display:flex;gap:8px;margin-bottom:10px}
        .status-btn{flex:1;padding:8px;border:1px solid #444;background:#252a33;color:#fff;border-radius:6px;cursor:pointer;text-align:center;font-size:.8rem}
        .status-btn.active-paid{border-color:#2ecc71;background:rgba(46,204,113,.15);color:#2ecc71}
        .status-btn.active-pending{border-color:#e74c3c;background:rgba(231,76,60,.15);color:#e74c3c}
        .quick-form{background:#1a1e26;border:1px solid var(--primary);border-radius:8px;padding:10px;margin-top:5px}
        .quick-form input{width:100%;padding:8px;background:#111;border:1px solid #444;color:#fff;border-radius:4px;margin-bottom:6px}
        .hotkey-badge{display:inline-block;background:#333;color:#888;font-size:.55rem;padding:1px 4px;border-radius:3px;margin-left:3px;font-weight:800;letter-spacing:.5px}
        @keyframes confetti-fall{0%{transform:translateY(-10vh) rotate(0deg);opacity:1}100%{transform:translateY(105vh) rotate(720deg);opacity:0}}
        .confetti-piece{position:fixed;width:10px;height:10px;top:-10px;z-index:999999;animation:confetti-fall 3s ease-in forwards;border-radius:2px}
    </style>
</head>
<body>
    <div class="pos-container">
        <div class="top-bar">
            <div class="search-box">
                <input type="text" id="search-input" placeholder="Buscar produto por nome ou SKU..." autocomplete="off">
                <i class="fas fa-search"></i>
            </div>
            <a href="index.php" style="background:#e74c3c;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;white-space:nowrap"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </div>
        <div class="results-grid" id="results"></div>
    </div>

    <div class="cart-panel">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Carrinho</h3>
            <button onclick="clearCart()" style="background:none;border:none;color:#e74c3c;cursor:pointer"><i class="fas fa-trash"></i></button>
        </div>

        <!-- Customer Selection -->
        <div style="padding:10px;border-bottom:1px solid #333">
            <div class="customer-section">
                <label style="font-size:.8rem;color:#888;margin-bottom:4px;display:block">👤 Cliente:</label>
                <div id="selected-customer" style="display:none;padding:6px;background:#111;border-radius:4px;margin-bottom:5px;font-weight:bold;color:var(--primary)"></div>
                <input type="text" id="customer-search" placeholder="Buscar cliente por nome/telefone..." oninput="searchCustomer(this.value)">
                <div id="customer-results"></div>
                <div style="margin-top:5px">
                    <button type="button" onclick="toggleQuickForm()" style="background:none;border:none;color:#3498db;cursor:pointer;font-size:.8rem">+ Cadastro Rápido</button>
                </div>
                <div id="quick-customer-form" class="quick-form" style="display:none">
                    <input type="text" id="qc-name" placeholder="Nome do cliente">
                    <input type="text" id="qc-phone" placeholder="Telefone/WhatsApp">
                    <button onclick="quickCreateCustomer()" style="background:#2ecc71;color:#000;border:none;padding:8px;border-radius:4px;width:100%;font-weight:bold;cursor:pointer">Cadastrar</button>
                </div>
            </div>
        </div>

        <div class="cart-items" id="cart-items"></div>

        <div class="cart-footer">
            <div style="display:flex;justify-content:space-between;font-size:.9rem;color:#888;margin-bottom:4px">
                <span>Subtotal:</span><span id="cart-subtotal">R$ 0,00</span>
            </div>
            <div class="discount-row">
                <span style="font-size:.85rem;color:#888">Desconto:</span>
                <span style="color:#888">R$</span>
                <input type="number" id="discount-input" value="0" min="0" step="0.01" oninput="renderCart()">
            </div>
            <div class="discount-row">
                <span style="font-size:.85rem;color:#888">Frete:</span>
                <span style="color:#888">R$</span>
                <input type="number" id="shipping-input-val" value="0" min="0" step="0.01" oninput="renderCart()">
            </div>
            <div class="total-row">
                <span>TOTAL:</span><span id="cart-total">R$ 0,00</span>
            </div>

            <p style="font-size:.75rem;color:#888;margin-bottom:6px">Status do Pedido:</p>
            <div class="status-toggle">
                <div class="status-btn" id="st-paid" onclick="setStatus('paid')">✅ Pago</div>
                <div class="status-btn active-pending" id="st-pending" onclick="setStatus('pending')">⏳ Pendente</div>
            </div>

            <p style="font-size:.75rem;color:#888;margin-bottom:6px">Pagamento:</p>
            <div class="payment-methods">
                <div class="pay-btn active" onclick="setPayment('Dinheiro',this)"><i class="fas fa-money-bill-wave"></i>Dinheiro <span class="hotkey-badge">F5</span></div>
                <div class="pay-btn" onclick="setPayment('PIX',this)"><i class="fas fa-qrcode"></i>PIX <span class="hotkey-badge">F3</span></div>
                <div class="pay-btn" onclick="setPayment('Cartão',this)"><i class="fas fa-credit-card"></i>Cartão <span class="hotkey-badge">F4</span></div>
                <div class="pay-btn" onclick="setPayment('Link',this)"><i class="fas fa-link"></i>Link</div>
            </div>

            <form method="POST" id="checkout-form">
                <input type="hidden" name="checkout" value="1">
                <input type="hidden" name="cart_json" id="cart-json">
                <input type="hidden" name="subtotal" id="subtotal-input">
                <input type="hidden" name="discount" id="discount-hidden">
                <input type="hidden" name="shipping_cost" id="shipping-input">
                <input type="hidden" name="total" id="total-input">
                <input type="hidden" name="payment_method" id="payment-input" value="Dinheiro">
                <input type="hidden" name="order_status" id="status-input" value="pending">
                <input type="hidden" name="customer_id" id="customer-id" value="0">
                <button type="submit" class="btn-checkout" id="btn-finish" disabled>FINALIZAR VENDA (F8)</button>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div id="posSuccessOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);z-index:99999;display:flex;align-items:center;justify-content:center">
        <div style="background:#1a1e26;padding:2.5rem;border-radius:16px;text-align:center;border:2px solid #2ecc71;max-width:450px;width:90%;position:relative;">
            <a href="pos.php" style="position:absolute;top:15px;right:15px;color:#888;text-decoration:none;font-size:1.5rem;line-height:1;"><i class="fas fa-times"></i></a>
            <i class="fas fa-check-square" style="font-size:4.5rem;color:#2ecc71;margin-bottom:1rem;background:#111;padding:10px;border-radius:12px;"></i>
            <h2 style="margin-bottom:0.5rem;color:#ecf0f1;">Venda Finalizada!</h2>
            <div style="background:#222;padding:8px;border-radius:6px;margin-bottom:1.5rem;color:#aaa;">Pedido <strong style="color:#fff;">#<?php echo $_GET['sale_id'] ?? ''; ?></strong> criado com sucesso.</div>
            
            <div style="display:flex;flex-direction:column;gap:10px;">
                <button onclick="window._mePosOrderId=<?php echo $_GET['sale_id'] ?? 0; ?>; openMEQuote(<?php echo $_GET['sale_id'] ?? 0; ?>)" style="background:#e74c3c;color:#fff;padding:12px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:1rem;width:100%"><i class="fas fa-box-open"></i> Cotar Frete e Gerar Etiqueta</button>
                <button onclick="window.print()" style="background:#3498db;color:#fff;padding:12px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:1rem;width:100%"><i class="fas fa-print"></i> Imprimir Comprovante</button>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <a href="orders.php" style="background:#333;color:#fff;padding:10px;text-decoration:none;border-radius:8px;font-size:0.9rem"><i class="fas fa-list"></i> Ver Pedidos</a>
                    <a href="../index.php" target="_blank" style="background:#333;color:#fff;padding:10px;text-decoration:none;border-radius:8px;font-size:0.9rem"><i class="fas fa-globe"></i> Ver Site</a>
                </div>

                <a href="pos.php" style="background:var(--primary);color:#000;padding:14px;text-decoration:none;border-radius:8px;font-weight:bold;font-size:1.1rem;margin-top:5px;display:block;"><i class="fas fa-plus"></i> NOVA VENDA</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div style="position:fixed;top:20px;right:20px;background:#e74c3c;color:#fff;padding:15px 20px;border-radius:8px;z-index:99999"><?php echo $error; ?></div>
    <?php endif; ?>

    <script>
    let cart = [];
    let selectedPayment = 'Dinheiro';
    let selectedStatus = 'pending';
    let selectedCustomerId = 0;
    const searchInput = document.getElementById('search-input');
    const resultsGrid = document.getElementById('results');
    const initialProducts = <?php echo json_encode($initialProducts); ?>;

    document.addEventListener('DOMContentLoaded', () => renderProducts(initialProducts));

    function renderProducts(list) {
        resultsGrid.innerHTML = '';
        if (!list.length) { resultsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:2rem;color:#666">Nenhum produto encontrado</div>'; return; }
        list.forEach(item => {
            const div = document.createElement('div');
            div.className = 'product-item';
            const imgSrc = item.image_path ? (item.image_path.startsWith('http') ? item.image_path : '../assets/uploads/' + item.image_path) : '../assets/no-img.png';
            div.innerHTML = `<div style="position:relative"><img src="${imgSrc}"><div style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,.7);color:#fff;padding:2px 5px;border-radius:4px;font-size:.65rem">Qt:${item.stock_qty}</div></div><div class="p-name">${item.name}</div><div class="p-price">R$ ${parseFloat(item.price).toLocaleString('pt-BR',{minimumFractionDigits:2})}</div>`;
            div.onclick = () => addToCart(item);
            resultsGrid.appendChild(div);
        });
    }

    let searchTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        const q = this.value;
        if (!q.length) { renderProducts(initialProducts); return; }
        if (q.length < 2) return;
        searchTimer = setTimeout(() => fetch(`pos.php?search=${q}`).then(r=>r.json()).then(data=>renderProducts(data)), 300);
    });

    function addToCart(item) {
        const existing = cart.find(i => i.vid == item.var_id && i.pid == item.id);
        if (existing) { existing.qty++; } else {
            cart.push({pid:item.id, vid:item.var_id, name:item.name, price:parseFloat(item.price), qty:1, image:item.image_path});
        }
        renderCart(); searchInput.value = ''; searchInput.focus();
    }

    function renderCart() {
        const container = document.getElementById('cart-items');
        container.innerHTML = '';
        cart.forEach((item, i) => {
            const imgSrc = item.image ? (item.image.startsWith('http') ? item.image : '../assets/uploads/' + item.image) : '../assets/no-img.png';
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <img src="${imgSrc}">
                <div class="cart-item-info" style="flex:1;">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price" style="display:flex; align-items:center; gap:5px; margin-top:5px;">
                        <span style="color:#888; font-size:0.8rem;">R$</span> 
                        <input type="number" step="0.01" value="${item.price}" oninput="setPrice(${i}, this.value)" style="width:80px; background:transparent; border:1px dashed var(--primary); color:var(--primary); font-weight:bold; padding:2px 5px; border-radius:4px; font-size:1rem;">
                    </div>
                </div>
                <div class="cart-qty-ctrl" style="display:flex; align-items:center; gap:5px;">
                    <button onclick="changeQty(${i},-1)">-</button>
                    <input type="number" value="${item.qty}" min="1" oninput="setQty(${i}, this.value)" style="width:45px; text-align:center; background:#111; color:#fff; border:1px solid #444; border-radius:4px; padding:4px;">
                    <button onclick="changeQty(${i},1)">+</button>
                </div>
                <button onclick="toggleGift(${i})" style="background:${item.is_gift ? '#f39c12' : 'none'}; border:none; color:${item.is_gift ? '#000' : '#888'}; cursor:pointer; margin-left:10px; font-size:1.1rem; border-radius:4px; padding:2px 5px;" title="Marcar/Desmarcar Brinde">🎁</button>
                <button onclick="changeQty(${i}, -9999)" style="background:none; border:none; color:#e74c3c; cursor:pointer; margin-left:10px; font-size:1.1rem;"><i class="fas fa-trash"></i></button>
            `;
            container.appendChild(div);
        });
        updateTotals();
    }

    function updateTotals() {
        let subtotal = 0;
        cart.forEach(item => { subtotal += item.price * item.qty; });
        const discount = parseFloat(document.getElementById('discount-input').value) || 0;
        const shipping = parseFloat(document.getElementById('shipping-input-val').value) || 0;
        const total = Math.max(0, subtotal - discount) + shipping;
        document.getElementById('cart-subtotal').innerText = `R$ ${subtotal.toLocaleString('pt-BR',{minimumFractionDigits:2})}`;
        document.getElementById('cart-total').innerText = `R$ ${total.toLocaleString('pt-BR',{minimumFractionDigits:2})}`;
        document.getElementById('subtotal-input').value = subtotal;
        document.getElementById('discount-hidden').value = discount;
        document.getElementById('shipping-input').value = shipping;
        document.getElementById('total-input').value = total;
        document.getElementById('cart-json').value = JSON.stringify(cart);
        document.getElementById('btn-finish').disabled = cart.length === 0 || selectedCustomerId === 0;
    }

    function changeQty(i, d) { cart[i].qty += d; if (cart[i].qty <= 0) cart.splice(i, 1); renderCart(); }
    
    function toggleGift(i) {
        if (!cart[i].is_gift) {
            cart[i].is_gift = true;
            cart[i].old_price = cart[i].price;
            cart[i].price = 0;
            if (!cart[i].name.includes('[BRINDE]')) cart[i].name = '[BRINDE] ' + cart[i].name;
        } else {
            cart[i].is_gift = false;
            cart[i].price = cart[i].old_price || 0;
            cart[i].name = cart[i].name.replace('[BRINDE] ', '');
        }
        renderCart();
    }
    
    function setQty(i, val) {
        let q = parseInt(val);
        if(isNaN(q) || q <= 0) { 
            cart.splice(i, 1); 
            renderCart(); 
        } else { 
            cart[i].qty = q; 
            updateTotals(); 
        }
    }
    
    function setPrice(i, val) {
        let p = parseFloat(val);
        if(isNaN(p) || p < 0) p = 0;
        cart[i].price = p;
        updateTotals();
    }
    function clearCart() { if (confirm('Limpar carrinho?')) { cart = []; renderCart(); } }
    function setPayment(m, btn) { selectedPayment = m; document.getElementById('payment-input').value = m; document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); }

    function setStatus(s) {
        selectedStatus = s;
        document.getElementById('status-input').value = s;
        document.getElementById('st-paid').className = 'status-btn' + (s==='paid' ? ' active-paid' : '');
        document.getElementById('st-pending').className = 'status-btn' + (s==='pending' ? ' active-pending' : '');
    }

    // Customer Search
    let custTimer;
    function searchCustomer(q) {
        clearTimeout(custTimer);
        if (q.length < 2) { document.getElementById('customer-results').innerHTML = ''; return; }
        custTimer = setTimeout(() => {
            fetch(`pos.php?search_customer=${q}`).then(r=>r.json()).then(list => {
                const el = document.getElementById('customer-results');
                el.innerHTML = '';
                list.forEach(c => {
                    const d = document.createElement('div');
                    d.className = 'customer-result';
                    d.innerHTML = `<strong>${c.name}</strong> <span style="color:#888;font-size:.8rem">${c.phone||''} ${c.document||''}</span>`;
                    d.onclick = () => selectCustomer(c.id, c.name);
                    el.appendChild(d);
                });
            });
        }, 300);
    }

    function selectCustomer(id, name) {
        selectedCustomerId = id;
        document.getElementById('customer-id').value = id;
        document.getElementById('selected-customer').style.display = 'block';
        document.getElementById('selected-customer').innerHTML = '👤 ' + name + ' <span onclick="clearCustomer()" style="color:#e74c3c;cursor:pointer;margin-left:10px">✕</span>';
        document.getElementById('customer-search').style.display = 'none';
        document.getElementById('customer-results').innerHTML = '';
        renderCart();
    }

    function clearCustomer() {
        selectedCustomerId = 0;
        document.getElementById('customer-id').value = 0;
        document.getElementById('selected-customer').style.display = 'none';
        document.getElementById('customer-search').style.display = 'block';
        document.getElementById('customer-search').value = '';
        renderCart();
    }

    function toggleQuickForm() {
        const f = document.getElementById('quick-customer-form');
        f.style.display = f.style.display === 'none' ? 'block' : 'none';
    }

    function quickCreateCustomer() {
        const name = document.getElementById('qc-name').value;
        const phone = document.getElementById('qc-phone').value;
        if (!name) { alert('Nome é obrigatório'); return; }
        const fd = new FormData();
        fd.append('quick_customer', '1');
        fd.append('cname', name);
        fd.append('cphone', phone);
        fetch('pos.php', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
            if (res.success) {
                selectCustomer(res.id, res.name);
                document.getElementById('quick-customer-form').style.display = 'none';
                document.getElementById('qc-name').value = '';
                document.getElementById('qc-phone').value = '';
            } else { alert('Erro: ' + res.error); }
        });
    }

    // Hotkeys
    window.addEventListener('keydown', e => {
        if (e.key === 'F8') { e.preventDefault(); if (cart.length > 0 && selectedCustomerId > 0) document.getElementById('checkout-form').submit(); }
        if (e.key === 'F2') { e.preventDefault(); searchInput.focus(); }
        if (e.key === 'F3') { e.preventDefault(); document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active')); document.querySelectorAll('.pay-btn')[1].classList.add('active'); setPayment('PIX', document.querySelectorAll('.pay-btn')[1]); }
        if (e.key === 'F4') { e.preventDefault(); document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active')); document.querySelectorAll('.pay-btn')[2].classList.add('active'); setPayment('Cartão', document.querySelectorAll('.pay-btn')[2]); }
        if (e.key === 'F5') { e.preventDefault(); document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active')); document.querySelectorAll('.pay-btn')[0].classList.add('active'); setPayment('Dinheiro', document.querySelectorAll('.pay-btn')[0]); }
        if (e.key === 'F9') { e.preventDefault(); clearCart(); }
        if (e.key === 'Escape') { closeLlmPosModal(); }
    });

    // --- MELHOR ENVIO INTEGRATION ---
    function openMEQuote(orderId, isRecalculate = false) {
        if(!isRecalculate) {
            document.getElementById('posSuccessOverlay').style.display = 'none';
            document.getElementById('mePosModal').style.display = 'flex';
        }
        document.getElementById('mePosLoading').style.display = 'block';
        document.getElementById('mePosResults').style.display = 'none';
        document.getElementById('mePosActions').style.display = 'none';
        document.getElementById('mePosFeedback').style.display = 'none';
        
        let extraParams = "";
        if(isRecalculate) {
            extraParams = `&box_w=${document.getElementById('me_box_w').value}&box_h=${document.getElementById('me_box_h').value}&box_l=${document.getElementById('me_box_l').value}&box_wt=${document.getElementById('me_box_wt').value}`;
        }
        
        fetch(`melhorenvio.php?ajax_quote=1&order_id=${orderId}${extraParams}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('mePosLoading').style.display = 'none';
                if (data.error) {
                    document.getElementById('mePosResults').innerHTML = `<div style="color:#e74c3c;padding:1rem">${data.error}</div>`;
                    document.getElementById('mePosResults').style.display = 'block';
                    return;
                }
                let quotesArray = [];
                if (Array.isArray(data.quotes)) {
                    quotesArray = data.quotes;
                } else if (data.quotes && typeof data.quotes === 'object') {
                    if (data.quotes.message || data.quotes.error) {
                        document.getElementById('mePosResults').innerHTML = `<div style="color:#e74c3c;padding:1rem;border:1px solid #e74c3c;border-radius:8px;background:rgba(231,76,60,0.1);"><strong>Erro do Melhor Envio:</strong><br>${data.quotes.message || data.quotes.error}<br><small>${JSON.stringify(data.quotes.errors || '')}</small></div>`;
                        document.getElementById('mePosResults').style.display = 'block';
                        return;
                    }
                    quotesArray = Object.values(data.quotes);
                }

                let html = '';
                let hasValid = false;
                quotesArray.forEach(q => {
                    if (!q.id || !q.name) return; // Prevent rendering garbage
                    if (q.error) {
                        html += `<div style="display:flex;align-items:center;gap:15px;padding:12px;background:#222;border-radius:6px;margin-bottom:6px;border:1px solid #333;opacity:0.5">
                            <div style="flex:1"><strong>${q.name}</strong><br><small style="color:#e74c3c">${q.error}</small></div>
                        </div>`;
                        return;
                    }
                    hasValid = true;
                    const price = parseFloat(q.custom_price || q.price);
                    const days = q.custom_delivery_time || q.delivery_time;
                    html += `<div onclick="this.parentElement.querySelectorAll('div').forEach(d=>d.style.borderColor='#333');this.style.borderColor='#2ecc71';window._mePosSvc=${q.id};document.getElementById('btnMePosBuy').disabled=false" style="display:flex;align-items:center;gap:15px;padding:12px;background:#222;border-radius:6px;margin-bottom:6px;cursor:pointer;border:2px solid #333">
                        <div style="flex:1"><strong>${q.name}</strong><br><small style="color:#888">${q.company?.name||''} - ${days} dias úteis</small></div>
                        <div style="font-weight:bold;color:#2ecc71;font-size:1.1rem">R$ ${price.toLocaleString('pt-BR',{minimumFractionDigits:2})}</div>
                    </div>`;
                });
                if (!hasValid && !html) html = '<div style="color:#e74c3c;padding:1rem">Nenhuma transportadora disponível para esta rota ou dimensões.</div>';
                document.getElementById('mePosResults').innerHTML = html;
                document.getElementById('mePosResults').style.display = 'block';
                document.getElementById('mePosActions').style.display = 'block';
            });
    }

    function buyMePos() {
        if (!window._mePosSvc) return;
        if (!confirm('Comprar frete e gerar etiqueta?')) return;
        const btn = document.getElementById('btnMePosBuy');
        btn.disabled = true; btn.textContent = 'Processando...';
        const fd = new FormData();
        fd.append('ajax_buy','1');
        fd.append('order_id', window._mePosOrderId);
        fd.append('service_id', window._mePosSvc);
        // Pass the box dimensions for final purchase
        fd.append('box_w', document.getElementById('me_box_w').value);
        fd.append('box_h', document.getElementById('me_box_h').value);
        fd.append('box_l', document.getElementById('me_box_l').value);
        fd.append('box_wt', document.getElementById('me_box_wt').value);
        fetch('melhorenvio.php',{method:'POST',body:fd})
            .then(r=>r.json())
            .then(data=>{
                const fb = document.getElementById('mePosFeedback');
                fb.style.display = 'block';
                if(data.success){
                    let btnPrint = '';
                    if (data.paid) {
                        btnPrint = `<button onclick="fetch('melhorenvio.php?print_label=${data.me_order_id}').then(r=>r.json()).then(d=>{if(d.url)window.open(d.url,'_blank');else alert('Processando...')})" class="btn" style="margin-top:10px;background:#f1c40f;color:#000">🏷️ Imprimir Etiqueta</button>`;
                    } else {
                        btnPrint = `<div style="margin-top:10px; font-size:0.85rem; color:#f1c40f;">
                            ⚠️ <strong>Saldo Insuficiente.</strong><br>
                            O frete foi enviado para o seu <strong>CARRINHO</strong> no Melhor Envio. 
                            Pague por lá e depois sincronize o pedido na lista de pedidos.
                            <br><a href="https://melhorenvio.com.br/carrinho" target="_blank" style="color:#fff; text-decoration:underline;">Ir para o Carrinho</a>
                        </div>`;
                    }
                    fb.innerHTML = `<div style="background:rgba(46,204,113,.1);border:1px solid #2ecc71;padding:15px;border-radius:8px;color:#2ecc71">
                        ✅ ${data.message}<br>
                        ${btnPrint}
                        <button onclick="location.href='pos.php'" class="btn" style="margin-top:10px;background:#333">Nova Venda</button>
                    </div>`;
                } else {
                    fb.innerHTML = `<div style="color:#e74c3c;padding:10px">❌ Erro: ${JSON.stringify(data.error)}</div>`;
                    btn.disabled = false; btn.textContent = '💳 Comprar Frete';
                }
            });
    }

    // --- LALAMOVE POS INTEGRATION ---
    function openLalamovePosQuote(orderId, isRecalculate = false) {
        window._llmPosOrderId = orderId;
        if (!isRecalculate) {
            document.getElementById('posSuccessOverlay').style.display = 'none';
            document.getElementById('llmPosModal').style.display = 'flex';
        }
        document.getElementById('llmPosLoading').style.display = 'block';
        document.getElementById('llmPosResults').style.display = 'none';
        document.getElementById('llmPosActions').style.display = 'none';
        document.getElementById('llmPosFeedback').style.display = 'none';
        document.getElementById('btnLlmPosBuy').disabled = true;

        let geoUrl = `lalamove.php?ajax_geocode=1&order_id=${orderId}`;
        const manualAddr = document.getElementById('llm_manual_address')?.value;
        if (isRecalculate && manualAddr) {
            geoUrl = `lalamove.php?ajax_geocode=1&address=${encodeURIComponent(manualAddr)}`;
        }

        fetch(geoUrl)
            .then(r => r.json())
            .then(d => {
                if (d.error) throw new Error(d.error);
                return fetch(`lalamove.php?ajax_quote=1&lat=${d.lat}&lng=${d.lng}&address=${encodeURIComponent(d.formatted_address)}`);
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('llmPosLoading').style.display = 'none';
                if (data.error) {
                    document.getElementById('llmPosResults').innerHTML = `<div style="color:#e74c3c;padding:1rem">${data.error}</div>`;
                    document.getElementById('llmPosResults').style.display = 'block';
                    return;
                }
                let html = '';
                if (data.sandbox) html += `<div style="background:rgba(255,102,0,.1);border:1px solid #FF6600;padding:8px 12px;border-radius:6px;margin-bottom:10px;font-size:.8rem;color:#FF6600"><i class="fas fa-flask"></i> Sandbox — valores simulados</div>`;
                window.llmPosQuotes = data.quotes || [];
                window.llmPosQuotes.forEach((q, i) => {
                    const hasErr = !!q.error;
                    const icon = {LALAGO:'🏍️', HATCHBACK:'🚗', CAR:'🚙', VAN:'🚐', UV_FIORINO:'🚐', TRUCK330:'🚛', TRUCK3_5T:'🚛'}[q.serviceType] || '📦';
                    const price = hasErr ? 'N/D' : 'R$ ' + parseFloat(q.total||0).toLocaleString('pt-BR',{minimumFractionDigits:2});
                    html += `<div onclick="${!hasErr ? `selectLlmPosQuote(this,${i})` : ''}" style="display:flex;align-items:center;gap:15px;padding:12px;background:#222;border-radius:8px;margin-bottom:6px;cursor:${hasErr?'not-allowed':'pointer'};border:2px solid #333;opacity:${hasErr?'.5':'1'};transition:.2s">
                        <div style="font-size:1.5rem">${icon}</div>
                        <div style="flex:1"><strong>${q.label}</strong><br><small style="color:${hasErr?'#e74c3c':'#888'}">${hasErr ? q.error : 'Express - Hoje'}</small></div>
                        <div style="font-weight:bold;color:${hasErr?'#666':'#FF6600'};font-size:1.1rem">${price}</div>
                    </div>`;
                });
                if (!html || window.llmPosQuotes.every(q => q.error)) {
                    html += `<div style="color:#e74c3c;padding:1rem">Nenhuma opção disponível. Lalamove atende SP e RJ.</div>`;
                }
                document.getElementById('llmPosResults').innerHTML = html;
                document.getElementById('llmPosResults').style.display = 'block';
                document.getElementById('llmPosActions').style.display = 'block';
            })
            .catch(err => {
                document.getElementById('llmPosLoading').style.display = 'none';
                document.getElementById('llmPosResults').innerHTML = `<div style="color:#e74c3c;padding:1rem">Erro: ${err.message}</div>`;
                document.getElementById('llmPosResults').style.display = 'block';
            });
    }

    function selectLlmPosQuote(el, idx) {
        document.querySelectorAll('#llmPosResults > div').forEach(d => d.style.borderColor = '#333');
        el.style.borderColor = '#FF6600';
        window._llmPosSelected = window.llmPosQuotes[idx];
        document.getElementById('btnLlmPosBuy').disabled = false;
    }

    function closeLlmPosModal() { document.getElementById('llmPosModal').style.display = 'none'; }

    function buyLlmPos() {
        if (!window._llmPosSelected) return;
        if (!confirm('Confirmar entrega expressa Lalamove?')) return;
        const btn = document.getElementById('btnLlmPosBuy');
        btn.disabled = true; btn.textContent = 'Chamando motoboy...';
        const fd = new FormData();
        fd.append('ajax_create_order', '1');
        fd.append('quotation_id', window._llmPosSelected.quotationId);
        fd.append('stops_json', JSON.stringify(window._llmPosSelected.stops || []));
        const phone = document.querySelector(`.customer-result[onclick*="selectCustomer(${selectedCustomerId}"]`)?.innerText.match(/\d+/g)?.join('') || '';
        
        fd.append('recipient_name', 'Cliente PDV');
        fd.append('recipient_phone', phone);
        fd.append('local_order_id', window._llmPosOrderId);
        fetch('lalamove.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(data => {
                const fb = document.getElementById('llmPosFeedback');
                fb.style.display = 'block';
                if (data.success) {
                    fb.innerHTML = `<div style="background:rgba(255,102,0,.1);border:1px solid #FF6600;padding:15px;border-radius:8px;color:#FF6600;text-align:center">
                        ✅ Motoboy chamado!<br>ID: <strong>${data.orderId}</strong><br>
                        <a href="pos.php" style="display:inline-block;margin-top:10px;background:#222;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Nova Venda</a>
                    </div>`;
                } else {
                    fb.innerHTML = `<div style="color:#e74c3c;padding:10px">❌ Erro: ${data.error}</div>`;
                    btn.disabled = false; btn.textContent = '🏀️ Chamar Motoboy';
                }
            });
    }

    // --- CONFETTI EFFECT ---
    <?php if ($saleSuccess): ?>
    function launchConfetti() {
        const colors = ['#f1c40f','#2ecc71','#e74c3c','#3498db','#9b59b6','#FF6600'];
        for (let i = 0; i < 60; i++) {
            const piece = document.createElement('div');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDuration = (2 + Math.random() * 2) + 's';
            piece.style.animationDelay = Math.random() * 0.5 + 's';
            piece.style.width = (6 + Math.random() * 8) + 'px';
            piece.style.height = (6 + Math.random() * 8) + 'px';
            piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
            document.body.appendChild(piece);
            setTimeout(() => piece.remove(), 4000);
        }
        // Sound
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator(); const gain = ctx.createGain();
            osc.type = 'sine'; osc.frequency.value = 800;
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.connect(gain); gain.connect(ctx.destination);
            osc.start(); osc.stop(ctx.currentTime + 0.3);
            setTimeout(() => {
                const osc2 = ctx.createOscillator(); const gain2 = ctx.createGain();
                osc2.type = 'sine'; osc2.frequency.value = 1200;
                gain2.gain.setValueAtTime(0.3, ctx.currentTime);
                gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
                osc2.connect(gain2); gain2.connect(ctx.destination);
                osc2.start(); osc2.stop(ctx.currentTime + 0.4);
            }, 150);
        } catch(e) {}
    }
    document.addEventListener('DOMContentLoaded', launchConfetti);
    <?php endif; ?>
    // --- UBER POS INTEGRATION ---
    function openUberPosQuote(orderId) {
        window._uberPosOrderId = orderId;
        document.getElementById('posSuccessOverlay').style.display = 'none';
        document.getElementById('uberPosModal').style.display = 'flex';
        document.getElementById('uberPosLoading').style.display = 'block';
        document.getElementById('uberPosResults').style.display = 'none';
        document.getElementById('uberPosActions').style.display = 'none';
        document.getElementById('uberPosFeedback').style.display = 'none';

        // Uber precisa de coordenadas. Vamos tentar pegar do Lalamove geocode primeiro ou ViaCEP
        fetch(`lalamove.php?ajax_geocode=1&order_id=${orderId}`)
            .then(r => r.json())
            .then(d => {
                if (d.error) throw new Error(d.error);
                return fetch(`uber_direct.php?ajax_quote=1&order_id=${orderId}&lat=${d.lat}&lng=${d.lng}`);
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('uberPosLoading').style.display = 'none';
                if (data.error) {
                    document.getElementById('uberPosResults').innerHTML = `<div style="color:#ff4444;padding:1rem">❌ ${data.error}</div>`;
                    document.getElementById('uberPosResults').style.display = 'block';
                    return;
                }
                
                let html = '';
                const q = data.quotes || [data]; // Dependendo de como retorna
                q.forEach((quote, i) => {
                    const price = quote.fee / 100; // Uber costuma vir em centavos
                    html += `<div onclick="selectUberPosQuote(this,'${quote.id}')" style="display:flex;align-items:center;gap:15px;padding:15px;background:#111;border-radius:10px;margin-bottom:10px;cursor:pointer;border:2px solid #222;transition:.2s">
                        <div style="font-size:1.5rem">🚗</div>
                        <div style="flex:1"><strong>Uber Flash / Direct</strong><br><small style="color:#888">Entrega Expressa</small></div>
                        <div style="font-weight:bold;color:#00ff88;font-size:1.2rem">R$ ${price.toLocaleString('pt-BR',{minimumFractionDigits:2})}</div>
                    </div>`;
                });
                
                document.getElementById('uberPosResults').innerHTML = html;
                document.getElementById('uberPosResults').style.display = 'block';
                document.getElementById('uberPosActions').style.display = 'block';
            })
            .catch(err => {
                document.getElementById('uberPosLoading').style.display = 'none';
                document.getElementById('uberPosResults').innerHTML = `<div style="color:#ff4444;padding:1rem">Erro: ${err.message}</div>`;
                document.getElementById('uberPosResults').style.display = 'block';
            });
    }

    function selectUberPosQuote(el, quoteId) {
        document.querySelectorAll('#uberPosResults > div').forEach(d => d.style.borderColor = '#222');
        el.style.borderColor = '#00ff88';
        window._uberPosQuoteId = quoteId;
        document.getElementById('btnUberPosBuy').disabled = false;
    }

    function closeUberPosModal() { document.getElementById('uberPosModal').style.display = 'none'; }

    function buyUberPos() {
        if (!window._uberPosQuoteId) return;
        const btn = document.getElementById('btnUberPosBuy');
        btn.disabled = true; btn.textContent = 'Processando...';
        
        const fd = new FormData();
        fd.append('ajax_create', '1');
        fd.append('quote_id', window._uberPosQuoteId);
        fd.append('order_id', window._uberPosOrderId);
        
        fetch('uber_direct.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(data => {
                const fb = document.getElementById('uberPosFeedback');
                fb.style.display = 'block';
                if (data.success) {
                    fb.innerHTML = `<div style="background:rgba(0,255,136,.1);border:1px solid #00ff88;padding:15px;border-radius:8px;color:#00ff88;text-align:center">
                        ✅ Entrega Uber solicitada!<br>ID: <strong>${data.id}</strong><br>
                        <a href="pos.php" style="display:inline-block;margin-top:10px;background:#222;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Nova Venda</a>
                    </div>`;
                } else {
                    fb.innerHTML = `<div style="color:#ff4444;padding:10px">❌ Erro: ${data.error}</div>`;
                    btn.disabled = false; btn.textContent = '🚗 Chamar Uber Direct';
                }
            });
    }
    </script>

    <!-- SUCCESS OVERLAY -->
    <?php if ($saleSuccess && $saleId > 0): ?>
    <div id="posSuccessOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:99999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px)">
        <div style="background:#1a1e26;border:2px solid #2ecc71;border-radius:16px;padding:3rem;text-align:center;max-width:450px;position:relative;box-shadow:0 20px 50px rgba(0,0,0,0.5)">
            <button onclick="document.getElementById('posSuccessOverlay').style.display='none'" style="position:absolute;top:15px;right:20px;background:none;border:none;color:#555;font-size:2rem;cursor:pointer;line-height:1">&times;</button>
            <div style="font-size:4rem;margin-bottom:1rem">✅</div>
            <h2 style="color:#2ecc71;margin-bottom:.5rem">Venda Finalizada!</h2>
            <p style="color:#888;margin-bottom:1.5rem">Pedido <strong style="color:#f1c40f">#<?php echo $saleId; ?></strong> criado com sucesso.</p>
            <div style="display:flex;flex-direction:column;gap:10px">
                <button onclick="window._mePosOrderId=<?php echo $saleId; ?>;openMEQuote(<?php echo $saleId; ?>)" style="background:#e74c3c;color:#fff;padding:14px;border:none;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:bold;transition:0.2s">
                    📦 Cotar Frete e Gerar Etiqueta
                </button>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <a href="order-print.php?id=<?php echo $saleId; ?>" target="_blank" style="background:#3498db;color:#fff;padding:12px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:0.9rem">
                        🖨️ Imprimir
                    </a>
                    <a href="orders.php" style="background:#2c3e50;color:#fff;padding:12px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:0.9rem">
                        📋 Ver Pedidos
                    </a>
                </div>
                <a href="pos.php" style="background:#222;color:#fff;padding:14px;border-radius:8px;text-decoration:none;font-weight:bold;border:1px solid #444">
                    Nova Venda
                </a>
                <button onclick="window._mePosOrderId=<?php echo $saleId; ?>;openLalamovePosQuote(<?php echo $saleId; ?>)" style="background:#FF6600;color:#fff;padding:14px;border:none;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:bold;transition:0.2s">
                    🏀️ Lalamove Express (Mesmo Dia)
                </button>
                <button onclick="window._mePosOrderId=<?php echo $saleId; ?>;openUberPosQuote(<?php echo $saleId; ?>)" style="background:#000;color:#00ff88;padding:14px;border:1px solid #00ff88;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:bold;transition:0.2s">
                    🚗 Uber Direct (Logística)
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ME QUOTE MODAL (POS) -->
    <div id="mePosModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);z-index:999999;align-items:center;justify-content:center">
        <div style="background:#1a1e26;border:2px solid #e74c3c;border-radius:16px;padding:2rem;max-width:550px;width:90%;max-height:80vh;overflow-y:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h2>📦 Escolha o Frete</h2>
                <button onclick="document.getElementById('mePosModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
            </div>
            
            <div style="background:#111; padding:10px; border-radius:8px; margin-bottom:1rem; border:1px solid #333; text-align:left;">
                <label style="color:#888; font-size:0.8rem; display:block; margin-bottom:5px;">📐 Forçar Dimensões da Caixa (opcional)</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <input type="number" id="me_box_l" placeholder="Comp(cm)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Comprimento (cm)">
                    <input type="number" id="me_box_w" placeholder="Larg(cm)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Largura (cm)">
                    <input type="number" id="me_box_h" placeholder="Alt(cm)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Altura (cm)">
                    <input type="number" id="me_box_wt" step="0.1" placeholder="Peso(kg)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Peso Total (kg)">
                    <button type="button" onclick="openMEQuote(window._mePosOrderId, true)" style="background:#f1c40f; color:#000; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; cursor:pointer;" title="Recalcular Frete">🔄</button>
                </div>
            </div>

            <div id="mePosLoading" style="text-align:center;padding:2rem;color:#888">Calculando fretes...</div>
            <div id="mePosResults" style="display:none"></div>
            <div id="mePosActions" style="display:none;margin-top:1rem;text-align:right">
                <button onclick="buyMePos()" class="btn" style="background:#2ecc71;color:#000;padding:12px 24px" id="btnMePosBuy" disabled>💳 Comprar Frete</button>
            </div>
            <div id="mePosFeedback" style="display:none;margin-top:1rem"></div>
        </div>
    </div>
    <!-- LALAMOVE QUOTE MODAL (POS) -->
    <div id="llmPosModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:999999;align-items:center;justify-content:center;backdrop-filter:blur(6px)">
        <div style="background:#1a1e26;border:2px solid #FF6600;border-radius:16px;padding:2rem;max-width:550px;width:90%;max-height:80vh;overflow-y:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h2 style="margin:0;display:flex;align-items:center;gap:8px">
                    <span style="background:#FF6600;color:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.9rem">L</span>
                    Lalamove Express
                </h2>
                <button onclick="closeLlmPosModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
            </div>
            <div style="background:#111; padding:10px; border-radius:8px; margin-bottom:1rem; border:1px solid #333; text-align:left;">
                <label style="color:#888; font-size:0.8rem; display:block; margin-bottom:5px;">📍 Informar/Corrigir Endereço ou CEP do Cliente</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="llm_manual_address" placeholder="Ex: CEP ou Endereço Completo" style="flex:1; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;">
                    <button type="button" onclick="openLalamovePosQuote(window._llmPosOrderId, true)" style="background:#FF6600; color:#fff; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; cursor:pointer;" title="Recalcular Lalamove">🔄 Buscar</button>
                </div>
            </div>
            
            <div id="llmPosLoading" style="text-align:center;padding:2rem;color:#FF6600">
                <div style="font-size:2rem;margin-bottom:.5rem">&#x1F6F5;</div>
                Buscando motoboys...
            </div>
            <div id="llmPosResults" style="display:none"></div>
            <div id="llmPosActions" style="display:none;margin-top:1rem;text-align:right">
                <button onclick="buyLlmPos()" style="background:#FF6600;color:#fff;padding:12px 24px;border:none;border-radius:8px;font-weight:bold;font-size:1rem;cursor:pointer" id="btnLlmPosBuy" disabled>&#x1F6F5; Chamar Motoboy</button>
            </div>
            <div id="llmPosFeedback" style="display:none;margin-top:1rem"></div>
        </div>
    </div>

    <!-- UBER QUOTE MODAL (POS) -->
    <div id="uberPosModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:999999;align-items:center;justify-content:center;backdrop-filter:blur(6px)">
        <div style="background:#000;border:2px solid #00ff88;border-radius:16px;padding:2rem;max-width:550px;width:90%;max-height:80vh;overflow-y:auto;color:#fff">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
                <h2 style="margin:0;display:flex;align-items:center;gap:12px">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Uber_logo_2018.svg/2560px-Uber_logo_2018.svg.png" style="height:20px;filter:invert(1)">
                    Direct
                </h2>
                <button onclick="closeUberPosModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
            </div>
            
            <div id="uberPosLoading" style="text-align:center;padding:2rem;color:#00ff88">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;margin-bottom:1rem"></i><br>
                Consultando Uber...
            </div>
            <div id="uberPosResults" style="display:none"></div>
            <div id="uberPosActions" style="display:none;margin-top:1.5rem;text-align:right">
                <button onclick="buyUberPos()" style="background:#00ff88;color:#000;padding:12px 24px;border:none;border-radius:8px;font-weight:bold;font-size:1rem;cursor:pointer" id="btnUberPosBuy" disabled>🚗 Chamar Uber Direct</button>
            </div>
            <div id="uberPosFeedback" style="display:none;margin-top:1rem"></div>
        </div>
    </div>

</body>
</html>