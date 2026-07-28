<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// --- 1. SCHEMA MIGRATION ---
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
} catch (Exception $e) { }

// --- 2. HANDLE AJAX ACTIONS ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'add') {
        $text = $_POST['task_text'] ?? '';
        $type = $_POST['task_type'] ?? 'once';
        if ($text) {
            $stmt = $pdo->prepare("INSERT INTO admin_tasks (task_text, task_type) VALUES (?, ?)");
            $stmt->execute([$text, $type]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'toggle') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE admin_tasks SET is_completed = NOT is_completed, completed_at = CASE WHEN is_completed = 0 THEN NOW() ELSE NULL END WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM admin_tasks WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_GET['action'] === 'list') {
        $tasks = $pdo->query("SELECT * FROM admin_tasks ORDER BY is_completed ASC, created_at DESC")->fetchAll();
        echo json_encode($tasks);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Obrigações | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #0b0e14;
            --surface: #141820;
            --surface2: #1c2130;
            --border: #252d3d;
            --primary: #f1c40f;
            --red: #e74c3c;
            --green: #2ecc71;
            --blue: #3498db;
            --text: #e8eaf0;
            --muted: #5a6478;
            --radius: 12px;
        }

        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }
        
        /* HEADER */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .page-header h1 { margin: 0; font-size: 1.75rem; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: var(--primary); }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--surface2); color: var(--text); padding: 10px 18px; border-radius: 8px; border: 1px solid var(--border); font-size: 0.85rem; font-weight: 600; transition: all 0.2s; }
        .btn-back:hover { background: var(--border); border-color: var(--muted); }

        /* QUICK ADD */
        .add-card { background: var(--surface); padding: 1.5rem; border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 2.5rem; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .add-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--primary); }
        .add-grid { display: grid; grid-template-columns: 1fr auto auto; gap: 12px; align-items: center; }
        
        .add-card input { background: #0d1017; border: 1px solid var(--border); color: #fff; padding: 12px 16px; border-radius: 8px; font-size: 1rem; width: 100%; outline: none; transition: border-color 0.2s; }
        .add-card input:focus { border-color: var(--primary); }
        
        .add-card select { background: #0d1017; border: 1px solid var(--border); color: #fff; padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; cursor: pointer; outline: none; }
        
        .btn-submit { background: var(--primary); color: #000; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.9rem; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-submit:hover { background: #d4ac0d; transform: translateY(-1px); }

        /* LISTS */
        .section-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .task-list { display: grid; gap: 10px; margin-bottom: 3rem; }
        
        .task-card { 
            background: var(--surface); 
            border: 1px solid var(--border); 
            border-radius: 10px; 
            padding: 12px 16px; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            transition: all 0.2s;
            animation: fadeIn 0.3s ease-out forwards;
        }
        .task-card:hover { border-color: var(--muted); background: var(--surface2); }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* CUSTOM CHECKBOX */
        .chk-container { position: relative; width: 24px; height: 24px; }
        .chk-container input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
        .checkmark { position: absolute; top: 0; left: 0; height: 24px; width: 24px; background-color: #0d1017; border: 2px solid var(--border); border-radius: 6px; transition: all 0.2s; }
        .chk-container:hover input ~ .checkmark { border-color: var(--primary); }
        .chk-container input:checked ~ .checkmark { background-color: var(--green); border-color: var(--green); }
        .checkmark:after { content: ""; position: absolute; display: none; left: 8px; top: 4px; width: 5px; height: 10px; border: solid #000; border-width: 0 3px 3px 0; transform: rotate(45deg); }
        .chk-container input:checked ~ .checkmark:after { display: block; }

        .task-content { flex: 1; }
        .task-text { font-size: 1.05rem; font-weight: 600; color: var(--text); display: block; }
        .task-card.done { opacity: 0.5; }
        .task-card.done .task-text { text-decoration: line-through; color: var(--muted); }

        /* BADGES */
        .badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px; }
        .badge-once { background: rgba(127,140,141,0.15); color: #95a5a6; }
        .badge-daily { background: rgba(52,152,219,0.15); color: var(--blue); }
        .badge-obligation { background: rgba(231,76,60,0.15); color: var(--red); }
        .badge-promise { background: rgba(241,196,15,0.15); color: var(--primary); }

        .task-actions { display: flex; gap: 10px; }
        .btn-delete { background: none; border: none; color: var(--muted); cursor: pointer; padding: 8px; font-size: 1rem; transition: color 0.2s; }
        .btn-delete:hover { color: var(--red); }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 3rem; background: var(--surface); border: 2px dashed var(--border); border-radius: var(--radius); color: var(--muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3; }
        .empty-state p { margin: 0; font-weight: 600; }

        @media (max-width: 768px) {
            .add-grid { grid-template-columns: 1fr; }
            .add-card select { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-tasks"></i> Central de Obrigações</h1>
                <p style="color:var(--muted); margin: 5px 0 0; font-size: 0.9rem;">Organize suas tarefas diárias e compromissos críticos.</p>
            </div>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-home"></i> Dashboard</a>
        </div>

        <!-- NOVO LEMBRETE -->
        <div class="add-card">
            <div class="add-grid">
                <input type="text" id="newTaskText" placeholder="O que precisa ser feito hoje?">
                <select id="newTaskType">
                    <option value="once">⚡ Único</option>
                    <option value="daily">📅 Diário</option>
                    <option value="obligation">🔥 Obrigação</option>
                    <option value="promise">🎁 Promessa</option>
                </select>
                <button class="btn-submit" onclick="addTask()">Adicionar</button>
            </div>
        </div>

        <!-- LISTA PENDENTES -->
        <div class="section-title">Pendentes</div>
        <div id="pendingTasks" class="task-list">
            <!-- Rendered by JS -->
        </div>

        <!-- LISTA CONCLUÍDAS -->
        <div class="section-title">Recentemente Concluídas</div>
        <div id="completedTasks" class="task-list">
            <!-- Rendered by JS -->
        </div>
    </div>

    <script>
        const taskIcons = {
            once: 'fa-thumbtack',
            daily: 'fa-calendar-day',
            obligation: 'fa-fire',
            promise: 'fa-gift'
        };

        const taskLabels = {
            once: 'Único',
            daily: 'Diário',
            obligation: 'Obrigação',
            promise: 'Promessa'
        };

        async function fetchTasks() {
            const res = await fetch('tasks.php?action=list');
            const tasks = await res.json();
            const pendingDiv = document.getElementById('pendingTasks');
            const completedDiv = document.getElementById('completedTasks');
            
            pendingDiv.innerHTML = '';
            completedDiv.innerHTML = '';
            
            let pendingCount = 0;
            let completedCount = 0;
            
            tasks.forEach(task => {
                const isDone = task.is_completed == 1;
                const html = `
                    <div class="task-card ${isDone ? 'done' : ''}" id="task-${task.id}">
                        <label class="chk-container">
                            <input type="checkbox" ${isDone ? 'checked' : ''} onchange="toggleTask(${task.id})">
                            <span class="checkmark"></span>
                        </label>
                        <div class="task-content">
                            <span class="task-text">${task.task_text}</span>
                            <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                                <span class="badge badge-${task.task_type}">
                                    <i class="fas ${taskIcons[task.task_type]}"></i> ${taskLabels[task.task_type]}
                                </span>
                                ${isDone ? `<span style="font-size: 0.65rem; color: var(--muted)">Concluído em: ${new Date(task.completed_at).toLocaleDateString()}</span>` : ''}
                            </div>
                        </div>
                        <div class="task-actions">
                            <button class="btn-delete" onclick="deleteTask(${task.id})" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </div>
                `;
                
                if (isDone) {
                    completedDiv.innerHTML += html;
                    completedCount++;
                } else {
                    pendingDiv.innerHTML += html;
                    pendingCount++;
                }
            });

            if (pendingCount === 0) {
                pendingDiv.innerHTML = `<div class="empty-state"><i class="fas fa-check-double"></i><p>Nenhuma tarefa pendente. Tudo em ordem!</p></div>`;
            }
            if (completedCount === 0) {
                completedDiv.innerHTML = `<p style="text-align:center; color:var(--muted); font-size:0.85rem; padding: 1rem;">Nenhuma tarefa concluída recentemente.</p>`;
            }
        }

        async function addTask() {
            const input = document.getElementById('newTaskText');
            const text = input.value.trim();
            const type = document.getElementById('newTaskType').value;
            
            if (!text) return;
            
            const formData = new FormData();
            formData.append('task_text', text);
            formData.append('task_type', type);
            
            const btn = document.querySelector('.btn-submit');
            btn.disabled = true;
            btn.innerText = '...';
            
            await fetch('tasks.php?action=add', { method: 'POST', body: formData });
            
            input.value = '';
            btn.disabled = false;
            btn.innerText = 'Adicionar';
            fetchTasks();
        }

        async function toggleTask(id) {
            const card = document.getElementById('task-' + id);
            card.style.opacity = '0.5';
            
            const formData = new FormData();
            formData.append('id', id);
            await fetch('tasks.php?action=toggle', { method: 'POST', body: formData });
            fetchTasks();
        }

        async function deleteTask(id) {
            if (!confirm('Deseja excluir permanentemente este lembrete?')) return;
            
            const card = document.getElementById('task-' + id);
            card.style.transform = 'translateX(20px)';
            card.style.opacity = '0';
            
            const formData = new FormData();
            formData.append('id', id);
            await fetch('tasks.php?action=delete', { method: 'POST', body: formData });
            
            setTimeout(fetchTasks, 200);
        }

        // Add task with Enter key
        document.getElementById('newTaskText').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') addTask();
        });

        fetchTasks();
    </script>
</body>
</html>
