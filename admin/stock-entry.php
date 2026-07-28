<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$msg = '';

// Handle AJAX: Search Products
if (isset($_GET['search_product'])) {
    $q = "%" . $_GET['search_product'] . "%";
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.sku, v.id as var_id, v.value as var_val, v.sku as var_sku 
                           FROM products p 
                           LEFT JOIN product_variations v ON p.id = v.product_id 
                           WHERE p.name LIKE ? OR p.sku LIKE ? OR v.sku LIKE ? 
                           LIMIT 10");
    $stmt->execute([$q, $q, $q]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Handle AJAX: Search Suppliers
if (isset($_GET['search_supplier'])) {
    $q = "%" . $_GET['search_supplier'] . "%";
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE name LIKE ? OR cnpj LIKE ? LIMIT 5");
    $stmt->execute([$q, $q]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Handle POST: Create Supplier
if (isset($_POST['quick_supplier'])) {
    $name = $_POST['name'];
    $cnpj = $_POST['cnpj'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO suppliers (name, cnpj) VALUES (?, ?)");
    $stmt->execute([$name, $cnpj]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
    exit;
}

// Handle POST: Create Product (Basic)
if (isset($_POST['quick_product'])) {
    $name = $_POST['name'];
    $sku = $_POST['sku'] ?? 'PRO-' . rand(100, 999);
    $price = $_POST['price'] ?? 0;
    $stmt = $pdo->prepare("INSERT INTO products (name, sku, price, active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$name, $sku, $price]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
    exit;
}

// Handle POST: Finalize Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_entry'])) {
    try {
        $pdo->beginTransaction();

        $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
        $invoice = $_POST['invoice_number'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $items = json_decode($_POST['items_json'], true);

        if (empty($items))
            throw new Exception("Lista de itens vazia.");

        // 1. Create Entry Record
        $stmt = $pdo->prepare("INSERT INTO stock_entries (supplier_id, invoice_number, notes) VALUES (?, ?, ?)");
        $stmt->execute([$supplier_id, $invoice, $notes]);
        $entry_id = $pdo->lastInsertId();

        foreach ($items as $item) {
            $pid = $item['product_id'];
            $vid = !empty($item['variation_id']) ? $item['variation_id'] : null;
            $qty = (int) $item['qty'];
            $cost = (float) $item['cost'];

            // 2. Add Entry Item
            $stmt = $pdo->prepare("INSERT INTO stock_entry_items (entry_id, product_id, variation_id, qty, unit_cost) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$entry_id, $pid, $vid, $qty, $cost]);

            // 3. Update Product/Variation Stock
            if ($vid) {
                $pdo->prepare("UPDATE product_variations SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$qty, $vid]);
            } else {
                $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$qty, $pid]);
            }

            // 4. Log Movement
            $reason = "Entrada NFE #" . $invoice;
            $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, variation_id, type, qty, reason, entry_id) VALUES (?, ?, 'in', ?, ?, ?)");
            $stmt->execute([$pid, $vid, $qty, $reason, $entry_id]);
        }

        $pdo->commit();
        $msg = "<div class='alert alert-success'>✅ Entrada de estoque registrada com sucesso!</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "<div class='alert alert-error'>❌ Erro: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Entrada de Estoque | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .entry-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            padding: 2rem;
        }

        .panel {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .search-results {
            position: absolute;
            background: #1a1f26;
            border: 1px solid #444;
            width: 100%;
            z-index: 100;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            display: none;
        }

        .search-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #333;
        }

        .search-item:hover {
            background: #333;
        }

        .item-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .item-list th {
            text-align: left;
            background: #000;
            padding: 10px;
            color: var(--primary);
        }

        .item-list td {
            padding: 10px;
            border-bottom: 1px solid #333;
        }

        .qty-input {
            width: 70px;
            padding: 5px;
            text-align: center;
            background: #111;
            color: #fff;
            border: 1px solid #444;
        }

        .remove-btn {
            color: #fe6161;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1.2rem;
        }

        .summary-box {
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid #00e676;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 2rem;
        }

        #modal-quick {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 12px;
            width: 400px;
            border: 1px solid var(--primary);
        }
    </style>
</head>

<body class="admin-body">
    <?php include 'header.php'; ?>

    <div class="container">
        <h1>➕ Entrada de Estoque (NFE / Carga)</h1>
        <?php echo $msg; ?>

        <form id="main-entry-form" method="POST">
            <div class="entry-container">
                <!-- Sidebar: Supplier & NFE -->
                <div class="panel">
                    <h3>📄 Dados da Carga</h3>

                    <div style="position:relative; margin-bottom:1rem;">
                        <label>Fornecedor</label>
                        <input type="text" id="sup-search" placeholder="Buscar ou digite nome..." autocomplete="off">
                        <input type="hidden" name="supplier_id" id="sup-id">
                        <div id="sup-results" class="search-results"></div>
                        <button type="button" class="btn-sm" style="width:100%; margin-top:5px;"
                            onclick="openQuickModal('sup')">+ Novo Fornecedor</button>
                    </div>

                    <div>
                        <label>Número da NFE / NF</label>
                        <input type="text" name="invoice_number" placeholder="Ex: 000123" required>
                    </div>

                    <div style="margin-top:1rem;">
                        <label>Observações</label>
                        <textarea name="notes" rows="4" placeholder="Ex: Carga recebida por João..."></textarea>
                    </div>

                    <div class="summary-box">
                        <div style="font-size:0.9rem; opacity:0.8;">Total de Itens: <b id="total-qty">0</b></div>
                        <div style="font-size:1.2rem; font-weight:bold; color:#00e676; margin-top:5px;">VALOR TOTAL: R$
                            <span id="total-val">0,00</span></div>
                    </div>

                    <button type="submit" name="finalize_entry" class="btn"
                        style="width:100%; margin-top:1.5rem; height:60px; font-size:1.1rem;">✅ FINALIZAR
                        ENTRADA</button>
                    <input type="hidden" name="items_json" id="items-json">
                </div>

                <!-- Main: Items List -->
                <div class="panel">
                    <div style="display:flex; gap:1rem; align-items:flex-end;">
                        <div style="flex:1; position:relative;">
                            <label>Buscar Produto para Adicionar</label>
                            <input type="text" id="prod-search" placeholder="Nome, SKU ou Código de Barras..."
                                autocomplete="off" style="height:50px; font-size:1.1rem;">
                            <div id="prod-results" class="search-results"></div>
                        </div>
                        <button type="button" class="btn" style="height:50px; background:#219ebc;"
                            onclick="openQuickModal('prod')">🆕 Novo Produto</button>
                    </div>

                    <table class="item-list">
                        <thead>
                            <tr>
                                <th>Produto / Variação</th>
                                <th style="width:120px;">Qtd Entrada</th>
                                <th style="width:150px;">Custo Unit. (R$)</th>
                                <th style="width:150px;">Subtotal</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cart-rows">
                            <!-- Items go here -->
                        </tbody>
                    </table>

                    <div id="empty-msg" style="text-align:center; padding:3rem; opacity:0.3; font-style:italic;">
                        Nenhum item adicionado à carga ainda.
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Entry Modals -->
    <div id="modal-quick">
        <div class="modal-content">
            <h2 id="modal-title">Novo Fornecedor</h2>
            <div id="modal-form-fields"></div>
            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                <button class="btn" style="flex:1;" onclick="submitQuick()">Criar</button>
                <button class="btn btn-secondary" style="flex:1;" onclick="closeModal()">Cancelar</button>
            </div>
        </div>
    </div>

    <script>
        let items = [];

        // Search Product
        const prodSearch = document.getElementById('prod-search');
        const prodResults = document.getElementById('prod-results');

        prodSearch.addEventListener('input', function () {
            if (this.value.length < 2) { prodResults.style.display = 'none'; return; }
            fetch('?search_product=' + this.value)
                .then(r => r.json())
                .then(data => {
                    prodResults.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(p => {
                            const div = document.createElement('div');
                            div.className = 'search-item';
                            let label = p.name;
                            if (p.var_val) label += ' (<b>' + p.var_val + '</b>)';
                            div.innerHTML = `<div>${label}</div><small style="opacity:0.6">${p.sku || p.var_sku || '-'}</small>`;
                            div.onclick = () => addItem(p);
                            prodResults.appendChild(div);
                        });
                        prodResults.style.display = 'block';
                    } else {
                        prodResults.style.display = 'none';
                    }
                });
        });

        // Search Supplier
        const supSearch = document.getElementById('sup-search');
        const supResults = document.getElementById('sup-results');
        supSearch.addEventListener('input', function () {
            if (this.value.length < 2) { supResults.style.display = 'none'; return; }
            fetch('?search_supplier=' + this.value)
                .then(r => r.json())
                .then(data => {
                    supResults.innerHTML = '';
                    data.forEach(s => {
                        const div = document.createElement('div');
                        div.className = 'search-item';
                        div.innerHTML = `<b>${s.name}</b><br><small>${s.cnpj || ''}</small>`;
                        div.onclick = () => {
                            document.getElementById('sup-id').value = s.id;
                            supSearch.value = s.name;
                            supResults.style.display = 'none';
                        };
                        supResults.appendChild(div);
                    });
                    supResults.style.display = 'block';
                });
        });

        function addItem(p) {
            const key = p.id + '-' + (p.var_id || '0');
            const exists = items.find(i => (i.product_id + '-' + (i.variation_id || '0')) === key);

            if (!exists) {
                items.push({
                    product_id: p.id,
                    variation_id: p.var_id,
                    name: p.name + (p.var_val ? ' (' + p.var_val + ')' : ''),
                    qty: 1,
                    cost: 0
                });
            }
            renderCart();
            prodResults.style.display = 'none';
            prodSearch.value = '';
        }

        function renderCart() {
            const tbody = document.getElementById('cart-rows');
            const empty = document.getElementById('empty-msg');
            tbody.innerHTML = '';

            if (items.length === 0) { empty.style.display = 'block'; } else { empty.style.display = 'none'; }

            let totalVal = 0;
            let totalQty = 0;

            items.forEach((item, index) => {
                const sub = item.qty * item.cost;
                totalVal += sub;
                totalQty += parseInt(item.qty);

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.name}</td>
                    <td><input type="number" class="qty-input" value="${item.qty}" min="1" onchange="updateItem(${index}, 'qty', this.value)"></td>
                    <td><input type="number" class="qty-input" style="width:100px" value="${item.cost}" step="0.01" onchange="updateItem(${index}, 'cost', this.value)"></td>
                    <td style="font-weight:bold;">R$ ${sub.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                    <td><button type="button" class="remove-btn" onclick="removeItem(${index})">&times;</button></td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('total-qty').innerText = totalQty;
            document.getElementById('total-val').innerText = totalVal.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            document.getElementById('items-json').value = JSON.stringify(items);
        }

        function updateItem(index, key, val) {
            items[index][key] = val;
            renderCart();
        }

        function removeItem(index) {
            items.splice(index, 1);
            renderCart();
        }

        // Modals Logic
        let currentModalType = '';
        function openQuickModal(type) {
            currentModalType = type;
            const title = document.getElementById('modal-title');
            const fields = document.getElementById('modal-form-fields');
            fields.innerHTML = '';

            if (type === 'sup') {
                title.innerText = 'Novo Fornecedor';
                fields.innerHTML = `
                    <div style="background:#000; padding:10px; border-radius:8px; border:1px dashed var(--primary); margin-bottom:10px;">
                        <input type="text" id="ai_q_text" placeholder="Cole dados IA..." style="width:70%; padding:5px; background:#111; color:#fff; border:1px solid #333;">
                        <button type="button" onclick="runQuickAI('sup')" style="background:var(--primary); border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">✨ IA</button>
                    </div>
                    <label>Nome/Razão Social</label><input type="text" id="q-name" required>
                    <label>CNPJ</label><input type="text" id="q-cnpj">`;
            } else {
                title.innerText = 'Novo Produto Rápido';
                fields.innerHTML = `
                    <div style="background:#000; padding:10px; border-radius:8px; border:1px dashed var(--primary); margin-bottom:10px;">
                        <input type="text" id="ai_q_text" placeholder="Cole dados IA..." style="width:70%; padding:5px; background:#111; color:#fff; border:1px solid #333;">
                        <button type="button" onclick="runQuickAI('prod')" style="background:var(--primary); border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">✨ IA</button>
                    </div>
                    <label>Nome do Produto</label><input type="text" id="q-name" required>
                    <label>SKU (Opcional)</label><input type="text" id="q-sku">
                    <label>Preço Venda (R$)</label><input type="number" id="q-price" step="0.01">`;
            }
            document.getElementById('modal-quick').style.display = 'flex';
        }

        function runQuickAI(type) {
            const txt = document.getElementById('ai_q_text').value;
            if(!txt) return;
            const btn = event.target;
            btn.innerText = '...';
            
            const url = type === 'sup' ? 'supplier-import-ia.php' : 'product-edit.php';
            const fd = new FormData();
            fd.append('ajax_ai', '1');
            fd.append('text', txt);
            
            fetch(url, { method:'POST', body:fd })
            .then(r=>r.json())
            .then(d=>{
                if(type === 'sup' && d.length > 0) {
                    document.getElementById('q-name').value = d[0].name || '';
                    document.getElementById('q-cnpj').value = d[0].document || d[0].cnpj || '';
                } else if(type === 'prod') {
                    document.getElementById('q-name').value = d.name || '';
                    document.getElementById('q-sku').value = d.sku || '';
                    document.getElementById('q_price' in document ? 'q_price' : 'q-price').value = d.price || '';
                }
            })
            .finally(()=> btn.innerText = '✨ IA');
        }

        function submitQuick() {
            const name = document.getElementById('q-name').value;
            if (!name) return alert('Nome é obrigatório');

            const fd = new FormData();
            fd.append('name', name);
            if (currentModalType === 'sup') {
                fd.append('quick_supplier', 1);
                fd.append('cnpj', document.getElementById('q-cnpj').value);
            } else {
                fd.append('quick_product', 1);
                fd.append('sku', document.getElementById('q-sku').value);
                fd.append('price', document.getElementById('q-price').value);
            }

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeModal();
                        if (currentModalType === 'sup') {
                            document.getElementById('sup-id').value = data.id;
                            document.getElementById('sup-search').value = data.name;
                        } else {
                            addItem({ id: data.id, name: data.name });
                        }
                    }
                });
        }

        function closeModal() { document.getElementById('modal-quick').style.display = 'none'; }
    </script>
</body>

</html>