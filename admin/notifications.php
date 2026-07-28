<?php
/**
 * admin/notifications.php
 */
try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/user_auth.php';
    require_once __DIR__ . '/../includes/notifications.php';
    isAdmin();

    $notif = new NotificationService($pdo);
$msg = '';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notif'])) {
    $settings = [
        'notif_provider'           => $_POST['notif_provider'] ?? 'log',
        'notif_admin_phone'        => preg_replace('/\D/', '', $_POST['notif_admin_phone'] ?? ''),
        'notif_site_url'           => rtrim($_POST['notif_site_url'] ?? '', '/'),
        // Z-API
        'notif_zapi_instance'      => $_POST['notif_zapi_instance'] ?? '',
        'notif_zapi_token'         => $_POST['notif_zapi_token'] ?? '',
        'notif_zapi_client_token'  => $_POST['notif_zapi_client_token'] ?? '',
        // Evolution API
        'notif_waapi_url'          => $_POST['notif_waapi_url'] ?? '',
        'notif_waapi_key'          => $_POST['notif_waapi_key'] ?? '',
        'notif_waapi_instance'     => $_POST['notif_waapi_instance'] ?? '',
        // Twilio
        'notif_twilio_sid'         => $_POST['notif_twilio_sid'] ?? '',
        'notif_twilio_token'       => $_POST['notif_twilio_token'] ?? '',
        'notif_twilio_from'        => $_POST['notif_twilio_from'] ?? '',
    ];
    $notif->saveSettings($settings);
    $msg = '<div class="alert alert-success">✅ Configurações de notificação salvas!</div>';
    $notif = new NotificationService($pdo); // Reload
}

$cfg = $notif->getConfig();

// Handle Test Send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_send'])) {
    $target  = $_POST['test_phone'] ?: ($cfg['notif_admin_phone'] ?? '');
    $message = "🕹️ *TESTE Fight Arcade*\nEsta é uma mensagem de teste do seu novo sistema de notificações! ✅";
    
    if ($target) {
        $res = $notif->send($target, $message);
        if ($res) $msg = '<div class="alert alert-success">🚀 Mensagem de teste enviada com sucesso para ' . $target . '!</div>';
        else $msg = '<div class="alert alert-error">❌ Falha ao enviar mensagem de teste. Verifique os logs no final da página.</div>';
    } else {
        $msg = '<div class="alert alert-warning">⚠️ Informe um número para o teste.</div>';
    }
}

// AJAX: Evolution API Status/Connect/Logout
if (isset($_GET['wa_action'])) {
    header('Content-Type: application/json');
    $action = $_GET['wa_action'];
    $url  = rtrim($cfg['notif_waapi_url'] ?? '', '/');
    $key  = $cfg['notif_waapi_key'] ?? '';
    $inst = $cfg['notif_waapi_instance'] ?? '';

    if (!$url || !$key || !$inst) {
        echo json_encode(['error' => 'Configuração incompleta']);
        exit;
    }

    $headers = ['Content-Type: application/json', 'apikey: ' . $key];

    if ($action === 'status') {
        $ch = curl_init("$url/instance/connectionState/$inst");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $res = curl_exec($ch);
        echo $res ?: json_encode(['state' => 'error']);
    } 
    elseif ($action === 'connect') {
        $ch = curl_init("$url/instance/connect/$inst");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $res = curl_exec($ch);
        echo $res ?: json_encode(['error' => 'Falha ao gerar QR']);
    }
    elseif ($action === 'create') {
        $ch = curl_init("$url/instance/create");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'instanceName' => $inst,
            'token' => $key,
            'qrcode' => true
        ]));
        $res = curl_exec($ch);
        echo $res ?: json_encode(['success' => true]);
    }
    elseif ($action === 'logout') {
        $ch = curl_init("$url/instance/logout/$inst");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $res = curl_exec($ch);
        echo $res ?: json_encode(['success' => true]);
    }
    elseif ($action === 'set_webhook') {
        $res = $notif->setWebhookEvolution();
        echo json_encode($res);
    }
    exit;
}

