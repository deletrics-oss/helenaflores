<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// --- AJAX HANDLERS ---

// 1. Bulk Marketing for Leads
if (isset($_GET['ajax_bulk_marketing'])) {
    header('Content-Type: application/json');
    $uid = (int)$_GET['id'];
    $template = $_GET['template'] ?? 'custom';
    $customMsg = $_GET['msg'] ?? '';

    require_once __DIR__ . '/../includes/notifications.php';
    $notif = new NotificationService($pdo);

    $lead = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
    if (!$lead || empty($lead['phone'])) {
        echo json_encode(['success' => false, 'error' => 'Contato sem número ou não encontrado']);
        exit;
    }

    $msg = "";
    if ($template === 'bomdia') {
        $msg = "Olá *" . $lead['name'] . "*! 👋 Vi que você acessou o catálogo da *Fight Arcade* recentemente. Como posso te ajudar a montar seu setup hoje? 🕹️";
    } elseif ($template === 'oferta') {
        $msg = "Olá *" . $lead['name'] . "*! 👋 Temos uma condição especial para sua primeira compra na *Fight Arcade* hoje. Me chama aqui se quiser fechar seu pedido! 🕹️🎁";
    } else {
        $msg = str_replace('{nome}', $lead['name'], $customMsg);
    }

    $res = $notif->notifyCustomer($lead['phone'], $msg);
    echo json_encode(['success' => $res]);
    exit;
}

// 2. Convert to Customer (Single)
if (isset($_GET['make_customer'])) {
    $uid = (int)$_GET['make_customer'];
    $pdo->prepare("UPDATE users SET is_lead = 0 WHERE id = ?")->execute([$uid]);
    $f = $_GET['filter'] ?? 'pending';
    header("Location: leads.php?filter=$f&msg=converted");
    exit;
}

// --- STANDARD POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_ids = $_POST['selected_ids'] ?? [];
    if (isset($_POST['bulk_delete']) && !empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $pdo->query("DELETE FROM users WHERE id IN ($ids) AND role != 'admin'");
        $passMsg = "<div class='alert alert-success'>🗑️ Leads excluídos com sucesso!</div>";
    }
    if (isset($_POST['bulk_make_customer']) && !empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $pdo->exec("UPDATE users SET is_lead = 0 WHERE id IN ($ids)");
        $passMsg = "<div class='alert alert-success'>✅ Leads convertidos em Clientes!</div>";
    }
    if (isset($_POST['recover_orphaned_leads'])) {
        $pdo->exec("UPDATE users SET is_lead = 1 WHERE role != 'admin' AND is_lead = 0 AND id NOT IN (SELECT user_id FROM orders)");
        $passMsg = "<div class='alert alert-success'>🔄 Leads órfãos recuperados com sucesso! (Usuários sem pedidos agora são marcados como leads)</div>";
    }
}

// --- DATA FETCH ---
$filter = $_GET['filter'] ?? 'pending';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as total_orders
        FROM users u 
        WHERE role != 'admin'";

if ($filter === 'pending') {
    $sql .= " AND is_lead = 1";
} elseif ($filter === 'converted') {
    $sql .= " AND is_lead = 0";
}

