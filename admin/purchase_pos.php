<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// Fetch suppliers for the sidebar
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

// AJAX: Search Products (with Cost Price History)
if (isset($_GET['search'])) {
    header('Content-Type: application/json');
    $q = "%" . $_GET['search'] . "%";
    
    // Fetch products and variations with sales velocity intelligence
    $sql = "
        (SELECT p.id, NULL as var_id, p.name, p.sku, p.cost_price, p.stock_qty, p.image_path, 'product' as type,
        (SELECT MIN(unit_cost) FROM purchase_order_items WHERE product_id = p.id AND variation_id IS NULL) as best_price,
        (SELECT COALESCE(SUM(quantity),0) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status IN ('paid','shipped') AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as sales_30d
        FROM products p 
        WHERE (p.name LIKE ? OR p.sku LIKE ?))
        UNION
        (SELECT p.id, v.id as var_id, CONCAT(p.name, ' - ', v.type, ': ', v.value) as name, v.sku, v.cost_price, v.stock_qty, COALESCE(v.image_path, p.image_path) as image_path, 'variation' as type,
        (SELECT MIN(unit_cost) FROM purchase_order_items WHERE variation_id = v.id) as best_price,
        (SELECT COALESCE(SUM(quantity),0) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.variation_id = v.id AND o.status IN ('paid','shipped') AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as sales_30d
        FROM product_variations v JOIN products p ON v.product_id = p.id
        WHERE (p.name LIKE ? OR v.sku LIKE ? OR v.value LIKE ?))
        LIMIT 100
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q, $q, $q, $q, $q]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($res);
    exit;
}

// AJAX: Quick Create Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_create') {
    header('Content-Type: application/json');
    try {
        $name = trim($_POST['name']);
        $sku = trim($_POST['sku']);
        $cat_id = (int)$_POST['category_id'];
        $cost = (float)str_replace(['.', ','], ['', '.'], $_POST['cost_price']);
        $price = (float)str_replace(['.', ','], ['', '.'], $_POST['price']);
        
        $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, cost_price, price, stock_qty, active) VALUES (?, ?, ?, ?, ?, 0, 1)");
        $stmt->execute([$name, $sku, $cat_id, $cost, $price]);
        $pid = $pdo->lastInsertId();
        
        // Return product object for cart
        $newProd = $pdo->query("SELECT id, NULL as var_id, name, sku, cost_price, stock_qty, image_path, 'product' as type FROM products WHERE id = $pid")->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'product' => $newProd]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX: Price Comparison Details
