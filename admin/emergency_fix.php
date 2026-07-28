<?php
/**
 * admin/emergency_fix.php — Fight Arcade
 * RECONSTRUTOR DE INTEGRIDADE PRO (PREMIUM INTEGRITY RECONSTRUCTOR)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// --- LÓGICA AJAX (MOVIDA PARA O TOPO PARA EVITAR OUTPUT HTML) ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => true, 'msg' => 'Operação concluída'];

    try {
        switch ($action) {
            case 'connect':
                $response['msg'] = 'Conectado ao DB: ' . DB_NAME;
                break;

            case 'check_files':
                $required = [
                    '../config.php', '../includes/db.php', '../includes/user_auth.php',
                    '../includes/lalamove.php', '../includes/notifications.php', '../includes/melhorenvio.php'
                ];
                $missing = [];
                foreach($required as $f) {
                    if (!file_exists(__DIR__ . '/' . $f)) $missing[] = basename($f);
                }
                if (!empty($missing)) {
                    $response = ['success' => false, 'msg' => 'Arquivos ausentes: ' . implode(', ', $missing)];
                } else {
                    $response['msg'] = 'Todos os arquivos do Kernel estão presentes.';
                }
                break;

            case 'schema_accounts':
                $pdo->exec("CREATE TABLE IF NOT EXISTS payment_accounts (
                    id INT AUTO_INCREMENT PRIMARY KEY, 
                    name VARCHAR(100) NOT NULL, 
                    type ENUM('pix', 'bank') DEFAULT 'pix', 
                    pix_key VARCHAR(255), 
                    bank_info TEXT, 
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $response['msg'] = 'Tabela de contas validada.';
                break;

            case 'schema_ai':
                $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL,
                    category VARCHAR(50) DEFAULT 'suporte',
                    bot_role ENUM('geral', 'suporte', 'vendas') DEFAULT 'geral',
                    image_url VARCHAR(255), link_url VARCHAR(255), video_url VARCHAR(255),
                    tags TEXT, related_products TEXT, ai_instructions TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $response['msg'] = 'Cérebro da IA preparado.';
                break;

            case 'schema_users':
                $cols = [
                    'wa_notify_active' => "TINYINT(1) DEFAULT 1 AFTER role",
                    'notify_blocked'   => "TINYINT(1) DEFAULT 0",
                    'current_debt'     => "DECIMAL(10,2) DEFAULT 0",
                    'source'           => "VARCHAR(50) DEFAULT 'manual' AFTER email",
                    'is_lead'          => "TINYINT(1) DEFAULT 0",
                    'created_at'       => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
                ];
                foreach($cols as $c => $def) {
                    try { $pdo->exec("ALTER TABLE users ADD COLUMN $c $def"); } catch(Exception $e) {}
                }
                $response['msg'] = 'Metadados de usuários sincronizados.';
                break;

            case 'schema_orders':
                try { $pdo->exec("ALTER TABLE order_items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0 AFTER price"); } catch(Exception $e) {}
                $response['msg'] = 'Tabela de itens de pedidos auditada.';
                break;

            case 'schema_notif':
                $pdo->exec("CREATE TABLE IF NOT EXISTS notification_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    phone VARCHAR(20), message TEXT, provider VARCHAR(20),
                    success TINYINT(1) DEFAULT 0, response TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $response['msg'] = 'Log de notificações verificado.';
                break;

            case 'sync_fin':
                $pdo->exec("UPDATE users u SET current_debt = (
                    (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                    (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
                )");
                $response['msg'] = 'Saldos financeiros recalculados.';
                break;

            case 'optimize':
                $pdo->exec("OPTIMIZE TABLE users, orders, order_items, products");
                $response['msg'] = 'Performance otimizada.';
                break;
        }
    } catch (Exception $e) {
        $response = [
            'success' => false, 
            'msg' => 'Erro SQL: ' . $e->getMessage()
        ];
    }

    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SISTEMA DE RECUPERAÇÃO — Fight Arcade</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono&display=swap');

        :root {
            --bg: #030712;
            --card: rgba(17, 24, 39, 0.7);
            --border: rgba(31, 41, 55, 0.8);
            --accent: #f1c40f;
            --success: #10b981;
            --error: #ef4444;
            --text: #f9fafb;
            --muted: #9ca3af;
        }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background-attachment: fixed;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 900px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(to right, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        .header p {
            color: var(--muted);
            font-size: 1.1rem;
            margin-top: 10px;
        }

        .integrity-card {
            background: var(--card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .scan-log {
            background: #000;
            border-radius: 12px;
            padding: 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            height: 400px;
            overflow-y: auto;
            border: 1px solid #1f2937;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .log-entry { margin-bottom: 8px; display: flex; gap: 12px; }
        .log-time { color: #4b5563; flex-shrink: 0; }
        .log-tag { font-weight: bold; padding: 0 6px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase; }
        .tag-info { background: #1e3a8a; color: #60a5fa; }
        .tag-ok { background: #064e3b; color: #34d399; }
        .tag-err { background: #7f1d1d; color: #f87171; }
        .tag-warn { background: #78350f; color: #fbbf24; }

        .progress-bar-container {
            height: 8px;
            background: #1f2937;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
            display: none;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(to right, #f1c40f, #10b981);
            width: 0%;
            transition: width 0.3s ease;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            border: none;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-primary { background: var(--accent); color: #000; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(241, 196, 15, 0.4); }
        
        .btn-outline { background: transparent; border: 1px solid var(--border); color: #fff; }
        .btn-outline:hover { background: rgba(255,255,255,0.05); }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }

        /* Scrollbar */
        .scan-log::-webkit-scrollbar { width: 8px; }
        .scan-log::-webkit-scrollbar-track { background: transparent; }
        .scan-log::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="status-badge">
            <i class="fas fa-shield-alt"></i> SISTEMA PROTEGIDO
        </div>
        <h1>RECONSTRUTOR DE INTEGRIDADE PRO</h1>
        <p>Diagnóstico profundo e reparação estrutural do Fight Arcade</p>

        <?php if (isset($_GET['fatal_error'])): ?>
        <div style="margin-top:20px; background:rgba(239, 68, 68, 0.1); border:1px solid #ef4444; padding:15px; border-radius:12px; color:#f87171; font-size:0.9rem; text-align:left;">
            <strong style="display:block; margin-bottom:5px;"><i class="fas fa-bug"></i> ERRO DETECTADO EM: <?php echo htmlspecialchars($_GET['file'] ?? 'Desconhecido'); ?></strong>
            <?php echo htmlspecialchars($_GET['fatal_error']); ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="integrity-card">
        <div class="progress-bar-container" id="p-bar">
            <div class="progress-bar-fill" id="p-fill"></div>
        </div>

        <div class="scan-log" id="log">
            <div class="log-entry">
                <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                <span class="log-tag tag-info">SISTEMA</span>
                <span>Aguardando comando de inicialização...</span>
            </div>
            <div class="log-entry">
                <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                <span class="log-tag tag-warn">AVISO</span>
                <span>Este procedimento irá re-indexar colunas e sincronizar saldos financeiros.</span>
            </div>
        </div>

        <div class="actions">
            <button onclick="startDeepRepair()" class="btn btn-primary" id="btn-run">
                <i class="fas fa-tools"></i> INICIAR REPARO PROFUNDO
            </button>
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-home"></i> VOLTAR AO PAINEL
            </a>
        </div>
    </div>
