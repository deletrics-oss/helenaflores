<?php
// admin/settings_modules.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$message = '';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Loop through posted settings
        // Expected format: settings[module_key] = { active: 1, keys: {...} }

        foreach ($_POST['modules'] as $key => $data) {
            $isActive = isset($data['active']) ? 1 : 0;
            $jsonSettings = isset($data['keys']) ? json_encode($data['keys']) : null;

            // Check if exists
            $stmt = $pdo->prepare("SELECT id FROM module_settings WHERE module_key = ?");
            $stmt->execute([$key]);

            if ($stmt->fetch()) {
                $upd = $pdo->prepare("UPDATE module_settings SET is_active = ?, settings_json = ?, updated_at = NOW() WHERE module_key = ?");
                $upd->execute([$isActive, $jsonSettings, $key]);
            } else {
                $ins = $pdo->prepare("INSERT INTO module_settings (module_key, is_active, settings_json) VALUES (?, ?, ?)");
                $ins->execute([$key, $isActive, $jsonSettings]);
            }
        }

        $pdo->commit();
        $message = '<div class="alert success">Configurações salvas com sucesso!</div>';

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert error">Erro ao salvar: ' . $e->getMessage() . '</div>';
    }
}

// Fetch Current Settings
$modules = $pdo->query("SELECT * FROM module_settings")->fetchAll(PDO::FETCH_ASSOC);
$modMap = [];
foreach ($modules as $m) {
    // Decode JSON safely
    $m['keys'] = json_decode($m['settings_json'], true) ?? [];
    $modMap[$m['module_key']] = $m;
}

