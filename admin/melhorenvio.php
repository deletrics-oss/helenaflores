<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/melhorenvio.php';
isAdmin();

$me = new MelhorEnvioAPI($pdo);
$msg = '';

// Save Config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $fields = ['me_client_id','me_client_secret','me_redirect_uri','me_sandbox','me_from_zipcode'];
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$f, $val, $val]);
    }
    $msg = '<div class="alert alert-success">✅ Configurações salvas!</div>';
    $me = new MelhorEnvioAPI($pdo); // Reload
}

// AJAX: Calculate shipping for an order
if (isset($_GET['ajax_quote']) && isset($_GET['order_id'])) {
    header('Content-Type: application/json');
    try {
        $oid = (int) $_GET['order_id'];
        $order = $pdo->query("SELECT o.*, COALESCE(u.zipcode, '') as dest_zip, COALESCE(u.name, 'Cliente') as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['quotes' => ['error' => 'Pedido não encontrado']]);
            exit;
        }

        $destZip = preg_replace('/\D/', '', $order['dest_zip'] ?? '');
        if (empty($destZip) && !empty($order['shipping_address'])) {
            if (preg_match('/(\d{5}-?\d{3})/', $order['shipping_address'], $matches)) {
                $destZip = preg_replace('/\D/', '', $matches[1]);
            }
        }
        if (empty($destZip)) {
            $destZip = '01420001'; // Default São Paulo SP (Jardins)
        }

        $fromZip = '03611060';
        try {
            $f = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'me_from_zipcode'")->fetchColumn();
            if ($f) $fromZip = preg_replace('/\D/', '', $f);
        } catch(Exception $e) {}

        // Check if user forced custom dimensions/insurance via UI
        $forceW = isset($_GET['box_w']) ? (int) $_GET['box_w'] : 0;
        $forceH = isset($_GET['box_h']) ? (int) $_GET['box_h'] : 0;
        $forceL = isset($_GET['box_l']) ? (int) $_GET['box_l'] : 0;
        $forceWt = isset($_GET['box_wt']) ? (float) $_GET['box_wt'] : 0;
        $forceIns = isset($_GET['box_ins']) && $_GET['box_ins'] !== '' ? (float) $_GET['box_ins'] : -1;

        $finalW = ($forceW > 0) ? $forceW : 15;
        $finalH = ($forceH > 0) ? $forceH : 10;
        $finalL = ($forceL > 0) ? $forceL : 20;
        $finalWt = ($forceWt > 0) ? $forceWt : 0.5;
        $finalIns = ($forceIns >= 0) ? $forceIns : min((float)($order['total_amount'] ?? 100), 1500.00);

        $products = [
            [
                'id' => '1',
                'width' => $finalW,
                'height' => $finalH,
                'length' => $finalL,
                'weight' => $finalWt,
                'insurance_value' => (float)$finalIns,
                'quantity' => 1
            ]
        ];

        $result = $me->calculateShipping($fromZip, $destZip, $products);

        echo json_encode(['quotes' => $result ?: [], 'order' => $order, 'debug_from' => $fromZip]);
    } catch (Exception $e) {
        echo json_encode(['quotes' => ['error' => 'Erro ao cotar frete: ' . $e->getMessage()]]);
    }
    exit;
}

