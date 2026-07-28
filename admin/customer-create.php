<?php
// catalogo/admin/customer-create.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$msg = '';

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
            // Se a IA retornar dados, mesclamos (prioridade para a IA)
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

    if (empty($res['name'])) {
        $lines = explode("\n", str_replace("\r", "", $text));
        foreach($lines as $line) {
            $line = trim($line);
            if(empty($line) || strlen($line) < 4) continue;
            // Ignorar cabeçalhos do WhatsApp
            if (preg_match('/^\[\d{2}:\d{2}, \d{2}\/\d{2}\/\d{4}\]/', $line)) continue;
            if(str_word_count($line) >= 2 && !preg_match('/[A-Z]{2}\s*$/', $line)) {
                $res['name'] = trim(preg_replace('/\b(shopee|mercado|venda|loja)\b/i', '', $line));
                break;
            }
        }
    }
    
    echo json_encode($res);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_customer'])) {
    $name = $_POST['name'];
    $email = trim($_POST['email'] ?? '');
    $phone = $_POST['phone'];
    $doc = $_POST['document'];

    // Auto-generate email if empty
    if (empty($email)) {
        $cleanDoc = preg_replace('/\D/', '', $doc);
        if (!empty($cleanDoc)) {
            $email = $cleanDoc . '@fightarcade.com.br';
        } else {
            $email = 'cliente_' . time() . '_' . rand(100, 999) . '@fightarcade.com.br';
        }
    }

    // Address
    $zip = $_POST['zipcode'] ?? '';
    $addr = $_POST['address'] ?? '';
    $num = $_POST['number'] ?? '';
    $bairro = $_POST['neighborhood'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $complement = $_POST['complement'] ?? '';
    $is_vip = isset($_POST['is_vip']) ? 1 : 0;

    // Password logic
    $raw_pass = $_POST['password'];
    if (empty($raw_pass)) {
        // Generate random password if empty
        $raw_pass = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    }
    $password = password_hash($raw_pass, PASSWORD_DEFAULT);

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $msg = '<div class="alert alert-error">E-mail já cadastrado! (Gerado: ' . htmlspecialchars($email) . ')</div>';
    } else {
        try {
            $sql = "INSERT INTO users (name, email, password, document, phone, zipcode, address, number, complement, neighborhood, city, state, role, is_vip, is_lead) 
                    VALUES (:name, :email, :pass, :doc, :phone, :zip, :addr, :num, :comp, :bairro, :city, :state, 'customer', :vip, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':pass' => $password,
                ':doc' => $doc,
                ':phone' => $phone,
                ':zip' => $zip,
                ':addr' => $addr,
                ':num' => $num,
                ':comp' => $complement,
                ':bairro' => $bairro,
                ':city' => $city,
                ':state' => $state,
                ':vip' => $is_vip
            ]);

            $msg = '<div class="alert alert-success">✅ Cliente cadastrado com sucesso!<br>Senha gerada: <strong>' . $raw_pass . '</strong></div>';
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-error">Erro ao salvar: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Novo Cliente | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        // ViaCEP Integration
        function buscarCep(cep) {
            // Remove tudo que não é número
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
                            document.querySelector('[name=number]').focus();
                            document.getElementById('cep-status').innerText = '✅ ' + data.localidade + '/' + data.uf;
                            document.getElementById('cep-status').style.color = '#2ecc71';
                        } else {
                            document.getElementById('cep-status').innerText = '❌ CEP não encontrado';
                            document.getElementById('cep-status').style.color = '#e74c3c';
                        }
                    })
                    .catch(() => {
                        document.getElementById('cep-status').innerText = '❌ Erro na busca';
                        document.getElementById('cep-status').style.color = '#e74c3c';
                    });
            }
        }
    </script>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:800px; margin:0 auto;">
            <h2 style="text-align:left; border-bottom:1px solid #333; padding-bottom:1rem; margin-bottom:1rem;">
                Cadastrar Novo Cliente
            </h2>

            <?php echo $msg; ?>

            <div style="background:#111; padding:1rem; border-radius:8px; border:1px dashed #f39c12; margin-bottom:1.5rem;">
                <strong style="color:#f39c12;"><i class="fas fa-robot"></i> Preenchimento Mágico (Copiar & Colar)</strong>
                <p style="font-size:0.85rem; color:#888; margin:5px 0;">Copie o rodapé do site do cliente ou a mensagem do WhatsApp e cole abaixo. O sistema extrairá CNPJ, Telefone e CEP sozinho.</p>
                <textarea id="ai_text" rows="3" style="width:100%; padding:8px; background:#000; color:#fff; border:1px solid #333;" placeholder="Ex: CNPJ: 65.986.000/0001-58 - WhatsApp 11 959542357"></textarea>
                <button type="button" onclick="runAI()" class="btn btn-sm" style="margin-top:5px; background:#f39c12; color:#000; font-weight:bold;">✨ Extrair Dados Automático</button>
            </div>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <!-- Col 1: Personal Data -->
                    <div>
                        <h4 style="color:var(--primary); margin-bottom:1rem;">Dados Pessoais</h4>

                        <label>Nome Completo / Empresa</label>
                        <input type="text" name="name" id="f_name" required>

                        <label>Email (Deixe em branco para gerar automático)</label>
                        <input type="email" name="email" id="f_email" placeholder="Ex: email@dominio.com">

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <div>
                                <label>CPF/CNPJ</label>
                                <div style="display:flex; gap:5px;">
                                    <input type="text" name="document" id="f_doc" style="flex:1;">
                                    <button type="button" onclick="generateCPF('f_doc')" class="btn btn-sm" style="background:#444; color:#fff; white-space:nowrap; padding:0 10px;">🎲 Gerar</button>
                                </div>
                            </div>
                            <div>
                                <label>WhatsApp/Telefone</label>
                                <input type="text" name="phone" id="f_phone">
                            </div>
                        </div>

                        <label>Senha Inicial (Deixe em branco para gerar aleatória)</label>
                        <input type="text" name="password" placeholder="Gerar Automática">
                    </div>

                    <!-- Col 2: Address Data -->
                    <div>
                        <h4 style="color:var(--primary); margin-bottom:1rem;">Endereço</h4>

                        <label>CEP</label>
                        <input type="text" name="zipcode" oninput="buscarCep(this.value)" onblur="buscarCep(this.value)" placeholder="00000-000">
                        <small id="cep-status" style="color:#888;"></small>

                        <label>Rua / Logradouro</label>
                        <input type="text" name="address">

                        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                            <div>
                                <label>Número</label>
                                <input type="text" name="number" id="f_num">
                            </div>
                            <div>
                                <label>Bairro</label>
                                <input type="text" name="neighborhood">
                            </div>
                        </div>

                        <label>Complemento</label>
                        <input type="text" name="complement" placeholder="Apt, Bloco, Sala...">

                        <div style="display:grid; grid-template-columns: 3fr 1fr; gap:10px;">
                            <div>
                                <label>Cidade</label>
                                <input type="text" name="city">
                            </div>
                            <div class="form-group">
                                <label>UF</label>
                                <input type="text" name="state" maxlength="2">
                            </div>
                        </div>

                        <div
                            style="margin: 1rem 0; padding:1rem; border:1px solid #333; background:#1a1a1a; border-radius:6px;">
                            <label style="display:flex; align-items:center; cursor:pointer;">
                                <input type="checkbox" name="is_vip" value="1" style="width:auto; margin:0 10px 0 0;">
                                <span style="color: gold; font-weight:bold;">👑 Cliente VIP (Acesso direto ao Catálogo
                                    Exclusivo)</span>
                            </label>
                        </div>

                        <div style="margin-top:2rem; text-align:right;">
                            <a href="customers.php" class="btn btn-secondary" style="margin-right:10px;">Voltar</a>
                            <button type="submit" name="create_customer" class="btn">Salvar Cliente</button>
                        </div>
            </form>
        </div>
    </div>
    <script>
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
        
        fetch('customer-create.php', { method:'POST', body:fd })
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
            alert('🤖 Dados extraídos! Confira e termine de preencher.');
        });
    }

    // Auto-preenche se veio da Central de Atendimento (WhatsApp)
    (function() {
        const params = new URLSearchParams(window.location.search);
        const phone = params.get('phone');
        const source = params.get('source');
        if (phone) {
            document.getElementById('f_phone').value = phone;
        }
        if (source === 'whatsapp') {
            const banner = document.createElement('div');
            banner.innerHTML = '📲 <strong>Contato vindo do WhatsApp!</strong> O telefone já foi preenchido. Complete os demais campos para cadastrar este lead no sistema.';
            banner.style.cssText = 'background:#1a3a2a; border:1px solid #27ae60; color:#2ecc71; padding:12px 16px; border-radius:8px; margin-bottom:1rem; font-size:0.9rem;';
            const container = document.querySelector('.auth-box');
            if (container) container.insertBefore(banner, container.querySelector('h2').nextSibling);
        }
    })();
    </script>
</body>

</html>