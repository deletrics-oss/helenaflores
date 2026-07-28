<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();
try {

// --- 0. AJAX TASK TOGGLE ---
if (isset($_POST['toggle_task'])) {
    $tid = (int)$_POST['task_id'];
    $pdo->prepare("UPDATE admin_tasks SET is_completed = 1, completed_at = NOW() WHERE id = ?")->execute([$tid]);
    echo json_encode(['success' => true]);
    exit;
}

// --- SCHEMA MIGRATIONS ---
try {
    $cols = $pdo->query("DESCRIBE rma_tickets")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('type', $cols)) {
        $pdo->exec("ALTER TABLE rma_tickets MODIFY COLUMN type ENUM('garantia', 'devolucao', 'promessa') DEFAULT 'garantia'");
    }
} catch(Exception $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_text TEXT NOT NULL,
        task_type ENUM('daily', 'once', 'obligation', 'promise') DEFAULT 'once',
        is_completed TINYINT(1) DEFAULT 0,
        due_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL
    )");
} catch (Exception $e) {}

try {
    $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_lead', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_lead TINYINT(1) DEFAULT 0");
    }
} catch(Exception $e) {}

// --- TRAFFIC SCHEMA ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        visited_at DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_visit (ip_address, visited_at)
    )");
} catch(Exception $e) {}

// --- 1. FINANCIAL ---
$fin = ['revenue_total'=>0,'revenue_month'=>0,'pending_debt'=>0,'orders_count'=>0,'orders_pending'=>0,'today_sales'=>0,'today_count'=>0,'profit_month'=>0,'revenue_total_all'=>0];
try {
    $fin = [
        'revenue_total'    => $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('paid','shipped')")->fetchColumn(),
        'revenue_total_all'=> $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('paid','shipped')")->fetchColumn(),
        'revenue_month'    => $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('paid','shipped') AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn(),
        'pending_debt'     => $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='pending'")->fetchColumn(),
        'orders_count'     => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'orders_pending'   => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
        'today_sales'      => $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('paid','shipped') AND DATE(created_at)=CURDATE()")->fetchColumn(),
        'today_count'      => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
    ];
    // Profit: revenue - cost
    try {
        $costMonth = $pdo->query("SELECT COALESCE(SUM(oi.cost_price * oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.status IN ('paid','shipped') AND DATE_FORMAT(o.created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn();
        $fin['profit_month'] = $fin['revenue_month'] - $costMonth;
        $fin['cost_month'] = $costMonth;
    } catch(Exception $e) { $fin['profit_month'] = 0; $fin['cost_month'] = 0; }
} catch(Exception $e) {}

// --- 1.1 SPARKLINE DATA (last 7 days) ---
$sparkDays = []; $sparkRevenue = []; $sparkProfit = [];
try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayLabel = date('D', strtotime($date));
        $sparkDays[] = $dayLabel;
        $rev = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('paid','shipped') AND DATE(created_at)='$date'")->fetchColumn();
        $cost = $pdo->query("SELECT COALESCE(SUM(oi.cost_price * oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.status IN ('paid','shipped') AND DATE(o.created_at)='$date'")->fetchColumn();
        $sparkRevenue[] = round((float)$rev, 2);
        $sparkProfit[] = round((float)$rev - (float)$cost, 2);
    }
} catch(Exception $e) {}

// --- 2. INVENTORY ---
$prodParams = ['total'=>0,'active'=>0,'total_stock'=>0,'stock_value'=>0];
try {
    $prodParams = [
        'total'       => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'active'      => $pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn(),
        'total_stock' => $pdo->query("SELECT COALESCE(SUM(stock_qty),0) FROM products")->fetchColumn(),
        'stock_value' => $pdo->query("SELECT COALESCE(SUM(price*stock_qty),0) FROM products")->fetchColumn(),
    ];
} catch(Exception $e) {}
$var_stock = 0;
try { $var_stock = $pdo->query("SELECT COALESCE(SUM(v.price*v.stock_qty),0) FROM product_variations v")->fetchColumn(); } catch(Exception $e) {}
$total_inventory_value = ($prodParams['stock_value'] ?? 0) + $var_stock;

// --- 3. CUSTOMERS ---
$crm = ['total_users'=>0,'new_users'=>0,'leads_game'=>0];
try {
    $crm = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role!='admin' AND (is_lead=0 OR is_lead IS NULL)")->fetchColumn(),
        'new_users'   => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND (is_lead=0 OR is_lead IS NULL)")->fetchColumn(),
        'leads_game'  => $pdo->query("SELECT COUNT(*) FROM users WHERE is_lead=1")->fetchColumn(),
        'visits_today'=> $pdo->query("SELECT COUNT(*) FROM site_visits WHERE visited_at=CURDATE()")->fetchColumn(),
        'visits_total'=> $pdo->query("SELECT COUNT(*) FROM site_visits")->fetchColumn(),
    ];
} catch(Exception $e) {
    $crm['visits_today'] = 0; $crm['visits_total'] = 0;
}

