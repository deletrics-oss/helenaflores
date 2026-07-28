<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

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
                $items_qty = $_POST['items_qty'] ?? []; // Existing items [item_id => qty]
                $add_products = $_POST['add_products'] ?? []; // New products [product_id => qty]

                $total_order = 0;
                $has_changes = false;

                // 1. Update/Remove existing items
                foreach ($items_qty as $item_id => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $order_id]);
                        $has_changes = true;
                    } else {
                        $stmt_item = $pdo->prepare("SELECT unit_price FROM order_items WHERE id = ?");
                        $stmt_item->execute([$item_id]);
                        $item_data = $stmt_item->fetch();

                        $subtotal = $item_data['unit_price'] * $qty;
                        $pdo->prepare("UPDATE order_items SET quantity = ?, subtotal = ? WHERE id = ?")->execute([$qty, $subtotal, $item_id]);
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
                            $price = $prod['price'];
                            // Apply wholesale if applicable
                            if ($prod['price_wholesale'] > 0 && $qty >= $prod['min_wholesale_qty']) {
                                $price = $prod['price_wholesale'];
                            }
                            $subtotal = $price * $qty;

                            $stmt_ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?,?,?,?,?,?)");
                            $stmt_ins->execute([$order_id, $pid, $prod['name'], $qty, $price, $subtotal]);
                            $total_order += $subtotal;
                            $has_changes = true;
                        }
                    }
                }

                // 3. Update Order Total and Status
                // If order was paid but total increased, mark as pending to allow client to pay more?
                // Or just keep current status if it's already paid? 
                // Decision: if any item ADDED and it was 'paid' or 'shipped', maybe just leave total updated.
                // But if it's 'pending', definitely update total.
                $new_status = $order['status'];
                if ($has_changes && $order['status'] === 'paid') {
                    // Keep as paid but updated total? Usually better to ask, but for now let's just update total.
                }

                $pdo->prepare("UPDATE orders SET total_amount = ?, updated_at = NOW() WHERE id = ?")->execute([$total_order, $order_id]);

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
$products = $pdo->query("SELECT id, name, sku, price FROM products WHERE active = 1 ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Editar Pedido #
        <?php echo $order_id; ?>
    </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .edit-card {
            background: #222;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #333;
            margin-bottom: 2rem;
        }

        .qty-input {
            width: 60px;
            padding: 5px;
            background: #111;
            color: #fff;
            border: 1px solid #444;
            border-radius: 4px;
            text-align: center;
        }

        .remove-btn {
            color: #ff4d4d;
            cursor: pointer;
            text-decoration: none;
            font-size: 1.2rem;
        }

        .add-section {
            margin-top: 2rem;
            border-top: 1px solid #444;
            padding-top: 1rem;
        }

        #productSearch {
            width: 100%;
            padding: 10px;
            margin-bottom: 1rem;
            background: #111;
            color: #fff;
            border: 1px solid #444;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Editar Pedido #
                <?php echo $order_id; ?>
            </h1>
            <a href="orders.php" class="btn btn-secondary">Voltar</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" style="margin-top:1rem;">✅ Pedido atualizado com sucesso!</div>
        <?php endif; ?>

        <div class="edit-card">
            <p><strong>Cliente:</strong>
                <?php echo htmlspecialchars($order['user_name']); ?> (
                <?php echo $order['email']; ?>)
            </p>
            <p><strong>Status Atual:</strong>
                <?php echo strtoupper($order['status']); ?>
            </p>
            <p><strong>Data:</strong>
                <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
            </p>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_changes">

            <h3>Itens do Pedido</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th width="100">Preço Unit.</th>
                            <th width="100">Qtd</th>
                            <th width="100">Subtotal</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </td>
                                <td>R$
                                    <?php echo number_format($item['unit_price'], 2, ',', '.'); ?>
                                </td>
                                <td>
                                    <input type="number" name="items_qty[<?php echo $item['id']; ?>]"
                                        value="<?php echo $item['quantity']; ?>" class="qty-input" min="0">
                                </td>
                                <td>R$
                                    <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="javascript:void(0)"
                                        onclick="if(confirm('Remover este item?')) { this.closest('tr').querySelector('.qty-input').value = 0; this.closest('form').submit(); }"
                                        class="remove-btn">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="add-section">
                <h3>➕ Adicionar Novos Produtos</h3>
                <input type="text" id="productSearch" placeholder="Buscar produto pelo nome ou SKU..."
                    onkeyup="filterProducts()">

                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #333; border-radius: 4px;">
                    <table id="productTable">
                        <tbody id="productList">
                            <?php foreach ($products as $p): ?>
                                <tr class="product-row">
                                    <td style="padding:10px;">
                                        <strong>
                                            <?php echo htmlspecialchars($p['name']); ?>
                                        </strong><br>
                                        <small>
                                            <?php echo $p['sku']; ?> - R$
                                            <?php echo number_format($p['price'], 2, ',', '.'); ?>
                                        </small>
                                    </td>
                                    <td width="100" style="text-align:right; padding-right:10px;">
                                        <input type="number" name="add_products[<?php echo $p['id']; ?>]" placeholder="Qtd"
                                            min="0" class="qty-input">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top:2rem; text-align:right;">
                <button type="submit" class="btn btn-success" style="padding:1rem 2rem; font-size:1.1rem;">SALVAR
                    ALTERAÇÕES</button>
            </div>
        </form>
    </div>

    <script>
        function filterProducts() {
            var input = document.getElementById("productSearch");
            var filter = input.value.toUpperCase();
            var rows = document.querySelectorAll(".product-row");

            rows.forEach(function (row) {
                var text = row.textContent || row.innerText;
                if (text.toUpperCase().indexOf(filter) > -1) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>
</body>

</html>