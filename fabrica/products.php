<?php
// catalogo/fabrica/products.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// AJAX: AI Product Parse
if (isset($_POST['ajax_ai'])) {
    header('Content-Type: application/json');
    $text = $_POST['text'] ?? '';
    $res = [];
    
    require_once __DIR__ . '/../includes/ai_sdr.php';
    $ai = new AIService($pdo);
    if ($ai->isActive()) {
        $res = $ai->extractProductData($text);
    }
    
    // Fallback
    if (empty($res['name'])) {
        if (preg_match('/^(.+)$/m', $text, $m)) $res['name'] = trim($m[1]);
    }
    echo json_encode($res);
    exit;
}

// AJAX: Quick Image Upload (Drag & Drop / Paste)
if (isset($_POST['quick_id']) && isset($_FILES['quick_image'])) {
    header('Content-Type: application/json');
    $id = intval($_POST['quick_id']);
    $uploadDir = __DIR__ . '/../assets/uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $ext = pathinfo($_FILES['quick_image']['name'], PATHINFO_EXTENSION);
    if (!$ext) $ext = 'png'; // Fallback for pasted images
    $filename = "prod_" . time() . "_" . $id . "." . $ext;
    
    if (move_uploaded_file($_FILES['quick_image']['tmp_name'], $uploadDir . $filename)) {
        $path = 'assets/uploads/products/' . $filename;
        $pdo->prepare("UPDATE factory_products SET image_path = ? WHERE id = ?")->execute([$path, $id]);
        echo json_encode(['success' => true, 'path' => BASE_URL . '/' . $path]);
    } else {
        echo json_encode(['error' => 'Falha ao salvar imagem no servidor.']);
    }
    exit;
}

// Get editing item
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM factory_products WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Add / Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $raw_material = floatval($_POST['raw_material_cost'] ?? 0);
    $labor = floatval($_POST['labor_cost'] ?? 0);
    $machinery = floatval($_POST['machinery_cost'] ?? 0);
    $sale_price = floatval($_POST['sale_price'] ?? 0);
    $stock = intval($_POST['stock_qty'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    $weight = floatval($_POST['weight_kg'] ?? 0.100);
    $length = intval($_POST['length_cm'] ?? 20);
    $width = intval($_POST['width_cm'] ?? 15);
    $height = intval($_POST['height_cm'] ?? 10);

    $total_cost = $raw_material + $labor + $machinery;

    // Image Upload Logic
    $image_path = $_POST['existing_image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = "prod_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $image_path = 'assets/uploads/products/' . $filename;
            } else {
                $err = 'Erro ao mover arquivo de imagem para o diretório de uploads.';
            }
        } else {
            $err = 'Formato de imagem inválido. Formatos permitidos: JPG, PNG, WEBP e GIF.';
        }
    }

    if (empty($err)) {
        if (!empty($name)) {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE factory_products SET name = ?, sku = ?, raw_material_cost = ?, labor_cost = ?, machinery_cost = ?, total_cost = ?, sale_price = ?, stock_qty = ?, notes = ?, weight_kg = ?, length_cm = ?, width_cm = ?, height_cm = ?, image_path = ? WHERE id = ?");
                if ($stmt->execute([$name, $sku, $raw_material, $labor, $machinery, $total_cost, $sale_price, $stock, $notes, $weight, $length, $width, $height, $image_path, $id])) {
                    $msg = 'Produto atualizado com sucesso!';
                } else {
                    $err = 'Erro ao atualizar dados no banco.';
                }
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO factory_products (name, sku, raw_material_cost, labor_cost, machinery_cost, total_cost, sale_price, stock_qty, notes, weight_kg, length_cm, width_cm, height_cm, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $sku, $raw_material, $labor, $machinery, $total_cost, $sale_price, $stock, $notes, $weight, $length, $width, $height, $image_path])) {
                    $msg = 'Produto cadastrado com sucesso!';
                } else {
                    $err = 'Erro ao inserir novo produto.';
                }
            }
            
            // Refresh local editing item if editing
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM factory_products WHERE id = ?");
                $stmt->execute([$id]);
                $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } else {
            $err = 'O nome do produto é obrigatório.';
        }
    }
}

// Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_products WHERE id = ?");
    if ($stmt->execute([$id])) {
        $msg = 'Produto removido com sucesso!';
    } else {
        $err = 'Erro ao remover produto.';
    }
}

// Fetch all products
$products = $pdo->query("SELECT * FROM factory_products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    function runAI() {
        const txt = document.getElementById('ai_text').value;
        if(!txt) return alert('Cole a descrição/dados do produto primeiro!');
        const fd = new FormData();
        fd.append('ajax_ai', '1');
        fd.append('text', txt);
        
        document.getElementById('ai_btn').innerText = '✨ Extraindo...';
        fetch('products.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if(d.name) document.getElementById('f_name').value = d.name;
            if(d.sku) document.getElementById('f_sku').value = d.sku;
            if(d.price) document.getElementById('f_price').value = d.price;
            if(d.weight_kg) document.getElementById('f_weight').value = d.weight_kg;
            if(d.length_cm) document.getElementById('f_length').value = d.length_cm;
            if(d.width_cm) document.getElementById('f_width').value = d.width_cm;
            if(d.height_cm) document.getElementById('f_height').value = d.height_cm;
            document.getElementById('ai_btn').innerText = '✨ Extrair e Preencher';
            alert('🤖 Dados do produto extraídos com inteligência artificial!');
        })
        .catch(() => {
            document.getElementById('ai_btn').innerText = '✨ Extrair e Preencher';
            alert('Erro ao extrair dados do produto.');
        });
    }
