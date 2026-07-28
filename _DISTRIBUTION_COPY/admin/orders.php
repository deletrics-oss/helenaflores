<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $oid = $_POST['order_id'];
        $status = $_POST['status'];
        $track = $_POST['tracking_code'] ?? null;

        $sql = "UPDATE orders SET status = :st, tracking_code = :track WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':st' => $status, ':track' => $track, ':id' => $oid]);

        // --- NOTIFICATIONS ---

        // 1. Fetch User Info for Notification
        $uStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = (SELECT user_id FROM orders WHERE id = ?)");
        $uStmt->execute([$oid]);
        $user = $uStmt->fetch();

        if ($user) {
            $msgTitle = "";
            $msgBody = "";
            $wppMsg = "";

            if ($status == 'shipped' && !empty($track)) {
                $msgTitle = "Seu Pedido #$oid foi Enviado! 🚚";
                $msgBody = "Olá {$user['name']},\n\nSeu pedido #$oid já está a caminho!\n\n📦 Código de Rastreio: $track\n\nAcompanhe a entrega no site da transportadora.\n\nObrigado por comprar na Fight Arcade!";
                $wppMsg = "Olá {$user['name']}! Seu pedido Fight Arcade #$oid foi enviado. 📦 Rastreio: $track";
            } elseif ($status == 'paid') {
                $msgTitle = "Pagamento Aprovado - Pedido #$oid ✅";
                $msgBody = "Olá {$user['name']},\n\nO pagamento do seu pedido #$oid foi confirmado. Estamos preparando tudo para o envio!\n\nObrigado!";
                $wppMsg = "Olá {$user['name']}! Pagamento do pedido #$oid confirmado. Já estamos preparando o envio! 🚀";
            }

            // Send Email (Basic PHP Mail)
            if (!empty($msgTitle) && !empty($user['email'])) {
                $headers = "From: contato@fightarcade.com.br\r\n";
                $headers .= "Reply-To: contato@fightarcade.com.br\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                @mail($user['email'], $msgTitle, $msgBody, $headers);
            }

            // Redirect with WhatsApp Trigger if applicable
            if (!empty($wppMsg)) {
                $phone = preg_replace('/\D/', '', $user['phone']);
                $wppUrl = "https://api.whatsapp.com/send?phone=55$phone&text=" . urlencode($wppMsg);

                // Show a standard page but with a Script to open WPP in new tab
                header("Location: orders.php?msg=updated&wpp=" . urlencode($wppUrl));
                exit;
            }
        }
    }
    header("Location: orders.php?msg=updated");
    exit;
}

// 2. CLONE ORDER
if (isset($_GET['clone_order'])) {
    $oid = (int) $_GET['clone_order'];
    // 1. Fetch Order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$oid]);
    $orgOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($orgOrder) {
        // 2. Duplicate Order
        unset($orgOrder['id']);
        $orgOrder['created_at'] = date('Y-m-d H:i:s');
        $orgOrder['status'] = 'pending';
        $orgOrder['tracking_code'] = NULL;
        $orgOrder['shipping_method'] .= ' (Cópia)';

        $cols = array_keys($orgOrder);
        $vals = array_values($orgOrder);
        $placeholders = str_repeat('?,', count($cols) - 1) . '?';

        $sql = "INSERT INTO orders (" . implode(',', $cols) . ") VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($vals);
        $newId = $pdo->lastInsertId();

        // 3. Duplicate Items
        $items = $pdo->query("SELECT * FROM order_items WHERE order_id = $oid")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            unset($item['id']);
            $item['order_id'] = $newId;

            $iCols = array_keys($item);
            $iVals = array_values($item);
            $iPlaceholders = str_repeat('?,', count($iCols) - 1) . '?';

            $iSql = "INSERT INTO order_items (" . implode(',', $iCols) . ") VALUES ($iPlaceholders)";
            $pdo->prepare($iSql)->execute($iVals);
        }

        header("Location: orders.php?msg=cloned");
        exit;
    }
}

