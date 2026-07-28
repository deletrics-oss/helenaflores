<?php
/**
 * admin/order-print-tag.php — Helena Flores
 * Gerador de Etiquetas e Tags Dinâmicas para Impressão (Buquês, Cestas & Presentes)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$orderId) {
    die("Pedido não especificado.");
}

// Fetch Order, Items & Customer
$stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone, u.address, u.number, u.complement, u.neighborhood, u.city, u.state 
                       FROM orders o 
                       LEFT JOIN users u ON o.user_id = u.id 
                       WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Pedido #{$orderId} não encontrado.");
}

$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItems->execute([$orderId]);
$items = $stmtItems->fetchAll();

$dedicatory = $order['admin_notes'] ?? 'Desejamos que o seu dia seja repleto de beleza e amor!';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas de Impressão — Pedido #<?php echo $orderId; ?> | Helena Flores</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@600;700&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f0f0; font-family: 'Inter', sans-serif; padding: 20px; color: #2C2C2C; }

        .no-print-bar {
            background: #1B3B2B; color: white; padding: 15px; border-radius: 10px; max-width: 900px;
            margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center;
        }

        .btn-print {
            background: #C5A059; color: white; border: none; padding: 10px 20px; border-radius: 6px;
            font-weight: bold; cursor: pointer; font-size: 1rem;
        }

        .print-sheet {
            background: white; max-width: 900px; margin: 0 auto; padding: 30px; border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); display: flex; flex-direction: column; gap: 30px;
        }

        /* MODELO 1: TAG REDONDA CLÁSSICA */
        .tag-round-container {
            display: flex; gap: 20px; align-items: center; border-bottom: 2px dashed #EFECE6; padding-bottom: 25px;
        }
        .tag-round {
            width: 140px; height: 140px; background: #8B263E; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #F8D7DA; text-align: center; position: relative; box-shadow: 0 4px 10px rgba(139,38,62,0.3);
        }
        .tag-round::before {
            content: ''; position: absolute; top: 12px; width: 10px; height: 10px;
            background: white; border-radius: 50%; border: 2px solid #8B263E;
        }
        .tag-round .logo-top { font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 700; line-height: 1.1; margin-top: 10px; }
        .tag-round .logo-sub { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #E8C3C8; margin-top: 4px; }

        /* MODELO 2: ETIQUETA RETANGULAR DE IDENTIFICAÇÃO & ENTREGA */
        .tag-delivery {
            border: 2px solid #8B263E; border-radius: 12px; overflow: hidden; width: 100%; background: #FFF;
        }
        .tag-delivery-header {
            background: #8B263E; color: white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;
        }
        .tag-delivery-header h2 { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: #F9F5EC; }
        .tag-delivery-body { padding: 20px; display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .info-group { margin-bottom: 10px; }
        .info-group label { font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; display: block; }
        .info-group span { font-size: 1rem; font-weight: 600; color: #1B3B2B; }

        /* MODELO 3: ETIQUETA DE CUIDADOS */
        .tag-care {
            background: #FDF6F7; border: 1px solid #F5C6CB; border-radius: 10px; padding: 20px;
        }
        .tag-care h3 { font-family: 'Playfair Display', serif; color: #8B263E; margin-bottom: 12px; text-align: center; }
        .care-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; text-align: center; }
        .care-item { background: white; padding: 12px; border-radius: 8px; border: 1px solid #EFECE6; }
        .care-item .icon { font-size: 1.8rem; margin-bottom: 6px; }
        .care-item p { font-size: 0.75rem; font-weight: 600; color: #2C2C2C; }

        /* MODELO 4: TAG DE LUXO COM DOBRA (CARTÃO DEDICATÓRIA) */
        .tag-folding {
            border: 1px solid #C5A059; border-radius: 12px; display: grid; grid-template-columns: 1fr 1fr;
            min-height: 180px; overflow: hidden; background: #FFFDFB;
        }
        .tag-fold-cover {
            background: #8B263E; color: #F9F5EC; padding: 25px; display: flex; flex-direction: column;
            justify-content: center; align-items: center; text-align: center; position: relative;
        }
        .tag-fold-cover::after {
            content: ''; position: absolute; right: 0; top: 0; bottom: 0; width: 1px; border-right: 2px dashed #C5A059;
        }
        .tag-fold-inside {
            padding: 25px; display: flex; flex-direction: column; justify-content: space-between; background: #FFFDFB;
        }
        .tag-fold-inside h4 { font-family: 'Playfair Display', serif; color: #8B263E; font-size: 1.1rem; }
        .tag-fold-inside p { font-style: italic; color: #4A4A4A; font-size: 0.95rem; margin: 12px 0; line-height: 1.5; }

        @media print {
            body { background: white; padding: 0; }
            .no-print-bar { display: none; }
            .print-sheet { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <strong style="font-size:1.1rem;">🏷️ Gerador de Etiquetas & Tags Helena Flores</strong><br>
            <small>Pedido #<?php echo $orderId; ?> — <?php echo htmlspecialchars($order['customer_name'] ?? 'Cliente'); ?></small>
        </div>
        <button onclick="window.print()" class="btn-print">🖨️ IMPRIMIR ETIQUETAS</button>
    </div>

    <div class="print-sheet">

        <!-- MODELO 1: TAG REDONDA DE LUXO -->
        <div class="tag-round-container">
            <div class="tag-round">
                <div class="logo-top">helena<br>flores</div>
                <div class="logo-sub">Atelier & Floricultura</div>
            </div>
            <div>
                <h3 style="color:#8B263E; font-family:'Playfair Display', serif;">Modelo 1: Tag Redonda de Luxo para Buquê</h3>
                <p style="font-size:0.85rem; color:#666; margin-top:4px;">
                    Perfeita para amarração com fita de cetim ou cordão de ráfia diretamente no cabo do buquê ou arranjo.
                </p>
            </div>
        </div>

        <!-- MODELO 2: ETIQUETA RETANGULAR DE IDENTIFICAÇÃO E ENTREGA -->
        <div>
            <h3 style="color:#8B263E; font-family:'Playfair Display', serif; margin-bottom:10px;">Modelo 2: Etiqueta de Identificação & Entrega</h3>
            <div class="tag-delivery">
                <div class="tag-delivery-header">
                    <h2>🌹 Helena Flores — Pedido #<?php echo $orderId; ?></h2>
                    <span style="font-size:0.85rem; background:rgba(255,255,255,0.2); padding:4px 10px; border-radius:12px;">
                        Data: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                    </span>
                </div>
                <div class="tag-delivery-body">
                    <div>
                        <div class="info-group">
                            <label>Destinatário (Para):</label>
                            <span><?php echo htmlspecialchars($order['customer_name'] ?? 'Cliente'); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Endereço de Entrega:</label>
                            <span>
                                <?php echo htmlspecialchars($order['address'] ?? 'Alameda Jaú, 1777'); ?>, <?php echo htmlspecialchars($order['number'] ?? 'SN'); ?> 
                                <?php echo !empty($order['complement']) ? ' - ' . htmlspecialchars($order['complement']) : ''; ?><br>
                                <?php echo htmlspecialchars($order['neighborhood'] ?? 'Jardim Paulista'); ?> — <?php echo htmlspecialchars($order['city'] ?? 'São Paulo'); ?>/<?php echo htmlspecialchars($order['state'] ?? 'SP'); ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <label>Telefone do Cliente:</label>
                            <span><?php echo htmlspecialchars($order['phone'] ?? '(11) 98672-7872'); ?></span>
                        </div>
                        <div class="info-group" style="margin-bottom:0;">
                            <label>Item / Arranjo:</label>
                            <span>
                                <?php foreach ($items as $item): ?>
                                    • <?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)<br>
                                <?php endforeach; ?>
                            </span>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; border-left:1px solid #EFECE6; padding-left:15px; text-align:center;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=https://helenafloresjardins.com.br/catalogo/my-orders.php" alt="QR Code" style="border:1px solid #DDD; padding:4px; border-radius:6px;">
                        <span style="font-size:0.65rem; color:#888; margin-top:6px; font-weight:bold;">Rastrear no Site</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODELO 3: ETIQUETA DE CUIDADOS COM AS FLORES -->
        <div>
            <h3 style="color:#8B263E; font-family:'Playfair Display', serif; margin-bottom:10px;">Modelo 3: Etiqueta de Cuidados com o Buquê</h3>
            <div class="tag-care">
                <h3>🌸 Como fazer suas Flores durarem mais tempo:</h3>
                <div class="care-grid">
                    <div class="care-item">
                        <div class="icon">💧</div>
                        <p>Troque a água limpa diariamente</p>
                    </div>
                    <div class="care-item">
                        <div class="icon">✂️</div>
                        <p>Corte 1cm dos caules em ângulo 45°</p>
                    </div>
                    <div class="care-item">
                        <div class="icon">☀️</div>
                        <p>Evite sol direto e correntes de ar</p>
                    </div>
                    <div class="care-item">
                        <div class="icon">🌿</div>
                        <p>Remova folhas submersas na água</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODELO 4: TAG DE LUXO COM DOBRA (CARTÃO DEDICATÓRIA) -->
        <div>
            <h3 style="color:#8B263E; font-family:'Playfair Display', serif; margin-bottom:10px;">Modelo 4: Tag de Luxo com Dobra (Cartão de Mensagem)</h3>
            <div class="tag-folding">
                <div class="tag-fold-cover">
                    <h2 style="font-family:'Playfair Display', serif; font-size:1.8rem; color:#F9F5EC;">helena<br>flores</h2>
                    <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; margin-top:8px; color:#E8C3C8;">
                        Com todo carinho
                    </p>
                </div>
                <div class="tag-fold-inside">
                    <div>
                        <h4>Mensagem Especial:</h4>
                        <p>"<?php echo htmlspecialchars($dedicatory); ?>"</p>
                    </div>
                    <div style="border-top:1px solid #C5A059; padding-top:8px; display:flex; justify-content:space-between; font-size:0.75rem; color:#888;">
                        <span>Helena Flores — Jardins</span>
                        <span>(11) 98672-7872</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
