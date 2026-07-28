<?php
/**
 * admin/lalamove.php
 */
try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/user_auth.php';
    require_once __DIR__ . '/../includes/lalamove.php';
    require_once __DIR__ . '/../includes/notifications.php';
    isAdmin();

    $llm = new LalamoveAPI($pdo);
    $notif = new NotificationService($pdo);
$msg = '';

// Carregar config
$cfg = [];
$cfgKeys = ['llm_api_key','llm_api_secret','llm_sandbox','llm_market','llm_store_name','llm_store_phone','llm_store_address','llm_store_lat','llm_store_lng'];
foreach ($cfgKeys as $k) {
    try { $cfg[$k] = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='$k'")->fetchColumn() ?: ''; }
    catch(Exception $e) { $cfg[$k] = ''; }
}

// ======================================================
// POST: Salvar configurações
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $fields = [
        'llm_api_key', 'llm_api_secret', 'llm_sandbox', 'llm_market',
        'llm_store_name', 'llm_store_phone', 'llm_store_address',
        'llm_store_lat', 'llm_store_lng'
    ];
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
            ->execute([$f, $val, $val]);
    }
    $msg = '<div class="alert alert-success">✅ Configurações salvas!</div>';
    $llm = new LalamoveAPI($pdo);
}

// ======================================================
// AJAX: Geocodificar endereço por CEP
// ======================================================
if (isset($_GET['ajax_geocode'])) {
    header('Content-Type: application/json');
    $cep    = preg_replace('/\D/', '', $_GET['cep']    ?? '');
    $number = $_GET['number'] ?? '';
    $address = $_GET['address'] ?? '';

    // Se vier order_id, buscar CEP/endereço do pedido
    if (empty($cep) && !empty($_GET['order_id'])) {
        $oid = (int)$_GET['order_id'];
        $orderUser = $pdo->prepare("SELECT u.name, u.phone, u.zipcode, u.address, u.number, u.city, u.state, u.neighborhood, u.complement 
                                     FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $orderUser->execute([$oid]);
        $ou = $orderUser->fetch();
        if ($ou) {
            $cep = preg_replace('/\D/', '', $ou['zipcode'] ?? '');
            $number = $ou['number'] ?? '';
            // Store extra data to return
            $extraData = [
                'recipient_name'  => $ou['name'],
                'recipient_phone' => $ou['phone'],
                'zipcode'         => $ou['zipcode'],
                'address'         => $ou['address'],
                'number'          => $ou['number'],
                'complement'      => $ou['complement'],
                'neighborhood'    => $ou['neighborhood'],
                'city'            => $ou['city'],
                'state'           => $ou['state']
            ];
        } else {
            echo json_encode(['error' => 'Pedido #' . $oid . ' não encontrado.']);
            exit;
        }
    }

    // Modo 1: Geocodificar por CEP
    if (!empty($cep)) {
        $coords = $llm->geocodeByCep($cep, $number);
        if ($coords && isset($extraData)) {
            $coords = array_merge($coords, $extraData);
        }
        echo json_encode($coords ?: ['error' => 'Endereço não encontrado para o CEP ' . $cep]);
        exit;
    }

    // Modo 2: Geocodificar por endereço completo (botão "Geocodificar Endereço da Loja")
    if (!empty($address)) {
        $coords = $llm->geocodeAddress($address);
        if ($coords) {
            $coords['formatted_address'] = $address;
            echo json_encode($coords);
        } else {
            echo json_encode(['error' => "Não foi possível encontrar as coordenadas para: $address.\nTente remover o número ou detalhes do endereço. Exemplo: 'Rua Cristiano Osorio, Vila Esperança, São Paulo, SP'."]);
        }
        exit;
    }

    echo json_encode(['error' => 'Informe um CEP ou endereço para geocodificar.']);
    exit;
}

// AJAX: Get Purchase Info for Lalamove
if (isset($_GET['ajax_get_purchase'])) {
    header('Content-Type: application/json');
    $pid = (int)$_GET['ajax_get_purchase'];
    $stmt = $pdo->prepare("SELECT s.* FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?");
    $stmt->execute([$pid]);
    $s = $stmt->fetch();
    if ($s) {
        echo json_encode($s);
    } else {
        echo json_encode(['error' => 'Compra não encontrada']);
    }
    exit;
}

// ======================================================
// AJAX: Debug — Testar conexão e config
// ======================================================
if (isset($_GET['ajax_debug'])) {
    header('Content-Type: application/json');
    
    $debugInfo = [
        'market'      => $llm->getMarket(),
        'sandbox'     => $llm->isSandbox(),
        'configured'  => $llm->isConfigured(),
        'store_lat'   => $llm->getStoreLatLng()['lat'],
        'store_lng'   => $llm->getStoreLatLng()['lng'],
    ];
    
    // Test single quote to MOTORCYCLE
    $testCoords = ['lat' => '-23.550520', 'lng' => '-46.633308']; // Praça da Sé, SP
    $testResult = $llm->getQuotation($testCoords, 'Praça da Sé, São Paulo, SP', 'MOTORCYCLE');
    
    $debugInfo['test_quote_result'] = $testResult;
    
    // Read last lines from error log
    $logFile = __DIR__ . '/../lalamove_errors.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $debugInfo['last_errors'] = array_slice($lines, -5);
    }
    
    echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ======================================================
// AJAX: Cotar em todos os veículos
// ======================================================
if (isset($_GET['ajax_quote'])) {
    header('Content-Type: application/json');

    $lat     = $_GET['lat']     ?? '';
    $lng     = $_GET['lng']     ?? '';
    $address = $_GET['address'] ?? '';

    if (!$lat || !$lng) {
        echo json_encode(['error' => 'Coordenadas não informadas. Geocodifique o endereço primeiro.']);
        exit;
    }

    $specialRequests = [];
    // COD is not a valid specialRequest for quotations in BR v3.
    // It is only used during order creation as part of the remarks/payment logic.

    // Handle Delivery Type (Priority / Grouped)
    // We already removed S_POOL and PRIORITY to prevent crashes.
    // Future: map to LALAGO_POOL if confirmed.

    // Handle Reverse Logistics (Supplier to Store)
    $fromCoords = null;
    $fromAddress = null;
    if (isset($_GET['is_reverse']) && $_GET['is_reverse'] === '1') {
        $fromCoords = ['lat' => $_GET['orig_lat'], 'lng' => $_GET['orig_lng']];
        $fromAddress = $_GET['orig_address'];
    }

    $isGrouped = isset($_GET['grouped']) && $_GET['grouped'] === '1';
    $quotes = $llm->getAllQuotations(['lat' => $lat, 'lng' => $lng], $address, $specialRequests, $fromCoords, $fromAddress, $isGrouped);

    // Ordena: sem erro primeiro, por preço
    usort($quotes, function($a, $b) {
        if (isset($a['error']) && !isset($b['error'])) return 1;
        if (!isset($a['error']) && isset($b['error'])) return -1;
        return ($a['total'] ?? 9999) <=> ($b['total'] ?? 9999);
    });

    echo json_encode([
        'quotes'   => $quotes,
        'sandbox'  => $llm->isSandbox(),
        'storeLat' => $llm->getStoreLatLng()['lat'],
        'storeLng' => $llm->getStoreLatLng()['lng'],
    ]);
    exit;
}


// ======================================================
// AJAX: Criar pedido Lalamove
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_create_order'])) {
    header('Content-Type: application/json');

    $quotationId   = $_POST['quotation_id']    ?? '';
    $stopsJson     = $_POST['stops_json']      ?? '[]';
    $recipientName = $_POST['recipient_name']  ?? '';
    $recipientPhone= $_POST['recipient_phone'] ?? '';
    $remarks       = $_POST['remarks']         ?? '';
    $notifySms     = ($_POST['notify_sms'] ?? '1') === '1';
    $paymentMethod = $_POST['payment_method']  ?? 'WALLET';
    $totalValue    = (float)($_POST['total_value'] ?? 0);

    if (empty($quotationId)) {
        echo json_encode(['success' => false, 'error' => 'Selecione uma opção de frete primeiro.']);
        exit;
    }

    $stops  = json_decode($stopsJson, true) ?: [];
    
    // Handle Reverse Logistics Sender Name/Phone
    $senderName = null;
    $senderPhone = null;
    if (isset($_POST['is_reverse']) && $_POST['is_reverse'] === '1') {
        $senderName = $_POST['orig_name'] ?? null;
        $senderPhone = $_POST['orig_phone'] ?? null;
    }

    if (empty($recipientPhone) && !empty($_POST['local_order_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT phone FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ?");
            $stmt->execute([(int)$_POST['local_order_id']]);
            $phone = $stmt->fetchColumn();
            if ($phone) $recipientPhone = $phone;
        } catch(Exception $e) {}
    }

    $result = $llm->createOrder($quotationId, $stops, $recipientName, $recipientPhone, $remarks, $notifySms, $paymentMethod, $totalValue, $senderName, $senderPhone);

    if (isset($result['data']['orderId'])) {
        $orderId = $result['data']['orderId'];
        $status  = $result['data']['status'] ?? 'ASSIGNING_DRIVER';

        // Se for Prioridade, adicionar taxa de prioridade
        $priorityFee = $_POST['priority_fee'] ?? '';
        if (!empty($priorityFee) && floatval($priorityFee) > 0) {
            try { $llm->addPriorityFee($orderId, $priorityFee); } catch (Exception $e) {}
        }

        // Notificar Cliente via WhatsApp/SMS
        if (method_exists($notif, 'lalamoveOnTheWay')) {
            try { $notif->lalamoveOnTheWay($recipientPhone, $recipientName); } catch (Exception $e) {}
        }

        // Salvar no DB se tiver order_id local
        if (!empty($_POST['local_order_id'])) {
            try {
                $pdo->prepare("UPDATE orders SET me_order_id=?, shipping_method=? WHERE id=?")
                    ->execute([$orderId, 'Lalamove', (int)$_POST['local_order_id']]);
            } catch(Exception $e) {}
        }
        if (!empty($_POST['rma_id'])) {
            try {
                $pdo->prepare("UPDATE rma_tickets SET me_order_id=?, status='shipped' WHERE id=?")
                    ->execute([$orderId, (int)$_POST['rma_id']]);
            } catch(Exception $e) {}
        }

        // Buscar shareLink se disponível
        $shareLink = $result['data']['shareLink'] ?? '';

        echo json_encode([
            'success'   => true,
            'orderId'   => $orderId,
            'status'    => $status,
            'shareLink' => $shareLink,
        ]);
    } else {
        $errorMessage = $result['errors'][0]['message'] ?? json_encode($result);
        if (strpos(strtolower($errorMessage), 'sufficient credit') !== false) {
            $errorMessage = 'Saldo insuficiente na carteira Lalamove! Recarregue pelo painel oficial da Lalamove.';
        }
        
        echo json_encode([
            'success' => false,
            'error'   => $errorMessage,
        ]);
    }
    exit;
}

// ======================================================
// AJAX: Verificar status do pedido + dados do motorista
// ======================================================
if (isset($_GET['ajax_status']) && isset($_GET['order_id'])) {
    header('Content-Type: application/json');
    $result = $llm->getOrder($_GET['order_id']);
    $data = $result['data'] ?? [];
    
    $driverName = '';
    $driverPhone = '';
    $driverPlate = '';
    $driverPhoto = '';
    $shareLink = $data['shareLink'] ?? '';
    
    // Buscar dados do motorista via endpoint dedicado se tiver driverId
    $driverId = $data['driverId'] ?? '';
    if (!empty($driverId)) {
        $driverResult = $llm->getDriverDetails($_GET['order_id'], $driverId);
        $driverData = $driverResult['data'] ?? [];
        $driverName  = $driverData['name']        ?? '';
        $driverPhone = $driverData['phone']       ?? '';
        $driverPlate = $driverData['plateNumber'] ?? '';
        $driverPhoto = $driverData['photo']       ?? '';
    }
    
    echo json_encode([
        'status'       => $data['status'] ?? 'UNKNOWN',
        'driverId'     => $driverId,
        'driverName'   => $driverName,
        'driverPhone'  => $driverPhone,
        'driverPlate'  => $driverPlate,
        'driverPhoto'  => $driverPhoto,
        'shareLink'    => $shareLink,
    ]);
    exit;
}

// ======================================================
// AJAX: Enviar dados do motoboy para o cliente via WhatsApp
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_notify_driver'])) {
    header('Content-Type: application/json');
    $phone = $_POST['customer_phone'] ?? '';
    $name  = $_POST['customer_name']  ?? '';
    $dName = $_POST['driver_name']    ?? '';
    $plate = $_POST['driver_plate']   ?? '';
    $dPhone= $_POST['driver_phone']   ?? '';
    $link  = $_POST['share_link']     ?? '';
    
    $sent = false;
    if (method_exists($notif, 'lalamoveOnTheWay')) {
        try {
            $sent = $notif->lalamoveOnTheWay($phone, $name, $dName, $plate, $dPhone, $link);
        } catch (Exception $e) {}
    }
    echo json_encode(['success' => $sent]);
    exit;
}