if ($search) {
    $sql .= " AND (name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3)";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
if ($search) {
    $searchTerm = "%$search%";
    $stmt->bindValue(':q1', $searchTerm);
    $stmt->bindValue(':q2', $searchTerm);
    $stmt->bindValue(':q3', $searchTerm);
}
$stmt->execute();
$leads = $stmt->fetchAll();

// --- ANALYTICS FOR LEADS ---
$leadStats = [
    'today_conversions' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead=0 AND created_at >= CURDATE() AND id IN (SELECT user_id FROM orders)")->fetchColumn(),
    'today_visits'      => $pdo->query("SELECT COUNT(*) FROM site_visits WHERE visited_at = CURDATE()")->fetchColumn(),
    'top_source'        => $pdo->query("SELECT source, COUNT(*) as qty FROM users WHERE source IS NOT NULL AND source != '' GROUP BY source ORDER BY qty DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC)['source'] ?? 'N/D',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Leads | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .btn-wa-notif { background: #25d366; color: #fff; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-wa-notif:hover { transform: scale(1.05); filter: brightness(1.1); }
        .btn-wa-notif.loading { background: #555; pointer-events: none; }
        
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

        .filter-tab { transition: 0.2s; }
        .filter-tab.active { background: var(--primary) !important; color: #000 !important; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <?php if (isset($passMsg)) echo $passMsg; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'converted') echo "<div class='alert alert-success'>✅ Lead convertido com sucesso!</div>"; ?>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h1>📢 CRM de Leads</h1>
            <div style="display:flex; gap:10px;">
                <a href="?filter=pending" class="filter-tab <?php echo $filter==='pending'?'active':''; ?>" style="padding:8px 15px; background:#222; border:1px solid #444; border-radius:6px; color:#ccc; text-decoration:none; font-size:0.85rem;">🎯 Aguardando</a>
                <a href="?filter=converted" class="filter-tab <?php echo $filter==='converted'?'active':''; ?>" style="padding:8px 15px; background:#222; border:1px solid #444; border-radius:6px; color:#ccc; text-decoration:none; font-size:0.85rem;">✅ Convertidos</a>
                <a href="?filter=all" class="filter-tab <?php echo $filter==='all'?'active':''; ?>" style="padding:8px 15px; background:#222; border:1px solid #444; border-radius:6px; color:#ccc; text-decoration:none; font-size:0.85rem;">👥 Todos</a>
            </div>
        </div>

        <!-- Analytics Bar -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:2rem;">
            <div style="background:#1a1e2a; padding:15px; border-radius:12px; border:1px solid #333; text-align:center;">
                <div style="font-size:0.75rem; color:#888; text-transform:uppercase; margin-bottom:5px;">Conversões Hoje</div>
                <div style="font-size:1.5rem; font-weight:bold; color:#27ae60;"><?php echo $leadStats['today_conversions']; ?></div>
            </div>
            <div style="background:#1a1e2a; padding:15px; border-radius:12px; border:1px solid #333; text-align:center;">
                <div style="font-size:0.75rem; color:#888; text-transform:uppercase; margin-bottom:5px;">Visitas no Site (Hoje)</div>
                <div style="font-size:1.5rem; font-weight:bold; color:#3498db;"><?php echo $leadStats['today_visits']; ?></div>
            </div>
            <div style="background:#1a1e2a; padding:15px; border-radius:12px; border:1px solid #333; text-align:center;">
                <div style="font-size:0.75rem; color:#888; text-transform:uppercase; margin-bottom:5px;">Canal Principal</div>
                <div style="font-size:1.5rem; font-weight:bold; color:#f1c40f;"><?php echo $leadStats['top_source']; ?></div>
            </div>
            <div style="background:#1a1e2a; padding:15px; border-radius:12px; border:1px solid #333; text-align:center;">
                <div style="font-size:0.75rem; color:#888; text-transform:uppercase; margin-bottom:5px;">Saúde SEO</div>
                <div style="font-size:1.5rem; font-weight:bold; color:#fff;">ÓTIMA ⚡</div>
            </div>
        </div>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:1.5rem;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="q" placeholder="Buscar lead..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding:12px; background:#111; border:1px solid #333; color:#fff; border-radius:8px;">
            <button type="submit" class="btn">🔍 Buscar</button>
        </form>

        <form method="POST" id="bulkForm">
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-bottom:1rem; align-items:center; flex-wrap: wrap;">
                <button type="submit" name="recover_orphaned_leads" class="btn" style="background:#f39c12; color:#000;" title="Re-tag usuarios sem pedido" onclick="return confirm('Identificar e re-tagar todos os usuários sem pedidos como Leads?')">🔄 RECUPERAR ÓRFÃOS</button>
                <button type="button" onclick="openMarketingModal()" class="btn" style="background:var(--primary); color:#000;">🚀 DISPARAR CAMPANHA</button>
                <button type="submit" name="bulk_make_customer" class="btn" style="background:#27ae60; color:#fff;">✅ TORNAR CLIENTES</button>
                <button type="submit" name="bulk_delete" class="btn btn-danger" onclick="return confirm('Excluir leads selecionados?')">🗑️ EXCLUIR</button>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th>Lead</th>
                            <th>WhatsApp</th>
                            <th style="text-align:center;">Data Acesso</th>
                            <th style="text-align:center;">Fonte</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:2rem;">Nenhum lead encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach($leads as $l): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $l['id']; ?>"></td>
                            <td>
                                <strong><?php echo htmlspecialchars($l['name']); ?></strong><br>
                                <small style="color:#666;"><?php echo htmlspecialchars($l['email'] ?: ''); ?></small>
                            </td>
                            <td>
                                <a href="https://wa.me/<?php echo preg_replace('/\D/','',$l['phone']); ?>" target="_blank" style="color:#25d366; text-decoration:none;">
                                    <?php echo htmlspecialchars($l['phone']); ?> 🟢
                                </a>
                            </td>
                            <td style="text-align:center; font-size:0.85rem;">
                                <?php echo date('d/m/Y', strtotime($l['created_at'])); ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="font-size:0.7rem; padding:2px 6px; border-radius:4px; background:<?php echo ($l['source']??'')==='link'?'rgba(0,132,255,0.1)':'rgba(255,255,255,0.05)'; ?>; color:<?php echo ($l['source']??'')==='link'?'#0084ff':'#888'; ?>;">
                                    <?php echo ($l['source']??'') === 'link' ? '🔗 Link' : '👤 Manual'; ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($l['is_lead']): ?>
                                    <span style="font-size:0.7rem; background:#444; color:#fff; padding:2px 6px; border-radius:4px; font-weight:bold;">🎯 AGUARDANDO</span>
                                <?php else: ?>
                                    <span style="font-size:0.7rem; background:#27ae60; color:#fff; padding:2px 6px; border-radius:4px; font-weight:bold;">✅ CONVERTIDO</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:5px; justify-content:center;">
                                    <button type="button" onclick="openVipModal(<?php echo $l['id']; ?>, '<?php echo htmlspecialchars($l['name']); ?>')" class="btn-wa-notif" style="background:#9b59b6;" title="Abordagem VIP">⭐ VIP</button>
                                    <a href="?make_customer=<?php echo $l['id']; ?>" class="btn-sm" style="background:#2ecc71; color:#fff;" title="Tornar Cliente" onclick="return confirm('Converter em cliente?')">✅</a>
                                    <a href="lead-edit.php?id=<?php echo $l['id']; ?>" class="btn-sm" style="background:#333; color:#fff;" title="Editar">✏️</a>
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
            <h2 style="margin-bottom:1rem; display:flex; align-items:center; gap:10px;">🚀 Abordagem em Massa (Leads)</h2>
            
            <label class="campaign-option">
                <input type="radio" name="tpl" value="bomdia" onclick="document.getElementById('bulk_custom_box').style.display='none'" checked>
                <strong>👋 Boas-vindas / Interesse</strong>
                <span>Deseja um ótimo dia e pergunta se precisa de ajuda com o catálogo.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="tpl" value="oferta" onclick="document.getElementById('bulk_custom_box').style.display='none'">
                <strong>🎁 Oferta Especial (Gatilho)</strong>
                <span>Oferece uma condição especial para fechar a primeira compra hoje.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="tpl" value="custom" onclick="document.getElementById('bulk_custom_box').style.display='block'">
                <strong>✍️ Mensagem Personalizada</strong>
                <span>Use seus modelos salvos ou escreva algo novo.</span>
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
            <h2 style="margin-bottom:1rem; color:#9b59b6;">✨ Atendimento Especial (Lead)</h2>
            <p id="vip_target_name" style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;"></p>
            <input type="hidden" id="vip_cust_id">

            <label class="campaign-option">
                <input type="radio" name="viptpl" value="bomdia" onclick="document.getElementById('vip_custom_box').style.display='none'" checked>
                <strong>👋 Abordagem Amigável</strong>
                <span>Pergunta se o lead encontrou o que buscava.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="viptpl" value="oferta" onclick="document.getElementById('vip_custom_box').style.display='none'">
                <strong>🎁 Fechamento Urgente</strong>
                <span>Condição especial para fechar agora.</span>
            </label>
            
            <label class="campaign-option">
                <input type="radio" name="viptpl" value="custom" onclick="document.getElementById('vip_custom_box').style.display='block'">
                <strong>✍️ Mensagem Livre</strong>
                <span>Escreva ou use modelos.</span>
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
                        ⭐ Salvar mensagem nos modelos
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

    <script>
    function toggleAll(source) {
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = source.checked);
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
        if(sel.length === 0) return alert('Selecione ao menos um lead!');
        document.getElementById('marketingModal').style.display = 'block';
        loadTemplates('bulk_saved_templates', 'bulk_templates_container');
    }
    function closeMarketingModal() { document.getElementById('marketingModal').style.display = 'none'; }

    function loadTemplates(selectId, containerId) {
        fetch('ajax_message_templates.php?action=list&category=leads')
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
            const title = document.getElementById('tpl_title').value || 'Modelo Leads';
            const fd = new FormData();
            fd.append('category', 'leads'); fd.append('title', title); fd.append('message', msg);
            fetch('ajax_message_templates.php?action=save', { method: 'POST', body: fd });
        }

        btn.disabled = true; btn.innerText = 'ENVIANDO...';
        fetch(`leads.php?ajax_bulk_marketing=1&id=${id}&template=${tpl}&msg=${encodeURIComponent(msg)}`)
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    btn.style.background = '#27ae60'; btn.innerText = '✅ ENVIADO!';
                    setTimeout(() => { closeVipModal(); btn.disabled = false; btn.innerText = '🚀 ENVIAR AGORA'; btn.style.background = '#9b59b6'; }, 2000);
                } else { alert('⚠️ ' + (d.error || 'Erro no envio')); btn.disabled = false; btn.innerText = 'TENTAR NOVAMENTE'; }
            });
    }

    async function startBulk() {
        const sel = document.querySelectorAll('input[name="selected_ids[]"]:checked');
        const tpl = document.querySelector('input[name="tpl"]:checked').value;
        const msg = document.getElementById('bulk_msg').value;
        if(!confirm(`Disparar para ${sel.length} leads?`)) return;

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
            try { await fetch(`leads.php?ajax_bulk_marketing=1&id=${cb.value}&template=${tpl}&msg=${encodeURIComponent(msg)}`); } catch(e) {}
            await new Promise(r => setTimeout(r, 1200));
        }
        txt.innerText = '✅ Finalizado!';
        setTimeout(() => { closeMarketingModal(); btn.disabled = false; con.style.display = 'none'; }, 2000);
    }
    </script>
</body>
</html>