// Handle Manual Automation Trigger
if (isset($_GET['trigger_automation'])) {
    header('Content-Type: application/json');
    try {
        ob_start();
        include __DIR__ . '/../cron_worker.php';
        $out = ob_get_clean();
        echo json_encode(['success' => true, 'output' => $out]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$logs = $notif->getRecentLogs(15);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações | Fight Arcade Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #0b0e14; --surface: #141820; --surface2: #1a1e2a;
            --border: #252d3d; --primary: #f1c40f; --text: #e8eaf0;
            --muted: #5a6478; --radius: 12px;
            --whatsapp: #25D366; --twilio: #F22F46; --zapi: #0084ff;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; margin: 0; }
        .container { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }
        
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card h3 { margin: 0 0 1.2rem; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: .75rem; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; font-weight: 700; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 12px; background: #0d1017;
            border: 1px solid var(--border); color: var(--text);
            border-radius: 8px; outline: none; transition: border-color .15s;
        }
        .form-group input:focus { border-color: var(--primary); }
        
        .provider-select {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 1.5rem;
        }
        .provider-option {
            background: var(--surface2); border: 2px solid var(--border); border-radius: 10px;
            padding: 12px; text-align: center; cursor: pointer; transition: .2s;
        }
        .provider-option i { font-size: 1.5rem; margin-bottom: 5px; display: block; }
        .provider-option.active { border-color: var(--primary); background: rgba(241,196,15,0.05); }
        
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer; border: none; transition: .2s; }
        .btn-primary { background: var(--primary); color: #000; }
        .btn-secondary { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.1); }
        
        .provider-fields { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .provider-fields.active { display: block; animation: fadeIn .3s; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: .9rem; }
        .alert-success { background: rgba(37,211,102,0.1); border: 1px solid rgba(37,211,102,0.3); color: var(--whatsapp); }
        .alert-error { background: rgba(242,47,70,0.1); border: 1px solid rgba(242,47,70,0.3); color: var(--twilio); }
        
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th { text-align: left; padding: 12px; color: var(--muted); text-transform: uppercase; font-size: .7rem; border-bottom: 1px solid var(--border); }
        td { padding: 12px; border-bottom: 1px solid #1a1e26; }
        .status-pill { padding: 2px 8px; border-radius: 12px; font-size: .7rem; font-weight: 800; }
        .status-ok { background: rgba(37,211,102,0.1); color: var(--whatsapp); }
        .status-err { background: rgba(242,47,70,0.1); color: var(--twilio); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h1 style="margin:0; font-size:1.8rem; display:flex; align-items:center; gap:12px;">
                    <i class="fas fa-bell" style="color:var(--primary)"></i> Central de Notificações
                </h1>
                <p style="color:var(--muted); margin:5px 0 0;">WhatsApp e SMS automático para seus clientes.</p>
            </div>
            <a href="orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <?php echo $msg; ?>

        <form method="POST">
            <div class="grid">
                <!-- Coluna 1: Provedores -->
                <div class="card">
                    <h3><i class="fas fa-plug"></i> Provedor Ativo</h3>
                    <div class="provider-select">
                        <div class="provider-option <?php echo ($cfg['notif_provider']??'log')==='zapi'?'active':''; ?>" onclick="selectProvider('zapi')">
                            <i class="fab fa-whatsapp" style="color:var(--zapi)"></i> Z-API
                        </div>
                        <div class="provider-option <?php echo ($cfg['notif_provider']??'log')==='waapi'?'active':''; ?>" onclick="selectProvider('waapi')">
                            <i class="fas fa-robot" style="color:var(--whatsapp)"></i> Evolution
                        </div>
                        <div class="provider-option <?php echo ($cfg['notif_provider']??'log')==='twilio'?'active':''; ?>" onclick="selectProvider('twilio')">
                            <i class="fas fa-sms" style="color:var(--twilio)"></i> Twilio
                        </div>
                        <div class="provider-option <?php echo ($cfg['notif_provider']??'log')==='log'?'active':''; ?>" onclick="selectProvider('log')">
                            <i class="fas fa-history"></i> Só Log
                        </div>
                    </div>
                    <input type="hidden" name="notif_provider" id="active_provider" value="<?php echo $cfg['notif_provider']??'log'; ?>">

                    <!-- Campos Z-API -->
                    <div id="fields-zapi" class="provider-fields <?php echo ($cfg['notif_provider']??'log')==='zapi'?'active':''; ?>">
                        <div class="form-group">
                            <label>ID da Instância (Instance ID)</label>
                            <input type="text" name="notif_zapi_instance" value="<?php echo htmlspecialchars($cfg['notif_zapi_instance']??''); ?>" placeholder="Ex: 3B8C...">
                        </div>
                        <div class="form-group">
                            <label>Token da Instância</label>
                            <input type="password" name="notif_zapi_token" value="<?php echo htmlspecialchars($cfg['notif_zapi_token']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Client Token (Opcional)</label>
                            <input type="password" name="notif_zapi_client_token" value="<?php echo htmlspecialchars($cfg['notif_zapi_client_token']??''); ?>">
                        </div>
                    </div>

                    <!-- Campos Evolution API -->
                    <div id="fields-waapi" class="provider-fields <?php echo ($cfg['notif_provider']??'log')==='waapi'?'active':''; ?>">
                        <div class="form-group">
                            <label>URL da API (Base URL)</label>
                            <input type="text" name="notif_waapi_url" value="<?php echo htmlspecialchars($cfg['notif_waapi_url']??''); ?>" placeholder="https://api.seusite.com">
                        </div>
                        <div class="form-group">
                            <label>API Key (Global/User)</label>
                            <input type="password" name="notif_waapi_key" value="<?php echo htmlspecialchars($cfg['notif_waapi_key']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Nome da Instância</label>
                            <input type="text" name="notif_waapi_instance" value="<?php echo htmlspecialchars($cfg['notif_waapi_instance']??'default'); ?>">
                        </div>
                        
                        <!-- Status e QR Code Section -->
                        <div id="wa-manager" style="margin-top:15px; padding:15px; background:rgba(0,0,0,0.2); border-radius:10px; border:1px solid #333;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span id="wa-status-label" style="font-size:0.8rem; font-weight:bold; color:var(--muted);">STATUS: <span id="wa-status-text">Buscando...</span></span>
                                <button type="button" onclick="checkWaStatus()" class="btn btn-sm" style="padding:4px 8px; font-size:0.7rem; background:#333;"><i class="fas fa-sync"></i></button>
                            </div>
                            
                            <div id="wa-qrcode-container" style="display:none; margin-top:15px; background:#fff; padding:10px; border-radius:8px; text-align:center;">
                                <p style="color:#000; font-weight:bold; margin-bottom:10px;">Escaneie o QR Code:</p>
                                <img id="wa-qrcode-img" src="" style="max-width:250px; border:1px solid #ddd;">
                            </div>

                            <div id="wa-actions" style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                                <button type="button" id="btn-wa-connect" class="btn" style="background:#25D366; color:#fff; display:none;">CONECTAR WHATSAPP</button>
                                <button type="button" id="btn-wa-logout" onclick="logoutWa()" class="btn" style="background:#e74c3c; color:#fff; display:none;">DESCONECTAR</button>
                                <button type="button" id="btn-wa-webhook" onclick="setWaWebhook()" class="btn" style="background:#0084FF; color:#fff;">🔧 ATIVAR IA (WEBHOOK)</button>
                            </div>
                        </div>
                    </div>

                    <!-- Campos Twilio -->
                    <div id="fields-twilio" class="provider-fields <?php echo ($cfg['notif_provider']??'log')==='twilio'?'active':''; ?>">
                        <div class="form-group">
                            <label>Account SID</label>
                            <input type="text" name="notif_twilio_sid" value="<?php echo htmlspecialchars($cfg['notif_twilio_sid']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Auth Token</label>
                            <input type="password" name="notif_twilio_token" value="<?php echo htmlspecialchars($cfg['notif_twilio_token']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Número Twilio (De:)</label>
                            <input type="text" name="notif_twilio_from" value="<?php echo htmlspecialchars($cfg['notif_twilio_from']??''); ?>" placeholder="+123456789">
                        </div>
                    </div>
                </div>

                <!-- Coluna 2: Config Geral & Teste -->
                <div>
                    <div class="card">
                        <h3><i class="fas fa-cog"></i> Configurações Gerais</h3>
                        <div class="form-group">
                            <label>WhatsApp do Admin (Alertas de novos pedidos)</label>
                            <input type="text" name="notif_admin_phone" value="<?php echo htmlspecialchars($cfg['notif_admin_phone']??''); ?>" placeholder="11999999999">
                        </div>
                        <div class="form-group">
                            <label>URL do Painel Admin (Para links em mensagens)</label>
                            <input type="text" name="notif_site_url" value="<?php echo htmlspecialchars($cfg['notif_site_url']??'https://fightarcade.com.br/admin'); ?>">
                        </div>
                        <button type="submit" name="save_notif" class="btn btn-primary" style="width:100%; margin-top:10px;">
                            <i class="fas fa-save"></i> SALVAR CONFIGURAÇÕES
                        </button>
                    </div>

                    <div class="card" style="margin-top:20px; border-top: 2px solid var(--primary);">
                        <h3><i class="fas fa-robot"></i> Automações e Lembretes</h3>
                        <p style="font-size: 0.85rem; color: var(--muted); margin-bottom: 1rem;">
                            Disparar manualmente os alertas de **estoque baixo**, **cobranças de dívidas** e **pedidos pendentes**.
                        </p>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" onclick="runAutomation()" id="btn-run-auto" class="btn" style="background: var(--primary); color: #000; padding:12px 20px; border-radius:8px; font-weight:bold; cursor:pointer; border:none; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-paper-plane"></i> Disparar Todos os Alertas Agora
                            </button>
                        </div>
                        <div id="automation-result" style="margin-top: 15px; display: none; font-size: 0.8rem; background: #000; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; white-space: pre-wrap; border: 1px solid #333; max-height:200px; overflow-y:auto; color: #00e676;"></div>
                    </div>

                    <div class="card">
                        <h3><i class="fas fa-paper-plane"></i> Enviar Teste</h3>
                        <div class="form-group">
                            <label>Número de Destino (DDD+Número)</label>
                            <input type="text" name="test_phone" placeholder="11988887777">
                        </div>
                        <button type="submit" name="test_send" class="btn btn-secondary" style="width:100%;">
                            <i class="fas fa-vial"></i> ENVIAR MENSAGEM DE TESTE
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Logs Recentes -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Logs Recentes</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Telefone</th>
                            <th>Mensagem</th>
                            <th>Status</th>
                            <th>Resposta da API</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--muted);">Nenhuma notificação enviada ainda.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><small><?php echo date('d/m H:i', strtotime($l['created_at'])); ?></small></td>
                            <td><?php echo $l['phone']; ?></td>
                            <td><div style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($l['message']); ?></div></td>
                            <td>
                                <span class="status-pill <?php echo $l['success'] ? 'status-ok' : 'status-err'; ?>">
                                    <?php echo $l['success'] ? 'OK' : 'ERRO'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="max-width:250px; font-size:0.7rem; color:var(--muted); font-family:monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($l['response']??''); ?>">
                                    <?php echo htmlspecialchars($l['response'] ?? '-'); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function selectProvider(id) {
            document.querySelectorAll('.provider-option').forEach(opt => opt.classList.remove('active'));
            document.querySelectorAll('.provider-fields').forEach(f => f.classList.remove('active'));
            
            const selectedOpt = Array.from(document.querySelectorAll('.provider-option')).find(el => el.textContent.toLowerCase().includes(id) || (id==='log' && el.textContent.includes('Só Log')));
            if(selectedOpt) selectedOpt.classList.add('active');
            
            const fieldSet = document.getElementById('fields-' + id);
            if(fieldSet) fieldSet.classList.add('active');
            
            document.getElementById('active_provider').value = id;
            if(id === 'waapi') checkWaStatus();
        }

        // Evolution API Management
        function checkWaStatus() {
            const label = document.getElementById('wa-status-text');
            label.innerText = 'Consultando...';
            label.style.color = 'var(--muted)';
            
            fetch('notifications.php?wa_action=status')
                .then(r => r.json())
                .then(data => {
                    // Tratar se a instância não existe (Evolution retorna erro ou 404)
                    if (data.status === 404 || (data.message && data.message.includes('not found'))) {
                        label.innerText = 'NÃO ENCONTRADA';
                        label.style.color = 'var(--twilio)';
                        document.getElementById('btn-wa-connect').innerText = 'CRIAR INSTÂNCIA';
                        document.getElementById('btn-wa-connect').style.display = 'block';
                        document.getElementById('btn-wa-connect').onclick = createWaInstance;
                        return;
                    }

                    const rawState = data.instance ? data.instance.state : (data.state || 'disconnected');
                    const state = String(rawState).toLowerCase();
                    label.innerText = state.toUpperCase();
                    
                    if (state === 'open' || state === 'connected') {
                        label.style.color = 'var(--whatsapp)';
                        document.getElementById('wa-qrcode-container').style.display = 'none';
                        document.getElementById('btn-wa-connect').style.display = 'none';
                        document.getElementById('btn-wa-logout').style.display = 'block';
                    } else {
                        label.style.color = 'var(--twilio)';
                        document.getElementById('btn-wa-connect').innerText = 'CONECTAR WHATSAPP';
                        document.getElementById('btn-wa-connect').style.display = 'block';
                        document.getElementById('btn-wa-connect').onclick = getWaQr;
                        document.getElementById('btn-wa-logout').style.display = 'none';
                    }
                })
                .catch(() => {
                    label.innerText = 'ERRO DE CONEXÃO';
                    label.style.color = 'var(--twilio)';
                });
        }

        function createWaInstance() {
            const label = document.getElementById('wa-status-text');
            label.innerText = 'Criando...';
            fetch('notifications.php?wa_action=create')
                .then(r => r.json())
                .then(data => {
                    alert('Instância criada com sucesso! Agora clique em Conectar para ver o QR Code.');
                    checkWaStatus();
                });
        }

        function getWaQr() {
            const container = document.getElementById('wa-qrcode-container');
            const img = document.getElementById('wa-qrcode-img');
            container.style.display = 'block';
            img.style.opacity = '0.5';
            
            fetch('notifications.php?wa_action=connect')
                .then(r => r.json())
                .then(data => {
                    if (data.base64) {
                        img.src = data.base64;
                        img.style.opacity = '1';
                        // Auto check status every 5 seconds while QR is visible
                        const interval = setInterval(() => {
                            if (container.style.display === 'none') { clearInterval(interval); return; }
                            fetch('notifications.php?wa_action=status').then(r=>r.json()).then(d=>{
                                const st = d.instance ? d.instance.state : (d.state || '');
                                if(st === 'open' || st === 'CONNECTED') {
                                    container.style.display = 'none';
                                    checkWaStatus();
                                    clearInterval(interval);
                                }
                            });
                        }, 5000);
                    } else if(data.code === 'instance_already_connected') {
                        alert('Já está conectado!');
                        container.style.display = 'none';
                        checkWaStatus();
                    } else {
                        alert('Erro: ' + (data.message || 'Falha ao gerar QR'));
                    }
                });
        }

        function logoutWa() {
            if(!confirm('Deseja realmente desconectar o WhatsApp?')) return;
            fetch('notifications.php?wa_action=logout').then(() => {
                checkWaStatus();
            });
        }

        function setWaWebhook() {
            const btn = event.target.closest('button');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ativando...';
            
            fetch('notifications.php?wa_action=set_webhook')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Inteligência Artificial Ativada!\nO Webhook foi configurado para: ' + data.url);
                    } else {
                        alert('❌ Erro: ' + data.error);
                    }
                    btn.disabled = false;
                    btn.innerHTML = original;
                })
                .catch(() => {
                    alert('Erro na requisição.');
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        }

        // Init
        window.onload = () => {
            if(document.getElementById('active_provider').value === 'waapi') checkWaStatus();
        }

        function runAutomation() {
            if(!confirm('Deseja disparar todas as automações agora? Isso enviará mensagens de: \n• Cobrança de devedores\n• Lembrete de pedidos pendentes\n• Alerta de estoque baixo\n• Sincronização de rastreios (Melhor Envio/Lalamove)')) return;
            
            const btn = document.getElementById('btn-run-auto');
            const res = document.getElementById('automation-result');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            res.style.display = 'block';
            res.innerHTML = '> Iniciando worker...\n';
            
            fetch('notifications.php?trigger_automation=1')
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        res.innerHTML += data.output + '\n> ✅ Concluído com sucesso!';
                        btn.innerHTML = '<i class="fas fa-check"></i> Alertas Enviados!';
                    } else {
                        res.innerHTML += '> ❌ Erro: ' + data.error;
                        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro na Automação';
                    }
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Disparar Todos os Alertas Agora';
                    }, 5000);
                })
                .catch(err => {
                    res.innerHTML += '> ❌ Erro de conexão.';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Disparar Todos os Alertas Agora';
                });
        }
    </script>
