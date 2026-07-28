<?php
// catalogo/fabrica/client-details.php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../includes/notifications.php';

$msg = '';
$err = '';

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$client_id) {
    header("Location: clients.php");
    exit;
}

// 1. AÇÃO: Marcar Ordem como Paga
if (isset($_GET['pay_order'])) {
    $order_id = intval($_GET['pay_order']);
    $stmt = $pdo->prepare("UPDATE factory_sales SET status = 'paid' WHERE id = ? AND client_id = ?");
    if ($stmt->execute([$order_id, $client_id])) {
        // Registra automaticamente a entrada correspondente no Livro Caixa para conciliação
        $ordTotal = $pdo->query("SELECT total_amount FROM factory_sales WHERE id = $order_id")->fetchColumn();
        $cName = $pdo->query("SELECT name FROM factory_clients WHERE id = $client_id")->fetchColumn();
        
        $logCash = $pdo->prepare("INSERT INTO factory_cashbook (type, amount, description) VALUES ('income', ?, ?)");
        $logCash->execute([$ordTotal, "Recebimento B2B - Pedido #$order_id - $cName"]);
        
        $msg = "Pedido #$order_id marcado como Pago e lançado no Caixa!";
    } else {
        $err = "Erro ao dar baixa no pagamento.";
    }
}

// 2. AÇÃO: Disparar Extrato Financeiro por WhatsApp
if (isset($_GET['send_statement'])) {
    $notif = new NotificationService($pdo);
    
    // Busca informações completas do cliente
    $stmt = $pdo->prepare("SELECT * FROM factory_clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client && !empty($client['phone'])) {
        // Busca todas as vendas pendentes
        $stmt_pending = $pdo->prepare("SELECT id, total_amount, created_at FROM factory_sales WHERE client_id = ? AND status = 'pending' ORDER BY id ASC");
        $stmt_pending->execute([$client_id]);
        $pending_orders = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
        
        $total_due = 0;
        $order_list_text = '';
        foreach ($pending_orders as $po) {
            $total_due += $po['total_amount'];
            $order_list_text .= "• *Pedido #{$po['id']}* - R$ " . number_format($po['total_amount'], 2, ',', '.') . " (" . date('d/m/Y', strtotime($po['created_at'])) . ")\n";
        }
        
        $statement_msg = "💼 *EXTRATO FINANCEIRO B2B — FÁBRICA*\n\n"
                       . "Prezado(a) *{$client['name']}*,\n"
                       . "Seguem abaixo os detalhes pendentes de sua conta em nossa fábrica:\n\n"
                       . ($total_due > 0 ? $order_list_text : "✅ *Não constam pendências financeiras em aberto!*\n")
                       . "\n💰 *Saldo Devedor Total:* R$ " . number_format($total_due, 2, ',', '.') . "\n\n"
                       . "Por favor, efetue o pagamento e nos envie o comprovante para conciliação. Obrigado!";
        
        $targetJid = $client['phone'] . '@s.whatsapp.net';
        if ($notif->send($targetJid, $statement_msg)) {
            $msg = "Extrato financeiro enviado com sucesso via WhatsApp para {$client['name']}!";
        } else {
            $err = "Erro ao disparar mensagem. Verifique a configuração da Evolution API.";
        }
    } else {
        $err = "Cliente não possui telefone cadastrado para notificações.";
    }
}

