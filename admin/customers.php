<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// --- GLOBAL ERROR HANDLING ---
try {
    // --- DB INTEGRITY CHECK ---
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_lead TINYINT(1) DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS current_debt DECIMAL(10,2) DEFAULT 0.00");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'manual'");
    } catch(Exception $e) {}

// --- AJAX HANDLERS ---

// 1. Personalized Individual Msg
if (isset($_GET['ajax_personalized_msg'])) {
    header('Content-Type: application/json');
    $uid = (int)$_GET['id'];
    $msg = $_GET['msg'];
    $u = $pdo->query("SELECT phone, name FROM users WHERE id = $uid")->fetch();
    if ($u && !empty($u['phone'])) {
        require_once __DIR__ . '/../includes/notifications.php';
        $notif = new NotificationService($pdo);
        $res = $notif->notifyCustomer($u['phone'], $msg);
        echo json_encode(['success' => $res]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Este cliente não possui um número de WhatsApp cadastrado.']);
    }
    exit;
}

// 2. Bulk Marketing
if (isset($_GET['ajax_bulk_marketing'])) {
    header('Content-Type: application/json');
    $uid = (int)$_GET['id'];
    $template = $_GET['template'] ?? 'custom';
    $customMsg = $_GET['msg'] ?? '';

    require_once __DIR__ . '/../includes/notifications.php';
    $notif = new NotificationService($pdo);

    $user = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
    if (!$user || empty($user['phone'])) {
        echo json_encode(['success' => false, 'error' => 'Sem número']);
        exit;
    }

    $msg = "";
    if ($template === 'bomdia') {
        $msg = "Olá *" . $user['name'] . "*! 👋 Passando para desejar um ótimo dia da equipe *Fight Arcade*! Como estão as jogatinas por aí? 🕹️";
    } elseif ($template === 'winback') {
        $lastProduct = $pdo->query("SELECT p.name FROM orders o JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id WHERE o.user_id = $uid ORDER BY o.created_at DESC LIMIT 1")->fetchColumn();
        $prodStr = $lastProduct ? "seu *$lastProduct*" : "seu setup";
        $msg = "Olá *" . $user['name'] . "*! 👋 Vimos que faz tempo que você não aparece por aqui. Que tal um upgrade no $prodStr? Temos novidades incríveis na Fight Arcade! 🕹️🔥";
    } else {
        $msg = str_replace('{nome}', $user['name'], $customMsg);
    }

    $res = $notif->notifyCustomer($user['phone'], $msg);
    echo json_encode(['success' => $res]);
    exit;
}

// 3. Quick Notify (Remind/Me)
if (isset($_GET['ajax_notify'])) {
    header('Content-Type: application/json');
    $uid = (int)$_GET['id'];
    require_once __DIR__ . '/../includes/notifications.php';
    $notif = new NotificationService($pdo);

    $user = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
    if (!$user) { echo json_encode(['success' => false, 'error' => 'Não encontrado']); exit; }

    if (empty($user['phone'])) { echo json_encode(['success' => false, 'error' => 'Sem telefone']); exit; }
    
    $debt = (float)($user['current_debt'] ?? 0);
    $msg = ($debt > 0) 
        ? "Olá *" . $user['name'] . "*! 👋 Lembramos que você possui um saldo de *R$ " . number_format($debt, 2, ',', '.') . "* em aberto. Caso queira o PIX ou negociar, fale com a gente! 🕹️"
        : "Olá *" . $user['name'] . "*! 👋 Passando para desejar um ótimo dia e saber se podemos ajudar em algo hoje? 🕹️";
    
    $res = $notif->notifyCustomer($user['phone'], $msg);
    echo json_encode(['success' => $res]);
    exit;
}

// 4. Global Balance Sync
if (isset($_GET['ajax_sync_balances'])) {
    header('Content-Type: application/json');
    try {
        $pdo->exec("UPDATE users u SET current_debt = (
            (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
            (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
        )");
        echo json_encode(['success' => true]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
// 5. Send Debt Reminder with Specific Account AND ITEMS
if (isset($_GET['ajax_send_debt_reminder'])) {
    header('Content-Type: application/json');
    $uid = (int)$_GET['user_id'];
    $accId = (int)$_GET['account_id'];

    require_once __DIR__ . '/../includes/notifications.php';
    $notif = new NotificationService($pdo);

    $user = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
    $acc = $pdo->query("SELECT * FROM payment_accounts WHERE id = $accId")->fetch();

    if (!$user || !$acc) {
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit;
    }

    $debt = (float)($user['current_debt'] ?? 0);
    if ($debt <= 0) {
        echo json_encode(['success' => false, 'error' => 'Este cliente não possui dívida ativa.']);
        exit;
    }

    // Fetch items from non-pending/canceled orders
    $itemsQuery = $pdo->query("
        SELECT oi.product_name, oi.quantity, o.id as order_id 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = $uid AND o.status != 'canceled'
        ORDER BY o.created_at DESC LIMIT 15
    ")->fetchAll();

    $itemsList = "";
    foreach ($itemsQuery as $it) {
        $itemsList .= "• " . $it['quantity'] . "x " . $it['product_name'] . " (Ped. #{$it['order_id']})\n";
    }

    $res = $notif->sendDebtReminder($user['phone'], $user['name'], $debt, $acc['name'], $acc['pix_key'], $acc['bank_info'], $itemsList);
    echo json_encode(['success' => $res]);
    exit;
}

// --- STANDARD POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_ids = $_POST['selected_ids'] ?? [];
    if (isset($_POST['bulk_delete']) && !empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $pdo->query("DELETE FROM users WHERE id IN ($ids) AND role != 'admin'");
        $passMsg = "<div class='alert alert-success'>🗑️ Clientes excluídos com sucesso!</div>";
    }
    if (isset($_POST['bulk_make_customer']) && !empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $pdo->exec("UPDATE users SET is_lead = 0 WHERE id IN ($ids)");
        $passMsg = "<div class='alert alert-success'>✅ Leads convertidos em Clientes!</div>";
    }
    if (isset($_POST['bulk_stealth_on']) && !empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $pdo->exec("UPDATE users SET hide_store_name = 1 WHERE id IN ($ids)");
        $passMsg = "<div class='alert alert-success'>🕵️ Modo Stealth ATIVADO para os selecionados!</div>";
    }
    if (isset($_POST['bulk_stealth_off']) && !empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $pdo->exec("UPDATE users SET hide_store_name = 0 WHERE id IN ($ids)");
        $passMsg = "<div class='alert alert-success'>🔓 Modo Stealth DESATIVADO para os selecionados!</div>";
    }
}

// Single actions
if (isset($_GET['make_customer'])) {
    $uid = (int)$_GET['make_customer'];
    $pdo->prepare("UPDATE users SET is_lead = 0 WHERE id = ?")->execute([$uid]);
    $passMsg = "<div class='alert alert-success'>✅ Lead convertido com sucesso!</div>";
}

// --- DATA FETCH ---
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as total_orders,
        (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id) as total_spent,
        (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id) as total_paid
        FROM users u WHERE role != 'admin'";

if ($filter === 'debtors') $sql .= " AND (current_debt > 0 OR ((SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)) > 0)";
elseif ($filter === 'leads') $sql .= " AND is_lead = 1";
else $sql .= " AND is_lead = 0";

if ($search) {
    $sql .= " AND (name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3 OR document LIKE :q4)";
}
$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
if ($search) {
    $searchTerm = "%$search%";
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
    $stmt->bindValue(':q4', $searchTerm);
}
$stmt->execute();
$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Clientes | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 1.5rem; }
        .filter-tab { padding: 8px 18px; background: #222; border: 1px solid #444; border-radius: 6px; color: #ccc; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .filter-tab.active { background: var(--primary); color: #000; font-weight: bold; }
        
        .btn-wa-notif { background: #25d366; color: #fff; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-wa-notif:hover { transform: scale(1.05); filter: brightness(1.1); }
        .btn-wa-notif.loading { background: #555; pointer-events: none; }
        .btn-wa-notif.success { background: #27ae60; }
        .btn-wa-notif.error { background: #e74c3c; }

        .modal-vip { display: none; justify-content: center; align-items: center; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); }
        .modal-vip-content { background: #1a1e2a; padding: 2.5rem; border: 1px solid #333; width: 550px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 16px; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .close-vip { position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; color: #666; transition: 0.2s; z-index: 1; }
        .close-vip:hover { color: #fff; }

        .campaign-option { display: block; padding: 15px; border: 1px solid #333; border-radius: 10px; margin-bottom: 12px; cursor: pointer; background: #111; transition: 0.2s; }
        .campaign-option:hover { border-color: #9b59b6; background: #161a24; }
        .campaign-option input { margin-right: 12px; }
        .campaign-option strong { display: block; color: #9b59b6; margin-bottom: 4px; }
        .campaign-option span { font-size: 0.8rem; color: #777; }

        .progress-container { margin-top: 2rem; display: none; }
        .progress-bar { height: 8px; background: #333; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.3s; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <?php if (isset($passMsg)) echo $passMsg; ?>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h1>Gerenciamento de Clientes</h1>
            <a href="customer-create.php" class="btn">Novo Cliente / Lead</a>
        </div>

        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">👥 Todos</a>
            <a href="?filter=debtors" class="filter-tab <?php echo $filter === 'debtors' ? 'active' : ''; ?>">🔴 Devedores</a>
            <a href="?filter=leads" class="filter-tab <?php echo $filter === 'leads' ? 'active' : ''; ?>">📢 Leads</a>
            <button onclick="syncAllBalances(this)" class="filter-tab" style="cursor:pointer; border-color:#2ecc71; color:#2ecc71; margin-left:auto;">🔄 Sincronizar Saldos</button>
            <a href="payment_accounts.php" class="filter-tab" style="border-color:#f1c40f; color:#f1c40f;">💳 Gerenciar Contas</a>
        </div>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:1.5rem;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="q" placeholder="Buscar por nome, telefone ou email..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding:12px; background:#111; border:1px solid #333; color:#fff; border-radius:8px;">
            <button type="submit" class="btn">🔍 Buscar</button>
        </form>

        <form method="POST">
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-bottom:1rem; align-items:center;">
                <button type="button" onclick="openMarketingModal()" class="btn" style="background:var(--primary); color:#000;">🚀 DISPARAR CAMPANHA</button>
                <?php if($filter==='leads'): ?>
                    <button type="submit" name="bulk_make_customer" class="btn" style="background:#27ae60; color:#fff;">✅ TORNAR CLIENTES</button>
                <?php endif; ?>
                <button type="submit" name="bulk_stealth_on" class="btn" style="background:#9b59b6; color:#fff;" title="Ocultar nome da loja nos disparos">🕵️ ATIVAR STEALTH</button>
                <button type="submit" name="bulk_stealth_off" class="btn" style="background:#333; color:#fff;">🔓 REMOVER STEALTH</button>
                <button type="submit" name="bulk_delete" class="btn btn-danger" onclick="return confirm('Excluir selecionados?')">🗑️ EXCLUIR</button>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th>Nome</th>
                            <th>Contato</th>
                            <th style="text-align:center;">Data</th>
                            <th style="text-align:center;">Fonte</th>
                            <th style="text-align:center;">Pedidos</th>
                            <th style="text-align:right;">Total Gasto</th>
                            <th style="text-align:right;">Dívida</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $u['id']; ?>"></td>
                            <td>
                                <a href="customer-details.php?id=<?php echo $u['id']; ?>" style="color:#fff; text-decoration:none;">
                                    <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                                </a>
                                <?php if($u['is_lead']): ?><span style="font-size:0.6rem; background:#34495e; padding:2px 5px; border-radius:4px; margin-left:5px;">LEAD</span><?php endif; ?>
                                <?php if($u['hide_store_name']): ?><span style="font-size:0.6rem; background:#9b59b6; color:#fff; padding:2px 5px; border-radius:4px; margin-left:5px;" title="Modo Stealth Ativo (Whitelabel)">🕵️ STEALTH</span><?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:0.85rem;"><?php echo htmlspecialchars($u['phone'] ?: 'Sem tel.'); ?></span>
                            </td>
                            <td style="text-align:center; font-size:0.8rem; color:var(--muted);">
                                <?php echo !empty($u['created_at']) ? date('d/m/y', strtotime($u['created_at'])) : '-'; ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-size:0.7rem; padding:2px 6px; border-radius:4px; background:<?php echo $u['source']==='link'?'rgba(0,132,255,0.1)':'rgba(255,255,255,0.05)'; ?>; color:<?php echo $u['source']==='link'?'#0084ff':'#888'; ?>;">
                                    <?php echo $u['source'] === 'link' ? '🔗 Link' : '👤 Manual'; ?>
                                </span>
                            </td>
                            <td style="text-align:center;"><?php echo $u['total_orders']; ?></td>
                            <td style="text-align:right; color:var(--accent-green);"><span class="finance-value">R$ <?php echo number_format($u['total_spent'] ?: 0, 2, ',', '.'); ?></span></td>
                            <td style="text-align:right; color:<?php echo $u['current_debt']>0?'#e74c3c':'#666';?>"><span class="finance-value">R$ <?php echo number_format($u['current_debt'] ?: 0, 2, ',', '.'); ?></span></td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:5px; justify-content:center;">
                                    <button type="button" onclick="notifyUser(<?php echo $u['id']; ?>, this)" class="btn-wa-notif" title="Lembrar Cliente (Dívida/Saudação)">🔔</button>
                                    <button type="button" onclick="notifyUser(<?php echo $u['id']; ?>, this, 'me')" class="btn-wa-notif" style="background:#0084ff;" title="Enviar ficha para meu WhatsApp">👤</button>
                                    <?php if ($u['current_debt'] > 0): ?>
                                    <button class="btn-sm" style="background:#e67e22; border:none; cursor:pointer;" onclick="openChargeModal(<?php echo $u['id']; ?>, '<?php echo addslashes($u['name']); ?>', <?php echo $u['current_debt']; ?>)">💰 Cobrar</button>
                                    <?php endif; ?>
                                    <button type="button" onclick="openVipModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['name']); ?>')" class="btn-wa-notif" style="background:#9b59b6;" title="Atendimento VIP">⭐ VIP</button>
                                    
                                    <a href="customer-details.php?id=<?php echo $u['id']; ?>" class="btn-sm" style="background:#333; color:#fff;" title="Ver Detalhes">🔍</a>
                                    <a href="rma.php?customer_id=<?php echo $u['id']; ?>" class="btn-sm" style="background:#9b59b6; color:#fff;" title="Criar RMA (Gerar Etiqueta)">🛠️</a>
                                    <a href="customer-edit.php?id=<?php echo $u['id']; ?>" class="btn-sm" style="background:var(--warning); color:#000;" title="Editar">✏️</a>
                                    
                                    <a href="?reset_pass=<?php echo $u['id']; ?>" class="btn-sm" style="background:#e67e22; color:#fff;" title="Resetar Senha" onclick="return confirm('Gerar nova senha?')">🔑</a>
                                    
                                    <?php if($u['is_lead']): ?>
                                        <a href="?make_customer=<?php echo $u['id']; ?>" class="btn-sm" style="background:#2ecc71; color:#fff;" title="Tornar Cliente" onclick="return confirm('Converter em cliente?')">✅</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- MODAL CAMPANHA (MARKETING) -->
    <div id="marketingModal" class="modal-vip" onclick="if(event.target == this) closeMarketingModal()">
        <div class="modal-vip-content">
            <span class="close-vip" onclick="closeMarketingModal()">&times;</span>
            <h2 style="margin-bottom:1rem; display:flex; align-items:center; gap:10px;">🚀 Disparo em Massa</h2>
            <p style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;">Selecione a abordagem para os clientes marcados:</p>
            
            <label class="campaign-option">
                <input type="radio" name="tpl" value="bomdia" onclick="document.getElementById('bulk_custom_box').style.display='none'" checked>
                <strong>✨ Bom Dia Premium</strong>
                <span>Deseja um ótimo dia e reforça sua marca.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="tpl" value="winback" onclick="document.getElementById('bulk_custom_box').style.display='none'">
                <strong>🔙 Saudades (Reativação)</strong>
                <span>Cita o último produto comprado e sugere novidades.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="tpl" value="custom" onclick="document.getElementById('bulk_custom_box').style.display='block'">
                <strong>✍️ Mensagem Personalizada</strong>
                <span>Escreva sua própria mensagem ou use um modelo salvo.</span>
            </label>

            <div id="bulk_custom_box" style="display:none; margin-top:15px; border-top:1px dashed #444; padding-top:15px;">
                <div id="bulk_templates_container" style="margin-bottom:12px; display:none;">
                    <label style="font-size:0.75rem; color:#f1c40f; font-weight:bold; display:block; margin-bottom:5px;">⭐ SEUS MODELOS SALVOS:</label>
                    <select id="bulk_saved_templates" style="width:100%; background:#000; border:1px solid #f1c40f; color:#fff; padding:8px; border-radius:6px;" onchange="if(this.value) document.getElementById('bulk_msg').value = decodeURIComponent(this.value)">
                        <option value="">-- Escolher um favorito --</option>
                    </select>
                </div>
                <textarea id="bulk_msg" placeholder="Use {nome} para o nome..." rows="4" style="width:100%; background:#111; border:1px solid #444; color:#fff; border-radius:8px; padding:10px;"></textarea>
            </div>

            <div class="progress-container" id="progContainer">
                <div class="progress-bar"><div class="progress-fill" id="progFill"></div></div>
                <div id="progText" style="font-size:0.8rem; color:#888; margin-top:5px; text-align:center;">Processando...</div>
            </div>

            <div style="margin-top:2rem; display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeMarketingModal()">Cancelar</button>
                <button type="button" id="btnStartBulk" class="btn" style="background:var(--primary); color:#000; flex:1;" onclick="startBulk()">🚀 DISPARAR AGORA</button>
            </div>
        </div>
    </div>

    <!-- MODAL VIP INDIVIDUAL -->
    <div id="vipModal" class="modal-vip" onclick="if(event.target == this) closeVipModal()">
        <div class="modal-vip-content">
            <span class="close-vip" onclick="closeVipModal()">&times;</span>
            <h2 style="margin-bottom:1rem; color:#9b59b6;">⭐ Atendimento VIP (WhatsApp)</h2>
            <p id="vip_target_name" style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;"></p>
            <input type="hidden" id="vip_cust_id">

            <label class="campaign-option">
                <input type="radio" name="viptpl" value="bomdia" onclick="document.getElementById('vip_custom_box').style.display='none'" checked>
                <strong>✨ Bom Dia Premium</strong>
                <span>Mensagem amigável de bom dia.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="viptpl" value="winback" onclick="document.getElementById('vip_custom_box').style.display='none'">
                <strong>🔙 Saudades</strong>
                <span>Reativação baseada no histórico.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="viptpl" value="custom" onclick="document.getElementById('vip_custom_box').style.display='block'">
                <strong>✍️ Mensagem Livre</strong>
                <span>Use seus modelos salvos ou escreva algo novo.</span>
            </label>

            <div id="vip_custom_box" style="display:none; margin-top:15px; border-top:1px dashed #444; padding-top:15px;">
                <div id="vip_templates_container" style="margin-bottom:12px; display:none;">
                    <label style="font-size:0.75rem; color:#f1c40f; font-weight:bold; display:block; margin-bottom:5px;">⭐ SEUS MODELOS SALVOS:</label>
                    <select id="vip_saved_templates" style="width:100%; background:#000; border:1px solid #f1c40f; color:#fff; padding:8px; border-radius:6px;" onchange="if(this.value) document.getElementById('vip_msg').value = decodeURIComponent(this.value)">
                        <option value="">-- Escolher um favorito --</option>
                    </select>
                </div>
                <textarea id="vip_msg" placeholder="Digite sua mensagem..." rows="3" style="width:100%; background:#111; border:1px solid #444; color:#fff; border-radius:8px; padding:10px;"></textarea>
                
                <div style="margin-top:10px; background:rgba(155, 89, 182, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(155, 89, 182, 0.3);">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" id="save_as_template" onchange="document.getElementById('tpl_title_box').style.display = this.checked ? 'block' : 'none'">
                        ⭐ Salvar esta mensagem como favorito
                    </label>
                    <div id="tpl_title_box" style="display:none; margin-top:8px;">
                        <input type="text" id="tpl_title" placeholder="Nome do modelo (Ex: Boas-vindas)" style="width:100%; background:#000; border:1px solid #9b59b6; color:#fff; padding:8px; border-radius:6px; font-size:0.8rem;">
                    </div>
                </div>
            </div>

            <div style="margin-top:2rem; display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeVipModal()">Cancelar</button>
                <button type="button" id="btnSendVip" class="btn" style="background:#9b59b6; color:#fff; flex:1;" onclick="sendVipMsg()">🚀 ENVIAR AGORA</button>
            </div>
        </div>
    </div>

    <!-- MODAL: CHARGE DEBT -->
    <div id="chargeModal" class="modal-vip" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px);">
        <div class="modal-vip-content">
            <h2 id="chargeTitle" style="color:var(--primary); margin-bottom:10px;">Cobrar Cliente</h2>
            <p id="chargeInfo" style="color:#aaa; margin-bottom:20px;"></p>
            
            <label style="display:block; margin-bottom:10px; color:#fff;">Selecione a Conta para Recebimento:</label>
            <select id="chargeAccount" style="width:100%; padding:12px; background:#222; border:1px solid #444; color:#fff; border-radius:8px; margin-bottom:20px;">
                <?php 
                $accs = $pdo->query("SELECT * FROM payment_accounts ORDER BY name ASC")->fetchAll();
                foreach($accs as $ac):
                ?>
                <option value="<?php echo $ac['id']; ?>"><?php echo htmlspecialchars($ac['name']); ?> (<?php echo strtoupper($ac['type']); ?>)</option>
                <?php endforeach; ?>
                <?php if(empty($accs)): ?>
                <option value="">Nenhuma conta cadastrada!</option>
                <?php endif; ?>
            </select>

            <div style="display:flex; gap:10px;">
                <button id="btnConfirmCharge" onclick="confirmCharge()" class="btn" style="flex:1; background:var(--primary); color:#000;">🚀 Enviar Cobrança</button>
                <button onclick="document.getElementById('chargeModal').style.display='none'" class="btn btn-secondary" style="flex:1;">Cancelar</button>
            </div>
        </div>
    </div>

    <script>
    let currentChargeUid = null;

    function openChargeModal(uid, name, debt) {
        currentChargeUid = uid;
        document.getElementById('chargeTitle').innerText = 'Cobrar ' + name;
        document.getElementById('chargeInfo').innerText = 'Dívida atual: R$ ' + debt.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('chargeModal').style.display = 'block';
    }

    function confirmCharge() {
        const accId = document.getElementById('chargeAccount').value;
        if(!accId) { alert('Selecione uma conta!'); return; }

        const btn = document.getElementById('btnConfirmCharge');
        btn.disabled = true;
        btn.innerText = '⌛ Enviando...';

        fetch(`customers.php?ajax_send_debt_reminder=1&user_id=${currentChargeUid}&account_id=${accId}`)
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    alert('✅ Cobrança enviada com sucesso!');
                    document.getElementById('chargeModal').style.display = 'none';
                } else {
                    alert('❌ Erro: ' + (data.error || 'Falha no disparo'));
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = '🚀 Enviar Cobrança';
            });
    }

    function toggleAll(source) {
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = source.checked);
    }

    function notifyUser(id, btn, type = 'remind') {
        btn.classList.add('loading');
        fetch(`customers.php?ajax_notify=1&id=${id}&type=${type}`)
            .then(r => r.json())
            .then(d => {
                btn.classList.remove('loading');
                if(d.success) btn.classList.add('success');
                else { alert('⚠️ ATENÇÃO: ' + (d.error || 'Erro no envio')); btn.classList.add('error'); }
                setTimeout(() => { btn.classList.remove('success', 'error'); }, 2000);
            });
    }

    function openVipModal(id, name) {
        document.getElementById('vip_cust_id').value = id;
        document.getElementById('vip_target_name').innerHTML = `Atendimento para: <strong>${name}</strong>`;
        document.getElementById('vipModal').style.display = 'block';
        loadTemplates('vip_saved_templates', 'vip_templates_container');
    }
    function closeVipModal() { document.getElementById('vipModal').style.display = 'none'; }

    function openMarketingModal() {
        const sel = document.querySelectorAll('input[name="selected_ids[]"]:checked');
        if(sel.length === 0) return alert('Selecione ao menos um cliente!');
        document.getElementById('marketingModal').style.display = 'block';
        loadTemplates('bulk_saved_templates', 'bulk_templates_container');
    }
    function closeMarketingModal() { document.getElementById('marketingModal').style.display = 'none'; }

    function loadTemplates(selectId, containerId) {
        fetch('ajax_message_templates.php?action=list&category=customers')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById(selectId);
                const con = document.getElementById(containerId);
                if(data.length > 0) {
                    sel.innerHTML = '<option value="">-- Escolher favorito --</option>';
                    data.forEach(t => sel.innerHTML += `<option value="${encodeURIComponent(t.message)}">${t.title}</option>`);
                    con.style.display = 'block';
                } else con.style.display = 'none';
            });
    }

    function sendVipMsg() {
        const id = document.getElementById('vip_cust_id').value;
        const tpl = document.querySelector('input[name="viptpl"]:checked').value;
        const msg = document.getElementById('vip_msg').value;
        const btn = document.getElementById('btnSendVip');

        if(tpl === 'custom' && !msg) return alert('Escreva a mensagem!');
        
        // Save as template
        if(tpl === 'custom' && document.getElementById('save_as_template').checked) {
            const title = document.getElementById('tpl_title').value || 'Modelo Sem Título';
            const fd = new FormData();
            fd.append('category', 'customers'); fd.append('title', title); fd.append('message', msg);
            fetch('ajax_message_templates.php?action=save', { method: 'POST', body: fd });
        }

        btn.disabled = true; btn.innerText = 'ENVIANDO...';
        fetch(`customers.php?ajax_bulk_marketing=1&id=${id}&template=${tpl}&msg=${encodeURIComponent(msg)}`)
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    btn.style.background = '#27ae60'; btn.innerText = '✅ ENVIADO!';
                    setTimeout(() => { closeVipModal(); btn.disabled = false; btn.innerText = '🚀 ENVIAR AGORA'; btn.style.background = '#9b59b6'; }, 2000);
                } else { alert('⚠️ ' + d.error); btn.disabled = false; btn.innerText = 'TENTAR NOVAMENTE'; }
            });
    }

    async function startBulk() {
        const sel = document.querySelectorAll('input[name="selected_ids[]"]:checked');
        const tpl = document.querySelector('input[name="tpl"]:checked').value;
        const msg = document.getElementById('bulk_msg').value;
        if(!confirm(`Disparar para ${sel.length} contatos?`)) return;

        const btn = document.getElementById('btnStartBulk');
        const fill = document.getElementById('progFill');
        const con = document.getElementById('progContainer');
        const txt = document.getElementById('progText');

        btn.disabled = true; con.style.display = 'block';
        let done = 0;
        for(let cb of sel) {
            done++;
            const pct = (done / sel.length) * 100;
            fill.style.width = pct + '%';
            txt.innerText = `Enviando ${done} de ${sel.length}...`;
            try { await fetch(`customers.php?ajax_bulk_marketing=1&id=${cb.value}&template=${tpl}&msg=${encodeURIComponent(msg)}`); } catch(e) {}
            await new Promise(r => setTimeout(r, 1200));
        }
        txt.innerText = '✅ Finalizado!';
        setTimeout(() => { closeMarketingModal(); btn.disabled = false; con.style.display = 'none'; }, 2000);
        }
    </script>
</body>
</html>
<?php
} catch (Throwable $e) {
    // TRATA ERRO GIGANTE
    ?>
    <div style="background:#0b0e14; color:#fff; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif; padding:20px; text-align:center;">
        <h1 style="font-size:4rem; margin-bottom:0;">⚠️</h1>
        <h2 style="color:#e74c3c;">Ops! Ocorreu um erro nesta página</h2>
        <p style="color:#888; max-width:600px; margin-bottom:30px;">O sistema detectou um problema (provavelmente no banco de dados) que está impedindo o carregamento. Tente rodar o reparo de emergência abaixo.</p>
        
        <div style="background:#1a1e2a; padding:20px; border-radius:10px; border:1px solid #333; margin-bottom:30px; text-align:left; font-family:monospace; font-size:0.9rem; max-width:800px; overflow-x:auto;">
            <b style="color:#f1c40f;">Detalhes do Erro:</b><br>
            <?php echo $e->getMessage(); ?> em <?php echo basename($e->getFile()); ?>:<?php echo $e->getLine(); ?>
        </div>

        <div style="display:flex; gap:15px;">
            <a href="emergency_fix.php" style="background:#f1c40f; color:#000; padding:12px 25px; border-radius:8px; font-weight:bold; text-decoration:none;">🚀 RODAR REPARO DE EMERGÊNCIA</a>
            <a href="dashboard.php" style="background:#333; color:#fff; padding:12px 25px; border-radius:8px; text-decoration:none;">🏠 Ir para Dashboard</a>
        </div>
    </div>
    <?php
}
?>