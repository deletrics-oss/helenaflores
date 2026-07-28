<?php
// catalogo/admin/rma.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/melhorenvio.php';
isAdmin();

// --- 1. SETUP DB TABELA RMA ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rma_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('garantia', 'devolucao', 'promessa') DEFAULT 'garantia',
        customer_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        document VARCHAR(20) NULL,
        phone VARCHAR(20) NULL,
        address VARCHAR(255) NULL,
        number VARCHAR(20) NULL,
        complement VARCHAR(100) NULL,
        neighborhood VARCHAR(100) NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(2) NULL,
        zipcode VARCHAR(10) NULL,
        product_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        issue_type VARCHAR(100) NULL,
        issue_desc TEXT NULL,
        preferred_action ENUM('enviar_peca', 'trazer_loja', 'outros') DEFAULT 'enviar_peca',
        status ENUM('pending', 'shipped', 'received', 'resolved') DEFAULT 'pending',
        source ENUM('admin', 'customer') DEFAULT 'admin',
        me_order_id VARCHAR(255) NULL,
        tracking_code VARCHAR(100) NULL,
        qty_returned INT DEFAULT 0,
        marketplace VARCHAR(50) NULL,
        request_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL
    )");
    
    // Migrations
    try { $pdo->exec("ALTER TABLE rma_tickets MODIFY COLUMN type ENUM('garantia', 'devolucao', 'promessa') DEFAULT 'garantia'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN preferred_action ENUM('enviar_peca', 'trazer_loja', 'outros') DEFAULT 'enviar_peca' AFTER issue_desc"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN marketplace VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN request_date DATE DEFAULT NULL"); } catch(Exception $e) {}
} catch (Exception $e) {}

$me = new MelhorEnvioAPI($pdo);
$msg = '';

// --- 2. AJAX ENDPOINTS ---
if (isset($_POST['ajax_ai'])) {
    header('Content-Type: application/json');
    $text = $_POST['text'] ?? '';
    $res = [];
    
    // Improved Doc Extraction (CPF/CNPJ)
    if(preg_match('/([\d]{3}\.?[\d]{3}\.?[\d]{3}-?[\d]{2}|[\d]{2}\.?[\d]{3}\.?[\d]{3}\/?[\d]{4}-?[\d]{2})/', $text, $m)) {
        $res['document'] = preg_replace('/\D/', '', $m[1]);
    }
    
    // Improved CEP Extraction
    if(preg_match('/([\d]{5}-?[\d]{3})/', $text, $m)) {
        $res['zipcode'] = preg_replace('/\D/', '', $m[1]);
    }
    
    // Improved Phone Extraction (BR format)
    // Matches 10 or 11 digits, but skips if it's a substring of the document
    if(preg_match('/(?:\(?\d{2}\)?\s?)?(?:9\s?)?\d{4,5}[-\s]?\d{4}/', $text, $m)) {
        $cleanPhone = preg_replace('/\D/', '', $m[0]);
        $isDoc = false;
        if (isset($res['document'])) {
            if (strpos($res['document'], $cleanPhone) !== false || strpos($cleanPhone, $res['document']) !== false) {
                $isDoc = true;
            }
        }
        if (!$isDoc) {
            $res['phone'] = $cleanPhone;
        }
    }

    // New: Extraction of Number and Complement (e.g. 682a or número 09)
    if(preg_match('/(?:n[º°.]?\s*|número\s*)(\d+)\s*([a-zA-Z]?)/i', $text, $m)) {
        $res['number'] = $m[1];
        if(!empty($m[2])) $res['complement'] = $m[2];
    } elseif(preg_match('/,?\s*(\d+)\s*([a-zA-Z])(?:\s|,|$)/i', $text, $m)) {
    } elseif(preg_match('/,?\s*(\d+)(?:\s|,|$)/', $text, $m)) {
        // Matches "Rua Tal, 123"
        $res['number'] = $m[1];
    }
    
    // New: Name Extraction (Usually the first line or a capitalized string at start)
    $lines = explode("\n", str_replace("\r", "", $text));
    foreach($lines as $line) {
        $line = trim($line);
        if(empty($line)) continue;
        // If line has 2+ words and no obvious address/zip/cpf keywords, it might be a name
        if(str_word_count($line) >= 2 && !preg_match('/(?:cep|rua|av|bairro|n[º°.]|cpf|cnpj|ms|mg|sp|rj|pr|rs)/i', $line)) {
            $res['name'] = $line;
            break;
        }
    }

    echo json_encode($res);
    exit;
}

if (isset($_GET['ajax_quote_rma'])) {
    header('Content-Type: application/json');
    $toZip = $_GET['zipcode'] ?? '';
    $w = (int)($_GET['width'] ?? 15);
    $h = (int)($_GET['height'] ?? 4);
    $l = (int)($_GET['length'] ?? 20);
    $weight = (float)($_GET['weight'] ?? 0.3);
    $val = (float)($_GET['value'] ?? 50.0);
    $fromZip = '03611060'; // Daniel Souza - SP (Tatuapé)
    try { 
        $dbZip = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'me_from_zipcode'")->fetchColumn();
        if ($dbZip && strlen(preg_replace('/\D/', '', $dbZip)) === 8) {
            $fromZip = preg_replace('/\D/', '', $dbZip);
        }
    } catch(Exception $e) {}
    $finalH_quote = $h;
    $products = [['id'=>'rma','width'=>$w,'height'=>$finalH_quote,'length'=>$l,'weight'=>$weight,'insurance_value'=>$val,'quantity'=>1]];
    $result = $me->calculateShipping($fromZip, $toZip, $products);
    echo json_encode(['quotes' => $result]);
    exit;
}

// --- 3. HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_rma'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO rma_tickets (type, customer_name, document, phone, address, number, complement, neighborhood, city, state, zipcode, product_name, issue_type, issue_desc, preferred_action, marketplace, request_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['type'], $_POST['customer_name'], preg_replace('/\D/', '', $_POST['document']), preg_replace('/\D/', '', $_POST['phone']),
            $_POST['address'], $_POST['number'], $_POST['complement'], $_POST['neighborhood'], $_POST['city'], $_POST['state'], preg_replace('/\D/', '', $_POST['zipcode']),
            $_POST['product_name'], $_POST['issue_type'] ?? 'Outro', $_POST['issue_desc'], $_POST['preferred_action'],
            $_POST['marketplace'] ?? '', $_POST['request_date'] ?? date('Y-m-d')
        ]);
        $rma_id = $pdo->lastInsertId();
        $msg = '<div class="alert alert-success">✅ Ocorrência RMA #'.$rma_id.' criada com sucesso!</div>';
        
        // Label logic only if service is selected
        if (!empty($_POST['selected_service'])) {
            $serviceId = (int) $_POST['selected_service'];
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
            $w = (int)($_POST['width'] ?? 15);
            $h = (int)($_POST['height'] ?? 10);
            $l = (int)($_POST['length'] ?? 20);
            $weight = (float)($_POST['weight'] ?? 0.3);
            $val = (float)($_POST['value'] ?? 50.0);
            
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
                    'number' => $storeNumber, 
                    'district' => $storeDistrict, 
                    'city' => $storeCity, 
                    'state_abbr' => $storeState, 
                    'country_id' => 'BR', 
                    'postal_code' => $fromZip
                ],
                'to' => [ 
                    'name' => $_POST['customer_name'], 
                    'phone' => preg_replace('/\D/', '', $_POST['phone']), 
                    'email' => !empty($_POST['email']) ? $_POST['email'] : 'cliente@fightarcade.temp', 
                    'document' => (strlen(preg_replace('/\D/', '', $_POST['document'])) <= 11) ? preg_replace('/\D/', '', $_POST['document']) : '',
                    'company_document' => (strlen(preg_replace('/\D/', '', $_POST['document'])) > 11) ? preg_replace('/\D/', '', $_POST['document']) : '',
                    'address' => $_POST['address'], 
                    'complement' => $_POST['complement'], 
                    'number' => $_POST['number'], 
                    'district' => $_POST['neighborhood'], 
                    'city' => $_POST['city'], 
                    'state_abbr' => $_POST['state'], 
                    'country_id' => 'BR', 
                    'postal_code' => preg_replace('/\D/', '', $_POST['zipcode']) 
                ],
                'products' => [[ 'name' => 'RMA - ' . $_POST['product_name'], 'quantity' => 1, 'unitary_value' => $val ]],
                'volumes' => [[ 'height' => $h, 'width' => $w, 'length' => $l, 'weight' => $weight ]],
                'options' => [ 'insurance_value' => $val, 'receipt' => false, 'own_hand' => false, 'non_commercial' => true ]
            ];
            $cartResult = $me->addToCart($cartPayload);
            if (isset($cartResult['id'])) {
                // Try to checkout and generate label (requires balance)
                $payResult = $me->checkout([$cartResult['id']]);
                if (isset($payResult['purchase']) || (isset($payResult[0]) && !isset($payResult['error']))) {
                    $lblResult = $me->generateLabel([$cartResult['id']]);
                    $pdo->prepare("UPDATE rma_tickets SET me_order_id = ?, tracking_code = ?, status = 'shipped' WHERE id = ?")->execute([$cartResult['id'], $lblResult['tracking'] ?? '', $rma_id]);
                    $msg .= '<div class="alert alert-success">🏷️ Etiqueta gerada com sucesso!</div>';
                } else {
                    // Just added to cart, no balance to pay
                    $pdo->prepare("UPDATE rma_tickets SET me_order_id = ? WHERE id = ?")->execute([$cartResult['id'], $rma_id]);
                    $msg .= '<div class="alert alert-warning">🛒 <strong>Enviado para o Carrinho!</strong> Como você está sem saldo, pague o frete manualmente no site do Melhor Envio e depois sincronize o pedido. <a href="https://melhorenvio.com.br/carrinho" target="_blank" style="color:inherit; font-weight:bold;">[Ir para o Carrinho]</a></div>';
                }
            } else {
                $envInfo = ($me->getSetting('me_sandbox') === '1') ? 'MODO SANDBOX (TESTE)' : 'MODO PRODUÇÃO (REAL)';
                $errDetail = is_array($cartResult) ? json_encode($cartResult) : $cartResult;
                $msg .= '<div class="alert alert-error">❌ Erro Melhor Envio (' . $envInfo . '): ' . $errDetail . '<br><small>Origem: ' . $fromZip . '</small></div>';
            }
        }
    } catch(Exception $e) { $msg = '<div class="alert alert-error">❌ Erro: '.$e->getMessage().'</div>'; }
}

