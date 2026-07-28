require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/melhorenvio.php';
require_once __DIR__ . '/includes/lalamove.php';

$notif = new NotificationService($pdo);
$me = new MelhorEnvioAPI($pdo);
$llm = new LalamoveAPI($pdo);

echo "--- INICIANDO WORKER DE NOTIFICAÇÕES ---\n";
echo "Data: " . date('d/m/Y H:i:s') . "\n";

// 4. MONITORAMENTO DE RASTREIO (Melhor Envio)
echo "Verificando rastreios Melhor Envio...\n";
if ($me->hasToken()) {
    // Pegar pedidos e RMAs que não estão entregues nem cancelados
    $stmt = $pdo->query("
        (SELECT id, me_order_id, last_tracking_status, 'order' as origin FROM orders WHERE me_order_id IS NOT NULL AND status NOT IN ('delivered','cancelled') AND shipping_method != 'Lalamove' AND wa_notify_tracking = 1 LIMIT 15)
        UNION
        (SELECT id, me_order_id, me_status as last_tracking_status, 'rma' as origin FROM rma_tickets WHERE me_order_id IS NOT NULL AND status NOT IN ('resolved') AND wa_notify_tracking = 1 LIMIT 15)
    ");
    $activeItems = $stmt->fetchAll();
    
    if ($activeItems) {
        $meIds = array_map(fn($i) => $i['me_order_id'], $activeItems);
        $trackData = $me->tracking($meIds);
        
        foreach ($activeItems as $item) {
            $meId = $item['me_order_id'];
            if (isset($trackData[$meId])) {
                $status = $trackData[$meId]['status'] ?? '';
                if ($status && $status != $item['last_tracking_status']) {
                    echo "Mudança de status [{$item['origin']}] #{$item['id']}: {$item['last_tracking_status']} -> $status\n";
                    
                    // Notificar cliente se for algo relevante
                    if ($item['origin'] == 'order') {
                        $user = $pdo->query("SELECT name, phone FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ".(int)$item['id'])->fetch();
                    } else {
                        $user = $pdo->query("SELECT customer_name as name, phone FROM rma_tickets WHERE id = ".(int)$item['id'])->fetch();
                    }

                    if ($user && !empty($user['phone'])) {
                        $msg = "";
                        if ($item['origin'] == 'order') {
                            if ($status == 'posted') $msg = "Olá *{$user['name']}*! Seu pedido *#{$item['id']}* foi postado e já está a caminho! 🚚";
                            if ($status == 'released') $msg = "Olá *{$user['name']}*! Seu pedido *#{$item['id']}* saiu para entrega! 🚚";
                            if ($status == 'delivered') $msg = "Oba, *{$user['name']}*! Seu pedido *#{$item['id']}* foi entregue! ✅";
                        } else {
                            if ($status == 'posted') $msg = "Olá *{$user['name']}*! A peça do seu RMA *#{$item['id']}* foi postada! 🚚";
                            if ($status == 'released') $msg = "Olá *{$user['name']}*! A peça do seu RMA *#{$item['id']}* saiu para entrega! 🚚";
                            if ($status == 'delivered') $msg = "Oba, *{$user['name']}*! A peça do seu RMA *#{$item['id']}* foi entregue! ✅";
                            if ($status == 'received') $msg = "Olá *{$user['name']}*! O pacote da sua logística reversa do RMA *#{$item['id']}* foi recebido! 📥";
                        }
                        
                        if ($msg) $notif->notifyCustomer($user['phone'], $msg);
                    }
                    
                    // Atualizar no banco
                    if ($item['origin'] == 'order') {
                        $pdo->prepare("UPDATE orders SET last_tracking_status = ? WHERE id = ?")->execute([$status, $item['id']]);
                    } else {
                        $pdo->prepare("UPDATE rma_tickets SET me_status = ? WHERE id = ?")->execute([$status, $item['id']]);
                    }
                }
            }
        }
    }
}

// 5. MONITORAMENTO LALAMOVE
echo "Verificando Lalamove...\n";
$stmt = $pdo->query("SELECT id, me_order_id, last_tracking_status, wa_notify_tracking FROM orders WHERE shipping_method LIKE '%Lalamove%' AND me_order_id IS NOT NULL AND status != 'delivered' LIMIT 10");
$llmOrders = $stmt->fetchAll();
foreach ($llmOrders as $lo) {
    $res = $llm->getOrder($lo['me_order_id']);
    $status = $res['data']['status'] ?? '';
    if ($status && $status != $lo['last_tracking_status']) {
        echo "Lalamove #{$lo['id']}: {$lo['last_tracking_status']} -> $status\n";
        
        if ($lo['wa_notify_tracking'] == 1 && ($status == 'PICKED_UP' || $status == 'COMPLETED')) {
            $user = $pdo->query("SELECT name, phone FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ".(int)$lo['id'])->fetch();
            if ($user && !empty($user['phone'])) {
                $msg = ($status == 'PICKED_UP') 
                    ? "🏍️ *{$user['name']}*, o motoboy Lalamove acabou de coletar seu pedido! Ele chega em instantes."
                    : "✅ *{$user['name']}*, seu pedido Lalamove foi entregue! Aproveite seu Fight Arcade! 🕹️";
                $notif->notifyCustomer($user['phone'], $msg);
            }
        }
        $pdo->prepare("UPDATE orders SET last_tracking_status = ? WHERE id = ?")->execute([$status, $lo['id']]);
    }
}

// 6. MONITORAMENTO UBER DIRECT
echo "Verificando Uber...\n";
require_once __DIR__ . '/includes/uber_api.php';
$uber = new UberService($pdo);
$stmt = $pdo->query("SELECT id, me_order_id, last_tracking_status, wa_notify_tracking FROM orders WHERE shipping_method LIKE '%Uber%' AND me_order_id IS NOT NULL AND status != 'delivered' LIMIT 10");
$uberOrders = $stmt->fetchAll();
foreach ($uberOrders as $uo) {
    $res = $uber->getDelivery($uo['me_order_id']);
    $status = $res['status'] ?? ''; // Ex: pickup, pickup_complete, delivered
    if ($status && $status != $uo['last_tracking_status']) {
        echo "Uber #{$uo['id']}: {$uo['last_tracking_status']} -> $status\n";

        if ($uo['wa_notify_tracking'] == 1 && ($status == 'pickup' || $status == 'delivered')) {
            $user = $pdo->query("SELECT name, phone FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ".(int)$uo['id'])->fetch();
            if ($user && !empty($user['phone'])) {
                $msg = ($status == 'pickup') 
                    ? "🚗 *{$user['name']}*, o motorista Uber já está com seu pedido! Ele deve chegar em breve."
                    : "✅ *{$user['name']}*, seu pedido Uber foi entregue! Aproveite seu Fight Arcade! 🕹️";
                $notif->notifyCustomer($user['phone'], $msg);
            }
        }
        $pdo->prepare("UPDATE orders SET last_tracking_status = ? WHERE id = ?")->execute([$status, $uo['id']]);
    }
}

// 1. ALERTA DE ESTOQUE BAIXO (Para o Admin)
echo "Verificando estoque...\n";
$stmt = $pdo->query("SELECT name, stock FROM products WHERE stock <= 2 AND stock > 0");
$lowStock = $stmt->fetchAll();
if ($lowStock) {
    $msg = "⚠️ *ALERTA DE ESTOQUE BAIXO*\n\n";
    foreach ($lowStock as $p) {
        $msg .= "• {$p['name']}: restam apenas {$p['stock']} un.\n";
    }
    $notif->notifyAdmin($msg);
    echo "Alerta de estoque enviado ao admin.\n";
}

// 2. LEMBRETE DE DÍVIDAS (Para Clientes)
echo "Verificando devedores...\n";
try {
    $stmt = $pdo->query("SELECT name, phone, current_debt FROM users WHERE current_debt > 0");
    $debtors = $stmt->fetchAll();
    foreach ($debtors as $d) {
        if (!empty($d['phone'])) {
            $msg = "Olá *{$d['name']}*! 👋\n\n"
                 . "Passando para lembrar do seu saldo em aberto na Fight Arcade: *R$ " . number_format($d['current_debt'], 2, ',', '.') . "*.\n"
                 . "Caso precise do PIX ou queira negociar, estamos à disposição! 🕹️";
            $notif->notifyCustomer($d['phone'], $msg);
            echo "Lembrete de dívida enviado para {$d['name']}.\n";
        }
    }
} catch (Exception $e) { echo "Pulei devedores (coluna não existe ainda ou erro).\n"; }

// 3. PEDIDOS PENDENTES (Lembrete de Pagamento)
echo "Verificando pedidos pendentes...\n";
$stmt = $pdo->query("SELECT o.id, u.name, u.phone, o.total_amount 
                     FROM orders o 
                     JOIN users u ON o.user_id = u.id 
                     WHERE o.status = 'pending' 
                     AND o.created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                     AND o.created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)");
$pendings = $stmt->fetchAll();
foreach ($pendings as $p) {
    if (!empty($p['phone'])) {
        $msg = "Oi *{$p['name']}*! 👋\n\n"
             . "Vimos que seu pedido *#{$p['id']}* ainda está aguardando pagamento.\n"
             . "Gostaria de concluir a compra? Se tiver alguma dúvida, fale com a gente! 🕹️";
        $notif->notifyCustomer($p['phone'], $msg);
        echo "Lembrete de pedido #{$p['id']} enviado.\n";
    }
}
}

