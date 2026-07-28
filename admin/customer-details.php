<?php
// catalogo/admin/customer-details.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$user_id = $_GET['id'] ?? 0;
$msg = '';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS hide_store_name TINYINT(1) DEFAULT 0");
} catch(Exception $e) {}

// Handle AJAX: Send Statement
if (isset($_GET['ajax_send_statement'])) {
    header('Content-Type: application/json');
    $accId = (int)($_GET['account_id'] ?? 0);
    $customAcc = $_GET['custom_acc'] ?? '';
    $customPix = $_GET['custom_pix'] ?? '';
    $detailed = isset($_GET['detailed']) && $_GET['detailed'] == '1';
    
    $targetPhone = $_GET['target_phone'] ?? '';
    $orderIdsStr = $_GET['order_ids'] ?? '';
    $orderIds = $orderIdsStr ? explode(',', $orderIdsStr) : [];
    
    require_once __DIR__ . '/../includes/notifications.php';
    $notif = new NotificationService($pdo);

    if ($accId > 0) {
        $acc = $pdo->query("SELECT * FROM payment_accounts WHERE id = $accId")->fetch();
        $res = $notif->notifyFinancialStatement($user_id, $acc['name'], $acc['pix_key'], $acc['bank_info'], $detailed, $targetPhone, $orderIds);
    } else {
        $res = $notif->notifyFinancialStatement($user_id, $customAcc ?: 'Fight Arcade', $customPix, '', $detailed, $targetPhone, $orderIds);
    }
    
    echo json_encode(['success' => $res]);
    exit;
}

// Handle AJAX: Send Order Debt Reminder
if (isset($_GET['ajax_send_order_debt'])) {
    header('Content-Type: application/json');
    $orderId = (int)$_GET['order_id'];
    $accId = (int)($_GET['account_id'] ?? 0);
    $customAcc = $_GET['custom_acc'] ?? '';
    $customPix = $_GET['custom_pix'] ?? '';
    
    $detailed = isset($_GET['detailed']) && $_GET['detailed'] == '1';
    
    require_once __DIR__ . '/../includes/notifications.php';
    $notif = new NotificationService($pdo);

    if ($accId > 0) {
        $acc = $pdo->query("SELECT * FROM payment_accounts WHERE id = $accId")->fetch();
        $res = $notif->notifyOrderDebt($user_id, $orderId, $acc['name'], $acc['pix_key'], $acc['bank_info'], $detailed);
    } else {
        $res = $notif->notifyOrderDebt($user_id, $orderId, $customAcc ?: 'Fight Arcade', $customPix, '', $detailed);
    }
    
    echo json_encode(['success' => $res]);
    exit;
}

// Handle Toggle Notification
if (isset($_GET['toggle_notify'])) {
    $newVal = (int)$_GET['toggle_notify'];
    try {
        $pdo->prepare("UPDATE users SET wa_notify_active = ? WHERE id = ?")->execute([$newVal, $user_id]);
    } catch(Exception $e) {}
    header("Location: customer-details.php?id=$user_id");
    exit;
}

// Handle Toggle Hide Store Name (Whitelabel/Stealth)
if (isset($_GET['toggle_hide_store'])) {
    $newVal = (int)$_GET['toggle_hide_store'];
    try {
        $pdo->prepare("UPDATE users SET hide_store_name = ? WHERE id = ?")->execute([$newVal, $user_id]);
    } catch(Exception $e) {}
    header("Location: customer-details.php?id=$user_id");
    exit;
}

// 1. Process New Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $amount = (float)str_replace(',', '.', $_POST['amount']);
    $method = $_POST['method'];
    $desc = $_POST['description'];
    $notify = isset($_POST['notify_wa']) ? 1 : 0;

    if ($amount > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $amount, $method, $desc]);

            // Sync dynamic current_debt for the user
            $pdo->prepare("UPDATE users u SET current_debt = (
                (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
            ) WHERE id = ?")->execute([$user_id]);

            $pdo->commit();
            $msg = '<div class="alert alert-success">✅ Pagamento de R$ ' . number_format($amount, 2, ',', '.') . ' registrado!</div>';

            // Send WhatsApp if requested
            if ($notify) {
                require_once __DIR__ . '/../includes/notifications.php';
                $notif = new NotificationService($pdo);
                
                // Fetch fresh balance
                $totalBought = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE user_id = $user_id")->fetchColumn() ?: 0;
                $totalPaid   = $pdo->query("SELECT SUM(amount) FROM customer_payments WHERE user_id = $user_id")->fetchColumn() ?: 0;
                $newBalance  = $totalBought - $totalPaid;
                
                $notif->notifyPaymentReceived($user['phone'], $user['name'], $amount, $newBalance, $desc);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '<div class="alert alert-error">Erro ao registrar pagamento: ' . $e->getMessage() . '</div>';
        }
    }
}