</script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:10px;">
    <h2><i class="fas fa-tags" style="color:var(--primary);"></i> Cadastro de Produtos e Custos</h2>
    <?php if(!$edit_item): ?>
    <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Produto</a>
    <?php endif; ?>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($err); ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: <?php echo (isset($_GET['add']) || $edit_item) ? '1fr 380px' : '1fr'; ?>; gap:2rem;">
    
    <!-- List Column -->
    <div>
        <div class="card">
            <h3>Lista de Produtos</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>ID</th>
                            <th>Produto</th>
                            <th>SKU</th>
                            <th>Custos Unitários (R$)</th>
                            <th>Total Custo</th>
                            <th>Preço Venda</th>
                            <th>Estoque</th>
                            <th>Margem</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($products)): ?>
                            <tr><td colspan="10" style="text-align:center; color:var(--text-muted); padding:30px;">Nenhum produto cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach($products as $p): 
                                $margin = $p['sale_price'] > 0 ? (($p['sale_price'] - $p['total_cost']) / $p['sale_price']) * 100 : 0;
                                $margin_color = $margin > 30 ? 'var(--primary)' : ($margin > 10 ? 'var(--accent)' : 'var(--danger)');
                            ?>
                                <tr>
                                    <td class="quick-dropzone" data-id="<?php echo $p['id']; ?>" style="cursor:pointer; position:relative;" title="Clique para selecionar e aperte Ctrl+V para colar imagem, ou arraste uma imagem aqui">
                                        <?php if(!empty($p['image_path'])): ?>
                                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($p['image_path']); ?>" style="width:45px; height:45px; object-fit:cover; border-radius:5px; border:1px solid var(--border); pointer-events:none;" alt="Thumb">
                                        <?php else: ?>
                                            <div style="width:45px; height:45px; background:#080b10; border:1px solid var(--border); border-radius:5px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:0.8rem; pointer-events:none;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>#<?php echo $p['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                    <td><span style="font-family:monospace;"><?php echo htmlspecialchars($p['sku'] ?: '-'); ?></span></td>
                                    <td>
                                        <div style="font-size:0.75rem; color:var(--text-muted);">
                                            Matéria-prima: R$ <?php echo number_format($p['raw_material_cost'], 2, ',', '.'); ?><br>
                                            Mão de Obra: R$ <?php echo number_format($p['labor_cost'], 2, ',', '.'); ?><br>
                                            Maquinário: R$ <?php echo number_format($p['machinery_cost'], 2, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td style="font-weight:bold; color:#ff6b6b;">R$ <?php echo number_format($p['total_cost'], 2, ',', '.'); ?></td>
                                    <td style="font-weight:bold; color:var(--primary);">R$ <?php echo number_format($p['sale_price'], 2, ',', '.'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $p['stock_qty'] > 50 ? 'badge-success' : ($p['stock_qty'] > 10 ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo $p['stock_qty']; ?> un
                                        </span>
                                    </td>
                                    <td style="font-weight:bold; color:<?php echo $margin_color; ?>;"><?php echo number_format($margin, 1); ?>%</td>
                                    <td style="text-align:center;">
                                        <a href="?edit=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $p['id']; ?>" onclick="return confirm('Deseja realmente excluir este produto?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form Column -->
    <?php if(isset($_GET['add']) || $edit_item): ?>
    <div>
        <!-- AI Assistant Card -->
        <div class="card" style="border:1px dashed var(--accent); background:rgba(241,196,15,0.02); margin-bottom:1.5rem;">
            <strong style="color:var(--accent);"><i class="fas fa-robot"></i> Assistente IA (Gemini)</strong>
            <p style="font-size:0.75rem; color:var(--text-muted); margin:5px 0 10px;">Cole dados do produto para extrair o nome, SKU, valor sugerido e especificações.</p>
            <textarea id="ai_text" class="form-control" rows="2" style="font-size:0.8rem; background:#080b10; border:1px solid var(--border);" placeholder="Ex: Gabinete 2 Players, SKU: GAB-001, Peso: 15kg, 80x50x40cm"></textarea>
            <button type="button" id="ai_btn" onclick="runAI()" class="btn btn-secondary btn-sm" style="margin-top:10px; width:100%; font-size:0.75rem; background:var(--accent); color:#000;"><i class="fas fa-magic"></i> ✨ Extrair e Preencher</button>
        </div>

        <div class="card">
            <h3><?php echo $edit_item ? 'Editar Produto' : 'Novo Produto'; ?></h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">
                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_item['image_path'] ?? ''); ?>">

                <!-- Foto do Produto -->
                <div class="form-group">
                    <label>Foto do Produto</label>
                    <?php if(!empty($edit_item['image_path'])): ?>
                        <div style="margin-bottom:10px; position:relative; width:80px; height:80px;">
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($edit_item['image_path']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:8px; border:1px solid var(--border);" alt="Preview">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*" class="form-control" style="background:#080b10; border:1px solid var(--border); padding:6px;">
                </div>

                <div class="form-group">
                    <label>Nome do Produto</label>
                    <input type="text" name="name" id="f_name" class="form-control" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="Ex: Fliperama de Metal 2 Players">
                </div>

                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" id="f_sku" class="form-control" value="<?php echo htmlspecialchars($edit_item['sku'] ?? ''); ?>" placeholder="Ex: FLIP-MET-2P">
                </div>

                <hr style="border-color:var(--border); margin:1.5rem 0;">
                <h4 style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem;">Dimensões de Frete (B2B)</h4>

                <div class="form-group">
                    <label>Peso (kg)</label>
                    <input type="number" step="0.001" name="weight_kg" id="f_weight" class="form-control" value="<?php echo $edit_item['weight_kg'] ?? '0.100'; ?>">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Comp. (cm)</label>
                        <input type="number" name="length_cm" id="f_length" class="form-control" value="<?php echo $edit_item['length_cm'] ?? '20'; ?>">
                    </div>
                    <div class="form-group">
                        <label>Largura (cm)</label>
                        <input type="number" name="width_cm" id="f_width" class="form-control" value="<?php echo $edit_item['width_cm'] ?? '15'; ?>">
                    </div>
                    <div class="form-group">
                        <label>Altura (cm)</label>
                        <input type="number" name="height_cm" id="f_height" class="form-control" value="<?php echo $edit_item['height_cm'] ?? '10'; ?>">
                    </div>
                </div>

                <hr style="border-color:var(--border); margin:1.5rem 0;">
                <h4 style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem;">Formação de Preço</h4>

                <div class="form-group">
                    <label>Custo Matéria-Prima (R$)</label>
                    <input type="number" step="0.01" name="raw_material_cost" id="raw_cost" class="form-control" value="<?php echo $edit_item['raw_material_cost'] ?? '0.00'; ?>" oninput="calcTotalCost()">
                </div>

                <div class="form-group">
                    <label>Custo Mão de Obra (R$)</label>
                    <input type="number" step="0.01" name="labor_cost" id="labor_cost" class="form-control" value="<?php echo $edit_item['labor_cost'] ?? '0.00'; ?>" oninput="calcTotalCost()">
                </div>

                <div class="form-group">
                    <label>Custo Maquinário/Depreciação (R$)</label>
                    <input type="number" step="0.01" name="machinery_cost" id="mach_cost" class="form-control" value="<?php echo $edit_item['machinery_cost'] ?? '0.00'; ?>" oninput="calcTotalCost()">
                </div>

                <div class="form-group">
                    <label>CUSTO TOTAL CALCULADO</label>
                    <input type="text" id="total_cost_display" class="form-control" style="background:#080b10; font-weight:bold; color:#ff6b6b; border:1px solid var(--border);" readonly value="R$ 0,00">
                </div>

                <div class="form-group">
                    <label>Preço Sugerido de Venda B2B (R$)</label>
                    <input type="number" step="0.01" name="sale_price" id="f_price" class="form-control" value="<?php echo $edit_item['sale_price'] ?? '0.00'; ?>">
                </div>

                <div class="form-group">
                    <label>Quantidade em Estoque</label>
                    <input type="number" name="stock_qty" class="form-control" value="<?php echo $edit_item['stock_qty'] ?? '0'; ?>">
                </div>

                <div class="form-group">
                    <label>Notas / Observações</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Insira qualquer nota de fabricação..."><?php echo htmlspecialchars($edit_item['notes'] ?? ''); ?></textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Salvar</button>
                    <a href="products.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function calcTotalCost() {
    const raw = parseFloat(document.getElementById('raw_cost').value) || 0;
    const labor = parseFloat(document.getElementById('labor_cost').value) || 0;
    const mach = parseFloat(document.getElementById('mach_cost').value) || 0;
    const total = raw + labor + mach;
    document.getElementById('total_cost_display').value = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
}
document.addEventListener('DOMContentLoaded', calcTotalCost);

// Quick Image Upload Logic (Drag & Drop / Paste)
let activeRowId = null;

document.querySelectorAll('.quick-dropzone').forEach(cell => {
    cell.addEventListener('click', (e) => {
        document.querySelectorAll('.quick-dropzone').forEach(c => c.style.borderColor = 'transparent');
        cell.style.border = '2px dashed var(--primary)';
        activeRowId = cell.getAttribute('data-id');
    });

    cell.addEventListener('dragover', (e) => {
        e.preventDefault();
        cell.style.opacity = '0.5';
    });
    cell.addEventListener('dragleave', (e) => {
        e.preventDefault();
        cell.style.opacity = '1';
    });
    cell.addEventListener('drop', (e) => {
        e.preventDefault();
        cell.style.opacity = '1';
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            uploadQuickImage(cell.getAttribute('data-id'), e.dataTransfer.files[0], cell);
        }
    });
});

window.addEventListener('paste', (e) => {
    if (!activeRowId) return;
    if (e.clipboardData.files && e.clipboardData.files[0]) {
        const cell = document.querySelector(`.quick-dropzone[data-id="${activeRowId}"]`);
        if(cell) uploadQuickImage(activeRowId, e.clipboardData.files[0], cell);
    }
});

function uploadQuickImage(id, file, cell) {
    if (!file.type.startsWith('image/')) return alert('Por favor, cole ou arraste uma imagem válida.');
    const originalHtml = cell.innerHTML;
    cell.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--primary); font-size:1.5rem;"></i>';
    
    const fd = new FormData();
    fd.append('quick_id', id);
    fd.append('quick_image', file);
    
    fetch('products.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            cell.innerHTML = `<img src="${d.path}" style="width:45px; height:45px; object-fit:cover; border-radius:5px; border:1px solid var(--border); pointer-events:none;" alt="Thumb">`;
            cell.style.border = 'none';
        } else {
            alert(d.error || 'Erro ao fazer upload da imagem.');
            cell.innerHTML = originalHtml;
        }
    }).catch(err => {
        alert('Erro de rede: ' + err.message);
        cell.innerHTML = originalHtml;
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