// Handle Update RMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rma'])) {
    $rid = (int)$_POST['rma_id'];
    try {
        $stmt = $pdo->prepare("UPDATE rma_tickets SET type=?, customer_name=?, document=?, phone=?, address=?, number=?, complement=?, neighborhood=?, city=?, state=?, zipcode=?, product_name=?, marketplace=?, request_date=? WHERE id=?");
        $stmt->execute([
            $_POST['type'], $_POST['customer_name'], preg_replace('/\D/', '', $_POST['document']), preg_replace('/\D/', '', $_POST['phone']),
            $_POST['address'], $_POST['number'], $_POST['complement'], $_POST['neighborhood'], $_POST['city'], $_POST['state'], preg_replace('/\D/', '', $_POST['zipcode']),
            $_POST['product_name'], $_POST['marketplace'] ?? '', $_POST['request_date'] ?? date('Y-m-d'), $rid
        ]);
        $msg = '<div class="alert alert-success">✅ Ocorrência #'.$rid.' atualizada!</div>';
    } catch(Exception $e) { $msg = '<div class="alert alert-error">❌ Erro: '.$e->getMessage().'</div>'; }
}

// Handle Sync RMA Tracking
if (isset($_GET['sync_rma']) && isset($_GET['id']) && isset($_GET['me_id'])) {
    $tid = (int)$_GET['id'];
    $me_id = $_GET['me_id'];
    $res = $me->tracking([$me_id]);
    if (isset($res[$me_id]['tracking'])) {
        $track = $res[$me_id]['tracking'];
        $pdo->prepare("UPDATE rma_tickets SET tracking_code = ?, status = 'shipped' WHERE id = ?")->execute([$track, $tid]);
        $msg = '<div class="alert alert-success">✅ Sincronizado! Rastreio: <strong>' . $track . '</strong></div>';
    } else {
        $msg = '<div class="alert alert-warning">⚠️ Ainda não pago ou sem rastreio disponível no Melhor Envio.</div>';
    }
}

