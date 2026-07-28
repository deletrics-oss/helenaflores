<?php
// catalogo/admin/customer-edit.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$msg = '';
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    header("Location: customers.php");
    exit;
}

// Fetch existing user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    die("Cliente não encontrado.");
}

// AJAX: AI Parse (Shared with customer-create)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $doc = $_POST['document'];
    $zip = $_POST['zipcode'] ?? '';
    $addr = $_POST['address'] ?? '';
    $num = $_POST['number'] ?? '';
    $bairro = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $complement = $_POST['complement'] ?? '';
    $is_vip = isset($_POST['is_vip']) ? 1 : 0;
    $is_lead = isset($_POST['is_lead']) ? 1 : 0;

    try {
        $sql = "UPDATE users SET 
                name = :name, email = :email, document = :doc, phone = :phone, 
                zipcode = :zip, address = :addr, number = :num, complement = :comp, 
                neighborhood = :bairro, city = :city, state = :state, 
                is_vip = :vip, is_lead = :lead
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':doc' => $doc,
            ':phone' => $phone,
            ':zip' => $zip,
            ':addr' => $addr,
            ':num' => $num,
            ':comp' => $complement,
            ':bairro' => $bairro,
            ':city' => $city,
            ':state' => $state,
            ':vip' => $is_vip,
            ':lead' => $is_lead,
            ':id' => $id
        ]);

        $msg = '<div class="alert alert-success">✅ Dados do cliente atualizados com sucesso!</div>';
        // Refresh local data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $msg = '<div class="alert alert-error">Erro ao atualizar: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function buscarCep(cep) {
            const cepClean = cep.replace(/\D/g, '');
            if (cepClean.length === 8) {
                document.getElementById('cep-status').innerText = 'Buscando...';
                fetch(`https://viacep.com.br/ws/${cepClean}/json/`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.erro) {
                            document.querySelector('[name=address]').value = data.logradouro || '';
                            document.querySelector('[name=neighborhood]').value = data.bairro || '';
                            document.querySelector('[name=city]').value = data.localidade || '';
                            document.querySelector('[name=state]').value = data.uf || '';
                            document.getElementById('cep-status').innerText = '✅ ' + data.localidade + '/' + data.uf;
                            document.getElementById('cep-status').style.color = '#2ecc71';
                        }
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
            fetch('customer-edit.php?id=<?php echo $id; ?>', { method:'POST', body:fd })
            .then(r=>r.json())
            .then(d=>{
                if(d.name) document.getElementById('f_name').value = d.name;
                if(d.document) document.getElementById('f_doc').value = d.document;
                if(d.phone) document.getElementById('f_phone').value = d.phone;
                if(d.email) document.getElementById('f_email').value = d.email;
                if(d.number) document.getElementById('f_num').value = d.number;
                if(d.zipcode) {
                    document.querySelector('[name=zipcode]').value = d.zipcode;
                    buscarCep(d.zipcode);
                }
                alert('🤖 Dados extraídos!');
            });
        }
    </script>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:800px; margin:0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #333; padding-bottom:1rem; margin-bottom:1rem;">
                <h2>Editar Cliente: <?php echo htmlspecialchars($user['name']); ?></h2>
                <a href="customers.php" class="btn btn-secondary">Voltar</a>
            </div>

            <?php echo $msg; ?>

            <div style="background:#111; padding:1rem; border-radius:8px; border:1px dashed #f39c12; margin-bottom:1.5rem;">
                <strong style="color:#f39c12;"><i class="fas fa-robot"></i> IA: Atualização Mágica</strong>
                <p style="font-size:0.85rem; color:#888;">Cole novos dados aqui para atualizar campos automaticamente.</p>
                <textarea id="ai_text" rows="2" style="width:100%; padding:8px; background:#000; color:#fff; border:1px solid #333;"></textarea>
                <button type="button" onclick="runAI()" class="btn btn-sm" style="margin-top:5px; background:#f39c12; color:#000;">✨ Extrair e Preencher</button>
            </div>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
                    <div>
                        <h4 style="color:var(--primary); margin-bottom:1rem;">Dados Cadastrais</h4>
                        <label>Nome Completo / Empresa</label>
                        <input type="text" name="name" id="f_name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        <label>Email</label>
                        <input type="email" name="email" id="f_email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <div>
                                <label>CPF/CNPJ</label>
                                <div style="display:flex; gap:5px;">
                                    <input type="text" name="document" id="f_doc" value="<?php echo htmlspecialchars($user['document']); ?>" style="flex:1;">
                                    <button type="button" onclick="generateCPF('f_doc')" class="btn btn-sm" style="background:#444; color:#fff; white-space:nowrap; padding:0 10px;">🎲 Gerar</button>
                                </div>
                            </div>
                            <div>
                                <label>WhatsApp/Telefone</label>
                                <input type="text" name="phone" id="f_phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                        </div>
                        <label style="display:flex; align-items:center; gap:10px; margin-top:1rem; cursor:pointer;">
                            <input type="checkbox" name="is_vip" value="1" <?php echo $user['is_vip'] ? 'checked' : ''; ?> style="width:auto;">
                            <span style="color:gold; font-weight:bold;">👑 Cliente VIP</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:10px; margin-top:0.5rem; cursor:pointer;">
                            <input type="checkbox" name="is_lead" value="1" <?php echo $user['is_lead'] ? 'checked' : ''; ?> style="width:auto;">
                            <span>🎯 Marcar como Lead</span>
                        </label>
                    </div>

                    <div>
                        <h4 style="color:var(--primary); margin-bottom:1rem;">Endereço de Entrega</h4>
                        <label>CEP</label>
                        <input type="text" name="zipcode" value="<?php echo htmlspecialchars($user['zipcode']); ?>" oninput="buscarCep(this.value)">
                        <small id="cep-status" style="color:#888;"></small>
                        <label>Rua / Logradouro</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>">
                        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                            <div>
                                <label>Número</label>
                                <input type="text" name="number" id="f_num" value="<?php echo htmlspecialchars($user['number']); ?>">
                            </div>
                            <div>
                                <label>Bairro</label>
                                <input type="text" name="neighborhood" value="<?php echo htmlspecialchars($user['neighborhood']); ?>">
                            </div>
                        </div>
                        <label>Complemento</label>
                        <input type="text" name="complement" value="<?php echo htmlspecialchars($user['complement']); ?>">
                        <div style="display:grid; grid-template-columns: 3fr 1fr; gap:10px;">
                            <div>
                                <label>Cidade</label>
                                <input type="text" name="city" value="<?php echo htmlspecialchars($user['city']); ?>">
                            </div>
                            <div>
                                <label>UF</label>
                                <input type="text" name="state" maxlength="2" value="<?php echo htmlspecialchars($user['state']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:2rem; text-align:right;">
                    <button type="submit" name="update_customer" class="btn" style="padding:12px 30px; font-size:1.1rem;">Atualizar Cadastro</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
