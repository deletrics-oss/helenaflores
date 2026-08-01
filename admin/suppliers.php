<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();
// ANTIGRAVITY TEST
// --- DB INTEGRITY CHECK ---
try {
    $pdo->exec("ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS catalog_path VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active'");
    $pdo->exec("ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS niche VARCHAR(255) NULL");
} catch(Exception $e) {}

// --- AJAX HANDLERS ---
if (isset($_GET['ajax_supplier_msg'])) {
    header('Content-Type: application/json');
    $sid = (int)$_GET['id'];
    $template = $_GET['template'] ?? 'custom';
    $customMsg = $_GET['msg'] ?? '';

    require_once __DIR__ . '/../includes/notifications.php';
    $notif = new NotificationService($pdo);

    $s = $pdo->query("SELECT * FROM suppliers WHERE id = $sid")->fetch();
    if (!$s || empty($s['phone'])) {
        echo json_encode(['success' => false, 'error' => 'Sem número']);
        exit;
    }

    $msg = "";
    if ($template === 'stock_check') {
        $msg = "Olá *" . $s['name'] . "*! 👋 Aqui é da *Fight Arcade*. Gostaria de verificar se você tem disponibilidade dos itens que compramos da última vez e se os preços mantêm os mesmos? 🕹️";
    } elseif ($template === 'quote') {
        $msg = "Olá *" . $s['name'] . "*! 👋 Poderia me enviar a tabela de preços atualizada de vocês? Estamos planejando novas reposições. 📦";
    } elseif ($template === 'find_product') {
        $msg = "Olá *" . $s['name'] . "*! 👋 Estou procurando um produto específico. Vocês teriam disponível: *" . $customMsg . "*? Se sim, qual o valor para atacado?";
    } else {
        $msg = str_replace('{nome}', $s['name'], $customMsg);
    }

    $res = $notif->notifyCustomer($s['phone'], $msg);
    echo json_encode(['success' => $res]);
    exit;
}

// --- STANDARD POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_ids = $_POST['selected_ids'] ?? [];
    if (isset($_POST['bulk_delete']) && !empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $pdo->query("DELETE FROM suppliers WHERE id IN ($ids)");
        $passMsg = "<div class='alert alert-success'>🗑️ Fornecedores excluídos com sucesso!</div>";
    }

    if (isset($_GET['clone'])) {
        $clone_id = (int)$_GET['clone'];
        $pdo->query("INSERT INTO suppliers (name, contact_name, phone, address, email, lat, lng, notes, niche, status) 
                     SELECT CONCAT(name, ' (CLONE)'), contact_name, phone, address, email, lat, lng, notes, niche, status 
                     FROM suppliers WHERE id = $clone_id");
        $passMsg = "<div class='alert alert-success'>👯 Fornecedor clonado com sucesso!</div>";
    }
    
    if (isset($_POST['add_supplier'])) {
        $name = $_POST['name'];
        $contact = $_POST['contact'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $email = $_POST['email'];
        $lat = $_POST['lat'];
        $lng = $_POST['lng'];
        $notes = $_POST['notes'];
        $niche = $_POST['niche'] ?? '';
        
        $catalog_path = null;
        if (!empty($_FILES['catalog']['name'])) {
            $ext = pathinfo($_FILES['catalog']['name'], PATHINFO_EXTENSION);
            $filename = 'catalog_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['catalog']['tmp_name'], __DIR__ . '/../assets/uploads/' . $filename)) {
                $catalog_path = 'assets/uploads/' . $filename;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_name, phone, address, email, lat, lng, notes, catalog_path, niche) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $contact, $phone, $address, $email, $lat, $lng, $notes, $catalog_path, $niche]);
        $passMsg = "<div class='alert alert-success'>✅ Fornecedor cadastrado com sucesso!</div>";
    }
}

// --- DATA FETCH ---
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) as total_purchases,
        (SELECT SUM(total_amount) FROM purchase_orders po WHERE po.supplier_id = s.id) as total_spent
        FROM suppliers s WHERE 1=1";

if ($filter === 'active') $sql .= " AND status = 'active'";
elseif ($filter === 'lalamove') $sql .= " AND lat != '' AND lng != ''";

