<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// AJAX: Quick Create Customer
if (isset($_GET['ajax_create_customer'])) {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $doc = trim($_POST['document'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'O nome é obrigatório.']);
        exit;
    }

    if (empty($email)) {
        $cleanDoc = preg_replace('/\D/', '', $doc);
        if (!empty($cleanDoc)) {
            $email = $cleanDoc . '@fightarcade.com.br';
        } else {
            $email = 'user_' . time() . '_' . rand(100, 999) . '@fightarcade.com.br';
        }
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este e-mail já está cadastrado.']);
            exit;
        }
    }

    $raw_pass = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    $password = password_hash($raw_pass, PASSWORD_DEFAULT);

    // Auto-migration for users columns
    $userCols = [
        'is_vip' => "TINYINT(1) DEFAULT 0",
        'is_lead' => "TINYINT(1) DEFAULT 0",
        'document' => "VARCHAR(50) DEFAULT ''",
        'phone' => "VARCHAR(50) DEFAULT ''"
    ];
    foreach ($userCols as $c => $def) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN $c $def"); } catch (Exception $e) {}
    }

    try {
        $sql = "INSERT INTO users (name, email, password, document, phone, role) 
                VALUES (:name, :email, :pass, :doc, :phone, 'customer')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':pass' => $password,
            ':doc' => $doc,
            ':phone' => $phone
        ]);
        $new_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $new_id, 'name' => $name, 'phone' => $phone, 'document' => $doc]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar cliente: ' . $e->getMessage()]);
    }
    exit;
}

