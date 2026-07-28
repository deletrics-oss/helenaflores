<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$id = (int)$_GET['id'];
$purchase = $pdo->query("SELECT po.*, s.name as supplier_name, s.phone as supplier_phone, s.address as supplier_address, s.email as supplier_email, s.contact_name 
                        FROM purchase_orders po 
                        JOIN suppliers s ON po.supplier_id = s.id 
                        WHERE po.id = $id")->fetch();

if (!$purchase) die("Pedido de compra não encontrado.");

$items = $pdo->query("SELECT poi.*, p.sku FROM purchase_order_items poi LEFT JOIN products p ON poi.product_id = p.id WHERE purchase_order_id = $id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pedido de Compra #<?php echo $id; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; padding: 40px; line-height: 1.6; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #f1c40f; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #000; }
        .logo span { color: #f1c40f; }
        .order-info { text-align: right; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .section-title { font-size: 14px; text-transform: uppercase; color: #888; font-weight: bold; border-bottom: 1px solid #eee; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f9f9f9; text-align: left; padding: 12px; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .total-box { text-align: right; font-size: 18px; font-weight: bold; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
        @media print {
            .no-print { display: none; }
            body { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 30px; background: #f1c40f; border: none; font-weight: bold; cursor: pointer; border-radius: 5px;">🖨️ IMPRIMIR PEDIDO</button>
        <button onclick="window.close()" style="padding: 10px 30px; background: #eee; border: none; cursor: pointer; border-radius: 5px; margin-left: 10px;">Fechar</button>
    </div>

    <div class="header">
        <div class="logo">Fight<span>Arcade</span> Compra</div>
        <div class="order-info">
            <div style="font-size: 20px; font-weight: bold;">PEDIDO #<?php echo $id; ?></div>
            <div style="color: #666;">Data: <?php echo date('d/m/Y H:i', strtotime($purchase['created_at'])); ?></div>
            <div style="color: #666;">Status: <?php echo strtoupper($purchase['status']); ?></div>
        </div>
    </div>

    <div class="grid">
        <div>
            <div class="section-title">Fornecedor</div>
            <strong><?php echo htmlspecialchars($purchase['supplier_name']); ?></strong><br>
            <?php if($purchase['contact_name']) echo "A/C: " . htmlspecialchars($purchase['contact_name']) . "<br>"; ?>
            <?php echo htmlspecialchars($purchase['supplier_address']); ?><br>
            <?php echo htmlspecialchars($purchase['supplier_phone']); ?><br>
            <?php echo htmlspecialchars($purchase['supplier_email']); ?>
        </div>
        <div>
            <div class="section-title">Destinatário</div>
            <strong>Fight Arcade</strong><br>
            Daniel Souza<br>
            Rua Cristiano Osorio, 143<br>
            Vila Esperança, São Paulo - SP<br>
            CEP: 03611-060
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produto / SKU</th>
                <th style="text-align: center;">Qtd</th>
                <th style="text-align: right;">Vlr Unit.</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                    <small style="color: #888;">SKU: <?php echo htmlspecialchars($item['sku'] ?: 'N/A'); ?></small>
                </td>
                <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                <td style="text-align: right;">R$ <?php echo number_format($item['unit_cost'], 2, ',', '.'); ?></td>
                <td style="text-align: right;">R$ <?php echo number_format($item['unit_cost'] * $item['quantity'], 2, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-box">
        TOTAL DO PEDIDO: R$ <?php echo number_format($purchase['total_amount'], 2, ',', '.'); ?>
    </div>

    <div class="footer">
        Este documento é uma solicitação oficial de compra da Fight Arcade.<br>
        Gerado automaticamente pelo sistema de gestão em <?php echo date('d/m/Y H:i'); ?>.
    </div>
</body>
</html>
