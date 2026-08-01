<?php
/**
 * my-orders.php — Helena Flores (Meus Pedidos do Cliente)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// 1. Check Authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $baseUrl . "/login.php?redirect=my-orders.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// 2. Fetch Customer Details
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$customer = $stmtUser->fetch(PDO::FETCH_ASSOC);

// 3. Fetch Orders
$stmtOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmtOrders->execute([$user_id]);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

// Helper for status badge style
function getOrderStatusBadge($status) {
    switch (strtolower(trim($status))) {
        case 'delivered':
        case 'entregue':
        case 'completed':
            return '<span style="background:#E8F5E9; color:#2E7D32; padding:6px 14px; border-radius:20px; font-weight:bold; font-size:0.82rem;">✅ Entregue</span>';
        case 'in_transit':
        case 'em trânsito':
        case 'released':
            return '<span style="background:#E3F2FD; color:#1565C0; padding:6px 14px; border-radius:20px; font-weight:bold; font-size:0.82rem;">🚚 Em Trânsito</span>';
        case 'canceled':
        case 'cancelado':
            return '<span style="background:#FFEBEE; color:#C2185B; padding:6px 14px; border-radius:20px; font-weight:bold; font-size:0.82rem;">❌ Cancelado</span>';
        default:
            return '<span style="background:#FFF8E1; color:#F57F17; padding:6px 14px; border-radius:20px; font-weight:bold; font-size:0.82rem;">⏳ Pendente / Em Preparo</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos | Helena Flores</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .order-card {
            background: #FFFFFF; border: 1px solid #EEEEEE; border-radius: 16px; padding: 1.8rem;
            margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .order-header {
            display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F0F0F0;
            padding-bottom: 1rem; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 10px;
        }
        .order-item-row {
            display: flex; align-items: center; gap: 15px; padding: 10px 0; border-bottom: 1px dashed #F5F5F5;
        }
        .order-item-row img {
            width: 55px; height: 55px; object-fit: cover; border-radius: 8px; border: 1px solid #EEE;
        }
        @media (max-width: 768px) {
            .order-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div style="max-width:1240px; margin: 2rem auto; padding: 0 20px; flex:1;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:15px;">
            <div>
                <h1 style="font-size:1.8rem; font-weight:800; color:var(--gf-magenta-dark); margin:0;">
                    📦 Meus Pedidos
                </h1>
                <p style="color:#666; font-size:0.9rem; margin-top:4px;">
                    Olá, <strong><?php echo htmlspecialchars($customer['name'] ?? 'Cliente'); ?></strong>! Aqui está o histórico das suas encomendas na Helena Flores.
                </p>
            </div>
            <a href="<?php echo $baseUrl; ?>/logout.php" style="color:#C2185B; font-weight:bold; text-decoration:none; font-size:0.9rem; border:1px solid #FCE4EC; padding:8px 18px; border-radius:20px; background:#FFF8F9;">
                Sair da Conta
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div style="background:#FFF; border:1px solid #EEE; border-radius:16px; padding:3rem; text-align:center; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
                <div style="font-size:3.5rem; margin-bottom:1rem;">🌸</div>
                <h2 style="font-size:1.4rem; font-weight:800; color:#333; margin-bottom:8px;">Você ainda não possui pedidos realizados.</h2>
                <p style="color:#666; margin-bottom:1.5rem;">Explore nosso catálogo e surpreenda quem você ama com buquês e cestas exclusivas!</p>
                <a href="<?php echo $baseUrl; ?>/" class="gf-btn-primary" style="display:inline-block; text-decoration:none; padding:12px 30px; border-radius:25px; font-weight:bold;">
                    🌹 Ver Flores Disponíveis
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <?php 
                // Fetch Items
                $stmtItems = $pdo->prepare("SELECT oi.*, p.image_path FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                $stmtItems->execute([$o['id']]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <span style="font-size:1.1rem; font-weight:800; color:var(--gf-magenta-dark);">
                                Pedido #<?php echo str_pad($o['id'], 5, '0', STR_PAD_LEFT); ?>
                            </span>
                            <span style="font-size:0.85rem; color:#888; margin-left:12px;">
                                📅 <?php echo date('d/m/Y \à\s H:i', strtotime($o['created_at'])); ?>
                            </span>
                        </div>
                        <div>
                            <?php echo getOrderStatusBadge($o['status']); ?>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div style="margin-bottom:1.2rem;">
                        <?php foreach ($items as $it): ?>
                            <?php 
                            $itName = $it['product_name'] ?? $it['name'] ?? 'Produto Helena Flores';
                            $itPrice = $it['unit_price'] ?? $it['price'] ?? 0;
                            $itQty = $it['quantity'] ?? $it['qty'] ?? 1;
                            $itImg = get_product_image_url($it['image_path'] ?? '', $itName);
                            ?>
                            <div class="order-item-row">
                                <img src="<?php echo $itImg; ?>" alt="<?php echo htmlspecialchars($itName); ?>">
                                <div style="flex:1;">
                                    <strong style="font-size:0.95rem; color:#222;"><?php echo htmlspecialchars($itName); ?></strong>
                                    <div style="font-size:0.8rem; color:#666; margin-top:2px;">
                                        Qtd: <?php echo $itQty; ?> x R$ <?php echo number_format($itPrice, 2, ',', '.'); ?>
                                    </div>
                                </div>
                                <strong style="font-size:0.95rem; color:var(--gf-magenta-dark);">
                                    R$ <?php echo number_format($itPrice * $itQty, 2, ',', '.'); ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Address & Actions -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; background:#FAFAFA; border-radius:12px; padding:1.2rem;">
                        <div style="font-size:0.88rem; color:#555;">
                            📍 <strong>Endereço de Entrega:</strong> <?php echo htmlspecialchars($o['shipping_address'] ?? 'Entrega Expressa SP'); ?>
                        </div>
                        <div style="display:flex; align-items:center; gap:15px;">
                            <span style="font-size:1.2rem; font-weight:800; color:var(--gf-magenta-dark);">
                                Total: R$ <?php echo number_format($o['total_amount'], 2, ',', '.'); ?>
                            </span>
                            <a href="https://wa.me/5511986727872?text=Ol%C3%A1!%20Gostaria%20de%20informa%C3%A7%C3%B5es%20sobre%20meu%20Pedido%20%23<?php echo $o['id']; ?>" 
                               target="_blank" class="gf-btn-whatsapp" style="height:38px; font-size:0.85rem; padding:0 16px; border-radius:20px; text-decoration:none;">
                                💬 WhatsApp Suporte
                            </a>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>