// --- 3.1 RECENT LEADS ---
$recent_leads = [];
try {
    $recent_leads = $pdo->query("SELECT id,name,phone,email,created_at,is_lead FROM users WHERE role!='admin' ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch(Exception $e) {}

// --- 4. RECENT ORDERS ---
$recent_orders = [];
try {
    $recent_orders = $pdo->query("SELECT o.*,u.name as user_name FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 5")->fetchAll();
} catch(Exception $e) {}

// --- 5. TOP DEBTORS ---
$top_debtors = [];
try {
    $top_debtors = $pdo->query("
        SELECT u.id,u.name,u.phone,
               COALESCE(SUM(o.total_amount),0) as total_debt,
               COUNT(o.id) as pending_count
        FROM orders o JOIN users u ON o.user_id=u.id
        WHERE o.status='pending'
        GROUP BY u.id,u.name,u.phone
        ORDER BY total_debt DESC LIMIT 10
    ")->fetchAll();
} catch(Exception $e) {}

// --- 6. BEST SELLERS ---
$best_sellers = [];
try {
    $best_sellers = $pdo->query("
        SELECT oi.product_id,oi.product_name,SUM(oi.quantity) as total_sold,SUM(oi.subtotal) as total_revenue,p.stock_qty
        FROM order_items oi JOIN orders o ON oi.order_id=o.id LEFT JOIN products p ON oi.product_id=p.id
        WHERE o.status IN ('paid','shipped')
        GROUP BY oi.product_id,oi.product_name,p.stock_qty
        ORDER BY total_sold DESC LIMIT 10
    ")->fetchAll();
} catch(Exception $e) {}

// --- 7. TOP BUYERS ---
$top_buyers = [];
try {
    $top_buyers = $pdo->query("
        SELECT u.id,u.name,u.phone,COUNT(DISTINCT o.id) as total_orders,COALESCE(SUM(o.total_amount),0) as total_spent
        FROM orders o JOIN users u ON o.user_id=u.id
        WHERE o.status IN ('paid','shipped')
        GROUP BY u.id,u.name,u.phone
        ORDER BY total_spent DESC LIMIT 10
    ")->fetchAll();
} catch(Exception $e) {}

// --- 8. BUYER PRODUCTS ---
$buyer_products = [];
foreach($top_buyers as $b) {
    try {
        $stmt = $pdo->prepare("SELECT oi.product_name,SUM(oi.quantity) as qty FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.user_id=? AND o.status IN ('paid','shipped') GROUP BY oi.product_name ORDER BY qty DESC LIMIT 3");
        $stmt->execute([$b['id']]);
        $buyer_products[$b['id']] = $stmt->fetchAll();
    } catch(Exception $e) { $buyer_products[$b['id']] = []; }
}

// --- 9. CATALOG HEALTH ---
$seo = ['missing_img'=>0,'missing_desc'=>0];
try {
    $seo = [
        'missing_img'  => $pdo->query("SELECT COUNT(*) FROM products WHERE image_path IS NULL OR image_path=''")->fetchColumn(),
        'missing_desc' => $pdo->query("SELECT COUNT(*) FROM products WHERE description IS NULL OR description=''")->fetchColumn(),
    ];
} catch(Exception $e) {}

// --- 10. RMAS & PROMISES ---
$pending_rma_tickets = [];
$pending_promises    = [];
try {
    $pending_rma_tickets = $pdo->query("SELECT * FROM rma_tickets WHERE status='pending' AND type!='promessa' ORDER BY created_at DESC LIMIT 4")->fetchAll();
    $pending_promises    = $pdo->query("SELECT * FROM rma_tickets WHERE status='pending' AND type='promessa' ORDER BY created_at DESC LIMIT 4")->fetchAll();
} catch(Exception $e) {}

// --- 11. RETENTION ---
$retention_opps = [];
try {
    $retention_opps = $pdo->query("
        SELECT u.id,u.name,u.phone,DATEDIFF(NOW(),MAX(o.created_at)) as days_since,COUNT(o.id) as total_orders
        FROM users u JOIN orders o ON u.id=o.user_id
        WHERE o.status IN ('paid','shipped') AND u.role!='admin'
        GROUP BY u.id,u.name,u.phone
        HAVING days_since>=20 AND days_since<=60
        ORDER BY days_since ASC LIMIT 4
    ")->fetchAll();
} catch(Exception $e) {}

// --- 12. TASKS & ALERTS ---
$admin_tasks     = [];
$critical_alerts = [];
try {
    $admin_tasks = $pdo->query("SELECT * FROM admin_tasks WHERE is_completed=0 ORDER BY task_type='obligation' DESC, created_at DESC LIMIT 10")->fetchAll();
    
    // Get urgent tasks from admin_tasks with prefixes
    $db_tasks = $pdo->query("SELECT task_text, task_type FROM admin_tasks WHERE is_completed=0 ORDER BY created_at DESC")->fetchAll();
    foreach($db_tasks as $t) {
        $prefix = "LEMBRETE: ";
        if($t['task_type'] === 'obligation') $prefix = "🔥 OBRIGAÇÃO: ";
        if($t['task_type'] === 'promise')    $prefix = "🎁 PROMESSA: ";
        if($t['task_type'] === 'daily')      $prefix = "📅 DIÁRIO: ";
        
        $critical_alerts[] = ['task_text' => $prefix . $t['task_text']];
    }

    // Add Promises from RMA table
    foreach($pending_promises as $p) {
        $critical_alerts[] = ['task_text' => "🎁 PROMESSA (RMA): Enviar ".$p['product_name']." para ".$p['customer_name']];
    }

    // Add Debtors (Cobranças) - they disappear when order status changes from 'pending'
    foreach($top_debtors as $d) {
        if ($d['total_debt'] > 0) {
            $critical_alerts[] = ['task_text' => "💰 COBRANÇA: ".$d['name']." — R$ ".number_format($d['total_debt'],2,',','.')];
        }
    }

    // Add Pending RMAs
    foreach($pending_rma_tickets as $rma) {
        $critical_alerts[] = ['task_text' => "📦 RMA PENDENTE: ".$rma['customer_name']." (".$rma['product_name'].")"];
    }

    // Add Low Stock Alerts
    try {
        $low_stock = $pdo->query("SELECT name, stock_qty FROM products WHERE stock_qty <= 3 AND active = 1 LIMIT 5")->fetchAll();
        foreach($low_stock as $ls) {
            $critical_alerts[] = ['task_text' => "⚠️ ESTOQUE BAIXO: ".$ls['name']." (Só ".$ls['stock_qty']." un)"];
        }
    } catch(Exception $e) {}
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
    <style>
        /* ── TOKENS ─────────────────────────────────── */
        :root {
            --bg:         #0b0e14;
            --surface:    #141820;
            --surface2:   #1c2130;
            --border:     #252d3d;
            --border2:    #2e3a50;
            --text:       #e8eaf0;
            --muted:      #5a6478;
            --primary:    #f1c40f;
            --red:        #e74c3c;
            --red-dim:    rgba(231,76,60,.12);
            --green:      #2ecc71;
            --orange:     #e67e22;
            --blue:       #3498db;
            --yellow-dim: rgba(241,196,15,.10);
            --orange-dim: rgba(230,126,34,.10);
            --radius:     10px;
            --radius-sm:  6px;
        }

        /* ── BASE ───────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; }
        a { text-decoration: none; }

        /* ── LAYOUT ─────────────────────────────────── */
        .wrap        { max-width: 1400px; margin: 0 auto; padding: 0 1.5rem 3rem; }
        .grid-4      { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; }
        .grid-2      { display: grid; grid-template-columns: 1fr 1fr;        gap: 1.5rem; }
        .grid-13     { display: grid; grid-template-columns: 1fr 2fr;         gap: 1.5rem; }
        .grid-21     { display: grid; grid-template-columns: 2fr 1fr;         gap: 1.5rem; }
        .mb-1 { margin-bottom: .75rem; }
        .mb-2 { margin-bottom: 1.5rem; }
        @media(max-width:1100px) { .grid-4  { grid-template-columns: repeat(2,1fr); } }
        @media(max-width:768px)  { .grid-4,.grid-2,.grid-13,.grid-21 { grid-template-columns: 1fr; } }

        /* ── CARD ───────────────────────────────────── */
        .card  { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .cbody { padding: 1.1rem 1.25rem; }
        .card-hover { transition: transform .18s, border-color .18s, box-shadow .18s; }
        .card-hover:hover { transform: translateY(-3px); border-color: var(--border2); box-shadow: 0 8px 24px rgba(0,0,0,.4); }

        /* ── KPI ────────────────────────────────────── */
        .kpi         { position: relative; padding: 1.1rem 1.25rem; }
        .kpi-icon    { position: absolute; top: 12px; right: 12px; font-size: 1.5rem; opacity: .07; }
        .kpi-label   { font-size: .68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: .35rem; }
        .kpi-val     { font-size: 1.65rem; font-weight: 800; line-height: 1; color: var(--text); }
        .kpi-sub     { font-size: .72rem; margin-top: .4rem; color: var(--muted); }
        .kpi-sub.pos { color: var(--green); }
        .kpi-sub.neg { color: var(--red);   }

        /* ── SECTION TITLE ──────────────────────────── */
        .sec-title { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.4px; color: var(--muted); display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
        .sec-title span { display: flex; align-items: center; gap: 6px; }
        .sec-title a    { font-size: .68rem; color: var(--primary); font-weight: 700; }

        /* ── ALERT BLOCK ────────────────────────────── */
        .ab { border-radius: var(--radius); padding: 1rem 1.1rem; margin-bottom: 1.1rem; }
        .ab-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .8rem; }
        .ab-head h3 { margin: 0; font-size: .88rem; display: flex; align-items: center; gap: 7px; }
        .ab-head a  { font-size: .7rem; color: var(--muted); }
        .ab-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(205px,1fr)); gap: .75rem; }
        .ab.red    { background: var(--red-dim);    border: 1px solid rgba(231,76,60,.4); }
        .ab.yellow { background: var(--yellow-dim); border: 1px solid rgba(241,196,15,.4); }
        .ab.orange { background: var(--orange-dim); border: 1px solid rgba(230,126,34,.4); }

        /* ── MINI CARD ──────────────────────────────── */
        .mc { background: #0d1017; border: 1px solid var(--border2); border-radius: var(--radius-sm); padding: 11px 13px; }
        .mc-name { font-weight: 700; font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
        .mc-sub  { font-size: .74rem; color: var(--muted); margin-bottom: 3px; }
        .mc-tag  { font-size: .7rem; margin-bottom: 9px; }
        .mc-btn  { display: block; text-align: center; padding: 6px; border-radius: 4px; font-weight: 700; font-size: .74rem; transition: opacity .15s; }
        .mc-btn:hover { opacity: .85; }
        .mc-btn.red    { background: var(--red);     color: #fff; }
        .mc-btn.yellow { background: var(--primary); color: #000; }
        .mc-btn.green  { background: #25D366;        color: #000; }

        /* ── EMPTY STATE ────────────────────────────── */
        .empty { text-align: center; padding: 1rem; background: #0d1017; border-radius: var(--radius-sm); border: 1px solid var(--border); color: var(--muted); font-size: .78rem; }
        .empty i { display: block; font-size: 1.2rem; margin-bottom: 5px; color: var(--green); }

        /* ── TICKER ─────────────────────────────────── */
        .ticker      { background: var(--red); border-radius: var(--radius-sm); display: flex; align-items: center; overflow: hidden; height: 36px; margin-bottom: 1.1rem; }
        .ticker-lbl  { background: #b03025; padding: 0 13px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; white-space: nowrap; flex-shrink: 0; height: 100%; display: flex; align-items: center; color: #fff; gap: 5px; }
        .ticker-body { overflow: hidden; flex: 1; height: 100%; display: flex; align-items: center; }
        .ticker-roll { white-space: nowrap; display: inline-flex; gap: 2.5rem; font-size: .74rem; font-weight: 600; color: #fff; animation: roll 120s linear infinite; }
        @keyframes roll { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        /* ── TABLES ─────────────────────────────────── */
        .tbl { width: 100%; border-collapse: collapse; font-size: .8rem; }
        .tbl th { background: var(--surface2); color: var(--muted); font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .tbl td { padding: 8px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .tbl tbody tr:last-child td { border-bottom: none; }
        .tbl tbody tr:hover td { background: var(--surface2); }
        .tc { text-align: center; }
        .tr { text-align: right; }

        /* ── RANK ───────────────────────────────────── */
        .rank { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; font-weight: 800; font-size: .62rem; flex-shrink: 0; }
        .rk-1 { background: #f1c40f; color: #000; }
        .rk-2 { background: #9eaab5; color: #000; }
        .rk-3 { background: #cd7f32; color: #fff; }
        .rk-n { background: var(--border2); color: var(--muted); }

        /* ── PILL ───────────────────────────────────── */
        .pill { display: inline-block; padding: 1px 7px; border-radius: 20px; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
        .p-pending { background: rgba(241,196,15,.15); color: #f1c40f; }
        .p-paid    { background: rgba(46,204,113,.15);  color: #2ecc71; }
        .p-shipped { background: rgba(52,152,219,.15);  color: #3498db; }
        .p-lead    { background: rgba(230,126,34,.2);   color: var(--orange); }

        /* ── CUSTOM CHECKBOX ────────────────────────── */
        .chk { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .chk input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .chk-box {
            width: 15px; height: 15px; flex-shrink: 0;
            border: 1.5px solid var(--border2); border-radius: 3px;
            background: var(--surface2);
            display: flex; align-items: center; justify-content: center;
            transition: background .12s, border-color .12s;
        }
        .chk-box::after { content: ''; display: none; width: 5px; height: 8px; border: 2px solid #000; border-top: none; border-left: none; transform: rotate(45deg) translateY(-1px); }
        .chk input:checked ~ .chk-box { background: var(--green); border-color: var(--green); }
        .chk input:checked ~ .chk-box::after { display: block; }
        .chk-lbl { font-size: .8rem; font-weight: 600; color: #cdd0db; line-height: 1.3; }

        /* ── TASK ITEM ──────────────────────────────── */
        .task-item { display: flex; align-items: flex-start; gap: 9px; padding: 8px 10px; border-radius: var(--radius-sm); background: var(--surface2); margin-bottom: 6px; border-left: 2px solid var(--blue); transition: opacity .2s; }
        .task-item.ob { border-left-color: var(--red); }
        .task-item.pr { border-left-color: var(--primary); }
        .task-item .chk { padding-top: 1px; }

        /* ── QUICK ADD ──────────────────────────────── */
        .quick-add { display: flex; gap: 6px; margin-bottom: .9rem; }
        .quick-add input { flex: 1; background: var(--surface2); border: 1px solid var(--border2); color: var(--text); padding: 6px 10px; border-radius: var(--radius-sm); font-size: .78rem; }
        .quick-add input:focus { outline: none; border-color: var(--primary); }
        .quick-add button { background: var(--primary); color: #000; border: none; padding: 0 12px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 800; font-size: .75rem; transition: background .12s; }
        .quick-add button:hover { background: #d4ac0d; }

        /* ── PAGE HEADER ────────────────────────────── */
        .ph { display: flex; justify-content: space-between; align-items: center; padding: 1.4rem 0 1rem; }
        .ph h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
        .ph p  { margin: 2px 0 0; font-size: .74rem; color: var(--muted); }
        .ph-btn { display: inline-flex; align-items: center; gap: 7px; background: var(--primary); color: #000; padding: 8px 14px; border-radius: var(--radius-sm); font-size: .75rem; font-weight: 800; transition: background .12s; }
        .ph-btn:hover { background: #d4ac0d; color: #000; }

        /* ── QUICK ACTION BTN ───────────────────────── */
        .qa { display: flex; align-items: center; gap: 9px; padding: 9px 13px; border-radius: var(--radius-sm); border: 1px solid var(--border2); color: var(--text); font-size: .78rem; font-weight: 600; transition: background .13s, border-color .13s, color .13s; background: var(--surface); }
        .qa:hover       { background: var(--surface2); border-color: var(--primary); color: var(--primary); }
        .qa.primary     { background: var(--orange); border-color: var(--orange); color: #fff; }
        .qa.primary:hover { background: #d35400; border-color: #d35400; color: #fff; }
        .qa.danger:hover  { background: var(--red-dim); border-color: var(--red); color: var(--red); }
        .qa i { width: 13px; text-align: center; flex-shrink: 0; }
        .qa .ml { margin-left: auto; }

        /* ── DEBT TABLE ─────────────────────────────── */
        .debt-wrap { border: 1px solid rgba(231,76,60,.5); border-radius: var(--radius); overflow: hidden; margin-bottom: 1.5rem; }
        .debt-head { background: var(--red-dim); padding: 9px 13px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
        .debt-head strong { font-size: .78rem; color: var(--red); }
        .debt-head a      { font-size: .7rem;  color: var(--blue); }

        /* ── HEALTH ─────────────────────────────────── */
        .health-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 13px; border-radius: var(--radius-sm); background: var(--surface2); border: 1px solid var(--border); }
        .health-row .h-lbl { font-size: .7rem; color: var(--muted); margin-bottom: 1px; }
        .health-row .h-val { font-size: 1rem; font-weight: 800; }
        .health-row .h-tag { font-size: .62rem; font-weight: 800; text-transform: uppercase; }

        /* ── TIP BOX ────────────────────────────────── */
        .tip { background: var(--yellow-dim); border-left: 3px solid var(--primary); padding: 10px 13px; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; font-size: .75rem; color: #bbb; line-height: 1.5; }
        .tip strong { color: var(--primary); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="wrap">

        <!-- TICKER -->
        <?php if (!empty($critical_alerts)): ?>
        <div class="ticker" style="margin-top:1.5rem">
            <div class="ticker-lbl"><i class="fas fa-bolt"></i> URGENTE</div>
            <div class="ticker-body">
                <div class="ticker-roll">
                    <?php
                    $all = array_merge($critical_alerts, $critical_alerts);
                    foreach($all as $a):
                        $ico = 'fa-exclamation-circle';
                        if (strpos($a['task_text'],'COBRANÇA') !== false) $ico = 'fa-hand-holding-usd';
                        if (strpos($a['task_text'],'PROMESSA') !== false) $ico = 'fa-gift';
                        if (strpos($a['task_text'],'OBRIGAÇÃO') !== false) $ico = 'fa-fire';
                        if (strpos($a['task_text'],'DIÁRIO') !== false) $ico = 'fa-calendar-day';
                        if (strpos($a['task_text'],'RMA PENDENTE') !== false) $ico = 'fa-box-open';
                        if (strpos($a['task_text'],'ESTOQUE BAIXO') !== false) $ico = 'fa-cubes';
                        
                        // Wrap "R$ XX,XX" with finance-value class so it can be masked by the eye toggle
                        $displayText = htmlspecialchars($a['task_text']);
                        $displayText = preg_replace('/R\$\s?[\d\.,]+/', '$0', $displayText);
                    ?>
                    <span><i class="fas <?= $ico ?>" style="margin-right:4px;opacity:.8"></i><?= $displayText ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAGE HEADER -->
        <div class="ph">
            <div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(52,152,219,0.1); color:#3498db;"><i class="fas fa-eye"></i></div>
                    <div class="stat-val"><?php echo number_format($crm['visits_today']); ?></div>
                    <div class="stat-label">Visitas Hoje</div>
                    <div style="font-size:0.7rem; color:#888; margin-top:5px;">Total: <?php echo number_format($crm['visits_total']); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(46,204,113,0.1); color:#2ecc71;"><i class="fas fa-search"></i></div>
                    <div class="stat-val">100%</div>
                    <div class="stat-label">Saúde SEO</div>
                    <div style="font-size:0.7rem; color:#2ecc71; margin-top:5px;"><i class="fas fa-check"></i> Meta tags ativas</div>
                </div>
                <h1>Visão Geral do Império <span style="font-size:.7rem;color:var(--muted);font-weight:400">v5.0</span></h1>
                <p id="greeting-text">Bem-vindo, Administrador.</p>
            </div>
            <div style="display:flex;align-items:center;gap:12px">
                <div id="live-clock" style="font-size:1.6rem;font-weight:800;color:var(--primary);font-family:'Courier New',monospace;letter-spacing:2px"></div>
                <a href="tasks.php" class="ph-btn"><i class="fas fa-tasks"></i> Central de Obrigações</a>
            </div>
        </div>

        <!-- RMA -->
        <div class="ab red">
            <div class="ab-head">
                <h3 style="color:var(--red)"><i class="fas fa-exclamation-triangle"></i> RMAs / Garantias Pendentes</h3>
                <a href="rma.php">Ver Painel ➔</a>
            </div>
            <?php if (!empty($pending_rma_tickets)): ?>
                <div class="ab-grid">
                <?php foreach($pending_rma_tickets as $rma): ?>
                    <div class="mc">
                        <div class="mc-name"><?= htmlspecialchars($rma['customer_name']) ?></div>
                        <div class="mc-sub" style="font-size:0.65rem; color:var(--primary); margin-bottom:5px;">🛒 <?= htmlspecialchars($rma['marketplace'] ?: 'Venda Direta') ?></div>
                        <div class="mc-sub"><i class="fab fa-whatsapp"></i> <strong><?= htmlspecialchars($rma['phone']) ?></strong></div>
                        <div class="mc-sub">Produto: <strong style="color:var(--text)"><?= htmlspecialchars($rma['product_name']) ?></strong></div>
                        <div class="mc-tag" style="color:#f39c12">Solicitado em: <?= $rma['request_date'] ? date('d/m/Y', strtotime($rma['request_date'])) : date('d/m/Y', strtotime($rma['created_at'])) ?></div>
                        <a href="rma.php" class="mc-btn red"><i class="fas fa-shipping-fast"></i> Gerar Envio / Resolver</a>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty"><i class="fas fa-check-circle"></i>Nenhum RMA pendente no momento.</div>
            <?php endif; ?>
        </div>

        <!-- TASKS / ROUTINE -->
        <div class="ab yellow">
            <div class="ab-head">
                <h3 style="color:var(--primary)"><i class="fas fa-calendar-check"></i> Rotina & Obrigações</h3>
                <a href="tasks.php">Ver Central ➔</a>
            </div>
            <?php if (!empty($admin_tasks)): ?>
                <div class="ab-grid">
                <?php foreach($admin_tasks as $task): 
                    $type_label = "Tarefa";
                    $type_color = "var(--muted)";
                    if ($task['task_type'] === 'obligation') { $type_label = "Obrigação"; $type_color = "var(--red)"; }
                    if ($task['task_type'] === 'daily')      { $type_label = "Diário";    $type_color = "var(--blue)"; }
                    if ($task['task_type'] === 'promise')    { $type_label = "Promessa";  $type_color = "var(--primary)"; }
                ?>
                    <div class="mc" id="task-card-<?= $task['id'] ?>">
                        <div class="mc-name"><?= htmlspecialchars($task['task_text']) ?></div>
                        <div class="mc-tag" style="color:<?= $type_color ?>; font-weight:700;"><?= $type_label ?></div>
                        <div class="mc-sub">Criado em: <?= date('d/m/Y', strtotime($task['created_at'])) ?></div>
                        <button onclick="resolveTask(<?= $task['id'] ?>)" class="mc-btn yellow" style="width:100%; border:none; cursor:pointer;">
                            <i class="fas fa-check-circle"></i> Resolver Já
                        </button>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty"><i class="fas fa-smile"></i> Nenhuma obrigação pendente para hoje!</div>
            <?php endif; ?>
        </div>

        <!-- PROMISES -->
        <?php if (!empty($pending_promises)): ?>
        <div class="ab yellow">
            <div class="ab-head">
                <h3 style="color:var(--primary)"><i class="fas fa-gift"></i> Promessas: Brindes / Amostras</h3>
                <a href="rma.php">Gerenciar ➔</a>
            </div>
            <div class="ab-grid">
            <?php foreach($pending_promises as $p): ?>
                <div class="mc">
                    <div class="mc-name"><?= htmlspecialchars($p['customer_name']) ?></div>
                    <div class="mc-sub">Item: <strong style="color:var(--text)"><?= htmlspecialchars($p['product_name']) ?></strong></div>
                    <div class="mc-tag" style="color:var(--primary)">Contexto: <?= htmlspecialchars($p['issue_type']) ?></div>
                    <a href="rma.php" class="mc-btn yellow"><i class="fas fa-shipping-fast"></i> Cumprir Promessa</a>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- RETENTION -->
        <div class="ab orange mb-2">
            <div class="ab-head">
                <h3 style="color:var(--orange)"><i class="fas fa-bullseye"></i> Oportunidades de Recompra</h3>
                <a href="retention.php">Ver CRM ➔</a>
            </div>
            <?php if (!empty($retention_opps)): ?>
                <div class="ab-grid">
                <?php foreach($retention_opps as $opp):
                    $phone = preg_replace('/\D/','',$opp['phone']);
                    if (!str_starts_with($phone,'55') && strlen($phone)<=11) $phone='55'.$phone;
                    $fn  = explode(' ',trim($opp['name']))[0];
                    $msg = rawurlencode("Fala ".$fn.", tudo bem? Aqui é da Fight Arcade. Vi que faz uns ".$opp['days_since']." dias da sua última compra, precisando de algo novo pra projeto? Tenho descontos!");
                ?>
                    <div class="mc">
                        <div class="mc-name"><?= htmlspecialchars($opp['name']) ?></div>
                        <div class="mc-sub">Última compra: <strong>Há <?= (int)$opp['days_since'] ?> dias</strong></div>
                        <div class="mc-tag" style="color:#f39c12"><?= (int)$opp['total_orders'] ?>× pedidos totais</div>
                        <a href="https://wa.me/<?= $phone ?>?text=<?= $msg ?>" target="_blank" class="mc-btn green"><i class="fab fa-whatsapp"></i> Abordar Cliente</a>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty"><i class="fas fa-check-circle"></i>Nenhum cliente na janela ideal de recompra (20–60 dias) hoje.</div>
            <?php endif; ?>
        </div>

        <!-- KPI CARDS -->
        <div class="grid-4 mb-2">
            <div class="card card-hover kpi" style="border-bottom:2px solid var(--green)">
                <i class="fas fa-wallet kpi-icon" style="color:var(--green)"></i>
                <div class="kpi-label">Receita (Mês)</div>
                <div class="kpi-val"><span class="finance-value">R$ <?= number_format($fin['revenue_month'],2,',','.') ?></span></div>
                <div class="kpi-sub pos"><i class="fas fa-arrow-up"></i> Fluxo de caixa</div>
            </div>
            <div class="card card-hover kpi" style="border-bottom:2px solid #9b59b6">
                <i class="fas fa-chart-line kpi-icon" style="color:#9b59b6"></i>
                <div class="kpi-label">Lucro Estimado (Mês)</div>
                <div class="kpi-val" style="color:#9b59b6"><span class="finance-value">R$ <?= number_format($fin['profit_month'],2,',','.') ?></span></div>
                <?php $margin = $fin['revenue_month'] > 0 ? round(($fin['profit_month'] / $fin['revenue_month']) * 100) : 0; ?>
                <div class="kpi-sub" style="color:<?= $margin >= 30 ? 'var(--green)' : ($margin >= 15 ? 'var(--primary)' : 'var(--red)') ?>">
                    <i class="fas fa-percentage"></i> Margem: <?= $margin ?>%
                </div>
            </div>
            <div class="card card-hover kpi" style="border-bottom:2px solid var(--red)">
                <i class="fas fa-exclamation-circle kpi-icon" style="color:var(--red)"></i>
                <div class="kpi-label">A Receber</div>
                <div class="kpi-val" style="color:var(--red)"><span class="finance-value">R$ <?= number_format($fin['pending_debt'],2,',','.') ?></span></div>
                <div class="kpi-sub neg"><?= (int)$fin['orders_pending'] ?> pedidos pendentes</div>
            </div>
            <div class="card card-hover kpi" style="border-bottom:2px solid var(--primary);position:relative;overflow:visible">
                <i class="fas fa-bolt kpi-icon" style="color:var(--primary)"></i>
                <div class="kpi-label">Vendas Hoje</div>
                <div class="kpi-val" style="color:var(--primary)">
                    <span class="finance-value">R$ <?= number_format($fin['today_sales'],2,',','.') ?></span>
                </div>
                <div class="kpi-sub">
                    <?= (int)$fin['today_count'] ?> pedido(s) hoje
                    <?php if ($fin['today_count'] > 0): ?>
                        <span style="color:var(--green);margin-left:6px">🔥</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- KPI ROW 2 -->
        <div class="grid-4 mb-2">
            <div class="card card-hover kpi">
                <i class="fas fa-users kpi-icon" style="color:var(--blue)"></i>
                <div class="kpi-label">Clientes</div>
                <div class="kpi-val"><?= (int)$crm['total_users'] ?></div>
                <div class="kpi-sub pos">+<?= (int)$crm['new_users'] ?> novos (30d)</div>
            </div>
            <div class="card card-hover kpi">
                <i class="fas fa-boxes kpi-icon" style="color:var(--primary)"></i>
                <div class="kpi-label">Estoque (Valor)</div>
                <div class="kpi-val" style="font-size:1.35rem"><span class="finance-value">R$ <?= number_format($total_inventory_value,2,',','.') ?></span></div>
                <div class="kpi-sub"><?= (int)$prodParams['total_stock'] ?> itens</div>
            </div>
            <div class="card card-hover kpi">
                <i class="fas fa-receipt kpi-icon" style="color:var(--green)"></i>
                <div class="kpi-label">Receita Total</div>
                <div class="kpi-val" style="font-size:1.25rem"><span class="finance-value">R$ <?= number_format($fin['revenue_total_all'],2,',','.') ?></span></div>
                <div class="kpi-sub"><?= (int)$fin['orders_count'] ?> pedidos totais</div>
            </div>
            <div class="card card-hover kpi">
                <i class="fas fa-bullseye kpi-icon" style="color:var(--orange)"></i>
                <div class="kpi-label">Leads Ativos</div>
                <div class="kpi-val"><?= (int)$crm['leads_game'] ?></div>
                <div class="kpi-sub"><a href="leads.php" style="color:var(--orange);font-size:.72rem">Gerenciar →</a></div>
            </div>
        </div>

        <!-- SPARKLINE CHARTS -->
        <div class="grid-2 mb-2">
            <div class="card cbody">
                <div class="sec-title" style="margin-bottom:.4rem"><span><i class="fas fa-chart-area" style="color:var(--green)"></i> Vendas (7 dias)</span></div>
                <div style="position:relative; height:180px; width:100%">
                    <canvas id="chartRevenue"></canvas>
                </div>
            </div>
            <div class="card cbody">
                <div class="sec-title" style="margin-bottom:.4rem"><span><i class="fas fa-chart-line" style="color:#9b59b6"></i> Lucro (7 dias)</span></div>
                <div style="position:relative; height:180px; width:100%">
                    <canvas id="chartProfit"></canvas>
                </div>
            </div>
        </div>

        <!-- DEBTORS -->
        <?php if (!empty($top_debtors)): ?>
        <div class="debt-wrap mb-2">
            <div class="debt-head">
                <strong><i class="fas fa-exclamation-triangle" style="margin-right:5px"></i>Pendências / Devedores</strong>
                <a href="customers.php?filter=debtors">Ver todos →</a>
            </div>
            <table class="tbl">
                <thead><tr><th>Cliente</th><th class="tc">Pend.</th><th class="tr">Dívida</th><th></th></tr></thead>
                <tbody>
                <?php foreach($top_debtors as $d): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                    <td class="tc"><?= (int)$d['pending_count'] ?></td>
                    <td class="tr" style="color:var(--red);font-weight:700"><span class="finance-value">R$ <?= number_format($d['total_debt'],2,',','.') ?></span></td>
                    <td style="width:65px">
                        <a href="customer-details.php?id=<?= (int)$d['id'] ?>" style="color:var(--blue);margin-right:8px;font-size:.8rem">💰</a>
                        <?php if(!empty($d['phone'])): ?>
                        <a href="https://api.whatsapp.com/send?phone=55<?= preg_replace('/\D/','',$d['phone']) ?>&text=<?= rawurlencode('Olá, vi que tem um pedido pendente...') ?>" target="_blank" style="color:var(--green);font-size:.82rem"><i class="fab fa-whatsapp"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- BEST SELLERS + TOP BUYERS -->
        <div class="grid-2 mb-2">
            <div>
                <div class="sec-title"><span><i class="fas fa-fire" style="color:var(--orange)"></i> Mais Vendidos</span></div>
                <div class="card">
                    <table class="tbl">
                        <thead><tr><th style="width:32px">#</th><th>Produto</th><th class="tc">Vendas</th><th class="tc">Estoque</th></tr></thead>
                        <tbody>
                        <?php foreach($best_sellers as $i => $bs): ?>
                        <tr style="<?= ($bs['stock_qty']!==null&&$bs['stock_qty']<=5)?'background:rgba(231,76,60,.05);':'' ?>">
                            <td><span class="rank <?= $i<3?'rk-'.($i+1):'rk-n' ?>"><?= $i+1 ?></span></td>
                            <td>
                                <?= htmlspecialchars($bs['product_name']) ?>
                                <?php if($bs['stock_qty']!==null&&$bs['stock_qty']<=5): ?>
                                <br><small style="color:var(--red);font-size:.64rem;font-weight:700"><i class="fas fa-exclamation-triangle"></i> Estoque crítico</small>
                                <?php endif; ?>
                            </td>
                            <td class="tc" style="font-weight:700;color:var(--primary)"><?= (int)$bs['total_sold'] ?></td>
                            <td class="tc" style="font-weight:700;color:<?= ($bs['stock_qty']!==null&&$bs['stock_qty']<=5)?'var(--red)':'var(--green)' ?>">
                                <?= $bs['stock_qty']!==null?(int)$bs['stock_qty'].' un':'-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($best_sellers)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--muted)">Nenhuma venda registrada.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="sec-title"><span><i class="fas fa-crown" style="color:var(--primary)"></i> Melhores Compradores</span></div>
                <div class="card">
                    <table class="tbl">
                        <thead><tr><th style="width:32px">#</th><th>Cliente</th><th class="tc">Pedidos</th><th class="tr">Total</th></tr></thead>
                        <tbody>
                        <?php foreach($top_buyers as $i => $tb): ?>
                        <tr>
                            <td><span class="rank <?= $i<3?'rk-'.($i+1):'rk-n' ?>"><?= $i+1 ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($tb['name']) ?></strong>
                                <?php if(!empty($buyer_products[$tb['id']])): ?>
                                <br><small style="color:var(--muted);font-size:.68rem"><?= implode(', ',array_map(function($p){ return htmlspecialchars($p['product_name']).' ('.(int)$p['qty'].'×)'; },$buyer_products[$tb['id']])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="tc"><?= (int)$tb['total_orders'] ?></td>
                            <td class="tr" style="color:var(--green);font-weight:700"><span class="finance-value">R$ <?= number_format($tb['total_spent'],2,',','.') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($top_buyers)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--muted)">Nenhum comprador ainda.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TASKS + CRM -->
        <div class="grid-13 mb-2">

            <!-- TASKS -->
            <div>
                <div class="sec-title"><span><i class="fas fa-tasks"></i> Rotina & Obrigações</span><a href="tasks.php">Ver tudo →</a></div>
                <div class="card cbody">
                    <div class="quick-add">
                        <input type="text" id="dashTaskText" placeholder="Novo lembrete...">
                        <button onclick="dashAddTask()"><i class="fas fa-plus"></i> Add</button>
                    </div>
                    <div id="dailyChecklist">
                    <?php foreach($admin_tasks as $task):
                        $cls='';
                        if($task['task_type']==='obligation') $cls='ob';
                        elseif($task['task_type']==='promise') $cls='pr';
                    ?>
                        <div class="task-item <?= $cls ?>" id="task-<?= (int)$task['id'] ?>">
                            <label class="chk" style="flex-shrink:0;padding-top:1px">
                                <input type="checkbox" onchange="dashToggleTask(<?= (int)$task['id'] ?>)">
                                <span class="chk-box"></span>
                            </label>
                            <span class="chk-lbl"><?= htmlspecialchars($task['task_text']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($admin_tasks)): ?>
                        <div class="empty" style="margin-top:0"><i class="fas fa-check-circle"></i>Nenhuma tarefa pendente! 🎉</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CRM -->
            <div>
                <div class="sec-title"><span><i class="fas fa-headset"></i> CRM: Captação e Pós-Venda</span></div>
                <div class="card">
                    <table class="tbl">
                        <thead><tr><th style="width:36px;text-align:center">✓</th><th>Cliente / Lead</th><th>Contato</th><th>Ação</th></tr></thead>
                        <tbody>
                        <?php foreach($recent_leads as $lead): ?>
                        <tr>
                            <td class="tc">
                                <label class="chk" style="justify-content:center">
                                    <input type="checkbox" class="crm-check" data-id="<?= (int)$lead['id'] ?>">
                                    <span class="chk-box"></span>
                                </label>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($lead['name']) ?></strong>
                                <?php if(!empty($lead['is_lead'])): ?><span class="pill p-lead" style="margin-left:4px">Lead</span><?php endif; ?>
                                <br><small style="color:var(--muted)">Desde <?= date('d/m/Y',strtotime($lead['created_at'])) ?></small>
                            </td>
                            <td style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($lead['phone'] ?: $lead['email']) ?></td>
                            <td>
                                <?php if(!empty($lead['phone'])): ?>
                                <a href="https://api.whatsapp.com/send?phone=55<?= preg_replace('/\D/','',$lead['phone']) ?>&text=<?= rawurlencode('Olá!') ?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;background:#25D366;color:#000;padding:4px 8px;border-radius:4px;font-size:.7rem;font-weight:800"><i class="fab fa-whatsapp"></i> Chamar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_leads)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--muted)">Nenhum cliente recente.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ORDERS + SIDE PANEL -->
        <div class="grid-21">
            <div>
                <div class="sec-title"><span><i class="fas fa-clock"></i> Últimos Pedidos</span></div>
                <div class="card">
                    <table class="tbl">
                        <thead><tr><th>ID</th><th>Cliente</th><th class="tr">Valor</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach($recent_orders as $o): ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.72rem">#<?= (int)$o['id'] ?></td>
                            <td><?= htmlspecialchars($o['user_name']) ?></td>
                            <td class="tr" style="font-weight:700"><span class="finance-value">R$ <?= number_format($o['total_amount'],2,',','.') ?></span></td>
                            <td>
                                <?php
                                    $pc = ['pending'=>'p-pending','paid'=>'p-paid','shipped'=>'p-shipped'];
                                    $pc = $pc[$o['status']] ?? '';
                                ?>
                                <span class="pill <?= $pc ?>"><?= htmlspecialchars($o['status']) ?></span>
                            </td>
                            <td style="text-align:right"><a href="edit_order.php?id=<?= (int)$o['id'] ?>" style="color:var(--blue);font-size:.8rem"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_orders)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--muted)">Nenhum pedido ainda.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SIDE PANEL -->
            <div>
                <div class="sec-title"><span><i class="fas fa-rocket"></i> Ações Rápidas</span></div>
                <div style="display:grid;gap:6px;margin-bottom:1.5rem">
                    <a href="pos.php"                       class="qa primary"><i class="fas fa-cash-register"></i> Abrir PDV</a>
                    <a href="purchase_pos.php"              class="qa primary" style="background:#2ecc71; border-color:#2ecc71;"><i class="fas fa-cart-plus"></i> PDV Compra</a>
                    <a href="product-edit.php"              class="qa"><i class="fas fa-plus-circle"></i> Add Produto</a>
                    <a href="customers.php"                 class="qa"><i class="fas fa-users"></i> Clientes</a>
                    <a href="suppliers.php"                 class="qa"><i class="fas fa-truck-loading"></i> Fornecedores</a>
                    <a href="customers.php?filter=debtors"  class="qa danger"><i class="fas fa-hand-holding-usd"></i> Devedores</a>
                    <a href="lalamove.php"                  class="qa" style="border-color:#FF6600;color:#FF6600"><i class="fas fa-motorcycle"></i> Lalamove Express</a>
                    <a href="melhorenvio.php"               class="qa"><i class="fas fa-truck"></i> Melhor Envio</a>
                    <a href="reports.php"                   class="qa"><i class="fas fa-chart-bar"></i> Relatórios</a>
                    <a href="leads.php"                     class="qa"><i class="fas fa-bullseye"></i> Leads <span class="pill p-lead ml"><?= (int)$crm['leads_game'] ?></span></a>
                </div>

                <div class="sec-title"><span><i class="fas fa-heartbeat"></i> Saúde do Catálogo</span></div>
                <div style="display:grid;gap:6px;margin-bottom:1.25rem">
                    <div class="health-row" style="border-color:<?= $seo['missing_img']>0?'rgba(231,76,60,.4)':'var(--border)' ?>">
                        <div><div class="h-lbl">Sem Foto</div><div class="h-val" style="color:<?= $seo['missing_img']>0?'var(--red)':'var(--green)' ?>"><?= (int)$seo['missing_img'] ?></div></div>
                        <span class="h-tag" style="color:<?= $seo['missing_img']>0?'var(--red)':'var(--green)' ?>"><?= $seo['missing_img']>0?'CRÍTICO':'OK' ?></span>
                    </div>
                    <div class="health-row" style="border-color:<?= $seo['missing_desc']>0?'rgba(241,196,15,.3)':'var(--border)' ?>">
                        <div><div class="h-lbl">Sem Descrição</div><div class="h-val" style="color:<?= $seo['missing_desc']>0?'var(--primary)':'var(--green)' ?>"><?= (int)$seo['missing_desc'] ?></div></div>
                        <span class="h-tag" style="color:<?= $seo['missing_desc']>0?'var(--primary)':'var(--green)' ?>"><?= $seo['missing_desc']>0?'ATENÇÃO':'OK' ?></span>
                    </div>
                </div>

                <div class="tip"><strong>💡 Dica:</strong> Use "Mais Vendidos" para priorizar reposição de estoque e maximizar o giro.</div>
            </div>
        </div>

    </div><!-- /.wrap -->

    <script>
        function resolveTask(tid) {
            if(!confirm('Marcar tarefa como concluída?')) return;
            const fd = new FormData();
            fd.append('toggle_task', '1');
            fd.append('task_id', tid);
            fetch('dashboard.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        const card = document.getElementById('task-card-' + tid);
                        if(card) card.style.opacity = '0.3';
                        location.reload(); // Reload to refresh ticker and stats
                    }
                });
        }

        // ── CRM Checkboxes ────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.crm-check').forEach(chk => {
                const id  = chk.dataset.id;
                const row = chk.closest('tr');
                if (localStorage.getItem('crm_' + id)) {
                    chk.checked = true;
                    row.style.opacity = '.4';
                }
                chk.addEventListener('change', function () {
                    if (this.checked) { localStorage.setItem('crm_' + id, '1'); row.style.opacity = '.4'; }
                    else              { localStorage.removeItem('crm_' + id);   row.style.opacity = '1';  }
                });
            });
        });

        // ── Add Task ──────────────────────────────────────
        async function dashAddTask() {
            const input = document.getElementById('dashTaskText');
            const text  = input.value.trim();
            if (!text) return;
            input.value = '';
            const fd = new FormData();
            fd.append('task_text', text);
            fd.append('task_type', 'once');
            await fetch('tasks.php?action=add', { method: 'POST', body: fd });
            location.reload();
        }
        document.getElementById('dashTaskText')?.addEventListener('keydown', e => { if (e.key === 'Enter') dashAddTask(); });

        // ── Toggle Task ───────────────────────────────────
        async function dashToggleTask(id) {
            const el = document.getElementById('task-' + id);
            if (el) el.style.opacity = '.35';
            const fd = new FormData();
            fd.append('id', id);
            await fetch('tasks.php?action=toggle', { method: 'POST', body: fd });
            setTimeout(() => location.reload(), 320);
        }

        // ── Live Clock + Greeting ─────────────────────────
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2,'0');
            const m = String(now.getMinutes()).padStart(2,'0');
            const s = String(now.getSeconds()).padStart(2,'0');
            document.getElementById('live-clock').textContent = `${h}:${m}:${s}`;
            const hour = now.getHours();
            let greeting = '🌙 Boa noite';
            if (hour >= 5 && hour < 12) greeting = '☀️ Bom dia';
            else if (hour >= 12 && hour < 18) greeting = '🌤️ Boa tarde';
            document.getElementById('greeting-text').textContent = `${greeting}, Administrador.`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ── Sparkline Charts ──────────────────────────────
        const sparkDays = <?= json_encode($sparkDays) ?>;
        const sparkRevenue = <?= json_encode($sparkRevenue) ?>;
        const sparkProfit = <?= json_encode($sparkProfit) ?>;

        const chartCfg = (ctx, data, color, label) => new Chart(ctx, {
            type: 'line',
            data: {
                labels: sparkDays,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: color,
                    backgroundColor: color + '20',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: color,
                    pointBorderColor: '#141820',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1e26',
                        borderColor: color,
                        borderWidth: 1,
                        titleColor: '#fff',
                        bodyColor: color,
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: ctx => 'R$ ' + ctx.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits:2})
                        }
                    }
                },
                scales: {
                    x: { grid: { color: '#1c2130' }, ticks: { color: '#5a6478', font: { size: 10, weight: 700 } } },
                    y: { grid: { color: '#1c2130' }, ticks: { color: '#5a6478', font: { size: 10 }, callback: v => 'R$' + (v/1000).toFixed(1) + 'k' } }
                }
            }
        });

        chartCfg(document.getElementById('chartRevenue'), sparkRevenue, '#2ecc71', 'Receita');
        chartCfg(document.getElementById('chartProfit'), sparkProfit, '#9b59b6', 'Lucro');
    </script>
</body>
</html>
<?php
} catch (Throwable $e) {
    ?>
    <div style="background:#0b0e14; color:#fff; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif; padding:20px; text-align:center;">
        <h1 style="font-size:4rem; margin-bottom:0;">📊</h1>
        <h2 style="color:#e74c3c;">Falha ao Carregar o Painel</h2>
        <p style="color:#888; max-width:600px; margin-bottom:30px;">Ocorreu um erro ao processar as estatísticas do sistema. Isso pode ser causado por tabelas de log ou colunas faltantes.</p>
        
        <div style="background:#1a1e2a; padding:20px; border-radius:10px; border:1px solid #333; margin-bottom:30px; text-align:left; font-family:monospace; font-size:0.9rem; max-width:800px; overflow-x:auto;">
            <b style="color:#f1c40f;">Erro:</b><br>
            <?php echo $e->getMessage(); ?>
        </div>

        <div style="display:flex; gap:15px;">
            <a href="emergency_fix.php" style="background:#f1c40f; color:#000; padding:12px 25px; border-radius:8px; font-weight:bold; text-decoration:none;">🚀 RODAR REPARO DE EMERGÊNCIA</a>
            <a href="login.php" style="background:#333; color:#fff; padding:12px 25px; border-radius:8px; text-decoration:none;">🚪 Tentar Relogar</a>
        </div>
    </div>
    <?php
}
?>