<?php
/**
 * fabrica/whatsapp.php
 * Painel de Conexão WhatsApp Dedicado da Fábrica (B2B)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

// Authentication Check
if (!isset($_SESSION['factory_user_id'])) {
    header('Location: login.php');
    exit;
}

$notif = new NotificationService($pdo, true); // true = escopo Fábrica B2B
$msg = '';

$cfg = $notif->getConfig();

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notif'])) {
    $settings = [
        'notif_provider'       => $_POST['notif_provider'] ?? 'log',
        'notif_admin_phone'    => preg_replace('/\D/', '', $_POST['notif_admin_phone'] ?? ''),
        'notif_site_url'       => rtrim($_POST['notif_site_url'] ?? '', '/'),
        'notif_waapi_url'      => $_POST['notif_waapi_url'] ?? '',
        'notif_waapi_key'      => $_POST['notif_waapi_key'] ?? '',
        'notif_waapi_instance' => $_POST['notif_waapi_instance'] ?? '',
    ];
    $notif->saveSettings($settings);
    $msg = '<div class="alert alert-success">✅ Configurações de notificação da Fábrica salvas!</div>';
    $notif = new NotificationService($pdo, true); // Recarrega
    $cfg = $notif->getConfig();
}

// Handle Test Send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_send'])) {
    $target  = $_POST['test_phone'] ?: ($cfg['notif_admin_phone'] ?? '');
    $message = "🛠️ *TESTE FÁBRICA ERP*\nEsta é uma mensagem de teste da Central de Notificações B2B! ✅";
    
    if ($target) {
        $res = $notif->send($target, $message);
        if ($res) {
            $msg = '<div class="alert alert-success">🚀 Mensagem de teste enviada com sucesso para ' . $target . '!</div>';
        } else {
            $msg = '<div class="alert alert-danger">❌ Falha ao enviar mensagem de teste. Verifique se a instância está conectada.</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">⚠️ Informe um número de telefone com DDD para realizar o teste.</div>';
    }
}

// AJAX actions for Evolution API
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        echo $res ?: json_encode(['state' => 'disconnected', 'curl_error' => $err]);
    } 
    elseif ($action === 'connect') {
        $ch = curl_init("$url/instance/connect/$inst");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        if ($res) {
            echo $res;
        } else {
            echo json_encode(['error' => 'Falha ao gerar QR', 'curl_error' => $err]);
        }
    }
    elseif ($action === 'create') {
        $ch = curl_init("$url/instance/create");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'instanceName' => $inst,
            'token' => $key,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS'
        ]));
        $res = curl_exec($ch);
        $err = curl_error($ch);
        echo $res ?: json_encode(['error' => 'Falha ao criar', 'curl_error' => $err]);
    }
    elseif ($action === 'logout') {
        $ch = curl_init("$url/instance/logout/$inst");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        echo $res ?: json_encode(['success' => true]);
    }
    elseif ($action === 'set_webhook') {
        $res = $notif->setWebhookEvolution();
        echo json_encode($res);
    }
    exit;
}

$active_page = 'whatsapp.php';
require_once __DIR__ . '/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4"><i class="fab fa-whatsapp text-success"></i> WhatsApp Central — Fábrica B2B</h2>
        <?php echo $msg; ?>
    </div>
</div>

<div class="grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
    <!-- Config Card -->
    <div class="card" style="background:#141820; border:1px solid #252d3d; border-radius:12px; padding:1.5rem; color:#fff;">
        <h3 style="margin-top:0; font-size:1.1rem; border-bottom:1px solid #252d3d; padding-bottom:10px;"><i class="fas fa-cog"></i> Configuração de Envio</h3>
        <form method="POST" style="margin-top:1.2rem;">
            <input type="hidden" name="save_notif" value="1">
            <input type="hidden" name="notif_provider" id="active_provider" value="<?php echo htmlspecialchars($cfg['notif_provider'] ?? 'log'); ?>">

            <div class="form-group mb-3">
                <label>Canal de Notificação Ativo</label>
                <div style="display:flex; gap:10px; margin-top:5px;">
                    <button type="button" class="btn provider-btn <?php echo ($cfg['notif_provider'] ?? 'log') === 'log' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="selectProvider('log')" style="flex:1;">
                        <i class="fas fa-file-alt"></i> Só Log
                    </button>
                    <button type="button" class="btn provider-btn <?php echo ($cfg['notif_provider'] ?? 'log') === 'waapi' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="selectProvider('waapi')" style="flex:1;">
                        <i class="fab fa-whatsapp"></i> Evolution API
                    </button>
                </div>
            </div>

            <div class="form-group mb-3">
                <label>Número Admin da Fábrica (Recebe Alertas)</label>
                <input type="text" name="notif_admin_phone" class="form-control" placeholder="Ex: 5511999999999" value="<?php echo htmlspecialchars($cfg['notif_admin_phone'] ?? ''); ?>">
            </div>

            <div class="form-group mb-3">
                <label>URL do Catálogo (Base URL)</label>
                <input type="text" name="notif_site_url" class="form-control" placeholder="https://www.fightarcade.com.br/catalogo" value="<?php echo htmlspecialchars($cfg['notif_site_url'] ?? 'https://www.fightarcade.com.br/catalogo'); ?>">
            </div>

            <!-- Evolution API Fields -->
            <div id="fields-waapi" class="provider-fields" style="display: <?php echo ($cfg['notif_provider'] ?? 'log') === 'waapi' ? 'block' : 'none'; ?>; border-top:1px dashed #252d3d; padding-top:1rem; margin-top:1rem;">
                <div class="form-group mb-3">
                    <label>URL da Evolution API</label>
                    <input type="text" name="notif_waapi_url" class="form-control" placeholder="https://api.meudominio.com" value="<?php echo htmlspecialchars($cfg['notif_waapi_url'] ?? ''); ?>">
                </div>
                <div class="form-group mb-3">
                    <label>API Apikey Token</label>
                    <input type="password" name="notif_waapi_key" class="form-control" placeholder="Sua Chave API" value="<?php echo htmlspecialchars($cfg['notif_waapi_key'] ?? ''); ?>">
                </div>
                <div class="form-group mb-3">
                    <label>Nome da Instância (Exclusiva da Fábrica)</label>
                    <input type="text" name="notif_waapi_instance" class="form-control" placeholder="Ex: fabrica_whatsapp" value="<?php echo htmlspecialchars($cfg['notif_waapi_instance'] ?? ''); ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" style="margin-top:1rem; background:var(--primary); color:#000; width:100%;"><i class="fas fa-save"></i> Salvar Configurações</button>
        </form>
    </div>

    <!-- Live Connection & Control -->
    <div class="card" style="background:#141820; border:1px solid #252d3d; border-radius:12px; padding:1.5rem; color:#fff;">
        <h3 style="margin-top:0; font-size:1.1rem; border-bottom:1px solid #252d3d; padding-bottom:10px;"><i class="fab fa-whatsapp"></i> Painel de Conexão (Evolution)</h3>
        
        <div style="margin-top:1.5rem; text-align:center; padding:1.5rem; background:#0b0e14; border-radius:8px; border:1px solid #252d3d;">
            <div style="font-size:0.85rem; color:#8892b0; text-transform:uppercase; letter-spacing:1px;">Status da Instância</div>
            <div id="wa-status-text" style="font-size:1.6rem; font-weight:bold; margin-top:5px; color:#888;">INATIVA</div>
            
            <div id="wa-qrcode-container" style="display:none; margin:1.5rem auto; padding:10px; background:#fff; border-radius:8px; width:220px; height:220px; box-shadow:0 0 15px rgba(0,0,0,0.5);">
                <img id="wa-qrcode-img" src="" alt="QR Code" style="width:100%; height:100%; transition:opacity 0.3s; display:block;">
            </div>
            
            <div style="display:flex; flex-direction:column; gap:10px; margin-top:1.5rem;">
                <button type="button" id="btn-wa-connect" class="btn btn-primary" onclick="getWaQr()" style="display:none; background:var(--primary); color:#000;">Conectar WhatsApp</button>
                <button type="button" id="btn-wa-logout" class="btn btn-danger" onclick="logoutWa()" style="display:none; background:#ef4444; color:#fff;">Desconectar / Sair</button>
                <button type="button" class="btn btn-secondary" onclick="checkWaStatus()"><i class="fas fa-sync"></i> Atualizar Status</button>
            </div>
        </div>

        <div style="margin-top:1.5rem; border-top:1px solid #252d3d; padding-top:1.2rem;">
            <h4 style="margin:0 0 8px 0; font-size:0.95rem;"><i class="fas fa-robot text-success"></i> Automação & IA</h4>
            <p style="font-size:0.8rem; color:#8892b0; margin:0 0 12px 0;">Configure o Webhook da Evolution API automaticamente para encaminhar mensagens de clientes e relatórios de defeitos para a IA da fábrica.</p>
            <button type="button" class="btn btn-secondary w-100" onclick="setWaWebhook()" style="width:100%;"><i class="fas fa-network-wired"></i> Ativar Webhook & IA da Fábrica</button>
        </div>
    </div>

    <!-- Test Send Card -->
    <div class="card" style="background:#141820; border:1px solid #252d3d; border-radius:12px; padding:1.5rem; color:#fff;">
        <h3 style="margin-top:0; font-size:1.1rem; border-bottom:1px solid #252d3d; padding-bottom:10px;"><i class="fas fa-paper-plane"></i> Teste de Mensagem</h3>
        
        <form method="POST" style="margin-top:1.2rem;">
            <input type="hidden" name="test_send" value="1">
            <div class="form-group mb-3">
                <label>Número de Telefone de Destino</label>
                <input type="text" name="test_phone" class="form-control" placeholder="Ex: 5511999999999" value="<?php echo htmlspecialchars($cfg['notif_admin_phone'] ?? ''); ?>">
                <small style="display:block; font-size:0.75rem; color:#8892b0; margin-top:5px;">Certifique-se de incluir o código do país (55) e o DDD.</small>
            </div>
            
            <button type="submit" class="btn btn-secondary w-100" style="width:100%; border-color:var(--primary);"><i class="fas fa-paper-plane"></i> Enviar Mensagem de Teste</button>
        </form>

        <div style="margin-top:1.5rem; border-top:1px solid #252d3d; padding-top:1.2rem;">
            <h4 style="margin:0 0 8px 0; font-size:0.95rem;"><i class="fas fa-info-circle"></i> logs Recentes</h4>
            <div style="max-height:150px; overflow-y:auto; background:#0b0e14; padding:8px; border-radius:6px; font-family:monospace; font-size:0.75rem; border:1px solid #252d3d;">
                <?php 
                try {
                    $logs = $notif->getRecentLogs(10);
                    if (empty($logs)) {
                        echo '<div style="color:#5a6478;">Nenhum registro de envio recente.</div>';
                    } else {
                        foreach ($logs as $l) {
                            $status = $l['success'] ? '<span style="color:#00e676;">[OK]</span>' : '<span style="color:#ef4444;">[FAIL]</span>';
                            echo '<div style="margin-bottom:4px;">' . date('H:i:s', strtotime($l['created_at'])) . ' - ' . $status . ' ' . htmlspecialchars($l['phone']) . ': ' . htmlspecialchars(substr($l['message'],0,30)) . '...</div>';
                        }
                    }
                } catch(Exception $e) {
                    echo '<div style="color:#ef4444;">Erro ao buscar logs.</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
    function selectProvider(id) {
        document.querySelectorAll('.provider-btn').forEach(btn => {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        });
        
        // Find clicked button
        event.currentTarget.classList.remove('btn-secondary');
        event.currentTarget.classList.add('btn-primary');
        
        document.getElementById('fields-waapi').style.display = (id === 'waapi') ? 'block' : 'none';
        document.getElementById('active_provider').value = id;
        
        if (id === 'waapi') checkWaStatus();
    }

    function checkWaStatus() {
        const label = document.getElementById('wa-status-text');
        label.innerText = 'Consultando...';
        label.style.color = '#8892b0';
        
        fetch('whatsapp.php?wa_action=status')
            .then(r => r.json())
            .then(data => {
                if (data.status === 404 || (data.message && data.message.includes('not found'))) {
                    label.innerText = 'NÃO ENCONTRADA';
                    label.style.color = '#ef4444';
                    document.getElementById('btn-wa-connect').innerText = 'CRIAR INSTÂNCIA';
                    document.getElementById('btn-wa-connect').style.display = 'block';
                    document.getElementById('btn-wa-connect').onclick = createWaInstance;
                    return;
                }

                const rawState = data.instance ? data.instance.state : (data.state || 'disconnected');
                const state = String(rawState).toLowerCase();
                label.innerText = state.toUpperCase();
                
                if (state === 'open' || state === 'connected') {
                    label.style.color = '#00e676';
                    document.getElementById('wa-qrcode-container').style.display = 'none';
                    document.getElementById('btn-wa-connect').style.display = 'none';
                    document.getElementById('btn-wa-logout').style.display = 'block';
                } else {
                    label.style.color = '#ef4444';
                    document.getElementById('btn-wa-connect').innerText = 'CONECTAR WHATSAPP';
                    document.getElementById('btn-wa-connect').style.display = 'block';
                    document.getElementById('btn-wa-connect').onclick = getWaQr;
                    document.getElementById('btn-wa-logout').style.display = 'none';
                }
            })
            .catch(() => {
                label.innerText = 'ERRO DE CONEXÃO';
                label.style.color = '#ef4444';
            });
    }

    function createWaInstance() {
        const label = document.getElementById('wa-status-text');
        const btnConnect = document.getElementById('btn-wa-connect');
        label.innerText = 'Criando instância...';
        label.style.color = '#f1c40f';
        btnConnect.disabled = true;
        btnConnect.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando...';
        
        fetch('whatsapp.php?wa_action=create')
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    alert('Erro detalhado: ' + JSON.stringify(data));
                    btnConnect.disabled = false;
                    btnConnect.innerHTML = 'CRIAR INSTÂNCIA';
                    checkWaStatus();
                    return;
                }
                label.innerText = 'Instância criada! Conectando...';
                label.style.color = '#00e676';
                // Wait 2s for instance to be ready, then get QR code
                setTimeout(() => {
                    btnConnect.disabled = false;
                    btnConnect.innerHTML = 'CONECTAR WHATSAPP';
                    btnConnect.onclick = getWaQr;
                    getWaQr();
                }, 2000);
            })
            .catch(err => {
                alert('Erro de rede ao criar instância.');
                btnConnect.disabled = false;
                btnConnect.innerHTML = 'CRIAR INSTÂNCIA';
            });
    }

    function getWaQr() {
        const container = document.getElementById('wa-qrcode-container');
        const img = document.getElementById('wa-qrcode-img');
        container.style.display = 'block';
        img.style.opacity = '0.5';
        
        fetch('whatsapp.php?wa_action=connect')
            .then(r => r.json())
            .then(data => {
                if (data.base64) {
                    img.src = data.base64;
                    img.style.opacity = '1';
                    
                    const interval = setInterval(() => {
                        if (container.style.display === 'none') { clearInterval(interval); return; }
                        fetch('whatsapp.php?wa_action=status').then(r=>r.json()).then(d=>{
                            const st = d.instance ? d.instance.state : (d.state || '');
                            if(st === 'open' || st === 'CONNECTED') {
                                container.style.display = 'none';
                                checkWaStatus();
                                clearInterval(interval);
                            }
                        });
                    }, 5000);
                } else if (data.code === 'instance_already_connected') {
                    alert('Já conectado!');
                    container.style.display = 'none';
                    checkWaStatus();
                } else {
                    alert('Erro detalhado da API: ' + (data.curl_error || data.message || data.error || JSON.stringify(data)));
                }
            })
            .catch(err => {
                alert('Erro de processamento: ' + err.message);
            });
    }

    function logoutWa() {
        if(!confirm('Deseja realmente desconectar o WhatsApp da fábrica?')) return;
        fetch('whatsapp.php?wa_action=logout').then(() => {
            checkWaStatus();
        });
    }

    function setWaWebhook() {
        const btn = event.target.closest('button') || event.target;
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando instância...';
        
        // First check if instance exists
        fetch('whatsapp.php?wa_action=status')
            .then(r => r.json())
            .then(data => {
                const notFound = (data.status === 404 || (data.message && data.message.includes('not found')));
                
                if (notFound) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando instância primeiro...';
                    // Create instance first
                    return fetch('whatsapp.php?wa_action=create')
                        .then(r => r.json())
                        .then(() => {
                            // Wait for instance to be ready
                            return new Promise(resolve => setTimeout(resolve, 3000));
                        })
                        .then(() => {
                            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ativando webhook...';
                            return fetch('whatsapp.php?wa_action=set_webhook').then(r => r.json());
                        });
                } else {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ativando webhook...';
                    return fetch('whatsapp.php?wa_action=set_webhook').then(r => r.json());
                }
            })
            .then(data => {
                if (data && data.success) {
                    alert('✅ Webhook & IA Ativados!\nURL configurada: ' + data.url);
                    checkWaStatus();
                } else {
                    alert('❌ Erro: ' + (data?.error || 'Falha ao configurar webhook. Verifique se a instância está conectada.'));
                }
                btn.disabled = false;
                btn.innerHTML = original;
            })
            .catch(err => {
                alert('Erro de rede: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = original;
            });
    }

    // Init
    window.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('active_provider').value === 'waapi') {
            checkWaStatus();
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