// ======================================================================
// 5. SDR AUTOMATIZADO (MEGA CENTRAL COM GEMINI)
// ======================================================================
echo "\nVerificando fila do SDR Automatizado (Follow-up de Leads)...\n";
require_once __DIR__ . '/includes/ai_sdr.php';
$ai = new AIService($pdo);

if ($ai->isActive()) {
    // Puxar leads onde o robô está ativo e o prazo já passou (ou nunca foi contatado)
    $stmt = $pdo->query("
        SELECT id, name, phone, sdr_followup_days 
        FROM users 
        WHERE is_lead = 1 
          AND sdr_bot_status = 'active' 
          AND (last_contacted_at IS NULL OR DATEDIFF(NOW(), last_contacted_at) >= sdr_followup_days)
          AND phone IS NOT NULL AND phone != ''
        LIMIT 10
    ");
    $leadsToFollowUp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $siteData = file_exists(__DIR__ . '/includes/site_settings.json') ? json_decode(file_get_contents(__DIR__ . '/includes/site_settings.json'), true) : [];
    $storeName = $siteData['store_name'] ?? 'Loja';
    
    foreach ($leadsToFollowUp as $lead) {
        echo ">> Gerando follow-up SDR para: {$lead['name']} ({$lead['phone']})\n";
        
        $prompt = "AJA COMO UM SDR (VENDEDOR PROATIVO) DA {$storeName}.
        O cliente se chama {$lead['name']}. Ele demonstrou interesse no passado, mas está frio há dias.
        Gere uma mensagem SUPER CURTA, amigável (sem parecer robô), perguntando se ele quer ver as novidades ou se ficou com alguma dúvida.
        Exemplo do que você pode falar: 'Oi [nome]! Tudo bem? Passando pra saber se você ainda tem interesse ou se quer que eu te mande fotos das novidades que chegaram!'.
        Importante: Seja criativo, não copie o exemplo exatamente. Use emojis. Assine como Equipe {$storeName}.";
        
        $aiMsg = $ai->generateResponse($prompt, "cron_sdr_" . $lead['id']);
        
        if ($aiMsg) {
            $num = preg_replace('/\D/', '', $lead['phone']);
            if (strlen($num) >= 10) {
                // Envia pelo WhatsApp
                $res = $notif->send($num, $aiMsg);
                if ($res) {
                    echo "   - Mensagem enviada via IA.\n";
                    // Atualiza data do último contato
                    $pdo->prepare("UPDATE users SET last_contacted_at = NOW() WHERE id = ?")->execute([$lead['id']]);
                } else {
                    echo "   - Falha ao enviar WhatsApp.\n";
                }
            }
        }
    }
} else {
    echo "SDR (Gemini) está desativado nas configurações.\n";
}

echo "--- WORKER FINALIZADO ---\n";
