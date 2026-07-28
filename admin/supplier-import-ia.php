<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();
requirePermission('suppliers');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_json'])) {
    $data = json_decode($_POST['json_content'], true);
    if ($data && is_array($data)) {
        $count = 0;
        foreach ($data as $s) {
            $name = $s['name'] ?? '';
            if (!$name) continue;

            $contact = $s['contact_name'] ?? '';
            $phone = $s['phone'] ?? '';
            $email = $s['email'] ?? '';
            $address = $s['address'] ?? '';
            $city = $s['city'] ?? '';
            $state = $s['state'] ?? '';
            $zip = $s['zipcode'] ?? '';
            $lat = $s['lat'] ?? null;
            $lng = $s['lng'] ?? null;
            $notes = $s['notes'] ?? '';

            $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_name, phone, email, address, city, state, zipcode, lat, lng, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $contact, $phone, $email, $address, $city, $state, $zip, $lat, $lng, $notes]);
            $count++;
        }
        $msg = "✅ Sucesso! $count fornecedores importados.";
    } else {
        $msg = "❌ Erro: JSON inválido.";
    }
}

// AJAX: AI Supplier Parse
if (isset($_POST['ajax_ai'])) {
    header('Content-Type: application/json');
    $text = $_POST['text'] ?? '';
    
    require_once __DIR__ . '/../includes/ai_sdr.php';
    $ai = new AIService($pdo);
    $res = [];

    if ($ai->isActive()) {
        $res = $ai->extractSupplierData($text);
    }
    
    // Fallback logic handled by frontend for now or minimal here
    echo json_encode($res);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Importar Fornecedores via IA | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .import-container { max-width: 900px; margin: 2rem auto; }
        .ai-prompt-box { background: #1a1e2a; border: 1px dashed var(--primary); padding: 20px; border-radius: 12px; margin-bottom: 2rem; position: relative; }
        .copy-btn { position: absolute; top: 15px; right: 15px; background: #333; color: #fff; border: none; padding: 5px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; }
        .copy-btn:hover { background: var(--primary); color: #000; }
        
        textarea { width: 100%; height: 300px; background: #0b0e14; border: 1px solid #333; color: #00ff00; padding: 15px; border-radius: 10px; font-family: 'Courier New', Courier, monospace; font-size: 0.9rem; resize: none; }
        
        .whatsapp-paste-box { background: rgba(37,211,102,0.05); border: 1px solid rgba(37,211,102,0.2); padding: 15px; border-radius: 10px; margin-top: 2rem; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="import-container">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h1>🤖 Cadastro IA / JSON (Fornecedores)</h1>
                <a href="suppliers.php" class="btn" style="background:#333;"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>

            <?php if($msg): ?>
                <div class="alert alert-info"><?php echo $msg; ?></div>
            <?php endif; ?>

            <div class="ai-prompt-box">
                <button class="copy-btn" onclick="copyPrompt()"><i class="fas fa-copy"></i> Copiar Prompt</button>
                <h3 style="color:var(--primary); margin-top:0;"><i class="fas fa-magic"></i> Como usar a IA para cadastrar:</h3>
                <p style="font-size:0.9rem; color:#aaa;">Copie o prompt abaixo e cole no seu Chat GPT ou Gemini junto com as informações do fornecedor (ou peça para ele criar novos):</p>
                <code id="ai-prompt" style="display:block; background:#000; padding:15px; border-radius:8px; font-size:0.85rem; color:#fff; border:1px solid #222;">
Atue como Especialista em Logística. Preciso cadastrar meus fornecedores no sistema.<br>
Gere um JSON array com os seguintes campos:<br>
"name", "contact_name", "phone" (com DDI 55), "email", "address", "city", "state" (UF), "zipcode", "lat", "lng", "notes".<br>
Retorne APENAS o JSON válido.
                </code>
            </div>

            <form method="POST">
                <div style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold;">Cole o JSON gerado abaixo:</label>
                    <textarea name="json_content" id="json_content" placeholder='[{"name": "Exemplo LTDA", "phone": "551199999999"...}]'></textarea>
                </div>
                <button type="submit" name="import_json" class="btn" style="width:100%; padding:15px; font-size:1.1rem;">🚀 PROCESSAR E CADASTRAR AGORA</button>
            </form>

            <div class="whatsapp-paste-box">
                <h4 style="color:#25d366; margin-top:0;"><i class="fab fa-whatsapp"></i> Extrair da "Cola" do WhatsApp</h4>
                <p style="font-size:0.8rem; color:#888;">Cole aqui o texto bruto que você recebeu e a IA (ou script) tentará converter para JSON:</p>
                <textarea id="wa_paste" style="height:120px; background:#111; color:#fff;" placeholder="Fornecedor: João Silva\nTel: 11 98888-7777..."></textarea>
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button onclick="runSmartAI()" class="btn-sm" style="flex:1; background:#9b59b6; color:#fff; border:none; padding:8px 15px; border-radius:6px; cursor:pointer; font-weight:bold;">✨ Extrair com Gemini (Smart)</button>
                    <button onclick="parseWhatsApp()" class="btn-sm" style="flex:1; background:#25d366; color:#fff; border:none; padding:8px 15px; border-radius:6px; cursor:pointer;">⚡ Script Rápido (Regex)</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function runSmartAI() {
            const txt = document.getElementById('wa_paste').value;
            if(!txt) return alert('Cole o texto primeiro!');
            
            const btn = event.target;
            btn.innerText = '🤖 Pensando...';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('ajax_ai', '1');
            fd.append('text', txt);

            fetch('supplier-import-ia.php', { method:'POST', body:fd })
            .then(r=>r.json())
            .then(data=>{
                if(data && data.length > 0) {
                    document.getElementById('json_content').value = JSON.stringify(data, null, 4);
                    alert("✨ Dados extraídos pelo Gemini! Revise e processe.");
                } else {
                    alert("A IA não conseguiu extrair dados válidos. Tente o Script Rápido.");
                }
            })
            .catch(e => alert('Erro: ' + e))
            .finally(() => {
                btn.innerText = '✨ Extrair com Gemini (Smart)';
                btn.disabled = false;
            });
        }
        function copyPrompt() {
            const prompt = document.getElementById('ai-prompt').innerText;
            navigator.clipboard.writeText(prompt);
            alert("Prompt copiado! Cole no seu chat de IA favorito.");
        }

        function parseWhatsApp() {
            const text = document.getElementById('wa_paste').value;
            if(!text) return;

            // Simple Regex Parser for common patterns
            const nameMatch = text.match(/(?:Nome|Fornecedor|Empresa):\s*(.*)/i);
            const phoneMatch = text.match(/(?:Tel|Fone|Cel|WhatsApp):\s*(.*)/i);
            const emailMatch = text.match(/(?:Email|E-mail):\s*([a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})/i);
            const addressMatch = text.match(/(?:End|Endereço|Rua):\s*(.*)/i);

            const result = [{
                name: nameMatch ? nameMatch[1].trim() : "Novo Fornecedor",
                contact_name: "",
                phone: phoneMatch ? phoneMatch[1].replace(/\D/g, '') : "",
                email: emailMatch ? emailMatch[1] : "",
                address: addressMatch ? addressMatch[1].trim() : "",
                city: "",
                state: "",
                zipcode: "",
                notes: "Importado via WhatsApp Paste"
            }];

            document.getElementById('json_content').value = JSON.stringify(result, null, 4);
            alert("Convertido! Revise o JSON e clique em Processar.");
        }
    </script>
</body>
</html>