// AJAX: Buy shipping (add to ME cart + checkout + generate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_buy'])) {
    header('Content-Type: application/json');
    $oid = (int) $_POST['order_id'];
    $serviceId = (int) $_POST['service_id'];
    
    $order = $pdo->query("SELECT o.*, u.* FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch();
    if (empty($order['zipcode'])) {
        echo json_encode(['success' => false, 'error' => '❌ Erro: O cliente não possui CEP cadastrado.']);
        exit;
    }
    $items = $pdo->query("SELECT oi.*, p.weight_kg, p.length_cm, p.width_cm, p.height_cm FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $oid")->fetchAll();
    
    $fromZip = '';
    try { $fromZip = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'me_from_zipcode'")->fetchColumn(); } catch(Exception $e) {}
    if (!$fromZip) $fromZip = '79002000';
    
    // Get store info for sender (Hardcoded to Daniel Souza as requested previously)
    $storeName = 'Daniel Souza';
    $storeDoc = '36542882863';
    $storePhone = '11999999999';
    $storeEmail = 'deletrics@gmail.com';
    $storeAddress = 'Rua Cristiano Osorio';
    $storeNumber = '143';
    $storeDistrict = 'Vila Esperança';
    $storeCity = 'São Paulo';
    $storeState = 'SP';
    $fromZip = '03611060';
    
    if (empty($order['city']) || empty($order['address'])) {
        echo json_encode(['success' => false, 'error' => '❌ Dados Incompletos: O cadastro do cliente está sem Cidade ou Endereço. Atualize o cadastro do cliente para gerar a etiqueta.']);
        exit;
    }

    $meProducts = [];
    foreach ($items as $it) {
        $meProducts[] = [
            'name' => $it['product_name'],
            'quantity' => (int) $it['quantity'],
            'unitary_value' => (float) $it['unit_price']
        ];
    }
    
    // Sum dimensions for package
    $totalWeight = 0;
    $maxW = 0; $maxL = 0; $totalH = 0;
    foreach ($items as $it) {
        $totalWeight += ($it['weight_kg'] ?? 0.3) * $it['quantity'];
        $w = $it['width_cm'] ?? 15; $l = $it['length_cm'] ?? 20; $h = $it['height_cm'] ?? 10;
        if ($w > $maxW) $maxW = $w;
        if ($l > $maxL) $maxL = $l;
        if ($h > $totalH) $totalH = $h; // Use a maior altura, não some todas
    }
    // Capture custom dimensions/insurance from POST if available (passed from the UI)
    $forceW = isset($_POST['box_w']) ? (int) $_POST['box_w'] : 0;
    $forceH = isset($_POST['box_h']) ? (int) $_POST['box_h'] : 0;
    $forceL = isset($_POST['box_l']) ? (int) $_POST['box_l'] : 0;
    $forceWt = isset($_POST['box_wt']) ? (float) $_POST['box_wt'] : 0;
    $forceIns = isset($_POST['box_ins']) && $_POST['box_ins'] !== '' ? (float) $_POST['box_ins'] : -1;

    $finalW = ($forceW > 0) ? $forceW : max(11, (int)$maxW);
    $finalH = ($forceH > 0) ? $forceH : ($totalH > 4 ? (int)$totalH : 4); 
    $finalL = ($forceL > 0) ? $forceL : max(16, (int)$maxL);
    $finalWt = ($forceWt > 0) ? $forceWt : max(0.3, $totalWeight);
    $finalIns = ($forceIns >= 0) ? $forceIns : min((float)$order['total_amount'], 1500.00);
    
    $cartPayload = [
        'service' => $serviceId,
        'agency' => null,
        'from' => [
            'name' => $storeName,
            'phone' => $storePhone,
            'email' => $storeEmail,
            'document' => (strlen($storeDoc) <= 11) ? $storeDoc : '',
            'company_document' => (strlen($storeDoc) > 11) ? $storeDoc : '',
            'address' => $storeAddress,
            'complement' => '',
            'number' => $storeNumber,
            'district' => $storeDistrict,
            'city' => $storeCity,
            'state_abbr' => $storeState,
            'country_id' => 'BR',
            'postal_code' => preg_replace('/\D/', '', $fromZip),
        ],
        'to' => [
            'name' => $order['name'] ?? '',
            'phone' => preg_replace('/\D/', '', $order['phone'] ?? ''),
            'email' => !empty($order['email']) ? $order['email'] : 'cliente@fightarcade.temp',
            'document' => (strlen(preg_replace('/\D/', '', $order['document'] ?? '')) <= 11) ? preg_replace('/\D/', '', $order['document'] ?? '') : '',
            'company_document' => (strlen(preg_replace('/\D/', '', $order['document'] ?? '')) > 11) ? preg_replace('/\D/', '', $order['document'] ?? '') : '',
            'address' => $order['address'] ?? '',
            'complement' => $order['complement'] ?? '',
            'number' => $order['number'] ?? '',
            'district' => $order['neighborhood'] ?? '',
            'city' => $order['city'] ?? '',
            'state_abbr' => $order['state'] ?? '',
            'country_id' => 'BR',
            'postal_code' => preg_replace('/\D/', '', $order['zipcode'] ?? ''),
        ],
        'products' => $meProducts,
        'volumes' => [[
            'height' => $finalH,
            'width' => $finalW,
            'length' => $finalL,
            'weight' => $finalWt
        ]],
        'options' => [
            'insurance_value' => (float) $finalIns,
            'receipt' => false,
            'own_hand' => false,
            'reverse' => false,
            'non_commercial' => true,
            'invoice' => ['key' => '']
        ]
    ];
    
    // Step 1: Add to cart
    $cartResult = $me->addToCart($cartPayload);
    
    if (isset($cartResult['id'])) {
        $meOrderId = $cartResult['id'];
        
        // Step 2: Checkout (pay)
        $checkoutResult = $me->checkout([$meOrderId]);
        
        // Check if checkout was successful (status 200/201 and has data)
        // If it fails (e.g. no balance), it will usually not return a successful response code or message
        $isPaid = (isset($checkoutResult['purchase']) || (isset($checkoutResult[0]) && !isset($checkoutResult['error'])));

        if ($isPaid) {
            // Step 3: Generate label (This creates the tracking number)
            $me->generateLabel([$meOrderId]);
            
            // Step 4: Get tracking immediately if possible
            $trackRes = $me->tracking([$meOrderId]);
            $trackCode = $trackRes[$meOrderId]['tracking'] ?? null;
            
            // Fallback: Se o rastreio for vazio, tenta obter os detalhes do pedido diretamente (GET /orders/{id})
            if (empty($trackCode)) {
                $orderDetail = $me->getOrder($meOrderId);
                if ($orderDetail && is_array($orderDetail) && !isset($orderDetail['error']) && !empty($orderDetail['tracking'])) {
                    $trackCode = $orderDetail['tracking'];
                }
            }
            
            if ($trackCode) {
                $existing = $pdo->query("SELECT me_order_id, tracking_code FROM orders WHERE id = $oid")->fetch(PDO::FETCH_ASSOC);
                $finalMeId = $meOrderId;
                if (!empty($existing['me_order_id'])) {
                    $existingMeIds = array_filter(array_map('trim', explode(',', $existing['me_order_id'])));
                    if (!in_array($meOrderId, $existingMeIds)) {
                        $finalMeId = $existing['me_order_id'] . ', ' . $meOrderId;
                    } else {
                        $finalMeId = $existing['me_order_id'];
                    }
                }
                $finalTrack = $trackCode;
                if (!empty($existing['tracking_code'])) {
                    $existingTracks = array_filter(array_map('trim', explode(',', $existing['tracking_code'])));
                    if (!in_array($trackCode, $existingTracks)) {
                        $finalTrack = $existing['tracking_code'] . ', ' . $trackCode;
                    } else {
                        $finalTrack = $existing['tracking_code'];
                    }
                }

                $pdo->prepare("UPDATE orders SET me_order_id = ?, shipping_method = ?, tracking_code = ?, status = 'shipped' WHERE id = ?")
                    ->execute([$finalMeId, 'Melhor Envio', $finalTrack, $oid]);
            } else {
                $existing = $pdo->query("SELECT me_order_id FROM orders WHERE id = $oid")->fetchColumn();
                $finalMeId = $meOrderId;
                if (!empty($existing)) {
                    $existingMeIds = array_filter(array_map('trim', explode(',', $existing)));
                    if (!in_array($meOrderId, $existingMeIds)) {
                        $finalMeId = $existing . ', ' . $meOrderId;
                    } else {
                        $finalMeId = $existing;
                    }
                }
                $pdo->prepare("UPDATE orders SET me_order_id = ?, shipping_method = ? WHERE id = ?")
                    ->execute([$finalMeId, 'Melhor Envio', $oid]);
            }
        } else {
            // Just save the ID to sync later
            $existing = $pdo->query("SELECT me_order_id FROM orders WHERE id = $oid")->fetchColumn();
            $finalMeId = $meOrderId;
            if (!empty($existing)) {
                $existingMeIds = array_filter(array_map('trim', explode(',', $existing)));
                if (!in_array($meOrderId, $existingMeIds)) {
                    $finalMeId = $existing . ', ' . $meOrderId;
                } else {
                    $finalMeId = $existing;
                }
            }
            $pdo->prepare("UPDATE orders SET me_order_id = ?, shipping_method = ? WHERE id = ?")
                ->execute([$finalMeId, 'Melhor Envio', $oid]);
        }
        
        echo json_encode([
            'success' => true, 
            'me_order_id' => $meOrderId, 
            'paid' => $isPaid,
            'message' => $isPaid ? 'Frete pago e etiqueta gerada!' : 'Enviado para o carrinho (Aguardando pagamento manual no site)'
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => $cartResult]);
        exit;
    }
}

// AJAX: Sync Order Tracking
if (isset($_GET['ajax_sync']) && isset($_GET['order_id']) && isset($_GET['me_id'])) {
    header('Content-Type: application/json');
    $oid = (int)$_GET['order_id'];
    $me_id = $_GET['me_id'];
    
    // Split by comma in case of multiple IDs
    $meIds = array_filter(array_map('trim', explode(',', $me_id)));
    $existingTrack = $pdo->query("SELECT tracking_code FROM orders WHERE id = $oid")->fetchColumn();
    $existingCodes = !empty($existingTrack) ? array_filter(array_map('trim', explode(',', $existingTrack))) : [];
    
    foreach ($meIds as $id) {
        $res = $me->tracking([$id]);
        $track = null;
        if (isset($res[$id]['tracking']) && !empty($res[$id]['tracking'])) {
            $track = $res[$id]['tracking'];
        } else {
            // Fallback: GET /orders/{id}
            $orderDetail = $me->getOrder($id);
            if ($orderDetail && is_array($orderDetail) && !isset($orderDetail['error']) && !empty($orderDetail['tracking'])) {
                $track = $orderDetail['tracking'];
            }
        }
        if ($track && !in_array($track, $existingCodes)) {
            $existingCodes[] = $track;
        }
    }
    
    if (!empty($existingCodes)) {
        $trackStr = implode(', ', $existingCodes);
        $pdo->prepare("UPDATE orders SET tracking_code = ?, status = 'shipped' WHERE id = ?")->execute([$trackStr, $oid]);
        echo json_encode(['success' => true, 'tracking' => $trackStr]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ainda não pago ou sem rastreio disponível']);
    }
    exit;
}

// AJAX: Print label
if (isset($_GET['print_label'])) {
    header('Content-Type: application/json');
    $meId = $_GET['print_label'];
    
    // Split by comma in case of multiple IDs
    $ids = array_filter(array_map('trim', explode(',', $meId)));
    
    // First, ensure all are generated
    foreach ($ids as $id) {
        $me->generateLabel([$id]);
    }
    
    // Give ME 0.5 seconds to process if it's the first time
    usleep(500000); 
    
    $result = $me->printLabel($ids);
    echo json_encode($result);
    exit;
}

// Load settings for form
$cfg = [];
$cfgKeys = ['me_client_id','me_client_secret','me_redirect_uri','me_sandbox','me_from_zipcode','me_access_token'];
foreach ($cfgKeys as $k) {
    try { $cfg[$k] = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = '$k'")->fetchColumn() ?: ''; } catch(Exception $e) { $cfg[$k] = ''; }
}

// Load recent orders for shipping
$orders = $pdo->query("SELECT o.*, u.name as customer_name, u.zipcode as dest_zip, u.city as dest_city, u.state as dest_state FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 20")->fetchAll();

// ME Balance
$balance = null;
if ($me->hasToken()) {
    $balance = $me->getBalance();
}

if (isset($_GET['msg']) && $_GET['msg'] === 'connected') {
    $msg = '<div class="alert alert-success">✅ Melhor Envio conectado com sucesso!</div>';
}
if (isset($_GET['error'])) {
    $msg = '<div class="alert alert-error">❌ Erro: ' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Melhor Envio | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .me-card{background:#1a1e26;border:1px solid #333;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem}
        .me-card h3{color:#e74c3c;margin-bottom:1rem;display:flex;align-items:center;gap:10px}
        .me-card h3 i{color:#e74c3c}
        .config-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .config-grid label{display:block;font-size:.85rem;color:#888;margin-bottom:4px}
        .config-grid input,.config-grid select{width:100%;padding:10px;background:#111;border:1px solid #444;color:#fff;border-radius:6px}
        .status-connected{background:rgba(46,204,113,.1);border:1px solid #2ecc71;padding:10px 15px;border-radius:8px;color:#2ecc71;display:flex;align-items:center;gap:8px;margin-bottom:1rem}
        .status-disconnected{background:rgba(231,76,60,.1);border:1px solid #e74c3c;padding:10px 15px;border-radius:8px;color:#e74c3c;display:flex;align-items:center;gap:8px;margin-bottom:1rem}
        .balance-box{background:#222;padding:12px 20px;border-radius:8px;border:1px solid #f1c40f;display:inline-flex;align-items:center;gap:10px;margin-bottom:1rem}
        .balance-val{font-size:1.5rem;font-weight:bold;color:#2ecc71}
        .quote-row{display:flex;align-items:center;gap:15px;padding:12px;background:#222;border-radius:6px;margin-bottom:6px;cursor:pointer;transition:border-color .2s;border:1px solid #333}
        .quote-row:hover{border-color:#f1c40f}
        .quote-row.selected{border-color:#2ecc71;background:rgba(46,204,113,.05)}
        .quote-logo{width:60px;height:30px;object-fit:contain;background:#fff;border-radius:4px;padding:2px}
        .quote-price{font-weight:bold;color:#2ecc71;font-size:1.1rem;min-width:100px}
        .quote-days{color:#888;font-size:.85rem}
        #quoteModal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center}
        #quoteModal.active{display:flex}
        .modal-box{background:#1a1e26;border:2px solid #f1c40f;border-radius:16px;padding:2rem;max-width:600px;width:90%;max-height:80vh;overflow-y:auto}
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <h1 style="margin-bottom:.5rem"><i class="fas fa-truck" style="color:#e74c3c"></i> Melhor Envio</h1>
        <p style="color:#777;margin-bottom:2rem">Cotação, compra de frete e geração de etiquetas</p>

        <?php echo $msg; ?>

        <!-- STATUS -->
        <?php if ($me->hasToken()): ?>
        <div class="status-connected"><i class="fas fa-check-circle"></i> Conectado ao Melhor Envio</div>
        <?php if ($balance): ?>
        <?php $bal = (float)($balance['balance'] ?? 0); ?>
        <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap; margin-bottom:1rem;">
            <div class="balance-box" style="<?php echo $bal <= 0 ? 'border-color:#e74c3c;' : ''; ?>">
                <span style="color:#888">💰 Saldo:</span>
                <span class="balance-val" style="<?php echo $bal <= 0 ? 'color:#e74c3c;' : ''; ?>">R$ <?php echo number_format($bal, 2, ',', '.'); ?></span>
            </div>
            <a href="https://melhorenvio.com.br/painel/gerenciar/carteira" target="_blank" rel="noopener" class="btn" style="background:#2ecc71; color:#000; font-weight:bold; padding:12px 20px; font-size:0.95rem; border-radius:8px; box-shadow:0 0 15px rgba(46,204,113,.3); text-decoration:none;">
                ➕ ADICIONAR SALDO (PIX)
            </a>
        </div>
        <?php if ($bal <= 0): ?>
        <div style="background:rgba(231,76,60,.1); border:1px solid #e74c3c; padding:12px 18px; border-radius:8px; color:#e74c3c; margin-bottom:1rem; font-size:0.88rem; display:flex; align-items:center; gap:10px;">
            <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;"></i>
            <div>
                <strong>Saldo Insuficiente!</strong> Você precisa adicionar saldo no painel do Melhor Envio para gerar etiquetas automaticamente.<br>
                <small style="color:#999;">Clique no botão verde acima → Faça PIX → O saldo atualiza em segundos. Depois volte aqui e recarregue a página.</small>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php else: ?>
        <div class="status-disconnected"><i class="fas fa-times-circle"></i> Não conectado</div>
        <?php endif; ?>

        <!-- CONFIG -->
        <div class="me-card">
            <h3><i class="fas fa-cog"></i> Configuração</h3>
            <form method="POST">
                <div class="config-grid">
                    <div>
                        <label>Client ID</label>
                        <input type="text" name="me_client_id" value="<?php echo htmlspecialchars($cfg['me_client_id']); ?>" placeholder="Cole o Client ID do app">
                    </div>
                    <div>
                        <label>Client Secret</label>
                        <input type="password" name="me_client_secret" value="<?php echo htmlspecialchars($cfg['me_client_secret']); ?>" placeholder="Cole o Client Secret">
                    </div>
                    <div>
                        <label>URL de Redirecionamento (Callback)</label>
                        <input type="text" name="me_redirect_uri" value="<?php echo htmlspecialchars($cfg['me_redirect_uri'] ?: 'https://www.fightarcade.com.br/catalogo/admin/melhorenvio_callback.php'); ?>">
                    </div>
                    <div>
                        <label>CEP de Origem (Sua Loja)</label>
                        <input type="text" name="me_from_zipcode" value="<?php echo htmlspecialchars($cfg['me_from_zipcode']); ?>" placeholder="79002-000">
                    </div>
                    <div>
                        <label>Ambiente</label>
                        <select name="me_sandbox">
                            <option value="0" <?php echo ($cfg['me_sandbox'] ?? '1') === '0' ? 'selected' : ''; ?>>🔴 Produção</option>
                            <option value="1" <?php echo ($cfg['me_sandbox'] ?? '1') === '1' ? 'selected' : ''; ?>>🟡 Sandbox (Testes)</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:1rem;display:flex;gap:10px">
                    <button type="submit" name="save_config" class="btn">💾 Salvar Config</button>
                    <?php if ($me->isConfigured()): ?>
                    <a href="<?php echo $me->getAuthUrl(); ?>" class="btn" style="background:#e74c3c">🔗 Autorizar no Melhor Envio</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ORDERS LIST -->
        <?php if ($me->hasToken()): ?>
        <div class="me-card">
            <h3><i class="fas fa-box"></i> Pedidos - Cotar e Gerar Etiqueta</h3>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Pedido</th><th>Cliente</th><th>Destino</th><th>Valor</th><th>Status</th><th>Frete</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?php echo $o['id']; ?></td>
                        <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars(($o['dest_city'] ?: '?') . '/' . ($o['dest_state'] ?: '?')); ?> <small style="color:#888"><?php echo $o['dest_zip']; ?></small></td>
                        <td>R$ <?php echo number_format($o['total_amount'], 2, ',', '.'); ?></td>
                        <td><span class="status-badge status-<?php echo $o['status']; ?>"><?php echo $o['status']; ?></span></td>
                        <td>
                            <?php 
                            $me_id = null;
                            $tracking_code = $o['tracking_code'] ?? null;
                            try { $me_id = $pdo->query("SELECT me_order_id FROM orders WHERE id = {$o['id']}")->fetchColumn(); } catch(Exception $e) {}
                            ?>
                            <?php if (!empty($me_id)): ?>
                            <button onclick="printLabel('<?php echo $me_id; ?>')" class="btn btn-sm" style="background:#2ecc71;font-size:.75rem">🏷️ Imprimir</button>
                            <?php if (empty($tracking_code)): ?>
                            <button onclick="syncTracking(<?php echo $o['id']; ?>, '<?php echo $me_id; ?>')" class="btn btn-sm" style="background:#f39c12;font-size:.75rem" title="Buscar código de rastreio">🔄 Sync</button>
                            <?php else: ?>
                            <span style="font-size:0.75rem; color:#888; margin-left:5px;"><?php echo htmlspecialchars($tracking_code); ?></span>
                            <?php endif; ?>
                            <?php else: ?>
                            <button onclick="openQuote(<?php echo $o['id']; ?>)" class="btn btn-sm" style="background:#3498db;font-size:.75rem">📦 Cotar Frete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quote Modal -->
    <div id="quoteModal">
        <div class="modal-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h2>📦 Cotação de Frete</h2>
                <button onclick="closeQuote()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
            </div>
            <div id="quoteOrderInfo" style="background:#222;padding:10px;border-radius:6px;margin-bottom:1rem;font-size:.9rem"></div>
            <div id="quoteLoading" style="text-align:center;padding:2rem;color:#888">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem"></i><br>Calculando fretes...
            </div>
            <div id="quoteResults" style="display:none"></div>
            <div id="quoteActions" style="display:none;margin-top:1rem;text-align:right">
                <button onclick="buyShipping()" class="btn" style="background:#2ecc71;color:#000;font-size:1rem;padding:12px 24px" id="btnBuy" disabled>
                    💳 Comprar Frete e Gerar Etiqueta
                </button>
            </div>
            <div id="quoteFeedback" style="display:none;margin-top:1rem"></div>
        </div>
    </div>

    <script>
    let currentOrderId = 0;
    let selectedService = 0;

    function openQuote(orderId) {
        currentOrderId = orderId;
        selectedService = 0;
        document.getElementById('quoteModal').classList.add('active');
        document.getElementById('quoteLoading').style.display = 'block';
        document.getElementById('quoteResults').style.display = 'none';
        document.getElementById('quoteActions').style.display = 'none';
        document.getElementById('quoteFeedback').style.display = 'none';
        document.getElementById('btnBuy').disabled = true;

        fetch(`melhorenvio.php?ajax_quote=1&order_id=${orderId}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('quoteLoading').style.display = 'none';
                if (data.error || (data.quotes && data.quotes.error)) {
                    let msg = data.error || data.quotes.error;
                    if (data.quotes && data.quotes.message) msg += ": " + data.quotes.message;
                    document.getElementById('quoteResults').innerHTML = `<div style="color:#e74c3c;padding:1rem;border:1px solid #e74c3c;border-radius:8px;background:rgba(231,76,60,0.1);"><strong>Erro:</strong> ${msg}</div>`;
                    document.getElementById('quoteResults').style.display = 'block';
                    return;
                }
                
                const order = data.order;
                document.getElementById('quoteOrderInfo').innerHTML = `
                    <strong>Pedido #${orderId}</strong> - ${order.customer_name}<br>
                    <small style="color:#888">CEP destino: ${order.dest_zip || 'N/A'}</small>
                `;

                let html = '';
                const quotes = data.quotes || [];
                quotes.forEach(q => {
                    if (q.error) return;
                    const price = parseFloat(q.custom_price || q.price);
                    const days = q.custom_delivery_time || q.delivery_time;
                    const logo = q.company?.picture || '';
                    html += `
                        <div class="quote-row" onclick="selectQuote(this, ${q.id})" data-service="${q.id}">
                            ${logo ? `<img src="${logo}" class="quote-logo">` : `<span style="width:60px;text-align:center">${q.company?.name || ''}</span>`}
                            <div style="flex:1">
                                <strong>${q.name}</strong><br>
                                <span class="quote-days">${q.company?.name || ''} - ${days} dias úteis</span>
                            </div>
                            <div class="quote-price">R$ ${price.toLocaleString('pt-BR',{minimumFractionDigits:2})}</div>
                        </div>
                    `;
                });

                if (!html) html = '<div style="color:#e74c3c;padding:1rem">Nenhuma cotação disponível para este destino.</div>';
                
                document.getElementById('quoteResults').innerHTML = html;
                document.getElementById('quoteResults').style.display = 'block';
                document.getElementById('quoteActions').style.display = 'block';
            })
            .catch(err => {
                document.getElementById('quoteLoading').style.display = 'none';
                document.getElementById('quoteResults').innerHTML = `<div style="color:#e74c3c;padding:1rem">Erro na requisição: ${err.message}</div>`;
                document.getElementById('quoteResults').style.display = 'block';
            });
    }

    function selectQuote(el, serviceId) {
        document.querySelectorAll('.quote-row').forEach(r => r.classList.remove('selected'));
        el.classList.add('selected');
        selectedService = serviceId;
        document.getElementById('btnBuy').disabled = false;
    }

    function closeQuote() {
        document.getElementById('quoteModal').classList.remove('active');
    }

    function buyShipping() {
        if (!selectedService || !currentOrderId) return;
        if (!confirm('Confirma a compra deste frete? O valor será debitado do seu saldo Melhor Envio.')) return;

        const btn = document.getElementById('btnBuy');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

        const fd = new FormData();
        fd.append('ajax_buy', '1');
        fd.append('order_id', currentOrderId);
        fd.append('service_id', selectedService);

        fetch('melhorenvio.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(data => {
                const fb = document.getElementById('quoteFeedback');
                fb.style.display = 'block';
                if (data.success) {
                    fb.innerHTML = `<div style="background:rgba(46,204,113,.1);border:1px solid #2ecc71;padding:15px;border-radius:8px;color:#2ecc71">
                        ✅ Frete comprado com sucesso!<br>
                        <small>ID Melhor Envio: ${data.me_order_id}</small><br>
                        <button onclick="printLabel('${data.me_order_id}')" class="btn" style="margin-top:10px;background:#f1c40f;color:#000">🏷️ Imprimir Etiqueta</button>
                        <button onclick="location.reload()" class="btn btn-secondary" style="margin-top:10px">Fechar</button>
                    </div>`;
                } else {
                    fb.innerHTML = `<div style="background:rgba(231,76,60,.1);border:1px solid #e74c3c;padding:15px;border-radius:8px;color:#e74c3c">
                        ❌ Erro ao comprar frete<br><pre style="font-size:.75rem;max-height:200px;overflow:auto">${JSON.stringify(data.error, null, 2)}</pre>
                    </div>`;
                    btn.disabled = false;
                    btn.innerHTML = '💳 Comprar Frete e Gerar Etiqueta';
                }
            })
            .catch(err => {
                alert('Erro: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '💳 Comprar Frete e Gerar Etiqueta';
            });
    }

    function printLabel(meOrderId) {
        fetch(`melhorenvio.php?print_label=${meOrderId}`)
            .then(r => r.json())
            .then(data => {
                if (data.url) {
                    window.open(data.url, '_blank');
                } else {
                    alert('Etiqueta ainda processando. Tente novamente em alguns segundos.');
                    console.log(data);
                }
            });
    }

    function syncTracking(orderId, meId) {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch(`melhorenvio.php?ajax_sync=1&order_id=${orderId}&me_id=${meId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Rastreio atualizado: ' + data.tracking);
                    location.reload();
                } else {
                    alert('Ainda não disponível ou erro: ' + (data.error || 'Tente novamente mais tarde.'));
                    btn.disabled = false;
                    btn.innerHTML = orig;
                }
            })
            .catch(err => {
                alert('Erro: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = orig;
            });
    }
    </script>
</body>
</html>