if (isset($_GET['ajax_price_history']) && isset($_GET['product_id'])) {
    header('Content-Type: application/json');
    $pid = (int)$_GET['product_id'];
    $stmt = $pdo->prepare("SELECT poi.unit_cost, po.created_at, s.name as supplier_name 
                          FROM purchase_order_items poi 
                          JOIN purchase_orders po ON poi.purchase_order_id = po.id 
                          JOIN suppliers s ON po.supplier_id = s.id 
                          WHERE poi.product_id = ? 
                          ORDER BY po.created_at DESC LIMIT 10");
    $stmt->execute([$pid]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// AJAX: Save Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_purchase') {
    header('Content-Type: application/json');
    $cart = json_decode($_POST['cart'], true);
    $supplier_id = (int)$_POST['supplier_id'];
    $total = (float)$_POST['total'];
    
    if (empty($cart) || !$supplier_id) {
        echo json_encode(['success' => false, 'error' => 'Carrinho vazio ou fornecedor não selecionado']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, total_amount, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$supplier_id, $total]);
        $purchase_id = $pdo->lastInsertId();
        
        foreach ($cart as $item) {
            $pid = $item['id'];
            $vid = $item['var_id'] ?: null;
            $qty = $item['qty'];
            $price = $item['price'];
            
            $stmt = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, variation_id, quantity, unit_cost) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$purchase_id, $pid, $vid, $qty, $price]);
            
            // Update cost price and stock in products/variations
            if ($vid) {
                $pdo->prepare("UPDATE product_variations SET stock_qty = stock_qty + ?, cost_price = ? WHERE id = ?")->execute([$qty, $price, $vid]);
            } else {
                $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, cost_price = ? WHERE id = ?")->execute([$qty, $price, $pid]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $purchase_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>PDV de Compra | Inteligência de Suprimentos</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #f1c40f; --bg-dark: #0b0e14; --bg-card: #161b22; --accent: #3498db; }
        body { background: var(--bg-dark); color: #fff; overflow: hidden; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .pos-wrapper { display: flex; height: calc(100vh - 140px); background: #0d1117; }
        
        /* Main Area */
        .main-content { flex: 1; display: flex; flex-direction: column; padding: 20px; overflow-y: auto; background: #0d1117; }
        
        .top-search-bar { 
            background: linear-gradient(145deg, #161b22, #0d1117); 
            padding: 15px 25px; 
            border-radius: 16px; 
            margin-bottom: 25px; 
            display: flex; 
            gap: 15px; 
            align-items: center; 
            border: 1px solid #30363d;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .top-search-bar input { 
            flex: 1; background: transparent; border: none; color: #fff; font-size: 1.1rem; outline: none; 
            font-weight: 500;
        }
        
        /* Product Grid */
        .product-grid { 
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; 
        }
        .product-card { 
            background: var(--bg-card); border-radius: 12px; border: 1px solid #30363d; padding: 12px; 
            transition: transform 0.2s, border-color 0.2s; cursor: pointer; position: relative;
        }
        .product-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .product-card img { width: 100%; height: 140px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; }
        
        .stock-badge { position: absolute; top: 18px; right: 18px; background: rgba(0,0,0,0.8); color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; }
        .p-name { font-weight: bold; font-size: 0.85rem; margin-bottom: 8px; color: #e6edf3; height: 2.4rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .p-price { color: var(--primary); font-weight: 900; font-size: 1.1rem; }
        
        /* Cart Sidebar (Matching POS Venda) */
        .cart-sidebar { 
            width: 420px; background: #161b22; border-left: 2px solid #30363d; 
            display: flex; flex-direction: column; height: 100%; position: relative;
        }
        .cart-header { padding: 20px; border-bottom: 1px solid #30363d; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; }
        .cart-items { flex: 1; overflow-y: auto; padding: 15px; }
        
        .cart-item { 
            background: #21262d; border-radius: 10px; padding: 12px; margin-bottom: 10px; 
            display: flex; gap: 12px; align-items: center; border: 1px solid transparent; transition: 0.2s;
        }
        .cart-item:hover { border-color: #444; background: #2d333b; }
        .cart-item img { width: 55px; height: 55px; object-fit: cover; border-radius: 8px; }
        .cart-item-info { flex: 1; }
        .cart-item-name { font-size: 0.85rem; font-weight: bold; color: #fff; margin-bottom: 5px; }
        
        .cart-item-price { display: flex; align-items: center; gap: 8px; }
        .cart-item-price input { 
            width: 80px; background: #0d1117; border: 1px solid #444; color: var(--primary); 
            padding: 5px; border-radius: 6px; font-weight: bold; text-align: center;
        }

        .cart-qty-ctrl { display: flex; align-items: center; gap: 4px; background: #0d1117; padding: 4px; border-radius: 8px; border: 1px solid #30363d; }
        .cart-qty-ctrl button { 
            background: #30363d; color: #fff; border: none; width: 32px; height: 32px; 
            border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .cart-qty-ctrl button:hover { background: var(--primary); color: #000; }
        .cart-qty-ctrl input { 
            width: 50px; background: transparent; border: none; color: #fff; 
            text-align: center; font-weight: bold; font-size: 1.1rem; outline: none;
            -moz-appearance: textfield;
        }
        .cart-qty-ctrl input::-webkit-outer-spin-button,
        .cart-qty-ctrl input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        .cart-footer { padding: 20px; background: #0d1117; border-top: 2px solid var(--primary); flex-shrink: 0; }
        .total-row { display: flex; justify-content: space-between; font-size: 1.5rem; font-weight: 900; margin-bottom: 15px; color: #fff; }
        
        .btn-confirm { background: var(--primary); color: #000; border: none; width: 100%; padding: 15px; border-radius: 10px; font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: 0.2s; }
        .btn-confirm:hover { transform: scale(1.02); filter: brightness(1.1); }
        .btn-confirm:disabled { opacity: 0.3; cursor: not-allowed; transform: none; }

        .supplier-select { width: 100%; padding: 12px; background: #21262d; border: 1px solid #30363d; color: #fff; border-radius: 8px; margin-bottom: 10px; outline: none; font-weight: bold; }
        
        .btn-add-to-cart {
            background: var(--primary);
            color: #000;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 900;
            font-size: 0.8rem;
            cursor: pointer;
            width: 100%;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .btn-add-to-cart:hover { filter: brightness(1.1); transform: scale(1.02); }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-card { background: #161b22; width: 500px; padding: 30px; border-radius: 20px; border: 1px solid #30363d; text-align: center; box-shadow: 0 0 30px rgba(0,0,0,0.5); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="pos-wrapper">
        <!-- Main Product Area -->
        <div class="main-content">
            <div class="top-search-bar">
                <i class="fas fa-search" style="color:#8b949e;"></i>
                <input type="text" id="product-search" placeholder="Buscar produto para COMPRA (Nome ou SKU)..." autocomplete="off">
                <button onclick="document.getElementById('product-search').value=''; renderResults([])" style="background:none; border:none; color:#8b949e; cursor:pointer;">LIMPAR</button>
            </div>

            <div class="product-grid" id="product-results">
                <!-- Products load here -->
            </div>
        </div>

        <!-- Right Cart Sidebar -->
        <div class="cart-sidebar">
            <div class="cart-header">
                <h3 style="margin:0;"><i class="fas fa-shopping-basket" style="color:var(--primary);"></i> Lista de Compra</h3>
                <span id="item-count" style="background:#333; padding:2px 10px; border-radius:20px; font-size:0.8rem; font-weight:bold;">0 itens</span>
            </div>

            <div class="cart-items" id="cart-list">
                <!-- Cart items load here -->
            </div>

            <div class="cart-footer">
                <select id="supplier-id" class="supplier-select">
                    <option value="">-- Selecionar Fornecedor --</option>
                    <?php foreach($suppliers as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <div style="display:flex; gap:8px; margin-bottom:20px;">
                    <button onclick="window.open('suppliers.php', '_blank')" class="btn-sm" style="flex:1; background:#21262d; border:1px solid #30363d; color:#8b949e; padding:8px; border-radius:6px; cursor:pointer;"><i class="fas fa-plus"></i> Novo Fornecedor</button>
                    <button onclick="window.location.href='suppliers.php'" class="btn-sm" style="flex:1; background:#21262d; border:1px solid #30363d; color:var(--primary); padding:8px; border-radius:6px; cursor:pointer;"><i class="fas fa-external-link-alt"></i> Central</button>
                </div>

                <div class="total-row">
                    <span>TOTAL:</span>
                    <span id="cart-total">R$ 0,00</span>
                </div>

                <button id="btn-save-purchase" onclick="savePurchase()" class="btn-confirm" disabled>📦 CONFIRMAR COMPRA</button>
            </div>
        </div>
    </div>

    <!-- Modal Sucesso -->
    <div id="successModal" class="modal-overlay">
        <div class="modal-card">
            <div style="width:70px; height:70px; background:rgba(46,204,113,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                <i class="fas fa-check" style="font-size:2.5rem; color:#2ecc71;"></i>
            </div>
            <h2>Compra Registrada!</h2>
            <p style="color:#8b949e; margin-bottom:25px;">O estoque foi atualizado e o custo médio recalculado.</p>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <button id="btn-print-now" class="btn" style="background:#333;">🖨️ Imprimir Recibo</button>
                <button id="btn-lalamove-coleta" class="btn" style="background:var(--accent); color:#fff;">🏍️ Chamar Coleta</button>
            </div>
            <button onclick="location.reload()" class="btn" style="width:100%; margin-top:10px; background:none; border:1px solid #333;">Nova Compra</button>
        </div>
    </div>

    <!-- Modal Comparação Histórico -->
    <div id="compareModal" class="modal-overlay">
        <div class="modal-card" style="width:600px; text-align:left;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 id="compareTitle" style="margin:0; color:var(--primary);">⚖️ Histórico de Preços</h3>
                <span onclick="document.getElementById('compareModal').style.display='none'" style="cursor:pointer; font-size:1.5rem;">&times;</span>
            </div>
            <div id="compareResults" style="max-height:400px; overflow-y:auto;">
                <!-- Histórico carregar aqui -->
            </div>
        </div>
    </div>

    </div>

    <!-- Modal Novo Produto Rápido -->
    <div id="quickProductModal" class="modal-overlay">
        <div class="modal-card" style="width:450px; text-align:left;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; color:var(--primary);"><i class="fas fa-plus-circle"></i> Novo Produto Rápido</h3>
                <span onclick="document.getElementById('quickProductModal').style.display='none'" style="cursor:pointer; font-size:1.5rem;">&times;</span>
            </div>

            <div style="background:#000; padding:15px; border-radius:8px; border:1px dashed var(--primary); margin-bottom:1.5rem;">
                <strong style="color:var(--primary); font-size:0.85rem;"><i class="fas fa-robot"></i> Preenchimento IA</strong>
                <div style="display:flex; gap:5px; margin-top:5px;">
                    <input type="text" id="ai_quick_text" placeholder="Cole o nome ou descrição..." style="flex:1; padding:8px; background:#111; border:1px solid #333; color:#fff; font-size:0.8rem;">
                    <button type="button" onclick="runQuickProdAI()" class="btn btn-sm" style="background:var(--primary); color:#000;">✨ OK</button>
                </div>
            </div>
            
            <form id="quickProductForm">
                <input type="hidden" name="action" value="quick_create">
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Nome do Produto</label>
                    <input type="text" name="name" id="q_name" required placeholder="Ex: Botão Sanwa Original" style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:8px;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">SKU</label>
                        <input type="text" name="sku" id="q_sku" placeholder="Código" style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Categoria</label>
                        <select name="category_id" style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:8px;">
                            <?php 
                            $cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
                            foreach($cats as $c) echo "<option value='{$c['id']}'>".htmlspecialchars($c['name'])."</option>";
                            ?>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px;">
                    <div>
                        <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Custo (R$)</label>
                        <input type="text" name="cost_price" id="q_cost" required placeholder="0,00" style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:var(--primary); border-radius:8px; font-weight:bold;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Venda (R$)</label>
                        <input type="text" name="price" id="q_price" required placeholder="0,00" style="width:100%; padding:10px; background:#0d1117; border:1px solid #30363d; color:#fff; border-radius:8px;">
                    </div>
                </div>
                <button type="submit" class="btn-confirm">CADASTRAR E ADICIONAR</button>
            </form>
        </div>
    </div>

    <script>
        function runQuickProdAI() {
            const txt = document.getElementById('ai_quick_text').value;
            if(!txt) return;
            const btn = event.target;
            btn.innerText = '...';
            
            const fd = new FormData();
            fd.append('ajax_ai', '1');
            fd.append('text', txt);
            
            fetch('product-edit.php', { method:'POST', body:fd })
            .then(r=>r.json())
            .then(d=>{
                if(d.name) document.getElementById('q_name').value = d.name;
                if(d.sku) document.getElementById('q_sku').value = d.sku;
                if(d.price) document.getElementById('q_price').value = d.price;
                if(d.price_wholesale) document.getElementById('q_cost').value = d.price_wholesale;
            })
            .finally(()=> btn.innerText = '✨ OK');
        }
        const productSearch = document.getElementById('product-search');
        const productResults = document.getElementById('product-results');
        const cartList = document.getElementById('cart-list');
        const cartTotal = document.getElementById('cart-total');
        const supSelect = document.getElementById('supplier-id');
        const btnSave = document.getElementById('btn-save-purchase');

        let cart = [];

        // Load initial products (matching POS Venda)
        document.addEventListener('DOMContentLoaded', () => {
            fetch('purchase_pos.php?search=')
                .then(r => r.json())
                .then(data => renderResults(data));
        });

        // Search logic
        let searchTimeout;
        productSearch.oninput = () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const q = productSearch.value;
                fetch(`purchase_pos.php?search=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(data => renderResults(data));
            }, 300);
        };

        function renderResults(products) {
            productResults.innerHTML = '';
            
            if (products.length === 0) {
                productResults.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align:center; padding:3rem; background:rgba(155,89,182,0.05); border:2px dashed #30363d; border-radius:20px;">
                        <i class="fas fa-search-minus" style="font-size:3rem; color:#30363d; margin-bottom:15px; display:block;"></i>
                        <h3 style="color:#8b949e;">Produto não encontrado?</h3>
                        <p style="color:#666; margin-bottom:20px;">Você pode cadastrá-lo agora mesmo sem sair desta tela.</p>
                        <button onclick="document.getElementById('quickProductModal').style.display='flex'" class="btn" style="background:#9b59b6; color:#fff; padding:12px 25px;">➕ CADASTRAR NOVO PRODUTO</button>
                    </div>
                `;
                return;
            }

            products.forEach(p => {
                const img = p.image_path ? `../assets/uploads/${p.image_path}` : '../assets/no-image.png';
                const card = document.createElement('div');
                card.className = 'product-card';
                
                let velocityHtml = '';
                if(p.sales_30d >= 10) velocityHtml = `<div style="position:absolute; top:18px; left:18px; background:#e74c3c; color:#fff; padding:3px 8px; border-radius:6px; font-size:0.65rem; font-weight:bold;"><i class="fas fa-fire"></i> ALTO GIRO</div>`;

                card.innerHTML = `
                    <div class="stock-badge">Estoque: ${p.stock_qty}</div>
                    ${velocityHtml}
                    <img src="${img}">
                    <div class="p-name">${p.name}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div class="p-price">R$ ${parseFloat(p.cost_price).toFixed(2)}</div>
                        <button onclick="event.stopPropagation(); comparePrices(${p.id}, '${p.name.replace(/'/g, "\\'")}')" 
                                style="background:rgba(255,255,255,0.05); border:1px solid #30363d; color:#8b949e; font-size:0.6rem; padding:4px 8px; border-radius:4px; cursor:pointer;" 
                                title="Ver Histórico de Custo">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    </div>
                    <button class="btn-add-to-cart" onclick="event.stopPropagation(); addToCart(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                        <i class="fas fa-plus"></i> ADICIONAR
                    </button>
                `;
                card.onclick = () => addToCart(p);
                productResults.appendChild(card);
            });
        }

        function addToCart(p) {
            const existing = cart.find(item => item.id === p.id && item.var_id === p.var_id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push({
                    id: p.id,
                    var_id: p.var_id,
                    name: p.name,
                    price: parseFloat(p.cost_price) || 0,
                    qty: 1,
                    image: p.image_path,
                    original_cost: parseFloat(p.cost_price) || 0
                });
            }
            renderCart();
        }

        function renderCart() {
            cartList.innerHTML = '';
            let total = 0;
            cart.forEach((item, index) => {
                const img = item.image ? `../assets/uploads/${item.image}` : '../assets/no-image.png';
                const div = document.createElement('div');
                div.className = 'cart-item';
                
                const diff = item.price - item.original_cost;
                const diffColor = diff > 0 ? '#e74c3c' : (diff < 0 ? '#2ecc71' : '#888');
                const diffText = diff === 0 ? '' : (diff > 0 ? ` (+${diff.toFixed(2)})` : ` (${diff.toFixed(2)})`);

                div.innerHTML = `
                    <img src="${img}">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">
                            R$ <input type="number" step="0.01" value="${item.price}" onchange="updateItemPrice(${index}, this.value)">
                            <small style="color:${diffColor}; font-size:0.65rem;">${diffText}</small>
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                        <div class="cart-qty-ctrl">
                            <button onclick="updateItemQty(${index}, ${item.qty - 1})"><i class="fas fa-minus"></i></button>
                            <input type="number" value="${item.qty}" onchange="updateItemQty(${index}, this.value)">
                            <button onclick="updateItemQty(${index}, ${item.qty + 1})"><i class="fas fa-plus"></i></button>
                        </div>
                        <button onclick="removeFromCart(${index})" style="background:none; border:none; color:#e74c3c; cursor:pointer; font-size:0.8rem;"><i class="fas fa-trash"></i> Remover</button>
                    </div>
                `;
                cartList.appendChild(div);
                total += item.price * item.qty;
            });
            
            cartTotal.innerText = `R$ ${total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            document.getElementById('item-count').innerText = `${cart.length} itens`;
            checkValid();
        }

        function updateItemPrice(index, price) { cart[index].price = parseFloat(price); renderCart(); }
        function updateItemQty(index, qty) { 
            if (qty < 1) return;
            cart[index].qty = parseInt(qty); 
            renderCart(); 
        }
        function removeFromCart(index) { cart.splice(index, 1); renderCart(); }
        
        function checkValid() {
            btnSave.disabled = cart.length === 0 || supSelect.value === '';
        }
        supSelect.onchange = checkValid;

        function savePurchase() {
            if (!confirm('Confirmar entrada de mercadoria no estoque?')) return;
            
            btnSave.disabled = true;
            btnSave.innerText = 'PROCESSANDO...';
            
            const fd = new FormData();
            fd.append('action', 'save_purchase');
            fd.append('supplier_id', supSelect.value);
            fd.append('cart', JSON.stringify(cart));
            fd.append('total', cart.reduce((acc, i) => acc + (i.price * i.qty), 0));
            
            fetch('purchase_pos.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('successModal').style.display = 'flex';
                        document.getElementById('btn-print-now').onclick = () => window.open(`purchase-print.php?id=${data.id}`, '_blank');
                        document.getElementById('btn-lalamove-coleta').onclick = () => window.open(`lalamove.php?purchase_id=${data.id}`, '_self');
                    } else {
                        alert('Erro: ' + data.error);
                        btnSave.disabled = false;
                        btnSave.innerText = '📦 CONFIRMAR COMPRA';
                    }
                });
        }

        function comparePrices(pid, name) {
            document.getElementById('compareModal').style.display = 'flex';
            document.getElementById('compareTitle').innerText = `⚖️ Histórico: ${name}`;
            document.getElementById('compareResults').innerHTML = '<div style="text-align:center; padding:20px; color:#888;"><i class="fas fa-spinner fa-spin"></i> Buscando histórico...</div>';
            
            fetch(`purchase_pos.php?ajax_price_history=1&product_id=${pid}`)
                .then(r => r.json())
                .then(data => {
                    if (data.length === 0) {
                        document.getElementById('compareResults').innerHTML = '<div style="text-align:center; padding:20px; color:#888;">Nenhuma compra anterior registrada.</div>';
                        return;
                    }
                    
                    let html = `<table style="width:100%; border-collapse:collapse; margin-top:15px;">
                        <thead>
                            <tr style="border-bottom:1px solid #333; color:#8b949e; font-size:0.8rem;">
                                <th style="text-align:left; padding:10px;">Fornecedor</th>
                                <th style="text-align:center; padding:10px;">Data</th>
                                <th style="text-align:right; padding:10px;">Preço</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    
                    data.forEach(h => {
                        html += `<tr style="border-bottom:1px solid #21262d;">
                            <td style="padding:10px;"><strong>${h.supplier_name}</strong></td>
                            <td style="text-align:center; padding:10px; color:#888;">${new Date(h.created_at).toLocaleDateString()}</td>
                            <td style="text-align:right; padding:10px; color:#2ecc71; font-weight:bold;">R$ ${parseFloat(h.unit_cost).toFixed(2)}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table>';
                    document.getElementById('compareResults').innerHTML = html;
                });
        }
    // Quick Product Creation Handler
    document.getElementById('quickProductForm').onsubmit = function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const btn = this.querySelector('button');
        btn.disabled = true; btn.innerText = 'CADASTRANDO...';

        fetch('purchase_pos.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    addToCart(data.product);
                    document.getElementById('quickProductModal').style.display = 'none';
                    this.reset();
                } else {
                    alert('Erro: ' + data.error);
                }
                btn.disabled = false; btn.innerText = 'CADASTRAR E ADICIONAR';
            });
    };
    </script>
</body>
</html>
