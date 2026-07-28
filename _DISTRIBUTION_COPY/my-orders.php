<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';

checkUser(); // Ensure user is logged in
$user_id = $_SESSION['user_id'];

// Get user orders
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE user_id = :uid 
    ORDER BY created_at DESC
");
$stmt->execute([':uid' => $user_id]);
$orders = $stmt->fetchAll();

// Handle Order Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $oid = (int) $_POST['cancel_order_id'];
    // Verify ownership and status
    $stmtC = $pdo->prepare("UPDATE orders SET status = 'canceled' WHERE id = ? AND user_id = ? AND status = 'pending'");
    if ($stmtC->execute([$oid, $user_id])) {
        header("Location: my-orders.php?msg=canceled");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        @media (max-width: 600px) {
            .table-responsive table {
                font-size: 0.8rem;
            }

            .table-responsive th,
            .table-responsive td {
                padding: 0.5rem 0.2rem;
            }

            .btn-sm {
                padding: 0.4rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container" style="padding-top: 2rem;">
        <h1 style="color: var(--primary); margin-bottom: 2rem;">Meus Pedidos</h1>

        <?php if (count($orders) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th># Pedido</th>
                            <th>Data</th>
                            <th>Itens</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Rastreio</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order):
                            // Fetch items for this order
                            $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :oid");
                            $stmt_items->execute([':oid' => $order['id']]);
                            $items = $stmt_items->fetchAll();
                            ?>
                            <tr>
                                <td># <?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <ul style="list-style:none; padding:0; margin:0; font-size:0.9rem; color:#ccc;">
                                        <?php foreach ($items as $item): ?>
                                            <li><?php echo $item['quantity']; ?>x
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td style="font-weight:bold; color:var(--success);">R$
                                    <?php echo number_format($order['total_amount'], 2, ',', '.'); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php
                                        $labels = [
                                            'pending' => 'Pendente',
                                            'paid' => 'Pago',
                                            'shipped' => 'Enviado',
                                            'canceled' => 'Cancelado'
                                        ];
                                        echo $labels[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $order['tracking_code'] ? htmlspecialchars($order['tracking_code']) : '—'; ?>
                                </td>
                                <td>
                                    <?php if ($order['status'] == 'pending'): ?>
                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                            <a href="re-pay.php?id=<?php echo $order['id']; ?>" class="btn-sm"
                                                style="background:#009ee3; color:#fff; text-align:center; border:none; text-decoration:none; padding:8px; font-weight:bold;">
                                                💳 Pagar Agora (Mercado Pago)
                                            </a>
                                            <a href="https://api.whatsapp.com/send?phone=<?php echo WHATSAPP_ADMIN; ?>&text=Ol%C3%A1%2C%20queria%20verificar%20o%20pedido%20%23<?php echo $order['id']; ?>"
                                                target="_blank" class="btn-sm btn-secondary"
                                                style="color: var(--success); border-color: var(--success); text-align:center; text-decoration:none; display:block;">
                                                Falar no WhatsApp
                                            </a>
                                            <form method="POST" style="margin:0;"
                                                onsubmit="return confirm('Tem certeza que deseja cancelar este pedido?')">
                                                <input type="hidden" name="cancel_order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn-sm btn-danger"
                                                    style="width:100%; border:none; background:rgba(255,0,0,0.1); color:#ff4d4d; border:1px solid #ff4d4d; cursor:pointer;">
                                                    ✖ Cancelar Pedido
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <!-- Re-Order Logic -->
                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                            <form action="cart.php" method="POST" style="margin:0;">
                                                <input type="hidden" name="action" value="reorder">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn-sm" 
                                                   style="width:100%; background:var(--primary); color:#000; text-align:center; font-weight:bold; border:none; cursor:pointer; padding:8px;">
                                                    🔄 Refazer mesmo Pedido
                                                </button>
                                            </form>
                                            <a href="https://api.whatsapp.com/send?phone=<?php echo WHATSAPP_ADMIN; ?>&text=Ol%C3%A1%2C%20queria%20verificar%20o%20pedido%20%23<?php echo $order['id']; ?>"
                                                target="_blank" class="btn-sm btn-secondary"
                                                style="color: var(--success); border-color: var(--success); text-align:center; text-decoration:none;">
                                                Falar no WhatsApp
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding: 4rem; background: var(--bg-card); border-radius:12px;">
                <p style="margin-bottom:1rem; font-size:1.2rem;">Você ainda não fez nenhum pedido.</p>
                <a href="<?php echo BASE_URL; ?>/" class="btn">Começar a Comprar</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>

</html>