<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// 1. DELETE SINGLE
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->query("DELETE FROM products WHERE id = $id");
    header("Location: products.php?msg=deleted");
    exit;
}

// Handle Status Toggle (Geral)
if (isset($_GET['toggle_status'])) {
    $id = (int) $_GET['toggle_status'];
    $curr = $pdo->query("SELECT active FROM products WHERE id = $id")->fetchColumn();
    $new = $curr ? 0 : 1;
    $pdo->query("UPDATE products SET active = $new WHERE id = $id");
    header("Location: products.php");
    exit;
}

// Handle Site Visibility Toggle
if (isset($_GET['toggle_site'])) {
    $id = (int) $_GET['toggle_site'];
    $curr = $pdo->query("SELECT show_on_site FROM products WHERE id = $id")->fetchColumn();
    $new = $curr ? 0 : 1;
    $pdo->query("UPDATE products SET show_on_site = $new WHERE id = $id");
    header("Location: products.php");
    exit;
}

// Handle Export Visibility Toggle
if (isset($_GET['toggle_export'])) {
    $id = (int) $_GET['toggle_export'];
    $curr = $pdo->query("SELECT allow_export FROM products WHERE id = $id")->fetchColumn();
    $new = $curr ? 0 : 1;
    $pdo->query("UPDATE products SET allow_export = $new WHERE id = $id");
    header("Location: products.php");
    exit;
}

// 2. BULK ACTIONS
if (!empty($_POST['selected_ids'])) {
    $ids = implode(',', array_map('intval', $_POST['selected_ids']));

    if (isset($_POST['bulk_delete'])) {
        $pdo->query("DELETE FROM products WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_deleted");
        exit;
    }
    if (isset($_POST['bulk_site_show'])) {
        $pdo->query("UPDATE products SET show_on_site = 1 WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_updated");
        exit;
    }
    if (isset($_POST['bulk_site_hide'])) {
        $pdo->query("UPDATE products SET show_on_site = 0 WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_updated");
        exit;
    }
    if (isset($_POST['bulk_export_allow'])) {
        $pdo->query("UPDATE products SET allow_export = 1 WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_updated");
        exit;
    }
    if (isset($_POST['bulk_export_deny'])) {
        $pdo->query("UPDATE products SET allow_export = 0 WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_updated");
        exit;
    }
}

// 3. CLONE PRODUCT
if (isset($_GET['clone'])) {
    $clone_id = (int) $_GET['clone'];
    $src = $pdo->query("SELECT * FROM products WHERE id = $clone_id")->fetch(PDO::FETCH_ASSOC);

    if ($src) {
        unset($src['id']);
        $src['name'] = $src['name'] . ' (Cópia)';
        $src['sku'] = $src['sku'] ? $src['sku'] . '-COPY' : '';
        if (isset($src['created_at'])) $src['created_at'] = date('Y-m-d H:i:s');
        if (isset($src['slug'])) $src['slug'] = $src['slug'] . '-copia-' . time();

        $cols = array_keys($src);
        $colStr = implode(', ', array_map(fn($c) => "`$c`", $cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));

        try {
            $stmt = $pdo->prepare("INSERT INTO products ($colStr) VALUES ($placeholders)");
            $stmt->execute(array_values($src));
            $newId = $pdo->lastInsertId();

            // Clone Variations
            $vars = $pdo->query("SELECT * FROM product_variations WHERE product_id = $clone_id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($vars as $v) {
                unset($v['id']);
                $v['product_id'] = $newId;
                $vCols = array_keys($v);
                $vColStr = implode(', ', array_map(fn($c) => "`$c`", $vCols));
                $vPlaceholders = implode(', ', array_fill(0, count($vCols), '?'));
                $pdo->prepare("INSERT INTO product_variations ($vColStr) VALUES ($vPlaceholders)")->execute(array_values($v));
            }

            // Clone Gallery Images
            try {
                $imgs = $pdo->query("SELECT * FROM product_images WHERE product_id = $clone_id")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($imgs as $img) {
                    unset($img['id']);
                    $img['product_id'] = $newId;
                    $iCols = array_keys($img);
                    $iColStr = implode(', ', array_map(fn($c) => "`$c`", $iCols));
                    $iPlaceholders = implode(', ', array_fill(0, count($iCols), '?'));
                    $pdo->prepare("INSERT INTO product_images ($iColStr) VALUES ($iPlaceholders)")->execute(array_values($img));
                }
            } catch (Exception $e) { /* product_images table may not exist */ }

            header("Location: products.php?msg=cloned");
            exit;
        } catch (Exception $e) {
            die("<div style='background:#e74c3c;color:#fff;padding:20px;margin:20px;border-radius:8px;font-family:sans-serif'><h3>Erro ao clonar produto:</h3><p>" . $e->getMessage() . "</p><a href='products.php' style='color:#fff'>← Voltar</a></div>");
        }
    }
}