// Busca dados do cliente
$stmt = $pdo->prepare("SELECT * FROM factory_clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    echo "<div class='alert alert-danger'>Cliente não encontrado.</div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// Cálculos de Totais B2B
$total_purchased = $pdo->query("SELECT SUM(total_amount) FROM factory_sales WHERE client_id = $client_id AND status != 'canceled'")->fetchColumn() ?: 0;
$total_paid = $pdo->query("SELECT SUM(total_amount) FROM factory_sales WHERE client_id = $client_id AND status IN ('paid', 'shipped')")->fetchColumn() ?: 0;
$total_pending = $pdo->query("SELECT SUM(total_amount) FROM factory_sales WHERE client_id = $client_id AND status = 'pending'")->fetchColumn() ?: 0;

// Histórico de Vendas
$sales = $pdo->prepare("SELECT * FROM factory_sales WHERE client_id = ? ORDER BY id DESC");
$sales->execute([$client_id]);
$sales = $sales->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:15px;">
    <div>
        <a href="clients.php" style="color:var(--text-muted); font-size:0.9rem; text-decoration:none;"><i class="fas fa-arrow-left"></i> Voltar para Lista</a>
        <h2 style="margin-top:5px;"><i class="fas fa-file-invoice-dollar" style="color:var(--primary);"></i> Extrato B2B: <?php echo htmlspecialchars($client['name']); ?></h2>
    </div>
    <div>
        <a href="?id=<?php echo $client_id; ?>&send_statement=1" class="btn btn-primary" style="background:#27ae60; color:#fff;"><i class="fab fa-whatsapp"></i> Enviar Extrato por WhatsApp</a>
    </div>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
<?php endif; ?>

<!-- Financial KPI Cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <div class="card" style="display:flex; align-items:center; justify-content:space-between; border-left:4px solid var(--blue);">
        <div>
            <div style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Total Faturado B2B</div>
            <div style="font-size:1.8rem; font-weight:900; color:var(--blue); margin-top:5px;">R$ <?php echo number_format($total_purchased, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-shopping-bag" style="font-size:2.5rem; color:rgba(59, 130, 246, 0.2);"></i>
    </div>
    
    <div class="card" style="display:flex; align-items:center; justify-content:space-between; border-left:4px solid var(--primary);">
        <div>
            <div style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Total Pago / Faturado</div>
            <div style="font-size:1.8rem; font-weight:900; color:var(--primary); margin-top:5px;">R$ <?php echo number_format($total_paid, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-check-circle" style="font-size:2.5rem; color:rgba(0, 230, 118, 0.2);"></i>
    </div>

    <div class="card" style="display:flex; align-items:center; justify-content:space-between; border-left:4px solid <?php echo $total_pending > 0 ? 'var(--danger)' : 'var(--border)'; ?>;">
        <div>
            <div style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Saldo Devedor em Aberto</div>
            <div style="font-size:1.8rem; font-weight:900; color:<?php echo $total_pending > 0 ? 'var(--danger)' : 'var(--text-muted)'; ?>; margin-top:5px;">R$ <?php echo number_format($total_pending, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-exclamation-circle" style="font-size:2.5rem; color:rgba(239, 68, 68, 0.2);"></i>
    </div>
</div>

<div style="display:grid; grid-template-columns: 350px 1fr; gap:2rem;">
    
    <!-- Client Profile Detail Card -->
    <div>
        <div class="card">
            <h3>Ficha Cadastral B2B</h3>
            <hr style="border-color:var(--border); margin:1rem 0;">
            
            <div style="display:flex; flex-direction:column; gap:12px; font-size:0.9rem;">
                <div>
                    <label style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; display:block;">CNPJ / CPF</label>
                    <strong><?php echo htmlspecialchars($client['document'] ?: 'Não cadastrado'); ?></strong>
                </div>
                <div>
                    <label style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; display:block;">WhatsApp / Telefone</label>
                    <strong><?php echo htmlspecialchars($client['phone'] ?: 'Não cadastrado'); ?></strong>
                </div>
                <div>
                    <label style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; display:block;">E-mail</label>
                    <strong><?php echo htmlspecialchars($client['email'] ?: 'Não cadastrado'); ?></strong>
                </div>
                <div>
                    <label style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; display:block;">Endereço de Faturamento</label>
                    <strong>
                        <?php echo htmlspecialchars($client['address'] ?? ''); ?> <?php echo htmlspecialchars($client['number'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($client['complement'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($client['neighborhood'] ?? ''); ?> - <?php echo htmlspecialchars($client['zipcode'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($client['city'] ?? ''); ?> / <?php echo htmlspecialchars($client['state'] ?? ''); ?>
                    </strong>
                </div>
                <div>
                    <label style="color:var(--text-muted); font-size:0.75rem; text-transform:uppercase; display:block;">Etiquetas</label>
                    <div style="display:flex; gap:5px; margin-top:5px;">
                        <?php if($client['is_vip']): ?>
                            <span class="badge badge-warning">👑 VIP</span>
                        <?php endif; ?>
                        <?php if($client['is_lead']): ?>
                            <span class="badge badge-danger">Lead</span>
                        <?php else: ?>
                            <span class="badge badge-success">B2B</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <a href="clients.php?edit=<?php echo $client_id; ?>" class="btn btn-secondary" style="width:100%; margin-top:1.5rem; text-align:center;"><i class="fas fa-edit"></i> Editar Ficha Cadastral</a>
        </div>
    </div>

    <!-- Purchases Ledger list -->
    <div>
        <div class="card">
            <h3>Histórico de Transações e Compras</h3>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID Ordem</th>
                            <th>Data</th>
                            <th>Forma de Pagto</th>
                            <th>Envio</th>
                            <th>NF-e</th>
                            <th>Total Ordem</th>
                            <th>Faturamento Status</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($sales)): ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">Nenhuma transação financeira registrada para este cliente.</td></tr>
                        <?php else: ?>
                            <?php foreach($sales as $s): 
                                $status_badge = 'badge-warning';
                                $status_label = 'Pendente';
                                if($s['status'] === 'paid') { $status_badge = 'badge-success'; $status_label = 'Pago'; }
                                elseif($s['status'] === 'shipped') { $status_badge = 'badge-info'; $status_label = 'Enviado'; }
                                elseif($s['status'] === 'canceled') { $status_badge = 'badge-danger'; $status_label = 'Cancelado'; }
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $s['id']; ?></strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($s['created_at'])); ?></td>
                                    <td style="text-transform:uppercase; font-size:0.8rem;"><?php echo htmlspecialchars($s['payment_method']); ?></td>
                                    <td>
                                        <span style="font-size:0.8rem;"><?php echo htmlspecialchars($s['shipping_method'] ?: 'Retirar'); ?></span>
                                    </td>
                                    <td>
                                        <?php echo !empty($s['invoice_number']) ? '🧾 ' . htmlspecialchars($s['invoice_number']) : '-'; ?>
                                    </td>
                                    <td style="font-weight:bold; color:var(--primary);">R$ <?php echo number_format($s['total_amount'], 2, ',', '.'); ?></td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <?php if($s['status'] === 'pending'): ?>
                                            <a href="?id=<?php echo $client_id; ?>&pay_order=<?php echo $s['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Deseja marcar esta fatura como paga?')" title="Dar baixa em pagamento"><i class="fas fa-check"></i> Baixa</a>
                                        <?php endif; ?>
                                        <a href="sales.php?view=<?php echo $s['id']; ?>" target="_blank" class="btn btn-secondary btn-sm" title="Ver Detalhes do Pedido"><i class="fas fa-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
