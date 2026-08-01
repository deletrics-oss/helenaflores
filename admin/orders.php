<?php
/**
 * admin/orders.php — Fight Arcade
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

require_once __DIR__ . '/../includes/melhorenvio.php';
require_once __DIR__ . '/../includes/uber_api.php';

$me = new MelhorEnvioAPI($pdo);
$uber = new UberService($pdo);

// Migrações automáticas para tabela de pedidos
try { $pdo->exec("ALTER TABLE orders ADD COLUMN last_tracking_status VARCHAR(255) NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN wa_notify_tracking TINYINT(1) DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_address TEXT NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(100) DEFAULT 'whatsapp'"); } catch(Exception $e) {}

$userCols = [
    'city'         => "VARCHAR(100) DEFAULT 'São Paulo'",
    'state'        => "VARCHAR(10) DEFAULT 'SP'",
    'phone'        => "VARCHAR(50) DEFAULT ''",
    'address'      => "VARCHAR(255) DEFAULT ''",
    'number'       => "VARCHAR(50) DEFAULT ''",
    'neighborhood' => "VARCHAR(100) DEFAULT ''",
    'zipcode'      => "VARCHAR(20) DEFAULT ''",
    'document'     => "VARCHAR(50) DEFAULT ''"
];
foreach($userCols as $c => $def) {
    try { $pdo->exec("ALTER TABLE users ADD COLUMN $c $def"); } catch(Exception $e) {}
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) DEFAULT 'Saldo/Manual',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Exception $e) {}


// Helper para tradução de status de rastreio
function getMeStatusTranslation($status) {
    if (empty($status)) return '';
    $statusMap = [
        'pending' => 'Pendente',
        'released' => 'Liberado / Em Trânsito',
        'posted' => 'Postado',
        'delivered' => 'Entregue',
        'received' => 'Recebido',
        'undelivered' => 'Não Entregue / Falhou',
        'canceled' => 'Cancelado',
        'in_transit' => 'Em Trânsito',
        'waiting_post' => 'Aguardando Postagem',
        'lost' => 'Extraviado',
        'returned' => 'Devolvido',
        // Lalamove statuses
        'assigning_driver' => 'Buscando Motorista',
        'assigned_driver' => 'Motorista Atribuído',
        'picked_up' => 'Coletado',
        'completed' => 'Entregue',
        'rejected' => 'Rejeitado',
        'expired' => 'Expirado',
        // Uber statuses
        'pickup' => 'Coleta em Andamento',
        'pickup_complete' => 'Coletado',
        'delivery' => 'Entrega em Andamento',
        'delivery_complete' => 'Entregue',
        'returned' => 'Devolvido'
    ];
    $lower = strtolower(trim($status));
    return isset($statusMap[$lower]) ? $statusMap[$lower] : ucfirst($status);
}

// Helper para obter a cor de cada status de rastreio
function getMeStatusColor($status) {
    if (empty($status)) return '#888';
    $lower = strtolower(trim($status));
    switch ($lower) {
        case 'delivered':
        case 'received':
        case 'completed':
        case 'delivery_complete':
            return '#00e676'; // Verde
        case 'released':
        case 'in_transit':
        case 'posted':
        case 'picked_up':
        case 'pickup_complete':
        case 'delivery':
        case 'pickup':
        case 'assigned_driver':
            return '#3498db'; // Azul
        case 'pending':
        case 'waiting_post':
        case 'assigning_driver':
            return '#f1c40f'; // Amarelo
        case 'canceled':
        case 'undelivered':
        case 'lost':
        case 'rejected':
        case 'expired':
            return '#e74c3c'; // Vermelho
        default:
            return '#e67e22'; // Laranja
    }
}

// AJAX: Customer Search Autocomplete
if (isset($_GET['ajax_search_customers'])) {
    header('Content-Type: application/json');
    $q = $_GET['q'] ?? '';
    if (mb_strlen($q) >= 2) {
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.city, u.state, u.phone, COUNT(o.id) as order_count 
            FROM users u LEFT JOIN orders o ON o.user_id = u.id 
            WHERE u.name LIKE :q AND u.role != 'admin'
            GROUP BY u.id ORDER BY order_count DESC LIMIT 8");
        $stmt->execute([':q' => "%$q%"]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
    exit;
}

// AJAX: Sincronizar Rastreio
if (isset($_GET['sync_track'])) {
    $oid = isset($_GET['id']) ? (int)$_GET['id'] : (int)$_GET['sync_track'];
    $order = $pdo->query("SELECT * FROM orders WHERE id = $oid")->fetch();
    if ($order) {
        $newStatus = null;
        
        // 1. Melhor Envio
        if (!empty($order['me_order_id']) && (empty($order['shipping_method']) || strpos($order['shipping_method'], 'Lalamove') === false && strpos($order['shipping_method'], 'Uber') === false)) {
            $meIds = array_filter(array_map('trim', explode(',', $order['me_order_id'])));
            $trackData = $me->tracking($meIds);
            
            $statusList = [];
            $trackingList = !empty($order['tracking_code']) ? array_filter(array_map('trim', explode(',', $order['tracking_code']))) : [];
            
            foreach ($meIds as $meId) {
                if (isset($trackData[$meId])) {
                    $statusList[] = $trackData[$meId]['status'] ?? '';
                    if (isset($trackData[$meId]['tracking'])) {
                        $tc = $trackData[$meId]['tracking'];
                        if (!in_array($tc, $trackingList)) {
                            $trackingList[] = $tc;
                        }
                    }
                }
            }
            
            if (!empty($statusList)) {
                $newStatus = $statusList[0];
            }
            
            if (!empty($trackingList)) {
                $newTrackingStr = implode(', ', $trackingList);
                $pdo->prepare("UPDATE orders SET tracking_code = ? WHERE id = ?")->execute([$newTrackingStr, $oid]);
                $order['tracking_code'] = $newTrackingStr;
            }
        }
        // 2. Lalamove
        elseif (!empty($order['me_order_id']) && strpos($order['shipping_method'], 'Lalamove') !== false) {
            $res = $llm->getOrder($order['me_order_id']);
            $newStatus = $res['data']['status'] ?? null;
        }
        // 3. Uber
        elseif (!empty($order['me_order_id']) && strpos($order['shipping_method'], 'Uber') !== false) {
            if (method_exists($uber, 'getDelivery')) {
                $res = $uber->getDelivery($order['me_order_id']);
                $newStatus = $res['status'] ?? null;
            }
        }

        if ($newStatus) {
            $oldStatus = $order['last_tracking_status'] ?? '';
            $pdo->prepare("UPDATE orders SET last_tracking_status = ? WHERE id = ?")->execute([$newStatus, $oid]);
            
            // Dispara WhatsApp via Evolution API se habilitado
            if ($newStatus !== $oldStatus && !empty($order['phone']) && !empty($order['tracking_code']) && !empty($order['wa_notify_tracking'])) {
                require_once __DIR__ . '/../includes/notifications.php';
                $notif = new NotificationService($pdo, true);
                $notif->trackingUpdate($order['phone'], $order['customer_name'], $order['tracking_code'], $newStatus, $order['user_id'] ?? 0);
            }
            
            header("Location: orders.php?msg=sync_ok&status=" . urlencode(getMeStatusTranslation($newStatus)));
        } else {
            header("Location: orders.php?msg=sync_fail");
        }
    }
    exit;
}

// AJAX: Send WhatsApp Notification manually
if (isset($_GET['ajax_send_wa']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $oid = (int)$_GET['id'];
    $order = $pdo->query("SELECT o.*, u.phone, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch();
    if ($order && !empty($order['phone'])) {
        $track = !empty($order['tracking_code']) ? $order['tracking_code'] : ($order['me_order_id'] ?? '');
        $res = $notif->orderShipped($order['phone'], $order['customer_name'], $oid, $track, $order['user_id']);
        
        $hideStore = false;
        if (!empty($order['user_id'])) {
            $hideStore = $pdo->query("SELECT hide_store_name FROM users WHERE id = " . (int)$order['user_id'])->fetchColumn();
        }
        $storeName = $hideStore ? "Catálogo de Games" : "*Fight Arcade*";

        $msgFallback = "Olá *{$order['customer_name']}*! 👋\n\nSeu pedido *#$oid* foi postado com sucesso! 🚀\n";
        if ($track) {
            $track_codes = preg_split('/[\s,;]+/', $track);
            $track_codes = array_filter(array_map('trim', $track_codes));
            if (count($track_codes) > 1) {
                $msgFallback .= "📦 Seus códigos de rastreio:\n";
                foreach ($track_codes as $tc) {
                    $msgFallback .= "• *$tc* (🔍 https://www.melhorrastreio.com.br/rastreio/$tc)\n";
                }
                $msgFallback .= "\n";
            } else {
                $msgFallback .= "📦 Código de Rastreio: *$track*\n🔍 https://www.melhorrastreio.com.br/rastreio/$track\n\n";
            }
        }
        $msgFallback .= "Pode acompanhar pelo site da transportadora ou nos consultar aqui.\n\nEquipe $storeName 🕹️";
        $fallbackUrl = NotificationService::waLink($order['phone'], $msgFallback);

        echo json_encode(['success' => $res, 'fallback_url' => $fallbackUrl]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Pedido ou telefone não encontrado']);
    }
    exit;
}

// AJAX: Send Personalized VIP Message (Orders)
if (isset($_GET['ajax_personalized_msg']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $oid = (int)$_GET['id'];
    $rawMsg = $_GET['msg'] ?? '';
    
    $order = $pdo->query("SELECT o.*, u.phone, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch();
    if (!$order || empty($order['phone'])) {
        echo json_encode(['success' => false, 'error' => 'Pedido ou telefone não encontrado']);
        exit;
    }
    
    // Replace placeholders
    $finalMsg = str_replace(
        ['{nome}', '{pedido}', '{produto}'],
        [$order['customer_name'], $oid, $order['product_name'] ?? 'Produto'],
        $rawMsg
    );
    
    $res = $notif->send($order['phone'], $finalMsg);
    
    if (!$res) {
        $fallbackUrl = NotificationService::waLink($order['phone'], $finalMsg);
        echo json_encode(['success' => false, 'error' => 'API offline. Use o WhatsApp Web.', 'fallback_url' => $fallbackUrl]);
    } else {
        echo json_encode(['success' => true]);
    }
    exit;
}

// Cleanup: Se houver rastreio começando com ORD- (erro de captura anterior), limpar para permitir novo sync
$pdo->query("UPDATE orders SET tracking_code = NULL WHERE tracking_code LIKE 'ORD-%'");

// Handle Toggle WhatsApp Notif
if (isset($_GET['toggle_wa'])) {
    $oid = (int)$_GET['toggle_wa'];
    $pdo->prepare("UPDATE orders SET wa_notify_tracking = 1 - IFNULL(wa_notify_tracking, 1) WHERE id = ?")->execute([$oid]);
    header("Location: orders.php?msg=notif_toggled");
    exit;
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'auto_quitar') {
        $customer_id = (int)$_POST['customer_id'];
        if ($customer_id > 0) {
            $pdo->beginTransaction();
            try {
                $total_paid = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE user_id = $customer_id")->fetchColumn();
                $orders_list = $pdo->query("SELECT id, total_amount, status FROM orders WHERE user_id = $customer_id AND status != 'canceled' ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
                
                $remaining_credit = $total_paid;
                $updated_count = 0;
                
                foreach ($orders_list as $ord) {
                    $amount = (float)$ord['total_amount'];
                    if ($remaining_credit >= $amount) {
                        $remaining_credit -= $amount;
                        if ($ord['status'] === 'pending') {
                            $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$ord['id']]);
                            $updated_count++;
                        }
                    } else {
                        // Credit is insufficient
                    }
                }
                
                $pdo->prepare("UPDATE users SET current_debt = (
                    (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = :uid1 AND status != 'canceled') - 
                    (SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE user_id = :uid2)
                ) WHERE id = :uid3")->execute([
                    ':uid1' => $customer_id,
                    ':uid2' => $customer_id,
                    ':uid3' => $customer_id
                ]);
                
                $pdo->commit();
                header("Location: orders.php?f_customer=" . urlencode($_POST['customer_name'] ?? '') . "&msg=auto_quitar_success&count=" . $updated_count);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = "Erro no auto-abate: " . $e->getMessage();
            }
        }
    }
    
    if ($_POST['action'] === 'update_status') {
        $oid = $_POST['order_id'];
        $status = $_POST['status'];
        $track = $_POST['tracking_code'] ?? null;

        $sql = "UPDATE orders SET status = :st, tracking_code = :track WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':st' => $status, ':track' => $track, ':id' => $oid]);

        // --- NOTIFICATIONS ---

        // 1. Fetch User Info for Notification
        $uStmt = $pdo->prepare("SELECT name, email, phone, id FROM users WHERE id = (SELECT user_id FROM orders WHERE id = ?)");
        $uStmt->execute([$oid]);
        $user = $uStmt->fetch();

        if ($user) {
            $userId = $user['id'];
            $total_amount = $pdo->query("SELECT total_amount FROM orders WHERE id = $oid")->fetchColumn() ?: 0;

            // Sync customer payments for this order status change
            if ($status === 'paid') {
                $pay_stmt = $pdo->prepare("SELECT id FROM customer_payments WHERE user_id = ? AND (description LIKE ? OR description LIKE ?)");
                $pay_stmt->execute([$userId, "%Pedido #$oid%", "%Pedido PDV #$oid%"]);
                $pay_id = $pay_stmt->fetchColumn();
                
                if (!$pay_id) {
                    $pdo->prepare("INSERT INTO customer_payments (user_id, amount, payment_method, description) VALUES (?, ?, 'Saldo/Manual', ?)")
                        ->execute([$userId, $total_amount, "Pagamento do Pedido #$oid"]);
                } else {
                    $pdo->prepare("UPDATE customer_payments SET amount = ? WHERE id = ?")->execute([$total_amount, $pay_id]);
                }
            } elseif ($status === 'pending' || $status === 'canceled') {
                $pdo->prepare("DELETE FROM customer_payments WHERE user_id = ? AND (description LIKE ? OR description LIKE ?)")
                    ->execute([$userId, "%Pedido #$oid%", "%Pedido PDV #$oid%"]);
            }

            // --- FINANCIAL SYNC ---
            $pdo->prepare("UPDATE users SET current_debt = (
                (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = :uid1 AND status != 'canceled') - 
                (SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE user_id = :uid2)
            ) WHERE id = :uid3")->execute([
                ':uid1' => $userId,
                ':uid2' => $userId,
                ':uid3' => $userId
            ]);

            // NOTA: Notificações automáticas DESATIVADAS
            // Use os botões de WhatsApp na tabela para enviar manualmente
            // quando desejar notificar o cliente.
            //
            // if ($status == 'shipped') {
            //     $notif->orderShipped($user['phone'], $user['name'], $oid, $track, "Correios / Transportadora");
            // } elseif ($status == 'paid') {
            //     $notif->orderPaid($user['phone'], $user['name'], $oid);
            // }

            // Still send email if preferred (basic mail for now)
            if ($status == 'shipped' && !empty($user['email'])) {
                $msgTitle = "Seu Pedido #$oid foi Enviado! 🚚";
                $msgBody = "Olá {$user['name']},\n\nSeu pedido #$oid já está a caminho!\n\n📦 Código de Rastreio: $track\n\nAcompanhe a entrega no site da transportadora.\n\nObrigado!";
                $headers = "From: contato@fightarcade.com.br\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                @mail($user['email'], $msgTitle, $msgBody, $headers);
            }
        }
    }
    header("Location: orders.php?msg=updated");
    exit;
}

// 2. CLONE ORDER
if (isset($_GET['clone_order'])) {
    $oid = (int) $_GET['clone_order'];
    // 1. Fetch Order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$oid]);
    $orgOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($orgOrder) {
        // 2. Duplicate Order
        unset($orgOrder['id']);
        $orgOrder['created_at'] = date('Y-m-d H:i:s');
        $orgOrder['status'] = 'pending';
        $orgOrder['tracking_code'] = NULL;
        $orgOrder['shipping_method'] .= ' (Cópia)';

        $cols = array_keys($orgOrder);
        $vals = array_values($orgOrder);
        $placeholders = str_repeat('?,', count($cols) - 1) . '?';

        $sql = "INSERT INTO orders (" . implode(',', $cols) . ") VALUES ($placeholders)";
        $pdo->prepare($sql)->execute($vals);
        $newId = $pdo->lastInsertId();

        // 3. Duplicate Items
        $items = $pdo->query("SELECT * FROM order_items WHERE order_id = $oid")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            unset($item['id']);
            $item['order_id'] = $newId;

            $iCols = array_keys($item);
            $iVals = array_values($item);
            $iPlaceholders = str_repeat('?,', count($iCols) - 1) . '?';

            $iSql = "INSERT INTO order_items (" . implode(',', $iCols) . ") VALUES ($iPlaceholders)";
            $pdo->prepare($iSql)->execute($iVals);
        }

        header("Location: orders.php?msg=cloned");
        exit;
    }
}

// 3. BULK ACTIONS
if (isset($_POST['bulk_action']) && !empty($_POST['selected_orders'])) {
    $idsArr = $_POST['selected_orders'];
    $ids = implode(',', array_map('intval', $idsArr));
    $action = $_POST['bulk_action'];
    
    if ($action === 'delete') {
        $orders_to_del = $pdo->query("SELECT id, user_id FROM orders WHERE id IN ($ids)")->fetchAll(PDO::FETCH_ASSOC);
        $userIdsToSync = [];
        foreach ($orders_to_del as $o_del) {
            $userIdsToSync[$o_del['user_id']] = true;
            $pdo->prepare("DELETE FROM customer_payments WHERE user_id = ? AND (description LIKE ? OR description LIKE ?)")
                ->execute([$o_del['user_id'], "%Pedido #" . $o_del['id'] . "%", "%Pedido PDV #" . $o_del['id'] . "%"]);
        }
        $pdo->query("DELETE FROM order_items WHERE order_id IN ($ids)");
        $pdo->query("DELETE FROM orders WHERE id IN ($ids)");
        
        // Sync affected users debt
        foreach (array_keys($userIdsToSync) as $uidToSync) {
            $pdo->prepare("UPDATE users u SET current_debt = (
                (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND status != 'canceled') - 
                (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.user_id = u.id)
            ) WHERE id = ?")->execute([$uidToSync]);
        }
        
        header("Location: orders.php?msg=bulk_deleted");
        exit;
    } elseif ($action === 'notify_auto') {
        $pdo->query("UPDATE orders SET wa_notify_tracking = 1 WHERE id IN ($ids)");
        header("Location: orders.php?msg=bulk_auto_enabled");
        exit;
    } elseif ($action === 'notify_manual') {
        $pdo->query("UPDATE orders SET wa_notify_tracking = 0 WHERE id IN ($ids)");
        header("Location: orders.php?msg=bulk_auto_disabled");
        exit;
    } elseif ($action === 'notify_initial') {
        require_once __DIR__ . '/../includes/notifications.php';
        $notif = new NotificationService($pdo);
        $count = 0;
        foreach ($idsArr as $oid) {
            $order = $pdo->query("SELECT o.*, u.phone, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch();
            if ($order && !empty($order['phone'])) {
                $track = !empty($order['tracking_code']) ? $order['tracking_code'] : ($order['me_order_id'] ?? '');
                $notif->orderShipped($order['phone'], $order['customer_name'], $oid, $track, $order['user_id']);
                $count++;
            }
        }
        header("Location: orders.php?msg=bulk_notified&count=$count");
        exit;
    } elseif ($action === 'sync_notify_selected') {
        $count = 0;
        $notified = 0;
        $errors = 0;
        
        require_once __DIR__ . '/../includes/notifications.php';
        $notif = new NotificationService($pdo, true);
        
        foreach ($idsArr as $oid) {
            $order = $pdo->query("SELECT o.*, u.phone, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = $oid")->fetch();
            if ($order && !empty($order['me_order_id'])) {
                $newStatus = null;
                try {
                    // 1. Melhor Envio
                    if (empty($order['shipping_method']) || strpos($order['shipping_method'], 'Lalamove') === false && strpos($order['shipping_method'], 'Uber') === false) {
                        $trackData = $me->tracking([$order['me_order_id']]);
                        if (isset($trackData[$order['me_order_id']])) {
                            $newStatus = $trackData[$order['me_order_id']]['status'] ?? null;
                        }
                    }
                    // 2. Lalamove
                    elseif (strpos($order['shipping_method'], 'Lalamove') !== false) {
                        $res = $llm->getOrder($order['me_order_id']);
                        $newStatus = $res['data']['status'] ?? null;
                    }
                    // 3. Uber
                    elseif (strpos($order['shipping_method'], 'Uber') !== false) {
                        if (method_exists($uber, 'getDelivery')) {
                            $res = $uber->getDelivery($order['me_order_id']);
                            $newStatus = $res['status'] ?? null;
                        }
                    }
                    
                    if ($newStatus) {
                        $pdo->prepare("UPDATE orders SET last_tracking_status = ? WHERE id = ?")->execute([$newStatus, $oid]);
                        $count++;
                        
                        // Send WhatsApp tracking update manually (disparo)
                        if (!empty($order['phone']) && !empty($order['tracking_code'])) {
                            $sent = $notif->trackingUpdate($order['phone'], $order['customer_name'], $order['tracking_code'], $newStatus, $order['user_id'] ?? 0);
                            if ($sent) {
                                $notified++;
                            } else {
                                $errors++;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errors++;
                }
            }
        }
        header("Location: orders.php?msg=bulk_sync_ok&synced=$count&notified=$notified&errors=$errors");
        exit;
    }
}

// Handle Export Single Order (CSV for Bling/Tiny)
if (isset($_GET['export_order'])) {
    $oid = $_GET['export_order'];
    $stmt = $pdo->prepare("SELECT o.*, u.name, u.document, u.email, u.phone, u.zipcode, u.address, u.number, u.neighborhood, u.city, u.state FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$oid]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pedido_' . $oid . '_bling_tiny.csv"');
        $out = fopen('php://output', 'w');

        // Header (Standard B2B Columns)
        fputcsv($out, ['Numero', 'Data', 'Cliente', 'CPF_CNPJ', 'Email', 'Telefone', 'CEP', 'Endereco', 'Numero', 'Bairro', 'Cidade', 'UF', 'Total', 'Frete', 'Itens']);

        // Logic to flatten items
        $items = $pdo->query("SELECT * FROM order_items WHERE order_id = $oid")->fetchAll(PDO::FETCH_ASSOC);
        $itemsStr = "";
        foreach ($items as $i) {
            $itemsStr .= "{$i['quantity']}x {$i['product_name']} | ";
        }

        fputcsv($out, [
            $order['id'],
            $order['created_at'],
            $order['name'],
            $order['document'],
            $order['email'],
            $order['phone'],
            $order['zipcode'],
            $order['address'],
            $order['number'],
            $order['neighborhood'],
            $order['city'],
            $order['state'],
            $order['total_amount'],
            $order['shipping_cost'] ?? 0,
            $itemsStr
        ]);
        fclose($out);
        exit;
    }
}

// Ensure notify_blocked column exists
try { $pdo->exec("ALTER TABLE users ADD COLUMN notify_blocked TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}

// Export Debtors CSV
if (isset($_GET['export_debtors'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="devedores_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['Pedido', 'Data', 'Cliente', 'Telefone', 'Email', 'Cidade/UF', 'Valor', 'Dias Pendente', 'Status']);
    $debtors = $pdo->query("SELECT o.*, u.name, u.phone, u.email, u.city, u.state, DATEDIFF(NOW(), o.created_at) as days_pending FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = 'pending' ORDER BY o.created_at ASC")->fetchAll();
    foreach ($debtors as $d) {
        fputcsv($out, [
            '#' . $d['id'],
            date('d/m/Y', strtotime($d['created_at'])),
            $d['name'],
            $d['phone'],
            $d['email'],
            ($d['city'] ?? '') . '/' . ($d['state'] ?? ''),
            'R$ ' . number_format($d['total_amount'], 2, ',', '.'),
            $d['days_pending'] . ' dias',
            $d['status']
        ]);
    }
    fclose($out);
    exit;
}

// Filters
$f_state = $_GET['f_state'] ?? '';
$f_city = $_GET['f_city'] ?? '';
$f_ship = $_GET['f_ship'] ?? '';
$f_sort = $_GET['f_sort'] ?? 'date_desc';
$f_status = $_GET['f_status'] ?? '';
$f_customer = $_GET['f_customer'] ?? '';

// Build Query
$sql_base = "o.*, u.name as user_name, u.phone, u.email, u.city, u.state, u.zipcode";
$sql_cost = "(SELECT SUM(cost_price * quantity) FROM order_items WHERE order_id = o.id) AS total_cost";

try {
    // Try with cost_price first
    $sql = "SELECT $sql_base, $sql_cost FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
    $test_stmt = $pdo->prepare($sql . " LIMIT 1");
    $test_stmt->execute();
} catch (Exception $e) {
    // Fallback if cost_price is missing
    $sql = "SELECT $sql_base, 0 as total_cost FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
}

$params = [];

if ($f_state) {
    $sql .= " AND u.state = :state";
    $params[':state'] = $f_state;
}

if ($f_city) {
    $sql .= " AND u.city LIKE :city";
    $params[':city'] = "%$f_city%";
}

if ($f_ship) {
    if ($f_ship == 'pickup') {
        $sql .= " AND o.shipping_method LIKE '%Retirada%'";
    } elseif ($f_ship == 'delivery') {
        $sql .= " AND o.shipping_method NOT LIKE '%Retirada%'";
    }
}

if ($f_status) {
    $sql .= " AND o.status = :status";
    $params[':status'] = $f_status;
}

if ($f_customer) {
    $sql .= " AND u.name LIKE :customer";
    $params[':customer'] = "%$f_customer%";
}

// Sorting
switch ($f_sort) {
    case 'date_asc':
        $sql .= " ORDER BY o.created_at ASC";
        break;
    case 'val_desc':
        $sql .= " ORDER BY o.total_amount DESC";
        break;
    case 'val_asc':
        $sql .= " ORDER BY o.total_amount ASC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY o.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Pedidos | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .btn-wa-notif { 
            background: #25d366; 
            color: #fff; 
            border: none; 
            padding: 6px 10px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            transition: 0.3s; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            box-shadow: 0 0 10px rgba(37, 211, 102, 0.2);
            text-decoration: none;
        }
        .btn-wa-notif:hover { background: #128c7e; transform: scale(1.1); box-shadow: 0 0 15px rgba(37, 211, 102, 0.4); color: #fff; }
        .btn-wa-notif.loading { background: #555; cursor: wait; }
        .btn-wa-notif.success { background: #27ae60; }
        .btn-wa-notif.error { background: #e74c3c; }

        .btn-wa-vip { background: #9b59b6; }
        .btn-wa-vip:hover { background: #8e44ad; }

        /* Payment Status Colors */
        .pay-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .pay-paid { background: rgba(46,204,113,.15); color: #2ecc71; border: 1px solid rgba(46,204,113,.3); }
        .pay-pending { background: rgba(231,76,60,.15); color: #e74c3c; border: 1px solid rgba(231,76,60,.3); animation: pulse-red 2s infinite; }
        .pay-shipped { background: rgba(241,196,15,.15); color: #f1c40f; border: 1px solid rgba(241,196,15,.3); }
        .pay-canceled { background: rgba(150,150,150,.15); color: #888; border: 1px solid rgba(150,150,150,.3); text-decoration: line-through; }
        @keyframes pulse-red { 0%,100% { box-shadow: 0 0 0 0 rgba(231,76,60,0); } 50% { box-shadow: 0 0 8px 2px rgba(231,76,60,.3); } }

        .pending-time { font-size: 0.72rem; color: #e74c3c; font-weight: 700; margin-top: 3px; }
        .pending-time.urgent { background: rgba(231,76,60,.15); padding: 2px 6px; border-radius: 4px; animation: pulse-red 1.5s infinite; }

        /* Debtors Section */
        .debtors-bar { background: linear-gradient(135deg, rgba(231,76,60,.08), rgba(231,76,60,.02)); border: 1px solid rgba(231,76,60,.25); border-radius: 12px; padding: 1.2rem 1.5rem; margin-bottom: 1.5rem; }
        .debtors-bar h3 { color: #e74c3c; margin: 0 0 .5rem; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .debtors-stats { display: flex; gap: 2rem; flex-wrap: wrap; }
        .debtors-stat { text-align: center; }
        .debtors-stat .val { font-size: 1.8rem; font-weight: 900; }
        .debtors-stat .lbl { font-size: 0.7rem; color: #888; text-transform: uppercase; }

        /* Receipt */
        .receipt-thumb { width: 28px; height: 28px; border-radius: 4px; object-fit: cover; border: 1px solid #444; cursor: pointer; transition: .2s; }
        .receipt-thumb:hover { transform: scale(1.5); border-color: #2ecc71; z-index: 10; position: relative; }
        .btn-receipt { background: none; border: 1px dashed #555; color: #888; padding: 3px 7px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; transition: .2s; }
        .btn-receipt:hover { border-color: #2ecc71; color: #2ecc71; }

        /* Modal Personalized Msg */
        .modal-vip { display: none; justify-content: center; align-items: center; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); }
        .modal-vip-content { background: #1a1e2a; padding: 2rem; border: 1px solid #444; width: 550px; max-width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); color: #fff; position: relative; }
        .close-vip { position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; color: #666; transition: 0.2s; z-index: 1; }
        .close-vip:hover { color: #fff; }
        .vip-option { display: flex; align-items: flex-start; gap: 12px; padding: 12px; border: 1px solid #333; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: 0.2s; background: #111; text-align: left; }
        .vip-option:hover { border-color: #9b59b6; background: #1c1f26; }
        .vip-option input[type="radio"] { margin-top: 4px; width: 18px; height: 18px; flex-shrink: 0; cursor: pointer; }
        .vip-text-content { flex: 1; }
        .vip-option strong { display: block; color: #9b59b6; margin-bottom: 3px; }
        .vip-option span { font-size: 0.8rem; color: #888; }

        /* ===== CUSTOMER FILTER STYLES ===== */
        .customer-search-wrapper { position: relative; }
        .customer-search-wrapper input { padding-left: 32px !important; }
        .customer-search-wrapper::before { content: '👤'; position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.9rem; z-index: 1; pointer-events: none; }
        .customer-autocomplete { position: absolute; top: 100%; left: 0; right: 0; background: #1a1e2a; border: 1px solid #f39c12; border-top: none; border-radius: 0 0 8px 8px; max-height: 220px; overflow-y: auto; z-index: 999; display: none; box-shadow: 0 8px 25px rgba(0,0,0,0.6); }
        .customer-autocomplete .ac-item { padding: 10px 14px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: 0.15s; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .customer-autocomplete .ac-item:hover { background: rgba(243, 156, 18, 0.15); }
        .customer-autocomplete .ac-item .ac-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #f39c12, #e67e22); display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 0.85rem; color: #000; flex-shrink: 0; }
        .customer-autocomplete .ac-item .ac-info { flex: 1; }
        .customer-autocomplete .ac-item .ac-name { color: #fff; font-weight: 700; font-size: 0.85rem; }
        .customer-autocomplete .ac-item .ac-detail { color: #888; font-size: 0.7rem; }
        .customer-autocomplete .ac-item .ac-orders-count { background: rgba(243, 156, 18, 0.2); color: #f39c12; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 800; flex-shrink: 0; }

        /* Customer Summary Card */
        .customer-summary-card { background: linear-gradient(135deg, rgba(243,156,18,.08) 0%, rgba(243,156,18,.02) 100%); border: 1px solid rgba(243,156,18,.35); border-radius: 16px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
        .customer-summary-card::before { content: ''; position: absolute; top: -50%; right: -20%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(243,156,18,.06) 0%, transparent 70%); pointer-events: none; }
        .customer-summary-header { display: flex; align-items: center; gap: 16px; margin-bottom: 1.2rem; }
        .customer-summary-avatar { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #f39c12, #e67e22); display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.5rem; color: #000; box-shadow: 0 4px 15px rgba(243,156,18,0.3); flex-shrink: 0; }
        .customer-summary-name { font-size: 1.3rem; font-weight: 900; color: #f39c12; }
        .customer-summary-sub { font-size: 0.8rem; color: #888; margin-top: 2px; }
        .customer-summary-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; }
        .cs-stat { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 14px 16px; text-align: center; transition: 0.2s; }
        .cs-stat:hover { border-color: rgba(243,156,18,0.3); transform: translateY(-2px); }
        .cs-stat .cs-val { font-size: 1.6rem; font-weight: 900; line-height: 1; }
        .cs-stat .cs-lbl { font-size: 0.68rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; }
        .customer-summary-close { position: absolute; top: 12px; right: 16px; }
        .customer-summary-close a { background: rgba(255,255,255,0.08); color: #888; border: 1px solid rgba(255,255,255,0.1); padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 0.75rem; font-weight: 700; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .customer-summary-close a:hover { background: rgba(231,76,60,0.15); border-color: rgba(231,76,60,0.3); color: #e74c3c; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-15px); } to { opacity: 1; transform: translateY(0); } }
        .customer-summary-card { animation: slideDown 0.35s ease-out; }
    </style>
    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByName('selected_orders[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        
        <!-- MODAL VIP MSG -->
        <div id="vipModal" class="modal-vip" onclick="if(event.target == this) closeVipModal()">
            <div class="modal-vip-content">
                <span class="close-vip" onclick="closeVipModal()">&times;</span>
                <h2 style="margin-bottom:1rem; color:#9b59b6; display:flex; align-items:center; gap:10px;">✨ Atendimento VIP (WhatsApp)</h2>
                <p id="vip_target_name" style="color:#888; font-size:0.9rem; margin-bottom:1.5rem;"></p>
                
                <input type="hidden" id="vip_order_id">

                <label class="vip-option">
                    <input type="radio" name="viptpl" value="Olá {nome}! 👋 Seu pedido #{pedido} ({produtos}) já está sendo preparado com todo carinho aqui na Fight Arcade! Logo mais te enviamos o rastreio. 🕹️" onclick="document.getElementById('vip_custom_box').style.display='none'" checked>
                    <div class="vip-text-content">
                        <strong>🛠️ Em Preparação</strong>
                        <span>Avisa que o produto já está na bancada técnica.</span>
                    </div>
                </label>
                
                <label class="vip-option">
                    <input type="radio" name="viptpl" value="Oi {nome}! 👋 Notamos que o pagamento do seu pedido #{pedido} ({produtos}) ainda não foi confirmado. Precisa de ajuda com o PIX ou cartão? 🕹️" onclick="document.getElementById('vip_custom_box').style.display='none'">
                    <div class="vip-text-content">
                        <strong>💸 Aguardando Pagamento</strong>
                        <span>Abordagem amigável para converter pedidos pendentes.</span>
                    </div>
                </label>
                
                <label class="vip-option">
                    <input type="radio" name="viptpl" value="Olá {nome}! 👋 Passando para agradecer a confiança! Seu {produtos} foi enviado. Depois nos conte o que achou das jogatinas! 🕹️🔥" onclick="document.getElementById('vip_custom_box').style.display='none'">
                    <div class="vip-text-content">
                        <strong>❤️ Gratidão / Pós-Venda</strong>
                        <span>Reforça o relacionamento após o envio.</span>
                    </div>
                </label>

                <label class="vip-option">
                    <input type="radio" name="viptpl" value="custom" onclick="document.getElementById('vip_custom_box').style.display='block'">
                    <div class="vip-text-content">
                        <strong>✍️ Mensagem Livre</strong>
                        <span>Escreva o que desejar para este cliente.</span>
                    </div>
                </label>

                <div id="vip_custom_box" style="display:none; margin-top:10px;">
                    <div id="vip_saved_templates_container" style="margin-bottom:12px; display:none;">
                        <label style="font-size:0.75rem; color:#f1c40f; font-weight:bold; display:block; margin-bottom:5px;">⭐ SEUS MODELOS SALVOS:</label>
                        <select id="vip_saved_templates" style="width:100%; background:#000; border:1px solid #f1c40f; color:#fff; padding:8px; border-radius:6px;" onchange="applySavedTemplate(this.value)">
                            <option value="">-- Escolher um favorito --</option>
                        </select>
                    </div>
                    <textarea id="vip_custom_msg" placeholder="Use {nome}, {pedido} ou {produtos}..." rows="3" style="width:100%; background:#111; border:1px solid #444; color:#fff; border-radius:8px; padding:10px;"></textarea>
                    
                    <div style="margin-top:10px; background:rgba(155, 89, 182, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(155, 89, 182, 0.3);">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem;">
                            <input type="checkbox" id="save_as_template" onchange="document.getElementById('template_title_box').style.display = this.checked ? 'block' : 'none'">
                            ⭐ Salvar esta mensagem como favorito
                        </label>
                        <div id="template_title_box" style="display:none; margin-top:8px;">
                            <input type="text" id="template_title" placeholder="Nome do modelo (Ex: Atraso Fornecedor)" style="width:100%; background:#000; border:1px solid #9b59b6; color:#fff; padding:5px; border-radius:4px; font-size:0.8rem;">
                        </div>
                    </div>
                </div>

                <div style="margin-top:2rem; display:flex; gap:10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeVipModal()">Cancelar</button>
                    <button type="button" class="btn" id="btnSendVip" style="background:#9b59b6; color:#fff; flex:1;" onclick="sendVipMsg()">🚀 ENVIAR AGORA</button>
                </div>
            </div>
        </div>

        <script>
            function openVipModal(id, name) {
                document.getElementById('vip_order_id').value = id;
                document.getElementById('vip_target_name').innerHTML = `Enviando para: <strong>${name}</strong> (Pedido #${id})`;
                document.getElementById('vipModal').style.display = 'flex';
                loadSavedTemplates();
            }

            function loadSavedTemplates() {
                fetch('ajax_message_templates.php?action=list&category=orders')
                    .then(r => r.json())
                    .then(data => {
                        const sel = document.getElementById('vip_saved_templates');
                        const container = document.getElementById('vip_saved_templates_container');
                        if (data.length > 0) {
                            sel.innerHTML = '<option value="">-- Selecionar Favorito --</option>';
                            data.forEach(t => {
                                sel.innerHTML += `<option value="${encodeURIComponent(t.message)}">${t.title}</option>`;
                            });
                            container.style.display = 'block';
                            window.savedTemplatesData = data;
                        } else {
                            container.style.display = 'none';
                        }
                    });
            }

            function applySavedTemplate(val) {
                if(val) document.getElementById('vip_custom_msg').value = decodeURIComponent(val);
            }

            function closeVipModal() {
                document.getElementById('vipModal').style.display = 'none';
                document.getElementById('save_as_template').checked = false;
                document.getElementById('template_title_box').style.display = 'none';
                document.getElementById('template_title').value = '';
            }

            function sendVipMsg() {
                const id = document.getElementById('vip_order_id').value;
                const btn = document.getElementById('btnSendVip');
                let msgValue = document.querySelector('input[name="viptpl"]:checked').value;
                let finalMsg = msgValue;
                
                if(msgValue === 'custom') {
                    finalMsg = document.getElementById('vip_custom_msg').value;
                    if(!finalMsg) return alert('Escreva a mensagem personalizada!');

                    // Save as template if checked
                    if (document.getElementById('save_as_template').checked) {
                        const title = document.getElementById('template_title').value || 'Modelo Sem Título';
                        const fd = new FormData();
                        fd.append('category', 'orders');
                        fd.append('title', title);
                        fd.append('message', finalMsg);
                        fetch('ajax_message_templates.php?action=save', { method: 'POST', body: fd });
                    }
                }

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ENVIANDO...';

                fetch(`orders.php?ajax_personalized_msg=1&id=${id}&msg=${encodeURIComponent(finalMsg)}`)
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            btn.style.background = '#27ae60';
                            btn.innerHTML = '✅ ENVIADO COM SUCESSO!';
                            setTimeout(() => {
                                closeVipModal();
                                btn.disabled = false;
                                btn.style.background = '#9b59b6';
                                btn.innerHTML = '🚀 ENVIAR AGORA';
                            }, 2000);
                        } else {
                            const errMsg = data.error || 'Erro ao enviar mensagem.';
                            alert('⚠️ ATENÇÃO: ' + errMsg);
                            btn.disabled = false;
                            btn.innerHTML = '🚀 TENTAR NOVAMENTE';
                        }
                    });
            }

            function sendOrderWaNotify(id, btn) {
                if(!confirm('Deseja enviar o rastreio para este cliente via WhatsApp?')) return;
                
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.classList.add('loading');
                btn.disabled = true;

                fetch(`orders.php?ajax_send_wa=1&id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            btn.innerHTML = '<i class="fas fa-check"></i>';
                            btn.classList.remove('loading');
                            btn.classList.add('success');
                            setTimeout(() => {
                                btn.innerHTML = originalHtml;
                                btn.classList.remove('success');
                                btn.disabled = false;
                            }, 3000);
                        } else {
                            btn.innerHTML = '<i class="fas fa-times"></i>';
                            btn.classList.remove('loading');
                            btn.classList.add('error');
                            
                            if (data.fallback_url) {
                                if (confirm("⚠️ O envio via API automática falhou ou está offline.\nDeseja abrir o WhatsApp com o link de rastreio para enviar manualmente ao cliente?")) {
                                    window.open(data.fallback_url, '_blank');
                                }
                            } else {
                                alert('Erro ao enviar: ' + (data.error || 'Verifique se o telefone está correto (DDD+9 dígitos) e se o WhatsApp está conectado.'));
                            }
                            
                            setTimeout(() => {
                                btn.innerHTML = originalHtml;
                                btn.classList.remove('error');
                                btn.disabled = false;
                            }, 4000);
                        }
                    })
                    .catch(err => {
                        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        btn.disabled = false;
                        setTimeout(() => { btn.innerHTML = originalHtml; }, 3000);
                    });
            }
        </script>
        
        <!-- MESSAGES -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'cloned'): ?>
                <div class="alert alert-success">✅ Pedido clonado com sucesso!</div>
            <?php elseif ($_GET['msg'] == 'bulk_deleted'): ?>
                <div class="alert alert-success">🗑️ Pedidos excluídos.</div>
            <?php elseif ($_GET['msg'] == 'updated'): ?>
                <div class="alert alert-success">✅ Status atualizado!</div>
            <?php elseif ($_GET['msg'] == 'bulk_notified'): ?>
                <div class="alert alert-success">📱 <?php echo (int)($_GET['count'] ?? 0); ?> notificações enviadas!</div>
            <?php elseif ($_GET['msg'] == 'auto_quitar_success'): ?>
                <div class="alert alert-success">✅ <?php echo (int)($_GET['count'] ?? 0); ?> pedidos mais antigos foram marcados como Pago automaticamente usando os créditos do cliente!</div>
            <?php endif; ?>
        <?php endif; ?>
        <!-- UNASSOCIATED RECEIPTS (from WhatsApp) -->
        <?php
        // Handle receipt association
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_receipt'])) {
            $rId = (int)$_POST['receipt_id'];
            $rOid = (int)$_POST['link_order_id'];
            if ($rId && $rOid) {
                $userId = $pdo->query("SELECT user_id FROM orders WHERE id = $rOid")->fetchColumn();
                $pdo->prepare("UPDATE payment_receipts SET order_id = ?, user_id = ? WHERE id = ?")->execute([$rOid, $userId, $rId]);
                echo '<div class="alert alert-success">✅ Comprovante #' . $rId . ' vinculado ao pedido #' . $rOid . '!</div>';
            }
        }
        // Handle receipt deletion
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_unlinked_receipt'])) {
            $rId = (int)$_POST['receipt_id'];
            $receipt = $pdo->query("SELECT file_path FROM payment_receipts WHERE id = $rId")->fetch();
            if ($receipt) {
                @unlink(__DIR__ . '/../' . $receipt['file_path']);
                $pdo->exec("DELETE FROM payment_receipts WHERE id = $rId");
            }
        }

        try {
            $unlinked = $pdo->query("SELECT * FROM payment_receipts WHERE order_id IS NULL ORDER BY received_at DESC LIMIT 10")->fetchAll();
        } catch(Exception $e) { $unlinked = []; }
        ?>
        <?php if (!empty($unlinked)): ?>
        <div style="background:linear-gradient(135deg,rgba(241,196,15,.08),rgba(241,196,15,.02)); border:1px solid rgba(241,196,15,.25); border-radius:12px; padding:1.2rem 1.5rem; margin-bottom:1.5rem;">
            <h3 style="color:#f1c40f; margin:0 0 .8rem; font-size:1rem; display:flex; align-items:center; gap:8px;">
                📎 Comprovantes Recebidos (não associados) — <span style="font-size:.8rem;color:#888"><?php echo count($unlinked); ?> pendente(s)</span>
            </h3>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <?php foreach ($unlinked as $ur): ?>
                <div style="background:#1a1e2a; border:1px solid #333; border-radius:10px; padding:12px; width:220px; text-align:center;">
                    <?php if (strpos($ur['file_path'], '.pdf') !== false): ?>
                        <div style="font-size:2.5rem; margin-bottom:8px;">📄</div>
                    <?php else: ?>
                        <img src="../<?php echo htmlspecialchars($ur['file_path']); ?>" 
                             style="max-width:100%; max-height:120px; border-radius:6px; cursor:pointer; border:1px solid #444;"
                             onclick="window.open('../<?php echo htmlspecialchars($ur['file_path']); ?>','_blank')">
                    <?php endif; ?>
                    <div style="font-size:.72rem; color:#888; margin-top:6px;">
                        <?php echo $ur['source'] === 'whatsapp' ? '📱 WhatsApp' : '📤 Upload'; ?> • <?php echo date('d/m H:i', strtotime($ur['received_at'])); ?>
                    </div>
                    <?php if ($ur['notes']): ?>
                        <div style="font-size:.75rem; color:#f1c40f; margin-top:3px;">"<?php echo htmlspecialchars(mb_strimwidth($ur['notes'], 0, 40, '...')); ?>"</div>
                    <?php endif; ?>
                    <form method="POST" style="margin-top:8px; display:flex; gap:4px;">
                        <input type="hidden" name="receipt_id" value="<?php echo $ur['id']; ?>">
                        <input type="number" name="link_order_id" placeholder="#Pedido" 
                               style="width:70px; padding:5px; background:#111; border:1px solid #444; color:#fff; border-radius:4px; font-size:.75rem;">
                        <button type="submit" name="link_receipt" value="1" 
                                style="background:#2ecc71; color:#000; border:none; padding:5px 8px; border-radius:4px; font-size:.72rem; font-weight:bold; cursor:pointer;">
                            🔗 Vincular
                        </button>
                        <button type="submit" name="delete_unlinked_receipt" value="1" onclick="return confirm('Excluir este comprovante?')"
                                style="background:#e74c3c; color:#fff; border:none; padding:5px 6px; border-radius:4px; font-size:.72rem; cursor:pointer;">
                            🗑️
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- DEBTORS SUMMARY -->
        <?php
        // Calculate debtors stats
        $pendingOrders = $pdo->query("SELECT o.*, u.name as user_name, DATEDIFF(NOW(), o.created_at) as days_pending FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = 'pending' ORDER BY o.created_at ASC")->fetchAll();
        $totalPendingValue = 0;
        $urgentCount = 0;
        foreach ($pendingOrders as $po) {
            $totalPendingValue += $po['total_amount'];
            if ($po['days_pending'] >= 30) $urgentCount++;
        }
        ?>
        <?php if (count($pendingOrders) > 0): ?>
        <div class="debtors-bar">
            <h3>🔴 Devedores — Pedidos Pendentes de Pagamento</h3>
            <div class="debtors-stats">
                <div class="debtors-stat">
                    <div class="val" style="color:#e74c3c"><?php echo count($pendingOrders); ?></div>
                    <div class="lbl">Pedidos Pendentes</div>
                </div>
                <div class="debtors-stat">
                    <div class="val" style="color:#e74c3c"><span class="finance-value">R$ <?php echo number_format($totalPendingValue, 2, ',', '.'); ?></span></div>
                    <div class="lbl">Valor Total em Débito</div>
                </div>
                <div class="debtors-stat">
                    <div class="val" style="color:<?php echo $urgentCount > 0 ? '#e74c3c' : '#2ecc71'; ?>"><?php echo $urgentCount; ?></div>
                    <div class="lbl">Urgentes (+30 dias)</div>
                </div>
                <div class="debtors-stat">
                    <div class="val" style="color:#f39c12"><?php echo count($pendingOrders) > 0 ? $pendingOrders[0]['days_pending'] : 0; ?> dias</div>
                    <div class="lbl">Dívida Mais Antiga</div>
                </div>
            </div>
            <?php if ($urgentCount > 0): ?>
            <div style="margin-top:10px; font-size:0.8rem; color:#e74c3c;">
                ⚠️ <strong>ATENÇÃO:</strong> <?php echo $urgentCount; ?> pedido(s) pendente(s) há mais de 30 dias!
                <?php foreach ($pendingOrders as $po): ?>
                    <?php if ($po['days_pending'] >= 30): ?>
                        <span style="background:rgba(231,76,60,.2); padding:2px 8px; border-radius:10px; margin-left:5px; font-weight:800;">#<?php echo $po['id']; ?> <?php echo htmlspecialchars($po['user_name']); ?> (<?php echo $po['days_pending']; ?>d)</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div style="margin-top:10px;">
                <a href="?f_sort=date_asc&f_status=pending" class="btn" style="background:#e74c3c; color:#fff; font-size:0.8rem; padding:8px 15px;">📋 Ver Todos os Devedores</a>
                <a href="orders.php?export_debtors=1" class="btn" style="background:#333; color:#fff; font-size:0.8rem; padding:8px 15px; margin-left:5px;">📥 Exportar CSV</a>
            </div>
        </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h1>Todos os Pedidos <span style="font-size:0.8rem; color:#888; font-weight:400;">(<?php echo count($orders); ?>)</span></h1>
            <a href="create-order.php" class="btn">Criar Pedido Manual</a>
        </div>

        <!-- FILTERS -->
        <form method="GET" id="ordersFilterForm"
            style="background:#222; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; border:1px solid #333;">
            
            <!-- CUSTOMER SEARCH - Prominent first position -->
            <div class="customer-search-wrapper" style="flex-basis:220px;">
                <label style="font-size:0.8rem; display:block; margin-bottom:5px; color:#f39c12; font-weight:700;">🔎 Buscar Cliente</label>
                <input type="text" name="f_customer" id="customerSearchInput" 
                    placeholder="Digite o nome do cliente..."
                    value="<?php echo htmlspecialchars($f_customer); ?>"
                    autocomplete="off"
                    style="padding:8px 8px 8px 32px; background:#111; color:#fff; border:1px solid <?php echo $f_customer ? '#f39c12' : '#444'; ?>; border-radius:4px; width:100%; transition:0.3s;">
                <div class="customer-autocomplete" id="customerAutocomplete"></div>
            </div>

            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Estado (UF)</label>
                <select name="f_state"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
                    <option value="">Todos</option>
                    <option value="SP" <?php echo ($f_state == 'SP') ? 'selected' : ''; ?>>SP</option>
                    <option value="RJ" <?php echo ($f_state == 'RJ') ? 'selected' : ''; ?>>RJ</option>
                    <option value="MG" <?php echo ($f_state == 'MG') ? 'selected' : ''; ?>>MG</option>
                    <option value="RS" <?php echo ($f_state == 'RS') ? 'selected' : ''; ?>>RS</option>
                    <option value="PR" <?php echo ($f_state == 'PR') ? 'selected' : ''; ?>>PR</option>
                    <!-- Add more as needed -->
                </select>
            </div>
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Cidade</label>
                <input type="text" name="f_city" placeholder="Ex: São Paulo"
                    value="<?php echo htmlspecialchars($f_city); ?>"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
            </div>
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Tipo de Envio</label>
                <select name="f_ship"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
                    <option value="">Todos</option>
                    <option value="pickup" <?php echo ($f_ship == 'pickup') ? 'selected' : ''; ?>>Retirada (Loja)</option>
                    <option value="delivery" <?php echo ($f_ship == 'delivery') ? 'selected' : ''; ?>>Envio
                        (Correios/Transp)
                    </option>
                </select>
            </div>
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Pagamento</label>
                <select name="f_status"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
                    <option value="">Todos</option>
                    <option value="pending" <?php echo ($f_status == 'pending') ? 'selected' : ''; ?>>🔴 Pendente</option>
                    <option value="paid" <?php echo ($f_status == 'paid') ? 'selected' : ''; ?>>🟢 Pago</option>
                    <option value="shipped" <?php echo ($f_status == 'shipped') ? 'selected' : ''; ?>>🟡 Enviado</option>
                    <option value="canceled" <?php echo ($f_status == 'canceled') ? 'selected' : ''; ?>>⚫ Cancelado</option>
                </select>
            </div>
            <div>
                <label style="font-size:0.8rem; display:block; margin-bottom:5px;">Filtro (Ranking)</label>
                <select name="f_sort"
                    style="padding:8px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;">
                    <option value="date_desc" <?php echo ($f_sort == 'date_desc') ? 'selected' : ''; ?>>Mais Recentes
                    </option>
                    <option value="date_asc" <?php echo ($f_sort == 'date_asc') ? 'selected' : ''; ?>>Mais Antigos
                    </option>
                    <option value="val_desc" <?php echo ($f_sort == 'val_desc') ? 'selected' : ''; ?>>💰 Maior Valor (VIP)
                    </option>
                    <option value="val_asc" <?php echo ($f_sort == 'val_asc') ? 'selected' : ''; ?>>Menor Valor</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-sm"
                    style="background:var(--primary); color:#000; padding:10px 15px; border:none; border-radius:4px; font-weight:bold;">🔍
                    Filtrar</button>
                <a href="orders.php" class="btn-sm"
                    style="background:#555; color:#fff; padding:10px 15px; margin-left:5px; text-decoration:none; border-radius:4px;">Limpar</a>
            </div>
        </form>

        <!-- ===== CUSTOMER SUMMARY CARD (appears when filtering by customer) ===== -->
        <?php if ($f_customer && count($orders) > 0): ?>
        <?php
            // Calculate customer-specific stats from the filtered results
            $cs_total_orders = count($orders);
            $cs_total_value = 0;
            $cs_total_paid = 0;
            $cs_total_pending = 0;
            $cs_total_shipped = 0;
            $cs_total_canceled = 0;
            $cs_pending_value = 0;
            $cs_paid_value = 0;
            $cs_first_order = null;
            $cs_last_order = null;
            $cs_customer_name = '';
            $cs_customer_phone = '';
            $cs_customer_city = '';
            $cs_customer_state = '';
            $cs_customer_id = 0;

            foreach ($orders as $co) {
                $cs_total_value += (float)$co['total_amount'];
                if ($co['status'] == 'paid') { $cs_total_paid++; $cs_paid_value += (float)$co['total_amount']; }
                elseif ($co['status'] == 'pending') { $cs_total_pending++; $cs_pending_value += (float)$co['total_amount']; }
                elseif ($co['status'] == 'shipped') { $cs_total_shipped++; $cs_paid_value += (float)$co['total_amount']; }
                elseif ($co['status'] == 'canceled') { $cs_total_canceled++; }

                if (!$cs_first_order || strtotime($co['created_at']) < strtotime($cs_first_order)) $cs_first_order = $co['created_at'];
                if (!$cs_last_order || strtotime($co['created_at']) > strtotime($cs_last_order)) $cs_last_order = $co['created_at'];

                $cs_customer_name = $co['user_name'];
                $cs_customer_phone = $co['phone'] ?? '';
                $cs_customer_city = $co['city'] ?? '';
                $cs_customer_state = $co['state'] ?? '';
                $cs_customer_id = $co['user_id'];
            }
            $cs_initials = '';
            $nameParts = explode(' ', trim($cs_customer_name));
            $cs_initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1));
            if (count($nameParts) > 1) $cs_initials .= mb_strtoupper(mb_substr(end($nameParts), 0, 1));

            $cs_actual_debt = 0;
            if ($cs_customer_id > 0) {
                $cs_total_bought_db = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = $cs_customer_id AND status != 'canceled'")->fetchColumn() ?: 0;
                $cs_total_payments_db = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE user_id = $cs_customer_id")->fetchColumn() ?: 0;
                $cs_actual_debt = $cs_total_bought_db - $cs_total_payments_db;
            }
        ?>
        <div class="customer-summary-card">
            <div class="customer-summary-close">
                <a href="orders.php">✕ Limpar Filtro</a>
            </div>
            <div class="customer-summary-header">
                <div class="customer-summary-avatar"><?php echo $cs_initials; ?></div>
                <div>
                    <div class="customer-summary-name"><?php echo htmlspecialchars($cs_customer_name); ?></div>
                    <div class="customer-summary-sub">
                        <?php if ($cs_customer_city): ?>
                            📍 <?php echo htmlspecialchars($cs_customer_city . '/' . $cs_customer_state); ?>
                        <?php endif; ?>
                        <?php if ($cs_customer_phone): ?>
                            &nbsp;•&nbsp; 📱 <?php echo htmlspecialchars($cs_customer_phone); ?>
                        <?php endif; ?>
                        &nbsp;•&nbsp; Cliente desde <?php echo date('d/m/Y', strtotime($cs_first_order)); ?>
                        &nbsp;•&nbsp; <a href="customer-details.php?id=<?php echo $cs_customer_id; ?>" style="color:#f39c12; text-decoration:none; font-weight:700;">Ver Perfil Completo →</a>
                        <?php if ($cs_total_pending > 0 && $cs_actual_debt < $cs_pending_value): ?>
                            &nbsp;•&nbsp; 
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Deseja que o sistema marque automaticamente os pedidos mais antigos como PAGO usando os créditos já depositados pelo cliente?');">
                                <input type="hidden" name="action" value="auto_quitar">
                                <input type="hidden" name="customer_id" value="<?php echo $cs_customer_id; ?>">
                                <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($cs_customer_name); ?>">
                                <button type="submit" style="background:#e67e22; color:#fff; border:none; padding:4px 12px; border-radius:6px; font-size:0.75rem; font-weight:700; cursor:pointer; transition:0.2s;"><i class="fas fa-magic"></i> Auto-Quitar Pedidos por Crédito</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="customer-summary-stats">
                <div class="cs-stat">
                    <div class="cs-val" style="color:#f39c12"><?php echo $cs_total_orders; ?></div>
                    <div class="cs-lbl">Total de Pedidos</div>
                </div>
                <div class="cs-stat">
                    <div class="cs-val" style="color:#fff"><span class="finance-value">R$ <?php echo number_format($cs_total_value, 2, ',', '.'); ?></span></div>
                    <div class="cs-lbl">Valor Total</div>
                </div>
                <div class="cs-stat">
                    <div class="cs-val" style="color:#2ecc71"><?php echo $cs_total_paid + $cs_total_shipped; ?></div>
                    <div class="cs-lbl">Pagos / Enviados</div>
                </div>
                <div class="cs-stat">
                    <div class="cs-val" style="color:<?php echo $cs_total_pending > 0 ? '#e74c3c' : '#2ecc71'; ?>"><?php echo $cs_total_pending; ?></div>
                    <div class="cs-lbl">Pendentes</div>
                </div>
                <?php if ($cs_pending_value > 0): ?>
                <div class="cs-stat">
                    <div class="cs-val" style="color:#e74c3c"><span class="finance-value">R$ <?php echo number_format($cs_pending_value, 2, ',', '.'); ?></span></div>
                    <div class="cs-lbl">Débito Pendente</div>
                </div>
                <?php endif; ?>
                <div class="cs-stat" style="border: 1px dashed <?php echo $cs_actual_debt > 0 ? 'rgba(231, 76, 60, 0.4)' : 'rgba(46, 204, 113, 0.4)'; ?>; background: rgba(0,0,0,0.15);">
                    <div class="cs-val" style="color:<?php echo $cs_actual_debt > 0 ? '#e74c3c' : '#2ecc71'; ?>"><span class="finance-value">R$ <?php echo number_format(abs($cs_actual_debt), 2, ',', '.'); ?></span></div>
                    <div class="cs-lbl" style="font-weight:bold; color:<?php echo $cs_actual_debt > 0 ? '#e74c3c' : '#2ecc71'; ?>"><?php echo $cs_actual_debt > 0 ? 'Saldo Devedor (Real)' : ($cs_actual_debt < 0 ? 'Saldo Credor' : 'Saldo Zerado'); ?></div>
                </div>
                <div class="cs-stat">
                    <div class="cs-val" style="color:#888; font-size:1rem;"><?php echo date('d/m/Y', strtotime($cs_last_order)); ?></div>
                    <div class="cs-lbl">Último Pedido</div>
                </div>
            </div>
        </div>
        <?php elseif ($f_customer && count($orders) == 0): ?>
        <div style="background:linear-gradient(135deg,rgba(231,76,60,.08),rgba(231,76,60,.02)); border:1px solid rgba(231,76,60,.25); border-radius:12px; padding:1.5rem 2rem; margin-bottom:1.5rem; text-align:center; animation:slideDown 0.35s ease-out;">
            <div style="font-size:2rem; margin-bottom:8px;">🔍</div>
            <div style="color:#e74c3c; font-weight:700; font-size:1.1rem;">Nenhum pedido encontrado</div>
            <div style="color:#888; font-size:0.85rem; margin-top:4px;">Nenhum pedido para o cliente "<strong style="color:#fff;"><?php echo htmlspecialchars($f_customer); ?></strong>"</div>
            <a href="orders.php" style="display:inline-block; margin-top:12px; background:#333; color:#fff; padding:8px 20px; border-radius:6px; text-decoration:none; font-size:0.85rem;">← Voltar para todos os pedidos</a>
        </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return handleBulkSubmit(event);" id="bulkForm" style="margin-bottom:10px;">
            
            <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                <select name="bulk_action" id="bulk_action_select" style="background:#222; color:#fff; border:1px solid #444; border-radius:4px; padding:5px; height:35px;">
                    <option value="">Ações em Massa</option>
                    <option value="notify_auto">🟢 Habilitar Notificação Automática</option>
                    <option value="notify_manual">⚪ Desabilitar Notificação Automática</option>
                    <option value="sync_notify_selected">📦 Sincronizar e Disparar Movimentação</option>
                    <option value="notify_initial">🔔 Notificar Envio (Inicial)</option>
                    <option value="delete">🗑️ Excluir Selecionados</option>
                </select>
                <button type="submit" class="btn btn-sm" style="background:var(--primary); color:#000; height:35px; border-radius:4px; padding:0 15px; font-weight:bold;">Aplicar</button>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Cliente / Endereço</th>
                            <th>Envio</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Status / Rastreio</th>
                            <th>Itens</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o):
                            // Get items
                            $items = $pdo->query("SELECT * FROM order_items WHERE order_id = {$o['id']}")->fetchAll();
                            ?>
                            <tr>
                                <td><input type="checkbox" name="selected_orders[]" value="<?php echo $o['id']; ?>"></td>
                                <td><a href="edit_order.php?id=<?php echo $o['id']; ?>" style="color:var(--primary); font-weight:bold;">#<?php echo $o['id']; ?></a></td>
                                <td><?php echo date('d/m H:i', strtotime($o['created_at'])); ?></td>
                                <td>
                                    <a href="customer-details.php?id=<?php echo $o['user_id']; ?>" style="color:#fff; text-decoration:none;">
                                        <strong><?php echo htmlspecialchars($o['user_name']); ?></strong>
                                    </a><br>
                                    <?php if (!empty($o['zipcode'])): ?>
                                        <small style="color:#ccc;">
                                            <?php echo htmlspecialchars($o['city'] . '/' . $o['state']); ?><br>
                                            CEP: <?php echo htmlspecialchars($o['zipcode']); ?>
                                        </small><br>
                                    <?php endif; ?>
                                    <a href="https://api.whatsapp.com/send?phone=<?php echo preg_replace('/\D/', '', $o['phone']); ?>&text=Ol%C3%A1%2C%20sobre%20o%20pedido%20%23<?php echo $o['id']; ?>"
                                        target="_blank" style="color:var(--success); font-size:0.8rem;">
                                        WhatsApp
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($o['shipping_method'] ?? '—'); ?></td>
                                <td>
                                    <strong><span class="finance-value">R$ <?php echo number_format($o['total_amount'], 2, ',', '.'); ?></span></strong>
                                    <?php 
                                    $shipCost = (float)($o['shipping_cost'] ?? 0);
                                    $profit = (float)$o['total_amount'] - (float)$o['total_cost'] - $shipCost;
                                    $profitColor = $profit >= 0 ? '#2ecc71' : '#e74c3c';
                                    ?>
                                    <div style="font-size:0.75rem; color:<?php echo $profitColor; ?>; margin-top:3px; font-weight:bold;">
                                        Lucro: <span class="finance-value">R$ <?php echo number_format($profit, 2, ',', '.'); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $statusClass = 'pay-pending';
                                    $statusIcon = '🔴';
                                    $statusLabel = 'Pendente';
                                    if ($o['status'] == 'paid') { $statusClass = 'pay-paid'; $statusIcon = '🟢'; $statusLabel = 'Pago'; }
                                    elseif ($o['status'] == 'shipped') { $statusClass = 'pay-shipped'; $statusIcon = '🟡'; $statusLabel = 'Enviado'; }
                                    elseif ($o['status'] == 'canceled') { $statusClass = 'pay-canceled'; $statusIcon = '⚫'; $statusLabel = 'Cancelado'; }
                                    ?>
                                    <span class="pay-badge <?php echo $statusClass; ?>"><?php echo $statusIcon; ?> <?php echo $statusLabel; ?></span>
                                    
                                    <?php if (!empty($o['payment_method'])): ?>
                                        <div style="margin-top:5px; font-size:0.75rem; font-weight:bold; color:#fff; display:flex; align-items:center; gap:5px;">
                                            <?php 
                                            $pMethod = strtoupper($o['payment_method']);
                                            $isPix = (strpos($pMethod, 'PIX') !== false);
                                            ?>
                                            <span style="<?php echo $isPix ? 'background:#00e676; color:#000; padding:2px 6px; border-radius:4px;' : 'color:#888;'; ?>">
                                                <?php echo $isPix ? '⚡ PIX' : '💳 ' . $o['payment_method']; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($o['status'] == 'pending'): ?>
                                        <?php 
                                        $daysPending = (int)((time() - strtotime($o['created_at'])) / 86400);
                                        $urgentClass = $daysPending >= 30 ? 'urgent' : '';
                                        ?>
                                        <div class="pending-time <?php echo $urgentClass; ?>">
                                            ⏱️ há <?php echo $daysPending; ?> dia<?php echo $daysPending != 1 ? 's' : ''; ?>
                                            <?php if ($daysPending >= 30): ?>
                                                <span style="background:#e74c3c; color:#fff; padding:1px 5px; border-radius:3px; font-size:0.65rem;">URGENTE</span>
                                            <?php elseif ($daysPending >= 7): ?>
                                                <span style="background:#f39c12; color:#000; padding:1px 5px; border-radius:3px; font-size:0.65rem;">ATRASO</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Receipt Thumbnail -->
                                    <?php
                                    try {
                                        $rcpt = $pdo->prepare("SELECT file_path FROM payment_receipts WHERE order_id = ? LIMIT 1");
                                        $rcpt->execute([$o['id']]);
                                        $receipt = $rcpt->fetch();
                                    } catch(Exception $e) { $receipt = null; }
                                    ?>
                                    <div style="margin-top:4px; display:flex; align-items:center; gap:4px;">
                                        <?php if ($receipt): ?>
                                            <img src="../<?php echo htmlspecialchars($receipt['file_path']); ?>" class="receipt-thumb" onclick="window.open('../<?php echo htmlspecialchars($receipt['file_path']); ?>','_blank')" title="Ver Comprovante">
                                            <span style="color:#2ecc71; font-size:0.7rem;">✓ Comprovante</span>
                                        <?php else: ?>
                                            <button type="button" class="btn-receipt" onclick="openReceiptUpload(<?php echo $o['id']; ?>)" title="Anexar Comprovante">📎 Anexar</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <select id="status_<?php echo $o['id']; ?>" onchange="updateStatus(<?php echo $o['id']; ?>, this.value)"
                                        style="padding:5px; margin:0; font-size:0.8rem; background:#111; color:#fff; border:1px solid #444; width:100%; cursor:pointer;">
                                        <option value="pending" <?php echo ($o['status'] == 'pending') ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="paid" <?php echo ($o['status'] == 'paid') ? 'selected' : ''; ?>>Pago</option>
                                        <option value="shipped" <?php echo ($o['status'] == 'shipped') ? 'selected' : ''; ?>>Enviado</option>
                                        <option value="canceled" <?php echo ($o['status'] == 'canceled') ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                    
                                    <?php if (!empty($o['tracking_code'])): ?>
                                        <div style="display:flex; flex-direction:column; gap:5px; margin-top:5px;">
                                            <?php 
                                            // Split by commas, semicolons or spaces
                                            $track_codes = preg_split('/[\s,;]+/', $o['tracking_code']);
                                            $track_codes = array_filter(array_map('trim', $track_codes));
                                            foreach ($track_codes as $tc):
                                            ?>
                                                <div style="display:flex; align-items:center; gap:5px;">
                                                    <a href="https://www.melhorrastreio.com.br/rastreio/<?php echo urlencode($tc); ?>" target="_blank" style="text-decoration:none; flex:1;">
                                                        <div style="font-size:0.7rem; background:#000; padding:3px 5px; border-radius:4px; color:var(--primary); font-family:monospace; border:1px solid #333; text-align:center; box-sizing:border-box;">
                                                            📦 <?php echo $tc; ?>
                                                            <?php if(!empty($o['last_tracking_status'])): ?>
                                                                <br><span style="color:<?php echo getMeStatusColor($o['last_tracking_status']); ?>; font-size:0.65rem; font-weight:bold; display:inline-block; margin-top:2px;"><?php echo strtoupper(getMeStatusTranslation($o['last_tracking_status'])); ?></span>
                                                            <?php endif; ?>
                                                            <br>
                                                            <span style="font-size:0.55rem; color:<?php echo ($o['wa_notify_tracking'] ?? 1) ? '#00e676' : '#888'; ?>; font-weight:bold; display:inline-block; margin-top:1px;">
                                                                ● Notificação: <?php echo ($o['wa_notify_tracking'] ?? 1) ? 'AUTO' : 'MANUAL'; ?>
                                                            </span>
                                                        </div>
                                                    </a>
                                                    <a href="?sync_track=<?php echo $o['id']; ?>" class="btn-wa-notif" style="background:#e67e22; padding:4px 8px; border-radius:4px; color:#fff;" title="Sincronizar Status">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                    <button type="button" onclick="sendOrderWaNotify(<?php echo $o['id']; ?>, this)" class="btn-wa-notif" style="padding:4px 8px; border-radius:4px;" title="Enviar Rastreio via WhatsApp">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <input type="text" placeholder="Editar Rastreio..." 
                                        value="<?php echo htmlspecialchars($o['tracking_code'] ?? ''); ?>"
                                        style="padding:5px; margin-top:5px; font-size:0.7rem; width:100%; box-sizing:border-box; background:transparent; border:1px dashed #444; color:#888;" 
                                        onblur="updateTracking(<?php echo $o['id']; ?>, this.value)">
                                </td>

                <td style="font-size:0.85rem; color:#ccc;">
                                    <?php foreach ($items as $i): ?>
                                        <div><?php echo $i['quantity']; ?>x <?php echo htmlspecialchars($i['product_name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:5px; margin-top:5px; flex-wrap:wrap;">
                                        
                                        <a href="edit_order.php?id=<?php echo $o['id']; ?>" class="btn-sm" 
                                           style="background:var(--warning); color:#000;" 
                                           title="Editar Pedido">✏️</a>

                                        <!-- Notif Toggle -->
                                        <a href="?toggle_wa=<?php echo $o['id']; ?>" class="btn-wa-notif" 
                                           style="background:<?php echo ($o['wa_notify_tracking'] ?? 1) ? '#2ecc71' : '#555'; ?>; color:#fff; padding:4px 8px; border-radius:4px;" 
                                           title="Notificações Automáticas (WhatsApp): <?php echo ($o['wa_notify_tracking'] ?? 1) ? 'ATIVADO' : 'DESATIVADO'; ?>">
                                            <i class="fas <?php echo ($o['wa_notify_tracking'] ?? 1) ? 'fa-bell' : 'fa-bell-slash'; ?>"></i>
                                        </a>

                                        <a href="order-print.php?id=<?php echo $o['id']; ?>" target="_blank" class="btn-sm"
                                            style="background:#333; color:#fff;" title="Imprimir Pedido">🖨️</a>

                                        <a href="order-print-tag.php?id=<?php echo $o['id']; ?>" target="_blank" class="btn-sm"
                                            style="background:#8B263E; color:#FFF;" title="Imprimir Etiquetas & Tags Helena Flores">🏷️ Tags</a>

                                        <a href="order-declaration.php?id=<?php echo $o['id']; ?>" target="_blank"
                                            class="btn-sm" style="background:#e67e22; color:#fff;"
                                            title="Declaração de Conteúdo (Correios)">📄</a>

                                        <a href="?export_order=<?php echo $o['id']; ?>" class="btn-sm"
                                            style="background:#27ae60; color:#fff;" title="Exportar (Bling/Tiny/CSV)">📥</a>

                                        <?php
                                        $me_oid = $o['me_order_id'];
                                        $is_lalamove = ($o['shipping_method'] === 'Lalamove');
                                        $has_track = !empty($o['tracking_code']) && strpos($o['tracking_code'], 'ORD-') === false;
                                        ?>
                                        <?php if (!$is_lalamove): ?>
                                            <a href="javascript:void(0)" onclick="openMEQuote(<?php echo $o['id']; ?>)" class="btn-sm"
                                                style="background:#e74c3c; color:#fff;" title="Cotar Frete (Melhor Envio)">📦</a>
                                            <?php if (!empty($me_oid)): ?>
                                                <?php if (!$has_track): ?>
                                                    <a href="javascript:void(0)" onclick="syncOrder(<?php echo $o['id']; ?>, '<?php echo $me_oid; ?>')" class="btn-sm"
                                                        style="background:#e67e22; color:#fff;" title="Sincronizar ME">🔄</a>
                                                <?php else: ?>
                                                    <a href="javascript:void(0)" onclick="printMELabel('<?php echo $me_oid; ?>')" class="btn-sm"
                                                        style="background:#2ecc71; color:#000;" title="Imprimir Etiqueta ME">🏷️</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($llm->isConfigured()): ?>
                                            <a href="javascript:void(0)" onclick="openLalamoveQuote(<?php echo $o['id']; ?>)" class="btn-sm"
                                                style="background:#FF6600; color:#fff;" title="Express Lalamove 🏍️">🏍️</a>
                                            <?php if ($is_lalamove): ?>
                                                <a href="javascript:void(0)" onclick="checkLalamoveStatus('<?php echo $me_oid; ?>', <?php echo $o['id']; ?>)" class="btn-sm"
                                                    style="background:#FF6600; color:#fff;" title="Status Lalamove">🔄</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="javascript:void(0)" onclick="openUberQuote(<?php echo $o['id']; ?>)" class="btn-sm" style="background:#000; color:#fff;" title="Uber Delivery 🚙">🚙</a>
                                         <div style="display:flex; gap:3px;">
                                            <?php if($has_track || $is_lalamove): ?>
                                                <a href="javascript:void(0)" onclick="sendOrderWaNotify(<?php echo $o['id']; ?>, this)" class="btn-wa-notif" title="Notificar Envio/Rastreio">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <button type="button" onclick="openVipModal(<?php echo $o['id']; ?>, '<?php echo htmlspecialchars($o['user_name']); ?>')" class="btn-wa-notif btn-wa-vip" title="Atenção Personalizada (Atendimento)">
                                                <i class="fas fa-comment-dots"></i>
                                            </button>
                                         </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Hidden Form for Status Update -->
        <form id="statusForm" method="POST" style="display:none;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="st_oid">
            <input type="hidden" name="status" id="st_val">
            <input type="hidden" name="tracking_code" id="st_track">
        </form>

        <!-- LLM QUOTE MODAL -->
        <div id="llmModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#1a1e26;border:2px solid #FF6600;border-radius:16px;padding:2rem;max-width:600px;width:90%;max-height:80vh;overflow-y:auto">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                    <h2 style="color:#FF6600">🏍️ Lalamove Express</h2>
                    <button onclick="closeLlmModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
                </div>
                <div id="llmLoading" style="text-align:center;padding:2rem;color:#888">Buscando veículos próximos...</div>
                
                <div style="margin-bottom:15px; background:rgba(255,255,255,0.05); padding:10px; border-radius:8px;">
                    <label style="display:flex; align-items:center; gap:10px; color:#bbb; font-size:0.9rem;">
                        <span>Tipo de Entrega:</span>
                        <select id="llmDeliveryTier" onchange="fetchLalamoveQuotes()" style="background:#222;color:#fff;border:1px solid #444;padding:8px;border-radius:4px;flex:1">
                            <option value="regular">🟢 Regular</option>
                            <option value="priority">⚡ Prioridade</option>
                            <option value="grouped">📦 Agrupado / Compartilhado</option>
                        </select>
                    </label>
                </div>

                <div id="llmResults" style="display:none"></div>
                <div id="llmActions" style="display:none;margin-top:1rem;text-align:right">
                    <label style="display:inline-flex;align-items:center;gap:8px;margin-bottom:10px;color:#bbb;font-size:0.9rem;cursor:pointer;width:100%">
                        <span style="flex:1">Forma de Pagamento:</span>
                        <select id="llmPaymentMethod" style="background:#222;color:#fff;border:1px solid #444;padding:5px;border-radius:4px;">
                            <option value="WALLET">Carteira Fight Arcade (Pré-pago)</option>
                            <option value="CASH">Dinheiro - Receber no Local (Cliente Paga)</option>
                        </select>
                    </label>
                    <label id="llmPriorityFeeGroup" style="display:none;align-items:center;gap:8px;margin-bottom:10px;color:#bbb;font-size:0.9rem;width:100%">
                        <span style="flex:1">Gorjeta (R$):</span>
                        <input type="number" id="llmPriorityFee" value="5" min="1" step="1" style="background:#222;color:#fff;border:1px solid #444;padding:5px;border-radius:4px;width:80px">
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:8px;margin-bottom:15px;color:#bbb;font-size:0.9rem;cursor:pointer">
                        <input type="checkbox" id="llmNotifySms" checked>
                        Avisar cliente via SMS (Lalamove) sobre o envio
                    </label><br>
                    <button onclick="buyLalamoveShipping()" class="btn" style="background:#FF6600;color:#fff;padding:12px 24px" id="btnLlmBuy" disabled>
                        🏍️ Chamar Entrega Expressa
                    </button>
                </div>
                <div id="llmFeedback" style="display:none;margin-top:1rem"></div>
            </div>
        </div>

        <!-- UBER QUOTE MODAL -->
        <div id="uberModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#000;border:2px solid #00ff88;border-radius:16px;padding:2rem;max-width:550px;width:90%;max-height:80vh;overflow-y:auto;color:#fff">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
                    <h2 style="margin:0;display:flex;align-items:center;gap:12px">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Uber_logo_2018.svg/2560px-Uber_logo_2018.svg.png" style="height:20px;filter:invert(1)">
                        Direct
                    </h2>
                    <button onclick="closeUberModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
                </div>
                
                <div id="uberLoading" style="text-align:center;padding:2rem;color:#00ff88">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;margin-bottom:1rem"></i><br>
                    Consultando Uber...
                </div>
                <div id="uberResults" style="display:none"></div>
                <div id="uberActions" style="display:none;margin-top:1.5rem;text-align:right">
                    <button onclick="buyUber()" style="background:#00ff88;color:#000;padding:12px 24px;border:none;border-radius:8px;font-weight:bold;font-size:1rem;cursor:pointer" id="btnUberBuy" disabled>🚗 Chamar Uber Direct</button>
                </div>
                <div id="uberFeedback" style="display:none;margin-top:1rem"></div>
            </div>
        </div>

        <!-- ME QUOTE MODAL -->
        <div id="meModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#1a1e26;border:2px solid #e74c3c;border-radius:16px;padding:2rem;max-width:600px;width:90%;max-height:80vh;overflow-y:auto">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                    <h2>📦 Cotar Frete - Melhor Envio</h2>
                    <button onclick="closeMEModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
                </div>
                <div style="background:#111; padding:10px; border-radius:8px; margin-bottom:1rem; border:1px solid #333; text-align:left;">
                    <label style="color:#888; font-size:0.8rem; display:block; margin-bottom:5px;">📐 Personalizar Caixa & Seguro (opcional)</label>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                        <input type="number" id="me_box_l" placeholder="Comp(cm)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Comprimento (cm)">
                        <input type="number" id="me_box_w" placeholder="Larg(cm)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Largura (cm)">
                        <input type="number" id="me_box_h" placeholder="Alt(cm)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Altura (cm)">
                        <input type="number" id="me_box_wt" step="0.1" placeholder="Peso(kg)" style="flex:1; min-width:70px; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Peso Total (kg)">
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="number" id="me_insurance" placeholder="Valor Segurado (R$)" style="flex:2; padding:8px; background:#222; border:1px solid #444; color:#fff; border-radius:4px;" title="Valor Segurado (R$)" min="0">
                        <button type="button" onclick="openMEQuote(meCurrentOrder, true)" style="background:#f1c40f; color:#000; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; cursor:pointer; display:flex; align-items:center; gap:5px;" title="Recalcular Frete e Seguro">Recalcular 🔄</button>
                    </div>
                    <small style="color:#aaa; font-size:0.7rem; display:block; margin-top:5px;">⚠️ Nota: O seguro máximo para envios não comerciais é R$ 1.500,00.</small>
                </div>
                <div id="meLoading" style="text-align:center;padding:2rem;color:#888">Calculando fretes...</div>
                <div id="meResults" style="display:none"></div>
                <div id="meActions" style="display:none;margin-top:1rem;text-align:right">
                    <button onclick="buyMEShipping()" class="btn" style="background:#2ecc71;color:#000;padding:12px 24px" id="btnMEBuy" disabled>
                        💳 Comprar e Gerar Etiqueta
                    </button>
                </div>
                <div id="meFeedback" style="display:none;margin-top:1rem"></div>
            </div>
        </div>

        <script>
            function updateStatus(oid, status) {
                if(confirm('Mudar status para '+status+'?')) {
                    var track = document.querySelector(`input[onblur*="updateTracking(${oid}"]`)?.value || '';
                    document.getElementById('st_oid').value = oid;
                    document.getElementById('st_val').value = status;
                    document.getElementById('st_track').value = track;
                    document.getElementById('statusForm').submit();
                }
            }
            function updateTracking(oid, code) {
                 var status = document.getElementById('status_'+oid).value;
                 document.getElementById('st_oid').value = oid;
                 document.getElementById('st_val').value = status;
                 document.getElementById('st_track').value = code;
                 document.getElementById('statusForm').submit();
            }

            // --- MELHOR ENVIO ---
            let meCurrentOrder = 0;
            let meSelectedService = 0;

            function openMEQuote(orderId, isRecalculate = false) {
                meCurrentOrder = orderId;
                meSelectedService = 0;
                if(!isRecalculate) {
                    document.getElementById('meModal').style.display = 'flex';
                }
                document.getElementById('meLoading').style.display = 'block';
                document.getElementById('meResults').style.display = 'none';
                document.getElementById('meActions').style.display = 'none';
                document.getElementById('meFeedback').style.display = 'none';
                document.getElementById('btnMEBuy').disabled = true;

                let extraParams = "";
                if(isRecalculate) {
                    extraParams = `&box_w=${document.getElementById('me_box_w').value}&box_h=${document.getElementById('me_box_h').value}&box_l=${document.getElementById('me_box_l').value}&box_wt=${document.getElementById('me_box_wt').value}&box_ins=${document.getElementById('me_insurance').value}`;
                }

                fetch(`melhorenvio.php?ajax_quote=1&order_id=${orderId}${extraParams}`)
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('meLoading').style.display = 'none';
                        if (data.error) {
                            document.getElementById('meResults').innerHTML = `<div style="color:#e74c3c;padding:1rem">${data.error}</div>`;
                            document.getElementById('meResults').style.display = 'block';
                            return;
                        }
                        if(!isRecalculate) {
                            document.getElementById('me_insurance').value = Math.min(parseFloat(data.order.total_amount), 1500.00);
                        }
                        let quotesArray = [];
                        if (Array.isArray(data.quotes)) {
                            quotesArray = data.quotes;
                        } else if (data.quotes && typeof data.quotes === 'object') {
                            if (data.quotes.message || data.quotes.error) {
                                document.getElementById('meResults').innerHTML = `<div style="color:#e74c3c;padding:1rem;border:1px solid #e74c3c;border-radius:8px;background:rgba(231,76,60,0.1);"><strong>Erro do Melhor Envio:</strong><br>${data.quotes.message || data.quotes.error}<br><small>${JSON.stringify(data.quotes.errors || '')}</small></div>`;
                                document.getElementById('meResults').style.display = 'block';
                                return;
                            }
                            quotesArray = Object.values(data.quotes);
                        }

                        let html = '';
                        let hasValid = false;
                        quotesArray.forEach(q => {
                            if (!q.id || !q.name) return; // Prevent rendering garbage
                            if (q.error) {
                                html += `<div style="display:flex;align-items:center;gap:15px;padding:12px;background:#222;border-radius:6px;margin-bottom:6px;border:1px solid #333;opacity:0.5">
                                    <div style="flex:1"><strong>${q.name}</strong><br><small style="color:#e74c3c">${q.error}</small></div>
                                </div>`;
                                return;
                            }
                            hasValid = true;
                            const price = parseFloat(q.custom_price || q.price);
                            const days = q.custom_delivery_time || q.delivery_time;
                            const logo = q.company?.picture || '';
                            html += `<div onclick="selectMEQuote(this,${q.id})" style="display:flex;align-items:center;gap:15px;padding:12px;background:#222;border-radius:6px;margin-bottom:6px;cursor:pointer;border:1px solid #333" data-svc="${q.id}">
                                ${logo ? `<img src="${logo}" style="width:60px;height:30px;object-fit:contain;background:#fff;border-radius:4px;padding:2px">` : ''}
                                <div style="flex:1"><strong>${q.name}</strong><br><small style="color:#888">${q.company?.name||''} - ${days} dias úteis</small></div>
                                <div style="font-weight:bold;color:#2ecc71;font-size:1.1rem">R$ ${price.toLocaleString('pt-BR',{minimumFractionDigits:2})}</div>
                            </div>`;
                        });
                        if (!hasValid && !html) html = '<div style="color:#e74c3c;padding:1rem">Nenhuma transportadora disponível para esta rota ou dimensões.</div>';
                        document.getElementById('meResults').innerHTML = html;
                        document.getElementById('meResults').style.display = 'block';
                        document.getElementById('meActions').style.display = 'block';
                    })
                    .catch(err => {
                        document.getElementById('meLoading').style.display = 'none';
                        document.getElementById('meResults').innerHTML = `<div style="color:#e74c3c">${err.message}</div>`;
                        document.getElementById('meResults').style.display = 'block';
                    });
            }

            function selectMEQuote(el, svc) {
                document.querySelectorAll('#meResults > div').forEach(d => d.style.borderColor = '#333');
                el.style.borderColor = '#2ecc71';
                meSelectedService = svc;
                document.getElementById('btnMEBuy').disabled = false;
            }

            function closeMEModal() { document.getElementById('meModal').style.display = 'none'; }

            function handleBulkSubmit(event) {
                const action = document.getElementById('bulk_action_select').value;
                if (!action) {
                    alert('Por favor, selecione uma ação em massa.');
                    event.preventDefault();
                    return false;
                }
                
                const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]:checked');
                if (checkboxes.length === 0) {
                    alert('Nenhum pedido selecionado.');
                    event.preventDefault();
                    return false;
                }

                let confirmMsg = '';
                if (action === 'delete') {
                    confirmMsg = 'Tem certeza que deseja excluir os pedidos selecionados?';
                } else if (action === 'sync_notify_selected') {
                    confirmMsg = 'Sincronizar status e disparar notificação de movimentação para os clientes selecionados via WhatsApp?';
                } else if (action === 'notify_initial') {
                    confirmMsg = 'Enviar notificação de envio inicial para os clientes selecionados?';
                }

                if (confirmMsg && !confirm(confirmMsg)) {
                    event.preventDefault();
                    return false;
                }
                return true;
            }

            function buyMEShipping() {
                if (!meSelectedService) return;
                if (!confirm('Comprar frete e gerar etiqueta?')) return;
                const btn = document.getElementById('btnMEBuy');
                btn.disabled = true; btn.textContent = 'Processando...';
                const fd = new FormData();
                fd.append('ajax_buy','1');
                fd.append('order_id', meCurrentOrder);
                fd.append('service_id', meSelectedService);
                // Dimensions/Insurance for purchase
                fd.append('box_w', document.getElementById('me_box_w').value);
                fd.append('box_h', document.getElementById('me_box_h').value);
                fd.append('box_l', document.getElementById('me_box_l').value);
                fd.append('box_wt', document.getElementById('me_box_wt').value);
                fd.append('box_ins', document.getElementById('me_insurance').value);
                fetch('melhorenvio.php',{method:'POST',body:fd})
                    .then(r=>r.json())
                    .then(data=>{
                        const fb = document.getElementById('meFeedback');
                        fb.style.display = 'block';
                        if(data.success){
                            let btnPrint = '';
                            if (data.paid) {
                                btnPrint = `<button onclick="printMELabel('${data.me_order_id}')" class="btn" style="margin-top:10px;background:#f1c40f;color:#000">🏷️ Imprimir Etiqueta</button>`;
                            } else {
                                btnPrint = `<div style="margin-top:10px; font-size:0.85rem; color:#f1c40f;">
                                    ⚠️ <strong>Saldo Insuficiente no Melhor Envio.</strong><br>
                                    O pedido foi enviado para o seu <strong>CARRINHO</strong> lá no site. 
                                    Pague por lá e depois use o botão 🔄 no pedido para baixar o rastreio.
                                    <br><a href="https://melhorenvio.com.br/carrinho" target="_blank" style="color:#fff; text-decoration:underline;">Ir para o Carrinho do Melhor Envio</a>
                                </div>`;
                            }
                            fb.innerHTML = `<div style="background:rgba(46,204,113,.1);border:1px solid #2ecc71;padding:15px;border-radius:8px;color:#2ecc71">
                                ✅ ${data.message}<br>
                                ${btnPrint}
                                <button onclick="location.reload()" class="btn btn-secondary" style="margin-top:10px">Fechar e Atualizar</button>
                            </div>`;
                        } else {
                            fb.innerHTML = `<div style="color:#e74c3c;padding:10px">❌ Erro<br><small>${JSON.stringify(data.error)}</small></div>`;
                            btn.disabled = false; btn.textContent = '💳 Comprar e Gerar Etiqueta';
                        }
                    });
            }

            function printMELabel(meId) {
                fetch(`melhorenvio.php?print_label=${meId}`)
                    .then(r=>r.json())
                    .then(data=>{
                        if(data.url) window.open(data.url,'_blank');
                        else alert('Etiqueta processando. Tente novamente em segundos.');
                    });
            }

            function syncOrder(oid, meId) {
                if(!confirm('Sincronizar rastreio com Melhor Envio?')) return;
                fetch(`melhorenvio.php?ajax_sync=1&order_id=${oid}&me_id=${meId}`)
                    .then(r=>r.json())
                    .then(data=>{
                        if(data.success) {
                            alert('Sincronizado! Rastreio: ' + data.tracking);
                            location.reload();
                        } else {
                            alert('Ainda não pago ou sem rastreio disponível.');
                        }
                    });
            }

            // --- LALAMOVE ---
            let llmCurrentOrder = 0;
            let llmSelectedQuote = null;

            function openLalamoveQuote(orderId) {
                llmCurrentOrder = orderId;
                llmSelectedQuote = null;
                document.getElementById('llmModal').style.display = 'flex';
                fetchLalamoveQuotes();
            }

            function fetchLalamoveQuotes() {
                const orderId = llmCurrentOrder;
                const tier = document.getElementById('llmDeliveryTier').value;
                document.getElementById('llmLoading').style.display = 'block';
                document.getElementById('llmResults').style.display = 'none';
                document.getElementById('llmActions').style.display = 'none';
                document.getElementById('llmFeedback').style.display = 'none';
                document.getElementById('btnLlmBuy').disabled = true;

                // Priority fee group visibility
                if (document.getElementById('llmPriorityFeeGroup')) {
                    document.getElementById('llmPriorityFeeGroup').style.display = (tier === 'priority' ? 'flex' : 'none');
                }

                // Step 1: Geocode Address
                fetch(`lalamove.php?ajax_geocode=1&cep=&order_id=${orderId}`)
                    .then(r => r.json())
                    .then(d => {
                        if(d.error) throw new Error(d.error);
                        
                        let queryParams = `ajax_quote=1&lat=${d.lat}&lng=${d.lng}&address=${encodeURIComponent(d.formatted_address)}`;
                        if (tier === 'grouped') queryParams += '&grouped=1';
                        
                        // Step 2: Get Quotes
                        return fetch(`lalamove.php?${queryParams}`);
                    })
                    .then(r => r.json())
                    .then(data => {
                        console.log("Resposta Lalamove:", data);
                        document.getElementById('llmLoading').style.display = 'none';
                        if(data.error) {
                            document.getElementById('llmResults').innerHTML = `<div style="color:#e74c3c;padding:1rem">${data.error}</div>`;
                            document.getElementById('llmResults').style.display = 'block';
                            return;
                        }
                        let html = '';
                        data.quotes.forEach((q, idx) => {
                            const hasError = !!q.error;
                            const price = parseFloat(q.total || 0).toLocaleString('pt-BR', {minimumFractionDigits:2});
                            const icon = {LALAGO:'🏍️', HATCHBACK:'🚗', CAR:'🚙', VAN:'🚐', UV_FIORINO:'🚐', TRUCK330:'🚛', TRUCK3_5T:'🚛'}[q.serviceType] || '📦';
                            html += `
                                <div class="quote-card ${hasError ? 'unavailable' : ''}" 
                                     onclick="${!hasError ? `selectLlmQuote(this, ${idx})` : ''}"
                                     style="display:flex;align-items:center;gap:15px;padding:12px;background:#222;border-radius:6px;margin-bottom:6px;cursor:${hasError?'not-allowed':'pointer'};border:1px solid #333">
                                    <div style="font-size:1.5rem">${q.label.split(' ')[0]}</div>
                                    <div style="flex:1">
                                        <strong>${q.label}</strong><br>
                                        <small style="color:${hasError?'#e74c3c':'#888'}">${hasError ? q.error : 'Express - Hoje'}</small>
                                    </div>
                                    <div style="font-weight:bold;color:#2ecc71;font-size:1.1rem">${hasError ? 'N/D' : 'R$ '+price}</div>
                                </div>`;
                        });
                        document.getElementById('llmResults').innerHTML = html;
                        document.getElementById('llmResults').style.display = 'block';
                        document.getElementById('llmActions').style.display = 'block';
                        // Store current quotes globally for selection
                        window.llmQuotes = data.quotes;
                    })
                    .catch(err => {
                        document.getElementById('llmLoading').style.display = 'none';
                        document.getElementById('llmResults').innerHTML = `<div style="color:#e74c3c;padding:1rem">Erro: ${err.message}</div>`;
                        document.getElementById('llmResults').style.display = 'block';
                    });
            }

            function selectLlmQuote(el, idx) {
                document.querySelectorAll('#llmResults > div').forEach(d => d.style.borderColor = '#333');
                el.style.borderColor = '#FF6600';
                llmSelectedQuote = window.llmQuotes[idx];
                document.getElementById('btnLlmBuy').disabled = false;
            }

            function closeLlmModal() { document.getElementById('llmModal').style.display = 'none'; }

            function buyLalamoveShipping() {
                if (!llmSelectedQuote) return;
                if (!confirm('Confirmar entrega expressa Lalamove? O valor será descontado da sua carteira Lalamove.')) return;
                
                const btn = document.getElementById('btnLlmBuy');
                btn.disabled = true; btn.textContent = 'Chamando...';

                // Fetch customer details from row for Order creation
                const row = Array.from(document.querySelectorAll('tbody tr')).find(tr => {
                    const td = tr.querySelector('td:nth-child(2)');
                    return td && td.textContent.trim() === '#' + llmCurrentOrder;
                });
                const name = row ? row.querySelector('strong').textContent : 'Cliente';
                const phoneLink = row ? row.querySelector('a[href*="api.whatsapp.com"]') : null;
                const phone = phoneLink ? phoneLink.href.split('phone=')[1].split('&')[0] : '';

                const fd = new FormData();
                fd.append('ajax_create_order', '1');
                fd.append('quotation_id', llmSelectedQuote.quotationId);
                fd.append('stops_json', JSON.stringify(llmSelectedQuote.stops));
                fd.append('recipient_name', name);
                fd.append('recipient_phone', phone);
                fd.append('local_order_id', llmCurrentOrder);
                fd.append('notify_sms', document.getElementById('llmNotifySms').checked ? '1' : '0');
                fd.append('payment_method', document.getElementById('llmPaymentMethod').value);
                fd.append('total_value', llmSelectedQuote.total);
                // Enviar priority fee se for prioridade
                const llmTier = document.getElementById('llmDeliveryTier');
                if (llmTier && llmTier.value === 'priority') {
                    fd.append('priority_fee', document.getElementById('llmPriorityFee').value);
                }

                fetch('lalamove.php', { method:'POST', body:fd })
                    .then(r => r.json())
                    .then(data => {
                        const fb = document.getElementById('llmFeedback');
                        fb.style.display = 'block';
                        if(data.success) {
                            let successHtml = `<div style="background:rgba(46,204,113,.1);border:1px solid #2ecc71;padding:15px;border-radius:8px;color:#2ecc71">
                                ✅ Pedido Lalamove Criado!<br>
                                ID: <strong>${data.orderId}</strong><br>`;
                            if (data.shareLink) {
                                successHtml += `<a href="${data.shareLink}" target="_blank" style="color:#2ecc71">📍 Link de rastreio</a><br>`;
                            }
                            successHtml += `<button onclick="location.reload()" class="btn btn-secondary" style="margin-top:10px">Fechar e Atualizar</button>
                            </div>`;
                            fb.innerHTML = successHtml;
                        } else {
                            fb.innerHTML = `<div style="color:#e74c3c;padding:10px">❌ Erro: ${data.error}</div>`;
                            btn.disabled = false; btn.textContent = '🏍️ Chamar Entrega Expressa';
                        }
                    });
            }
            function checkLalamoveStatus(llmOrderId, localId) {
                fetch(`lalamove.php?ajax_status=1&order_id=${llmOrderId}`)
                    .then(r => r.json())
                    .then(data => {
                        const s = data.status;
                        let info = `Status: ${s}`;
                        if (data.driverName) info += `\nMotorista: ${data.driverName} (${data.driverPhone})`;
                        if (data.driverPlate) info += `\nPlaca: ${data.driverPlate}`;
                        alert(info);
                    });
            }
            function sendOrderWaNotify(id, btn) {
                if(!confirm('Deseja enviar a notificação de rastreio para este cliente?')) return;
                
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.classList.add('loading');
                btn.style.pointerEvents = 'none';

                fetch(`orders.php?ajax_send_wa=1&id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            btn.innerHTML = '<i class="fas fa-check"></i>';
                            btn.classList.remove('loading');
                            btn.classList.add('success');
                            setTimeout(() => {
                                btn.innerHTML = originalHtml;
                                btn.classList.remove('success');
                                btn.style.pointerEvents = 'auto';
                            }, 3000);
                        } else {
                            btn.innerHTML = '<i class="fas fa-times"></i>';
                            btn.classList.remove('loading');
                            btn.classList.add('error');
                            
                            if (data.fallback_url) {
                                if (confirm("⚠️ O envio via API automática falhou ou está offline.\nDeseja abrir o WhatsApp com o link de rastreio para enviar manualmente ao cliente?")) {
                                    window.open(data.fallback_url, '_blank');
                                }
                            } else {
                                alert('Erro ao enviar: ' + (data.error || 'Verifique se o telefone está correto (DDD+9 dígitos) e se o WhatsApp está conectado na Central de Notificações.'));
                            }
                            
                            setTimeout(() => {
                                btn.innerHTML = originalHtml;
                                btn.classList.remove('error');
                                btn.style.pointerEvents = 'auto';
                            }, 4000);
                        }
                    })
                    .catch(err => {
                        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        alert('Erro de conexão com o servidor.');
                        btn.style.pointerEvents = 'auto';
                    });
            }
            function toggleAll(source) {
                const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');
                checkboxes.forEach(cb => { cb.checked = source.checked; });
            }
        </script>

        <!-- RECEIPT UPLOAD MODAL -->
        <div id="receiptModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:10001;align-items:center;justify-content:center" onclick="if(event.target==this)closeReceiptModal()">
            <div style="background:#1a1e2a;border:2px solid #2ecc71;border-radius:16px;padding:2rem;max-width:500px;width:90%;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                    <h2 style="color:#2ecc71;margin:0">📎 Anexar Comprovante</h2>
                    <button onclick="closeReceiptModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
                </div>
                <p style="color:#888;font-size:0.85rem;margin-bottom:1rem">Pedido: <strong id="receiptOrderLabel" style="color:#fff"></strong></p>
                <form id="receiptForm" enctype="multipart/form-data">
                    <input type="hidden" name="order_id" id="receipt_order_id">
                    <div id="receiptDropZone" style="border:2px dashed #444;border-radius:12px;padding:2rem;text-align:center;cursor:pointer;transition:.2s;margin-bottom:1rem" 
                        onclick="document.getElementById('receiptFile').click()"
                        ondragover="event.preventDefault();this.style.borderColor='#2ecc71';this.style.background='rgba(46,204,113,.05)'"
                        ondragleave="this.style.borderColor='#444';this.style.background='transparent'"
                        ondrop="event.preventDefault();this.style.borderColor='#444';this.style.background='transparent';document.getElementById('receiptFile').files=event.dataTransfer.files;previewReceipt()">
                        <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:#555;display:block;margin-bottom:.5rem"></i>
                        <span style="color:#888;font-size:0.85rem">Arraste uma imagem ou clique para selecionar</span>
                        <br><small style="color:#555">JPG, PNG, WEBP ou PDF — máx 10MB</small>
                    </div>
                    <input type="file" id="receiptFile" name="receipt" accept="image/*,.pdf" style="display:none" onchange="previewReceipt()">
                    <div id="receiptPreview" style="display:none;text-align:center;margin-bottom:1rem">
                        <img id="receiptPreviewImg" style="max-width:100%;max-height:200px;border-radius:8px;border:1px solid #333">
                    </div>
                    <div style="margin-bottom:1rem">
                        <label style="font-size:0.75rem;color:#888;display:block;margin-bottom:4px">Observação (opcional)</label>
                        <input type="text" name="notes" placeholder="Ex: PIX confirmado pelo Daniel" style="width:100%;padding:9px;background:#111;border:1px solid #333;color:#fff;border-radius:6px;font-size:0.85rem">
                    </div>
                    <button type="button" onclick="uploadReceipt()" class="btn" id="btnUploadReceipt" style="background:#2ecc71;color:#000;width:100%;padding:12px;font-weight:bold;font-size:0.95rem">
                        📤 Enviar Comprovante
                    </button>
                </form>
            </div>
        </div>

        <script>
            function openReceiptUpload(orderId) {
                document.getElementById('receipt_order_id').value = orderId;
                document.getElementById('receiptOrderLabel').textContent = '#' + orderId;
                document.getElementById('receiptPreview').style.display = 'none';
                document.getElementById('receiptFile').value = '';
                document.getElementById('receiptModal').style.display = 'flex';
            }
            function closeReceiptModal() {
                document.getElementById('receiptModal').style.display = 'none';
            }
            function previewReceipt() {
                const file = document.getElementById('receiptFile').files[0];
                if (!file) return;
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        document.getElementById('receiptPreviewImg').src = e.target.result;
                        document.getElementById('receiptPreview').style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('receiptPreview').style.display = 'none';
                }
                document.getElementById('receiptDropZone').innerHTML = '<i class="fas fa-check-circle" style="color:#2ecc71;font-size:1.5rem"></i><br><span style="color:#2ecc71">' + file.name + '</span>';
            }
            function uploadReceipt() {
                const form = document.getElementById('receiptForm');
                const btn = document.getElementById('btnUploadReceipt');
                const fd = new FormData(form);
                if (!document.getElementById('receiptFile').files.length) {
                    alert('Selecione um arquivo primeiro.');
                    return;
                }
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                fetch('ajax_receipt_upload.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            btn.style.background = '#27ae60';
                            btn.innerHTML = '✅ Comprovante Anexado!';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            alert('Erro: ' + (data.error || 'Falha no upload'));
                            btn.disabled = false;
                            btn.innerHTML = '📤 Enviar Comprovante';
                        }
                    })
                    .catch(() => {
                        alert('Erro de conexão');
                        btn.disabled = false;
                        btn.innerHTML = '📤 Enviar Comprovante';
                    });
            }

            function openUberQuote(orderId) {
                window.uberCurrentOrder = orderId;
                document.getElementById('uberModal').style.display = 'flex';
                document.getElementById('uberLoading').style.display = 'block';
                document.getElementById('uberResults').style.display = 'none';
                document.getElementById('uberActions').style.display = 'none';
                document.getElementById('uberFeedback').style.display = 'none';

                fetch(`lalamove.php?ajax_geocode=1&order_id=${orderId}`)
                    .then(r => r.json())
                    .then(d => {
                        if (d.error) throw new Error(d.error);
                        return fetch(`uber_direct.php?ajax_quote=1&order_id=${orderId}&lat=${d.lat}&lng=${d.lng}`);
                    })
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('uberLoading').style.display = 'none';
                        if (data.error) {
                            document.getElementById('uberResults').innerHTML = `<div style="color:#ff4444;padding:1rem">❌ ${data.error}</div>`;
                            document.getElementById('uberResults').style.display = 'block';
                            return;
                        }
                        
                        let html = '';
                        const q = data.quotes || [data];
                        q.forEach((quote, i) => {
                            const price = quote.fee / 100;
                            html += `<div onclick="selectUberQuote(this,'${quote.id}')" style="display:flex;align-items:center;gap:15px;padding:15px;background:#111;border-radius:10px;margin-bottom:10px;cursor:pointer;border:2px solid #222;transition:.2s">
                                <div style="font-size:1.5rem">🚗</div>
                                <div style="flex:1"><strong>Uber Flash / Direct</strong><br><small style="color:#888">Entrega Expressa</small></div>
                                <div style="font-weight:bold;color:#00ff88;font-size:1.2rem">R$ ${price.toLocaleString('pt-BR',{minimumFractionDigits:2})}</div>
                            </div>`;
                        });
                        
                        document.getElementById('uberResults').innerHTML = html;
                        document.getElementById('uberResults').style.display = 'block';
                        document.getElementById('uberActions').style.display = 'block';
                    })
                    .catch(err => {
                        document.getElementById('uberLoading').style.display = 'none';
                        document.getElementById('uberResults').innerHTML = `<div style="color:#ff4444;padding:1rem">Erro: ${err.message}</div>`;
                        document.getElementById('uberResults').style.display = 'block';
                    });
            }

            function selectUberQuote(el, quoteId) {
                document.querySelectorAll('#uberResults > div').forEach(d => d.style.borderColor = '#222');
                el.style.borderColor = '#00ff88';
                window.uberSelectedQuoteId = quoteId;
                document.getElementById('btnUberBuy').disabled = false;
            }

            function closeUberModal() { document.getElementById('uberModal').style.display = 'none'; }

            function buyUber() {
                if (!window.uberSelectedQuoteId) return;
                const btn = document.getElementById('btnUberBuy');
                btn.disabled = true; btn.textContent = 'Processando...';
                
                const fd = new FormData();
                fd.append('ajax_create', '1');
                fd.append('quote_id', window.uberSelectedQuoteId);
                fd.append('order_id', window.uberCurrentOrder);
                
                fetch('uber_direct.php', {method:'POST', body:fd})
                    .then(r => r.json())
                    .then(data => {
                        const fb = document.getElementById('uberFeedback');
                        fb.style.display = 'block';
                        if (data.success) {
                            fb.innerHTML = `<div style="background:rgba(0,255,136,.1);border:1px solid #00ff88;padding:15px;border-radius:8px;color:#00ff88;text-align:center">
                                ✅ Entrega Uber solicitada!<br>ID: <strong>${data.id}</strong><br>
                                <button onclick="location.reload()" class="btn" style="margin-top:10px;background:#222;color:#fff">Fechar e Atualizar</button>
                            </div>`;
                        } else {
                            fb.innerHTML = `<div style="color:#ff4444;padding:10px">❌ Erro: ${data.error}</div>`;
                            btn.disabled = false; btn.textContent = '🚗 Chamar Uber Direct';
                        }
                    });
            }
        </script>

        <!-- CUSTOMER AUTOCOMPLETE SCRIPT -->
        <script>
        (function() {
            const input = document.getElementById('customerSearchInput');
            const dropdown = document.getElementById('customerAutocomplete');
            if (!input || !dropdown) return;

            let debounceTimer = null;

            input.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const val = this.value.trim();
                if (val.length < 2) { dropdown.style.display = 'none'; return; }

                debounceTimer = setTimeout(() => {
                    fetch(`orders.php?ajax_search_customers=1&q=${encodeURIComponent(val)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.length === 0) { dropdown.style.display = 'none'; return; }
                            dropdown.innerHTML = '';
                            data.forEach(c => {
                                const initials = c.name.split(' ').map(w => w[0]).filter((_,i) => i === 0 || i === c.name.split(' ').length - 1).join('').toUpperCase().substring(0,2);
                                const loc = [c.city, c.state].filter(Boolean).join('/');
                                const div = document.createElement('div');
                                div.className = 'ac-item';
                                div.innerHTML = `
                                    <div class="ac-avatar">${initials}</div>
                                    <div class="ac-info">
                                        <div class="ac-name">${c.name}</div>
                                        <div class="ac-detail">${loc || 'Sem localização'}</div>
                                    </div>
                                    <span class="ac-orders-count">${c.order_count} pedido${c.order_count != 1 ? 's' : ''}</span>
                                `;
                                div.addEventListener('click', () => {
                                    input.value = c.name;
                                    dropdown.style.display = 'none';
                                    document.getElementById('ordersFilterForm').submit();
                                });
                                dropdown.appendChild(div);
                            });
                            dropdown.style.display = 'block';
                        });
                }, 250);
            });

            input.addEventListener('focus', function() {
                if (this.value.trim().length >= 2 && dropdown.children.length > 0) {
                    dropdown.style.display = 'block';
                }
            });

            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });

            // Highlight the input border when filtered
            input.addEventListener('focus', function() {
                this.style.borderColor = '#f39c12';
            });
            input.addEventListener('blur', function() {
                if (!this.value) this.style.borderColor = '#444';
            });
        })();
        </script>

    </div>

</body>
</html>
<?php
} catch (Throwable $e) {
    header("Location: emergency_fix.php?fatal_error=" . urlencode($e->getMessage()) . "&file=orders.php");
    exit;
}
?>