if ($search) {
    $sql .= " AND (name LIKE :q1 OR contact_name LIKE :q2 OR phone LIKE :q3 OR email LIKE :q4)";
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
$suppliers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Fornecedores | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 1.5rem; }
        .filter-tab { padding: 8px 18px; background: #222; border: 1px solid #444; border-radius: 6px; color: #ccc; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .filter-tab.active { background: var(--primary); color: #000; font-weight: bold; }
        
        .btn-wa-notif { background: #25d366; color: #fff; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-wa-notif:hover { transform: scale(1.05); filter: brightness(1.1); }

        .modal-vip { display: none; justify-content: center; align-items: center; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); }
        .modal-vip-content { background: #1a1e2a; padding: 2.5rem; border: 1px solid #333; width: 550px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 16px; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .close-vip { position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; color: #666; transition: 0.2s; z-index: 1; }
        .close-vip:hover { color: #fff; }

        .stat-card { background: #161b22; border: 1px solid #333; padding: 15px; border-radius: 10px; text-align: center; }
        .stat-val { font-size: 1.2rem; font-weight: bold; color: var(--primary); }
        .stat-label { font-size: 0.7rem; color: #888; text-transform: uppercase; margin-top: 5px; }

        .supplier-row:hover { background: rgba(241,196,15,0.05); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <?php if (isset($passMsg)) echo $passMsg; ?>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h1>Central de Suprimentos</h1>
            <div style="display:flex; gap:10px;">
                <a href="supplier-import-ia.php" class="btn" style="background:#333;">🤖 Importar IA</a>
                <button onclick="document.getElementById('addModal').style.display='flex'" class="btn">Novo Fornecedor</button>
            </div>
        </div>

        <div class="grid-4 mb-2">
            <div class="stat-card">
                <div class="stat-val"><?php echo count($suppliers); ?></div>
                <div class="stat-label">Fornecedores Listados</div>
            </div>
            <div class="stat-card">
                <div class="stat-val"><span class="finance-value">R$ <?php echo number_format(array_sum(array_column($suppliers, 'total_spent')), 2, ',', '.'); ?></span></div>
                <div class="stat-label">Total Investido (Compras)</div>
            </div>
            <div class="stat-card">
                <div class="stat-val"><?php echo array_sum(array_column($suppliers, 'total_purchases')); ?></div>
                <div class="stat-label">Pedidos de Compra</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color:#2ecc71;">Ativo</div>
                <div class="stat-label">Status do Módulo</div>
            </div>
        </div>

        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo ($filter === 'all') ? 'active' : ''; ?>">👥 Todos</a>
            <a href="?filter=active" class="filter-tab <?php echo ($filter === 'active') ? 'active' : ''; ?>">✅ Ativos</a>
            <a href="?filter=lalamove" class="filter-tab <?php echo ($filter === 'lalamove') ? 'active' : ''; ?>">🏍️ Com GPS (Lalamove)</a>
        </div>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:1.5rem;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="q" placeholder="Buscar fornecedor por nome, contato ou email..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding:12px; background:#111; border:1px solid #333; color:#fff; border-radius:8px;">
            <button type="submit" class="btn">🔍 Buscar</button>
        </form>

        <form method="POST" id="bulk-form">
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-bottom:1rem; align-items:center;">
                <button type="button" onclick="openMarketingModal()" class="btn" style="background:var(--primary); color:#000;">🚀 CAMPANHA DE REPOSIÇÃO</button>
                <button type="submit" name="bulk_delete" class="btn btn-danger" onclick="return confirm('Excluir selecionados?')">🗑️ EXCLUIR</button>
            </div>

            <div class="card">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" onclick="toggleSelectAll(this)"></th>
                            <th>Fornecedor</th>
                            <th>Contato / Whats</th>
                            <th>Financeiro</th>
                            <th>Localização</th>
                            <th width="150">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($suppliers as $s): ?>
                        <tr class="supplier-row">
                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $s['id']; ?>" class="row-checkbox"></td>
                            <td>
                                <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                                <small style="color:var(--primary); font-size:0.7rem;"><?php echo htmlspecialchars($s['niche'] ?: 'Sem nicho definido'); ?></small><br>
                                <?php if($s['catalog_path']): ?>
                                    <a href="../<?php echo $s['catalog_path']; ?>" target="_blank" style="font-size:0.7rem; color:#f1c40f; text-decoration:none;">📑 Ver Catálogo</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:0.8rem; color:#888;"><?php echo htmlspecialchars($s['contact_name']); ?></span><br>
                                <button type="button" onclick="sendQuickMsg(<?php echo $s['id']; ?>)" class="btn-wa-notif">
                                    <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($s['phone']); ?>
                                </button>
                            </td>
                            <td>
                                <span style="font-size:0.75rem; color:#888;">Gasto Total:</span><br>
                                <strong style="color:#2ecc71;"><span class="finance-value">R$ <?php echo number_format($s['total_spent'] ?: 0, 2, ',', '.'); ?></span></strong><br>
                                <small style="color:#555;"><?php echo $s['total_purchases']; ?> pedidos</small>
                            </td>
                            <td>
                                <span style="font-size:0.75rem; color:#888;"><?php echo htmlspecialchars($s['city'] ?: '—'); ?></span>
                                <?php if($s['lat'] && $s['lng']): ?>
                                    <br><small style="color:#3498db;"><i class="fas fa-map-marker-alt"></i> GPS OK</small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
                                    <a href="purchase_pos.php?supplier_id=<?php echo $s['id']; ?>" class="btn-action" title="Nova Compra" style="background:#2ecc71; color:#000; padding:10px; border-radius:8px; display:inline-flex;"><i class="fas fa-shopping-cart"></i></a>
                                    <a href="supplier-edit.php?id=<?php echo $s['id']; ?>" class="btn-action" title="Editar" style="background:#333; color:#fff; padding:10px; border-radius:8px; display:inline-flex; border:1px solid #444;"><i class="fas fa-edit"></i></a>
                                    <a href="?clone=<?php echo $s['id']; ?>" class="btn-action" title="Clonar" style="background:#3498db; color:#fff; padding:10px; border-radius:8px; display:inline-flex;"><i class="fas fa-copy"></i></a>
                                    <button type="button" onclick="openMarketingModal(<?php echo $s['id']; ?>)" class="btn-action" title="Marketing" style="background:#9b59b6; color:#fff; padding:10px; border-radius:8px; display:inline-flex; border:none; cursor:pointer;"><i class="fas fa-bullhorn"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($suppliers)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:3rem; color:#666;">Nenhum fornecedor encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- Modal: Cadastro Fornecedor -->
    <div id="addModal" class="modal-vip">
        <div class="modal-vip-content" style="position:relative;">
            <span class="close-vip" onclick="document.getElementById('addModal').style.display='none'" style="position:absolute; top:15px; right:20px; font-size:1.8rem; font-weight:bold; color:#FFECB3; cursor:pointer;">&times;</span>
            <h2 style="margin-bottom:1rem;">Cadastrar Novo Fornecedor</h2>
            
            <div style="background:#000; padding:15px; border-radius:8px; border:1px dashed var(--primary); margin-bottom:1.5rem;">
                <strong style="color:var(--primary); font-size:0.9rem;"><i class="fas fa-robot"></i> Assistente IA</strong>
                <textarea id="ai_sup_text" rows="2" style="width:100%; background:#111; color:#fff; border:1px solid #333; margin-top:5px;" placeholder="Cole dados brutos aqui..."></textarea>
                <button type="button" onclick="runSupAI()" class="btn btn-sm" style="margin-top:5px; background:var(--primary); color:#000;">✨ Extrair Dados</button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="addSupForm">
                <input type="hidden" name="add_supplier" value="1">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div>
                        <label>Nome da Empresa *</label>
                        <input type="text" name="name" id="sup_name" required style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                    <div>
                        <label>Contato</label>
                        <input type="text" name="contact" id="sup_contact" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                </div>
                <div style="margin-top:15px;">
                    <label>Nicho / O que vende? (Ex: Eletrônicos, Peças Arcade)</label>
                    <input type="text" name="niche" id="sup_niche" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:15px;">
                    <div>
                        <label>WhatsApp (com DDI)</label>
                        <input type="text" name="phone" id="sup_phone" required style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" id="sup_email" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                </div>
                <div style="margin-top:15px;">
                    <label>Catálogo (PDF/Excel)</label>
                    <input type="file" name="catalog" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                </div>
                <div style="margin-top:15px;">
                    <label>Endereço Completo</label>
                    <textarea name="address" id="sup_address" rows="2" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;"></textarea>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:15px;">
                    <div>
                        <label>Latitude</label>
                        <input type="text" name="lat" id="sup_lat" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                    <div>
                        <label>Longitude</label>
                        <input type="text" name="lng" id="sup_lng" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                </div>
                <div style="margin-top:15px; text-align:right;">
                    <button type="submit" class="btn" style="padding:12px 30px;">💾 Salvar Fornecedor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Campanha Marketing / Reposição -->
    <div id="marketingModal" class="modal-vip">
        <div class="modal-vip-content">
            <span class="close-vip" onclick="document.getElementById('marketingModal').style.display='none'">&times;</span>
            <h2 style="margin-bottom:1.5rem;">🚀 Campanha de Reposição</h2>
            <div id="marketing-config">
                <p style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;">Selecione o modelo de mensagem para enviar via WhatsApp:</p>
                
                <div class="campaign-option" onclick="selectTemplate('stock_check')">
                    <input type="radio" name="tpl" value="stock_check" id="tpl_stock" checked>
                    <strong>🔍 Verificação de Estoque</strong>
                    <span>Pergunta se os itens da última compra estão disponíveis e se o preço mudou.</span>
                </div>

                <div class="campaign-option" onclick="selectTemplate('quote')">
                    <input type="radio" name="tpl" value="quote" id="tpl_quote">
                    <strong>📄 Pedir Tabela de Preços</strong>
                    <span>Solicita a tabela atualizada para novas compras.</span>
                </div>

                <div class="campaign-option" onclick="selectTemplate('find_product')">
                    <input type="radio" name="tpl" value="find_product" id="tpl_find">
                    <strong>🔍 Procurar Produto Específico</strong>
                    <span>Envia uma mensagem perguntando se eles têm o item que você procura.</span>
                </div>

                <div id="product-search-box" style="display:none; margin-top:15px;">
                    <label style="font-size:0.8rem; color:#888;">Qual produto você está procurando?</label>
                    <input type="text" id="find_product_name" placeholder="Ex: Botão Sanwa Original" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px; margin-top:5px;">
                </div>

                <div id="bulk-progress" style="display:none; margin-top:20px; text-align:center;">
                    <div style="margin-bottom:10px;"><strong id="progress-text">Enviando... 0/0</strong></div>
                    <div style="height:10px; background:#333; border-radius:5px; overflow:hidden;">
                        <div id="progress-fill" style="height:100%; background:var(--primary); width:0%;"></div>
                    </div>
                </div>

                <div style="margin-top:20px; text-align:right;">
                    <button onclick="startCampaign()" class="btn" style="background:var(--primary); color:#000; font-weight:bold; padding:15px 40px;">🚀 DISPARAR AGORA</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function runSupAI() {
        const txt = document.getElementById('ai_sup_text').value;
        if(!txt) return alert('Cole o texto primeiro!');
        
        const btn = event.target;
        btn.innerText = '🤖 Pensando...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('ajax_ai', '1');
        fd.append('text', txt);

        fetch('supplier-import-ia.php', { method:'POST', body:fd })
        .then(r=>r.json())
        .then(data=>{
            if(data && data.length > 0) {
                const s = data[0];
                if(s.name) document.getElementById('sup_name').value = s.name;
                if(s.contact_name) document.getElementById('sup_contact').value = s.contact_name;
                if(s.phone) document.getElementById('sup_phone').value = s.phone;
                if(s.email) document.getElementById('sup_email').value = s.email;
                if(s.address) document.getElementById('sup_address').value = s.address;
                if(s.city) {
                    if(document.getElementById('sup_address').value) document.getElementById('sup_address').value += ', ' + s.city;
                    else document.getElementById('sup_address').value = s.city;
                }
                alert("✨ Dados extraídos pelo Gemini!");
            } else {
                alert("A IA não conseguiu extrair dados.");
            }
        })
        .finally(() => {
            btn.innerText = '✨ Extrair Dados';
            btn.disabled = false;
        });
    }
    let selectedSingleId = null;

    function toggleSelectAll(master) {
        document.querySelectorAll('.row-checkbox').forEach(c => c.checked = master.checked);
    }

    function openMarketingModal(id = null) {
        selectedSingleId = id;
        document.getElementById('marketingModal').style.display = 'flex';
    }

    function selectTemplate(tpl) {
        document.getElementById('tpl_' + tpl).checked = true;
        document.getElementById('product-search-box').style.display = (tpl === 'find_product') ? 'block' : 'none';
    }

    function sendQuickMsg(id) {
        if(!confirm('Deseja enviar uma mensagem rápida de "Verificação de Estoque" para este fornecedor?')) return;
        fetch(`suppliers.php?ajax_supplier_msg=1&id=${id}&template=stock_check`)
            .then(r => r.json())
            .then(data => {
                if(data.success) alert('✅ Mensagem enviada via Evolution API!');
                else alert('❌ Erro: ' + data.error);
            });
    }

    async function startCampaign() {
        const template = document.querySelector('input[name="tpl"]:checked').value;
        const customMsg = document.getElementById('find_product_name').value;
        let ids = [];
        
        if (selectedSingleId) {
            ids = [selectedSingleId];
        } else {
            document.querySelectorAll('.row-checkbox:checked').forEach(c => ids.push(c.value));
        }

        if (ids.length === 0) return alert('Selecione ao menos um fornecedor.');
        if (!confirm(`Deseja iniciar o disparo para ${ids.length} fornecedores?`)) return;

        const progressDiv = document.getElementById('bulk-progress');
        const progressText = document.getElementById('progress-text');
        const progressFill = document.getElementById('progress-fill');
        
        progressDiv.style.display = 'block';
        
        for (let i = 0; i < ids.length; i++) {
            progressText.innerText = `Enviando... ${i+1}/${ids.length}`;
            progressFill.style.width = `${((i+1)/ids.length)*100}%`;
            
            try {
                await fetch(`suppliers.php?ajax_supplier_msg=1&id=${ids[i]}&template=${template}&msg=${encodeURIComponent(customMsg)}`);
            } catch(e) {}
            
            // Small delay between msgs
            await new Promise(r => setTimeout(r, 1500));
        }
        
        alert('🚀 Campanha finalizada!');
        location.reload();
    }
    </script>
</body>
</html>
