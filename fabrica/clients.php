<?php
// catalogo/fabrica/clients.php
require_once __DIR__ . '/header.php';

$msg = '';
$err = '';

// AJAX: AI Parse
if (isset($_POST['ajax_ai'])) {
    header('Content-Type: application/json');
    $text = $_POST['text'] ?? '';
    $res = [];
    
    // AI Smart Extraction (Gemini)
    require_once __DIR__ . '/../includes/ai_sdr.php';
    $ai = new AIService($pdo);
    
    if ($ai->isActive()) {
        $aiRes = $ai->extractCustomerData($text);
        if (!empty($aiRes)) {
            foreach ($aiRes as $k => $v) {
                if (!empty($v)) $res[$k] = $v;
            }
        }
    }

    // Standard Regex Fallback
    if (empty($res['document'])) {
        preg_match('/(?:cpf|cnpj|doc)[\s:]*([\d\.\-\/]{11,18})/i', $text, $m);
        if(!$m) preg_match('/([\d]{2}\.[\d]{3}\.[\d]{3}\/[\d]{4}-[\d]{2}|[\d]{3}\.[\d]{3}\.[\d]{3}-[\d]{2})/', $text, $m);
        if($m) $res['document'] = preg_replace('/[^\d]/', '', $m[1]);
    }
    
    if (empty($res['zipcode'])) {
        preg_match('/(?:cep)[\s:]*([\d]{5}-?[\d]{3})/i', $text, $m);
        if(!$m) preg_match('/([\d]{5}-[\d]{3})/', $text, $m);
        if($m) $res['zipcode'] = preg_replace('/[^\d]/', '', $m[1]);
    }
    
    if (empty($res['phone'])) {
        preg_match('/(?:\(?\d{2}\)?\s?)?(?:9\s?)?\d{4}[-\s]?\d{4}/', $text, $m);
        if($m) $res['phone'] = preg_replace('/[^\d]/', '', $m[0]);
    }
    
    if (empty($res['email'])) {
        preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $m);
        if($m) $res['email'] = $m[1];
    }
    
    if (empty($res['number'])) {
        preg_match('/,\s*(s\/?n|\d{1,5})\b/i', $text, $m);
        if($m) $res['number'] = strtoupper(trim($m[1]));
    }

    echo json_encode($res);
    exit;
}

// Bulk Import Action
if (isset($_POST['bulk_import_catalog'])) {
    try {
        $count = 0;
        $crm_users = $pdo->query("SELECT name, document, phone, email, address, number, complement, neighborhood, city, state, zipcode, is_vip, is_lead FROM users WHERE role != 'admin'")->fetchAll(PDO::FETCH_ASSOC);
        
        $check = $pdo->prepare("SELECT COUNT(*) FROM factory_clients WHERE (phone = ? AND phone != '') OR (email = ? AND email != '') OR (document = ? AND document != '')");
        $ins = $pdo->prepare("INSERT INTO factory_clients (name, document, phone, email, address, number, complement, neighborhood, city, state, zipcode, is_vip, is_lead) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($crm_users as $u) {
            $check->execute([$u['phone'], $u['email'], $u['document']]);
            if ($check->fetchColumn() == 0) {
                $ins->execute([
                    $u['name'], $u['document'], $u['phone'], $u['email'], 
                    $u['address'], $u['number'], $u['complement'], $u['neighborhood'], 
                    $u['city'], $u['state'], $u['zipcode'], $u['is_vip'], $u['is_lead']
                ]);
                $count++;
            }
        }
        $msg = "Importação concluída! $count clientes novos foram copiados do catálogo para o sistema da fábrica.";
    } catch (Exception $e) {
        $err = "Erro na importação: " . $e->getMessage();
    }
}

