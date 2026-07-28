<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// --- API TESTING HANDLERS ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['status' => 'error', 'message' => 'Ação desconhecida', 'data' => null];

    try {
        if ($action === 'test_mercadopago') {
            // 1. MERCADO PAGO
            $stmt = $pdo->prepare("SELECT settings_json FROM module_settings WHERE module_key = 'payment_mercadopago'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row)
                throw new Exception("Módulo não encontrado no banco de dados.");

            $settings = json_decode($row['settings_json'], true);
            $token = $settings['access_token'] ?? '';

            if (empty($token))
                throw new Exception("Access Token não configurado.");

            // Test Request: GET /users/me
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/users/me");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $token,
                "Content-Type: application/json"
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err)
                throw new Exception("Erro de Conexão Curl: $err");

            $json = json_decode($res, true);

            if ($httpCode === 200 && isset($json['id'])) {
                $response = [
                    'status' => 'success',
                    'message' => 'Conexão OK! Usuário: ' . ($json['first_name'] ?? 'N/A') . ' ' . ($json['last_name'] ?? ''),
                    'data' => $json
                ];
            } else {
                $msg = $json['message'] ?? 'Token inválido ou erro na API.';
                throw new Exception("Falha na API ($httpCode): $msg");
            }

        } elseif ($action === 'test_melhorenvio') {
            // 2. MELHOR ENVIO
            $stmt = $pdo->prepare("SELECT settings_json FROM module_settings WHERE module_key = 'shipping_melhorenvio'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row)
                throw new Exception("Módulo não encontrado.");
            $settings = json_decode($row['settings_json'], true);
            $token = $settings['token'] ?? '';
            $env = $settings['environment'] ?? 'sandbox'; // sandbox or production

            if (empty($token))
                throw new Exception("Token não configurado.");

            $url = ($env === 'production')
                ? "https://melhorenvio.com.br/api/v2/me"
                : "https://sandbox.melhorenvio.com.br/api/v2/me";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $token,
                "Accept: application/json"
            ]); // User-Agent is important for ME but trying basic first
            curl_setopt($ch, CURLOPT_USERAGENT, "FightArcade/1.0 (test@fightarcade.com)");

            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err)
                throw new Exception("Erro de Conexão Curl: $err");

            $json = json_decode($res, true);

            if ($httpCode === 200 && isset($json['id'])) {
                $response = [
                    'status' => 'success',
                    'message' => 'Conexão OK! Conta: ' . ($json['email'] ?? 'N/A') . " ($env)",
                    'data' => $json
                ];
            } else {
                $msg = $json['message'] ?? 'Token inválido.';
                throw new Exception("Falha na API ($httpCode): $msg");
            }
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Testar Integrações API | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .test-card {
            background: #111;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .test-card:hover {
            border-color: var(--primary);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            background: #444;
            color: #aaa;
            margin-left: 10px;
        }

        .status-success {
            background: #27ae60;
            color: white;
        }

        .status-error {
            background: #c0392b;
            color: white;
        }

        .status-loading {
            background: #f39c12;
            color: #000;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }

            100% {
                opacity: 1;
            }
        }

        pre {
            background: #000;
            padding: 1rem;
            border-radius: 6px;
            color: #4cc9f0;
            font-size: 0.8rem;
            overflow-x: auto;
            display: none;
            /* Hidden by default */
            margin-top: 1rem;
            border: 1px solid #333;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <h1>📡 Diagnóstico de Integrações</h1>
        <p style="color:#aaa; margin-bottom:2rem;">Teste a conectividade das suas APIs (Pagamentos e Fretes) em tempo
            real.</p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">

            <!-- MERCADO PAGO -->
            <div class="test-card">
                <div style="display:flex; align-items:center; margin-bottom:1rem;">
                    <img src="https://logospng.org/download/mercado-pago/logo-mercado-pago-icone-1024.png" width="40"
                        style="margin-right:10px;">
                    <h3 style="margin:0;">Mercado Pago</h3>
                    <span id="badge-mp" class="status-badge">Aguardando</span>
                </div>
                <p style="color:#888; font-size:0.9rem;">Verifica se o <strong>Access Token</strong> está válido e
                    consegue acessar os dados da conta.</p>

                <div style="margin-top:1rem;">
                    <button class="btn" onclick="runTest('mercadopago')" id="btn-mp">▶️ Testar Conexão</button>
                </div>
                <pre id="log-mp"></pre>
            </div>

            <!-- MELHOR ENVIO -->
            <div class="test-card">
                <div style="display:flex; align-items:center; margin-bottom:1rem;">
                    <img src="https://melhorenvio.com.br/images/logo-icon.png" width="40" style="margin-right:10px;">
                    <h3 style="margin:0;">Melhor Envio</h3>
                    <span id="badge-me" class="status-badge">Aguardando</span>
                </div>
                <p style="color:#888; font-size:0.9rem;">Verifica o Token e o ambiente (Sandbox/Produção).</p>

                <div style="margin-top:1rem;">
                    <button class="btn" onclick="runTest('melhorenvio')" id="btn-me">▶️ Testar Conexão</button>
                    <a href="../test-me.php" target="_blank" class="btn btn-secondary btn-sm"
                        style="margin-left:5px;">Log Completo</a>
                </div>
                <pre id="log-me"></pre>
            </div>

        </div>
    </div>

    <script>
        async function runTest(service) {
            const badge = document.getElementById(`badge-${service === 'mercadopago' ? 'mp' : 'me'}`); // simple map
            const pre = document.getElementById(`log-${service === 'mercadopago' ? 'mp' : 'me'}`);
            const btn = document.getElementById(`btn-${service === 'mercadopago' ? 'mp' : 'me'}`);

            // Reset UI
            badge.className = 'status-badge status-loading';
            badge.textContent = 'Testando...';
            pre.style.display = 'none';
            btn.disabled = true;

            try {
                const response = await fetch(`?action=test_${service}`);
                const data = await response.json();

                pre.style.display = 'block';
                pre.textContent = JSON.stringify(data, null, 2);

                if (data.status === 'success') {
                    badge.className = 'status-badge status-success';
                    badge.textContent = 'CONECTADO ✅';
                } else {
                    badge.className = 'status-badge status-error';
                    badge.textContent = 'ERRO ❌';
                }
            } catch (error) {
                badge.className = 'status-badge status-error';
                badge.textContent = 'FALHA REQ';
                pre.style.display = 'block';
                pre.textContent = "Erro na requisição JS: " + error;
            } finally {
                btn.disabled = false;
            }
        }
    </script>
</body>

</html>