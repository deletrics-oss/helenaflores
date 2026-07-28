<?php
// catalogo/admin/customer-details.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$user_id = $_GET['id'] ?? 0;
$msg = '';

// 1. Process New Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $amount = str_replace(',', '.', $_POST['amount']);
    $method = $_POST['method'];
    $desc = $_POST['description'];

    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $amount, $method, $desc])) {
            $msg = '<div class="alert alert-success">✅ Pagamento de R$ ' . number_format($amount, 2, ',', '.') . ' registrado!</div>';
        } else {
            $msg = '<div class="alert alert-error">Erro ao registrar pagamento.</div>';
        }
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
    $stmt_orders = $pdo->prepare("SELECT id, total_amount as val, created_at, 'order' as type FROM orders WHERE user_id = ? ORDER BY created_at DESC");
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
foreach ($orders as $o)
    $total_bought += $o['val'];

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
            padding: 10px;
            border-bottom: 1px solid #333;
            align-items: center;
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
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>👤 <?php echo htmlspecialchars($user['name']); ?></h1>
            <a href="customers.php" class="btn btn-secondary">Voltar</a>
        </div>
        <p style="color:#888;"><?php echo $user['email']; ?> | Tel: <?php echo $user['phone']; ?></p>

        <?php echo $msg; ?>

        <!-- FINANCIAL SUMMARY -->
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem; margin-top:2rem;">
            <div class="finance-card">
                <h3>Total Comprado</h3>
                <div style="font-size:1.5rem;">R$ <?php echo number_format($total_bought, 2, ',', '.'); ?></div>
            </div>
            <div class="finance-card">
                <h3>Total Pago</h3>
                <div style="font-size:1.5rem; color:var(--success);">R$
                    <?php echo number_format($total_paid, 2, ',', '.'); ?></div>
            </div>
            <div class="finance-card"
                style="border-color: <?php echo $balance > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                <h3>Saldo (Pendente)</h3>
                <div class="<?php echo $balance > 0 ? 'val-debt' : 'val-ok'; ?>">
                    R$ <?php echo number_format($balance, 2, ',', '.'); ?>
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
                        <div class="timeline-item">
                            <div>
                                <div style="font-weight:bold;">
                                    <?php if ($item['type'] == 'order'): ?>
                                        <span class="badge-order">PEDIDO #<?php echo $item['id']; ?></span>
                                    <?php else: ?>
                                        <span class="badge-pay">PAGAMENTO</span>
                                    <?php endif; ?>
                                    <span style="font-size:0.9rem; color:#888; margin-left:10px;">
                                        <?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?>
                                    </span>
                                </div>
                                <?php if ($item['type'] == 'payment'): ?>
                                    <div style="font-size:0.9rem; margin-top:5px;">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                        <span style="color:#aaa;">(Method: <?php echo $item['payment_method']; ?>)</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="text-align:right;">
                                <?php if ($item['type'] == 'order'): ?>
                                    <div style="color:var(--danger); font-weight:bold;">- R$
                                        <?php echo number_format($item['val'], 2, ',', '.'); ?></div>
                                <?php else: ?>
                                    <div style="color:var(--success); font-weight:bold;">+ R$
                                        <?php echo number_format($item['val'], 2, ',', '.'); ?></div>
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

                        <button type="submit" name="add_payment" value="1" class="btn btn-success"
                            style="width:100%; margin-top:1rem;">
                            CONFIRMAR PAGAMENTO
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </div>
</body>

</html>