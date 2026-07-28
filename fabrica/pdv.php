<?php
// catalogo/fabrica/pdv.php
require_once __DIR__ . '/header.php';

// Complete B2B Sale Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $discount = floatval($_POST['discount_amount'] ?? 0);
    $shipping = floatval($_POST['shipping_cost'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? 'pix');
    $shipping_method = trim($_POST['shipping_method'] ?? '');
    $cart_json = trim($_POST['cart_data'] ?? '[]');

    $cart = json_decode($cart_json, true);

    if ($client_id > 0 && !empty($cart)) {
        try {
            $pdo->beginTransaction();

            // Calculate total
            $subtotal = 0;
            foreach ($cart as $item) {
                $subtotal += floatval($item['price']) * intval($item['qty']);
            }
            $total_amount = max(0, $subtotal - $discount) + $shipping;

            // 1. Insert into factory_sales
            $stmt = $pdo->prepare("INSERT INTO factory_sales (client_id, total_amount, discount_amount, shipping_cost, payment_method, shipping_method, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$client_id, $total_amount, $discount, $shipping, $payment_method, $shipping_method]);
            $sale_id = $pdo->lastInsertId();

            // 2. Insert items and decrease stock
            $stmtItem = $pdo->prepare("INSERT INTO factory_sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtStock = $pdo->prepare("UPDATE factory_products SET stock_qty = stock_qty - ? WHERE id = ?");

            foreach ($cart as $item) {
                $prod_id = intval($item['id']);
                $qty = intval($item['qty']);
                $price = floatval($item['price']);
                $item_subtotal = $price * $qty;

                // Insert item record
                $stmtItem->execute([$sale_id, $prod_id, $item['name'], $qty, $price, $item_subtotal]);

                // Update inventory
                $stmtStock->execute([$qty, $prod_id]);
            }

            // 3. Insert revenue entry in Cashbook
            $client_stmt = $pdo->prepare("SELECT name FROM factory_clients WHERE id = ?");
            $client_stmt->execute([$client_id]);
            $client_name = $client_stmt->fetchColumn() ?: 'Cliente B2B';

            $desc = "Venda B2B #$sale_id - Cliente: $client_name";
            $stmtCash = $pdo->prepare("INSERT INTO factory_cashbook (type, amount, description) VALUES ('income', ?, ?)");
            $stmtCash->execute([$total_amount, $desc]);

            $pdo->commit();
            echo "<script>alert('Venda #$sale_id realizada com sucesso!'); window.location.href='sales.php';</script>";
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Erro ao finalizar venda: " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('Carrinho vazio ou cliente não selecionado.');</script>";
    }
}

// Fetch clients and products for the checkout UI
$clients = $pdo->query("SELECT id, name FROM factory_clients ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT fp.id, fp.name, fp.sku, fp.sale_price, fp.stock_qty, COALESCE(NULLIF(fp.image_path,''), p.image_path) AS image_path FROM factory_products fp LEFT JOIN products p ON fp.sku = p.sku ORDER BY fp.name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; height: calc(100vh - 180px); min-height: 550px;">
    
    <!-- Products Column -->
    <div style="display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; padding-right: 10px;">
        <div class="card" style="margin-bottom:0; flex:1; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3><i class="fas fa-cubes"></i> Produtos da Fábrica</h3>
                <input type="text" id="pdvSearch" placeholder="Pesquisar produto ou SKU..." class="form-control" style="max-width:300px;" onkeyup="filterPdvProducts()">
            </div>
            
            <div style="flex:1; overflow-y:auto;">
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:1.2rem;" id="pdvProductsList">
                    <?php foreach($products as $p): ?>
                        <div class="product-pdv-card" data-name="<?php echo htmlspecialchars($p['name']); ?>" data-sku="<?php echo htmlspecialchars($p['sku']); ?>" style="background:#080b10; border: 1px solid var(--border); border-radius:10px; padding:1.2rem; display:flex; flex-direction:column; justify-content:space-between; transition:0.2s">
                            <div>
                                <!-- Image Thumbnail -->
                                <div style="width:100%; height:120px; border-radius:8px; overflow:hidden; background:#111b21; border:1px solid var(--border); margin-bottom:12px; display:flex; align-items:center; justify-content:center;">
                                    <?php if(!empty($p['image_path'])): ?>
                                        <?php 
                                        $imgPath = $p['image_path'];
                                        // If it's just a filename (from products table), prepend the uploads path
                                        if (strpos($imgPath, '/') === false) {
                                            $imgPath = 'assets/uploads/' . $imgPath;
                                        }
                                        ?>
                                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($imgPath); ?>" style="width:100%; height:100%; object-fit:cover;" alt="Prod Image">
                                    <?php else: ?>
                                        <i class="fas fa-image" style="font-size:2rem; color:var(--text-muted);"></i>
                                    <?php endif; ?>
                                </div>
                                <h4 style="margin-bottom:5px; font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></h4>
                                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:10px;">SKU: <?php echo htmlspecialchars($p['sku'] ?: '-'); ?></div>
                            </div>
                            <div>
                                <div style="font-size:1.2rem; font-weight:800; color:var(--primary); margin-bottom:10px;">R$ <?php echo number_format($p['sale_price'], 2, ',', '.'); ?></div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:0.8rem; color:var(--text-muted);">Estoque: <?php echo $p['stock_qty']; ?> un</span>
                                    <button class="btn btn-primary btn-sm" onclick="addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['sale_price']; ?>, <?php echo $p['stock_qty']; ?>)"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Cart Column -->
    <div style="display: flex; flex-direction: column;">
        <div class="card" style="height:100%; display:flex; flex-direction:column; margin-bottom:0; justify-content:space-between;">
            <form method="POST" id="checkoutForm" onsubmit="prepareCheckout(event)">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="cart_data" id="cart_data">

                <div>
                    <h3 style="margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:10px; display:flex; align-items:center; gap:10px;"><i class="fas fa-shopping-cart" style="color:var(--primary);"></i> Carrinho B2B</h3>
                    
                    <div class="form-group">
                        <label>Cliente B2B</label>
                        <select name="client_id" class="form-control" required>
                            <option value="">Selecione o Cliente...</option>
                            <?php foreach($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cart Items List -->
                    <div style="max-height:220px; overflow-y:auto; margin:1.5rem 0; border:1px solid var(--border); border-radius:8px; background:#080b10;" id="cartList">
                        <div style="padding:15px; text-align:center; color:var(--text-muted); font-size:0.9rem;" id="cartPlaceholder">Carrinho vazio</div>
                    </div>
                </div>

                <!-- Totals & Invoicing Details -->
                <div>
                    <div class="form-group" style="margin-bottom: 0.8rem;">
                        <label>Desconto (R$)</label>
                        <input type="number" step="0.01" min="0" name="discount_amount" id="disc" class="form-control" value="0.00" oninput="updateCartDisplay()">
                    </div>

                    <div class="form-group" style="margin-bottom: 0.8rem;">
                        <label>Frete (R$)</label>
                        <input type="number" step="0.01" min="0" name="shipping_cost" id="ship" class="form-control" value="0.00" oninput="updateCartDisplay()">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 0.8rem;">
                        <div>
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800;">Faturamento</label>
                            <select name="payment_method" class="form-control" style="padding:8px;">
                                <option value="pix">PIX</option>
                                <option value="boleto">Boleto Bancário</option>
                                <option value="card">Cartão</option>
                                <option value="money">Dinheiro</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800;">Transportadora</label>
                            <select name="shipping_method" class="form-control" style="padding:8px;">
                                <option value="retirar">Retirar na Fábrica</option>
                                <option value="lalamove">Lalamove Express</option>
                                <option value="melhorenvio">Melhor Envio</option>
                                <option value="proprio">Veículo Próprio</option>
                            </select>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border); padding-top: 10px; margin-top: 10px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:var(--text-muted); margin-bottom:5px;">
                            Subtotal: <span id="cartSubtotal" style="color:#fff;">R$ 0,00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:1.3rem; font-weight:800; color:var(--primary);">
                            TOTAL: <span id="cartTotal">R$ 0,00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; font-size:1.05rem; font-weight:800; padding:12px; margin-top:1.5rem;"><i class="fas fa-check-double"></i> FINALIZAR PEDIDO</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
let cart = [];

function filterPdvProducts() {
    const query = document.getElementById('pdvSearch').value.toUpperCase();
    document.querySelectorAll('.product-pdv-card').forEach(card => {
        const name = card.dataset.name.toUpperCase();
        const sku = card.dataset.sku.toUpperCase();
        if (name.includes(query) || sku.includes(query)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function addToCart(id, name, price, stock) {
    let item = cart.find(x => x.id === id);
    if (item) {
        if(item.qty >= stock) {
            alert('Quantidade máxima em estoque atingida.');
            return;
        }
        item.qty += 1;
    } else {
        if(stock <= 0) {
            alert('Produto sem estoque disponível na fábrica.');
            return;
        }
        cart.push({ id, name, price, qty: 1, stock });
    }
    updateCartDisplay();
}

function removeFromCart(id) {
    cart = cart.filter(x => x.id !== id);
    updateCartDisplay();
}

function changeQty(id, delta) {
    let item = cart.find(x => x.id === id);
    if(item) {
        item.qty += delta;
        if(item.qty <= 0) {
            removeFromCart(id);
        } else if(item.qty > item.stock) {
            alert('Quantidade máxima de estoque atingida.');
            item.qty = item.stock;
        }
    }
    updateCartDisplay();
}

function updateCartDisplay() {
    const list = document.getElementById('cartList');
    const placeholder = document.getElementById('cartPlaceholder');
    
    if(cart.length === 0) {
        list.innerHTML = `<div style="padding:15px; text-align:center; color:var(--text-muted); font-size:0.9rem;" id="cartPlaceholder">Carrinho vazio</div>`;
        document.getElementById('cartSubtotal').innerText = 'R$ 0,00';
        document.getElementById('cartTotal').innerText = 'R$ 0,00';
        return;
    }

    let subtotal = 0;
    let html = '';
    
    cart.forEach(item => {
        const itemSub = item.price * item.qty;
        subtotal += itemSub;
        html += `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid var(--border);">
                <div style="flex:1;">
                    <div style="font-weight:600; font-size:0.85rem; color:#fff;">${item.name}</div>
                    <div style="font-size:0.75rem; color:var(--text-muted);">R$ ${item.price.toLocaleString('pt-BR', {minimumFractionDigits:2})} c/u</div>
                </div>
                <div style="display:flex; align-items:center; gap:8px; margin-right:15px;">
                    <button type="button" onclick="changeQty(${item.id}, -1)" style="background:var(--border); border:none; color:#fff; width:22px; height:22px; border-radius:50%; font-weight:bold; cursor:pointer;">-</button>
                    <span style="font-size:0.9rem; font-weight:bold;">${item.qty}</span>
                    <button type="button" onclick="changeQty(${item.id}, 1)" style="background:var(--border); border:none; color:#fff; width:22px; height:22px; border-radius:50%; font-weight:bold; cursor:pointer;">+</button>
                </div>
                <div style="font-weight:bold; font-size:0.85rem; width:80px; text-align:right;">
                    R$ ${itemSub.toLocaleString('pt-BR', {minimumFractionDigits:2})}
                </div>
                <a href="javascript:void(0)" onclick="removeFromCart(${item.id})" style="color:var(--danger); margin-left:10px;"><i class="fas fa-trash-alt"></i></a>
            </div>
        `;
    });
    
    list.innerHTML = html;

    const discount = parseFloat(document.getElementById('disc').value) || 0;
    const shipping = parseFloat(document.getElementById('ship').value) || 0;
    const total = Math.max(0, subtotal - discount) + shipping;

    document.getElementById('cartSubtotal').innerText = 'R$ ' + subtotal.toLocaleString('pt-BR', {minimumFractionDigits:2});
    document.getElementById('cartTotal').innerText = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2});
}

function prepareCheckout(e) {
    if(cart.length === 0) {
        e.preventDefault();
        alert('Adicione pelo menos um produto ao carrinho.');
        return;
    }
    document.getElementById('cart_data').value = JSON.stringify(cart);
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
