<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/notifications.php';
isAdmin();
requirePermission('admin_manage');

// --- AJAX: NOTIFY VIA WHATSAPP ---
if (isset($_GET['ajax_notify']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $uid = (int)$_GET['id'];
    $msg = $_GET['msg'] ?? '';
    
    $user = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
    if ($user && !empty($user['phone'])) {
        $notif = new NotificationService($pdo);
        $res = $notif->sendCustomMessage($user['phone'], $msg);
        echo json_encode(['success' => $res]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Usuário ou telefone não encontrado']);
    }
    exit;
}

// --- DB SCHEMA AUTO-UPDATE ---
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions TEXT NULL");
} catch(Exception $e) {}

// --- PERMISSION DEFINITIONS ---
$available_permissions = [
    'dashboard'    => '🏠 Visão Geral',
    'orders'       => '📦 Pedidos de Venda',
    'products'     => '🏷️ Produtos & Categorias',
    'customers'    => '👥 Clientes & Leads',
    'suppliers'    => '🏭 Fornecedores',
    'pos_sale'     => '💰 PDV de Venda',
    'pos_purchase' => '🛒 PDV de Compra',
    'reports'      => '📊 Financeiro/Relatórios',
    'marketing'    => '🚀 Marketing/WhatsApp',
    'logistics'    => '🚚 Lalamove/Melhor Envio',
    'rma'          => '🔧 Garantias/RMA',
    'settings'     => '⚙️ Configurações Sistema',
    'admin_manage' => '👑 Gerenciar Admins'
];

// --- AJAX: SAVE PERMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $uid = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $perms = json_encode($_POST['perms'] ?? []);
    $password = $_POST['password'] ?? '';

    if ($uid === 0) { // New Admin
        $name = $_POST['name'];
        $email = $_POST['email'];
        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, permissions) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $passHash, $role, $perms]);
    } else { // Update
        $name = $_POST['name'];
        $email = $_POST['email'];
        $sql = "UPDATE users SET name = ?, email = ?, role = ?, permissions = ?";
        $params = [$name, $email, $role, $perms];
        if (!empty($password)) {
            $sql .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id = ?";
        $params[] = $uid;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Sync session if updating self
        if ($uid === $_SESSION['user_id']) {
            $_SESSION['name'] = $name;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_permissions'] = json_decode($perms, true);
        }
    }
    header("Location: manage-admins.php?msg=saved");
    exit;
}

// --- DELETE ---
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid != $_SESSION['user_id']) { // Prevent self-delete
        $pdo->query("DELETE FROM users WHERE id = $uid");
    }
    header("Location: manage-admins.php?msg=deleted");
    exit;
}

