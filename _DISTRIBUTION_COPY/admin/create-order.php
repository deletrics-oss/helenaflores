<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// Fetch Data for Selects
$users = $pdo->query("SELECT id, name, document FROM users ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, sku, price FROM products WHERE active = 1 ORDER BY name")->fetchAll();

// Handle Order Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int) $_POST['user_id'];
    $shipping = $_POST['shipping_method'];
    $items_raw = $_POST['items']; // Array of qty indexed by product_id e.g. [12 => 3, 5 => 1]

    // Filter out zero qty
    $items_to_add = [];
    $total = 0;

    foreach ($items_raw as $pid => $qty) {
        $qty = (int) $qty;
        if ($qty > 0) {
            // Fetch product price again for security/accuracy
            $curr_p = $pdo->query("SELECT * FROM products WHERE id = $pid")->fetch();

            // Default manual orders to standard price? or apply wholesale? 
            // Lets apply wholesale logic automatically if user wants overrides they can edit later?
            // For simplicity: auto-calculate.
            $price = $curr_p['price'];
            if ($curr_p['price_wholesale'] > 0 && $qty >= $curr_p['min_wholesale_qty']) {
                $price = $curr_p['price_wholesale'];
            }

            $subtotal = $price * $qty;
            $items_to_add[] = [
                'id' => $pid,
                'name' => $curr_p['name'],
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $subtotal
            ];
            $total += $subtotal;
        }
    }

    if (count($items_to_add) > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_method, created_at) VALUES (:uid, :total, 'pending', :ship, NOW())");
            $stmt->execute([':uid' => $user_id, ':total' => $total, ':ship' => $shipping]);
            $order_id = $pdo->lastInsertId();

            $stmt_i = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?,?,?,?,?,?)");
            foreach ($items_to_add as $i) {
                $stmt_i->execute([$order_id, $i['id'], $i['name'], $i['qty'], $i['price'], $i['subtotal']]);
            }

            $pdo->commit();
            header("Location: orders.php?success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro: " . $e->getMessage();
        }
    } else {
        $error = "Adicione pelo menos 1 item.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Criar Pedido Manual | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function toggleProduct(pid) {
            const el = document.getElementById('qty_' + pid);
            if (el.value === '' || el.value === '0') {
                el.value = 1;
                el.style.backgroundColor = '#d4edda';
            } else {
                // Keep it editable, don't remove unless manually cleared
            }
        }
    </script>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <h1>Criar Pedido Manual</h1>

        <?php if (isset($error)): ?>
            <div style="background:var(--danger); color:white; padding:1rem; margin-bottom:1rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-bottom:2rem;">
                <div>
                    <label>Cliente</label>
                    <select name="user_id" class="form-control" required style="width:100%; height:40px;">
                        <option value="">Selecione...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['name']); ?> (
                                <?php echo $u['document']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Forma de Envio</label>
                    <select name="shipping_method" class="form-control" style="width:100%; height:40px;">
                        <option value="A Combinar">A Combinar</option>
                        <option value="Transportadora">Transportadora</option>
                        <option value="Melhor Envio">Melhor Envio</option>
                        <option value="Motoboy">Motoboy</option>
                        <option value="Retirada">Retirada</option>
                    </select>
                </div>
            </div>

            <h3>Adicionar Produtos</h3>
            <div class="table-responsive" style="max-height:500px; overflow-y:auto; border:1px solid var(--border);">
                <table>
                    <thead>
                        <tr>
                            <th width="50">Qtd</th>
                            <th>Produto</th>
                            <th>Preço Base</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <input type="number" id="qty_<?php echo $p['id']; ?>"
                                        name="items[<?php echo $p['id']; ?>]" min="0" placeholder="0"
                                        style="width:60px; text-align:center;">
                                </td>
                                <td>
                                    <label for="qty_<?php echo $p['id']; ?>" style="cursor:pointer; display:block;">
                                        <strong>
                                            <?php echo htmlspecialchars($p['name']); ?>
                                        </strong><br>
                                        <small>
                                            <?php echo $p['sku']; ?>
                                        </small>
                                    </label>
                                </td>
                                <td>R$
                                    <?php echo number_format($p['price'], 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-success"
                style="margin-top:2rem; width:100%; padding:1rem; font-size:1.2rem;">CRIAR PEDIDO</button>
        </form>
    </div>

</body>

</html>