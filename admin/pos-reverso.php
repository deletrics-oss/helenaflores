<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();
if (empty($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// AJAX: Search Products (Without stock constraints for returns)
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
        $email = 'pdv_rev_' . time() . '@temp.com';
        $stmt->execute([$name, $phone, $pass, $email]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Checkout POST (Refund Processing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_json'], true);
    $subtotal = (float) $_POST['subtotal'];
    $discount = (float) $_POST['discount'];
    $total = (float) $_POST['total'];
    $refund_method = $_POST['payment_method']; // 'Saldo', 'Pix', 'Dinheiro'
    $customer_id = (int) $_POST['customer_id'];
    $reason = trim($_POST['return_reason'] ?? '');
    if (empty($reason)) {
        $reason = 'Devolução via PDV Reverso';
    }

    if (!empty($cart) && $customer_id > 0) {
        $pdo->beginTransaction();
        try {
            // 1. Fetch Customer info
            $c_stmt = $pdo->prepare("SELECT name, phone, document FROM users WHERE id = ?");
            $c_stmt->execute([$customer_id]);
            $customer = $c_stmt->fetch();

            $item_names = [];
            foreach ($cart as $item) {
                $pid = $item['pid'];
                $vid = !empty($item['vid']) ? $item['vid'] : null;
                $qty = (int) $item['qty'];
                $price = (float) $item['price'];

                $item_names[] = $item['name'] . " (x" . $qty . ")";

                // 2. Put item back into stock (Restocking)
                if ($vid) {
                    $pdo->prepare("UPDATE product_variations SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$qty, $vid]);
                } else {
                    $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$qty, $pid]);
                }

                // 3. Record in stock_movements (type = 'in')
                $pdo->prepare("INSERT INTO stock_movements (product_id, variation_id, type, qty, reason) VALUES (?, ?, 'in', ?, ?)")
                    ->execute([$pid, $vid, $qty, "Devolução via PDV Reverso (RMA)"]);

                // 4. Create an RMA ticket marked as 'resolved'
                $pdo->prepare("INSERT INTO rma_tickets (type, customer_name, document, phone, product_id, product_name, issue_type, issue_desc, status, qty_returned, refund_price, refund_method, resolved_at) VALUES ('devolucao', ?, ?, ?, ?, ?, 'Devolução via PDV Reverso', ?, 'resolved', ?, ?, ?, NOW())")
                    ->execute([
                        $customer['name'], 
                        $customer['document'], 
                        $customer['phone'], 
                        $pid, 
                        $item['name'], 
                        $reason, 
                        $qty,
                        $price,
                        $refund_method
                    ]);
            }

            // 5. Generate Ledger Credit / Payment entry
            $summary_desc = "Devolução de itens no PDV Reverso: " . implode(', ', $item_names);
            if ($reason !== 'Devolução via PDV Reverso') {
                $summary_desc .= " | Motivo: " . $reason;
            }

            $method_label = "Estorno / Devolução (" . $refund_method . ")";
            $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, ?, ?)")
                ->execute([$customer_id, $total, $method_label, $summary_desc]);

            // Sync dynamic current_debt for the user
            $pdo->prepare("UPDATE users u SET current_debt = (
                (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
            ) WHERE id = ?")->execute([$customer_id]);

            $pdo->commit();
            header("Location: pos-reverso.php?success=1&total_credit=" . $total . "&customer_id=" . $customer_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao processar devolução: " . $e->getMessage();
        }
    } else {
        $error = "Selecione um cliente e adicione itens para devolução.";
    }
}

// Initial products list for returns (Without stock constraints)
$initialProducts = $pdo->query("(SELECT id, NULL as var_id, name, price, stock_qty, image_path, 'product' as type FROM products WHERE active = 1)
UNION (SELECT p.id, v.id as var_id, CONCAT(p.name, ' - ', v.type, ': ', v.value) as name, COALESCE(v.price, p.price) as price, v.stock_qty, COALESCE(v.image_path, p.image_path) as image_path, 'variation' as type FROM product_variations v JOIN products p ON v.product_id = p.id WHERE p.active = 1) LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$refundSuccess = isset($_GET['success']) ? true : false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>PDV Reverso - Devolução & RMA | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root{--primary:#e74c3c;--bg-pdv:#0f131a;--bg-card:#1a1e26;--text:#ecf0f1}
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
        .payment-methods{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:10px}
        .pay-btn{background:#252a33;border:1px solid #444;color:#fff;padding:8px;border-radius:6px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:3px;font-size:.75rem}
        .pay-btn.active{border-color:var(--primary);background:rgba(231,76,60,.1)}
        .btn-checkout{background:var(--primary);color:#fff;width:100%;padding:12px;border:none;border-radius:8px;font-weight:bold;font-size:1.1rem;cursor:pointer}
        .btn-checkout:disabled{opacity:.5;cursor:not-allowed}
        .customer-section{background:#252a33;border-radius:8px;padding:10px;margin-bottom:10px}
        .customer-section input{width:100%;padding:8px;background:#111;border:1px solid #444;color:#fff;border-radius:4px;margin-bottom:5px}
        .customer-result{padding:6px 8px;cursor:pointer;border-bottom:1px solid #333;font-size:.85rem}
        .customer-result:hover{background:#333}
        .discount-row{display:flex;gap:10px;align-items:center;margin-bottom:8px}
        .discount-row input{width:120px;padding:6px;background:#111;border:1px solid #444;color:#fff;border-radius:4px;text-align:right}
        .quick-form{background:#1a1e26;border:1px solid var(--primary);border-radius:8px;padding:10px;margin-top:5px}
        .quick-form input{width:100%;padding:8px;background:#111;border:1px solid #444;color:#fff;border-radius:4px;margin-bottom:6px}
        .hotkey-badge{display:inline-block;background:#333;color:#888;font-size:.55rem;padding:1px 4px;border-radius:3px;margin-left:3px;font-weight:800;letter-spacing:.5px}
    </style>
</head>
<body>
    <div class="pos-container">
        <div class="top-bar">
            <div style="font-size:1.3rem; font-weight:bold; color:var(--primary); margin-right:15px; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-undo"></i> PDV REVERSO
            </div>
            <div class="search-box">
                <input type="text" id="search-input" placeholder="Buscar peça devolvida por nome ou SKU..." autocomplete="off">
                <i class="fas fa-search"></i>
            </div>
            <a href="index.php" style="background:#333;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;white-space:nowrap"><i class="fas fa-arrow-left"></i> Painel</a>
        </div>
        <div class="results-grid" id="results"></div>
    </div>

    <div class="cart-panel">
        <div class="cart-header">
            <h3><i class="fas fa-undo-alt"></i> Devoluções</h3>
            <button onclick="clearCart()" style="background:none;border:none;color:#e74c3c;cursor:pointer"><i class="fas fa-trash"></i></button>
        </div>

        <!-- Customer Selection -->
        <div style="padding:10px;border-bottom:1px solid #333">
            <div class="customer-section">
                <label style="font-size:.8rem;color:#888;margin-bottom:4px;display:block">👤 Cliente da Devolução:</label>
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
            
            <div class="discount-row" style="display:block; margin-bottom:10px;">
                <label style="font-size:.75rem;color:#888;margin-bottom:4px;display:block">Motivo da Devolução:</label>
                <input type="text" id="return-reason-input" placeholder="Ex: Defeito de fábrica, arrependimento..." style="width:100%; padding:8px; background:#111; border:1px solid #444; color:#fff; border-radius:4px; text-align:left;">
            </div>

            <div class="total-row">
                <span>VALOR RETORNO:</span><span id="cart-total" style="color:var(--primary);">R$ 0,00</span>
            </div>

            <p style="font-size:.75rem;color:#888;margin-bottom:6px">Forma de Reembolso / Estorno:</p>
            <div class="payment-methods">
                <div class="pay-btn active" onclick="setPayment('Saldo',this)"><i class="fas fa-wallet"></i>Gerar Crédito <span class="hotkey-badge">F5</span></div>
                <div class="pay-btn" onclick="setPayment('PIX',this)"><i class="fas fa-qrcode"></i>PIX <span class="hotkey-badge">F3</span></div>
                <div class="pay-btn" onclick="setPayment('Dinheiro',this)"><i class="fas fa-money-bill-wave"></i>Dinheiro <span class="hotkey-badge">F4</span></div>
            </div>

            <form method="POST" id="checkout-form">
                <input type="hidden" name="checkout" value="1">
                <input type="hidden" name="cart_json" id="cart-json">
                <input type="hidden" name="subtotal" id="subtotal-input">
                <input type="hidden" name="discount" id="discount-hidden" value="0">
                <input type="hidden" name="total" id="total-input">
                <input type="hidden" name="payment_method" id="payment-input" value="Saldo">
                <input type="hidden" name="customer_id" id="customer-id" value="0">
                <input type="hidden" name="return_reason" id="return-reason-hidden">
                <button type="submit" class="btn-checkout" id="btn-finish" disabled>FINALIZAR DEVOLUÇÃO (F8)</button>
            </form>
        </div>
    </div>

    <!-- SUCCESS OVERLAY -->
    <?php if ($refundSuccess && isset($_GET['total_credit']) && isset($_GET['customer_id'])): 
        $cred = (float)$_GET['total_credit'];
        $cid = (int)$_GET['customer_id'];
        $cname = $pdo->query("SELECT name FROM users WHERE id = $cid")->fetchColumn() ?: "Cliente";
    ?>
    <div id="posSuccessOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:99999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px)">
        <div style="background:#1a1e26;border:2px solid var(--primary);border-radius:16px;padding:3rem;text-align:center;max-width:450px;position:relative;box-shadow:0 20px 50px rgba(0,0,0,0.5)">
            <button onclick="document.getElementById('posSuccessOverlay').style.display='none'" style="position:absolute;top:15px;right:20px;background:none;border:none;color:#555;font-size:2rem;cursor:pointer;line-height:1">&times;</button>
            <div style="font-size:4rem;margin-bottom:1rem">🔄</div>
            <h2 style="color:var(--primary);margin-bottom:.5rem">Devolução Concluída!</h2>
            <div style="background:#222;padding:12px;border-radius:6px;margin-bottom:1.5rem;color:#aaa;">
                Crédito de <strong style="color:#2ecc71; font-size:1.2rem;">R$ <?php echo number_format($cred, 2, ',', '.'); ?></strong><br>
                gerado para <strong style="color:#fff;"><?php echo htmlspecialchars($cname); ?></strong>.
            </div>
            
            <div style="display:flex;flex-direction:column;gap:10px">
                <a href="customer-details.php?id=<?php echo $cid; ?>" style="background:#3498db;color:#fff;padding:12px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:1rem;display:block;">
                    👤 Ver Extrato do Cliente
                </a>
                <a href="rma.php" style="background:#2ecc71;color:#000;padding:12px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:1rem;display:block;">
                    🔄 Ir para Painel RMA
                </a>
                <a href="pos-reverso.php" style="background:#222;color:#fff;padding:14px;border-radius:8px;text-decoration:none;font-weight:bold;border:1px solid #444;display:block;">
                    Nova Devolução
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div style="position:fixed;top:20px;right:20px;background:#e74c3c;color:#fff;padding:15px 20px;border-radius:8px;z-index:99999"><?php echo $error; ?></div>
    <?php endif; ?>

    <script>
    let cart = [];
    let selectedPayment = 'Saldo';
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
            div.innerHTML = `<div style="position:relative"><img src="${imgSrc}"><div style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,.7);color:#fff;padding:2px 5px;border-radius:4px;font-size:.65rem">Estoque:${item.stock_qty}</div></div><div class="p-name">${item.name}</div><div class="p-price">R$ ${parseFloat(item.price).toLocaleString('pt-BR',{minimumFractionDigits:2})}</div>`;
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
        searchTimer = setTimeout(() => fetch(`pos-reverso.php?search=${q}`).then(r=>r.json()).then(data=>renderProducts(data)), 300);
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
                <button onclick="changeQty(${i}, -9999)" style="background:none; border:none; color:#e74c3c; cursor:pointer; margin-left:10px; font-size:1.1rem;"><i class="fas fa-trash"></i></button>
            `;
            container.appendChild(div);
        });
        updateTotals();
    }

    function updateTotals() {
        let subtotal = 0;
        cart.forEach(item => { subtotal += item.price * item.qty; });
        const total = subtotal;
        document.getElementById('cart-subtotal').innerText = `R$ ${subtotal.toLocaleString('pt-BR',{minimumFractionDigits:2})}`;
        document.getElementById('cart-total').innerText = `R$ ${total.toLocaleString('pt-BR',{minimumFractionDigits:2})}`;
        document.getElementById('subtotal-input').value = subtotal;
        document.getElementById('total-input').value = total;
        document.getElementById('cart-json').value = JSON.stringify(cart);
        document.getElementById('btn-finish').disabled = cart.length === 0 || selectedCustomerId === 0;
        
        // Sync return reason
        document.getElementById('return-reason-hidden').value = document.getElementById('return-reason-input').value;
    }

    function changeQty(i, d) { cart[i].qty += d; if (cart[i].qty <= 0) cart.splice(i, 1); renderCart(); }
    
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

    // Customer Search
    let custTimer;
    function searchCustomer(q) {
        clearTimeout(custTimer);
        if (q.length < 2) { document.getElementById('customer-results').innerHTML = ''; return; }
        custTimer = setTimeout(() => {
            fetch(`pos-reverso.php?search_customer=${q}`).then(r=>r.json()).then(list => {
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
        fetch('pos-reverso.php', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
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
        if (e.key === 'F8') { 
            e.preventDefault(); 
            if (cart.length > 0 && selectedCustomerId > 0) {
                document.getElementById('return-reason-hidden').value = document.getElementById('return-reason-input').value;
                document.getElementById('checkout-form').submit(); 
            }
        }
        if (e.key === 'F2') { e.preventDefault(); searchInput.focus(); }
        if (e.key === 'F3') { e.preventDefault(); document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active')); document.querySelectorAll('.pay-btn')[1].classList.add('active'); setPayment('PIX', document.querySelectorAll('.pay-btn')[1]); }
        if (e.key === 'F4') { e.preventDefault(); document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active')); document.querySelectorAll('.pay-btn')[2].classList.add('active'); setPayment('Dinheiro', document.querySelectorAll('.pay-btn')[2]); }
        if (e.key === 'F5') { e.preventDefault(); document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active')); document.querySelectorAll('.pay-btn')[0].classList.add('active'); setPayment('Saldo', document.querySelectorAll('.pay-btn')[0]); }
        if (e.key === 'F9') { e.preventDefault(); clearCart(); }
    });
    </script>
</body>
</html>
