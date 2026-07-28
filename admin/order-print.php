<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$id = $_GET['id'] ?? 0;
$mode = $_GET['mode'] ?? 'order'; // 'order' or 'budget'
$isBudget = ($mode === 'budget');

$stmt = $pdo->prepare("SELECT o.*, u.name, u.email, u.phone, u.document, u.city, u.state, u.zipcode, u.address, u.number, u.neighborhood, u.company_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order)
    die("Pedido não encontrado");

// Fetch items with product image via JOIN
$items = $pdo->query("SELECT oi.*, p.image_path, p.sku FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $id")->fetchAll();

// Fetch Store Settings
$s_file = __DIR__ . '/../includes/site_settings.json';
$store = file_exists($s_file) ? json_decode(file_get_contents($s_file), true) : [];

// Calculate totals
$subtotalItems = 0;
foreach ($items as $item) {
    $uPrice = $item['unit_price'] ?? $item['price'] ?? 0;
    $uTotal = $item['subtotal'] ?? ($uPrice * $item['quantity']);
    $subtotalItems += $uTotal;
}
$shippingCost = $order['shipping_cost'] ?? 0;
$discount = $order['discount_amount'] ?? 0;
$totalGeral = $order['total_amount'] ?? $subtotalItems;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isBudget ? 'Orçamento' : 'Pedido'; ?> #<?php echo $id; ?> | Fight Arcade</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            background: #f0f0f5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page {
            max-width: 820px;
            margin: 20px auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 30px rgba(0,0,0,0.08);
        }

        /* ===== HEADER ===== */
        .doc-header {
            background: linear-gradient(135deg, #0a0a1a 0%, #16213e 60%, #1a1a2e 100%);
            color: #fff;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .doc-header .brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .doc-header .brand img {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255,255,255,0.1);
            padding: 4px;
        }

        .doc-header .brand-text h1 {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: 2px;
            background: linear-gradient(90deg, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .doc-header .brand-text small {
            color: rgba(255,255,255,0.5);
            font-size: 0.72rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .doc-header .doc-meta {
            text-align: right;
        }

        .doc-header .doc-meta .doc-type {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 4px;
        }

        .doc-header .doc-meta .doc-number {
            font-size: 2rem;
            font-weight: 900;
            color: #f1c40f;
        }

        .doc-header .doc-meta .doc-date {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
        }

        /* ===== BUDGET BADGE ===== */
        .budget-badge {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #000;
            text-align: center;
            padding: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* ===== INFO SECTION ===== */
        .info-section {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #eee;
        }

        .info-box {
            flex: 1;
            padding: 24px 30px;
        }

        .info-box:first-child {
            border-right: 1px solid #eee;
        }

        .info-box .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #999;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-box .label::before {
            content: '';
            width: 3px;
            height: 12px;
            background: linear-gradient(180deg, #f1c40f, #e67e22);
            border-radius: 2px;
        }

        .info-box .name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 6px;
        }

        .info-box .detail {
            font-size: 0.82rem;
            color: #666;
            line-height: 1.6;
        }

        /* ===== ITEMS TABLE ===== */
        .items-section {
            padding: 0 30px 20px;
        }

        .items-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #999;
            font-weight: 700;
            padding: 20px 0 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .items-title::before {
            content: '';
            width: 3px;
            height: 12px;
            background: linear-gradient(180deg, #f1c40f, #e67e22);
            border-radius: 2px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        thead th {
            background: #f8f8fc;
            padding: 10px 12px;
            text-align: left;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            font-weight: 700;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        thead th:first-child { border-radius: 8px 0 0 8px; }
        thead th:last-child { border-radius: 0 8px 8px 0; text-align: right; }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid #f4f4f8;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }

        .item-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .item-thumb {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #eee;
            background: #f9f9f9;
            flex-shrink: 0;
        }

        .item-thumb-placeholder {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f0f0f5, #e8e8f0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .item-details .item-name {
            font-weight: 600;
            font-size: 0.88rem;
            color: #1a1a2e;
            line-height: 1.3;
        }

        .item-details .item-sku {
            font-size: 0.7rem;
            color: #aaa;
            margin-top: 2px;
        }

        .qty-cell {
            text-align: center;
            font-weight: 600;
            color: #555;
        }

        .price-cell {
            text-align: right;
            font-weight: 500;
            color: #555;
        }

        .total-cell {
            text-align: right;
            font-weight: 700;
            color: #1a1a2e;
        }

        /* ===== TOTALS ===== */
        .totals-section {
            padding: 0 30px 30px;
        }

        .totals-box {
            background: #f8f8fc;
            border-radius: 10px;
            padding: 20px 24px;
            margin-left: auto;
            max-width: 320px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.88rem;
            color: #666;
        }

        .totals-row.discount { color: #e74c3c; }

        .totals-divider {
            border-top: 2px solid #e0e0e8;
            margin: 8px 0;
        }

        .totals-row.grand {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1a1a2e;
            padding: 10px 0 0;
        }

        .totals-row.grand .val {
            background: linear-gradient(90deg, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* ===== BUDGET FOOTER ===== */
        .budget-footer {
            border-top: 2px solid #f1c40f;
            padding: 25px 30px;
            background: #fffef8;
        }

        .validity {
            font-size: 0.82rem;
            color: #666;
            margin-bottom: 15px;
        }

        .validity strong { color: #e67e22; }

        .conditions {
            font-size: 0.72rem;
            color: #999;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .signature-area {
            display: flex;
            gap: 40px;
            margin-top: 30px;
        }

        .sig-block {
            flex: 1;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 0.75rem;
            color: #888;
        }

        /* ===== SHIPPING LABEL ===== */
        .shipping-label {
            page-break-before: always;
            padding: 30px;
            border-top: 3px dashed #ddd;
            margin-top: 30px;
        }

        .label-title {
            text-align: center;
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #1a1a2e;
            margin-bottom: 20px;
        }

        .label-grid {
            display: flex;
            gap: 20px;
        }

        .label-box {
            flex: 1;
            border: 2px solid #1a1a2e;
            border-radius: 10px;
            padding: 20px;
        }

        .label-box h4 {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #888;
            margin-bottom: 10px;
        }

        .label-box .big-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .label-box .addr-text {
            font-size: 0.85rem;
            color: #444;
            line-height: 1.5;
        }

        .label-box .cep-highlight {
            font-size: 1rem;
            font-weight: 800;
            color: #1a1a2e;
            margin-top: 6px;
        }

        .receipt-footer {
            margin-top: 40px;
            border-top: 1px dashed #ccc;
            padding-top: 15px;
            text-align: center;
            font-size: 0.78rem;
            color: #888;
        }

        .receipt-sig-line {
            width: 300px;
            border-top: 1px solid #999;
            margin: 30px auto 5px;
        }

        /* ===== TOOLBAR (no print) ===== */
        .toolbar {
            position: fixed;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
            z-index: 1000;
        }

        .toolbar a, .toolbar button {
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-dark { background: #1a1a2e; color: #fff; }
        .btn-dark:hover { background: #16213e; transform: scale(1.03); }
        .btn-orange { background: #e67e22; color: #fff; }
        .btn-orange:hover { background: #d35400; transform: scale(1.03); }
        .btn-gold { background: linear-gradient(135deg, #f1c40f, #e67e22); color: #000; }
        .btn-gold:hover { transform: scale(1.03); box-shadow: 0 4px 15px rgba(241,196,15,0.3); }

        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .page { margin: 0; box-shadow: none; border-radius: 0; }
        }

        /* ===== STORE FOOTER BAR ===== */
        .store-bar {
            background: #f8f8fc;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.72rem;
            color: #999;
            border-top: 1px solid #eee;
        }

        .store-bar .contacts {
            display: flex;
            gap: 20px;
        }
    </style>
</head>

<body>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <button onclick="window.print()" class="btn-dark">🖨️ Imprimir</button>
        <?php if ($isBudget): ?>
            <a href="order-print.php?id=<?php echo $id; ?>" class="btn-dark">📦 Ver Pedido</a>
        <?php else: ?>
            <a href="order-print.php?id=<?php echo $id; ?>&mode=budget" class="btn-gold">📋 Orçamento</a>
        <?php endif; ?>
        <a href="order-declaration.php?id=<?php echo $id; ?>" class="btn-orange">📄 Declaração</a>
    </div>

    <div class="page">

        <!-- HEADER -->
        <div class="doc-header">
            <div class="brand">
                <img src="../assets/logo.png" alt="Logo" onerror="this.style.display='none'">
                <div class="brand-text">
                    <h1><?php echo strtoupper($store['store_name'] ?? 'FIGHT ARCADE'); ?></h1>
                    <small><?php echo $store['footer_text'] ?? 'Peças e controles arcade profissionais'; ?></small>
                </div>
            </div>
            <div class="doc-meta">
                <div class="doc-type"><?php echo $isBudget ? 'Orçamento' : 'Pedido'; ?></div>
                <div class="doc-number">#<?php echo $id; ?></div>
                <div class="doc-date"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
            </div>
        </div>

        <?php if ($isBudget): ?>
        <div class="budget-badge">⚡ PROPOSTA COMERCIAL — ORÇAMENTO SEM COMPROMISSO</div>
        <?php endif; ?>

        <!-- CLIENT + DELIVERY INFO -->
        <div class="info-section">
            <div class="info-box">
                <div class="label">Cliente</div>
                <div class="name"><?php echo $order['name']; ?></div>
                <?php if ($order['company_name']): ?>
                    <div class="detail"><?php echo $order['company_name']; ?></div>
                <?php endif; ?>
                <div class="detail">
                    CPF/CNPJ: <?php echo $order['document']; ?><br>
                    Tel: <?php echo $order['phone']; ?><br>
                    <?php echo $order['email']; ?>
                </div>
            </div>
            <div class="info-box">
                <div class="label"><?php echo $isBudget ? 'Endereço de Entrega (Estimado)' : 'Entrega'; ?></div>
                <div class="detail">
                    <?php echo $order['address']; ?>, <?php echo $order['number']; ?><br>
                    <?php echo $order['neighborhood']; ?><br>
                    <?php echo $order['city']; ?> - <?php echo $order['state']; ?><br>
                    CEP: <?php echo $order['zipcode']; ?>
                    <?php if (!$isBudget && $order['shipping_method']): ?>
                        <br><br><strong>Método:</strong> <?php echo $order['shipping_method']; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ITEMS -->
        <div class="items-section">
            <div class="items-title">Itens <?php echo $isBudget ? 'do Orçamento' : 'do Pedido'; ?></div>
            <table>
                <thead>
                    <tr>
                        <th style="width:55%;">Produto</th>
                        <th style="width:10%; text-align:center;">Qtd</th>
                        <th style="width:17%; text-align:right;">Unitário</th>
                        <th style="width:18%; text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item):
                        $uPrice = $item['unit_price'] ?? $item['price'] ?? 0;
                        $uTotal = $item['subtotal'] ?? ($uPrice * $item['quantity']);
                        $imgPath = '';
                        if (!empty($item['image_path'])) {
                            $imgPath = '../assets/uploads/' . $item['image_path'];
                        }
                    ?>
                        <tr>
                            <td>
                                <div class="item-cell">
                                    <?php if ($imgPath): ?>
                                        <img class="item-thumb" src="<?php echo $imgPath; ?>" alt="">
                                    <?php else: ?>
                                        <div class="item-thumb-placeholder">📦</div>
                                    <?php endif; ?>
                                    <div class="item-details">
                                        <div class="item-name"><?php echo $item['product_name']; ?></div>
                                        <?php if (!empty($item['sku'])): ?>
                                            <div class="item-sku">SKU: <?php echo $item['sku']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="qty-cell"><?php echo $item['quantity']; ?></td>
                            <td class="price-cell">R$ <?php echo number_format($uPrice, 2, ',', '.'); ?></td>
                            <td class="total-cell">R$ <?php echo number_format($uTotal, 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TOTALS -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="totals-row">
                    <span>Subtotal dos itens</span>
                    <span>R$ <?php echo number_format($subtotalItems, 2, ',', '.'); ?></span>
                </div>
                <?php if ($shippingCost > 0): ?>
                <div class="totals-row">
                    <span>Frete (<?php echo $order['shipping_method'] ?? ''; ?>)</span>
                    <span>R$ <?php echo number_format($shippingCost, 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($discount > 0): ?>
                <div class="totals-row discount">
                    <span>Desconto</span>
                    <span>- R$ <?php echo number_format($discount, 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                <div class="totals-divider"></div>
                <div class="totals-row grand">
                    <span>Total</span>
                    <span class="val">R$ <?php echo number_format($totalGeral, 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- STORE CONTACTS BAR -->
        <div class="store-bar">
            <span><?php echo $store['store_name'] ?? 'Fight Arcade'; ?></span>
            <div class="contacts">
                <?php if (!empty($store['phone'])): ?>
                    <span>📞 <?php echo $store['phone']; ?></span>
                <?php endif; ?>
                <?php if (!empty($store['email'])): ?>
                    <span>✉️ <?php echo $store['email']; ?></span>
                <?php endif; ?>
                <?php if (!empty($store['social_instagram'])): ?>
                    <span>📸 @fightarcade</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isBudget): ?>
        <!-- BUDGET FOOTER -->
        <div class="budget-footer">
            <div class="validity">
                ⏰ Este orçamento é válido por <strong>15 dias</strong> a partir da data de emissão (<?php echo date('d/m/Y'); ?>).
            </div>
            <div class="conditions">
                <strong>Condições:</strong><br>
                • Os valores acima não incluem frete, que será calculado no momento da confirmação do pedido.<br>
                • Os preços estão sujeitos à disponibilidade de estoque.<br>
                • Este documento não constitui compromisso de venda e serve apenas como referência comercial.<br>
                • Para confirmar este orçamento, entre em contato pelo WhatsApp ou e-mail indicados acima.
            </div>
            <div class="signature-area">
                <div class="sig-block">
                    <div style="height:50px;"></div>
                    <div class="sig-line"><?php echo $store['store_name'] ?? 'Fight Arcade'; ?> — Vendedor</div>
                </div>
                <div class="sig-block">
                    <div style="height:50px;"></div>
                    <div class="sig-line"><?php echo $order['name']; ?> — Cliente</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isBudget): ?>
        <!-- SHIPPING LABEL -->
        <div class="shipping-label">
            <div class="label-title">Etiqueta de Envio</div>
            <div class="label-grid">
                <!-- DESTINATÁRIO -->
                <div class="label-box">
                    <h4>Destinatário (Para)</h4>
                    <div class="big-name"><?php echo $order['name']; ?></div>
                    <div class="addr-text">
                        <?php echo $order['address']; ?>, <?php echo $order['number']; ?><br>
                        <?php echo $order['neighborhood']; ?><br>
                        <?php echo $order['city']; ?> - <?php echo $order['state']; ?>
                    </div>
                    <div class="cep-highlight">CEP: <?php echo $order['zipcode']; ?></div>
                    <div style="font-size:0.75rem; color:#888; margin-top:6px;">Ref: Pedido #<?php echo $id; ?></div>
                </div>

                <!-- REMETENTE -->
                <div class="label-box" style="background:#f8f8fc;">
                    <h4>Remetente (De)</h4>
                    <div class="big-name"><?php echo $store['store_name'] ?? 'Fight Arcade'; ?></div>
                    <div class="addr-text">
                        <?php echo $store['address'] ?? 'Endereço não configurado'; ?><br>
                        <?php echo $store['phone'] ?? ''; ?><br>
                        <small><?php echo $store['email'] ?? ''; ?></small>
                    </div>
                </div>
            </div>

            <div style="text-align:center; margin-top:15px; font-size:0.82rem; color:#666;">
                <strong>Transportadora:</strong> <?php echo $order['shipping_method']; ?>
            </div>
        </div>

        <!-- RECEIPT SIGNATURE -->
        <div class="receipt-footer">
            Declaro ter recebido os produtos acima em perfeito estado.
            <div class="receipt-sig-line"></div>
            Assinatura do Recebedor | Data: ____/____/________
        </div>
        <?php endif; ?>

    </div>
</body>
</html>