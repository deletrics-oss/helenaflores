<?php
// catalogo/order-success.php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/user_auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$order_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch Order Details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Pedido não encontrado.");
}

// Fetch Items
$stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll();

// 4. Send Initial Order Confirmation Email (only if not already sent/notifying success page for first time)
if (!isset($_GET['status'])) { // Basic check to avoid resending on refresh
    $uStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $uData = $uStmt->fetch();

    if ($uData && !empty($uData['email'])) {
        $title = "Pedido Recebido! #$order_id 🛒";
        $body = "Olá {$uData['name']},\n\nSeu pedido #$order_id foi recebido com sucesso!\n\nStatus Atual: Aguardando Pagamento\n\nAssim que o pagamento for confirmado, iniciaremos a preparação para o envio.\n\nVocê pode acompanhar seus pedidos aqui: " . BASE_URL . "/my-orders.php\n\nObrigado por comprar na Fight Arcade!";
        $headers = "From: contato@fightarcade.com.br\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($uData['email'], $title, $body, $headers);
    }
}

// 5. Build WhatsApp Message
$msg = "*Novo Pedido #$order_id*\n";
$msg .= "------------------\n";
foreach ($items as $i) {
    $msg .= "{$i['quantity']}x {$i['product_name']} (R$ " . number_format($i['subtotal'], 2, ',', '.') . ")\n";
}
$msg .= "------------------\n";
$msg .= "*Total: R$ " . number_format($order['total_amount'], 2, ',', '.') . "*\n";
$msg .= "Frete: " . $order['shipping_method'] . "\n\n";
$msg .= "Olá! Acabei de fazer esse pedido no site e gostaria de combinar o pagamento.";

$wa_link = "https://api.whatsapp.com/send?phone=" . preg_replace('/[^0-9]/', '', $store_phone) . "&text=" . urlencode($msg);

// MP Specific Status
$mp_status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Pedido Confirmado! | Fight Arcade</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .success-box {
            text-align: center;
            background: var(--bg-card);
            padding: 3rem;
            border-radius: 12px;
            max-width: 600px;
            margin: 4rem auto;
            border: 1px solid var(--border);
        }

        .icon-check {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1rem;
        }

        .order-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }
    </style>
</head>

<body>
    <?php include 'includes/header_public.php'; ?>

    <div class="container">
        <div class="success-box">
            <div class="icon-check">✅</div>
            <?php if ($mp_status === 'success' || $mp_status === 'approved'): ?>
                <div class="alert alert-success"
                    style="padding:1rem; margin-bottom:1rem; background:rgba(37, 211, 102, 0.1); color:#25D366; border:1px solid #25D366; border-radius:8px;">
                    🎉 <strong>Pagamento Aprovado!</strong><br>
                    Obrigado! Já recebemos a confirmação do seu pagamento.
                </div>
            <?php elseif ($mp_status === 'pending'): ?>
                <div class="alert alert-warning"
                    style="padding:1rem; margin-bottom:1rem; background:rgba(243, 156, 18, 0.1); color:#f39c12; border:1px solid #f39c12; border-radius:8px;">
                    ⏳ <strong>Pagamento em Processamento</strong><br>
                    Seu pagamento está sendo analisado. Assim que aprovado, você receberá um e-mail.
                </div>
            <?php endif; ?>

            <h1>Pedido Recebido!</h1>
            <p>Seu pedido <strong>#<?php echo $order_id; ?></strong> foi registrado com sucesso.</p>
            <p>Para agilizar o atendimento, envie o resumo abaixo para nosso WhatsApp.</p>

            <div class="order-actions">
                <a href="<?php echo $wa_link; ?>" target="_blank" class="btn"
                    style="background: #25D366; color: white;">
                    📱 Compartilhar com a Loja (WhatsApp)
                </a>

                <button onclick="window.print()" class="btn btn-secondary">
                    📄 Imprimir / Salvar PDF
                </button>

                <a href="my-orders.php" class="btn btn-secondary"
                    style="margin-top:10px; border:none; color:var(--text-muted);">
                    Voltar aos Meus Pedidos
                </a>
            </div>
        </div>

        <!-- Printable Area (Hidden normally, shown on print) -->
        <div id="printable" style="display:none;">
            <h1>Pedido #<?php echo $order_id; ?></h1>
            <ul>
                <?php foreach ($items as $i): ?>
                    <li><?php echo "{$i['quantity']}x {$i['product_name']} - R$ " . number_format($i['subtotal'], 2, ',', '.'); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <h3>Total: R$ <?php echo number_format($order['total_amount'], 2, ',', '.'); ?></h3>
        </div>
    </div>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            #printable,
            #printable * {
                visibility: visible;
            }

            #printable {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                display: block !important;
                color: black;
                background: white;
                padding: 20px;
            }

            .success-box,
            .order-actions,
            header,
            footer {
                display: none !important;
            }
        }
    </style>
</body>

</html>