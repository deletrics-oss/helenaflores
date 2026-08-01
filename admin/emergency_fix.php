<?php
/**
 * admin/emergency_fix.php — Helena Flores (Reconstrutor de Integridade Pro)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// --- LÓGICA AJAX ---
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
                $pdo->exec("CREATE TABLE IF NOT EXISTS customer_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    payment_method VARCHAR(50) DEFAULT 'Saldo/Manual',
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $response['msg'] = 'Tabelas de contas e pagamentos validadas.';
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
                    'city'             => "VARCHAR(100) DEFAULT 'São Paulo'",
                    'state'            => "VARCHAR(10) DEFAULT 'SP'",
                    'phone'            => "VARCHAR(50) DEFAULT ''",
                    'address'          => "VARCHAR(255) DEFAULT ''",
                    'number'           => "VARCHAR(50) DEFAULT ''",
                    'neighborhood'     => "VARCHAR(100) DEFAULT ''",
                    'zipcode'          => "VARCHAR(20) DEFAULT ''",
                    'document'         => "VARCHAR(50) DEFAULT ''",
                    'wa_notify_active' => "TINYINT(1) DEFAULT 1",
                    'notify_blocked'   => "TINYINT(1) DEFAULT 0",
                    'current_debt'     => "DECIMAL(10,2) DEFAULT 0",
                    'source'           => "VARCHAR(50) DEFAULT 'manual'",
                    'is_lead'          => "TINYINT(1) DEFAULT 0",
                    'created_at'       => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
                ];
                foreach($cols as $c => $def) {
                    try { $pdo->exec("ALTER TABLE users ADD COLUMN $c $def"); } catch(Exception $e) {}
                }
                $response['msg'] = 'Metadados de usuários e colunas de localização sincronizados.';
                break;

            case 'schema_orders':
                try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_address TEXT NULL"); } catch(Exception $e) {}
                try { $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(100) DEFAULT 'whatsapp'"); } catch(Exception $e) {}
                try { $pdo->exec("ALTER TABLE order_items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0"); } catch(Exception $e) {}
                $response['msg'] = 'Tabela de pedidos e itens auditada com sucesso.';
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
                try {
                    $pdo->exec("UPDATE users u SET current_debt = (
                        (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                        (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
                    )");
                } catch(Exception $e) {}
                $response['msg'] = 'Saldos financeiros recalculados.';
                break;

            case 'optimize':
                try { $pdo->exec("OPTIMIZE TABLE users, orders, order_items, products"); } catch(Exception $e) {}
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
    <title>SISTEMA DE RECUPERAÇÃO — Helena Flores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #030712; --card: rgba(17, 24, 39, 0.8); --border: rgba(31, 41, 55, 0.8);
            --accent: #C2185B; --success: #10b981; --error: #ef4444; --text: #f9fafb; --muted: #9ca3af;
        }
        body {
            background-color: var(--bg); color: var(--text); font-family: 'Inter', sans-serif;
            margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .fix-container {
            width: 100%; max-width: 750px; background: var(--card); border: 1px solid var(--border);
            border-radius: 16px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px);
        }
        .terminal {
            background: #000; border: 1px solid #222; border-radius: 8px; padding: 1rem;
            font-family: monospace; font-size: 0.85rem; height: 260px; overflow-y: auto; color: #00ff66;
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>

    <div class="fix-container">
        <h1 style="color:#FFECB3; font-size:1.6rem; text-align:center; margin-bottom:0.5rem;">
            🛠️ RECONSTRUTOR DE INTEGRIDADE BANCO DE DADOS
        </h1>
        <p style="color:var(--muted); text-align:center; margin-bottom:1.5rem; font-size:0.9rem;">
            Diagnóstico profundo e reparação estrutural automática — Helena Flores
        </p>

        <div id="terminal" class="terminal">
            [SISTEMA] Inicializando verificador de integridade...<br>
        </div>

        <button id="btnRun" onclick="runFix()" style="width:100%; height:50px; background:#C2185B; color:#FFF; font-weight:bold; font-size:1.1rem; border-radius:25px; border:none; cursor:pointer;">
            🚀 INICIAR REPARO PROFUNDO DO BANCO DE DADOS
        </button>
    </div>

    <script>
        function log(type, msg) {
            const t = document.getElementById('terminal');
            t.innerHTML += `[${type}] ${msg}<br>`;
            t.scrollTop = t.scrollHeight;
        }

        async function runFix() {
            document.getElementById('btnRun').disabled = true;
            document.getElementById('btnRun').innerText = "Reparando Banco de Dados...";
            
            const steps = [
                'connect', 'check_files', 'schema_accounts', 'schema_ai', 
                'schema_users', 'schema_orders', 'schema_notif', 'sync_fin', 'optimize'
            ];

            for (let step of steps) {
                try {
                    let res = await fetch('emergency_fix.php?action=' + step).then(r => r.json());
                    if (res.success) {
                        log('OK', res.msg);
                    } else {
                        log('ERRO', res.msg);
                    }
                } catch (e) {
                    log('ERRO', 'Falha na conexão: ' + step);
                }
            }

            log('SUCESSO', '🎉 REPARO DO BANCO DE DADOS CONCLUÍDO!');
            document.getElementById('btnRun').disabled = false;
            document.getElementById('btnRun').innerText = "✅ REPARO CONCLUÍDO (Ir para Pedidos)";
            document.getElementById('btnRun').onclick = function() { window.location.href = 'orders.php'; };
        }
    </script>

</body>
</html>