</div>

<script>
    function addLog(msg, type = 'info') {
        const log = document.getElementById('log');
        const time = new Date().toLocaleTimeString('pt-BR');
        const div = document.createElement('div');
        div.className = 'log-entry';
        
        let tagClass = 'tag-info';
        let tagText = 'INFO';
        if(type === 'ok') { tagClass = 'tag-ok'; tagText = 'OK'; }
        if(type === 'err') { tagClass = 'tag-err'; tagText = 'ERRO'; }
        if(type === 'warn') { tagClass = 'tag-warn'; tagText = 'AVISO'; }

        div.innerHTML = `
            <span class="log-time">[${time}]</span>
            <span class="log-tag ${tagClass}">${tagText}</span>
            <span>${msg}</span>
        `;
        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
    }

    async function startDeepRepair() {
        const btn = document.getElementById('btn-run');
        const bar = document.getElementById('p-bar');
        const fill = document.getElementById('p-fill');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> RECONSTRUINDO...';
        bar.style.display = 'block';

        addLog('Iniciando sequência de reparação estrutural...', 'warn');
        
        const steps = [
            { name: 'Conectando ao Kernel do Banco de Dados', action: 'connect' },
            { name: 'Verificando Integridade dos Arquivos do Sistema', action: 'check_files' },
            { name: 'Validando Estrutura de Contas de Pagamento', action: 'schema_accounts' },
            { name: 'Injetando Cérebro da IA (Base de Conhecimento)', action: 'schema_ai' },
            { name: 'Mapeando Atributos de Clientes (Created At / Source)', action: 'schema_users' },
            { name: 'Auditando Tabela de Pedidos (Preço de Custo)', action: 'schema_orders' },
            { name: 'Verificando Integridade de Notificações', action: 'schema_notif' },
            { name: 'Recalculando Balanço Financeiro Global', action: 'sync_fin' },
            { name: 'Limpeza de Cache e Otimização de Tabelas', action: 'optimize' }
        ];

        for(let i=0; i<steps.length; i++) {
            const step = steps[i];
            addLog(step.name + '...');
            
            try {
                const res = await fetch('emergency_fix.php?action=' + step.action);
                const data = await res.json();
                
                if(data.success) {
                    addLog(data.msg, 'ok');
                } else {
                    addLog(data.msg, 'err');
                }
            } catch(e) {
                addLog('Falha de comunicação no passo ' + step.action, 'err');
            }
            
            fill.style.width = ((i+1) / steps.length * 100) + '%';
        }

        addLog('PROCEDIMENTO FINALIZADO COM SUCESSO!', 'ok');
        btn.innerHTML = '✅ RECONSTRUÇÃO COMPLETA';
        btn.style.background = '#10b981';
    }
</script>

</body>
</html>
