<?php
/**
 * admin/uber_settings.php — Fight Arcade
 * Painel de Configuração Uber (Versão Blindada Industrial)
 *
 * CORREÇÕES APLICADAS:
 *  - session_start() no topo absoluto (fix de headers)
 *  - isAdmin() para barreira de segurança real
 *  - Validação de credenciais antes de autorização
 *  - UI Premium com feedback de erro/sucesso
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php'; // Para isAdmin()
require_once __DIR__ . '/../includes/uber_api.php';

// Segurança: Apenas admins reais entram aqui
isAdmin();

$uber = new UberService($pdo);
$msg = $_SESSION['flash_msg'] ?? "";
$err = $_SESSION['error_msg'] ?? "";
unset($_SESSION['flash_msg'], $_SESSION['error_msg']);

// Salvar Configurações
if (isset($_POST['save_uber'])) {
    foreach ($_POST['cfg'] as $key => $val) {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, trim($val), trim($val)]);
    }
    // Recarrega o serviço com as novas chaves
    $uber = new UberService($pdo);
    $_SESSION['flash_msg'] = "✅ Configurações Salvas com Sucesso!";
    header("Location: uber_settings.php");
    exit;
}

$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'uber_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$isConnected = $uber->isConnected();

// Prepara o link de autorização com State para CSRF
$authUrl = null;
$authError = null;
try {
    if ($uber->hasCredentials()) {
        $state = bin2hex(random_bytes(16));
        $_SESSION['uber_oauth_state'] = $state;
        $authUrl = $uber->getAuthUrl($state);
    }
} catch (Exception $e) {
    $authError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uber Delivery | Fight Arcade Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .uber-wrapper { max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .uber-card { background: #0a0a0a; border: 1px solid #2a2a2a; border-radius: 16px; padding: 36px; box-shadow: 0 8px 40px rgba(0,0,0,0.6); }
        .uber-logo { height: 32px; margin-bottom: 24px; filter: invert(1); }
        .status-badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; }
        .badge-ok { background: #00ff88; color: #000; }
        .badge-off { background: #ff4444; color: #fff; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: #aaa; text-transform: uppercase; margin-bottom: 6px; }
        .form-group input { width: 100%; box-sizing: border-box; background: #111; border: 1px solid #333; border-radius: 8px; color: #fff; padding: 10px 14px; font-size: 0.9rem; }
        .form-group input:focus { outline: none; border-color: #00ff88; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem; }
        .alert-success { background: #1a2e1a; border: 1px solid #2d6a2d; color: #7fff7f; }
        .alert-error { background: #2e1a1a; border: 1px solid #6a2d2d; color: #ff9a9a; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="uber-wrapper">
    <div class="uber-card">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Uber_logo_2018.svg/2560px-Uber_logo_2018.svg.png" class="uber-logo">
        <h2>Uber Delivery / Eats</h2>
        <p style="color:#666; font-size:0.88rem; margin-bottom:28px;">Gerencie suas entregas e pedidos Uber diretamente no painel Fight Arcade.</p>

        <?php if($msg): ?> <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div> <?php endif; ?>
        <?php if($err): ?> <div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div> <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Client ID</label>
                <input type="text" name="cfg[uber_client_id]" value="<?php echo htmlspecialchars($settings['uber_client_id'] ?? ''); ?>" required placeholder="Cole o seu Client ID da Uber">
            </div>
            <div class="form-group">
                <label>Client Secret</label>
                <input type="password" name="cfg[uber_client_secret]" value="<?php echo htmlspecialchars($settings['uber_client_secret'] ?? ''); ?>" required placeholder="Cole o seu Client Secret da Uber">
            </div>
            <div class="form-group">
                <label>URL de Callback (Redirecionamento)</label>
                <input type="text" value="https://fightarcade.com.br/catalogo/admin/uber_callback.php" readonly style="color:#555; background:#111;">
                <small style="color:#555; display:block; margin-top:5px;">Esta URL deve ser cadastrada no seu App no Dashboard da Uber.</small>
            </div>
            
            <div class="form-group">
                <label>Webhook Signing Key</label>
                <input type="text" name="cfg[uber_webhook_signing_key]" value="<?php echo htmlspecialchars($settings['uber_webhook_signing_key'] ?? ''); ?>" placeholder="Cole o seu Signing Key (HMAC) da Uber">
                <small style="color:#555; display:block; margin-top:5px;">Necessário para validar eventos de status de entrega.</small>
            </div>
            
            <button type="submit" name="save_uber" class="btn" style="width:100%; background:#fff; color:#000; font-weight:700; padding:12px; border-radius:8px; border:none; cursor:pointer;">Salvar Configurações</button>
        </form>

        <div style="margin-top:30px; padding-top:20px; border-top:1px solid #222; text-align:center;">
            <?php if ($isConnected): ?>
                <span class="badge badge-ok">✓ SISTEMA CONECTADO COM UBER</span>
                <p style="margin-top:15px;"><a href="<?php echo $authUrl; ?>" style="color:#00ff88; font-size:0.85rem; text-decoration:none; font-weight:bold;">🔄 Reautorizar Conexão</a></p>
            <?php else: ?>
                <span class="badge badge-off">⚠️ AGUARDANDO CONEXÃO</span>
                <?php if ($authError): ?>
                    <p style="color:#ff4444; font-size:0.8rem; margin-top:10px;">⚠️ <?php echo htmlspecialchars($authError); ?></p>
                <?php elseif ($authUrl): ?>
                    <p style="margin-top:20px;">
                        <a href="<?php echo $authUrl; ?>" class="btn" style="background:#00ff88; color:#000; padding:14px 32px; border-radius:10px; font-weight:700; text-decoration:none; display:inline-block; transition:0.2s;">🔌 Conectar Conta Uber</a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
