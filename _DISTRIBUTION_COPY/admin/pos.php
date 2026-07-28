<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();

// Admin Auth check
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch Categories for quick filter
$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Fetch Initial Products (Top 50 by stock or name)
$initialQuery = "(SELECT id, NULL as var_id, name, price, stock_qty, image_path, 'product' as type FROM products WHERE active = 1 AND stock_qty > 0)
UNION
(SELECT p.id, v.id as var_id, CONCAT(p.name, ' - ', v.type, ': ', v.value) as name, COALESCE(v.price, p.price) as price, v.stock_qty, COALESCE(v.image_path, p.image_path) as image_path, 'variation' as type 
 FROM product_variations v JOIN products p ON v.product_id = p.id 
 WHERE p.active = 1 AND v.stock_qty > 0)
LIMIT 50";
$initialProducts = $pdo->query($initialQuery)->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX Product Search
if (isset($_GET['search'])) {
    header('Content-Type: application/json');
    $q = "%" . $_GET['search'] . "%";

    // Search in products and variations
    $sql = "(SELECT id, NULL as var_id, name, price, stock_qty, image_path, 'product' as type FROM products WHERE (name LIKE ? OR sku LIKE ?) AND active = 1)
            UNION
            (SELECT p.id, v.id as var_id, CONCAT(p.name, ' - ', v.type, ': ', v.value) as name, COALESCE(v.price, p.price) as price, v.stock_qty, COALESCE(v.image_path, p.image_path) as image_path, 'variation' as type 
             FROM product_variations v JOIN products p ON v.product_id = p.id 
             WHERE (p.name LIKE ? OR v.sku LIKE ? OR v.value LIKE ?) AND p.active = 1)
            LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q, $q, $q, $q, $q]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Handle Checkout (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_json'], true);
    $total = (float) $_POST['total'];
    $payment = $_POST['payment_method'];

    if (!empty($cart)) {
        $pdo->beginTransaction();
        try {
            // 1. Create Sale Record
            $stmt = $pdo->prepare("INSERT INTO pos_sales (total, payment_method, status) VALUES (?, ?, 'completed')");
            $stmt->execute([$total, $payment]);
            $sale_id = $pdo->lastInsertId();

            // 2. Process Items and Deduct Stock
            $item_stmt = $pdo->prepare("INSERT INTO pos_sale_items (sale_id, product_id, variation_id, qty, unit_price) VALUES (?, ?, ?, ?, ?)");

            foreach ($cart as $item) {
                $pid = $item['pid'];
                $vid = !empty($item['vid']) ? $item['vid'] : null;
                $qty = (int) $item['qty'];
                $price = (float) $item['price'];

                $item_stmt->execute([$sale_id, $pid, $vid, $qty, $price]);

                // Update Stock
                if ($vid) {
                    $pdo->prepare("UPDATE product_variations SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $vid]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, variation_id, type, qty, reason) VALUES (?, ?, 'out', ?, ?)")
                        ->execute([$pid, $vid, $qty, "Venda PDV ID #$sale_id"]);
                } else {
                    $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $pid]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty, reason) VALUES (?, 'out', ?, ?)")
                        ->execute([$pid, $qty, "Venda PDV ID #$sale_id"]);
                }
            }

            $pdo->commit();
            header("Location: pos.php?success=1&sale_id=" . $sale_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro no checkout: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>PDV - Frente de Loja | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #f1c40f;
            --bg-pdv: #0f131a;
            --bg-card: #1a1e26;
            --text: #ecf0f1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--bg-pdv);
            color: var(--text);
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Left: Search & Products */
        .pos-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        #search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            background: var(--bg-card);
            border: 2px solid #333;
            border-radius: 10px;
            color: #fff;
            font-size: 1.2rem;
        }

        .search-box i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.2rem;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            overflow-y: auto;
        }

        .product-item {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, border-color 0.2s;
            border: 2px solid transparent;
        }

        .product-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .product-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 8px;
        }

        .product-item .p-name {
            font-weight: bold;
            font-size: 0.9rem;
            height: 2.4rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            margin-bottom: 5px;
        }

        .product-item .p-price {
            color: var(--primary);
            font-weight: bold;
            font-size: 1.1rem;
        }

        .product-item .p-stock {
            font-size: 0.75rem;
            color: #888;
            margin-top: 5px;
        }

        /* Right: Cart & Summary */
        .cart-panel {
            width: 400px;
            background: var(--bg-card);
            border-left: 2px solid #333;
            display: flex;
            flex-direction: column;
        }

        .cart-header {
            padding: 20px;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .cart-item {
            display: flex;
            gap: 10px;
            padding: 10px;
            background: #252a33;
            border-radius: 6px;
            margin-bottom: 8px;
            align-items: center;
        }

        .cart-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-size: 0.85rem;
            font-weight: bold;
        }

        .cart-item-price {
            color: var(--primary);
            font-size: 0.85rem;
        }

        .cart-qty-ctrl {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cart-qty-ctrl button {
            background: #333;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 3px;
            cursor: pointer;
        }

        .cart-footer {
            padding: 20px;
            background: #0b0e13;
            border-top: 2px solid var(--primary);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .pay-btn {
            background: #252a33;
            border: 1px solid #444;
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
        }

        .pay-btn.active {
            border-color: var(--primary);
            background: rgba(241, 196, 15, 0.1);
        }

        .pay-btn i {
            font-size: 1.2rem;
        }

        .btn-checkout {
            background: var(--primary);
            color: #000;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .btn-checkout:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Animations */
        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        .loading {
            animation: pulse 1s infinite;
        }
    </style>
</head>

<body>

    <div class="pos-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <div class="search-box" style="margin-bottom:0; flex:1; margin-right:20px;">
                <input type="text" id="search-input" placeholder="Buscar produto por nome ou SKU..." autocomplete="off">
                <i class="fas fa-search"></i>
            </div>
            <a href="index.php" class="btn-exit"
                style="background:#e74c3c; color:#fff; padding:15px 20px; border-radius:8px; text-decoration:none; font-weight:bold; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>

        <div class="results-grid" id="results">
            <!-- Products will appear here -->
            <div style="text-align:center; grid-column: 1/-1; padding-top: 5rem; color:#444;">
                <i class="fas fa-barcode" style="font-size: 4rem; display:block; margin-bottom:1rem;"></i>
                Use o campo acima para buscar itens
            </div>
        </div>
    </div>

    <div class="cart-panel">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Carrinho</h3>
            <button onclick="clearCart()" style="background:none; border:none; color: #e74c3c; cursor:pointer;"><i
                    class="fas fa-trash"></i></button>
        </div>

        <div class="cart-items" id="cart-items">
            <!-- Cart items will appear here -->
        </div>

        <div class="cart-footer">
            <div class="total-row">
                <span>TOTAL:</span>
                <span id="cart-total">R$ 0,00</span>
            </div>

            <p style="font-size: 0.8rem; color: #888; margin-bottom: 8px;">Método de Pagamento:</p>
            <div class="payment-methods">
                <div class="pay-btn active" onclick="setPayment('Dinheiro', this)">
                    <i class="fas fa-money-bill-wave"></i>
                    Dinheiro
                </div>
                <div class="pay-btn" onclick="setPayment('PIX', this)">
                    <i class="fas fa-qrcode"></i>
                    PIX
                </div>
                <div class="pay-btn" onclick="setPayment('Cartão (Maquininha)', this)">
                    <i class="fas fa-credit-card"></i>
                    Cartão
                </div>
                <div class="pay-btn" onclick="setPayment('Link (Remoto)', this)">
                    <i class="fas fa-link"></i>
                    Link Pago
                </div>
            </div>

            <form method="POST" id="checkout-form">
                <input type="hidden" name="checkout" value="1">
                <input type="hidden" name="cart_json" id="cart-json">
                <input type="hidden" name="total" id="total-input">
                <input type="hidden" name="payment_method" id="payment-input" value="Dinheiro">
                <button type="submit" class="btn-checkout" id="btn-finish" disabled>FINALIZAR VENDA (F8)</button>
            </form>
        </div>
    </div>

    <!-- Success Modal/Popup -->
    <?php if (isset($_GET['success'])): ?>
        <div
            style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:99999; display:flex; align-items:center; justify-content:center;">
            <div
                style="background:#1a1e26; padding:3rem; border-radius:20px; text-align:center; border: 2px solid var(--primary); max-width: 400px;">
                <i class="fas fa-check-circle" style="font-size: 5rem; color: #2ecc71; margin-bottom: 1.5rem;"></i>
                <h2 style="margin-bottom:1rem;">Venda Concluída!</h2>
                <p style="color:#aaa; margin-bottom:2rem;">O estoque foi atualizado automaticamente.</p>
                <a href="pos.php" class="btn"
                    style="background:var(--primary); color:#000; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;">NOVA
                    VENDA</a>
            </div>
        </div>
    <?php endif; ?>

    <script>
        let cart = [];
        let selectedPayment = 'Dinheiro';
        const searchInput = document.getElementById('search-input');
        const resultsGrid = document.getElementById('results');
        const initialProducts = <?php echo json_encode($initialProducts); ?>;

        // Initital Load
        document.addEventListener('DOMContentLoaded', () => {
            renderProducts(initialProducts);
        });

        function renderProducts(list) {
            resultsGrid.innerHTML = '';
            if (list.length === 0) {
                resultsGrid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:2rem; color:#666;">Nenhum produto encontrado</div>';
                return;
            }
            list.forEach(item => {
                const div = document.createElement('div');
                div.className = 'product-item';
                div.innerHTML = `
                    <div style="position:relative;">
                        <img src="${item.image_path ? (item.image_path.startsWith('http') ? item.image_path : '../assets/uploads/' + item.image_path) : '../assets/no-img.png'}">
                        <div class="p-stock-badge" style="position:absolute; top:5px; right:5px; background:rgba(0,0,0,0.7); color:#fff; padding:2px 6px; border-radius:4px; font-size:0.7rem;">Qt: ${item.stock_qty}</div>
                    </div>
                    <div class="p-name">${item.name}</div>
                    <div class="p-price">R$ ${parseFloat(item.price).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                `;
                div.onclick = () => addToCart(item);
                resultsGrid.appendChild(div);
            });
        }

        // Search Logic
        searchInput.addEventListener('input', function () {
            const query = this.value;
            if (query.length === 0) {
                renderProducts(initialProducts);
                return;
            }
            if (query.length < 2) return;

            fetch(`pos.php?search=${query}`)
                .then(r => r.json())
                .then(data => {
                    renderProducts(data);
                });
        });

        function addToCart(item) {
            const key = item.var_id ? `v${item.var_id}` : `p${item.id}`;
            const existing = cart.find(i => (i.vid == item.var_id && i.pid == item.id));

            if (existing) {
                existing.qty++;
            } else {
                cart.push({
                    pid: item.id,
                    vid: item.var_id,
                    name: item.name,
                    price: parseFloat(item.price),
                    qty: 1,
                    image: item.image_path
                });
            }
            renderCart();
            searchInput.value = '';
            searchInput.focus();
        }

        function renderCart() {
            const container = document.getElementById('cart-items');
            container.innerHTML = '';
            let total = 0;

            cart.forEach((item, index) => {
                total += item.price * item.qty;
                const div = document.createElement('div');
                div.className = 'cart-item';
                div.innerHTML = `
                    <img src="${item.image ? (item.image.startsWith('http') ? item.image : '../assets/uploads/' + item.image) : '../assets/no-img.png'}">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">R$ ${item.price.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                    </div>
                    <div class="cart-qty-ctrl">
                        <button onclick="changeQty(${index}, -1)">-</button>
                        <span>${item.qty}</span>
                        <button onclick="changeQty(${index}, 1)">+</button>
                    </div>
                `;
                container.appendChild(div);
            });

            document.getElementById('cart-total').innerText = `R$ ${total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            document.getElementById('total-input').value = total;
            document.getElementById('cart-json').value = JSON.stringify(cart);
            document.getElementById('btn-finish').disabled = cart.length === 0;
        }

        function changeQty(index, delta) {
            cart[index].qty += delta;
            if (cart[index].qty <= 0) cart.splice(index, 1);
            renderCart();
        }

        function clearCart() {
            if (confirm('Limpar o carrinho?')) {
                cart = [];
                renderCart();
            }
        }

        function setPayment(method, btn) {
            selectedPayment = method;
            document.getElementById('payment-input').value = method;
            document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        // Hotkeys
        window.addEventListener('keydown', e => {
            if (e.key === 'F8') {
                e.preventDefault();
                if (cart.length > 0) document.getElementById('checkout-form').submit();
            }
            if (e.key === 'F2') {
                e.preventDefault();
                searchInput.focus();
            }
        });
    </script>
</body>

</html>