// 3. BULK DELETE
if (isset($_POST['bulk_delete_orders']) && !empty($_POST['selected_orders'])) {
    $ids = implode(',', array_map('intval', $_POST['selected_orders']));
    $pdo->query("DELETE FROM orders WHERE id IN ($ids)");
    header("Location: orders.php?msg=bulk_deleted");
    exit;
}

// Handle Export Single Order (CSV for Bling/Tiny)
if (isset($_GET['export_order'])) {
    $oid = $_GET['export_order'];
    $stmt = $pdo->prepare("SELECT o.*, u.name, u.document, u.email, u.phone, u.zipcode, u.address, u.number, u.neighborhood, u.city, u.state FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$oid]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pedido_' . $oid . '_bling_tiny.csv"');
        $out = fopen('php://output', 'w');

        // Header (Standard B2B Columns)
        fputcsv($out, ['Numero', 'Data', 'Cliente', 'CPF_CNPJ', 'Email', 'Telefone', 'CEP', 'Endereco', 'Numero', 'Bairro', 'Cidade', 'UF', 'Total', 'Frete', 'Itens']);

        // Logic to flatten items
        $items = $pdo->query("SELECT * FROM order_items WHERE order_id = $oid")->fetchAll(PDO::FETCH_ASSOC);
        $itemsStr = "";
        foreach ($items as $i) {
            $itemsStr .= "{$i['quantity']}x {$i['product_name']} | ";
        }

        fputcsv($out, [
            $order['id'],
            $order['created_at'],
            $order['name'],
            $order['document'],
            $order['email'],
            $order['phone'],
            $order['zipcode'],
            $order['address'],
            $order['number'],
            $order['neighborhood'],
            $order['city'],
            $order['state'],
            $order['total_amount'],
            $order['shipping_cost'] ?? 0,
            $itemsStr
        ]);
        fclose($out);
        exit;
    }
}

// Filters
$f_state = $_GET['f_state'] ?? '';
$f_city = $_GET['f_city'] ?? '';
$f_ship = $_GET['f_ship'] ?? '';
$f_sort = $_GET['f_sort'] ?? 'date_desc';

// Build Query
$sql = "SELECT o.*, u.name as user_name, u.phone, u.email, u.city, u.state, u.zipcode 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE 1=1";

$params = [];

if ($f_state) {
    $sql .= " AND u.state = :state";
    $params[':state'] = $f_state;
}

if ($f_city) {
    $sql .= " AND u.city LIKE :city";
    $params[':city'] = "%$f_city%";
}

if ($f_ship) {
    if ($f_ship == 'pickup') {
        $sql .= " AND o.shipping_method LIKE '%Retirada%'";
    } elseif ($f_ship == 'delivery') {
        $sql .= " AND o.shipping_method NOT LIKE '%Retirada%'";
    }
}