// Add / Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $document = trim($_POST['document'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $number = trim($_POST['number'] ?? '');
    $complement = trim($_POST['complement'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zipcode = trim($_POST['zipcode'] ?? '');
    $is_vip = isset($_POST['is_vip']) ? 1 : 0;
    $is_lead = isset($_POST['is_lead']) ? 1 : 0;

    if (!empty($name)) {
        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare("UPDATE factory_clients SET name = ?, document = ?, phone = ?, email = ?, address = ?, number = ?, complement = ?, neighborhood = ?, city = ?, state = ?, zipcode = ?, is_vip = ?, is_lead = ? WHERE id = ?");
            if ($stmt->execute([$name, $document, $phone, $email, $address, $number, $complement, $neighborhood, $city, $state, $zipcode, $is_vip, $is_lead, $id])) {
                $msg = 'Cliente atualizado com sucesso!';
            } else {
                $err = 'Erro ao atualizar dados.';
            }
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO factory_clients (name, document, phone, email, address, number, complement, neighborhood, city, state, zipcode, is_vip, is_lead) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $document, $phone, $email, $address, $number, $complement, $neighborhood, $city, $state, $zipcode, $is_vip, $is_lead])) {
                $msg = 'Cliente cadastrado com sucesso!';
            } else {
                $err = 'Erro ao cadastrar novo cliente.';
            }
        }
    } else {
        $err = 'O nome do cliente é obrigatório.';
    }
}

// Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM factory_clients WHERE id = ?");
    if ($stmt->execute([$id])) {
        $msg = 'Cliente removido com sucesso!';
    } else {
        $err = 'Erro ao remover cliente.';
    }
}