// --- DATA FETCH ---
$admins = $pdo->query("SELECT * FROM users WHERE role != 'customer' ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Equipe | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 15px; }
        .perm-item { background: #111; padding: 10px; border-radius: 8px; border: 1px solid #333; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: 0.2s; }
        .perm-item:hover { border-color: var(--primary); }
        .perm-item input { width: 18px; height: 18px; cursor: pointer; }
        
        .role-badge { padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; }
        .role-admin { background: #e74c3c; color: #fff; }
        .role-vendedor { background: #3498db; color: #fff; }
        .role-cadastro { background: #f1c40f; color: #000; }
        .role-factory { background: #9b59b6; color: #fff; }

        .modal-vip { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-content { background: #1a1e2a; width: 650px; padding: 2.5rem; border-radius: 16px; border: 1px solid #333; position: relative; }

        /* Action Buttons */
        .btn-action { width: 34px; height: 34px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; color: #fff; font-size: 1rem; text-decoration:none; }
        .btn-edit { background: #3498db; }
        .btn-wa   { background: #25d366; }
        .btn-del  { background: #e74c3c; }
        .btn-action:hover { transform: scale(1.15); filter: brightness(1.2); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <h1>Equipe & Acessos</h1>
            <button onclick="openUserModal()" class="btn">🚀 Novo Funcionário</button>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success">✅ Operação realizada com sucesso!</div>
        <?php endif; ?>

        <div class="card">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nome / Email</th>
                        <th>Nível / Cargo</th>
                        <th>Acessos Ativos</th>
                        <th width="140">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($admins as $u): 
                        $perms = json_decode($u['permissions'] ?: '[]', true);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($u['name']); ?></strong><br>
                            <small style="color:#666;"><?php echo htmlspecialchars($u['email']); ?></small>
                        </td>
                        <td>
                            <span class="role-badge role-<?php echo $u['role']; ?>">
                                <?php echo strtoupper($u['role']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                <?php if($u['role'] === 'admin'): ?>
                                    <span style="font-size:0.6rem; background:rgba(231,76,60,0.1); padding:2px 8px; border-radius:3px; color:#e74c3c; border:1px solid rgba(231,76,60,0.2);">ACESSO TOTAL</span>
                                <?php elseif(empty($perms)): ?>
                                    <small style="color:#444;">Nenhum acesso manual</small>
                                <?php else: ?>
                                    <?php foreach($perms as $p): ?>
                                        <span style="font-size:0.6rem; background:#333; padding:2px 6px; border-radius:3px; color:#aaa;"><?php echo $available_permissions[$p] ?? $p; ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <button onclick='editUser(<?php echo json_encode($u); ?>)' class="btn-action btn-edit" title="Editar / Alterar Senha">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button onclick="openVipModal('<?php echo $u['id']; ?>', '<?php echo addslashes($u['name']); ?>')" class="btn-action btn-wa" title="Notificar via Evolution API">
                                    <i class="fab fa-whatsapp"></i>
                                </button>

                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $u['id']; ?>" class="btn-action btn-del" onclick="return confirm('Excluir este acesso definitivo?')" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Editar/Criar -->
    <div id="userModal" class="modal-vip">
        <div class="modal-content">
            <h2 id="modalTitle">Configurar Acessos</h2>
            <form method="POST">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" id="f_id" value="0">
                
                <div id="new_user_fields" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div>
                        <label>Nome</label>
                        <input type="text" name="name" id="f_name" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" id="f_email" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div>
                        <label>Cargo / Role</label>
                        <select name="role" id="f_role" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                            <option value="admin">Administrador (Total)</option>
                            <option value="manager">Gerente</option>
                            <option value="vendedor">Vendedor</option>
                            <option value="cadastro">Pessoa do Cadastro</option>
                            <option value="factory">Fábrica / Logística</option>
                        </select>
                    </div>
                    <div>
                        <label>Nova Senha <small>(vazio para manter)</small></label>
                        <input type="password" name="password" style="width:100%; padding:10px; background:#111; border:1px solid #333; color:#fff; border-radius:6px;">
                    </div>
                </div>

                <h3 style="margin-top:25px; margin-bottom:10px; font-size:1rem; border-bottom:1px solid #333; padding-bottom:10px;">Permissões Granulares</h3>
                <div class="perm-grid">
                    <?php foreach($available_permissions as $key => $label): ?>
                    <label class="perm-item">
                        <input type="checkbox" name="perms[]" value="<?php echo $key; ?>" class="perm-check">
                        <span><?php echo $label; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:30px; text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('userModal').style.display='none'" class="btn" style="background:#333;">Cancelar</button>
                    <button type="submit" class="btn" style="padding:10px 40px;">💾 Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal WhatsApp VIP -->
    <div id="vipModal" class="modal-vip">
        <div class="modal-content" style="max-width:500px;">
            <div style="text-align:center; margin-bottom:1.5rem;">
                <div style="width:60px; height:60px; background:rgba(37,211,102,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                    <i class="fab fa-whatsapp" style="font-size:2rem; color:#25d366;"></i>
                </div>
                <h2 style="margin:0;">Notificar Funcionário</h2>
                <p id="vip_target_name" style="color:#888; font-size:0.9rem; margin-top:5px;"></p>
            </div>
            
            <input type="hidden" id="vip_user_id">
            
            <div style="margin-bottom:1.5rem;">
                <label style="display:block; margin-bottom:8px; font-size:0.85rem; color:#aaa;">Mensagem Personalizada:</label>
                <textarea id="vip_msg" style="width:100%; height:120px; background:#111; border:1px solid #333; color:#fff; border-radius:10px; padding:15px; font-family:inherit; resize:none;" placeholder="Olá! Preciso que você verifique..."></textarea>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <button onclick="document.getElementById('vipModal').style.display='none'" class="btn" style="background:#333;">Cancelar</button>
                <button id="btnSendVip" onclick="sendVipMsg()" class="btn" style="background:#25d366; color:#fff;">🚀 ENVIAR VIA EVOLUTION API</button>
            </div>
        </div>
    </div>

    <script>
        function openUserModal() {
            document.getElementById('modalTitle').innerText = 'Novo Funcionário';
            document.getElementById('f_id').value = '0';
            document.getElementById('f_name').value = '';
            document.getElementById('f_email').value = '';
            document.getElementById('new_user_fields').style.display = 'grid';
            document.querySelectorAll('.perm-check').forEach(c => c.checked = false);
            document.getElementById('userModal').style.display = 'flex';
        }

        function editUser(u) {
            document.getElementById('modalTitle').innerText = 'Editar Acesso: ' + u.name;
            document.getElementById('f_id').value = u.id;
            document.getElementById('f_name').value = u.name;
            document.getElementById('f_email').value = u.email;
            document.getElementById('f_role').value = u.role;
            
            const perms = JSON.parse(u.permissions || '[]');
            document.querySelectorAll('.perm-check').forEach(c => {
                c.checked = perms.includes(c.value);
            });

            document.getElementById('userModal').style.display = 'flex';
        }

        function openVipModal(id, name) {
            document.getElementById('vip_user_id').value = id;
            document.getElementById('vip_target_name').innerText = name;
            document.getElementById('vip_msg').value = "Olá " + name.split(' ')[0] + ",\n\n";
            document.getElementById('vipModal').style.display = 'flex';
        }

        function sendVipMsg() {
            const uid = document.getElementById('vip_user_id').value;
            const msg = document.getElementById('vip_msg').value;
            const btn = document.getElementById('btnSendVip');

            if(!msg.trim()) return alert("Escreva uma mensagem!");

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ENVIANDO...';

            fetch(`manage-admins.php?ajax_notify=1&id=${uid}&msg=${encodeURIComponent(msg)}`)
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        btn.style.background = "#2ecc71";
                        btn.innerHTML = "✅ ENVIADO!";
                        setTimeout(() => {
                            document.getElementById('vipModal').style.display = 'none';
                            btn.disabled = false;
                            btn.style.background = "#25d366";
                            btn.innerHTML = "🚀 ENVIAR VIA EVOLUTION API";
                        }, 2000);
                    } else {
                        alert("Erro: " + (data.error || "Erro desconhecido"));
                        btn.disabled = false;
                        btn.innerHTML = "🚀 TENTAR NOVAMENTE";
                    }
                });
        }
    </script>
</body>
</html>