// Helper to get value
function getVal($map, $mod, $key)
{
    return $map[$mod]['keys'][$key] ?? '';
}
function isChk($map, $mod)
{
    return (!empty($map[$mod]['is_active']) && $map[$mod]['is_active'] == 1) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Módulos / Integrações | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .module-card {
            background: #222;
            border: 1px solid #444;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #333;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--success);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        /* Fix Click Area */
        .switch input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 20;
            top: 0;
            left: 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #aaa;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            background: #111;
            border: 1px solid #444;
            color: #fff;
            border-radius: 4px;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .alert.success {
            background: rgba(37, 211, 102, 0.2);
            color: #25D366;
            border: 1px solid #25D366;
        }

        .alert.error {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <h1>🧩 Módulos & Integrações</h1>
            <a href="test-integrations.php" class="btn btn-secondary"
                style="border:1px solid #4cc9f0; color:#4cc9f0;">📡 Diagnóstico de API (Testar)</a>
        </div>

        <?php echo $message; ?>

        <form method="POST">

            <!-- PAGAMENTOS -->
            <h2 style="color:var(--primary); margin-top:2rem;">💰 Pagamentos</h2>

            <!-- PagSeguro -->
            <div class="module-card">
                <div class="module-header">
                    <h3>PagSeguro (Checkout / Link)</h3>
                    <label class="switch">
                        <input type="checkbox" name="modules[payment_pagseguro][active]" value="1" <?php echo isChk($modMap, 'payment_pagseguro'); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="module-body">
                    <div class="form-group">
                        <label>E-mail da Conta PagSeguro:</label>
                        <input type="email" name="modules[payment_pagseguro][keys][email]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'payment_pagseguro', 'email')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Token de Produção:</label>
                        <input type="password" name="modules[payment_pagseguro][keys][token]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'payment_pagseguro', 'token')); ?>">
                    </div>
                </div>
            </div>

            <!-- Mercado Pago -->
            <div class="module-card">
                <div class="module-header">
                    <h3>Mercado Pago (Checkout Transparente)</h3>
                    <label class="switch">
                        <input type="checkbox" name="modules[payment_mercadopago][active]" value="1" <?php echo isChk($modMap, 'payment_mercadopago'); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="module-body">
                    <div class="form-group">
                        <label>Public Key (Chave Pública):</label>
                        <input type="text" name="modules[payment_mercadopago][keys][public_key]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'payment_mercadopago', 'public_key')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Access Token (Token de Acesso):</label>
                        <input type="password" name="modules[payment_mercadopago][keys][access_token]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'payment_mercadopago', 'access_token')); ?>">
                    </div>
                </div>
            </div>

            <!-- NuPay -->
            <div class="module-card">
                <div class="module-header">
                    <h3>NuPay (Nubank Empresas)</h3>
                    <label class="switch">
                        <input type="checkbox" name="modules[payment_nupay][active]" value="1" <?php echo isChk($modMap, 'payment_nupay'); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="module-body">
                    <div class="form-group">
                        <label>Client ID / Merchant Key:</label>
                        <input type="text" name="modules[payment_nupay][keys][key]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'payment_nupay', 'key')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Client Secret / Token:</label>
                        <input type="password" name="modules[payment_nupay][keys][token]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'payment_nupay', 'token')); ?>">
                    </div>
                </div>
            </div>

            <!-- ENVIOS -->
            <h2 style="color:var(--primary); margin-top:3rem;">🚚 Frete e Logística</h2>

            <!-- Correios -->
            <div class="module-card">
                <div class="module-header">
                    <h3>Correios (PAC / SEDEX)</h3>
                    <label class="switch">
                        <input type="checkbox" name="modules[shipping_correios][active]" value="1" <?php echo isChk($modMap, 'shipping_correios'); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="module-body">
                    <p style="font-size:0.9rem; color:#888;">Utiliza calculadora padrão. Se tiver contrato,
                        preencha
                        abaixo.</p>
                    <div class="form-group">
                        <label>Código Administrativo (Opcional):</label>
                        <input type="text" name="modules[shipping_correios][keys][cod_admin]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'shipping_correios', 'cod_admin')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Senha API (Opcional):</label>
                        <input type="password" name="modules[shipping_correios][keys][senha_api]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'shipping_correios', 'senha_api')); ?>">
                    </div>
                </div>
            </div>

            <!-- Melhor Envio -->
            <div class="module-card">
                <div class="module-header">
                    <h3>Melhor Envio</h3>
                    <label class="switch">
                        <input type="checkbox" name="modules[shipping_melhorenvio][active]" value="1" <?php echo isChk($modMap, 'shipping_melhorenvio'); ?>>
                        <span class="slider"></span>
                    </label>
                    <a href="test-me.php" target="_blank"
                        style="margin-left:15px; font-size:0.8rem; color:#00e676; text-decoration:underline;">🔍
                        Testar
                        Diagnóstico</a>
                </div>
                <div class="module-body">
                    <div class="form-group">
                        <label>Token da API (Gerado no Painel Melhor Envio):</label>
                        <input type="password" name="modules[shipping_melhorenvio][keys][token]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'shipping_melhorenvio', 'token')); ?>">
                    </div>
                    <div class="form-group">
                        <label>CEP de Origem (Onde estão seus produtos):</label>
                        <input type="text" name="modules[shipping_melhorenvio][keys][zip_origin]"
                            placeholder="Ex: 03313000"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'shipping_melhorenvio', 'zip_origin')); ?>">
                    </div>
                </div>
            </div>

            <!-- Motoboy -->
            <div class="module-card">
                <div class="module-header">
                    <h3>Entrega Local (Motoboy)</h3>
                    <label class="switch">
                        <input type="checkbox" name="modules[shipping_motoboy][active]" value="1" <?php echo isChk($modMap, 'shipping_motoboy'); ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="module-body">
                    <p style="color:#aaa; font-size:0.9rem; margin-bottom:1rem;">
                        Configure faixas de CEP para entrega própria/motoboy.
                        <br><strong>Dica:</strong> Use os 5 primeiros dígitos do CEP. Ex: De 03000 a 03999.
                    </p>

                    <?php
                    // Helper to get zone values
                    $zones = getVal($modMap, 'shipping_motoboy', 'zones') ?? [];

                    // Defaults for a Fresh Install (User Origin: 03611-060 Penha/Tatuapé)
                    if (empty($zones) || empty($zones[0]['name'])) {
                        $zones = [
                            0 => ['name' => 'Zona 1: Vizinhança (Penha/Tatuapé/Carrão)', 'zip_start' => '03000', 'zip_end' => '03799', 'price' => '14.90'],
                            1 => ['name' => 'Zona 2: Mooca / V. Prudente / Aricanduva', 'zip_start' => '03800', 'zip_end' => '03999', 'price' => '19.90'],
                            2 => ['name' => 'Zona 3: Centro de SP', 'zip_start' => '01000', 'zip_end' => '01599', 'price' => '32.50'],
                            3 => ['name' => 'Zona 4: Zona Leste Distante (Itaquera)', 'zip_start' => '08000', 'zip_end' => '08499', 'price' => '39.90'],
                            4 => ['name' => 'Zona 5: Paulista / Jardins', 'zip_start' => '01300', 'zip_end' => '01499', 'price' => '35.00'],
                        ];
                    }
                    ?>

                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div
                            style="background:#1a1a1a; padding:1rem; border-radius:6px; margin-bottom:1rem; border:1px solid #333;">
                            <strong style="color:var(--primary);">Zona <?php echo $i + 1; ?></strong>
                            <div
                                style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:0.5rem; margin-top:0.5rem;">
                                <div>
                                    <label style="font-size:0.8rem;">Nome (Ex: Zona Leste)</label>
                                    <input type="text"
                                        name="modules[shipping_motoboy][keys][zones][<?php echo $i; ?>][name]"
                                        value="<?php echo htmlspecialchars($zones[$i]['name'] ?? ''); ?>"
                                        placeholder="Nome da Região">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;">CEP Início</label>
                                    <input type="text"
                                        name="modules[shipping_motoboy][keys][zones][<?php echo $i; ?>][zip_start]"
                                        value="<?php echo htmlspecialchars($zones[$i]['zip_start'] ?? ''); ?>"
                                        placeholder="00000" maxlength="5">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;">CEP Fim</label>
                                    <input type="text"
                                        name="modules[shipping_motoboy][keys][zones][<?php echo $i; ?>][zip_end]"
                                        value="<?php echo htmlspecialchars($zones[$i]['zip_end'] ?? ''); ?>"
                                        placeholder="99999" maxlength="5">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;">Preço (R$)</label>
                                    <input type="text"
                                        name="modules[shipping_motoboy][keys][zones][<?php echo $i; ?>][price]"
                                        value="<?php echo htmlspecialchars($zones[$i]['price'] ?? ''); ?>"
                                        placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Bling/Tiny -->
            <h2 style="color:var(--primary); margin-top:3rem;">🏭 ERP (Notas Fiscais)</h2>
            <div class="module-card">
                <div class="module-header">
                    <h3>Bling / Tiny (Integração de Pedidos)</h3>
                    <!-- Sempre ativo se tiver chave -->
                </div>
                <div class="module-body">
                    <div class="form-group">
                        <label>Webhook URL (Para configurar no seu ERP):</label>
                        <input type="text" value="<?php echo BASE_URL; ?>/api/webhook_erp.php" readonly
                            style="background:#000; color:#aaa; cursor:copy;">
                    </div>
                    <div class="form-group">
                        <label>API Key (Bling ou Tiny):</label>
                        <input type="password" name="modules[erp_integration][keys][api_key]"
                            value="<?php echo htmlspecialchars(getVal($modMap, 'erp_integration', 'api_key')); ?>">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success"
                style="padding:1rem 2rem; font-size:1.2rem; width:100%; margin-bottom:4rem;">💾 SALVAR
                CONFIGURAÇÕES</button>

        </form>
    </div>

</body>

</html>