</body>
</html>
<?php
} catch (Throwable $e) {
    ?>
    <div style="background:#0b0e14; color:#fff; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif; padding:20px; text-align:center;">
        <h1 style="font-size:4rem; margin-bottom:0;">🔔</h1>
        <h2 style="color:#e74c3c;">Falha na Central de Notificações</h2>
        <p style="color:#888; max-width:600px; margin-bottom:30px;">Ocorreu um erro ao carregar as configurações de notificação. Tente o reparo de emergência.</p>
        
        <div style="background:#1a1e2a; padding:20px; border-radius:10px; border:1px solid #333; margin-bottom:30px; text-align:left; font-family:monospace; font-size:0.9rem; max-width:800px; overflow-x:auto;">
            <b style="color:#f1c40f;">Erro:</b><br>
            <?php echo $e->getMessage(); ?>
        </div>

        <div style="display:flex; gap:15px;">
            <a href="emergency_fix.php" style="background:#f1c40f; color:#000; padding:12px 25px; border-radius:8px; font-weight:bold; text-decoration:none;">🚀 RODAR REPARO DE EMERGÊNCIA</a>
            <a href="dashboard.php" style="background:#333; color:#fff; padding:12px 25px; border-radius:8px; text-decoration:none;">🏠 Dashboard</a>
        </div>
    </div>
    <?php
}
?>