// Get editing item
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM factory_clients WHERE id = ?");
    $stmt->execute([$id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all clients
$clients = $pdo->query("SELECT * FROM factory_clients ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    function buscarCep(cep) {
        const cepClean = cep.replace(/\D/g, '');
        if (cepClean.length === 8) {
            document.getElementById('cep-status').innerText = 'Buscando CEP...';
            fetch(`https://viacep.com.br/ws/${cepClean}/json/`)
                .then(res => res.json())
                .then(data => {
                    if (!data.erro) {
                        document.querySelector('[name=address]').value = data.logradouro || '';
                        document.querySelector('[name=neighborhood]').value = data.bairro || '';
                        document.querySelector('[name=city]').value = data.localidade || '';
                        document.querySelector('[name=state]').value = data.uf || '';
                        document.getElementById('cep-status').innerText = '✅ ' + data.localidade + '/' + data.uf;
                        document.getElementById('cep-status').style.color = '#00e676';
                    } else {
                        document.getElementById('cep-status').innerText = '❌ CEP não encontrado.';
                        document.getElementById('cep-status').style.color = '#ef4444';
                    }
                })
                .catch(() => {
                    document.getElementById('cep-status').innerText = '❌ Erro ao buscar CEP.';
                });
        }
    }
    
    function generateCPF(fieldId) {
        const n = () => Math.floor(Math.random() * 9);
        const mod = (n, m) => Math.round(n - (Math.floor(n / m) * m));
        let n1 = n(), n2 = n(), n3 = n(), n4 = n(), n5 = n(), n6 = n(), n7 = n(), n8 = n(), n9 = n();
        let d1 = n9 * 2 + n8 * 3 + n7 * 4 + n6 * 5 + n5 * 6 + n4 * 7 + n3 * 8 + n2 * 9 + n1 * 10;
        d1 = 11 - (mod(d1, 11));
        if (d1 >= 10) d1 = 0;
        let d2 = d1 * 2 + n9 * 3 + n8 * 4 + n7 * 5 + n6 * 6 + n5 * 7 + n4 * 8 + n3 * 9 + n2 * 10 + n1 * 11;
        d2 = 11 - (mod(d2, 11));
        if (d2 >= 10) d2 = 0;
        const cpf = `${n1}${n2}${n3}${n4}${n5}${n6}${n7}${n8}${n9}${d1}${d2}`;
        document.getElementById(fieldId).value = cpf;
    }
    
    function runAI() {
        const txt = document.getElementById('ai_text').value;
        if(!txt) return alert('Cole o texto primeiro!');
        const fd = new FormData();
        fd.append('ajax_ai', '1');
        fd.append('text', txt);
        
        document.getElementById('ai_btn').innerText = '✨ Extraindo...';
        fetch('clients.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if(d.name) document.getElementById('f_name').value = d.name;
            if(d.document) document.getElementById('f_doc').value = d.document;
            if(d.phone) document.getElementById('f_phone').value = d.phone;
            if(d.email) document.getElementById('f_email').value = d.email;
            if(d.number) document.getElementById('f_num').value = d.number;
            if(d.zipcode) {
                document.querySelector('[name=zipcode]').value = d.zipcode;
                buscarCep(d.zipcode);
            }
            document.getElementById('ai_btn').innerText = '✨ Extrair e Preencher';
            alert('🤖 Dados extraídos com inteligência artificial!');
        })
        .catch(() => {
            document.getElementById('ai_btn').innerText = '✨ Extrair e Preencher';
            alert('Erro ao extrair dados via IA.');
        });
    }
</script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:15px;">
    <h2><i class="fas fa-users" style="color:var(--primary);"></i> Gestão de Clientes B2B (Fábrica)</h2>
    <div style="display:flex; gap:10px;">
        <form method="POST" style="margin:0;">
            <button type="submit" name="bulk_import_catalog" value="1" class="btn btn-secondary" onclick="return confirm('Deseja copiar todos os clientes cadastrados no catálogo principal para a fábrica?')"><i class="fas fa-file-import"></i> Importar do Catálogo</button>
        </form>
        <?php if(!$edit_item): ?>
            <a href="?add=1" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Cliente</a>
        <?php endif; ?>
    </div>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $err; ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: <?php echo (isset($_GET['add']) || $edit_item) ? '1fr 380px' : '1fr'; ?>; gap:2rem;">
    
    <!-- List Column -->
    <div>
        <div class="card">
            <h3>Lista de Clientes Cadastrados</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>CNPJ / CPF</th>
                            <th>Contato</th>
                            <th>Status</th>
                            <th>Cidade / UF</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($clients)): ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">Nenhum cliente cadastrado no módulo da fábrica.</td></tr>
                        <?php else: ?>
                            <?php foreach($clients as $c): 
                                $isVip = (int)($c['is_vip'] ?? 0);
                                $isLead = (int)($c['is_lead'] ?? 0);
                            ?>
                                <tr>
                                    <td>#<?php echo $c['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                                        <?php if($isVip): ?><span style="color:#f1c40f; margin-left:5px;" title="VIP">👑</span><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['document'] ?: '-'); ?></td>
                                    <td>
                                        <div style="font-size:0.85rem;"><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($c['email'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if($isLead): ?>
                                            <span class="badge badge-warning">Lead</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">B2B</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(($c['city'] && $c['state']) ? $c['city'] . ' - ' . $c['state'] : '-'); ?></td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <a href="client-details.php?id=<?php echo $c['id']; ?>" class="btn btn-primary btn-sm" title="Ver Extrato e Conta"><i class="fas fa-file-invoice-dollar"></i></a>
                                        <a href="?edit=<?php echo $c['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $c['id']; ?>" onclick="return confirm('Deseja realmente excluir este cliente?')" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
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
        <!-- AI Smart Assistant -->
        <div class="card" style="border:1px dashed var(--accent); background:rgba(241,196,15,0.02); margin-bottom:1.5rem;">
            <strong style="color:var(--accent);"><i class="fas fa-robot"></i> Assistente IA (Gemini)</strong>
            <p style="font-size:0.75rem; color:var(--text-muted); margin:5px 0 10px;">Cole dados do cliente como nome, celular, CEP para preencher o formulário automaticamente.</p>
            <textarea id="ai_text" class="form-control" rows="2" style="font-size:0.8rem; background:#080b10; border:1px solid var(--border);" placeholder="Ex: Nome: João da Silva, Celular: 11999998888, CEP: 01001000, Num: 123"></textarea>
            <button type="button" id="ai_btn" onclick="runAI()" class="btn btn-secondary btn-sm" style="margin-top:10px; width:100%; font-size:0.75rem; background:var(--accent); color:#000;"><i class="fas fa-magic"></i> ✨ Extrair e Preencher</button>
        </div>

        <div class="card">
            <h3><?php echo $edit_item ? 'Editar Cadastro' : 'Novo Cadastro'; ?></h3>
            <form method="POST" style="margin-top:1.5rem;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">

                <div class="form-group">
                    <label>Nome / Razão Social</label>
                    <input type="text" name="name" id="f_name" class="form-control" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="Ex: Metalúrgica Silva Ltda">
                </div>

                <div class="form-group">
                    <label>CPF / CNPJ</label>
                    <div style="display:flex; gap:5px;">
                        <input type="text" name="document" id="f_doc" class="form-control" value="<?php echo htmlspecialchars($edit_item['document'] ?? ''); ?>" placeholder="Apenas números">
                        <button type="button" onclick="generateCPF('f_doc')" class="btn btn-secondary btn-sm" style="white-space:nowrap; padding:0 8px;">🎲 Gerar</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Telefone / WhatsApp</label>
                    <input type="text" name="phone" id="f_phone" class="form-control" value="<?php echo htmlspecialchars($edit_item['phone'] ?? ''); ?>" placeholder="Ex: 11988887777">
                </div>

                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" id="f_email" class="form-control" value="<?php echo htmlspecialchars($edit_item['email'] ?? ''); ?>" placeholder="Ex: cliente@empresa.com">
                </div>

                <div style="display:flex; gap:15px; margin-bottom:1.2rem;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" name="is_vip" value="1" <?php echo ($edit_item && $edit_item['is_vip']) ? 'checked' : ''; ?>>
                        <span style="color:#f1c40f; font-weight:bold;">👑 Cliente VIP</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.85rem;">
                        <input type="checkbox" name="is_lead" value="1" <?php echo ($edit_item && $edit_item['is_lead']) ? 'checked' : ''; ?>>
                        <span>🎯 Lead</span>
                    </label>
                </div>

                <hr style="border-color:var(--border); margin:1.5rem 0;">
                <h4 style="font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem;">Endereço</h4>

                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="zipcode" class="form-control" value="<?php echo htmlspecialchars($edit_item['zipcode'] ?? ''); ?>" placeholder="Ex: 01310100" oninput="buscarCep(this.value)">
                    <small id="cep-status" style="display:block; margin-top:4px; font-weight:600; font-size:0.75rem; color:var(--text-muted);"></small>
                </div>

                <div class="form-group">
                    <label>Endereço / Logradouro</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($edit_item['address'] ?? ''); ?>" placeholder="Rua, Avenida...">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="number" id="f_num" class="form-control" value="<?php echo htmlspecialchars($edit_item['number'] ?? ''); ?>" placeholder="Ex: 123 ou S/N">
                    </div>
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complement" class="form-control" value="<?php echo htmlspecialchars($edit_item['complement'] ?? ''); ?>" placeholder="Apt, Bloco...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="neighborhood" class="form-control" value="<?php echo htmlspecialchars($edit_item['neighborhood'] ?? ''); ?>" placeholder="Bairro...">
                </div>

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($edit_item['city'] ?? ''); ?>" placeholder="Cidade...">
                    </div>
                    <div class="form-group">
                        <label>Estado (UF)</label>
                        <input type="text" name="state" maxlength="2" class="form-control" placeholder="SP" value="<?php echo htmlspecialchars($edit_item['state'] ?? ''); ?>">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Salvar</button>
                    <a href="clients.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