// 4. FILTERS
$where = ["1=1"];
$params = [];

if (!empty($_GET['cat'])) {
    $where[] = "p.category_id = ?";
    $params[] = (int) $_GET['cat'];
}

if (!empty($_GET['q'])) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%" . $_GET['q'] . "%";
    $params[] = "%" . $_GET['q'] . "%";
}

$whereSql = implode(" AND ", $where);
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $whereSql 
        ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Produtos | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByName('selected_ids[]');
            for (var i = 0, n = checkboxes.length; i < n; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function submitExport(format) {
            const form = document.getElementById('bulkForm');
            // Check if any checkbox is selected
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');

            // Allow export all if none selected? Or require selection?
            // User requested "select and throw to bot", but usually bulk actions require selection.
            // Let's allow "Export ALL" if none selected by confirming logic.

            let url = 'export_bot.php?format=' + format;

            if (checkboxes.length === 0) {
                if (confirm('Nenhum produto selecionado. Deseja exportar TODOS os produtos ativos?')) {
                    // Export all via GET
                    window.open(url, '_blank');
                    return;
                } else {
                    return;
                }
            }

            // If selected, we must use POST to send IDs, OR construct a long GET string.
            // Since we are inside a form, we can just change action and submit.
            // But we need to handle "new tab" for PDF/TXT.

            const originalAction = form.action;
            const originalTarget = form.target;

            form.action = url;
            form.target = '_blank'; // Open in new tab for download/view

            // We need to append selected_ids to the form submission, which they already are.
            // But we need to ensure the validation passes.

            form.submit();

            // Restore
            setTimeout(() => {
                form.action = originalAction;
                form.target = originalTarget;
            }, 500);
        }

        function submitTikTokExport() {
            const form = document.getElementById('bulkForm');
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checkboxes.length === 0) {
                if (!confirm('Nenhum produto selecionado. Deseja exportar TODOS os produtos ativos para o TikTok?')) return;
            }
            form.action = 'export_tiktok.php';
            form.target = '_blank';
            form.submit();
        }
    </script>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">

        <!-- MESSAGES -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'cloned'): ?>
                <div class="alert alert-success">✅ Produto clonado com sucesso!</div>
            <?php elseif ($_GET['msg'] == 'bulk_deleted'): ?>
                <div class="alert alert-success">🗑️ Produtos selecionados foram excluídos.</div>
            <?php elseif ($_GET['msg'] == 'bulk_updated'): ?>
                <div class="alert alert-success">✅ Produtos selecionados foram atualizados.</div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- FILTERS (Outside Main Form to avoid Delete Confirm collision) -->
        <form method="GET"
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:10px; background:#111; padding:10px; border-radius:8px; border:1px solid #333;">
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="text" name="q" placeholder="Buscar Nome/SKU..."
                    value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                    style="background:#000; border:1px solid #444; color:white; padding:5px 10px; border-radius:4px; outline:none;">
                <select name="cat" onchange="this.form.submit()"
                    style="background:#000; color:white; border:1px solid #444; border-radius:4px; padding:5px 10px;">
                    <option value="">Todas Categorias</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-sm"
                    style="background:var(--primary); color:#000; padding:5px 15px; border-radius:4px; border:none; cursor:pointer; font-weight:bold;">Filtrar</button>
            </div>
            <a href="product-edit.php" class="btn">Novo Produto</a>
        </form>

        <form method="POST" id="bulkForm">

            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <!-- BULK BUTTONS -->
                    <span style="color:#888; font-size:0.9rem;">Ações em Massa:</span>

                    <button type="submit" name="bulk_site_show" class="btn-sm" style="background:#3498db; color:white;"
                        title="Exibir no Site">🌐 Exibir</button>
                    <button type="submit" name="bulk_site_hide" class="btn-sm" style="background:#555; color:white;"
                        title="Ocultar no Site">🌑 Ocultar</button>

                    <button type="submit" name="bulk_export_allow" class="btn-sm"
                        style="background:#f39c12; color:white;" title="Permitir Exportação">📤 Permitir</button>
                    <button type="submit" name="bulk_export_deny" class="btn-sm" style="background:#555; color:white;"
                        title="Bloquear Exportação">📥 Bloquear</button>

                    <div style="border-left:1px solid #444; height:25px; margin:0 10px;"></div>

                    <!-- AI EXPORT -->
                    <button type="button" onclick="submitExport('txt')" class="btn-sm"
                        style="background:#2c3e50; color:white;" title="Exportar TXT para Bot">🤖 TXT</button>
                    <button type="button" onclick="submitExport('csv')" class="btn-sm"
                        style="background:#2c3e50; color:white;" title="Exportar Excel para Bot">🤖 CSV</button>
                    <button type="button" onclick="submitExport('pdf')" class="btn-sm"
                        style="background:#2c3e50; color:white;" title="Exportar PDF para Bot">🤖 PDF</button>

                    <div style="border-left:1px solid #444; height:25px; margin:0 10px;"></div>

                    <!-- TIKTOK EXPORT -->
                    <button type="button" onclick="submitTikTokExport()" class="btn-sm"
                        style="background:#000; color:white;" title="Exportar para TikTok Shop">🎵 TikTok</button>


                    <button type="submit" name="bulk_delete" class="btn-sm btn-danger"
                        onclick="return confirm('Excluir selecionados?')">🗑️ Excluir</button>

                    <div style="border-left:1px solid #444; height:25px; margin:0 10px;"></div>

                    <button type="button" class="btn-sm" onclick="openAIModal('price')"
                        style="background:#8e44ad; color:white;">💰 IA: Preços</button>
                    <button type="button" class="btn-sm" onclick="openWholesaleModal()"
                        style="background:#e67e22; color:white;">🏪 IA: Atacado</button>
                    <button type="button" class="btn-sm" onclick="openAIModal('info')"
                        style="background:#27ae60; color:white;">📦 IA: Cadastro</button>

                    <button type="button" onclick="goToMarketplaces()" class="btn-sm"
                        style="background:var(--accent); color:white;" title="Marketplaces">🚀
                        Integrações</button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="productsTable">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th>IMG</th>
                            <th>Nome</th>
                            <th>SKU</th>
                            <th style="text-align:center;">Geral</th>
                            <th style="text-align:center;">Site</th>
                            <th style="text-align:center;">Export</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                data-price="<?php echo $p['price']; ?>"
                                data-price-wholesale="<?php echo $p['price_wholesale']; ?>">
                                <td><input type="checkbox" name="selected_ids[]" value="<?php echo $p['id']; ?>"></td>
                                <td>
                                    <?php if ($p['image_path']): ?>
                                        <img src="../assets/uploads/<?php echo $p['image_path']; ?>"
                                            style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                                    <?php else: ?>
                                        <div style="width:50px; height:50px; background:#333; border-radius:4px;"></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                    <?php if (!empty($p['is_vip'])): ?>
                                        <span
                                            style="background:gold; color:black; padding:2px 4px; border-radius:4px; font-size:0.6rem; font-weight:bold;">👑</span>
                                    <?php endif; ?>
                                    <br>
                                    <a href="../product.php?id=<?php echo $p['id']; ?>" target="_blank"
                                        style="font-size:0.8rem; color:#4cc9f0; text-decoration:none;">👁️ Ver</a>
                                </td>
                                <td><small><?php echo htmlspecialchars($p['sku']); ?></small></td>

                                <td style="text-align:center;">
                                    <a href="?toggle_status=<?php echo $p['id']; ?>" style="text-decoration:none;">
                                        <?php if ($p['active']): ?>
                                            <span style="color:#2ecc71; font-size:1.1rem;" title="Ativo">🟢</span>
                                        <?php else: ?>
                                            <span style="color:#e74c3c; font-size:1.1rem;" title="Inativo">🔴</span>
                                        <?php endif; ?>
                                    </a>
                                </td>

                                <td style="text-align:center;">
                                    <a href="?toggle_site=<?php echo $p['id']; ?>" style="text-decoration:none;">
                                        <?php if ($p['show_on_site'] ?? 1): ?>
                                            <span style="color:#3498db; font-size:1.1rem;" title="No Site">🌐</span>
                                        <?php else: ?>
                                            <span style="color:#555; font-size:1.1rem;" title="Oculto Site">🌑</span>
                                        <?php endif; ?>
                                    </a>
                                </td>

                                <td style="text-align:center;">
                                    <a href="?toggle_export=<?php echo $p['id']; ?>" style="text-decoration:none;">
                                        <?php if ($p['allow_export'] ?? 1): ?>
                                            <span style="color:#f39c12; font-size:1.1rem;" title="Exportar Sim">📤</span>
                                        <?php else: ?>
                                            <span style="color:#555; font-size:1.1rem;" title="Exportar Não">📥</span>
                                        <?php endif; ?>
                                    </a>
                                </td>

                                <td><small><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></small></td>
                                <td>R$ <?php echo number_format($p['price'], 2, ',', '.'); ?></td>
                                <td>
                                    <div style="display:flex; gap:5px; align-items:center;">
                                        <!-- CLONE BTN -->
                                        <a href="?clone=<?php echo $p['id']; ?>" class="btn-sm"
                                            style="background:var(--warning); color:#000; text-decoration:none; padding:5px 10px; border-radius:4px; font-weight:bold;"
                                            title="Clonar Produto">📑</a>

                                        <a href="product-edit.php?id=<?php echo $p['id']; ?>" class="btn-sm"
                                            style="background:#3498db; color:#fff; text-decoration:none; padding:5px 10px; border-radius:4px;"
                                            title="Editar">✏️</a>

                                        <a href="?delete=<?php echo $p['id']; ?>" class="btn-sm"
                                            style="background:var(--danger); color:#fff; text-decoration:none; padding:5px 10px; border-radius:4px;"
                                            onclick="return confirm('Tem certeza?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- AI BULK MODAL (Unified) -->
    <div id="aiBulkModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
        <div
            style="background:var(--bg-card); padding:2rem; width:90%; max-width:600px; border-radius:12px; position:relative;">
            <button onclick="document.getElementById('aiBulkModal').style.display='none'"
                style="position:absolute; top:10px; right:10px; background:transparent; border:none; color:white; font-size:1.5rem; cursor:pointer;">&times;</button>

            <h2 id="aiModalTitle" style="color:var(--accent); margin-bottom:1rem;">🤖 IA: Processamento em Massa</h2>

            <div style="margin-bottom:1rem;" id="instructionBox">
                <label style="display:block; margin-bottom:0.5rem; color:#fff;">Instrução para a IA:</label>
                <input type="text" id="aiInstruction"
                    style="width:100%; padding:10px; background:#111; color:#fff; border:1px solid #444; border-radius:4px;"
                    placeholder="Ex: Aumentar 10% | Diminuir 5% | Arredondar para .90">
                <small style="color:#aaa;">Digite a regra de preço que você quer aplicar.</small>
            </div>

            <div style="margin-bottom:1rem;">
                <p>1. <button type="button" class="btn" onclick="copyBulkPrompt()" style="background:#555;">📋 Copiar
                        Prompt</button> (Cola no Gemini)</p>
                <small style="color:#aaa;">O prompt será gerado com sua instrução.</small>
            </div>

            <div style="margin-bottom:1rem;">
                <p>2. Cole o JSON gerado pelo Gemini abaixo:</p>
                <textarea id="jsonBulkInput"
                    style="width:100%; height:200px; background:#111; color:#0f0; border:1px solid #444; padding:10px; font-family:monospace;"
                    placeholder='Ex: [{"id":10, "ncm": "95045000", "ean": "789..."}, ...]'></textarea>
            </div>

            <div style="text-align:right;">
                <span id="bulkStatus" style="margin-right:10px; color:#aaa;"></span>
                <button type="button" class="btn" onclick="applyBulkUpdate()" style="background:#2ecc71;">🚀 Aplicar
                    Alterações</button>
            </div>
        </div>
    </div>

    <!-- MODAL ATACADO AUTOMÁTICO -->
    <div id="wholesaleModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:var(--bg-card); padding:2rem; width:90%; max-width:500px; border-radius:12px; position:relative; border:1px solid #e67e22;">
            <button onclick="document.getElementById('wholesaleModal').style.display='none'"
                style="position:absolute; top:10px; right:10px; background:#e74c3c; border:none; color:white; font-size:1rem; cursor:pointer; padding:4px 10px; border-radius:4px;">X Fechar</button>

            <h2 style="color:#e67e22; margin-bottom:0.5rem;">🏪 IA: Preço de Atacado Automático</h2>
            <p style="color:#aaa; font-size:0.85rem; margin-bottom:1.5rem;">Define o preço de atacado dos produtos selecionados baseado no preço de varejo.</p>

            <div style="display:flex; gap:10px; align-items:center; margin-bottom:1rem;">
                <select id="ws_direction" style="background:#111; color:#fff; border:1px solid #444; border-radius:4px; padding:8px;">
                    <option value="reduce">📉 Reduzir (Desconto)</option>
                    <option value="increase">📈 Aumentar (Margem)</option>
                </select>
                <input type="number" id="ws_percent" value="5" min="1" max="90" step="0.5"
                    style="background:#111; color:#fff; border:1px solid #444; border-radius:4px; padding:8px; width:80px; text-align:center; font-size:1.1rem;">
                <span style="color:#fff; font-size:1.2rem;">%</span>
            </div>

            <div id="ws_preview" style="background:#111; border:1px solid #333; border-radius:8px; max-height:250px; overflow-y:auto; padding:10px; margin-bottom:1rem;"></div>

            <div style="display:flex; gap:10px; justify-content:space-between; align-items:center;">
                <button type="button" class="btn" onclick="previewWholesale()" style="background:#555;">👁️ Pré-Visualizar</button>
                <button type="button" class="btn" onclick="applyWholesale()" style="background:#27ae60; font-weight:bold;">🚀 Aplicar Atacado</button>
            </div>
            <span id="ws_status" style="display:block; margin-top:10px; color:#aaa; text-align:center;"></span>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        let currentMode = 'price'; // price | info

        function openAIModal(mode) {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Selecione os produtos na lista primeiro!');
                return;
            }

            currentMode = mode;
            const title = document.getElementById('aiModalTitle');
            const instructionBox = document.getElementById('instructionBox');

            if (mode === 'price') {
                title.innerText = "💰 IA: Atualizar Preços em Massa";
                instructionBox.style.display = 'block';
            }
            if (mode === 'info') {
                title.innerText = "📦 IA: Completar Cadastro Técnico (EAN/NCM)";
                instructionBox.style.display = 'none';
            }

            document.getElementById('aiBulkModal').style.display = 'flex';
        }

        function copyBulkPrompt() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            let data = [];

            checkboxes.forEach(cb => {
                const tr = cb.closest('tr');
                const id = reqAttr(tr, 'data-id');
                const name = reqAttr(tr, 'data-name');
                const price = reqAttr(tr, 'data-price');

                data.push({ id: id, name: name, current_price: price });
            });

            let prompt = "";
            let jsonList = JSON.stringify(data, null, 2);

            if (currentMode === 'price') {
                const instruction = document.getElementById('aiInstruction').value || '[ESCREVA SUA REGRA, EX: +10%]';
                prompt = `Atue como Gerente de E-commerce. Tenho esta lista de produtos:
${jsonList}

Gere um JSON de atualização de preços seguindo a regra: ${instruction}.
Retorne APENAS o JSON válido: [{"id": 1, "price": 100.00}, ...]`;
            }
            else if (currentMode === 'info') {
                prompt = `Atue como Especialista em Cadastro de Produtos. Tenho esta lista:
${jsonList}

Preciso completar os dados fiscais e logísticos faltantes.
Para cada produto, identifique o NCM mais provável, gere um EAN fictício válido (se não souber o real), estime peso (kg) e dimensões da embalagem (cm).
Retorne APENAS um JSON array válido com os campos: "id", "ncm", "ean", "weight_kg", "length_cm", "width_cm", "height_cm", "brand".
Formato: [{"id": 1, "ncm": "95045000", "ean": "789...", "weight_kg": 0.5, "length_cm": 20, "width_cm": 15, "height_cm": 10, "brand": "Marca"}, ...]`;
            }

            navigator.clipboard.writeText(prompt).then(() => {
                if (currentMode === 'price') {
                    alert('Prompt copiado! \n1. Cole no Gemini. \n2. Copie o JSON de volta aqui.');
                } else {
                    alert('Prompt copiado! \n1. Cole no Gemini. \n2. Copie o JSON de volta aqui.');
                }
            });
        }

        function goToMarketplaces() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            const ids = [];
            checkboxes.forEach(cb => ids.push(cb.value));
            sessionStorage.setItem('selected_product_ids', JSON.stringify(ids));
            window.location.href = 'marketplaces.php';
        }

        function reqAttr(el, attr) {
            return el.getAttribute(attr) || '';
        }

        function applyBulkUpdate() {
            const jsonStr = document.getElementById('jsonBulkInput').value;
            const status = document.getElementById('bulkStatus');

            try {
                // Find JSON content even if user pasted extra text
                const jsonMatch = jsonStr.match(/\[[\s\S]*\]/);
                if (!jsonMatch) throw new Error("JSON Array não encontrado. Busque por [ ... ]");

                const json = JSON.parse(jsonMatch[0]);
                status.innerText = 'Processando...';

                fetch('api_bulk_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(json)
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            alert(res.message);
                            location.reload();
                        } else {
                            alert('Erro: ' + (res.error || 'Erro desconhecido'));
                            console.log(res.errors);
                            status.innerText = 'Erro.';
                        }
                    })
                    .catch(e => {
                        alert('Erro de conexão.');
                        console.error(e);
                    });

            } catch (e) {
                alert('JSON Inválido! ' + e.message);
            }
        }

        // ===== ATACADO AUTOMÁTICO =====
        function openWholesaleModal() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Selecione os produtos na lista primeiro!');
                return;
            }
            document.getElementById('ws_preview').innerHTML = '<p style="color:#aaa; text-align:center;">Clique em "Pré-Visualizar" para ver os novos preços.</p>';
            document.getElementById('ws_status').innerText = checkboxes.length + ' produto(s) selecionado(s)';
            document.getElementById('wholesaleModal').style.display = 'flex';
        }

        function previewWholesale() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            const direction = document.getElementById('ws_direction').value;
            const percent = parseFloat(document.getElementById('ws_percent').value) || 5;
            const container = document.getElementById('ws_preview');
            
            let html = '<table style="width:100%; font-size:0.85rem; color:#ccc;"><thead><tr style="color:#e67e22;"><th>Produto</th><th>Varejo</th><th>Atacado Atual</th><th>Novo Atacado</th></tr></thead><tbody>';
            
            checkboxes.forEach(cb => {
                const tr = cb.closest('tr');
                const name = reqAttr(tr, 'data-name');
                const price = parseFloat(reqAttr(tr, 'data-price'));
                const currentWS = parseFloat(reqAttr(tr, 'data-price-wholesale')) || 0;
                
                let newWS;
                if (direction === 'reduce') {
                    newWS = price * (1 - percent / 100);
                } else {
                    newWS = price * (1 + percent / 100);
                }
                newWS = Math.round(newWS * 100) / 100;
                
                const diff = newWS - currentWS;
                const diffColor = diff > 0 ? '#e74c3c' : '#2ecc71';
                
                html += `<tr style="border-bottom:1px solid #333;">
                    <td style="padding:4px;">${name.substring(0, 30)}${name.length > 30 ? '...' : ''}</td>
                    <td style="padding:4px;">R$ ${price.toFixed(2).replace('.', ',')}</td>
                    <td style="padding:4px;">${currentWS > 0 ? 'R$ ' + currentWS.toFixed(2).replace('.', ',') : '-'}</td>
                    <td style="padding:4px; color:${diffColor}; font-weight:bold;">R$ ${newWS.toFixed(2).replace('.', ',')}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function applyWholesale() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            const direction = document.getElementById('ws_direction').value;
            const percent = parseFloat(document.getElementById('ws_percent').value) || 5;
            const status = document.getElementById('ws_status');
            
            if (!confirm(`Confirma aplicar ${direction === 'reduce' ? 'DESCONTO' : 'AUMENTO'} de ${percent}% no preço de atacado de ${checkboxes.length} produto(s)?`)) return;
            
            let updates = [];
            checkboxes.forEach(cb => {
                const tr = cb.closest('tr');
                const id = parseInt(reqAttr(tr, 'data-id'));
                const price = parseFloat(reqAttr(tr, 'data-price'));
                
                let newWS;
                if (direction === 'reduce') {
                    newWS = price * (1 - percent / 100);
                } else {
                    newWS = price * (1 + percent / 100);
                }
                newWS = Math.round(newWS * 100) / 100;
                
                updates.push({ id: id, price_wholesale: newWS });
            });
            
            status.innerText = 'Aplicando...';
            
            fetch('api_bulk_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updates)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('✅ ' + res.message + '\nPreços de atacado atualizados com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + (res.error || 'Erro desconhecido'));
                    status.innerText = 'Erro.';
                }
            })
            .catch(e => {
                alert('Erro de conexão.');
                console.error(e);
                status.innerText = 'Falha na conexão.';
            });
        }
    </script>

</body>

</html>