<?php
// catalogo/fabrica/defects.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// Garante diretório de uploads de defeitos
$uploadDir = __DIR__ . '/../assets/uploads/defects/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 1. AÇÃO: Resolver Defeito
if (isset($_GET['resolve'])) {
    $id = intval($_GET['resolve']);
    $stmt = $pdo->prepare("UPDATE factory_defects SET status = 'resolved', resolved_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt->execute([$id])) {
        $msg = 'Ticket de defeito marcado como resolvido!';
    } else {
        $err = 'Erro ao resolver ticket.';
    }
}

// 2. AÇÃO: Excluir Ticket
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_defects WHERE id = ?");
    if ($stmt->execute([$id])) {
        $msg = 'Ticket excluído com sucesso.';
    }
}

// 3. AÇÃO: Salvar Novo Defeito Manual (Celular/Upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_defect') {
    $op_id = !empty($_POST['production_order_id']) ? intval($_POST['production_order_id']) : null;
    $product_id = !empty($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $description = trim($_POST['description'] ?? '');
    
    // Upload da Imagem
    $file_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array(strtolower($ext), $allowed)) {
            $filename = "defect_manual_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $file_path = 'assets/uploads/defects/' . $filename;
            } else {
                $err = 'Falha ao salvar imagem de upload.';
            }
        } else {
            $err = 'Formato de arquivo inválido. Use JPG, PNG, WEBP ou GIF.';
        }
    } else {
        $err = 'A imagem do defeito é obrigatória para registro.';
    }
    
    if (empty($err)) {
        if (!empty($description)) {
            $stmt = $pdo->prepare("INSERT INTO factory_defects (production_order_id, product_id, file_path, description, sender_phone, status) VALUES (?, ?, ?, ?, 'Painel Web', 'pending')");
            if ($stmt->execute([$op_id, $product_id, $file_path, $description])) {
                $msg = 'Defeito registrado com sucesso!';
                
                // Dispara notificação automática sobre o defeito
                require_once __DIR__ . '/../includes/notifications.php';
                $notif = new NotificationService($pdo);
                $cfg = $notif->getConfig();
                
                // Tenta notificar o telefone da OP se houver
                if ($op_id) {
                    $op_phone = $pdo->query("SELECT notification_phone FROM factory_production_orders WHERE id = $op_id")->fetchColumn();
                    if ($op_phone) {
                        $pName = $pdo->query("SELECT name FROM factory_products WHERE id = $product_id")->fetchColumn();
                        $alertMsg = "⚠️ *ALERTA DE DEFEITO REGISTRADO NO PAINEL*\n\nOP: *#$op_id*\nProduto: $pName\nProblema: $description\n\nPor favor, verifique a fila de produção.";
                        $notif->send($op_phone, $alertMsg);
                    }
                }
            } else {
                $err = 'Erro ao salvar no banco de dados.';
            }
        } else {
            $err = 'A descrição do defeito é obrigatória.';
        }
    }
}

// 4. AÇÃO: Enviar Notificação WhatsApp Manual
if (isset($_GET['notify_id'])) {
    $id = intval($_GET['notify_id']);
    $phone = trim($_GET['phone'] ?? '');
    
    if (!empty($phone)) {
        $defect = $pdo->query("SELECT d.*, p.name as product_name FROM factory_defects d LEFT JOIN factory_products p ON d.product_id = p.id WHERE d.id = $id")->fetch(PDO::FETCH_ASSOC);
        if ($defect) {
            require_once __DIR__ . '/../includes/notifications.php';
            $notif = new NotificationService($pdo);
            $domain = 'https://www.fightarcade.com.br/catalogo/';
            $alertMsg = "🚨 *DEFEITO REGISTRADO (ATENÇÃO)*\n\n"
                      . "ID do Ticket: *#{$defect['id']}*\n"
                      . "Produto: " . ($defect['product_name'] ?: 'Não especificado') . "\n"
                      . "Relato: {$defect['description']}\n"
                      . "Visualizar foto: " . $domain . $defect['file_path'] . "\n\n"
                      . "Favor verificar e responder esta pendência na Fábrica.";
            if ($notif->send($phone, $alertMsg)) {
                $msg = 'Notificação enviada com sucesso!';
            } else {
                $err = 'Erro ao enviar mensagem pelo WhatsApp.';
            }
        }
    } else {
        $err = 'Telefone para envio não fornecido.';
    }
}