// 1.1 Process Delete Payment
if (isset($_GET['delete_payment'])) {
    $pay_id = (int)$_GET['delete_payment'];
    $revert_order_id = (int)($_GET['revert_order_id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT amount FROM customer_payments WHERE id = ? AND user_id = ?");
        $stmt->execute([$pay_id, $user_id]);
        $pay = $stmt->fetch();
        if ($pay) {
            // Revert order status if requested
            if ($revert_order_id > 0) {
                $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ? AND user_id = ?")->execute([$revert_order_id, $user_id]);
            }
            
            // Delete payment
            $pdo->prepare("DELETE FROM customer_payments WHERE id = ?")->execute([$pay_id]);

            // Sync dynamic current_debt for the user
            $pdo->prepare("UPDATE users u SET current_debt = (
                (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
            ) WHERE id = ?")->execute([$user_id]);

            $pdo->commit();
            $msg = '<div class="alert alert-success">✅ Pagamento excluído' . ($revert_order_id > 0 ? ' e pedido associado alterado para Pendente' : '') . ' com sucesso!</div>';
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = '<div class="alert alert-error">Erro: ' . $e->getMessage() . '</div>';
    }
}

// 1.2 Process Edit Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_payment_id'])) {
    $pay_id = (int)$_POST['edit_payment_id'];
    $new_amount = (float)str_replace(',', '.', $_POST['amount']);
    $method = $_POST['method'];
    $desc = $_POST['description'];
    $revert_order_id = (int)($_POST['revert_order_id'] ?? 0);
    $revert_status = isset($_POST['revert_order_status']) ? 1 : 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT amount FROM customer_payments WHERE id = ? AND user_id = ?");
        $stmt->execute([$pay_id, $user_id]);
        $pay = $stmt->fetch();

        if ($pay && $new_amount >= 0) {
            $old_amount = (float)$pay['amount'];
            $diff = $old_amount - $new_amount; // Se velho era 100 e novo é 80, dif é +20 (adiciona 20 à dívida)

            $pdo->prepare("UPDATE customer_payments SET amount = ?, payment_method = ?, description = ? WHERE id = ?")->execute([$new_amount, $method, $desc, $pay_id]);
            
            if ($revert_status && $revert_order_id > 0) {
                $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ? AND user_id = ?")->execute([$revert_order_id, $user_id]);
            }

            // Sync dynamic current_debt for the user
            $pdo->prepare("UPDATE users u SET current_debt = (
                (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
            ) WHERE id = ?")->execute([$user_id]);
            
            $pdo->commit();
            $msg = '<div class="alert alert-success">✅ Pagamento atualizado' . ($revert_status && $revert_order_id > 0 ? ' e pedido associado alterado para Pendente' : '') . ' com sucesso!</div>';
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = '<div class="alert alert-error">Erro: ' . $e->getMessage() . '</div>';
    }
}

// 2. Fetch Customer Info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user)
    die("Cliente não encontrado.");

// 3. Fetch Financial History (Orders + Payments)
// We combine them to make a timeline
// Orders are Debits (-), Payments are Credits (+)

// 3. Fetch Financial History (Orders + Payments)
try {
    // Get Orders
    $stmt_orders = $pdo->prepare("SELECT id, total_amount as val, created_at, 'order' as type, status FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt_orders->execute([$user_id]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    // Get Payments
    $stmt_pay = $pdo->prepare("SELECT id, amount as val, created_at, 'payment' as type, description, payment_method FROM customer_payments WHERE user_id = ? ORDER BY created_at DESC");
    $stmt_pay->execute([$user_id]);
    $payments = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        die("<h1>Erro: Tabela de Pagamentos não encontrada!</h1><p>Você precisa rodar o script de atualização do banco.</p><a href='../update_db_ledger.php' target='_blank' style='background:red; color:white; padding:10px; text-decoration:none;'>CLIQUE AQUI PARA CRIAR A TABELA</a>");
    } else {
        die("Erro banco de dados: " . $e->getMessage());
    }
}

// Merge and Sort
$history = array_merge($orders, $payments);
usort($history, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']); // Descending
});

// Calculate Totals
$total_bought = 0;
foreach ($orders as $o) {
    if ($o['status'] !== 'canceled') {
        $total_bought += $o['val'];
    }
}

$total_paid = 0;
foreach ($payments as $p)
    $total_paid += $p['val'];

$balance = $total_bought - $total_paid; // Positive means Debt
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Detalhes do Cliente | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .finance-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .val-debt {
            color: var(--danger);
            font-size: 1.5rem;
            font-weight: bold;
        }

        .val-ok {
            color: var(--success);
            font-size: 1.5rem;
            font-weight: bold;
        }

        .timeline-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            align-items: center;
            transition: 0.2s;
        }
        .timeline-item:hover {
            background: rgba(255,255,255,0.03);
        }
        .timeline-item.is-payment:hover {
            background: rgba(46,204,113,0.04);
        }

        .badge-order {
            background: #333;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .badge-pay {
            background: var(--success);
            color: #000;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        /* Payment action buttons */
        .pay-actions {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }
        .pay-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: 0.2s;
            text-decoration: none;
        }
        .pay-action-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.2);
        }
        .pay-action-btn.btn-edit {
            background: rgba(52,152,219,0.15);
            color: #3498db;
            border: 1px solid rgba(52,152,219,0.3);
        }
        .pay-action-btn.btn-edit:hover {
            background: rgba(52,152,219,0.25);
        }
        .pay-action-btn.btn-del {
            background: rgba(231,76,60,0.15);
            color: #e74c3c;
            border: 1px solid rgba(231,76,60,0.3);
        }
        .pay-action-btn.btn-del:hover {
            background: rgba(231,76,60,0.25);
        }
        .pay-action-btn.btn-view {
            background: rgba(243,156,18,0.15);
            color: #f39c12;
            border: 1px solid rgba(243,156,18,0.3);
        }
        .pay-action-btn.btn-view:hover {
            background: rgba(243,156,18,0.25);
        }

        /* Payment detail modal */
        .pay-detail-overlay {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
        }
        .pay-detail-card {
            background: #1a1e2a;
            border: 1px solid rgba(243,156,18,0.4);
            border-radius: 16px;
            padding: 2rem;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
        .pay-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .pay-detail-row:last-child { border-bottom: none; }
        .pay-detail-label { color: #888; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .pay-detail-value { color: #fff; font-weight: 700; text-align: right; }

        .btn-wa-notif { 
            background: #25d366; 
            color: #fff; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            transition: 0.3s; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            font-weight: bold;
            text-decoration: none;
            box-shadow: 0 0 10px rgba(37, 211, 102, 0.2);
        }
        .btn-wa-notif:hover { background: #128c7e; transform: scale(1.05); color: #fff; }
        .btn-wa-notif.loading { background: #555; cursor: wait; }
        .btn-wa-notif.success { background: #27ae60; }
        .btn-wa-notif.error { background: #e74c3c; }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="margin-bottom:5px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">👤 <?php echo htmlspecialchars($user['name']); ?> <a href="orders.php?f_customer=<?php echo urlencode($user['name']); ?>" class="btn-sm" style="background:rgba(243, 156, 18, 0.15); color:#f39c12; border:1px solid rgba(243, 156, 18, 0.3); font-size:0.75rem; text-decoration:none; padding:5px 10px; border-radius:6px; font-weight:bold; display:inline-flex; align-items:center; gap:6px; transition:0.2s;" onmouseover="this.style.background='rgba(243, 156, 18, 0.25)'" onmouseout="this.style.background='rgba(243, 156, 18, 0.15)'"><i class="fas fa-shopping-bag"></i> Ver Pedidos</a></h1>
                <div style="display:flex; align-items:center; gap:15px;">
                    <span style="color:<?php echo $user['wa_notify_active'] ? '#25d366' : '#888'; ?>; font-weight:bold; font-size:0.85rem;">
                        <i class="fab fa-whatsapp"></i> Notificações: <?php echo $user['wa_notify_active'] ? 'ATIVAS' : 'INATIVAS'; ?>
                    </span>
                    <a href="?id=<?php echo $user_id; ?>&toggle_notify=<?php echo $user['wa_notify_active'] ? 0 : 1; ?>" 
                       class="btn-sm" style="background:#334155; font-size:0.7rem; color:#fff;">
                       <?php echo $user['wa_notify_active'] ? 'Desativar' : 'Ativar'; ?>
                    </a>
                    
                    <!-- NEW STEALTH MODE TOGGLE -->
                    <div style="display:flex; align-items:center; gap:8px; margin-left:15px; border-left:1px solid #333; padding-left:15px;">
                        <span style="color:<?php echo $user['hide_store_name'] ? '#f1c40f' : '#888'; ?>; font-weight:bold; font-size:0.85rem;">
                            <i class="fas fa-user-secret"></i> Modo Stealth: <?php echo $user['hide_store_name'] ? 'ON' : 'OFF'; ?>
                        </span>
                        <a href="?id=<?php echo $user_id; ?>&toggle_hide_store=<?php echo $user['hide_store_name'] ? 0 : 1; ?>" 
                           class="btn-sm" style="background:<?php echo $user['hide_store_name'] ? '#9b59b6' : '#333'; ?>; font-size:0.7rem; color:#fff;">
                           <?php echo $user['hide_store_name'] ? 'Remover Sigilo' : 'Ativar Sigilo (Whitelabel)'; ?>
                        </a>
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="customer-edit.php?id=<?php echo $user_id; ?>" class="btn" style="background:#f1c40f; color:#000; font-weight:bold; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-edit"></i> EDITAR DADOS
                </a>
                <a href="rma.php?customer_id=<?php echo $user_id; ?>" class="btn" style="background:#9b59b6; color:#fff; font-weight:bold; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-tools"></i> CRIAR RMA (ETIQUETA)
                </a>
                <button type="button" onclick="openStatementModal()" class="btn-wa-notif" <?php echo !$user['wa_notify_active'] ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                    <i class="fab fa-whatsapp"></i> ENVIAR EXTRATO
                </button>
                <a href="customers.php" class="btn btn-secondary">Voltar</a>
            </div>
        </div>
        <p style="color:#888;"><?php echo $user['email']; ?> | Tel: <?php echo $user['phone']; ?></p>

        <?php echo $msg; ?>

        <!-- FINANCIAL SUMMARY -->
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem; margin-top:2rem;">
            <div class="finance-card">
                <h3>Total Comprado</h3>
                <div style="font-size:1.5rem;"><span class="finance-value">R$ <?php echo number_format($total_bought, 2, ',', '.'); ?></span></div>
            </div>
            <div class="finance-card">
                <h3>Total Pago</h3>
                <div style="font-size:1.5rem; color:var(--success);"><span class="finance-value"><span class="finance-value">R$
                    <?php echo number_format($total_paid, 2, ',', '.'); ?></span></span></div>
            </div>
            <div class="finance-card"
                style="border-color: <?php echo $balance > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                <h3>Saldo (Pendente)</h3>
                <div class="<?php echo $balance > 0 ? 'val-debt' : 'val-ok'; ?>">
                    <span class="finance-value">R$ <?php echo number_format($balance, 2, ',', '.'); ?></span>
                </div>
                <small><?php echo $balance > 0 ? 'O cliente deve' : 'Tudo quitado / Crédito'; ?></small>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem; margin-top:3rem;">

            <!-- EXTRATO / HISTORY -->
            <div>
                <h2 style="border-bottom:1px solid #333; padding-bottom:10px;">📜 Extrato Financeiro</h2>
                <div style="background:var(--bg-card); border-radius:8px; border:1px solid var(--border);">
                    <?php foreach ($history as $item): ?>
                        <div class="timeline-item <?php echo $item['type'] == 'payment' ? 'is-payment' : ''; ?>">
                            <div style="flex:1;">
                                <div style="font-weight:bold; display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                                    <?php if ($item['type'] == 'order'): ?>
                                        <a href="edit_order.php?id=<?php echo $item['id']; ?>" style="text-decoration:none;">
                                            <span class="badge-order" style="<?php echo $item['status'] == 'canceled' ? 'background:#555; color:#aaa; text-decoration:line-through;' : ''; ?>">
                                                PEDIDO #<?php echo $item['id']; ?><?php echo $item['status'] == 'canceled' ? ' (Cancelado)' : ''; ?>
                                            </span>
                                        </a>
                                        <?php if ($item['status'] !== 'canceled'): ?>
                                            <button onclick="openOrderDebtModal(<?php echo $item['id']; ?>, <?php echo $item['val']; ?>)" class="btn-sm" style="background:#e67e22; border:none; padding:2px 8px; font-size:0.7rem; cursor:pointer; color:#fff;">💰 Cobrar este</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge-pay">PAGAMENTO</span>
                                    <?php endif; ?>
                                    <span style="font-size:0.85rem; color:#888;">
                                        <?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?>
                                    </span>
                                </div>
                                <?php if ($item['type'] == 'payment'): ?>
                                    <div style="font-size:0.85rem; margin-top:5px; color:#ccc;">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                        <span style="color:#aaa;">(Method: <?php echo $item['payment_method']; ?>)</span>
                                    </div>
                                    <!-- PAYMENT ACTION BUTTONS -->
                                    <div class="pay-actions">
                                        <button onclick="viewPaymentDetail(<?php echo $item['id']; ?>, '<?php echo number_format($item['val'], 2, ',', '.'); ?>', '<?php echo addslashes($item['payment_method']); ?>', '<?php echo addslashes($item['description']); ?>', '<?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?>')" class="pay-action-btn btn-view">
                                            👁️ Ver
                                        </button>
                                        <button onclick="openEditPaymentModal(<?php echo $item['id']; ?>, '<?php echo $item['val']; ?>', '<?php echo addslashes($item['payment_method']); ?>', '<?php echo addslashes($item['description']); ?>')" class="pay-action-btn btn-edit">
                                            ✏️ Editar
                                        </button>
                                        <a href="javascript:void(0)" onclick="confirmDeletePayment(<?php echo $item['id']; ?>, '<?php echo number_format($item['val'], 2, ',', '.'); ?>', '<?php echo addslashes($item['description']); ?>')" class="pay-action-btn btn-del">
                                            🗑️ Excluir
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="text-align:right; flex-shrink:0; margin-left:15px;">
                                <?php if ($item['type'] == 'order'): ?>
                                    <div style="color:<?php echo $item['status'] == 'canceled' ? '#555' : 'var(--danger)'; ?>; font-weight:bold; font-size:1.05rem; <?php echo $item['status'] == 'canceled' ? 'text-decoration:line-through;' : ''; ?>">- <span class="finance-value">R$
                                        <?php echo number_format($item['val'], 2, ',', '.'); ?></span></div>
                                <?php else: ?>
                                    <div style="color:var(--success); font-weight:bold; font-size:1.05rem;">+ <span class="finance-value">R$
                                        <?php echo number_format($item['val'], 2, ',', '.'); ?></span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- REGISTER PAYMENT FORM -->
            <div>
                <div class="auth-box" style="margin:0; width:100%; border:1px solid var(--primary);">
                    <h2 style="margin-bottom:1rem;">💰 Registrar Pagamento</h2>
                    <form method="POST">
                        <label>Valor Recebido (R$)</label>
                        <input type="text" name="amount" placeholder="0,00" required style="font-size:1.2rem;">

                        <label>Forma de Pagamento</label>
                        <select name="method" required>
                            <option value="Pix">Pix</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão de Crédito">Cartão de Crédito</option>
                            <option value="Cartão de Débito">Cartão de Débito</option>
                            <option value="Outro">Outro</option>
                        </select>

                        <label>Descrição / Obs</label>
                        <textarea name="description" placeholder="Ex: Entrada referente ao pedido X..."
                            rows="3"></textarea>

                        <div style="margin:10px 0; background:rgba(37, 211, 102, 0.1); padding:12px; border-radius:8px; border:1px solid rgba(37, 211, 102, 0.3);">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#25d366; font-weight:bold;">
                                <input type="checkbox" name="notify_wa" value="1" checked style="width:18px; height:18px;">
                                📱 Notificar cliente via WhatsApp (Evolution)
                            </label>
                            <small style="display:block; color:#888; margin-top:5px; margin-left:28px;">Envia o valor recebido e o saldo restante automaticamente.</small>
                        </div>

                        <button type="submit" name="add_payment" value="1" class="btn btn-success"
                            style="width:100%; margin-top:1rem; font-weight:900; font-size:1.1rem; padding:15px;">
                            CONFIRMAR PAGAMENTO
                        </button>
                    </form>
                </div>
            </div>

      <!-- MODAL: ENVIAR EXTRATO -->
    <div id="statementModal" class="modal-vip" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px);">
        <div class="modal-vip-content" style="max-width:500px;">
            <h2 style="color:var(--primary); margin-bottom:10px;" id="modalTitle"><i class="fab fa-whatsapp"></i> Enviar Extrato</h2>
            <p style="color:#aaa; font-size:0.9rem; margin-bottom:20px;" id="modalDesc">Selecione a conta para o cliente realizar depósitos/transferências.</p>
            <input type="hidden" id="pending_order_id" value="0">

            <div class="form-group" style="margin-bottom:15px;">
                <label style="color:#fff;">Escolher Conta Cadastrada:</label>
                <select id="stmt_account" onchange="toggleCustomAcc(this.value)" style="width:100%; padding:12px; background:#222; color:#fff; border-radius:8px; border:1px solid #444;">
                    <option value="0">-- Adicionar Conta Manual --</option>
                    <?php 
                    try {
                        $accs = $pdo->query("SELECT * FROM payment_accounts ORDER BY name ASC")->fetchAll();
                        foreach($accs as $ac): ?>
                            <option value="<?php echo $ac['id']; ?>"><?php echo htmlspecialchars($ac['name']); ?> (<?php echo strtoupper($ac['type']); ?>)</option>
                        <?php endforeach; 
                    } catch(Exception $e) {
                        echo "<option disabled>⚠️ Erro: Rode o 'Reparar Banco' no menu Ferramentas</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:15px; background:rgba(255,255,255,0.05); padding:12px; border-radius:8px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#fff;">
                    <input type="checkbox" id="stmt_detailed" checked style="width:18px; height:18px;">
                    📋 Incluir itens dos produtos (Detalhado)
                </label>
                <small style="display:block; color:#777; margin-top:5px; margin-left:28px;">Mostra o que foi comprado em cada pedido.</small>
            </div>

            <div id="custom_acc_fields" style="background:#0f172a; padding:15px; border-radius:10px; margin-bottom:20px;">
                <div class="form-group">
                    <label style="color:#888; font-size:0.8rem;">Nome da Conta / Banco</label>
                    <input type="text" id="custom_acc_name" placeholder="Ex: PIX Fight Arcade" style="margin-bottom:10px;">
                </div>
                <div class="form-group">
                    <label style="color:#888; font-size:0.8rem;">Chave PIX ou Dados</label>
                    <input type="text" id="custom_acc_pix" placeholder="E-mail, CNPJ ou Ag/Conta">
                </div>
            </div>

            <div id="stmt_options_section">
                <div class="form-group" style="margin-bottom:15px; background:rgba(255,255,255,0.05); padding:12px; border-radius:8px;">
                    <label style="color:#fff; font-size:0.9rem; display:block; margin-bottom:5px;">📱 Enviar para número específico (Teste):</label>
                    <input type="text" id="stmt_target_phone" placeholder="Ex: 11999999999 (Deixe em branco para o cliente)" style="width:100%; padding:8px; border-radius:4px; background:#222; color:#fff; border:1px solid #444;">
                    <small style="color:#777;">Útil para revisar ou editar o extrato antes de encaminhar ao cliente.</small>
                </div>

                <div class="form-group" style="margin-bottom:15px; background:rgba(255,255,255,0.05); padding:12px; border-radius:8px; max-height:150px; overflow-y:auto;">
                    <label style="color:#fff; font-size:0.9rem; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span>📦 Pedidos (Extrato Parcial):</span>
                        <div>
                            <button onclick="selectAllOrders(true)" type="button" class="btn-sm" style="background:#444; color:#fff; font-size:0.7rem; border:none; cursor:pointer;">Todos</button>
                            <button onclick="selectLast3Orders()" type="button" class="btn-sm" style="background:#2980b9; color:#fff; font-size:0.7rem; border:none; cursor:pointer;">Últimos 3</button>
                        </div>
                    </label>
                    <div id="stmt_orders_list">
                        <?php foreach ($orders as $o): ?>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#ccc; margin-bottom:5px; font-size:0.85rem;">
                            <input type="checkbox" name="stmt_order_check" value="<?php echo $o['id']; ?>" checked style="width:16px; height:16px;">
                            Pedido #<?php echo $o['id']; ?> - <?php echo date('d/m/Y', strtotime($o['created_at'])); ?> - <span class="finance-value">R$ <?php echo number_format($o['val'], 2, ',', '.'); ?></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if(empty($orders)): ?>
                            <small style="color:#777;">Nenhum pedido encontrado.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="background:rgba(231, 76, 60, 0.1); border:1px solid #e74c3c; color:#e74c3c; padding:10px; border-radius:8px; font-size:0.8rem; margin-bottom:20px;">
                ⚠️ <strong>AVISO:</strong> A mensagem incluirá o alerta para o cliente nunca depositar sem confirmar a conta.
            </div>

            <div style="display:flex; gap:10px;">
                <button id="btnConfirmStmt" onclick="confirmSendStatement()" class="btn-wa-notif" style="flex:2; justify-content:center;">🚀 DISPARAR AGORA</button>
                <button onclick="document.getElementById('statementModal').style.display='none'" class="btn" style="flex:1; background:#333; color:white;">CANCELAR</button>
            </div>
        </div>
    </div>

    <!-- MODAL: EDITAR PAGAMENTO -->
    <div id="editPaymentModal" class="modal-vip" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px);">
        <div class="modal-vip-content" style="max-width:400px; background:var(--bg-card); border:1px solid var(--border); border-radius:12px;">
            <h2 style="color:var(--primary); margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:10px;"><i class="fas fa-edit"></i> Editar Pagamento</h2>
            <form method="POST">
                <input type="hidden" name="edit_payment_id" id="edit_payment_id" value="">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="color:#aaa;">Valor Pago (R$)</label>
                    <input type="text" name="amount" id="edit_payment_amount" required style="width:100%; font-size:1.2rem; padding:10px; background:#222; border:1px solid #444; color:#fff; border-radius:6px;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="color:#aaa;">Forma de Pagamento</label>
                    <select name="method" id="edit_payment_method" required style="width:100%; padding:10px; background:#222; border:1px solid #444; color:#fff; border-radius:6px;">
                        <option value="Pix">Pix</option>
                        <option value="Dinheiro">Dinheiro</option>
                        <option value="Cartão de Crédito">Cartão de Crédito</option>
                        <option value="Cartão de Débito">Cartão de Débito</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="color:#aaa;">Descrição / Obs</label>
                    <textarea name="description" id="edit_payment_desc" rows="3" style="width:100%; padding:10px; background:#222; border:1px solid #444; color:#fff; border-radius:6px;"></textarea>
                </div>

                <div id="revertOrderGroup" class="form-group" style="margin-bottom:15px; display:none; background:rgba(231,76,60,0.1); padding:12px; border-radius:8px; border:1px solid rgba(231,76,60,0.2);">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#e74c3c; font-weight:bold; margin:0;">
                        <input type="checkbox" name="revert_order_status" value="1" style="width:18px; height:18px;">
                        <span id="revertOrderLabel">Alterar status do pedido associado para Pendente</span>
                    </label>
                    <input type="hidden" name="revert_order_id" id="revert_order_id" value="0">
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-success" style="flex:2; font-weight:bold; padding:12px;">SALVAR ALTERAÇÕES</button>
                    <button type="button" onclick="document.getElementById('editPaymentModal').style.display='none'" class="btn" style="flex:1; background:#333; color:white; padding:12px;">CANCELAR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: VER DETALHES DO PAGAMENTO -->
    <div id="viewPaymentModal" class="pay-detail-overlay" onclick="if(event.target===this)this.style.display='none'">
        <div class="pay-detail-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; border-bottom:1px solid rgba(243,156,18,0.2); padding-bottom:12px;">
                <h2 style="color:#f39c12; margin:0; font-size:1.15rem;">💰 Detalhes do Pagamento</h2>
                <button onclick="document.getElementById('viewPaymentModal').style.display='none'" style="background:none; border:none; color:#888; font-size:1.5rem; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <div id="viewPaymentContent"></div>
            <div style="margin-top:1.2rem; display:flex; gap:8px;">
                <button type="button" id="viewPayEditBtn" class="pay-action-btn btn-edit" style="flex:1; justify-content:center; padding:10px;">✏️ Editar</button>
                <a href="#" id="viewPayDeleteBtn" class="pay-action-btn btn-del" style="flex:1; justify-content:center; padding:10px; text-align:center;">🗑️ Excluir</a>
                <button onclick="document.getElementById('viewPaymentModal').style.display='none'" style="flex:1; background:#333; color:#fff; border:none; padding:10px; border-radius:6px; cursor:pointer; font-weight:700;">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        function viewPaymentDetail(id, amount, method, desc, date) {
            const content = document.getElementById('viewPaymentContent');
            content.innerHTML = `
                <div class="pay-detail-row">
                    <span class="pay-detail-label">ID do Pagamento</span>
                    <span class="pay-detail-value">#${id}</span>
                </div>
                <div class="pay-detail-row">
                    <span class="pay-detail-label">Valor</span>
                    <span class="pay-detail-value" style="color:#2ecc71; font-size:1.2rem;">R$ ${amount}</span>
                </div>
                <div class="pay-detail-row">
                    <span class="pay-detail-label">Forma de Pagamento</span>
                    <span class="pay-detail-value">${method}</span>
                </div>
                <div class="pay-detail-row">
                    <span class="pay-detail-label">Data / Hora</span>
                    <span class="pay-detail-value">${date}</span>
                </div>
                <div class="pay-detail-row">
                    <span class="pay-detail-label">Descrição</span>
                    <span class="pay-detail-value" style="max-width:250px; word-break:break-word;">${desc || '<em style="color:#555">Sem descrição</em>'}</span>
                </div>
            `;
            // Wire up Edit button
            document.getElementById('viewPayEditBtn').onclick = function() {
                document.getElementById('viewPaymentModal').style.display = 'none';
                openEditPaymentModal(id, amount.replace('.','').replace(',','.'), method, desc);
            };
            // Wire up Delete button
            const delBtn = document.getElementById('viewPayDeleteBtn');
            delBtn.href = 'javascript:void(0)';
            delBtn.onclick = function(e) {
                document.getElementById('viewPaymentModal').style.display = 'none';
                confirmDeletePayment(id, amount, desc);
            };
            document.getElementById('viewPaymentModal').style.display = 'flex';
        }

        function openEditPaymentModal(id, amount, method, desc) {
            document.getElementById('edit_payment_id').value = id;
            document.getElementById('edit_payment_amount').value = parseFloat(amount).toFixed(2).replace('.', ',');
            document.getElementById('edit_payment_method').value = method;
            document.getElementById('edit_payment_desc').value = desc;

            const orderMatch = desc.match(/Pedido\s*(?:PDV\s*)?#(\d+)/i);
            const revertCheckboxGroup = document.getElementById('revertOrderGroup');
            const checkboxInput = document.querySelector('input[name="revert_order_status"]');
            if (checkboxInput) checkboxInput.checked = false;

            if (orderMatch && orderMatch[1]) {
                const orderId = orderMatch[1];
                document.getElementById('revert_order_id').value = orderId;
                document.getElementById('revertOrderLabel').innerHTML = `🔄 Alterar status do <strong>Pedido #${orderId}</strong> para <strong>Pendente</strong>`;
                revertCheckboxGroup.style.display = 'block';
            } else {
                document.getElementById('revert_order_id').value = '0';
                revertCheckboxGroup.style.display = 'none';
            }

            document.getElementById('editPaymentModal').style.display = 'flex';
        }

        function confirmDeletePayment(payId, amount, desc) {
            let msg = `⚠️ Tem certeza que deseja EXCLUIR este pagamento?\n\nValor: R$ ${amount}\nDescrição: ${desc}\n\nO valor será adicionado de volta à dívida do cliente.`;
            
            if (confirm(msg)) {
                const orderMatch = desc.match(/Pedido\s*(?:PDV\s*)?#(\d+)/i);
                if (orderMatch && orderMatch[1]) {
                    const orderId = orderMatch[1];
                    if (confirm(`🔄 DETECTADO: Este pagamento está vinculado ao Pedido #${orderId}.\n\nDeseja alterar o status do Pedido #${orderId} de volta para PENDENTE?`)) {
                        window.location.href = `?id=<?php echo $user_id; ?>&delete_payment=${payId}&revert_order_id=${orderId}`;
                        return;
                    }
                }
                window.location.href = `?id=<?php echo $user_id; ?>&delete_payment=${payId}`;
            }
        }

        function openStatementModal() {
            document.getElementById('pending_order_id').value = "0";
            document.getElementById('modalTitle').innerHTML = '<i class="fab fa-whatsapp"></i> Enviar Extrato Completo';
            document.getElementById('modalDesc').innerText = "O cliente receberá o extrato. Você pode filtrar os pedidos abaixo.";
            document.getElementById('stmt_options_section').style.display = 'block';
            document.getElementById('statementModal').style.display = 'block';
        }

        function openOrderDebtModal(orderId, val) {
            document.getElementById('pending_order_id').value = orderId;
            document.getElementById('modalTitle').innerHTML = '<i class="fab fa-whatsapp"></i> Cobrar Pedido #' + orderId;
            document.getElementById('modalDesc').innerText = "O cliente receberá uma lembrança específica deste pedido (R$ " + val.toFixed(2) + ") com a lista de itens.";
            document.getElementById('stmt_options_section').style.display = 'none'; // Hide partial statement options for single order debt
            document.getElementById('statementModal').style.display = 'block';
        }

        function selectAllOrders(checked) {
            document.querySelectorAll('input[name="stmt_order_check"]').forEach(cb => cb.checked = checked);
        }

        function selectLast3Orders() {
            selectAllOrders(false);
            const checkboxes = document.querySelectorAll('input[name="stmt_order_check"]');
            for(let i=0; i<3 && i<checkboxes.length; i++) {
                checkboxes[i].checked = true;
            }
        }

        function toggleCustomAcc(val) {
            document.getElementById('custom_acc_fields').style.display = (val == '0') ? 'block' : 'none';
        }

        function confirmSendStatement() {
            const accId = document.getElementById('stmt_account').value;
            const orderId = document.getElementById('pending_order_id').value;
            const detailed = document.getElementById('stmt_detailed').checked ? 1 : 0;
            const cName = document.getElementById('custom_acc_name').value;
            const cPix = document.getElementById('custom_acc_pix').value;
            
            let targetPhone = "";
            let orderIdsStr = "";
            if (orderId == "0") {
                targetPhone = document.getElementById('stmt_target_phone').value.trim();
                const checkedOrders = Array.from(document.querySelectorAll('input[name="stmt_order_check"]:checked')).map(cb => cb.value);
                
                if (checkedOrders.length === 0 && document.querySelectorAll('input[name="stmt_order_check"]').length > 0) {
                    alert('Selecione pelo menos um pedido para enviar no extrato, ou não envie itens.');
                    return;
                }
                
                // If there are no orders at all, allow it to pass empty, or if we checked some
                orderIdsStr = checkedOrders.join(',');
                
                // A small trick to avoid sending ALL orders when we intentionally uncheck all
                if (checkedOrders.length === 0) orderIdsStr = "NONE"; 
            }

            const btn = document.getElementById('btnConfirmStmt');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ENVIANDO...';

            let action = (orderId == "0") ? 'ajax_send_statement' : 'ajax_send_order_debt';
            let url = `customer-details.php?id=<?php echo $user_id; ?>&${action}=1&account_id=${accId}&detailed=${detailed}`;
            
            if(orderId != "0") url += `&order_id=${orderId}`;
            if(orderId == "0") url += `&target_phone=${encodeURIComponent(targetPhone)}&order_ids=${encodeURIComponent(orderIdsStr)}`;

            if(accId == '0') {
                url += `&custom_acc=${encodeURIComponent(cName)}&custom_pix=${encodeURIComponent(cPix)}`;
            }

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Mensagem enviada com sucesso!');
                        document.getElementById('statementModal').style.display = 'none';
                    } else {
                        alert('❌ Erro ao enviar. Verifique se o WhatsApp está conectado.');
                    }
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '🚀 DISPARAR AGORA';
                });
        }
    </script>
</body>

</html>