// Sorting
switch ($f_sort) {
    case 'date_asc':
        $sql .= " ORDER BY o.created_at ASC";
        break;
    case 'val_desc':
        $sql .= " ORDER BY o.total_amount DESC";
        break;
    case 'val_asc':
        $sql .= " ORDER BY o.total_amount ASC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY o.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Pedidos | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByName('selected_orders[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        
        <!-- MESSAGES -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'cloned'): ?>
                <div class="alert alert-success">✅ Pedido clonado com sucesso!</div>
            <?php elseif ($_GET['msg'] == 'bulk_deleted'): ?>
                <div class="alert alert-success">🗑️ Pedidos excluídos.</div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h1>Todos os Pedidos</h1>
            <a href="create-order.php" class="btn">Criar Pedido Manual</a>
        </div>

        <!-- FILTERS -->
        <form method="GET"
            style="background:#222; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; border:1px solid #333;">
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Estado (UF)</label>
                <select name="f_state"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
                    <option value="">Todos</option>
                    <option value="SP" <?php echo ($f_state == 'SP') ? 'selected' : ''; ?>>SP</option>
                    <option value="RJ" <?php echo ($f_state == 'RJ') ? 'selected' : ''; ?>>RJ</option>
                    <option value="MG" <?php echo ($f_state == 'MG') ? 'selected' : ''; ?>>MG</option>
                    <option value="RS" <?php echo ($f_state == 'RS') ? 'selected' : ''; ?>>RS</option>
                    <option value="PR" <?php echo ($f_state == 'PR') ? 'selected' : ''; ?>>PR</option>
                    <!-- Add more as needed -->
                </select>
            </div>
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Cidade</label>
                <input type="text" name="f_city" placeholder="Ex: São Paulo"
                    value="<?php echo htmlspecialchars($f_city); ?>"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
            </div>
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Tipo de Envio</label>
                <select name="f_ship"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
                    <option value="">Todos</option>
                    <option value="pickup" <?php echo ($f_ship == 'pickup') ? 'selected' : ''; ?>>Retirada (Loja)</option>
                    <option value="delivery" <?php echo ($f_ship == 'delivery') ? 'selected' : ''; ?>>Envio
                        (Correios/Transp)
                    </option>
                </select>
            </div>
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Filtro (Ranking)</label>
                <select name="f_sort"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
                    <option value="date_desc" <?php echo ($f_sort == 'date_desc') ? 'selected' : ''; ?>>Mais Recentes
                    </option>
                    <option value="date_asc" <?php echo ($f_sort == 'date_asc') ? 'selected' : ''; ?>>Mais Antigos
                    </option>
                    <option value="val_desc" <?php echo ($f_sort == 'val_desc') ? 'selected' : ''; ?>>💰 Maior Valor (VIP)
                    </option>
                    <option value="val_asc" <?php echo ($f_sort == 'val_asc') ? 'selected' : ''; ?>>Menor Valor</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-sm"
                    style="background:var(--primary); color:#000; padding:10px 15px; border:none; border-radius:4px; font-weight:bold;">🔍
                    Filtrar</button>
                <a href="orders.php" class="btn-sm"
                    style="background:#555; color:#fff; padding:10px 15px; margin-left:5px; text-decoration:none; border-radius:4px;">Limpar</a>
            </div>
        </form>

        <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir os pedidos selecionados?');">
            
            <div style="margin-bottom:10px;">
                <button type="submit" name="bulk_delete_orders" class="btn btn-danger" value="1">🗑️ Excluir Selecionados</button>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Cliente / Endereço</th>
                            <th>Envio</th>
                            <th>Total</th>
                            <th>Status / Rastreio</th>
                            <th>Itens</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o):
                            // Get items
                            $items = $pdo->query("SELECT * FROM order_items WHERE order_id = {$o['id']}")->fetchAll();
                            ?>
                            <tr>
                                <td><input type="checkbox" name="selected_orders[]" value="<?php echo $o['id']; ?>"></td>
                                <td>#<?php echo $o['id']; ?></td>
                                <td><?php echo date('d/m H:i', strtotime($o['created_at'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($o['user_name']); ?></strong><br>
                                    <?php if (!empty($o['zipcode'])): ?>
                                        <small style="color:#ccc;">
                                            <?php echo htmlspecialchars($o['city'] . '/' . $o['state']); ?><br>
                                            CEP: <?php echo htmlspecialchars($o['zipcode']); ?>
                                        </small><br>
                                    <?php endif; ?>
                                    <a href="https://api.whatsapp.com/send?phone=<?php echo preg_replace('/\D/', '', $o['phone']); ?>&text=Ol%C3%A1%2C%20sobre%20o%20pedido%20%23<?php echo $o['id']; ?>"
                                        target="_blank" style="color:var(--success); font-size:0.8rem;">
                                        WhatsApp
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($o['shipping_method'] ?? '—'); ?></td>
                                <td>R$ <?php echo number_format($o['total_amount'], 2, ',', '.'); ?></td>
                                <td>
                                    <!-- Embedded Form - Use distinct form or AJAX if possible, but nested forms are invalid. 
                                         Ideally move status update to AJAX or separate page, but for simple fix: 
                                         We cannot nest forms. So we use a little JS trick or links. 
                                         Actually, we wrapped the whole table in a form for bulk delete. 
                                         So the row status update form will conflict. 
                                         
                                         SOLUTION: Move status update out of form or use 'formaction' attribute or simply don't wrap table in form but put the bulk delete button outside and use JS to gather IDs. 
                                         
                                         Let's use the JS approach for bulk delete to avoid structural changes to the status update forms row by row.
                                    -->
                                </td>
                                <!-- We need to be careful. The original code had a form inside each row for status update. 
                                     If I wrap the table in a form, I break that. 
                                     
                                     BETTER APPROACH: Keep the table as is. Put the bulk form OUTSIDE. 
                                     Wait, the previous `products.php` implementation wrapped the table. 
                                     
                                     Let's revert to separate forms approach. 
                                     I will NOT wrap the table in a form. I will make a separate form for bulk and use JS to fill hidden input.
                                -->
                                <td>
                                    <!-- Status Form (Keep standalone) -->
                                    <select id="status_<?php echo $o['id']; ?>" onchange="updateStatus(<?php echo $o['id']; ?>, this.value)"
                                        style="padding:5px; margin:0; font-size:0.8rem; background:#111; color:#fff; border:1px solid #444;">
                                        <option value="pending" <?php echo ($o['status'] == 'pending') ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="paid" <?php echo ($o['status'] == 'paid') ? 'selected' : ''; ?>>Pago</option>
                                        <option value="shipped" <?php echo ($o['status'] == 'shipped') ? 'selected' : ''; ?>>Enviado</option>
                                        <option value="canceled" <?php echo ($o['status'] == 'canceled') ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                    
                                    <?php if ($o['status'] == 'shipped' || $o['status'] == 'paid'): ?>
                                        <input type="text" placeholder="Rastreio..." 
                                            value="<?php echo htmlspecialchars($o['tracking_code'] ?? ''); ?>"
                                            style="padding:5px; margin-top:5px; font-size:0.8rem; width:100%; box-sizing:border-box;" 
                                            onblur="updateTracking(<?php echo $o['id']; ?>, this.value)">
                                    <?php endif; ?>
                                </td>

                                <td style="font-size:0.85rem; color:#ccc;">
                                    <?php foreach ($items as $i): ?>
                                        <div><?php echo $i['quantity']; ?>x <?php echo htmlspecialchars($i['product_name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <!-- Actions -->
                                <td>
                                    <div style="display:flex; gap:5px; margin-top:5px; flex-wrap:wrap;">
                                        
                                        <!-- EDIT ORDER -->
                                        <a href="edit_order.php?id=<?php echo $o['id']; ?>" class="btn-sm" 
                                           style="background:var(--warning); color:#000;" 
                                           title="Editar Pedido">✏️</a>

                                        <a href="order-print.php?id=<?php echo $o['id']; ?>" target="_blank" class="btn-sm"
                                            style="background:#333; color:#fff;" title="Imprimir Pedido">🖨️</a>

                                        <a href="order-declaration.php?id=<?php echo $o['id']; ?>" target="_blank"
                                            class="btn-sm" style="background:#e67e22; color:#fff;"
                                            title="Declaração de Conteúdo (Correios)">📄</a>

                                        <!-- Simple Export Trigger (CSV) -->
                                        <a href="?export_order=<?php echo $o['id']; ?>" class="btn-sm"
                                            style="background:#27ae60; color:#fff;" title="Exportar (Bling/Tiny/CSV)">📥</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Hidden Form for Status Update (AJAX replacement or Post Redirect) --> 
        <form id="statusForm" method="POST" style="display:none;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="st_oid">
            <input type="hidden" name="status" id="st_val">
            <input type="hidden" name="tracking_code" id="st_track">
        </form>

        <script>
            function updateStatus(oid, status) {
                if(confirm('Mudar status para '+status+'?')) {
                    var track = document.querySelector(`input[onblur*="updateTracking(${oid}"]`)?.value || '';
                    document.getElementById('st_oid').value = oid;
                    document.getElementById('st_val').value = status;
                    document.getElementById('st_track').value = track;
                    document.getElementById('statusForm').submit();
                }
            }
            function updateTracking(oid, code) {
                 var status = document.getElementById('status_'+oid).value;
                 document.getElementById('st_oid').value = oid;
                 document.getElementById('st_val').value = status;
                 document.getElementById('st_track').value = code;
                 document.getElementById('statusForm').submit();
            }
        </script>
    </div>

</body>

</html>