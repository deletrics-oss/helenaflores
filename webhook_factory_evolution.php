<?php
/**
 * webhook_factory_evolution.php — Fight Arcade Fábrica
 * Handler Evolution API para B2B — VERSÃO 1.0
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/ai_sdr.php';

// Self-healing: Garante coluna de status do bot no cadastro da fábrica
try {
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS sdr_bot_status VARCHAR(20) DEFAULT 'active'");
} catch (Exception $e) {}

// Bootstrapping com escopo da Fábrica B2B
$notif      = new NotificationService($pdo, true); // true = B2B prefix!
$ai         = new AIService($pdo);
$cfg        = $notif->getConfig();
$adminPhone = $cfg['notif_admin_phone'] ?? '';

// Recebe payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Debug Log
if ($json) {
    file_put_contents(__DIR__ . '/assets/uploads/defects/webhook_log.txt', "[" . date('Y-m-d H:i:s') . "] B2B PAYLOAD: " . substr($json, 0, 1500) . "\n", FILE_APPEND);
}

if (!$data) {
    http_response_code(200); 
    exit;
}

// Extrai campos
$msgData   = $data['data'] ?? [];
$key       = $msgData['key'] ?? ($data['key'] ?? []);
$messageId = $key['id'] ?? '';
$fromMe    = (bool) ($key['fromMe'] ?? false);
$inst      = $data['instance'] ?? 'default';

// Evita loops
if ($fromMe) {
    http_response_code(200);
    exit;
}

$senderJid = $key['remoteJid'] ?? '';
$sender    = preg_replace('/\D/', '', $senderJid);

// Ignora grupos
if (strpos($senderJid, '@g.us') !== false) {
    http_response_code(200);
    exit;
}

// Identifica se há anexo de imagem ou documento
$m = $msgData['message'] ?? $data['message'] ?? [];
$imageMessage = $m['imageMessage'] ?? null;
$documentMessage = $m['documentMessage'] ?? null;

$text = '';
$mimetype = '';
if ($imageMessage) {
    $text = $imageMessage['caption'] ?? '';
    $mimetype = $imageMessage['mimetype'] ?? 'image/jpeg';
} elseif ($documentMessage) {
    $text = $documentMessage['caption'] ?? $documentMessage['fileName'] ?? '';
    $mimetype = $documentMessage['mimetype'] ?? 'application/octet-stream';
} else {
    $text = $m['conversation'] ?? $m['extendedTextMessage']['text'] ?? '';
}

$text = trim($text);

// Se houver anexo e contiver palavras-chave de defeito/problema, processa abertura do ticket de defeitos
if (($imageMessage || $documentMessage) && !empty($text)) {
    $captionLower = mb_strtolower($text);
    $isDefectReport = false;
    $keywords = ['defeito', 'problema', 'quebrado', 'quebra', 'danificado', 'erro', 'defeito_op', 'bug', 'conserto', 'falha'];
    foreach ($keywords as $kw) {
        if (strpos($captionLower, $kw) !== false) {
            $isDefectReport = true;
            break;
        }
    }
    $hasOpTag = (bool)preg_match('/#(?:op|po|prod|pedido)?\s*(\d+)/i', $text);

    if ($isDefectReport || $hasOpTag) {
        // Garante diretório de uploads de defeitos
        $uploadDir = __DIR__ . '/assets/uploads/defects/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Identifica OP ou Produto
        $production_order_id = null;
        $product_id = null;

        if (preg_match('/#(?:op|po|pedido)?\s*(\d+)/i', $text, $matches)) {
            $opCandidate = (int)$matches[1];
            try {
                $check = $pdo->prepare("SELECT id, product_id FROM factory_production_orders WHERE id = ?");
                $check->execute([$opCandidate]);
                $op = $check->fetch(PDO::FETCH_ASSOC);
                if ($op) {
                    $production_order_id = $op['id'];
                    $product_id = $op['product_id'];
                }
            } catch (Exception $e) {}
        }

        if (!$product_id) {
            try {
                $products = $pdo->query("SELECT id, name, sku FROM factory_products")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($products as $p) {
                    if (!empty($p['sku']) && strpos($captionLower, mb_strtolower($p['sku'])) !== false) {
                        $product_id = $p['id'];
                        break;
                    }
                    if (strpos($captionLower, mb_strtolower($p['name'])) !== false) {
                        $product_id = $p['id'];
                        break;
                    }
                }
            } catch (Exception $e) {}
        }

        // Download da mídia
        $mediaUrl = $imageMessage['url'] ?? $documentMessage['url'] ?? null;
        $mediaData = null;

        if ($mediaUrl) {
            $ch = curl_init($mediaUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $mediaData = curl_exec($ch);
            curl_close($ch);
        }

        // Fallback Evolution base64 endpoint
        $evoUrl  = rtrim($cfg['notif_waapi_url'] ?? '', '/');
        $evoKey  = $cfg['notif_waapi_key'] ?? '';
        if ((!$mediaData || strlen($mediaData) < 100) && $evoUrl && $evoKey && $messageId) {
            $downloadUrl = "$evoUrl/chat/getBase64FromMediaMessage/$inst";
            $ch = curl_init($downloadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'apikey: ' . $evoKey],
                CURLOPT_POSTFIELDS => json_encode([
                    'message' => ['key' => $key],
                    'convertToMp4' => false,
                ]),
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $resData = json_decode($res, true);
            if (!empty($resData['base64'])) {
                $mediaData = base64_decode($resData['base64']);
            }
        }

        if ($mediaData && strlen($mediaData) > 100) {
            $ext = 'jpg';
            if (strpos($mimetype, 'png') !== false) $ext = 'png';
            elseif (strpos($mimetype, 'webp') !== false) $ext = 'webp';
            elseif (strpos($mimetype, 'pdf') !== false) $ext = 'pdf';

            $filename = "defect_" . ($production_order_id ? "op{$production_order_id}_" : "unknown_") . time() . "." . $ext;
            $filePath = $uploadDir . $filename;
            $relPath  = 'assets/uploads/defects/' . $filename;

            file_put_contents($filePath, $mediaData);

            // Grava na tabela de defeitos
            try {
                $stmt = $pdo->prepare("INSERT INTO factory_defects (production_order_id, product_id, file_path, description, sender_phone, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$production_order_id, $product_id, $relPath, $text, $sender]);
                $defectId = $pdo->lastInsertId();

                // Envia retorno ao remetente
                $responseMsg = "⚠️ *RELATO DE DEFEITO RECEBIDO*\n\n"
                             . "Registramos o seu alerta no sistema da fábrica! 🛠️\n"
                             . "📝 *ID do Relato:* #$defectId\n"
                             . "💬 *Descrição:* $text\n";
                
                if ($production_order_id) {
                    $responseMsg .= "⚙️ *Ordem de Produção vinculada:* #$production_order_id\n";
                }
                if ($product_id) {
                    $pName = $pdo->query("SELECT name FROM factory_products WHERE id = $product_id")->fetchColumn();
                    $responseMsg .= "📦 *Item:* $pName\n";
                }
                $responseMsg .= "\nA equipe já foi notificada e está analisando o problema. Obrigado!";
                $notif->send($senderJid, $responseMsg);

                // Alerta Administrador
                $adminAlert = "🚨 *NOVO DEFEITO RELATADO VIA WHATSAPP*\n"
                            . "Remetente: $sender\n"
                            . "Descrição: $text\n"
                            . ($production_order_id ? "OP Vinculada: #$production_order_id\n" : "")
                            . "Acesse o painel da fábrica para ver a imagem e resolver o ticket.";
                $notif->notifyAdmin($adminAlert);
            } catch (Exception $e) {
                file_put_contents($uploadDir . 'webhook_error.txt', "[" . date('Y-m-d H:i:s') . "] Db error: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            http_response_code(200);
            exit;
        }
    }
}

if (empty($text)) {
    http_response_code(200);
    exit;
}

// Deduplicação de mensagens
if ($messageId) {
    $midKey = '__mid_b2b__' . $messageId;
    try {
        $dup = $pdo->prepare(
            "SELECT id FROM ai_logs WHERE message = ? AND type = 'dedup_b2b' AND created_at > NOW() - INTERVAL 30 SECOND LIMIT 1"
        );
        $dup->execute([$midKey]);
        if ($dup->fetch()) { 
            http_response_code(200); 
            exit; 
        }

        $pdo->prepare(
            "INSERT INTO ai_logs (sender, role, message, type) VALUES (?, 'user', ?, 'dedup_b2b')"
        )->execute([$sender, $midKey]);
    } catch (Exception $e) {}
}

// Normalização de telefones
function normalizePhoneB2B(string $phone): string {
    $p = preg_replace('/\D/', '', $phone);
    if (strpos($p, '55') === 0 && strlen($p) >= 12) {
        $p = substr($p, 2);
    }
    return $p;
}

$normalizedSender = normalizePhoneB2B($sender);
$normalizedAdmin  = normalizePhoneB2B($adminPhone);
$isAdmin = ($normalizedSender === $normalizedAdmin || (!empty($adminPhone) && strpos($sender, $adminPhone) !== false));

// =========================================================
// 1. COMANDOS DO ADMIN DA FÁBRICA
// =========================================================
if ($isAdmin && strpos($text, '!') === 0) {
    $cmd      = strtolower(trim($text));
    $response = '';

    switch ($cmd) {
        case '!estoque':
            $items = $pdo->query(
                "SELECT name, stock_qty FROM factory_products WHERE stock_qty <= 5 LIMIT 10"
            )->fetchAll();
            $response = $items ? "🚨 *ESTOQUE BAIXO FÁBRICA*\n\n" : "✅ *Estoque da fábrica em dia!*";
            foreach ($items as $i) {
                $response .= "• {$i['name']}: *{$i['stock_qty']} un*\n";
            }
            break;

        case '!pedidos':
            $today = date('Y-m-d');
            $count = $pdo->query("SELECT COUNT(*) FROM factory_sales WHERE DATE(created_at)='$today'")->fetchColumn();
            $sum   = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM factory_sales WHERE DATE(created_at)='$today'")->fetchColumn();
            $response = "🏭 *VENDAS FÁBRICA HOJE*\n\nPedidos: *{$count}*\nFaturamento: *R$ " . number_format((float) $sum, 2, ',', '.') . "*";
            break;

        case '!ops':
            $ops = $pdo->query("
                SELECT po.id, p.name as product_name, po.status 
                FROM factory_production_orders po
                JOIN factory_products p ON po.product_id = p.id
                WHERE po.status != 'completed'
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            $response = $ops ? "⚙️ *ORDENS DE PRODUÇÃO ATIVAS*\n\n" : "✅ *Nenhuma OP pendente no momento.*";
            foreach ($ops as $op) {
                $response .= "• OP #{$op['id']} - {$op['product_name']} (*{$op['status']}*)\n";
            }
            break;

        case '!ajuda':
            $response = "🤖 *FÁBRICA-ADMIN*\n\n!estoque | !pedidos | !ops | !ajuda";
            break;
    }

    if ($response) {
        $notif->notifyAdmin($response);
    }
    http_response_code(200);
    exit;
}

// =========================================================
// 2. SALVA NO HISTÓRICO LOCAL B2B
// =========================================================
try {
    $stmt = $pdo->prepare("INSERT INTO whatsapp_messages (remote_jid, from_me, message_text) VALUES (?, 0, ?)");
    $stmt->execute([$senderJid, $text]);
} catch (Exception $e) {}

// =========================================================
// 3. IA SDR B2B
// =========================================================
if ($ai->isActive()) {
    $type      = $isAdmin ? 'admin' : 'sdr';
    $botStatus = 'paused'; // Contato desconhecido não recebe IA
    $userId    = 0;

    if (!$isAdmin) {
        $stmt = $pdo->prepare("SELECT id, sdr_bot_status FROM factory_clients WHERE REPLACE(REPLACE(REPLACE(phone,'+',''),'-',''),' ','') LIKE ? LIMIT 1");
        $stmt->execute(["%$normalizedSender%"]);
        $u = $stmt->fetch();
        if ($u) {
            $userId = $u['id'];
            $botStatus = $u['sdr_bot_status'] ?? 'active';

            // Auto-reativação após 2 horas sem mensagens do admin
            if ($botStatus === 'paused') {
                $lastAdminMsgTime = $pdo->prepare("SELECT MAX(created_at) FROM whatsapp_messages WHERE remote_jid = ? AND from_me = 1");
                $lastAdminMsgTime->execute([$senderJid]);
                $lastTime = $lastAdminMsgTime->fetchColumn();
                
                if ($lastTime) {
                    $hoursSinceLastMsg = (time() - strtotime($lastTime)) / 3600;
                    if ($hoursSinceLastMsg >= 2) {
                        $botStatus = 'active';
                        $pdo->prepare("UPDATE factory_clients SET sdr_bot_status = 'active' WHERE id = ?")->execute([$userId]);
                    }
                }
            }
        }
    }

    // Se pausado, encerra processamento
    if (!$isAdmin && $botStatus === 'paused') {
        http_response_code(200);
        exit;
    }

    // Gera a resposta pela IA
    $aiReply = $ai->generateResponse($text, $senderJid, $isAdmin, $inst);

    if ($aiReply) {
        if ($isAdmin) {
            // Ações de Admin B2B
            if (preg_match('/\[\[TASK_ADD:\s*(.*?)\]\]/i', $aiReply, $m)) {
                $taskText = trim($m[1]);
                $stmt = $pdo->prepare("INSERT INTO factory_tasks (task_text, is_completed) VALUES (?, 0)");
                $stmt->execute([$taskText]);
                $aiReply = str_replace($m[0], "✅ (Tarefa da Fábrica Cadastrada!)", $aiReply);
            }
            if (preg_match('/\[\[TASK_DONE:\s*(.*?)\]\]/i', $aiReply, $m)) {
                $taskMatch = trim($m[1]);
                $stmt = $pdo->prepare("UPDATE factory_tasks SET is_completed = 1, completed_at = NOW() WHERE task_text LIKE ? AND is_completed = 0 LIMIT 1");
                $stmt->execute(["%$taskMatch%"]);
                $aiReply = str_replace($m[0], "🏁 (Tarefa da Fábrica Concluída!)", $aiReply);
            }
        } else {
            // Ações de Cliente B2B (SDR Pause)
            if (preg_match('/\[\[SDR_PAUSE\]\]/i', $aiReply, $m)) {
                if ($userId > 0) {
                    $pdo->prepare("UPDATE factory_clients SET sdr_bot_status = 'paused' WHERE id = ?")->execute([$userId]);
                }
                $aiReply = str_replace($m[0], "", $aiReply);
                // Alerta o Admin da Fábrica
                $notif->notifyAdmin("🚨 *SDR SOLICITA INTERVENÇÃO FÁBRICA*\n\nO cliente B2B {$normalizedSender} solicitou atendimento humano. O robô foi pausado para ele automaticamente.\n\nAcesse o Painel da Fábrica!");
            }
        }

        // Envia mensagem de resposta
        if (trim($aiReply) !== '') {
            if ($isAdmin) {
                $notif->notifyAdmin($aiReply);
            } else {
                $notif->send($senderJid, $aiReply);
                try {
                    $stmt = $pdo->prepare("INSERT INTO whatsapp_messages (remote_jid, from_me, message_text) VALUES (?, 1, ?)");
                    $stmt->execute([$senderJid, $aiReply]);
                } catch (Exception $e) {}
            }
        }

        // Registra histórico no banco
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO ai_logs (sender, role, message, response, type) VALUES (?, 'user', ?, ?, ?)"
            );
            $stmt->execute([$sender, $text, $aiReply, 'sdr_factory']);
        } catch (Exception $e) {}
    }
}

http_response_code(200);
exit;
