<?php
// catalogo/admin/retention.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// Fetch Data for CRM
$sql = "
    SELECT 
        u.id, 
        u.name, 
        u.phone, 
        MAX(o.created_at) as last_order_date,
        DATEDIFF(NOW(), MAX(o.created_at)) as days_since_last_order,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as lifetime_value,
        (SELECT GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ' | ') 
         FROM order_items oi 
         JOIN orders o2 ON o2.id = oi.order_id 
         WHERE o2.user_id = u.id AND o2.status IN ('paid', 'shipped')) as bought_products
    FROM users u
    JOIN orders o ON u.id = o.user_id
    WHERE o.status IN ('paid', 'shipped') AND u.role != 'admin'
    GROUP BY u.id, u.name, u.phone
    ORDER BY days_since_last_order DESC
";

$clients = $pdo->query($sql)->fetchAll();

// Stats
$stats = [
    'total_clients' => count($clients),
    'recurring' => 0,
    'at_risk' => 0, // > 30 days
    'lost' => 0 // > 90 days
];

$opportunities = []; // Between 20 and 60 days
foreach ($clients as $c) {
    if ($c['total_orders'] > 1) $stats['recurring']++;
    if ($c['days_since_last_order'] > 90) $stats['lost']++;
    else if ($c['days_since_last_order'] > 30) $stats['at_risk']++;
    
    if ($c['days_since_last_order'] >= 20 && $c['days_since_last_order'] <= 60) {
        $opportunities[] = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Retenção & Recompra | CRM</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .crm-card { background:#1a1e26; border:1px solid #333; border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
        .stat-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem; margin-bottom:1.5rem; }
        .stat-box { background:#222; border-left:4px solid var(--primary); padding:1rem; border-radius:6px; }
        .stat-num { font-size:2rem; font-weight:bold; margin:5px 0; }
        .badge { padding:4px 8px; border-radius:12px; font-size:0.8rem; font-weight:bold; }
        .badge-safe { background:rgba(46,204,113,.2); color:#2ecc71; }
        .badge-risk { background:rgba(241,196,15,.2); color:#f1c40f; }
        .badge-lost { background:rgba(231,76,60,.2); color:#e74c3c; }
        
        .whatsapp-btn { background:#25D366; color:#000; font-weight:bold; padding:8px 15px; border-radius:6px; display:inline-block; text-decoration:none; cursor:pointer; font-size:0.85rem;}
        .whatsapp-btn:hover { background:#128C7E; color:#fff;}
        
        /* Modal IA */
        .modal-ia { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.8); z-index:9999; justify-content:center; align-items:center; }
        .modal-box { background:#1a1e26; width:500px; padding:20px; border-radius:12px; border:1px solid #f39c12; }
        .modal-box textarea { width:100%; background:#111; color:#fff; border:1px solid #333; padding:10px; border-radius:6px; margin-bottom:10px; font-family:inherit;}
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <h1><i class="fas fa-bullseye" style="color:#e74c3c"></i> Retenção & Recompra (CRM)</h1>
        <p style="color:#aaa; margin-bottom:2rem;">Analise o ciclo de vida dos seus clientes e venda novamente de forma inteligente.</p>

        <!-- DASHBOARD DE RETENÇÃO -->
        <div class="stat-grid">
            <div class="stat-box" style="border-left-color:#3498db">
                <div>Total Compradores</div>
                <div class="stat-num"><?php echo $stats['total_clients']; ?></div>
                <small>Carteira Ativa</small>
            </div>
            <div class="stat-box" style="border-left-color:#2ecc71">
                <div>Recorrentes</div>
                <div class="stat-num"><?php echo $stats['recurring']; ?></div>
                <small>Compraram 2x ou mais</small>
            </div>
            <div class="stat-box" style="border-left-color:#f1c40f">
                <div>Em Risco (30+ Dias)</div>
                <div class="stat-num"><?php echo $stats['at_risk']; ?></div>
                <small>Precisam de Abordagem</small>
            </div>
            <div class="stat-box" style="border-left-color:#e74c3c">
                <div>Perdidos (90+ Dias)</div>
                <div class="stat-num"><?php echo $stats['lost']; ?></div>
                <small>Requerem Mega Oferta</small>
            </div>
        </div>

        <div class="crm-card">
            <h3>👥 Lista Inteligente de Clientes</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Última Compra</th>
                            <th>Status</th>
                            <th>Últimos Produtos (Histórico)</th>
                            <th>Pedidos/LTV</th>
                            <th>Ação IA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($clients as $c): 
                            $days = $c['days_since_last_order'];
                            $badge = 'badge-safe'; $stText = 'Recente';
                            if ($days > 90) { $badge = 'badge-lost'; $stText = 'Perdido'; }
                            else if ($days > 30) { $badge = 'badge-risk'; $stText = 'Adormecido'; }
                            
                            // Formatar produtos para não ficar gigante
                            $prods = explode(' | ', $c['bought_products']);
                            $shortProds = implode(', ', array_slice($prods, 0, 2)) . (count($prods)>2 ? '...' : '');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($c['name']); ?></strong><br>
                                <small style="color:#888"><?php echo $c['phone']; ?></small>
                            </td>
                            <td>
                                Há <strong><?php echo $days; ?></strong> dias<br>
                                <small style="color:#666"><?php echo date('d/m/Y', strtotime($c['last_order_date'])); ?></small>
                            </td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo $stText; ?></span></td>
                            <td style="max-width:250px; font-size:0.85rem; color:#ccc;">
                                <?php echo htmlspecialchars($shortProds); ?>
                            </td>
                            <td>
                                <?php echo $c['total_orders']; ?>x<br>
                                <strong style="color:#2ecc71">R$ <?php echo number_format($c['lifetime_value'], 2, ',','.'); ?></strong>
                            </td>
                            <td>
                                <button onclick="openWppModal('<?php echo addslashes($c['name']); ?>', '<?php echo addslashes($c['phone']); ?>', <?php echo $days; ?>, '<?php echo addslashes($shortProds); ?>')" class="whatsapp-btn">
                                    <i class="fab fa-whatsapp"></i> Abordar (IA)
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL WPP IA -->
    <div id="wppModal" class="modal-ia">
        <div class="modal-box">
            <h3 style="color:#25D366; margin-top:0;"><i class="fab fa-whatsapp"></i> Gerador de Mensagem</h3>
            <p style="color:#aaa; font-size:0.85rem;">Revise o texto criado automaticamente pelo sistema antes de enviar para <strong id="m_name" style="color:#fff"></strong>.</p>
            
            <textarea id="m_text" rows="6"></textarea>
            
            <div style="display:flex; justify-content:space-between; margin-top:10px;">
                <button onclick="document.getElementById('wppModal').style.display='none'" class="btn" style="background:#333; color:#fff;">Cancelar</button>
                <button onclick="sendWpp()" class="btn" style="background:#25D366; color:#000; font-weight:bold;">Abrir WhatsApp ➔</button>
            </div>
            <input type="hidden" id="m_phone">
        </div>
    </div>

    <script>
    function openWppModal(name, phone, days, products) {
        document.getElementById('wppModal').style.display = 'flex';
        document.getElementById('m_name').innerText = name;
        document.getElementById('m_phone').value = phone.replace(/\D/g, '');
        document.getElementById('m_text').value = '🤖 A IA está redigindo uma oferta personalizada...';
        
        fetch('../api/ai_retention_msg.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, days, products })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('m_text').value = data.message;
            } else {
                document.getElementById('m_text').value = 'Erro ao gerar mensagem. Tente novamente.';
            }
        });
    }

    function sendWpp() {
        let phone = document.getElementById('m_phone').value;
        let text = encodeURIComponent(document.getElementById('m_text').value);
        if(!phone.startsWith('55') && phone.length <= 11) phone = '55' + phone;
        window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
        document.getElementById('wppModal').style.display = 'none';
    }
    </script>
</body>
</html>