// ======================================================
// AJAX: Cancelar pedido
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cancel'])) {
    header('Content-Type: application/json');
    $result = $llm->cancelOrder($_POST['order_id'] ?? '');
    $ok = ($result === null || (isset($result['data']) && empty($result['errors'])));
    echo json_encode(['success' => $ok, 'raw' => $result]);
    exit;
}

// Carregar config
$cfg = [];
$cfgKeys = ['llm_api_key','llm_api_secret','llm_sandbox','llm_market','llm_store_name','llm_store_phone','llm_store_address','llm_store_lat','llm_store_lng'];
foreach ($cfgKeys as $k) {
    try { $cfg[$k] = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='$k'")->fetchColumn() ?: ''; }
    catch(Exception $e) { $cfg[$k] = ''; }
}

// Pedidos recentes para mostrar na tabela
try {
    $recentOrders = $pdo->query("SELECT o.id, o.total_amount, o.status, o.me_order_id, o.shipping_method,
        u.name as customer_name, u.zipcode, u.city, u.state, u.address, u.number, u.neighborhood, u.phone
        FROM orders o JOIN users u ON o.user_id = u.id
        WHERE o.shipping_method = 'Lalamove' OR o.shipping_method IS NULL
        ORDER BY o.created_at DESC LIMIT 20")->fetchAll();
} catch(Exception $e) { $recentOrders = []; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lalamove | Fight Arcade Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #0b0e14; --surface: #141820; --surface2: #1a1e2a;
            --border: #252d3d; --primary: #f1c40f; --text: #e8eaf0;
            --muted: #5a6478; --radius: 12px;
            --red: #e74c3c; --green: #2ecc71; --blue: #3498db; --orange: #e67e22;
            --llm-orange: #FF6600; --llm-orange-dark: #cc5200;
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* Cards */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card h3 { margin: 0 0 1.2rem; font-size: 1rem; display: flex; align-items: center; gap: 9px; }

        /* Lalamove brand */
        .llm-brand { color: var(--llm-orange); }
        .llm-badge { background: var(--llm-orange); color: #fff; padding: 2px 10px; border-radius: 20px; font-size: .7rem; font-weight: 800; }

        /* Alert */
        .alert { padding: 11px 15px; border-radius: 8px; margin-bottom: 1rem; font-size: .88rem; }
        .alert-success { background: rgba(46,204,113,.08); border: 1px solid rgba(46,204,113,.3); color: var(--green); }
        .alert-warning { background: rgba(243,156,18,.08); border: 1px solid rgba(243,156,18,.3); color: #f39c12; }
        .alert-error   { background: rgba(231,76,60,.08);  border: 1px solid rgba(231,76,60,.3);  color: var(--red); }
        .alert-info    { background: rgba(52,152,219,.08); border: 1px solid rgba(52,152,219,.3); color: var(--blue); }

        /* Form */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .form-group { margin-bottom: .75rem; }
        .form-group label { display: block; font-size: .72rem; color: var(--muted); text-transform: uppercase; margin-bottom: 5px; font-weight: 700; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 9px 11px; background: #0d1017;
            border: 1px solid var(--border); color: var(--text);
            border-radius: 8px; outline: none; font-size: .88rem; transition: border-color .15s;
        }
        .form-group input:focus, .form-group select:focus { border-color: var(--llm-orange); }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 8px; font-size: .85rem; font-weight: 700; cursor: pointer; border: none; text-decoration: none; transition: .15s; }
        .btn-llm      { background: var(--llm-orange); color: #fff; }
        .btn-llm:hover{ background: var(--llm-orange-dark); }
        .btn-secondary{ background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { border-color: var(--llm-orange); }
        .btn-sm       { padding: 5px 10px; font-size: .75rem; border-radius: 6px; }
        .btn-green    { background: var(--green); color: #000; }
        .btn-red      { background: var(--red);   color: #fff; }
        .btn-blue     { background: var(--blue);  color: #fff; }

        /* Quote cards */
        .quote-card { display: flex; align-items: center; gap: 14px; padding: 13px 15px; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; cursor: pointer; transition: border-color .15s, background .15s; }
        .quote-card:hover { border-color: var(--llm-orange); background: rgba(255,102,0,.03); }
        .quote-card.selected { border-color: var(--green); background: rgba(46,204,113,.05); }
        .quote-card.cheapest { border-color: rgba(46,204,113,.4); }
        .quote-card.unavailable { opacity: .5; cursor: not-allowed; }
        .quote-icon { font-size: 1.5rem; width: 40px; text-align: center; flex-shrink: 0; }
        .quote-info { flex: 1; }
        .quote-name { font-weight: 700; font-size: .9rem; }
        .quote-meta { font-size: .75rem; color: var(--muted); margin-top: 2px; }
        .quote-error-text { font-size: .72rem; color: var(--red); margin-top: 2px; }
        .quote-price-col { text-align: right; flex-shrink: 0; }
        .quote-price { font-weight: 800; font-size: 1.05rem; color: var(--green); }
        .cheapest-tag { background: rgba(46,204,113,.15); color: var(--green); font-size: .6rem; padding: 1px 7px; border-radius: 10px; font-weight: 800; display: inline-block; margin-top: 2px; }
        .price-unavail { color: var(--red); font-size: .8rem; }

        /* Status badges */
        .status-badge { padding: 3px 9px; border-radius: 20px; font-size: .68rem; font-weight: 800; text-transform: uppercase; }
        .s-assigning  { background: rgba(243,156,18,.1); color: #f39c12; }
        .s-ongoing    { background: rgba(52,152,219,.1); color: var(--blue); }
        .s-pickedup   { background: rgba(155,89,182,.1); color: #9b59b6; }
        .s-completed  { background: rgba(46,204,113,.1); color: var(--green); }
        .s-cancelled  { background: rgba(231,76,60,.1);  color: var(--red); }
        .s-other      { background: rgba(255,255,255,.06); color: var(--muted); }

        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th { text-align: left; padding: 10px 12px; font-size: .68rem; color: var(--muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 11px 12px; border-bottom: 1px solid #1a1e26; vertical-align: middle; }
        tr:hover td { background: rgba(255,255,255,.015); }

        /* Sandbox notice */
        .sandbox-notice { background: rgba(243,156,18,.07); border: 1px solid rgba(243,156,18,.25); color: #f39c12; padding: 10px 14px; border-radius: 8px; font-size: .82rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 9px; }

        /* Stepper */
        .steps { display: flex; gap: 0; margin-bottom: 1.5rem; }
        .step { flex: 1; display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--surface2); border: 1px solid var(--border); font-size: .8rem; color: var(--muted); }
        .step:first-child { border-radius: 8px 0 0 8px; }
        .step:last-child  { border-radius: 0 8px 8px 0; }
        .step.active { background: rgba(255,102,0,.08); border-color: var(--llm-orange); color: var(--text); }
        .step.done   { background: rgba(46,204,113,.06); border-color: rgba(46,204,113,.3); color: var(--green); }
        .step-num { width: 22px; height: 22px; border-radius: 50%; background: var(--border); display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 800; flex-shrink: 0; }
        .step.active .step-num { background: var(--llm-orange); color: #fff; }
        .step.done   .step-num { background: var(--green); color: #000; }

        /* Loading */
        .loading { display: flex; align-items: center; gap: 10px; padding: 1rem; color: var(--muted); font-size: .85rem; }

        /* Map iframe area */
        .coord-display { background: var(--surface2); border: 1px solid var(--border); padding: 8px 12px; border-radius: 8px; font-size: .78rem; color: var(--muted); margin-top: .5rem; font-family: monospace; }

        @media(max-width:768px) { .form-grid { grid-template-columns: 1fr; } .steps { flex-direction: column; } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">

        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0;font-size:1.5rem;display:flex;align-items:center;gap:10px">
                    <svg width="28" height="28" viewBox="0 0 100 100" style="flex-shrink:0">
                        <circle cx="50" cy="50" r="50" fill="#FF6600"/>
                        <text x="50" y="67" text-anchor="middle" font-size="52" font-weight="900" fill="#fff" font-family="Arial">L</text>
                    </svg>
                    Lalamove
                    <span class="llm-badge"><?php echo $llm->isSandbox() ? 'SANDBOX' : 'PRODUÇÃO'; ?></span>
                </h1>
                <p style="color:var(--muted);margin:.3rem 0 0;font-size:.88rem">Entrega expressa no mesmo dia — São Paulo e Rio de Janeiro</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="melhorenvio.php" class="btn btn-secondary btn-sm"><i class="fas fa-truck"></i> Melhor Envio</a>
                <a href="dashboard.php"  class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <?php echo $msg; ?>

        <?php if ($llm->isSandbox()): ?>
        <div class="sandbox-notice">
            <i class="fas fa-flask"></i>
            <span><strong>Modo Sandbox:</strong> Cotações e pedidos são simulados. Mude para Produção nas configurações abaixo quando estiver pronto.</span>
        </div>
        <?php endif; ?>

        <!-- INFO CARD -->
        <div class="alert alert-info" style="margin-bottom:1.5rem">
            <i class="fas fa-info-circle"></i>
            <strong>Quando usar Lalamove vs Melhor Envio?</strong><br>
            <small>
                <strong>Lalamove:</strong> Entrega expressa no <em>mesmo dia</em>, dentro de SP ou RJ. Ideal para clientes da cidade.
                <strong>Melhor Envio:</strong> Fretes para qualquer cidade do Brasil (Correios, Loggi, J&T, Jadlog...).
            </small>
        </div>

        <!-- CONFIGURAÇÃO -->
        <div class="card">
            <h3><i class="fas fa-cog llm-brand"></i> Configuração da API</h3>
            <div class="alert alert-warning" style="margin-bottom:1rem">
                <i class="fas fa-key"></i>
                Para obter a API Key e Secret, acesse le <a href="https://partners.lalamove.com" target="_blank" style="color:inherit;font-weight:bold">Portal de Parceiros Lalamove</a> → Developers.
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="text" name="llm_api_key" value="<?php echo htmlspecialchars($cfg['llm_api_key']); ?>" placeholder="Sua API Key do portal">
                    </div>
                    <div class="form-group">
                        <label>API Secret</label>
                        <input type="password" name="llm_api_secret" value="<?php echo htmlspecialchars($cfg['llm_api_secret']); ?>" placeholder="Seu API Secret">
                    </div>
                    <div class="form-group">
                        <label>Ambiente</label>
                        <select name="llm_sandbox">
                            <option value="0" <?php echo ($cfg['llm_sandbox'] ?? '1') === '0' ? 'selected':''; ?>>🟢 Produção (Entregas Reais)</option>
                            <option value="1" <?php echo ($cfg['llm_sandbox'] ?? '1') === '1' ? 'selected':''; ?>>🟡 Sandbox (Testes)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Região (Market) <small style="color:var(--muted)">Obrigatório</small></label>
                        <select name="llm_market">
                            <option value="BR_SAO" <?php echo ($cfg['llm_market'] ?? 'BR_SAO') === 'BR_SAO' ? 'selected':''; ?>>🏙️ São Paulo (BR_SAO)</option>
                            <option value="BR_RIO" <?php echo ($cfg['llm_market'] ?? '') === 'BR_RIO' ? 'selected':''; ?>>🏖️ Rio de Janeiro (BR_RIO)</option>
                        </select>
                        <small style="color:var(--muted);display:block;margin-top:4px">Define o mercado Lalamove. Escolha a cidade onde sua loja está localizada.</small>
                    </div>
                </div>
                <hr style="border-color:var(--border);margin:1rem 0">
                <p style="font-size:.8rem;color:var(--muted);margin:0 0 .75rem">Dados do Remetente (sua loja)</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nome do Remetente</label>
                        <input type="text" name="llm_store_name" value="<?php echo htmlspecialchars($cfg['llm_store_name'] ?: 'Daniel Souza'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone (+55...)</label>
                        <input type="text" name="llm_store_phone" value="<?php echo htmlspecialchars($cfg['llm_store_phone'] ?: '+5511999999999'); ?>" placeholder="+5511999999999">
                    </div>
                </div>
                <div class="form-group">
                    <label>Endereço Completo da Loja</label>
                    <input type="text" name="llm_store_address" value="<?php echo htmlspecialchars($cfg['llm_store_address'] ?: 'Rua Cristiano Osorio, 143, Vila Esperança, São Paulo, SP'); ?>">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Latitude da Loja <small>(negativa para SP)</small></label>
                        <input type="text" name="llm_store_lat" id="storeLat" value="<?php echo htmlspecialchars($cfg['llm_store_lat'] ?: '-23.543598'); ?>" placeholder="-23.543598">
                    </div>
                    <div class="form-group">
                        <label>Longitude da Loja</label>
                        <input type="text" name="llm_store_lng" id="storeLng" value="<?php echo htmlspecialchars($cfg['llm_store_lng'] ?: '-46.574902'); ?>" placeholder="-46.574902">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="button" onclick="geocodeStore()" class="btn btn-secondary" style="width:100%">
                            <i class="fas fa-map-marker-alt"></i> Geocodificar Endereço da Loja
                        </button>
                    </div>
                </div>
                <div id="storeCoordInfo" style="display:none" class="coord-display"></div>
                <div style="margin-top:1.2rem">
                    <button type="submit" name="save_config" class="btn btn-llm"><i class="fas fa-save"></i> Salvar Configurações</button>
                </div>
            </form>
        </div>

        <!-- NOVA ENTREGA -->
        <?php if ($llm->isConfigured()): ?>
        <div class="card">
            <h3><i class="fas fa-motorcycle llm-brand"></i> Nova Entrega Lalamove</h3>

            <!-- Stepper visual -->
            <div class="steps">
                <div class="step active" id="step1-indicator">
                    <div class="step-num">1</div>
                    <div><strong>Endereço</strong><br><small>do destinatário</small></div>
                </div>
                <div class="step" id="step2-indicator">
                    <div class="step-num">2</div>
                    <div><strong>Cotação</strong><br><small>escolha o veículo</small></div>
                </div>
                <div class="step" id="step3-indicator">
                    <div class="step-num">3</div>
                    <div><strong>Confirmar</strong><br><small>e criar pedido</small></div>
                </div>
            </div>

            <!-- PASSO 1: Endereço -->
            <div id="passo1">
                <div class="form-grid">
                    <div class="form-group">
                        <label>CEP do Destinatário</label>
                        <input type="text" id="dest_cep" placeholder="01234-567" maxlength="9" oninput="formatCep(this)" onblur="geocodeCep()">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" id="dest_number" placeholder="123">
                    </div>
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" id="dest_complement" placeholder="Apto 42">
                    </div>
                </div>
                <div id="geocodeResult" style="display:none" class="coord-display"></div>

                <div class="form-grid" style="margin-top:.75rem">
                    <div class="form-group">
                        <label>Rua / Endereço completo</label>
                        <input type="text" id="dest_address" placeholder="Preenchido automaticamente pelo CEP">
                    </div>
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" id="dest_city" readonly style="opacity:.7">
                    </div>
                </div>

                <input type="hidden" id="dest_lat">
                <input type="hidden" id="dest_lng">

                <div class="form-grid" style="margin-top:.5rem">
                    <div class="form-group">
                        <label>Nome do Destinatário</label>
                        <input type="text" id="recipient_name" placeholder="Nome completo">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp / Telefone</label>
                        <input type="text" id="recipient_phone" placeholder="11 9 9999-9999">
                    </div>
                </div>
                <div class="form-group">
                    <label>Observações para o Motoboy</label>
                    <input type="text" id="delivery_remarks" placeholder="Ex: Ligar ao chegar, portão azul, código do portão 1234...">
                </div>
                <div class="form-group" id="localOrderGroup">
                    <label>Pedido Relacionado (opcional)</label>
                    <select id="local_order_id" style="background:#0d1017;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:9px 11px;width:100%">
                        <option value="">— Nenhum pedido específico —</option>
                        <?php foreach($recentOrders as $o): ?>
                        <option value="<?php echo $o['id']; ?>">Pedido #<?php echo $o['id']; ?> — <?php echo htmlspecialchars($o['customer_name']); ?> (R$ <?php echo number_format($o['total_amount'],2,',','.'); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Forma de Pagamento</label>
                    <select id="payment_method" style="background:#0d1017;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:9px 11px;width:100%">
                        <option value="WALLET">Carteira Fight Arcade (Pré-pago)</option>
                        <option value="CASH">Dinheiro - Receber no Local (Cliente Paga)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de Entrega</label>
                    <select id="delivery_tier" onchange="togglePriorityFee()" style="background:#0d1017;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:9px 11px;width:100%">
                        <option value="regular">🟢 Regular (padrão)</option>
                        <option value="priority">⚡ Prioridade (Moto Pro - Mais rápido)</option>
                        <option value="economy">📦 Econômico (Agrupado se disponível)</option>
                    </select>
                </div>
                <div class="form-group" id="priorityFeeGroup" style="display:none">
                    <label>Valor da Gorjeta de Prioridade (R$)</label>
                    <input type="number" id="priority_fee_amount" value="5" min="1" step="1" style="background:#0d1017;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:9px 11px;width:100%">
                    <small style="color:var(--muted)">Quanto maior a gorjeta, mais rápido um motorista aceita o pedido.</small>
                </div>

                <div style="margin-top:1rem">
                    <button type="button" onclick="goToQuote()" class="btn btn-llm" style="font-size:.95rem;padding:11px 24px">
                        <i class="fas fa-calculator"></i> Cotar Frete Agora
                    </button>
                </div>
            </div>

            <!-- PASSO 2: Cotação -->
            <div id="passo2" style="display:none">
                <div id="quoteLoading" class="loading">
                    <i class="fas fa-spinner fa-spin llm-brand" style="font-size:1.4rem"></i>
                    Consultando preços da Lalamove...
                </div>
                <div id="quoteDestInfo" style="margin-bottom:1rem;display:none" class="coord-display"></div>
                <div id="quoteList"></div>

                <input type="hidden" id="selected_quotation_id">
                <input type="hidden" id="selected_stops_json">
                <input type="hidden" id="selected_service_type">

                <div id="quoteActions" style="display:none;margin-top:1.2rem;display:flex;gap:10px;flex-wrap:wrap">
                    <button type="button" onclick="goBack()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</button>
                    <button type="button" onclick="goToConfirm()" class="btn btn-llm" id="btnConfirm" disabled style="font-size:.95rem;padding:11px 24px">
                        <i class="fas fa-check"></i> Confirmar e Criar Pedido
                    </button>
                </div>
            </div>

            <!-- PASSO 3: Confirmar / Resultado -->
            <div id="passo3" style="display:none">
                <div id="confirmSummary" style="margin-bottom:1.2rem"></div>
                <div id="orderResult"></div>
                <div id="confirmActions" style="margin-top:1rem;display:flex;gap:10px">
                    <button type="button" onclick="goBack2()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</button>
                    <button type="button" onclick="submitOrder()" class="btn btn-llm" id="btnSubmit" style="font-size:.95rem;padding:11px 24px">
                        <i class="fas fa-motorcycle"></i> Confirmar e Chamar Motoboy
                    </button>
                </div>
            </div>
        </div>

        <!-- PEDIDOS LALAMOVE ATIVOS -->
        <div class="card">
            <h3><i class="fas fa-list llm-brand"></i> Pedidos Lalamove Recentes</h3>
            <?php
            try {
                $llmOrders = $pdo->query("SELECT o.id, o.me_order_id, o.total_amount, o.status, u.name as customer_name, u.city
                    FROM orders o JOIN users u ON o.user_id=u.id
                    WHERE o.shipping_method='Lalamove' AND o.me_order_id IS NOT NULL AND o.me_order_id != ''
                    ORDER BY o.created_at DESC LIMIT 15")->fetchAll();
            } catch(Exception $e) { $llmOrders = []; }
            ?>
            <?php if (empty($llmOrders)): ?>
            <div style="text-align:center;padding:2rem;color:var(--muted)">
                <i class="fas fa-motorcycle" style="font-size:2rem;display:block;margin-bottom:.5rem;color:var(--border)"></i>
                Nenhum pedido Lalamove ainda.
            </div>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table>
                    <thead><tr>
                        <th>Pedido</th><th>Cliente</th><th>Cidade</th><th>Valor</th><th>ID Lalamove</th><th>Status</th><th>Ações</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($llmOrders as $o): ?>
                    <tr>
                        <td style="font-weight:700">#<?php echo $o['id']; ?></td>
                        <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($o['city'] ?? '—'); ?></td>
                        <td>R$ <?php echo number_format($o['total_amount'],2,',','.'); ?></td>
                        <td><small style="font-family:monospace;color:var(--muted)"><?php echo htmlspecialchars($o['me_order_id']); ?></small></td>
                        <td id="status_<?php echo $o['id']; ?>"><span class="status-badge s-other">—</span></td>
                        <td>
                            <button onclick="checkStatus('<?php echo $o['me_order_id']; ?>', <?php echo $o['id']; ?>)"
                                class="btn btn-secondary btn-sm"><i class="fas fa-sync-alt"></i> Status</button>
                            <button onclick="cancelLlmOrder('<?php echo $o['me_order_id']; ?>')"
                                class="btn btn-sm btn-red" style="margin-left:4px"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Configure a <strong>API Key</strong> e o <strong>API Secret</strong> acima para começar a usar o Lalamove.
        </div>
        <?php endif; ?>

    </div>

    <script>
    // =====================================================
    // Estado global
    // =====================================================
    let currentQuotes     = [];
    let selectedQuoteIdx  = -1;

    // =====================================================
    // Utilitários
    // =====================================================
    function formatCep(el) {
        let v = el.value.replace(/\D/g, '').slice(0, 8);
        if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
        el.value = v;
    }

    function setStep(n) {
        [1,2,3].forEach(i => {
            const el = document.getElementById('step' + i + '-indicator');
            el.className = 'step' + (i < n ? ' done' : i === n ? ' active' : '');
        });
    }

    function statusClass(s) {
        const map = {
            'ASSIGNING_DRIVER': 's-assigning',
            'ON_GOING':         's-ongoing',
            'PICKED_UP':        's-pickedup',
            'COMPLETED':        's-completed',
            'CANCELLED':        's-cancelled',
            'EXPIRED':          's-cancelled',
            'REJECTED':         's-cancelled',
        };
        return map[s] || 's-other';
    }

    function statusLabel(s) {
        const map = {
            'ASSIGNING_DRIVER': '🔍 Buscando motorista',
            'ON_GOING':         '🚗 A caminho',
            'PICKED_UP':        '📦 Coletado',
            'COMPLETED':        '✅ Entregue',
            'CANCELLED':        '❌ Cancelado',
            'EXPIRED':          '⌛ Expirado',
            'REJECTED':         '🚫 Rejeitado',
        };
        return map[s] || s;
    }

    // =====================================================
    // Geocodificação da loja (config)
    // =====================================================
    function geocodeStore() {
        const addr = document.querySelector('input[name="llm_store_address"]').value;
        if (!addr) return alert('Preencha o endereço da loja primeiro.');
        fetch(`lalamove.php?ajax_geocode=1&cep=&address=${encodeURIComponent(addr)}`)
            .then(r => r.json())
            .then(d => {
                if (d.error) { alert('Erro: ' + d.error); return; }
                document.getElementById('storeLat').value = d.lat;
                document.getElementById('storeLng').value = d.lng;
                const info = document.getElementById('storeCoordInfo');
                info.style.display = 'block';
                info.textContent   = `📍 Lat: ${d.lat} | Lng: ${d.lng}`;
            });
    }

    // =====================================================
    // PASSO 1 → Geocodificar CEP do destinatário
    // =====================================================
    function geocodeCep() {
        const cep = document.getElementById('dest_cep').value.replace(/\D/g,'');
        if (cep.length !== 8) return;

        const num = document.getElementById('dest_number').value;
        const res = document.getElementById('geocodeResult');
        res.style.display = 'block';
        res.textContent   = '⏳ Buscando endereço...';

        fetch(`lalamove.php?ajax_geocode=1&cep=${cep}&number=${encodeURIComponent(num)}`)
            .then(r => r.json())
            .then(d => {
                if (d.error) {
                    res.style.color = 'var(--red)';
                    res.textContent = '❌ ' + d.error;
                    return;
                }
                document.getElementById('dest_lat').value     = d.lat;
                document.getElementById('dest_lng').value     = d.lng;
                document.getElementById('dest_address').value = d.formatted_address || d.display_name || '';
                document.getElementById('dest_city').value    = d.city || '';
                res.style.color = 'var(--muted)';
                res.textContent = `📍 Lat: ${parseFloat(d.lat).toFixed(6)} | Lng: ${parseFloat(d.lng).toFixed(6)} | ${d.display_name?.slice(0,60)}...`;
            })
            .catch(() => { res.style.color='var(--red)'; res.textContent='❌ Erro de rede'; });
    }

    // =====================================================
    // PASSO 2 → Cotar frete
    // =====================================================
    function goToQuote() {
        const lat  = document.getElementById('dest_lat').value;
        const lng  = document.getElementById('dest_lng').value;
        const addr = document.getElementById('dest_address').value;

        if (!lat || !lng) {
            alert('Digite o CEP e aguarde a geocodificação antes de cotar.');
            return;
        }
        if (!document.getElementById('recipient_name').value) {
            alert('Preencha o nome do destinatário.');
            return;
        }

        document.getElementById('passo1').style.display = 'none';
        document.getElementById('passo2').style.display = 'block';
        document.getElementById('passo3').style.display = 'none';
        setStep(2);

        const loading    = document.getElementById('quoteLoading');
        const quoteList  = document.getElementById('quoteList');
        const quoteActions= document.getElementById('quoteActions');
        const destInfo   = document.getElementById('quoteDestInfo');

        loading.style.display    = 'flex';
        quoteList.innerHTML      = '';
        quoteActions.style.display = 'none';
        destInfo.style.display   = 'none';
        selectedQuoteIdx         = -1;
        document.getElementById('btnConfirm').disabled = true;

        // Detectar se pagamento é dinheiro para passar COD na cotação
        const payMethod = document.getElementById('payment_method').value;
        const tier = document.getElementById('delivery_tier').value;
        
        let queryParams = `ajax_quote=1&lat=${lat}&lng=${lng}&address=${encodeURIComponent(addr)}&type=${tier}`;
        if (payMethod === 'CASH') queryParams += '&cod=1';
        if (tier === 'economy') queryParams += '&grouped=1';
        
        if (window._isReversePurchase) {
            queryParams += `&is_reverse=1&orig_lat=${window._supplierOrigin.lat}&orig_lng=${window._supplierOrigin.lng}&orig_address=${encodeURIComponent(window._supplierOrigin.address)}`;
        }

        fetch(`lalamove.php?${queryParams}`)
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';

                if (data.error) {
                    quoteList.innerHTML = `<div class="alert alert-error"><i class="fas fa-times-circle"></i> ${data.error}</div>`;
                    return;
                }

                destInfo.style.display = 'block';
                if (window._isReversePurchase) {
                    destInfo.innerHTML = `🏍️ <b>COLETA REVERSA</b>: De <u>${window._supplierOrigin.name}</u> para <u>Loja</u>`;
                } else {
                    destInfo.textContent = `📦 De: ${data.storeLat}, ${data.storeLng} → Para: ${lat}, ${lng}`;
                }

                currentQuotes = data.quotes || [];
                let html      = '';
                let firstOk   = true;

                if (data.sandbox) {
                    html += `<div class="sandbox-notice" style="margin-bottom:.75rem"><i class="fas fa-flask"></i> Modo Sandbox — valores são simulados</div>`;
                }

                const iconMap = {
                    'LALAGO_POOL': '📦', 'LALAGO': '🏍️', 'LALAPRO': '⚡',
                    'MOTORCYCLE': '🏍️', 'HATCHBACK': '🚗', 'CAR': '🚙',
                    'UV_FIORINO': '🚐', 'VAN': '🚐', 'TRUCK330': '🚛', 'TRUCK3_5T': '🚛'
                };

                let autoSelectIdx = -1;

                currentQuotes.forEach((q, i) => {
                    const hasError = !!q.error;
                    const icon = iconMap[q.serviceType] || '📦';
                    const priceRaw = q.total || 0;
                    const price = (parseFloat(priceRaw)).toLocaleString('pt-BR', {minimumFractionDigits:2});
                    const isCheap = !hasError && firstOk;
                    if (!hasError) firstOk = false;

                    // Auto-select: agrupado no modo econômico, ou mais barato por padrão
                    if (!hasError && tier === 'economy' && q.serviceType === 'LALAGO_POOL') {
                        autoSelectIdx = i;
                    } else if (!hasError && autoSelectIdx === -1 && isCheap) {
                        autoSelectIdx = i;
                    }

                    const isPool = q.serviceType === 'LALAGO_POOL';
                    const metaText = hasError ? '' 
                        : (isPool ? 'Econômico — Pode demorar mais, menor custo' : 'Entrega expressa no mesmo dia');

                    html += `
                        <div class="quote-card ${hasError ? 'unavailable' : ''} ${isCheap ? 'cheapest' : ''}" 
                             id="quote-card-${i}"
                             onclick="${!hasError ? `selectQuote(${i})` : 'void(0)'}">
                            <div class="quote-icon">${icon}</div>
                            <div class="quote-info">
                                <div class="quote-name">${q.label}</div>
                                ${hasError
                                    ? `<div class="quote-error-text"><i class="fas fa-ban"></i> ${q.error}</div>`
                                    : `<div class="quote-meta">${metaText}</div>`
                                }
                            </div>
                            <div class="quote-price-col">
                                ${hasError
                                    ? `<div class="price-unavail">N/D</div>`
                                    : `<div class="quote-price">R$ ${price}</div>
                                       ${isCheap ? '<div class="cheapest-tag">mais barato</div>' : ''}`
                                }
                            </div>
                        </div>`;
                });

                // Aviso se agrupado não disponível no modo econômico
                if (tier === 'economy' && autoSelectIdx === -1) {
                    html = `<div class="alert alert-warning" style="margin-bottom:.75rem"><i class="fas fa-info-circle"></i>
                        <strong>Agrupado (LALAGO_POOL)</strong> não disponível nesta região. As alternativas mais em conta estão listadas abaixo.</div>` + html;
                }

                if (!html || currentQuotes.every(q => q.error)) {
                    html = `<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i>
                        Nenhuma opção disponível para este destino. Lalamove atende apenas dentro de São Paulo e Rio de Janeiro.</div>`;
                }

                quoteList.innerHTML      = html;
                quoteActions.style.display = 'flex';

                // Auto-selecionar o melhor (agrupado ou mais barato)
                if (autoSelectIdx >= 0) {
                    selectQuote(autoSelectIdx);
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                quoteList.innerHTML   = `<div class="alert alert-error">Erro de rede: ${err.message}</div>`;
            });
    }

    function selectQuote(idx) {
        // Limpar seleção anterior
        document.querySelectorAll('.quote-card').forEach(c => c.classList.remove('selected'));
        
        // Selecionar pelo ID direto
        const card = document.getElementById('quote-card-' + idx);
        if (card) card.classList.add('selected');

        selectedQuoteIdx = idx;
        const selected = currentQuotes[idx];
        if (!selected || selected.error) return;
        
        document.getElementById('selected_quotation_id').value  = selected.quotationId;
        document.getElementById('selected_stops_json').value    = JSON.stringify(selected.stops || []);
        document.getElementById('selected_service_type').value  = selected.serviceType;
        document.getElementById('btnConfirm').disabled          = false;
    }

    // =====================================================
    // PASSO 3 → Confirmar
    // =====================================================
    function goToConfirm() {
        if (selectedQuoteIdx < 0) return;
        const q       = currentQuotes[selectedQuoteIdx];
        const price   = (parseFloat(q.total) || 0).toLocaleString('pt-BR', {minimumFractionDigits:2});
        const name    = document.getElementById('recipient_name').value;
        const phone   = document.getElementById('recipient_phone').value;
        const address = document.getElementById('dest_address').value;
        const remarks = document.getElementById('delivery_remarks').value;
        const iconMap = { MOTORCYCLE:'🏍️', VAN:'🚐', TRUCK175:'🚛', TRUCK330:'🚛' };

        document.getElementById('passo1').style.display = 'none';
        document.getElementById('passo2').style.display = 'none';
        document.getElementById('passo3').style.display = 'block';
        setStep(3);

        document.getElementById('confirmSummary').innerHTML = `
            <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;padding:1.25rem">
                <div style="font-size:.8rem;color:var(--muted);text-transform:uppercase;font-weight:700;margin-bottom:.75rem">Resumo da Entrega</div>
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
                    <span style="color:var(--muted)">Veículo:</span>
                    <span style="font-weight:600">${iconMap[q.serviceType] || '📦'} ${q.label}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
                    <span style="color:var(--muted)">Preço:</span>
                    <span style="font-weight:700;color:var(--brand);font-size:1.1rem">R$ ${price}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
                    <span style="color:var(--muted)">Pagamento:</span>
                    <span style="font-weight:600">${document.getElementById('payment_method').value === 'CASH' ? '💵 Dinheiro (Cliente Paga)' : '💳 Carteira'}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
                    <span style="color:var(--muted)">Tipo:</span>
                    <span style="font-weight:600">${document.getElementById('delivery_tier').value === 'priority' ? '⚡ Prioridade (+R$ ' + document.getElementById('priority_fee_amount').value + ')' : '🟢 Regular'}</span>
                </div>
                <hr style="border:0;border-top:1px solid var(--border);margin:.75rem 0">
                <div style="margin-bottom:.25rem;font-weight:600">${name}</div>
                <div style="font-size:.85rem;color:var(--muted);margin-bottom:.25rem">${phone}</div>
                <div style="font-size:.85rem;color:var(--muted);line-height:1.4">${address}</div>
            </div>
            <div style="margin-top:1rem;font-size:.85rem;color:var(--muted);text-align:center">
                <i class="fas fa-info-circle"></i> O valor será cobrado conforme a opção de pagamento selecionada.
            </div>
        `;
        document.getElementById('orderResult').innerHTML = '';
    }

    function goBack()  {
        document.getElementById('passo1').style.display = 'block';
        document.getElementById('passo2').style.display = 'none';
        document.getElementById('passo3').style.display = 'none';
        setStep(1);
    }
    function goBack2() {
        document.getElementById('passo2').style.display = 'block';
        document.getElementById('passo3').style.display = 'none';
        setStep(2);
    }

    // =====================================================
    // PASSO 3 → Submeter pedido
    // =====================================================
    function submitOrder() {
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chamando motoboy...';

        const fd = new FormData();
        fd.append('ajax_create_order',  '1');
        fd.append('quotation_id',       document.getElementById('selected_quotation_id').value);
        fd.append('stops_json',         document.getElementById('selected_stops_json').value);
        fd.append('recipient_name',     document.getElementById('recipient_name').value);
        fd.append('recipient_phone',    document.getElementById('recipient_phone').value);
        fd.append('remarks',            document.getElementById('delivery_remarks').value);
        fd.append('local_order_id',     document.getElementById('local_order_id').value);
        fd.append('payment_method',     document.getElementById('payment_method').value);
        fd.append('total_value',        currentQuotes[selectedQuoteIdx].total);
        fd.append('notify_sms',         '1');
        // Enviar priority fee se for tipo prioridade
        if (document.getElementById('delivery_tier').value === 'priority') {
            fd.append('priority_fee', document.getElementById('priority_fee_amount').value);
        }
        
        if (window._isReversePurchase) {
            fd.append('is_reverse', '1');
            fd.append('orig_name', window._supplierOrigin.name);
            fd.append('orig_phone', window._supplierOrigin.phone);
        }

        fetch('lalamove.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                const res = document.getElementById('orderResult');
                const actions = document.getElementById('confirmActions');
                if (data.success) {
                    actions.innerHTML = `<button onclick="location.reload()" class="btn btn-secondary">🔄 Nova entrega</button>`;
                    res.innerHTML = `
                        <div class="alert alert-success" style="margin-top:1rem">
                            <strong><i class="fas fa-check-circle"></i> Pedido criado com sucesso!</strong><br>
                            <span style="font-family:monospace;font-size:.85rem">ID: ${data.orderId}</span><br>
                            <small>Status: ${statusLabel(data.status)}</small><br><br>
                            <button onclick="checkStatus('${data.orderId}', 0)" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sync-alt"></i> Atualizar status
                            </button>
                        </div>`;
                } else {
                    res.innerHTML = `
                        <div class="alert alert-error" style="margin-top:1rem">
                            <strong><i class="fas fa-times-circle"></i> Erro ao criar pedido</strong><br>
                            ${data.error}
                        </div>`;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-motorcycle"></i> Tentar novamente';
                }
            })
            .catch(err => {
                alert('Erro de rede: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-motorcycle"></i> Confirmar e Chamar Motoboy';
            });
    }

    // =====================================================
    // Verificar status de pedido existente
    // =====================================================
    function checkStatus(llmOrderId, localId) {
        fetch(`lalamove.php?ajax_status=1&order_id=${llmOrderId}`)
            .then(r => r.json())
            .then(data => {
                const s = data.status;
                const cell = document.getElementById('status_' + localId);
                if (cell) cell.innerHTML = `<span class="status-badge ${statusClass(s)}">${statusLabel(s)}</span>`;

                let info = `<strong>${statusLabel(s)}</strong>`;
                if (data.driverName) {
                    info += `<br>👤 Motorista: <b>${data.driverName}</b>`;
                    if (data.driverPhone) info += ` (${data.driverPhone})`;
                    if (data.driverPlate) info += `<br>🚲 Placa: <b>${data.driverPlate}</b>`;
                    if (data.driverPhoto) info += `<br><img src="${data.driverPhoto}" style="width:50px;height:50px;border-radius:50%;margin-top:5px">`;
                    if (data.shareLink) info += `<br><a href="${data.shareLink}" target="_blank" style="color:var(--brand)">📍 Link de rastreio</a>`;
                    
                    // Botão para enviar via WhatsApp
                    const custName = document.getElementById('recipient_name')?.value || '';
                    const custPhone = document.getElementById('recipient_phone')?.value || '';
                    if (custPhone) {
                        info += `<br><br><button onclick="notifyDriverToCustomer('${custPhone}','${custName}','${data.driverName}','${data.driverPlate}','${data.driverPhone}','${data.shareLink || ''}')" class="btn btn-llm btn-sm" style="font-size:.8rem;padding:6px 12px">📲 Enviar dados ao cliente via WhatsApp</button>`;
                    }
                }
                
                // Mostrar no resultado do pedido
                const res = document.getElementById('orderResult');
                if (res) {
                    res.innerHTML = `<div class="alert alert-success" style="margin-top:1rem">${info}</div>`;
                }
            });
    }

    // Enviar dados do motoboy para o cliente via WhatsApp
    function notifyDriverToCustomer(phone, name, dName, plate, dPhone, link) {
        const fd = new FormData();
        fd.append('ajax_notify_driver', '1');
        fd.append('customer_phone', phone);
        fd.append('customer_name', name);
        fd.append('driver_name', dName);
        fd.append('driver_plate', plate);
        fd.append('driver_phone', dPhone);
        fd.append('share_link', link);
        fetch('lalamove.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) alert('✅ Dados do motoboy enviados ao cliente via WhatsApp!');
                else alert('⚠️ Não foi possível enviar. Verifique a configuração da Evolution API.');
            });
    }

    // Auto-preencher dados ao selecionar pedido
    document.getElementById('local_order_id').addEventListener('change', function() {
        const oid = this.value;
        if (!oid) return;
        
        // Buscar CEP/Dados do pedido via AJAX (reaproveitando geocode)
        fetch(`lalamove.php?ajax_geocode=1&order_id=${oid}`)
            .then(r => r.json())
            .then(d => {
                if (d.error) return;
                document.getElementById('dest_cep').value = d.zipcode || '';
                document.getElementById('dest_number').value = d.number || '';
                document.getElementById('dest_complement').value = d.complement || '';
                document.getElementById('dest_address').value = d.formatted_address || d.address || '';
                document.getElementById('dest_city').value = d.city || '';
                document.getElementById('dest_lat').value = d.lat || '';
                document.getElementById('dest_lng').value = d.lng || '';
                
                // Novos campos auto-preenchidos
                if (d.recipient_name) document.getElementById('recipient_name').value = d.recipient_name;
                if (d.recipient_phone) document.getElementById('recipient_phone').value = d.recipient_phone;
            });
    });

    // Toggle visibility da gorjeta de prioridade e recota ao mudar
    function togglePriorityFee() {
        const tier = document.getElementById('delivery_tier').value;
        document.getElementById('priorityFeeGroup').style.display = (tier === 'priority') ? 'block' : 'none';
        
        // Recota se já estiver no passo 2
        if (document.getElementById('passo2').style.display === 'block') {
            goToQuote();
        }
    }
    
    document.getElementById('delivery_tier').addEventListener('change', togglePriorityFee);

    // =====================================================
    // Cancelar pedido
    // =====================================================
    function cancelLlmOrder(llmOrderId) {
        if (!confirm('Cancelar este pedido Lalamove? Só é possível antes do motorista coletar.')) return;
        const fd = new FormData();
        fd.append('ajax_cancel', '1');
        fd.append('order_id', llmOrderId);
        fetch('lalamove.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) { alert('✅ Pedido cancelado!'); location.reload(); }
                else alert('❌ Não foi possível cancelar. O motorista pode já ter coletado.');
            });
    }
    </script>
</body>
</html>
<?php
} catch (Throwable $e) {
    header("Location: emergency_fix.php?fatal_error=" . urlencode($e->getMessage()) . "&file=lalamove.php");
    exit;
}
?>