// Bulk Actions
if (isset($_POST['bulk_action']) && !empty($_POST['selected_ids'])) {
    $ids = implode(',', array_map('intval', $_POST['selected_ids']));
    $action = $_POST['bulk_action'];
    if ($action === 'delete') $pdo->query("DELETE FROM rma_tickets WHERE id IN ($ids)");
    elseif ($action === 'resolved') $pdo->query("UPDATE rma_tickets SET status = 'resolved', resolved_at = NOW() WHERE id IN ($ids)");
    elseif ($action === 'pending') $pdo->query("UPDATE rma_tickets SET status = 'pending' WHERE id IN ($ids)");
    $msg = '<div class="alert alert-success">✅ Ação aplicada com sucesso!</div>';
}

// Mark Status (Received/Resolved/Pending)
if (isset($_GET['mark_status']) && isset($_GET['id'])) {
    $st = $_GET['mark_status'];
    $tid = (int)$_GET['id'];
    if ($st === 'resolved') $pdo->query("UPDATE rma_tickets SET status = 'resolved', resolved_at = NOW() WHERE id = $tid");
    elseif ($st === 'received') $pdo->query("UPDATE rma_tickets SET status = 'received' WHERE id = $tid");
    elseif ($st === 'pending') $pdo->query("UPDATE rma_tickets SET status = 'pending' WHERE id = $tid");
    header("Location: rma.php");
    exit;
}