$users = $pdo->query("SELECT id, name, document, phone FROM users WHERE role != 'admin' AND (is_lead = 0 OR is_lead IS NULL) ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, sku, price, image_path, stock_qty FROM products WHERE active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $user_id = (int) $_POST['user_id'];
    $shipping = $_POST['shipping_method'];
    $discount = (float) ($_POST['discount_amount'] ?? 0);
    $shipping_cost = (float) ($_POST['shipping_cost'] ?? 0);
    $cart_json = $_POST['cart_json'] ?? '[]';
    $items_raw = json_decode($cart_json, true) ?? [];
    $status = $_POST['order_status'] ?? 'pending';

    $items_to_add = [];
    $total = 0;

    foreach ($items_raw as $item) {
        $pid = (int) $item['pid'];
        $qty = (int) $item['qty'];
        $price = (float) $item['price'];

        if ($qty > 0) {
            $curr_p = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $curr_p->execute([$pid]);
            $product_data = $curr_p->fetch();
            
            if ($product_data) {
                $item_name = $product_data['name'];
                if (isset($item['is_gift']) && $item['is_gift']) {
                    $item_name = '[BRINDE] ' . $item_name;
                }

                $subtotal = $price * $qty;
                $cost_price = (float)($product_data['cost_price'] ?? 0);
                
                $items_to_add[] = [
                    'id' => $pid, 
                    'name' => $item_name, 
                    'price' => $price, 
                    'qty' => $qty, 
                    'subtotal' => $subtotal,
                    'cost_price' => $cost_price
                ];
                $total += $subtotal;
            }
        }
    }

    $final_total = max(0, $total - $discount) + $shipping_cost;

    if (count($items_to_add) > 0 && $user_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // 1. Insert Order
            try {
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, discount_amount, shipping_cost, status, shipping_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$user_id, $final_total, $discount, $shipping_cost, $status, $shipping]);
            } catch (PDOException $col_err) {
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_method, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$user_id, $final_total, $status, $shipping]);
            }
            $order_id = $pdo->lastInsertId();

            // 2. Insert Items
            $stmt_i = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal, cost_price) VALUES (?,?,?,?,?,?,?)");
            foreach ($items_to_add as $i) {
                $stmt_i->execute([$order_id, $i['id'], $i['name'], $i['qty'], $i['price'], $i['subtotal'], $i['cost_price']]);
                
                // 3. Update Stock
                $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$i['qty'], $i['id']]);
            }

            // 4. Financial Ledger Integration
            if ($user_id > 0) {
                if ($status === 'paid') {
                    $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, ?, ?)")
                        ->execute([$user_id, $final_total, 'Manual/Order', "Pagamento do Pedido #$order_id"]);
                }

                // Sync dynamic current_debt for the user
                $pdo->prepare("UPDATE users u SET current_debt = (
                    (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                    (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
                ) WHERE id = ?")->execute([$user_id]);
            }

            $pdo->commit();
            header("Location: orders.php?msg=created");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro: " . $e->getMessage();
        }
    } else {
        $error = "Selecione um cliente e adicione pelo menos 1 item.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Pedido Manual | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --primary: #f1c40f; --bg-card: #1a1e26; --border: #333; }
        body { background: #0b0e14; color: #fff; }
        .main-layout { display: flex; gap: 20px; height: calc(100vh - 120px); overflow: hidden; margin-top: 20px; }
        
        /* Products Section */
        .products-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); grid-auto-rows: max-content; gap: 12px; overflow-y: auto; padding: 10px; flex: 1; align-items: start; align-content: start; }
        .product-card { background: var(--bg-card); border: 2px solid var(--border); border-radius: 12px; padding: 10px; cursor: pointer; transition: 0.2s; position: relative; min-height: 230px; display: flex; flex-direction: column; }
        .product-card:hover { border-color: var(--primary); transform: translateY(-3px); }
        .product-card img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; flex-shrink: 0; }
        .product-card .stock-badge { position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.8); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; z-index: 2; }
        .product-card .p-name { font-weight: bold; font-size: 0.85rem; height: 2.4rem; overflow: hidden; margin-bottom: auto; }
        .product-card .p-price { color: var(--primary); font-weight: bold; font-size: 1rem; margin-top: 5px; }
        
        /* Cart Section */
        .cart-panel { width: 450px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0; }
        .cart-header { padding: 15px; background: #222; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .cart-items { flex: 1; overflow-y: auto; padding: 15px; }
        .cart-item { background: #222; border-radius: 8px; padding: 12px; margin-bottom: 10px; border-left: 4px solid var(--primary); }
        .cart-item-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .cart-item-name { font-weight: bold; font-size: 0.9rem; flex: 1; }
        
        .item-controls { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .qty-controls { display: flex; align-items: center; gap: 8px; }
        .qty-btn { background: #333; color: #fff; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .qty-btn:hover { background: var(--primary); color: #000; }
        .qty-input { width: 45px; text-align: center; background: #111; border: 1px solid #444; color: #fff; border-radius: 4px; padding: 4px; }
        
        .price-input-group { display: flex; align-items: center; gap: 5px; background: #111; padding: 5px 10px; border-radius: 6px; border: 1px dashed var(--primary); }
        .price-input-group span { color: #888; font-size: 0.8rem; }
        .price-input { background: transparent; border: none; color: var(--primary); font-weight: bold; width: 80px; outline: none; }

        .cart-footer { padding: 20px; background: #111; border-top: 2px solid var(--primary); }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; }
        .total-row { display: flex; justify-content: space-between; font-size: 1.4rem; font-weight: bold; margin-top: 10px; color: var(--primary); }

        #productSearch { width: 100%; padding: 12px 15px; background: var(--bg-card); border: 2px solid var(--border); color: #fff; border-radius: 10px; font-size: 1rem; margin-bottom: 15px; }
        #productSearch:focus { border-color: var(--primary); outline: none; }
        
        .btn-submit { background: var(--primary); color: #000; border: none; width: 100%; padding: 15px; border-radius: 10px; font-weight: bold; font-size: 1.1rem; cursor: pointer; margin-top: 15px; transition: 0.2s; }
        .btn-submit:hover { transform: scale(1.02); box-shadow: 0 0 20px rgba(241,196,15,0.3); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .gift-toggle { cursor: pointer; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; display: flex; align-items: center; gap: 4px; }
        .gift-active { background: #e67e22; color: #fff; }
        .gift-inactive { background: #333; color: #888; }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 900px) {
            .main-layout {
                flex-direction: column;
                height: auto;
                overflow: visible;
            }
            .products-section {
                max-height: 50vh;
                overflow: hidden;
            }
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 8px;
            }
            .product-card img { height: 90px; }
            .product-card .p-name { font-size: 0.75rem; height: 2rem; }
            .product-card .p-price { font-size: 0.85rem; }
            .cart-panel {
                width: 100%;
                max-height: none;
                border-radius: 12px 12px 0 0;
            }
            .cart-items {
                max-height: 300px;
            }
            .btn-submit {
                position: sticky;
                bottom: 0;
                z-index: 100;
                border-radius: 0;
                padding: 18px;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 600px) {
            .container { padding: 10px !important; }
            .container > div:first-child { flex-direction: column; gap: 10px; }
            .container > div:first-child h1 { font-size: 1.1rem; }
            div[style*="grid-template-columns:1fr 1fr 1fr"] {
                display: flex !important;
                flex-direction: column !important;
            }
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .product-card { padding: 6px; }
            .product-card img { height: 70px; }
            .product-card .p-name { font-size: 0.7rem; height: 1.8rem; }
            .item-controls { flex-direction: column; align-items: stretch; gap: 8px; }
            .qty-controls { justify-content: center; }
            .price-input-group { justify-content: center; }
        }

        /* Modal Style for Quick Customer */
        .modal-quick-customer { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); align-items: center; justify-content: center; }
        .modal-qc-content { background: #1a1e2a; border: 1px solid rgba(241,196,15,0.4); border-radius: 16px; padding: 2rem; max-width: 450px; width: 90%; box-shadow: 0 15px 40px rgba(0,0,0,0.5); animation: slideDown 0.3s ease-out; }
        .modal-qc-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .modal-qc-title { font-size: 1.2rem; font-weight: bold; color: var(--primary); }
        .modal-qc-close { background: none; border: none; color: #888; font-size: 1.5rem; cursor: pointer; transition: 0.2s; }
        .modal-qc-close:hover { color: #fff; }
        .qc-form-group { margin-bottom: 12px; }
        .qc-form-group label { display: block; margin-bottom: 4px; font-size: 0.8rem; color: #aaa; text-align: left; }
        .qc-input { width: 100%; padding: 10px; background: #111; border: 1px solid #444; color: #fff; border-radius: 6px; outline: none; box-sizing: border-box; }
        .qc-input:focus { border-color: var(--primary); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container" style="max-width: 1400px; padding: 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h1>Criar Pedido Manual</h1>
            <a href="orders.php" class="btn btn-secondary">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
        <div style="background:var(--danger);color:white;padding:1rem;margin-bottom:1rem;border-radius:8px"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="orderForm">
            <input type="hidden" name="create_order" value="1">
            <input type="hidden" name="cart_json" id="cart_json">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:20px;background:var(--bg-card);padding:20px;border-radius:12px;border:1px solid var(--border)">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="font-size:0.85rem;color:#888; margin:0;">👤 Cliente</label>
                        <a href="javascript:void(0)" onclick="openNewCustomerModal()" style="font-size:0.75rem; color:var(--primary); text-decoration:none; font-weight:bold;"><i class="fas fa-plus-circle"></i> + Novo Cliente</a>
                    </div>
                    <select name="user_id" required style="width:100%;padding:10px;background:#111;color:#fff;border:1px solid #444;border-radius:6px">
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?> (<?php echo $u['document'] ?: $u['phone']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;font-size:0.85rem;color:#888">🚚 Método de Envio</label>
                    <select name="shipping_method" style="width:100%;padding:10px;background:#111;color:#fff;border:1px solid #444;border-radius:6px">
                        <option value="A Combinar">A Combinar</option>
                        <option value="Retirada">Retirada</option>
                        <option value="Correios">Correios</option>
                        <option value="Transportadora">Transportadora</option>
                        <option value="Motoboy">Motoboy</option>
                        <option value="Melhor Envio">Melhor Envio</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;font-size:0.85rem;color:#888">📋 Status Inicial</label>
                    <select name="order_status" style="width:100%;padding:10px;background:#111;color:#fff;border:1px solid #444;border-radius:6px">
                        <option value="pending">⏳ Pendente</option>
                        <option value="paid">✅ Pago</option>
                        <option value="shipped">🚚 Enviado</option>
                    </select>
                </div>
            </div>

            <div class="main-layout">
                <!-- Left: Product Grid -->
                <div class="products-section">
                    <div style="position:relative">
                        <input type="text" id="productSearch" placeholder="🔍 Pesquisar por nome ou SKU..." onkeyup="filterProducts()">
                    </div>
                    <div class="product-grid" id="productGrid">
                        <?php foreach ($products as $p): ?>
                        <div class="product-card" onclick="addToCart(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                            <span class="stock-badge">Stock: <?php echo $p['stock_qty']; ?></span>
                            <?php if (!empty($p['image_path'])): ?>
                            <img src="../assets/uploads/<?php echo $p['image_path']; ?>">
                            <?php else: ?>
                            <div style="width:100%;height:120px;background:#333;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#666">Sem Foto</div>
                            <?php endif; ?>
                            <div class="p-name"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="p-price">R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></div>
                            <small style="color:#666"><?php echo $p['sku']; ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Cart Panel -->
                <div class="cart-panel">
                    <div class="cart-header">
                        <h3><i class="fas fa-shopping-basket"></i> Itens do Pedido</h3>
                        <span id="itemCount" style="background:var(--primary);color:#000;padding:2px 8px;border-radius:10px;font-size:0.8rem;font-weight:bold">0</span>
                    </div>

                    <div class="cart-items" id="cartItems">
                        <!-- Items populated by JS -->
                        <div style="text-align:center;color:#666;padding:2rem;">Nenhum item adicionado</div>
                    </div>

                    <div class="cart-footer">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="subtotalLabel">R$ 0,00</span>
                        </div>
                        <div class="summary-row" style="align-items:center">
                            <span>Desconto</span>
                            <div style="display:flex;align-items:center;gap:5px">
                                <span>R$</span>
                                <input type="number" name="discount_amount" id="discount_amount" value="0" min="0" step="0.01" oninput="updateTotals()" style="width:90px;padding:6px;background:#222;border:1px solid #444;color:#fff;border-radius:4px;text-align:right">
                            </div>
                        </div>
                        <div class="summary-row" style="align-items:center">
                            <span>Frete</span>
                            <div style="display:flex;align-items:center;gap:5px">
                                <span>R$</span>
                                <input type="number" name="shipping_cost" id="shipping_cost" value="0" min="0" step="0.01" oninput="updateTotals()" style="width:90px;padding:6px;background:#222;border:1px solid #444;color:#fff;border-radius:4px;text-align:right">
                            </div>
                        </div>
                        <div class="total-row">
                            <span>TOTAL</span>
                            <span id="totalLabel">R$ 0,00</span>
                        </div>

                        <button type="submit" class="btn-submit" id="btnSubmit" disabled>CRIAR PEDIDO</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
    let cart = [];

    function addToCart(product) {
        const existing = cart.find(i => i.pid === product.id);
        if (existing) {
            existing.qty++;
        } else {
            cart.push({
                pid: product.id,
                name: product.name,
                price: parseFloat(product.price),
                qty: 1,
                image: product.image_path,
                is_gift: false
            });
        }
        renderCart();
    }

    function changeQty(pid, delta) {
        const item = cart.find(i => i.pid === pid);
        if (item) {
            item.qty += delta;
            if (item.qty <= 0) {
                cart = cart.filter(i => i.pid !== pid);
            }
            renderCart();
        }
    }

    function setQty(pid, val) {
        const item = cart.find(i => i.pid === pid);
        if (item) {
            item.qty = parseInt(val) || 0;
            if (item.qty <= 0) cart = cart.filter(i => i.pid !== pid);
            renderCart();
        }
    }

    function setPrice(pid, val) {
        const item = cart.find(i => i.pid === pid);
        if (item) {
            item.price = parseFloat(val) || 0;
            updateTotals();
        }
    }

    function toggleGift(pid) {
        const item = cart.find(i => i.pid === pid);
        if (item) {
            item.is_gift = !item.is_gift;
            if (item.is_gift) {
                item.old_price = item.price;
                item.price = 0;
            } else {
                item.price = item.old_price || 0;
            }
            renderCart();
        }
    }

    function removeItem(pid) {
        cart = cart.filter(i => i.pid !== pid);
        renderCart();
    }

    function renderCart() {
        const container = document.getElementById('cartItems');
        const itemCount = document.getElementById('itemCount');
        
        if (cart.length === 0) {
            container.innerHTML = '<div style="text-align:center;color:#666;padding:2rem;">Nenhum item adicionado</div>';
            itemCount.innerText = '0';
            updateTotals();
            return;
        }

        itemCount.innerText = cart.length;
        container.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-header">
                    <span class="cart-item-name">${item.is_gift ? '<span style="color:#e67e22">🎁 [BRINDE]</span> ' : ''}${item.name}</span>
                    <button type="button" onclick="removeItem(${item.pid})" style="background:none;border:none;color:#e74c3c;cursor:pointer"><i class="fas fa-trash"></i></button>
                </div>
                <div class="item-controls">
                    <div class="qty-controls">
                        <button type="button" class="qty-btn" onclick="changeQty(${item.pid}, -1)">-</button>
                        <input type="number" class="qty-input" value="${item.qty}" min="1" onchange="setQty(${item.pid}, this.value)">
                        <button type="button" class="qty-btn" onclick="changeQty(${item.pid}, 1)">+</button>
                    </div>
                    
                    <div class="price-input-group">
                        <span>R$</span>
                        <input type="number" step="0.01" class="price-input" value="${item.price.toFixed(2)}" oninput="setPrice(${item.pid}, this.value)">
                    </div>

                    <div class="gift-toggle ${item.is_gift ? 'gift-active' : 'gift-inactive'}" onclick="toggleGift(${item.pid})">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
            </div>
        `).join('');

        updateTotals();
    }

    function updateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.price * item.qty;
        });

        const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
        const shipping = parseFloat(document.getElementById('shipping_cost').value) || 0;
        const total = Math.max(0, subtotal - discount) + shipping;

        document.getElementById('subtotalLabel').innerText = 'R$ ' + subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('totalLabel').innerText = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        
        document.getElementById('cart_json').value = JSON.stringify(cart);
        document.getElementById('btnSubmit').disabled = cart.length === 0;
    }

    function filterProducts() {
        const val = document.getElementById('productSearch').value.toLowerCase();
        document.querySelectorAll('.product-card').forEach(card => {
            const name = card.querySelector('.p-name').innerText.toLowerCase();
            const sku = card.querySelector('small').innerText.toLowerCase();
            if (name.includes(val) || sku.includes(val)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Hotkeys
    window.addEventListener('keydown', e => {
        if (e.key === 'F2') {
            e.preventDefault();
            document.getElementById('productSearch').focus();
        }
    });

    function openNewCustomerModal() {
        document.getElementById('qc_name').value = '';
        document.getElementById('qc_phone').value = '';
        document.getElementById('qc_email').value = '';
        document.getElementById('qc_doc').value = '';
        document.getElementById('qcError').style.display = 'none';
        document.getElementById('quickCustomerModal').style.display = 'flex';
    }

    function closeNewCustomerModal() {
        document.getElementById('quickCustomerModal').style.display = 'none';
    }

    function saveQuickCustomer() {
        const name = document.getElementById('qc_name').value.trim();
        const phone = document.getElementById('qc_phone').value.trim();
        const email = document.getElementById('qc_email').value.trim();
        const doc = document.getElementById('qc_doc').value.trim();
        const errEl = document.getElementById('qcError');

        if (!name) {
            errEl.innerText = '⚠️ O nome é obrigatório.';
            errEl.style.display = 'block';
            return;
        }

        const btn = document.getElementById('btnSaveQuickCustomer');
        btn.disabled = true;
        btn.innerText = '⌛ Salvando...';
        errEl.style.display = 'none';

        const fd = new FormData();
        fd.append('name', name);
        fd.append('phone', phone);
        fd.append('email', email);
        fd.append('document', doc);

        fetch('create-order.php?ajax_create_customer=1', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const select = document.querySelector('select[name="user_id"]');
                const opt = document.createElement('option');
                opt.value = data.id;
                const info = data.document || data.phone || 'Sem doc/tel';
                opt.innerText = `${data.name} (${info})`;
                select.appendChild(opt);
                select.value = data.id;
                closeNewCustomerModal();
            } else {
                errEl.innerText = '❌ ' + (data.error || 'Erro desconhecido');
                errEl.style.display = 'block';
            }
        })
        .catch(err => {
            errEl.innerText = '❌ Erro de conexão com o servidor.';
            errEl.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = '🚀 Salvar Cliente';
        });
    }
    </script>

    <!-- MODAL: QUICK CUSTOMER CREATION -->
    <div id="quickCustomerModal" class="modal-quick-customer" style="display:none;" onclick="if(event.target == this) closeNewCustomerModal()">
        <div class="modal-qc-content">
            <div class="modal-qc-header">
                <span class="modal-qc-title">👤 Cadastrar Novo Cliente</span>
                <button type="button" class="modal-qc-close" onclick="closeNewCustomerModal()">&times;</button>
            </div>
            
            <div id="qcError" style="display:none; background:#e74c3c; color:white; padding:8px 12px; border-radius:6px; font-size:0.85rem; margin-bottom:12px; text-align:left;"></div>
            
            <div class="qc-form-group">
                <label>Nome Completo *</label>
                <input type="text" id="qc_name" class="qc-input" placeholder="Ex: João Silva" required>
            </div>
            
            <div class="qc-form-group">
                <label>WhatsApp / Telefone</label>
                <input type="text" id="qc_phone" class="qc-input" placeholder="Ex: 11999999999">
            </div>
            
            <div class="qc-form-group">
                <label>E-mail (Opcional)</label>
                <input type="email" id="qc_email" class="qc-input" placeholder="Ex: joao@gmail.com">
            </div>
            
            <div class="qc-form-group">
                <label>CPF/CNPJ (Opcional)</label>
                <input type="text" id="qc_doc" class="qc-input" placeholder="Ex: 123.456.789-00">
            </div>
            
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeNewCustomerModal()" style="flex:1;">Cancelar</button>
                <button type="button" id="btnSaveQuickCustomer" onclick="saveQuickCustomer()" class="btn" style="background:var(--primary); color:#000; flex:1; font-weight:bold;">🚀 Salvar Cliente</button>
            </div>
        </div>
    </div>
</body>
</html>