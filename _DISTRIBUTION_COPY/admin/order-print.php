<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT o.*, u.name, u.email, u.phone, u.document, u.city, u.state, u.zipcode, u.address, u.number, u.neighborhood, u.company_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order)
    die("Pedido não encontrado");

$items = $pdo->query("SELECT * FROM order_items WHERE order_id = $id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Pedido #<?php echo $id; ?></title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 14px;
            max-width: 800px;
            margin: 0 auto;
            color: #000;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
        }

        .info {
            display: flex;
            gap: 40px;
            margin-bottom: 20px;
        }

        .box {
            flex: 1;
        }

        h3 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f4f4f4;
        }

        .totals {
            text-align: right;
            margin-top: 20px;
            font-size: 16px;
        }

        @media print {
            .btn-print {
                display: none;
            }
        }

        .btn-print {
            background: #000;
            color: #fff;
            border: 0;
            padding: 10px 20px;
            cursor: pointer;
            float: right;
        }
    </style>
</head>

<body>
    <div class="no-print" style="position:fixed; top:10px; right:10px; display:flex; gap:10px;">
        <button onclick="window.print()"
            style="background:#000; color:#fff; border:0; padding:10px 20px; cursor:pointer; font-weight:bold; border-radius:4px;">Imprimir
            Pedido</button>
        <a href="order-declaration.php?id=<?php echo $id; ?>"
            style="background:#e67e22; color:#fff; text-decoration:none; padding:10px 20px; cursor:pointer; font-weight:bold; border-radius:4px;">Declaração
            de Conteúdo</a>
    </div>

    <div class="header">
        <div class="logo">FIGHT ARCADE</div>
        <div style="text-align:right;">
            <h1>Pedido #<?php echo $id; ?></h1>
            <p>Data: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
        </div>
    </div>

    <div class="info">
        <div class="box">
            <h3>Cliente</h3>
            <strong><?php echo $order['name']; ?></strong><br>
            <?php if ($order['company_name'])
                echo $order['company_name'] . '<br>'; ?>
            CPF/CNPJ: <?php echo $order['document']; ?><br>
            Tel: <?php echo $order['phone']; ?><br>
            Email: <?php echo $order['email']; ?>
        </div>
        <div class="box">
            <h3>Entrega</h3>
            <?php echo $order['address']; ?>, <?php echo $order['number']; ?><br>
            <?php echo $order['neighborhood']; ?><br>
            <?php echo $order['city']; ?> - <?php echo $order['state']; ?><br>
            CEP: <?php echo $order['zipcode']; ?><br><br>
            <strong>Método:</strong> <?php echo $order['shipping_method']; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th style="width:50px;">Qtd</th>
                <th style="width:100px;">Unit.</th>
                <th style="width:100px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $item['product_name']; ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <p>Subtotal: R$ <?php echo number_format($order['total_amount'], 2, ',', '.'); ?></p>
        <p><strong>Total Geral: R$ <?php echo number_format($order['total_amount'], 2, ',', '.'); ?></strong></p>
    </div>

    <!-- SHIPPING LABEL SECTION -->
    <div style="page-break-before: always; border: 2px dashed #000; padding: 20px; margin-top: 50px;">
        <h2 style="text-align:center; margin-top:0;">ETIQUETA DE ENVIO</h2>

        <div style="display:flex; justify-content:space-between; gap:20px;">
            <!-- DESTINATARIO -->
            <div style="flex:1; padding:15px; border:1px solid #000;">
                <h3 style="margin-top:0;">DESTINATÁRIO (Para)</h3>
                <strong style="font-size:1.2em;"><?php echo $order['name']; ?></strong><br>
                <?php echo $order['address']; ?>, <?php echo $order['number']; ?><br>
                <?php echo $order['neighborhood']; ?><br>
                <?php echo $order['city']; ?> - <?php echo $order['state']; ?><br>
                <strong>CEP: <?php echo $order['zipcode']; ?></strong><br><br>
                <small>Ref: Pedido #<?php echo $id; ?></small>
            </div>

            <!-- REMETENTE -->
            <?php
            // Fetch Store Settings
            $s_file = __DIR__ . '/../includes/site_settings.json';
            $store = file_exists($s_file) ? json_decode(file_get_contents($s_file), true) : [];
            ?>
            <div style="flex:1; padding:15px; border:1px solid #000; background:#f9f9f9;">
                <h3 style="margin-top:0;">REMETENTE (De)</h3>
                <strong style="font-size:1.2em;"><?php echo $store['store_name'] ?? 'Fight Arcade'; ?></strong><br>
                <?php echo $store['address'] ?? 'Endereço da Loja não configurado'; ?><br>
                <?php echo $store['phone'] ?? ''; ?><br>
                <br>
                <small><?php echo $store['email'] ?? ''; ?></small>
            </div>
        </div>

        <div style="margin-top:20px; text-align:center;">
            <p><strong>Transportadora:</strong> <?php echo $order['shipping_method']; ?></p>
        </div>
    </div>

    <div style="margin-top: 50px; border-top: 1px dashed #000; padding-top: 10px; font-size: 12px; text-align: center;">
        Declaro ter recebido os produtos acima em perfeito estado.<br><br><br>
        _______________________________________________________________<br>
        Assinatura do Recebedor | Data: ____/____/________
    </div>
</body>

</html>