// Fetch for Edit or Clone
$edit_data = null;
if (isset($_GET['edit']) || isset($_GET['clone'])) {
    $tid = (int)($_GET['edit'] ?? $_GET['clone']);
    $stmt = $pdo->prepare("SELECT * FROM rma_tickets WHERE id = ?");
    $stmt->execute([$tid]);
    $edit_data = $stmt->fetch();
    // If cloning, we don't want the ID or old shipping info
    if (isset($_GET['clone']) && $edit_data) {
        unset($edit_data['id']);
        unset($edit_data['me_order_id']);
        unset($edit_data['tracking_code']);
        $edit_data['status'] = 'pending';
        $edit_data['request_date'] = date('Y-m-d');
    }
}

// Handle Update (if editing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rma'])) {
    $tid = (int)$_POST['rma_id'];
    try {
        $stmt = $pdo->prepare("UPDATE rma_tickets SET type=?, customer_name=?, document=?, phone=?, address=?, number=?, complement=?, neighborhood=?, city=?, state=?, zipcode=?, product_name=?, issue_type=?, issue_desc=?, preferred_action=?, marketplace=?, request_date=? WHERE id=?");
        $stmt->execute([
            $_POST['type'], $_POST['customer_name'], preg_replace('/\D/', '', $_POST['document']), preg_replace('/\D/', '', $_POST['phone']),
            $_POST['address'], $_POST['number'], $_POST['complement'], $_POST['neighborhood'], $_POST['city'], $_POST['state'], preg_replace('/\D/', '', $_POST['zipcode']),
            $_POST['product_name'], $_POST['issue_type'] ?? 'Outro', $_POST['issue_desc'], $_POST['preferred_action'],
            $_POST['marketplace'] ?? '', $_POST['request_date'] ?? date('Y-m-d'), $tid
        ]);
        $msg = '<div class="alert alert-success">✅ RMA #'.$tid.' atualizado com sucesso!</div>';
        $edit_data = null; // Clear edit mode
    } catch(Exception $e) { $msg = '<div class="alert alert-error">❌ Erro ao atualizar: '.$e->getMessage().'</div>'; }
}

// Sync RMA with Melhor Envio
if (isset($_GET['sync_rma']) && isset($_GET['id'])) {
    $tid = (int)$_GET['id'];
    $me_id = $_GET['me_id'];
    $res = $me->tracking([$me_id]);
    if (isset($res[$me_id]['tracking'])) {
        $track = $res[$me_id]['tracking'];
        $pdo->prepare("UPDATE rma_tickets SET tracking_code = ?, status = 'shipped' WHERE id = ?")->execute([$track, $tid]);
        $msg = '<div class="alert alert-success">🔄 Sincronizado! Código de rastreio: '.$track.'</div>';
    } else {
        $msg = '<div class="alert alert-warning">⏳ Ainda não pago ou rastreio não disponível no Melhor Envio.</div>';
    }
}

