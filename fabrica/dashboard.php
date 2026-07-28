<?php
// catalogo/fabrica/dashboard.php
require_once __DIR__ . '/header.php';

// --- SELF-HEALING AUTOMATIC SCHEMA MIGRATIONS ---
try {
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS neighborhood VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS number VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS complement VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS is_vip TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_clients ADD COLUMN IF NOT EXISTS is_lead TINYINT(1) DEFAULT 0");

    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(10,3) DEFAULT 0.000");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS length_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS width_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS height_cm INT DEFAULT 0");
    $pdo->exec("ALTER TABLE factory_products ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL");

    $pdo->exec("ALTER TABLE factory_employees ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL");
    $pdo->exec("ALTER TABLE factory_production_orders ADD COLUMN IF NOT EXISTS notification_phone VARCHAR(30) DEFAULT NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_defects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        production_order_id INT DEFAULT NULL,
        product_id INT DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        sender_phone VARCHAR(30) DEFAULT NULL,
        status ENUM('pending', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS factory_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_text TEXT NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch(Exception $e) {}

// AJAX: Toggle Factory Task
if (isset($_POST['toggle_task'])) {
    header('Content-Type: application/json');
    $tid = (int)$_POST['task_id'];
    try {
        $stmt = $pdo->prepare("UPDATE factory_tasks SET is_completed = 1, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$tid]);
        echo json_encode(['success' => true]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Action: Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    $task_text = trim($_POST['task_text'] ?? '');
    if (!empty($task_text)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO factory_tasks (task_text, is_completed) VALUES (?, 0)");
            $stmt->execute([$task_text]);
        } catch(Exception $e) {}
        header("Location: dashboard.php");
        exit;
    }
}

// Resilient Query Fetching
$pending_ops = 0;
$total_sales = 0;
$total_income = 0;
$total_expense = 0;
$cash_balance = 0;
$products_count = 0;

try { $pending_ops = $pdo->query("SELECT COUNT(*) FROM factory_production_orders WHERE status IN ('pending', 'in_production')")->fetchColumn() ?: 0; } catch(Exception $e) {}
try { $total_sales = $pdo->query("SELECT SUM(total_amount) FROM factory_sales WHERE status IN ('paid', 'shipped')")->fetchColumn() ?: 0; } catch(Exception $e) {}
try { $total_income = $pdo->query("SELECT SUM(amount) FROM factory_cashbook WHERE type = 'income'")->fetchColumn() ?: 0; } catch(Exception $e) {}
try { $total_expense = $pdo->query("SELECT SUM(amount) FROM factory_cashbook WHERE type = 'expense'")->fetchColumn() ?: 0; } catch(Exception $e) {}
$cash_balance = $total_income - $total_expense;
try { $products_count = $pdo->query("SELECT COUNT(*) FROM factory_products")->fetchColumn() ?: 0; } catch(Exception $e) {}

// Fetch top 5 pending production orders
$upcoming_ops = [];
try {
    $upcoming_ops = $pdo->query("
        SELECT po.*, p.name as product_name 
        FROM factory_production_orders po
        JOIN factory_products p ON po.product_id = p.id
        WHERE po.status IN ('pending', 'in_production')
        ORDER BY po.id ASC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Fetch top 5 recent B2B sales
$recent_sales = [];
try {
    $recent_sales = $pdo->query("
        SELECT s.*, c.name as client_name 
        FROM factory_sales s
        JOIN factory_clients c ON s.client_id = c.id
        ORDER BY s.id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Fetch pending defects
$pending_defects = [];
try {
    $pending_defects = $pdo->query("
        SELECT d.*, p.name as product_name 
        FROM factory_defects d
        LEFT JOIN factory_products p ON d.product_id = p.id
        WHERE d.status = 'pending'
        ORDER BY d.id DESC LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Fetch active tasks
$active_tasks = [];
try {
    $active_tasks = $pdo->query("SELECT * FROM factory_tasks WHERE is_completed = 0 ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// --- MONTHLY COMPARISON LOGS ---
$now_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));

$rev_now = 0; $rev_last = 0; $rev_diff = 0;
try {
    $rev_now = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM factory_sales WHERE status IN ('paid','shipped') AND DATE_FORMAT(created_at,'%Y-%m')='$now_month'")->fetchColumn() ?: 0;
    $rev_last = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM factory_sales WHERE status IN ('paid','shipped') AND DATE_FORMAT(created_at,'%Y-%m')='$last_month'")->fetchColumn() ?: 0;
    $rev_diff = $rev_last > 0 ? (($rev_now - $rev_last) / $rev_last) * 100 : ($rev_now > 0 ? 100 : 0);
} catch(Exception $e) {}

$exp_now = 0; $exp_last = 0; $exp_diff = 0;
try {
    $exp_now = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM factory_cashbook WHERE type='expense' AND DATE_FORMAT(created_at,'%Y-%m')='$now_month'")->fetchColumn() ?: 0;
    $exp_last = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM factory_cashbook WHERE type='expense' AND DATE_FORMAT(created_at,'%Y-%m')='$last_month'")->fetchColumn() ?: 0;
    $exp_diff = $exp_last > 0 ? (($exp_now - $exp_last) / $exp_last) * 100 : ($exp_now > 0 ? 100 : 0);
} catch(Exception $e) {}

$ops_now = 0; $ops_last = 0; $ops_diff = 0;
try {
    $ops_now = $pdo->query("SELECT COUNT(*) FROM factory_production_orders WHERE status='completed' AND DATE_FORMAT(created_at,'%Y-%m')='$now_month'")->fetchColumn() ?: 0;
    $ops_last = $pdo->query("SELECT COUNT(*) FROM factory_production_orders WHERE status='completed' AND DATE_FORMAT(created_at,'%Y-%m')='$last_month'")->fetchColumn() ?: 0;
    $ops_diff = $ops_last > 0 ? (($ops_now - $ops_last) / $ops_last) * 100 : ($ops_now > 0 ? 100 : 0);
} catch(Exception $e) {}
?>

<!-- Welcome Banner -->
<div class="card" style="background: linear-gradient(135deg, #121824 0%, #1e293b 100%); border-left: 5px solid var(--primary); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem; padding: 2rem;">
    <div>
        <h1 style="font-size: 1.8rem; font-weight:800; color:#fff;">Bem-vindo ao Fábrica ERP, <?php echo htmlspecialchars($_SESSION['factory_user_name']); ?>!</h1>
        <p style="color: var(--text-muted); margin-top: 5px; font-size:0.95rem;">Controle integrado de produção, custos de insumos, faturamento B2B e logística de frota.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="pdv.php" class="btn btn-primary" style="padding:10px 20px;"><i class="fas fa-shopping-cart"></i> Abrir PDV</a>
        <a href="production.php?add=1" class="btn btn-secondary" style="padding:10px 20px;"><i class="fas fa-plus"></i> Nova OP</a>
    </div>
</div>

<!-- Monthly Performance Log Cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    <div class="card" style="border: 1px solid var(--border);">
        <span style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Receita de Venda (B2B)</span>
        <div style="display:flex; align-items:baseline; justify-content:space-between; margin-top:5px;">
            <span style="font-size:1.6rem; font-weight:900; color:var(--primary);">R$ <?php echo number_format($rev_now, 2, ',', '.'); ?></span>
            <span style="font-size:0.8rem; font-weight:bold; color:<?php echo $rev_diff >= 0 ? '#00e676' : '#ef4444'; ?>;">
                <i class="fas <?php echo $rev_diff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> <?php echo number_format($rev_diff, 1); ?>%
            </span>
        </div>
        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:5px;">Mês passado: R$ <?php echo number_format($rev_last, 2, ',', '.'); ?></div>
    </div>
    
    <div class="card" style="border: 1px solid var(--border);">
        <span style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Gastos de Insumo / Caixa</span>
        <div style="display:flex; align-items:baseline; justify-content:space-between; margin-top:5px;">
            <span style="font-size:1.6rem; font-weight:900; color:#ff6b6b;">R$ <?php echo number_format($exp_now, 2, ',', '.'); ?></span>
            <span style="font-size:0.8rem; font-weight:bold; color:<?php echo $exp_diff <= 0 ? '#00e676' : '#ef4444'; ?>;">
                <i class="fas <?php echo $exp_diff <= 0 ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i> <?php echo number_format(abs($exp_diff), 1); ?>%
            </span>
        </div>
        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:5px;">Mês passado: R$ <?php echo number_format($exp_last, 2, ',', '.'); ?></div>
    </div>

    <div class="card" style="border: 1px solid var(--border);">
        <span style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800;">OPs Finalizadas</span>
        <div style="display:flex; align-items:baseline; justify-content:space-between; margin-top:5px;">
            <span style="font-size:1.6rem; font-weight:900; color:var(--accent);"><?php echo $ops_now; ?> finalizadas</span>
            <span style="font-size:0.8rem; font-weight:bold; color:<?php echo $ops_diff >= 0 ? '#00e676' : '#ef4444'; ?>;">
                <i class="fas <?php echo $ops_diff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> <?php echo number_format($ops_diff, 1); ?>%
            </span>
        </div>
        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:5px;">Mês passado: <?php echo $ops_last; ?> concluídas</div>
    </div>
</div>

<!-- KPIs grid -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.5rem; margin-bottom:2.5rem;">
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
        <div>
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:0.05em;">OPs Pendentes</div>
            <div style="font-size:1.8rem; font-weight:900; margin-top:8px; color:var(--accent);"><?php echo $pending_ops; ?> ordens</div>
        </div>
        <i class="fas fa-industry" style="font-size:2.2rem; color:rgba(241, 196, 15, 0.15);"></i>
    </div>
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
        <div>
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:0.05em;">Faturamento Geral B2B</div>
            <div style="font-size:1.8rem; font-weight:900; margin-top:8px; color:var(--primary);">R$ <?php echo number_format($total_sales, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-box-open" style="font-size:2.2rem; color:rgba(0, 230, 118, 0.15);"></i>
    </div>
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; border-left:3px solid <?php echo $cash_balance >= 0 ? 'var(--primary)' : 'var(--danger)'; ?>; margin-bottom:0;">
        <div>
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:0.05em;">Saldo Geral Caixa</div>
            <div style="font-size:1.8rem; font-weight:900; margin-top:8px; color:<?php echo $cash_balance >= 0 ? 'var(--primary)' : 'var(--danger)'; ?>;">R$ <?php echo number_format($cash_balance, 2, ',', '.'); ?></div>
        </div>
        <i class="fas fa-wallet" style="font-size:2.2rem; color:rgba(255,255,255,0.04);"></i>
    </div>
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
        <div>
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:0.05em;">Produtos de Fábrica</div>
            <div style="font-size:1.8rem; font-weight:900; margin-top:8px; color:var(--blue);"><?php echo $products_count; ?> itens</div>
        </div>
        <i class="fas fa-tags" style="font-size:2.2rem; color:rgba(59, 130, 246, 0.15);"></i>
    </div>
</div>

<!-- Two Columns Layout (Top Section) -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap:2rem; margin-bottom:2rem;">
    
    <!-- Production Queue -->
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="font-weight:800;"><i class="fas fa-tools" style="color:var(--accent);"></i> OPs em Andamento</h3>
            <a href="production.php" style="font-size:0.8rem; color:var(--primary); font-weight:bold;">Ver Todas <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-responsive" style="margin-top:0; border:none;">
            <table>
                <thead>
                    <tr>
                        <th>OP</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($upcoming_ops)): ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:20px;">Fila de produção limpa!</td></tr>
                    <?php else: ?>
                        <?php foreach($upcoming_ops as $op): 
                            $status_badge = $op['status'] === 'in_production' ? 'badge-info' : 'badge-warning';
                            $status_label = $op['status'] === 'in_production' ? 'Produzindo' : 'Pendente';
                        ?>
                            <tr>
                                <td>#<?php echo $op['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($op['product_name']); ?></strong></td>
                                <td><?php echo $op['quantity']; ?> un</td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="font-weight:800;"><i class="fas fa-box-open" style="color:var(--primary);"></i> Vendas Recentes</h3>
            <a href="sales.php" style="font-size:0.8rem; color:var(--primary); font-weight:bold;">Ver Todas <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-responsive" style="margin-top:0; border:none;">
            <table>
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($recent_sales)): ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:20px;">Nenhuma venda faturada.</td></tr>
                    <?php else: ?>
                        <?php foreach($recent_sales as $rs): 
                            $status_badge = 'badge-warning';
                            $status_label = 'Pendente';
                            if($rs['status'] === 'paid') { $status_badge = 'badge-success'; $status_label = 'Pago'; }
                            elseif($rs['status'] === 'shipped') { $status_badge = 'badge-info'; $status_label = 'Enviado'; }
                            elseif($rs['status'] === 'canceled') { $status_badge = 'badge-danger'; $status_label = 'Cancelado'; }
                        ?>
                            <tr>
                                <td><strong>#<?php echo $rs['id']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($rs['client_name']); ?></strong></td>
                                <td style="font-weight:bold; color:var(--primary);">R$ <?php echo number_format($rs['total_amount'], 2, ',', '.'); ?></td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Two Columns Layout (Lower Section) -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap:2rem;">
    
    <!-- Checklist / Tarefas do Dia -->
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="font-weight:800;"><i class="fas fa-tasks" style="color:var(--primary);"></i> Lembretes & Tarefas da Fábrica</h3>
        </div>
        
        <form method="POST" style="display:flex; gap:10px; margin-bottom:1.5rem;">
            <input type="hidden" name="action" value="add_task">
            <input type="text" name="task_text" class="form-control" placeholder="Escreva uma nova tarefa e aperte Enter..." required style="flex:1;">
            <button type="submit" class="btn btn-primary" style="padding:10px 15px;"><i class="fas fa-plus"></i></button>
        </form>

        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php if(empty($active_tasks)): ?>
                <div style="text-align:center; color:var(--text-muted); padding:20px; font-size:0.9rem;">Parabéns! Todas as tarefas concluídas.</div>
            <?php else: ?>
                <?php foreach($active_tasks as $t): ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; background:#080b10; border:1px solid var(--border); padding:10px 15px; border-radius:8px;" id="task-row-<?php echo $t['id']; ?>">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <input type="checkbox" onclick="toggleTask(<?php echo $t['id']; ?>)" style="width:18px; height:18px; cursor:pointer;">
                            <strong style="font-size:0.95rem; color:#e2e8f0;"><?php echo htmlspecialchars($t['task_text']); ?></strong>
                        </div>
                        <small style="color:var(--text-muted); font-size:0.75rem;"><i class="far fa-clock"></i> <?php echo date('d/m H:i', strtotime($t['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Defect Alerts -->
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="font-weight:800; color:#ff6b6b;"><i class="fas fa-exclamation-circle"></i> Defeitos Recentes Relatados</h3>
            <a href="defects.php" style="font-size:0.8rem; color:var(--primary); font-weight:bold;">Gerenciar Todos <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:12px;">
            <?php if(empty($pending_defects)): ?>
                <div style="text-align:center; color:var(--text-muted); padding:20px; font-size:0.9rem;">Excelente! Nenhum defeito pendente.</div>
            <?php else: ?>
                <?php foreach($pending_defects as $d): ?>
                    <div style="background:#080b10; border:1px solid var(--border); border-left:4px solid #ef4444; padding:12px; border-radius:8px; display:flex; gap:12px; align-items:center;">
                        <?php if(!empty($d['file_path'])): ?>
                            <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($d['file_path']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid var(--border); cursor:pointer;" onclick="window.open('<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($d['file_path']); ?>', '_blank')">
                        <?php else: ?>
                            <div style="width:50px; height:50px; background:#111b21; border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:0.8rem;"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                        
                        <div style="flex:1;">
                            <strong style="font-size:0.9rem;"><?php echo htmlspecialchars($d['product_name'] ?: 'Produto Desconhecido'); ?></strong>
                            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                <?php echo htmlspecialchars($d['description']); ?>
                            </div>
                            <div style="font-size:0.7rem; color:var(--text-muted); margin-top:4px;">
                                Remetente: <?php echo htmlspecialchars($d['sender_phone']); ?> | Data: <?php echo date('d/m/Y H:i', strtotime($d['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function toggleTask(taskId) {
    const row = document.getElementById('task-row-' + taskId);
    if (!row) return;

    row.style.transition = 'opacity 0.4s ease';
    row.style.opacity = '0.3';

    const fd = new FormData();
    fd.append('toggle_task', '1');
    fd.append('task_id', taskId);

    fetch('dashboard.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            setTimeout(() => { row.remove(); }, 300);
        } else {
            row.style.opacity = '1';
            alert('Falha ao atualizar tarefa.');
        }
    })
    .catch(() => {
        row.style.opacity = '1';
        alert('Erro ao atualizar tarefa.');
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
