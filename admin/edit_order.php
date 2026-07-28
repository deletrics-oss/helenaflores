<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// Ensure schema is up to date
try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0.00"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN tracking_code VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_method VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00"); } catch (Exception $e) {}

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Fetch Order
$stmt = $pdo->prepare("SELECT o.*, u.name as user_name, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Pedido não encontrado.");
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $pdo->beginTransaction();

            if ($_POST['action'] === 'save_changes') {
                $items_qty = $_POST['items_qty'] ?? [];
                $items_price = $_POST['items_price'] ?? [];
                $add_products = $_POST['add_products'] ?? [];
                $status = $_POST['order_status'] ?? $order['status'];
                $shipping_method = $_POST['shipping_method'] ?? $order['shipping_method'];
                $tracking_code = trim($_POST['tracking_code'] ?? '');
                $shipping_cost = (float) ($_POST['shipping_cost'] ?? 0);
                
                $total_order = 0;

                // 1. Update/Remove existing items
                foreach ($items_qty as $item_id => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $order_id]);
                    } else {
                        $price = (float) ($items_price[$item_id] ?? 0);
                        $is_gift = isset($_POST['is_gift_existing'][$item_id]);
                        
                        $stmt_item = $pdo->prepare("SELECT product_name FROM order_items WHERE id = ?");
                        $stmt_item->execute([$item_id]);
                        $item_data = $stmt_item->fetch();
                        $pname = $item_data ? $item_data['product_name'] : 'Produto Desconhecido';
                        
                        if ($is_gift) {
                            $price = 0;
                            if (strpos($pname, '[BRINDE]') === false) $pname = '[BRINDE] ' . $pname;
                        } else {
                            $pname = str_replace('[BRINDE] ', '', $pname);
                        }

                        $subtotal = $price * $qty;
                        $pdo->prepare("UPDATE order_items SET quantity = ?, unit_price = ?, subtotal = ?, product_name = ? WHERE id = ?")->execute([$qty, $price, $subtotal, $pname, $item_id]);
                        $total_order += $subtotal;
                    }
                }

                // 2. Add new products
                foreach ($add_products as $pid => $qty) {
                    $qty = (int) $qty;
                    if ($qty > 0) {
                        $p = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                        $p->execute([$pid]);
                        $prod = $p->fetch();

                        if ($prod) {
                            $price = isset($_POST['add_products_prices'][$pid]) ? (float)$_POST['add_products_prices'][$pid] : $prod['price'];
                            
                            $pname = $prod['name'];
                            if (isset($_POST['is_gift_new'][$pid])) {
                                $price = 0;
                                if (strpos($pname, '[BRINDE]') === false) {
                                    $pname = '[BRINDE] ' . $pname;
                                }
                            }
                            
                            $subtotal = $price * $qty;
                            $stmt_ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?,?,?,?,?,?)");
                            $stmt_ins->execute([$order_id, $pid, $pname, $qty, $price, $subtotal]);
                            $total_order += $subtotal;
                        }
                    }
                }

                $discount = (float) ($_POST['discount_amount'] ?? 0);
                $final_total = max(0, $total_order - $discount) + $shipping_cost;

                $pdo->prepare("UPDATE orders SET total_amount = ?, discount_amount = ?, status = ?, shipping_method = ?, tracking_code = ?, shipping_cost = ?, updated_at = NOW() WHERE id = ?")->execute([$final_total, $discount, $status, $shipping_method, $tracking_code, $shipping_cost, $order_id]);

                // Sync dynamic current_debt for the user
                $userId = (int)$order['user_id'];
                if ($userId > 0) {
                    // Sync customer payments for this order status/total change
                    if ($status === 'paid') {
                        $pay_stmt = $pdo->prepare("SELECT id FROM customer_payments WHERE user_id = ? AND (description LIKE ? OR description LIKE ?)");
                        $pay_stmt->execute([$userId, "%Pedido #$order_id%", "%Pedido PDV #$order_id%"]);
                        $pay_id = $pay_stmt->fetchColumn();
                        
                        if ($pay_id) {
                            $pdo->prepare("UPDATE customer_payments SET amount = ? WHERE id = ?")->execute([$final_total, $pay_id]);
                        } else {
                            $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, 'Saldo/Manual', ?)")
                                ->execute([$userId, $final_total, "Pagamento do Pedido #$order_id"]);
                        }
                    } else {
                        // Delete automatic payment if status is not paid
                        $pdo->prepare("DELETE FROM customer_payments WHERE user_id = ? AND (description LIKE ? OR description LIKE ?)")
                            ->execute([$userId, "%Pedido #$order_id%", "%Pedido PDV #$order_id%"]);
                    }

                    $pdo->prepare("UPDATE users u SET current_debt = (
                        (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                        (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
                    ) WHERE id = ?")->execute([$userId]);
                }
            }

            $pdo->commit();
            header("Location: edit_order.php?id=$order_id&success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Fetch Items
$stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll();

// Fetch All Products for addition
$products = $pdo->query("SELECT id, name, sku, price, price_wholesale, min_wholesale_qty, image_path FROM products WHERE active = 1 ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Pedido #<?php echo $order_id; ?> | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #0f131a;
            --surface: #1a1e26;
            --border: #2c3e50;
            --primary: #f1c40f;
            --text: #ecf0f1;
        }
        body { background: var(--bg); color: var(--text); }
        .edit-card { background: var(--surface); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 2rem; }
        .qty-input { width: 70px; padding: 8px; background: #111; color: #fff; border: 1px solid #444; border-radius: 6px; text-align: center; }
        .price-input { width: 100px; padding: 8px; background: #111; color: #fff; border: 1px solid #444; border-radius: 6px; }
        
        /* Gift Checkbox Styling (Fixed giant button) */
        .gift-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #f1c40f;
            cursor: pointer;
            background: rgba(241, 196, 15, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid rgba(241, 196, 15, 0.3);
            transition: all 0.2s;
            margin-top: 5px;
            width: fit-content;
        }
        .gift-label:hover { background: rgba(241, 196, 15, 0.2); }
        .gift-label input { width: 14px !important; height: 14px !important; margin: 0; cursor: pointer; }

        .table-responsive { border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #252a33; padding: 12px; text-align: left; font-size: 0.85rem; color: #888; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #222; background: #1a1e26; }
        
        .total-box { background: #111; padding: 1.5rem; border-radius: 12px; border: 1px solid #333; margin-top: 2rem; display: flex; justify-content: space-between; align-items: flex-end; }
        .btn-save { background: #2ecc71; color: #000; font-weight: 800; padding: 12px 30px; border-radius: 8px; border: none; cursor: pointer; font-size: 1rem; transition: transform 0.2s; }
        .btn-save:hover { transform: scale(1.02); background: #27ae60; }
        
        .search-input { width: 100%; padding: 12px; background: #111; color: #fff; border: 1px solid #444; border-radius: 8px; margin-bottom: 1rem; }
        .product-list { max-height: 400px; overflow-y: auto; border: 1px solid #333; border-radius: 8px; }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .status-pending { background: rgba(241,196,15,0.1); color: #f1c40f; }
        .status-paid { background: rgba(46,204,113,0.1); color: #2ecc71; }
        .status-shipped { background: rgba(52,152,219,0.1); color: #3498db; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem; max-width: 1100px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <div>
                <h1 style="margin:0;">Editar Pedido #<?php echo $order_id; ?></h1>
                <p style="color:#666; margin:5px 0 0;">Gerencie itens, descontos e status do pedido.</p>
            </div>
            <a href="orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" style="margin-bottom:1.5rem;"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" style="margin-bottom:1.5rem;"><i class="fas fa-check-circle"></i> Pedido atualizado com sucesso!</div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save_changes">

            <!-- CONFIGURAÇÕES DO PEDIDO -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
                <div class="edit-card" style="margin-bottom:0;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold; color:#888; font-size:0.75rem; text-transform:uppercase;">Status do Pedido</label>
                    <select name="order_status" style="width:100%; padding:10px; background:#111; color:#fff; border:1px solid #444; border-radius:6px;">
                        <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>⏳ Pendente</option>
                        <option value="paid" <?php echo $order['status']=='paid'?'selected':''; ?>>✅ Pago</option>
                        <option value="shipped" <?php echo $order['status']=='shipped'?'selected':''; ?>>📦 Enviado</option>
                        <option value="canceled" <?php echo $order['status']=='canceled'?'selected':''; ?>>❌ Cancelado</option>
                    </select>
                </div>
                <div class="edit-card" style="margin-bottom:0;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold; color:#888; font-size:0.75rem; text-transform:uppercase;">Envio & Rastreio</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="shipping_method" placeholder="Método (ex: SEDEX)" value="<?php echo htmlspecialchars($order['shipping_method']??''); ?>" style="flex:1; padding:10px; background:#111; color:#fff; border:1px solid #444; border-radius:6px;">
                        <input type="text" name="tracking_code" placeholder="Código Rastreio" value="<?php echo htmlspecialchars($order['tracking_code']??''); ?>" style="flex:1; padding:10px; background:#111; color:#fff; border:1px solid #444; border-radius:6px;">
                    </div>
                </div>
            </div>

            <!-- ITENS ATUAIS -->
            <h3 style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <span><i class="fas fa-shopping-cart"></i> Itens do Pedido</span>
                <span style="font-size:1.1rem; color:#2ecc71;" id="live_total_top">Total: R$ <?php echo number_format($order['total_amount'],2,',','.'); ?></span>
            </h3>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th width="150">Preço Unit.</th>
                            <th width="100">Qtd</th>
                            <th width="150" style="text-align:right">Subtotal</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="orderItemsTable">
                        <?php foreach ($items as $item): ?>
                            <tr data-product-id="<?php echo $item['product_id']; ?>">
                                <td>
                                    <div style="font-weight:bold;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div style="font-size:0.7rem; color:#666;">ID: #<?php echo $item['product_id']; ?></div>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:5px;">
                                        <span style="color:#666; font-size:0.8rem;">R$</span>
                                        <input type="number" step="0.01" name="items_price[<?php echo $item['id']; ?>]" value="<?php echo number_format($item['unit_price'], 2, '.', ''); ?>" class="price-input price-val" oninput="recalcTotals()">
                                    </div>
                                    <label class="gift-label">
                                        <input type="checkbox" name="is_gift_existing[<?php echo $item['id']; ?>]" value="1" onchange="toggleGiftInput(this)" <?php echo (strpos($item['product_name'], '[BRINDE]') !== false) ? 'checked' : ''; ?>> 🎁 Brinde
                                    </label>
                                </td>
                                <td>
                                    <input type="number" name="items_qty[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" class="qty-input qty-val" min="0" oninput="recalcTotals()">
                                </td>
                                <td style="text-align:right; font-weight:bold;" class="item-subtotal">
                                    R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="javascript:void(0)" onclick="if(confirm('Remover item?')) { this.closest('tr').querySelector('.qty-val').value = 0; recalcTotals(); this.closest('tr').style.opacity='0.3'; }" class="btn-sm" style="background:#e74c3c; color:#fff;"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- RESUMO E DESCONTO -->
            <div class="total-box">
                <div style="flex:1;">
                    <h3 style="color:#f1c40f; margin:0 0 1rem;"><i class="fas fa-percent"></i> Ajustes Financeiros</h3>
                    <div style="display:flex; gap:2rem;">
                        <div>
                            <label style="display:block; font-size:0.7rem; color:#888; text-transform:uppercase; margin-bottom:5px;">Desconto (R$)</label>
                            <input type="number" name="discount_amount" id="f_discount" step="0.01" min="0" value="<?php echo number_format($order['discount_amount'] ?? 0, 2, '.', ''); ?>" class="price-input" style="font-size:1.1rem; font-weight:bold; color:#e74c3c;" oninput="recalcTotals()">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; color:#888; text-transform:uppercase; margin-bottom:5px;">Frete (R$)</label>
                            <input type="number" step="0.01" min="0" name="shipping_cost" id="f_shipcost" value="<?php echo number_format($order['shipping_cost']??0,2,'.',''); ?>" class="price-input" style="font-size:1.1rem; font-weight:bold; color:#3498db;" oninput="recalcTotals()">
                        </div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="color:#666; font-size:0.9rem; margin-bottom:5px;">Subtotal Itens: <span id="live_subtotal" style="color:#fff;">R$ 0,00</span></div>
                    <div style="color:#666; font-size:0.9rem; margin-bottom:5px;">Desconto: <span id="live_discount" style="color:#e74c3c;">- R$ 0,00</span></div>
                    <div style="color:#666; font-size:0.9rem; margin-bottom:8px;">Frete: <span id="live_frete" style="color:#3498db;">+ R$ 0,00</span></div>
                    <div style="font-size:1.8rem; font-weight:900; color:#2ecc71;">TOTAL: <span id="live_total_bottom">R$ 0,00</span></div>
                </div>
            </div>

            <!-- ADICIONAR PRODUTOS -->
            <div style="margin-top:3rem;">
                <h3><i class="fas fa-plus-circle"></i> Adicionar Novos Produtos</h3>
                <input type="text" id="productSearch" class="search-input" placeholder="Pesquisar por nome ou SKU..." onkeyup="filterProducts()">

                <div class="product-list">
                    <table>
                        <tbody id="productList">
                            <?php foreach ($products as $p): ?>
                                <tr class="product-row">
                                    <td width="60">
                                        <?php if(!empty($p['image_path'])): ?>
                                        <img src="../assets/uploads/<?php echo $p['image_path']; ?>" style="width:45px; height:45px; object-fit:cover; border-radius:6px;">
                                        <?php else: ?>
                                        <div style="width:45px; height:45px; background:#222; display:flex; align-items:center; justify-content:center; border-radius:6px;"><i class="fas fa-box" style="opacity:0.3"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:bold;"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div style="font-size:0.75rem; color:#666;">SKU: <?php echo $p['sku']; ?> | R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></div>
                                    </td>
                                    <td width="120" style="text-align:right;">
                                        <button type="button" class="btn btn-sm" style="background:#2ecc71; color:#000; font-weight:bold; padding:6px 12px; border-radius:6px; cursor:pointer;" onclick="addProductToOrder(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['price']; ?>)">
                                            <i class="fas fa-plus"></i> Adicionar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top:3rem; padding:2rem; background: rgba(46,204,113,0.05); border: 1px dashed #2ecc71; border-radius: 12px; text-align:center;">
                <p style="margin-bottom:1.5rem; color:#aaa;">Revise todas as informações antes de salvar. As alterações afetarão o estoque e o financeiro.</p>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> SALVAR ALTERAÇÕES DO PEDIDO</button>
            </div>
        </form>
    </div>

    <script>
        function filterProducts() {
            const filter = document.getElementById("productSearch").value.toUpperCase();
            const rows = document.querySelectorAll(".product-row");
            rows.forEach(row => {
                const text = row.textContent || row.innerText;
                row.style.display = text.toUpperCase().includes(filter) ? "" : "none";
            });
        }
        
        function toggleGiftInput(checkbox) {
            const row = checkbox.closest('tr');
            const priceInput = row.querySelector('.price-val');
            if (checkbox.checked) {
                priceInput.dataset.oldPrice = priceInput.value;
                priceInput.value = '0.00';
                priceInput.readOnly = true;
            } else {
                priceInput.value = priceInput.dataset.oldPrice || '0.00';
                priceInput.readOnly = false;
            }
            recalcTotals();
        }

        function recalcTotals() {
            let subtotal = 0;
            document.querySelectorAll('#orderItemsTable tr').forEach(tr => {
                const qtyInput = tr.querySelector('.qty-val');
                const priceInput = tr.querySelector('.price-val');
                if (qtyInput && priceInput) {
                    const q = parseInt(qtyInput.value) || 0;
                    const p = parseFloat(priceInput.value) || 0;
                    const sub = q * p;
                    subtotal += sub;
                    const subEl = tr.querySelector('.item-subtotal');
                    if(subEl) subEl.innerText = 'R$ ' + sub.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
                }
            });

            const discount = parseFloat(document.getElementById('f_discount').value) || 0;
            const ship = parseFloat(document.getElementById('f_shipcost').value) || 0;
            const finalTotal = Math.max(0, subtotal - discount) + ship;

            document.getElementById('live_subtotal').innerText = 'R$ ' + subtotal.toLocaleString('pt-BR', {minimumFractionDigits:2});
            document.getElementById('live_discount').innerText = '- R$ ' + discount.toLocaleString('pt-BR', {minimumFractionDigits:2});
            document.getElementById('live_frete').innerText = '+ R$ ' + ship.toLocaleString('pt-BR', {minimumFractionDigits:2});
            
            const fmtTotal = 'R$ ' + finalTotal.toLocaleString('pt-BR', {minimumFractionDigits:2});
            document.getElementById('live_total_bottom').innerText = fmtTotal;
            document.getElementById('live_total_top').innerText = 'Total: ' + fmtTotal;
        }

        function addProductToOrder(productId, productName, price) {
            let existingRow = document.querySelector(`#orderItemsTable tr[data-product-id="${productId}"]`);
            if (existingRow) {
                let qtyInput = existingRow.querySelector('.qty-val');
                qtyInput.value = (parseInt(qtyInput.value) || 0) + 1;
                existingRow.style.opacity = '1';
                recalcTotals();
                return;
            }

            const tableBody = document.getElementById('orderItemsTable');
            const newRow = document.createElement('tr');
            newRow.setAttribute('data-product-id', productId);
            newRow.className = 'new-order-item';
            newRow.innerHTML = `
                <td>
                    <div style="font-weight:bold;">${productName}</div>
                    <div style="font-size:0.7rem; color:#888;">ID: #${productId} (Novo)</div>
                </td>
                <td>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <span style="color:#666; font-size:0.8rem;">R$</span>
                        <input type="number" step="0.01" name="add_products_prices[${productId}]" value="${parseFloat(price).toFixed(2)}" class="price-input price-val" oninput="recalcTotals()">
                    </div>
                    <label class="gift-label">
                        <input type="checkbox" name="is_gift_new[${productId}]" value="1" class="add-gift-check" onchange="toggleGiftInput(this)"> 🎁 Brinde
                    </label>
                </td>
                <td>
                    <input type="number" name="add_products[${productId}]" value="1" class="qty-input qty-val" min="0" oninput="recalcTotals()">
                </td>
                <td style="text-align:right; font-weight:bold;" class="item-subtotal">
                    R$ ${parseFloat(price).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </td>
                <td style="text-align:center;">
                    <a href="javascript:void(0)" onclick="this.closest('tr').remove(); recalcTotals();" class="btn-sm" style="background:#e74c3c; color:#fff;"><i class="fas fa-trash"></i></a>
                </td>
            `;
            tableBody.appendChild(newRow);
            recalcTotals();
        }

        document.addEventListener('DOMContentLoaded', recalcTotals);
    </script>
</body>
</html>