$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM rma_tickets")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM rma_tickets WHERE status = 'pending'")->fetchColumn(),
    'resolved' => $pdo->query("SELECT COUNT(*) FROM rma_tickets WHERE status = 'resolved'")->fetchColumn(),
    'this_month' => $pdo->query("SELECT COUNT(*) FROM rma_tickets WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn(),
];
$tickets = $pdo->query("SELECT * FROM rma_tickets ORDER BY CASE WHEN status IN ('pending','shipped') THEN 1 ELSE 2 END, created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMA & Pós-Venda | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --bg: #0b0e14; --surface: #141820; --border: #252d3d; --primary: #f1c40f; --text: #e8eaf0; --muted: #5a6478; --radius: 12px; --red: #e74c3c; --green: #2ecc71; --blue: #3498db; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        .glass-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.5rem; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-box { background: var(--surface); border-radius: 10px; padding: 1.2rem; border-left: 4px solid var(--primary); }
        .stat-val { font-size: 1.8rem; font-weight: 800; margin: 5px 0; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.75rem; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; font-weight: 700; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; background: #0d1017; border: 1px solid var(--border); color: #fff; border-radius: 8px; outline: none; }
        .form-group input:focus { border-color: var(--primary); }
        
        .ai-box { background: rgba(241, 196, 15, 0.05); border: 1px dashed var(--primary); border-radius: 10px; padding: 1.2rem; margin-bottom: 2rem; }
        .ai-box h4 { margin: 0 0 8px; font-size: 0.9rem; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-pending { background: rgba(243, 156, 18, 0.1); color: #f39c12; }
        .status-shipped { background: rgba(52, 152, 219, 0.1); color: #3498db; }
        .status-resolved { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; font-size: 0.7rem; color: var(--muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 12px; border-bottom: 1px solid #1a1e26; vertical-align: middle; }
        
        .btn-premium { background: var(--primary); color: #000; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.2s; }
        .btn-premium:hover { transform: scale(1.02); background: #d4ac0d; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h1 style="margin:0;"><i class="fas fa-hand-holding-heart" style="color:var(--primary)"></i> RMA & Pós-Venda</h1>
                <p style="color:var(--muted); margin:5px 0 0;">Gestão de ocorrências e devoluções.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </div>

        <?php echo $msg; ?>

        <div class="stat-grid">
            <div class="stat-box"><div>Total</div><div class="stat-val"><?php echo $stats['total']; ?></div></div>
            <div class="stat-box" style="border-color:var(--red)"><div>Pendentes</div><div class="stat-val"><?php echo $stats['pending']; ?></div></div>
            <div class="stat-box" style="border-color:var(--green)"><div>Resolvidos</div><div class="stat-val"><?php echo $stats['resolved']; ?></div></div>
            <div class="stat-box" style="border-color:var(--blue)"><div>Neste Mês</div><div class="stat-val"><?php echo $stats['this_month']; ?></div></div>
        </div>

        <div class="glass-card">
            <h3><?php echo $edit_data ? '📝 Editar Ocorrência #'.$edit_data['id'] : '➕ Nova Ocorrência'; ?></h3>
            
            <?php if (!$edit_data): ?>
            <div class="ai-box">
                <h4><i class="fas fa-magic"></i> Assistente IA (Opcional)</h4>
                <p style="font-size:0.8rem; color:var(--muted); margin-bottom:12px;">Cole a mensagem do cliente para extrair endereço.</p>
                <div style="display:flex; gap:10px;">
                    <textarea id="ai_text" rows="1" style="flex:1; background:#000; border:1px solid #333; color:#fff; padding:10px; border-radius:6px;" placeholder="Ex: Meu CEP é 00000..."></textarea>
                    <button type="button" onclick="runAI()" class="btn btn-sm" style="background:var(--primary); color:#000; font-weight:800; padding:0 20px;">EXTRAIR</button>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="rmaForm">
                <?php if ($edit_data && isset($edit_data['id'])): ?>
                    <input type="hidden" name="rma_id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group"><label>Tipo de Ticket</label><select name="type">
                        <option value="garantia" <?php echo ($edit_data && $edit_data['type']=='garantia') ? 'selected' : ''; ?>>Garantia</option>
                        <option value="devolucao" <?php echo ($edit_data && $edit_data['type']=='devolucao') ? 'selected' : ''; ?>>Devolução</option>
                        <option value="promessa" <?php echo ($edit_data && $edit_data['type']=='promessa') ? 'selected' : ''; ?>>🎁 Promessa / Brinde</option>
                    </select></div>
                    <div class="form-group"><label>Nome do Cliente</label><input type="text" name="customer_name" id="f_name" required value="<?php echo htmlspecialchars($edit_data['customer_name'] ?? ''); ?>"></div>
                    <div class="form-group"><label>CPF/CNPJ</label><input type="text" name="document" id="f_doc" required value="<?php echo htmlspecialchars($edit_data['document'] ?? ''); ?>"></div>
                    <div class="form-group"><label>WhatsApp/Telefone</label><input type="text" name="phone" id="f_phone" value="<?php echo htmlspecialchars($edit_data['phone'] ?? ''); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>CEP</label><input type="text" name="zipcode" id="f_zip" required onblur="fetchCep()" value="<?php echo htmlspecialchars($edit_data['zipcode'] ?? ''); ?>"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Endereço</label><input type="text" name="address" id="f_addr" required value="<?php echo htmlspecialchars($edit_data['address'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Número</label><input type="text" name="number" id="f_num" required value="<?php echo htmlspecialchars($edit_data['number'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Complemento</label><input type="text" name="complement" id="f_complement" value="<?php echo htmlspecialchars($edit_data['complement'] ?? ''); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Bairro</label><input type="text" name="neighborhood" id="f_bairro" value="<?php echo htmlspecialchars($edit_data['neighborhood'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Cidade</label><input type="text" name="city" id="f_city" value="<?php echo htmlspecialchars($edit_data['city'] ?? ''); ?>"></div>
                    <div class="form-group"><label>UF</label><input type="text" name="state" id="f_uf" value="<?php echo htmlspecialchars($edit_data['state'] ?? ''); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Marketplace / Origem</label><input type="text" name="marketplace" placeholder="Ex: Shopee Deletrics, Mercado Livre..." value="<?php echo htmlspecialchars($edit_data['marketplace'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Data da Solicitação</label><input type="date" name="request_date" value="<?php echo $edit_data['request_date'] ?? date('Y-m-d'); ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Produto / Peça</label><input type="text" name="product_name" required value="<?php echo htmlspecialchars($edit_data['product_name'] ?? ''); ?>"></div>
                    <div class="form-group">
                        <label>Ação / Logística</label>
                        <select name="preferred_action" id="f_action" onchange="toggleShippingArea()">
                            <option value="enviar_peca" <?php echo ($edit_data && $edit_data['preferred_action']=='enviar_peca') ? 'selected' : ''; ?>>📦 Enviar Peça (Frete)</option>
                            <option value="trazer_loja" <?php echo ($edit_data && $edit_data['preferred_action']=='trazer_loja') ? 'selected' : ''; ?>>🏪 Trazer à Loja / Retirada</option>
                            <option value="outros" <?php echo ($edit_data && $edit_data['preferred_action']=='outros') ? 'selected' : ''; ?>>❓ Outro / Manual</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Motivo do RMA</label>
                    <select name="issue_type">
                        <?php $it = $edit_data['issue_type'] ?? ''; ?>
                        <option value="Defeito de Fábrica" <?php echo $it=='Defeito de Fábrica' ? 'selected' : ''; ?>>Defeito de Fábrica</option>
                        <option value="Falta de Item" <?php echo $it=='Falta de Item' ? 'selected' : ''; ?>>Falta de Item</option>
                        <option value="Item Errado" <?php echo $it=='Item Errado' ? 'selected' : ''; ?>>Item Errado</option>
                        <option value="Arrependimento" <?php echo $it=='Arrependimento' ? 'selected' : ''; ?>>Arrependimento</option>
                        <option value="Brinde / Promessa" <?php echo $it=='Brinde / Promessa' ? 'selected' : ''; ?>>Brinde / Promessa</option>
                    </select>
                </div>
                <div class="form-group"><label>Observações Detalhadas</label><textarea name="issue_desc" rows="2"><?php echo htmlspecialchars($edit_data['issue_desc'] ?? ''); ?></textarea></div>
                
                <div id="shippingArea" style="background:rgba(0,0,0,0.15); padding:1.5rem; border-radius:10px; margin-top:1rem; border:1px solid var(--border); <?php echo ($edit_data && $edit_data['preferred_action'] !== 'enviar_peca') ? 'display:none;' : ''; ?>">
                    <label style="display:block; margin-bottom:12px; font-weight:800; font-size:0.75rem; color:var(--primary);"><i class="fas fa-truck"></i> Melhor Envio (Opcional)</label>
                    <div class="form-grid" style="grid-template-columns: repeat(5, 1fr);">
                        <div class="form-group"><label>Peso</label><input type="text" name="weight" id="f_wgt" value="0.3"></div>
                        <div class="form-group"><label>Altura</label><input type="text" name="height" id="f_h" value="4"></div>
                        <div class="form-group"><label>Larg.</label><input type="text" name="width" id="f_w" value="11"></div>
                        <div class="form-group"><label>Comp.</label><input type="text" name="length" id="f_l" value="16"></div>
                        <div class="form-group"><label>Valor</label><input type="text" name="value" id="f_val" value="50.00"></div>
                    </div>
                    <button type="button" onclick="quoteRma()" class="btn btn-sm" style="width:100%; background:var(--blue); color:#fff; font-weight:800; padding:10px; border-radius:6px;">COTAR FRETE AGORA</button>
                    <div id="quoteArea" style="margin-top:1rem; display:none;"><div id="quoteResults"></div><input type="hidden" name="selected_service" id="selected_service"></div>
                </div>

                <div style="text-align:right; margin-top:2rem; display:flex; gap:10px; justify-content:flex-end;">
                    <?php if ($edit_data && isset($edit_data['id'])): ?>
                        <a href="rma.php" class="btn btn-secondary">CANCELAR</a>
                        <button type="submit" name="update_rma" class="btn-premium">💾 ATUALIZAR OCORRÊNCIA</button>
                    <?php else: ?>
                        <button type="submit" name="create_rma" class="btn-premium">💾 SALVAR OCORRÊNCIA</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="glass-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3>📋 Histórico Recente</h3>
                <form method="POST" id="bulkForm" style="display:flex; gap:10px; align-items:center;">
                    <select name="bulk_action" style="background:#222; color:#fff; border:1px solid #444; border-radius:4px; padding:5px;">
                        <option value="">Ações em Massa</option>
                        <option value="resolved">Marcar Resolvido</option>
                        <option value="pending">Marcar Pendente</option>
                        <option value="delete">Excluir Selecionados</option>
                    </select>
                    <button type="submit" class="btn btn-sm" style="background:var(--primary); color:#000;">Aplicar</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr>
                        <th><input type="checkbox" onclick="toggleAll(this)"></th>
                        <th>ID</th><th>Cliente</th><th>Produto</th><th>Logística</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach($tickets as $t): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo $t['id']; ?>"></td>
                            <td>#<?php echo $t['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($t['customer_name']); ?></strong><br>
                                <small style="color:var(--primary)"><?php echo $t['marketplace'] ?: 'Venda Direta'; ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($t['product_name']); ?><br>
                                <small style="color:var(--muted)"><?php echo $t['request_date'] ? date('d/m/Y', strtotime($t['request_date'])) : 'N/A'; ?></small>
                            </td>
                            <td><span class="badge" style="background:#222;"><?php echo $t['preferred_action'] === 'enviar_peca' ? 'Frete' : ($t['preferred_action'] === 'trazer_loja' ? 'Retirada' : 'Manual'); ?></span></td>
                            <td>
                                <select onchange="location.href='?mark_status='+this.value+'&id=<?php echo $t['id']; ?>'" 
                                        class="badge" style="background:rgba(0,0,0,0.3); color:inherit; border:1px solid rgba(255,255,255,0.1); cursor:pointer;">
                                    <option value="pending" <?php echo $t['status']=='pending' ? 'selected' : ''; ?>>PENDENTE</option>
                                    <option value="shipped" <?php echo $t['status']=='shipped' ? 'selected' : ''; ?>>ENVIADO</option>
                                    <option value="received" <?php echo $t['status']=='received' ? 'selected' : ''; ?>>RECEBIDO</option>
                                    <option value="resolved" <?php echo $t['status']=='resolved' ? 'selected' : ''; ?>>RESOLVIDO</option>
                                </select>
                            </td>
                            <td style="display:flex; gap:5px; flex-wrap:wrap;">
                                <a href="?edit=<?php echo $t['id']; ?>" class="btn btn-sm" style="background:var(--blue); color:#fff;" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="?clone=<?php echo $t['id']; ?>" class="btn btn-sm" style="background:var(--muted); color:#fff;" title="Clonar"><i class="fas fa-copy"></i></a>
                                
                                <?php if($t['me_order_id']): ?>
                                    <?php if(!$t['tracking_code']): ?>
                                        <a href="?sync_rma=1&id=<?php echo $t['id']; ?>&me_id=<?php echo $t['me_order_id']; ?>" class="btn btn-sm" style="background:#e67e22; color:#fff; font-size:0.65rem; padding:4px 8px;" title="No Carrinho (Sincronizar)">
                                            <i class="fas fa-shopping-cart"></i> SINC
                                        </a>
                                    <?php else: ?>
                                        <button type="button" onclick="printLabel('<?php echo $t['me_order_id']; ?>')" class="btn btn-sm" style="background:var(--primary); color:#000;" title="Etiqueta Melhor Envio"><i class="fas fa-shipping-fast"></i></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="rma-print.php?id=<?php echo $t['id']; ?>&type=label" target="_blank" class="btn btn-sm" style="background:#555; color:#fff;" title="Etiqueta Manual"><i class="fas fa-print"></i></a>
                                <a href="rma-print.php?id=<?php echo $t['id']; ?>&type=declaration" target="_blank" class="btn btn-sm" style="background:#555; color:#fff;" title="Declaração de Conteúdo"><i class="fas fa-file-invoice"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleShippingArea() {
        const action = document.getElementById('f_action').value;
        const area = document.getElementById('shippingArea');
        area.style.display = (action === 'enviar_peca') ? 'block' : 'none';
    }
    function runAI() {
        const txt = document.getElementById('ai_text').value;
        if(!txt) return;
        const fd = new FormData(); fd.append('ajax_ai', '1'); fd.append('text', txt);
        fetch('rma.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=>{
            if(d.name) document.getElementById('f_name').value = d.name;
            if(d.document) document.getElementById('f_doc').value = d.document;
            if(d.phone) document.getElementById('f_phone').value = d.phone;
            if(d.number) document.getElementById('f_num').value = d.number;
            if(d.complement) document.getElementById('f_complement').value = d.complement;
            if(d.zipcode) { document.getElementById('f_zip').value = d.zipcode; fetchCep(); }
        });
    }
    function fetchCep() {
        let zipField = document.getElementById('f_zip');
        let cep = zipField.value.replace(/\D/g, '');
        if (cep.length === 8) {
            zipField.style.borderColor = 'var(--primary)';
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(res => res.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('f_addr').value = data.logradouro;
                        document.getElementById('f_bairro').value = data.bairro;
                        document.getElementById('f_city').value = data.localidade;
                        document.getElementById('f_uf').value = data.uf;
                        zipField.style.borderColor = 'var(--border)';
                    } else {
                        zipField.style.borderColor = 'var(--red)';
                    }
                })
                .catch(() => { zipField.style.borderColor = 'var(--red)'; });
        }
    }
    function toggleAll(el) {
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = el.checked);
    }
    function quoteRma() {
        const zip = document.getElementById('f_zip').value;
        if(!zip) return alert('CEP obrigatório!');
        document.getElementById('quoteArea').style.display = 'block';
        const params = new URLSearchParams({ ajax_quote_rma: '1', zipcode: zip, weight: document.getElementById('f_wgt').value, height: document.getElementById('f_h').value, width: document.getElementById('f_w').value, length: document.getElementById('f_l').value, value: document.getElementById('f_val').value });
        fetch('rma.php?' + params.toString()).then(r=>r.json()).then(data => {
            let html = '';
            (data.quotes || []).forEach(q => {
                if (q.error) return;
                html += `<div style="padding:10px; border:1px solid #333; margin-bottom:5px; cursor:pointer; border-radius:6px;" onclick="selectService(this, ${q.id})"><strong>${q.name}</strong> - R$ ${parseFloat(q.price).toFixed(2)}</div>`;
            });
            document.getElementById('quoteResults').innerHTML = html || 'Nenhuma cotação.';
        });
    }
    function selectService(el, id) {
        document.querySelectorAll('#quoteResults div').forEach(r => r.style.borderColor = '#333');
        el.style.borderColor = 'var(--primary)';
        document.getElementById('selected_service').value = id;
        
        // Update button text to be clear
        const btn = document.querySelector('button[name="create_rma"]');
        if(btn) btn.innerHTML = '💾 SALVAR E GERAR ETIQUETA';
    }
    function printLabel(id) {
        fetch('melhorenvio.php?print_label=' + id).then(r=>r.json()).then(d=>{
            if(d.url) window.open(d.url, '_blank');
            else alert('Erro ao gerar link de impressão. Verifique se o pedido já foi pago e sincronizado.');
        });
    }
    window.onload = toggleShippingArea;
    </script>
</body>
</html>