// Buscar todos os relatos de defeito
$defects = $pdo->query("
    SELECT d.*, p.name as product_name, po.status as op_status 
    FROM factory_defects d
    LEFT JOIN factory_products p ON d.product_id = p.id
    LEFT JOIN factory_production_orders po ON d.production_order_id = po.id
    ORDER BY d.status ASC, d.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Buscar OPs ativas e Produtos para os formulários
$active_ops = $pdo->query("SELECT po.id, p.name FROM factory_production_orders po JOIN factory_products p ON po.product_id = p.id WHERE po.status != 'completed' ORDER BY po.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT id, name FROM factory_products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:10px;">
    <h2><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> Controle de Defeitos e Problemas</h2>
    <a href="#reportForm" class="btn btn-danger"><i class="fas fa-camera"></i> Reportar Novo Defeito</a>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($err); ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr; gap:2rem;">

    <!-- Form de Envio Manual (Mobile friendly) -->
    <div class="card" id="reportForm" style="border:1px solid rgba(231,76,60,0.3); background:#171111;">
        <h3 style="color:#ff6b6b;"><i class="fas fa-plus-circle"></i> Registrar Novo Defeito / Problema</h3>
        <p style="font-size:0.85rem; color:var(--text-muted);">Tire uma foto do item danificado e preencha as informações para registrar na fila da fábrica.</p>
        
        <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem; display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
            <input type="hidden" name="action" value="save_defect">
            
            <div>
                <div class="form-group">
                    <label>Imagem do Defeito</label>
                    <div id="drop_zone" style="border: 2px dashed #ff6b6b; border-radius: 8px; padding: 25px; text-align: center; background: #0c0808; cursor: pointer; transition: all 0.3s; position: relative;">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: #ff6b6b; margin-bottom: 10px; display: block;"></i>
                        <span id="drop_zone_text" style="color: var(--text-muted); font-size: 0.85rem; display: block; line-height: 1.4;">
                            <strong>Arraste e solte</strong> a imagem aqui, <strong style="color: var(--primary);">cole (Ctrl+V)</strong> ou clique para selecionar.
                        </span>
                        <input type="file" name="image" id="file_input" accept="image/*" capture="environment" required style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                    </div>
                    <div id="preview_container" style="display: none; margin-top: 15px; text-align: center; position: relative; background: #131924; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                        <img id="image_preview" src="" style="max-height: 150px; max-width: 100%; object-fit: contain; border-radius: 5px; display: inline-block;">
                        <button type="button" id="remove_preview_btn" class="btn btn-sm btn-danger" style="display: block; margin: 8px auto 0; padding: 4px 10px; font-size: 0.75rem;"><i class="fas fa-trash-alt"></i> Remover Imagem</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Ordem de Produção Relacionada (Opcional)</label>
                    <select name="production_order_id" class="form-control">
                        <option value="">Nenhuma...</option>
                        <?php foreach($active_ops as $op): ?>
                            <option value="<?php echo $op['id']; ?>">OP #<?php echo $op['id']; ?> - <?php echo htmlspecialchars($op['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Produto com Defeito (Opcional)</label>
                    <select name="product_id" class="form-control">
                        <option value="">Selecione o Produto...</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <div class="form-group">
                    <label>Descrição do Problema / Observações</label>
                    <textarea name="description" class="form-control" rows="6" placeholder="Escreva aqui detalhes sobre o erro, a peça quebrada ou o maquinário que falhou..." required></textarea>
                </div>
                
                <div style="margin-top:1.5rem;">
                    <button type="submit" class="btn btn-danger" style="width:100%; font-size:1rem; padding:12px;"><i class="fas fa-paper-plane"></i> Registrar e Alertar Equipe</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista de Defeitos -->
    <div class="card">
        <h3>Tickets de Defeitos Registrados</h3>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:1.5rem; margin-top:1.5rem;">
            <?php if(empty($defects)): ?>
                <div style="grid-column: 1 / -1; text-align:center; padding:3rem; color:var(--text-muted);">
                    <i class="fas fa-check-circle" style="font-size:3rem; color:var(--primary); margin-bottom:1rem;"></i>
                    <p>Nenhum problema ou defeito relatado no momento. Tudo operando perfeitamente!</p>
                </div>
            <?php else: ?>
                <?php foreach($defects as $d): 
                    $status_color = $d['status'] === 'resolved' ? '#2ecc71' : '#ff6b6b';
                    $status_label = $d['status'] === 'resolved' ? 'Resolvido' : 'Pendente';
                ?>
                    <div style="background:#131924; border:1px solid #252d3d; border-radius:8px; overflow:hidden; display:flex; flex-direction:column;">
                        
                        <!-- Imagem do Defeito -->
                        <div style="position:relative; height:200px; background:#000; display:flex; align-items:center; justify-content:center; border-bottom:1px solid #252d3d;">
                            <?php if(!empty($d['file_path'])): ?>
                                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($d['file_path']); ?>" style="max-height:100%; max-width:100%; object-fit:contain;" alt="Defeito">
                            <?php else: ?>
                                <div style="color:var(--text-muted); font-size:0.85rem;"><i class="fas fa-image" style="font-size:2rem; display:block; margin-bottom:5px; text-align:center;"></i> Sem Imagem</div>
                            <?php endif; ?>
                            
                            <!-- Badge Status -->
                            <span style="position:absolute; top:10px; right:10px; background:<?php echo $status_color; ?>; color:#000; font-size:0.7rem; font-weight:bold; padding:4px 8px; border-radius:20px; text-transform:uppercase;">
                                <?php echo $status_label; ?>
                            </span>
                        </div>

                        <!-- Detalhes do Defeito -->
                        <div style="padding:1.25rem; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; color:var(--text-muted); margin-bottom:5px;">
                                    <span>Ticket #<?php echo $d['id']; ?></span>
                                    <span><?php echo date('d/m/Y H:i', strtotime($d['created_at'])); ?></span>
                                </div>
                                
                                <div style="font-size:0.8rem; margin-bottom:8px;">
                                    <strong>Origem:</strong> <span style="font-family:monospace;"><?php echo htmlspecialchars($d['sender_phone'] ?: 'N/A'); ?></span>
                                </div>

                                <div style="margin-bottom:10px;">
                                    <?php if($d['production_order_id']): ?>
                                        <span class="badge badge-info" style="font-size:0.7rem; margin-right:5px;">OP #<?php echo $d['production_order_id']; ?></span>
                                    <?php endif; ?>
                                    <?php if($d['product_name']): ?>
                                        <span class="badge badge-warning" style="font-size:0.7rem;"><?php echo htmlspecialchars($d['product_name']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <p style="font-size:0.9rem; color:#e0e0e0; line-height:1.4; margin-bottom:1.5rem; background:#1b2230; padding:10px; border-radius:5px; border-left:3px solid <?php echo $status_color; ?>;">
                                    <?php echo nl2br(htmlspecialchars($d['description'])); ?>
                                </p>
                            </div>

                            <div>
                                <?php if($d['status'] === 'pending'): ?>
                                    <!-- Ações do Ticket Pendente -->
                                    <div style="display:grid; grid-template-columns: 1fr; gap:8px;">
                                        <a href="?resolve=<?php echo $d['id']; ?>" class="btn btn-success btn-sm" style="text-align:center; padding:8px;"><i class="fas fa-check"></i> Marcar Resolvido</a>
                                        
                                        <!-- Form para notificar via WhatsApp -->
                                        <form method="GET" style="display:flex; gap:5px; margin-top:5px;">
                                            <input type="hidden" name="notify_id" value="<?php echo $d['id']; ?>">
                                            <input type="text" name="phone" placeholder="WhatsApp para notificar..." class="form-control" style="font-size:0.75rem; padding:6px; flex:1;" value="<?php echo htmlspecialchars($d['sender_phone'] ?: ''); ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding:0 10px;"><i class="fab fa-whatsapp"></i></button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:0.75rem; color:var(--text-muted); background:rgba(46,204,113,0.1); padding:8px; border-radius:5px; text-align:center;">
                                        <i class="fas fa-check-double"></i> Resolvido em: <?php echo date('d/m/Y H:i', strtotime($d['resolved_at'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="text-align:right; margin-top:10px;">
                                    <a href="?delete=<?php echo $d['id']; ?>" onclick="return confirm('Deseja realmente excluir este relato?')" style="color:#ff6b6b; font-size:0.75rem; text-decoration:none;"><i class="fas fa-trash"></i> Excluir</a>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop_zone');
    const fileInput = document.getElementById('file_input');
    const dropZoneText = document.getElementById('drop_zone_text');
    const previewContainer = document.getElementById('preview_container');
    const imagePreview = document.getElementById('image_preview');
    const removeBtn = document.getElementById('remove_preview_btn');

    // Highlight drop zone on dragover
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.background = '#1a0d0d';
            dropZone.style.borderColor = 'var(--primary)';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (eventName === 'dragleave') {
                dropZone.style.background = '#0c0808';
                dropZone.style.borderColor = '#ff6b6b';
            }
        }, false);
    });

    // Handle dropped files
    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            handleFiles(files);
        }
    }, false);

    // Handle selected file via input click
    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files.length > 0) {
            handleFiles(this.files);
        }
    });

    // Handle clipboard paste (Ctrl+V)
    window.addEventListener('paste', function(e) {
        // Only run paste if the active element is not a textarea/input to prevent interrupting text typing
        const active = document.activeElement;
        if (active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type !== 'file'))) {
            // Let normal typing happen
            return;
        }

        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (let index in items) {
            const item = items[index];
            if (item.kind === 'file' && item.type.indexOf('image/') !== -1) {
                const blob = item.getAsFile();
                const file = new File([blob], "pasted_image_" + Date.now() + ".png", { type: item.type });
                
                const container = new DataTransfer();
                container.items.add(file);
                fileInput.files = container.files;
                
                handleFiles(fileInput.files);
            }
        }
    });

    function handleFiles(files) {
        const file = files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onloadend = function() {
                imagePreview.src = reader.result;
                previewContainer.style.display = 'block';
                dropZone.style.display = 'none';
            }
        }
    }

    removeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.value = '';
        imagePreview.src = '';
        previewContainer.style.display = 'none';
        dropZone.style.display = 'block';
        dropZone.style.background = '#0c0808';
        dropZone.style.borderColor = '#ff6b6b';
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
