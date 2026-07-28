<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$file = __DIR__ . '/../includes/site_settings.json';
$data = [];

if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true) ?? [];
}

// Defaults
$defaults = [
    'store_name' => 'Fight Arcade',
    'whatsapp' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'hours' => '',
    'social_instagram' => '',
    'social_facebook' => '',
    'social_youtube' => '',
    'footer_text' => 'Sua loja especializada em peças Arcade.',
    'enable_pix' => 1,
    'enable_card' => 1,
    'enable_wholesale' => 1,
    'api_token' => ''
];

$data = array_merge($defaults, $data);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newData = [
        'store_name' => $_POST['store_name'],
        'whatsapp' => preg_replace('/\D/', '', $_POST['whatsapp']),
        'phone' => $_POST['phone'],
        'email' => $_POST['email'],
        'address' => $_POST['address'],
        'hours' => $_POST['hours'],
        'social_instagram' => $_POST['social_instagram'],
        'social_facebook' => $_POST['social_facebook'],
        'social_youtube' => $_POST['social_youtube'],
        'footer_text' => $_POST['footer_text'],
        'slider_speed' => $_POST['slider_speed'] ?? 4000,

        // Theme Settings
        'theme_mode' => $_POST['theme_mode'] ?? 'dark',
        'color_primary' => $_POST['color_primary'] ?? '#ffb703',
        'color_bg' => $_POST['color_bg'] ?? '#05070a',
        'color_card' => $_POST['color_card'] ?? '#11161f',
        'color_text' => $_POST['color_text'] ?? '#f8f9fa',

        'policy_privacy' => $_POST['policy_privacy'],
        'policy_terms' => $_POST['policy_terms'],
        'policy_about' => $_POST['policy_about'],
        'enable_pix' => isset($_POST['enable_pix']) ? 1 : 0,
        'enable_card' => isset($_POST['enable_card']) ? 1 : 0,
        'enable_wholesale' => isset($_POST['enable_wholesale']) ? 1 : 0,
        'api_token' => $_POST['api_token'] ?? ''
    ];

    file_put_contents($file, json_encode($newData, JSON_PRETTY_PRINT));
    $data = $newData;
    $msg = "✅ Configurações salvas com sucesso!";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Configurações da Loja | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function aiAutoFill() {
            let text = document.getElementById('ai_input').value;
            if (!text) return alert('Cole o texto primeiro!');

            // Simple Regex "AI" Parser

            // Email
            let email = text.match(/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/);
            if (email) document.getElementsByName('email')[0].value = email[0];

            // Phone (generic match)
            let phone = text.match(/(\(?\d{2}\)?\s?\d{4,5}-?\d{4})/);
            if (phone) {
                document.getElementsByName('phone')[0].value = phone[0];
                document.getElementsByName('whatsapp')[0].value = phone[0].replace(/\D/g, '');
            }

            // Address (heuristic: Look for Av/Rua)
            let address = text.match(/(Av\.|Rua|Alameda|Travessa).*?\d+/i);
            if (address) document.getElementsByName('address')[0].value = address[0]; // Simple capture

            // Socials (Basic keywords)
            if (text.toLowerCase().includes('instagram')) document.getElementsByName('social_instagram')[0].value = 'https://instagram.com/fightarcade';
            if (text.toLowerCase().includes('facebook')) document.getElementsByName('social_facebook')[0].value = 'https://facebook.com/fightarcade';

            alert('🤖 A IA preencheu os campos detectados!');
        }
    </script>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:800px; margin:0 auto;">
            <div style="display:flex; justify-content:space-between;">
                <h2>⚙️ Configurações Gerais</h2>
            </div>

            <?php if (isset($msg))
                echo "<div class='alert alert-success'>$msg</div>"; ?>

            <!-- AI Section -->
            <div
                style="background:#1a1a1a; padding:1rem; border-radius:8px; margin-bottom:2rem; border:1px solid #333;">
                <h4 style="color:var(--primary);">🤖 Assistente IA de Preenchimento</h4>
                <p style="font-size:0.9rem; color:#ccc;">Cole um texto com os dados da loja (e-mail, telefone, endereço)
                    e a IA vai tentar preencher o formulário.</p>
                <textarea id="ai_input" rows="3"
                    placeholder="Ex: Contato: loja@email.com | Tel: (11) 99999-9999 | Endereço: Av Paulista 1000..."
                    style="width:100%; margin-bottom:10px;"></textarea>
                <button type="button" onclick="aiAutoFill()" class="btn btn-sm"
                    style="background:var(--accent); color:#000;">🪄 Preencher Automaticamente</button>
            </div>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div>
                        <label>Nome da Loja</label>
                        <input type="text" name="store_name"
                            value="<?php echo htmlspecialchars($data['store_name']); ?>">
                    </div>
                    <div>
                        <label>E-mail de Contato</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div>
                        <label>WhatsApp (Apenas Números)</label>
                        <input type="text" name="whatsapp" value="<?php echo htmlspecialchars($data['whatsapp']); ?>">
                    </div>
                    <div>
                        <label>Telefone Fixo / SAC</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($data['phone']); ?>">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                    <div>
                        <label>Endereço Completo</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($data['address']); ?>">
                    </div>
                    <div>
                        <label>Horário de Atendimento</label>
                        <input type="text" name="hours" placeholder="Seg-Sex 9h às 18h"
                            value="<?php echo htmlspecialchars($data['hours']); ?>">
                    </div>
                </div>

                <label>Texto do Rodapé (Slogan)</label>
                <input type="text" name="footer_text" value="<?php echo htmlspecialchars($data['footer_text']); ?>">

                <h4 style="margin-top:2rem; border-bottom:1px solid #333; padding-bottom:5px;">Redes Sociais (Links)
                </h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
                    <div>
                        <label>Instagram</label>
                        <input type="text" name="social_instagram" placeholder="https://instagram.com/..."
                            value="<?php echo htmlspecialchars($data['social_instagram']); ?>">
                    </div>
                    <div>
                        <label>Facebook</label>
                        <input type="text" name="social_facebook" placeholder="https://facebook.com/..."
                            value="<?php echo htmlspecialchars($data['social_facebook']); ?>">
                    </div>
                    <div>
                        <label>YouTube</label>
                        <input type="text" name="social_youtube" placeholder="https://youtube.com/..."
                            value="<?php echo htmlspecialchars($data['social_youtube']); ?>">
                    </div>
                </div>

                <h4 style="margin-top:2rem; border-bottom:1px solid #333; padding-bottom:5px;">Aparência & Banners</h4>
        </div>

        <!-- THEME SETTINGS -->
        <h4 style="margin-top:2rem; border-bottom:1px solid #333; padding-bottom:5px;">🎨 Temas & Cores</h4>

        <div style="background:#1a1a1a; padding:1.5rem; border-radius:8px; border:1px solid #333;">
            <div style="margin-bottom:1.5rem;">
                <label>Modo de Cor (Base)</label>
                <select name="theme_mode" id="themeMode"
                    style="width:100%; padding:10px; background:#222; color:#fff; border:1px solid #444;"
                    onchange="togglePresets()">
                    <option value="dark" <?php echo ($data['theme_mode'] ?? 'dark') === 'dark' ? 'selected' : ''; ?>>🌑
                        Dark (Padrão)</option>
                    <option value="light" <?php echo ($data['theme_mode'] ?? 'dark') === 'light' ? 'selected' : ''; ?>>☀️
                        Light (Claro)</option>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <label>Cor Primária (Destaque)</label>
                    <input type="color" name="color_primary" id="col_primary"
                        value="<?php echo $data['color_primary'] ?? '#ffb703'; ?>" style="width:100%; height:40px;">
                </div>
                <div>
                    <label>Cor de Fundo (Background)</label>
                    <input type="color" name="color_bg" id="col_bg"
                        value="<?php echo $data['color_bg'] ?? '#05070a'; ?>" style="width:100%; height:40px;">
                </div>
                <div>
                    <label>Cor dos Cards/Módulos</label>
                    <input type="color" name="color_card" id="col_card"
                        value="<?php echo $data['color_card'] ?? '#11161f'; ?>" style="width:100%; height:40px;">
                </div>
                <div>
                    <label>Cor do Texto Principal</label>
                    <input type="color" name="color_text" id="col_text"
                        value="<?php echo $data['color_text'] ?? '#f8f9fa'; ?>" style="width:100%; height:40px;">
                </div>
            </div>

            <div style="border-top:1px solid #333; paddingTop:1rem;">
                <label style="display:block; margin-bottom:10px;">Predefinições (Clique para Aplicar):</label>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="btn" style="background:#000; color:#fff;"
                        onclick="applyPreset('fight-arcade')">Fight Arcade (Original)</button>
                    <button type="button" class="btn" style="background:#ffee32; color:#000;"
                        onclick="applyPreset('cyberpunk')">Cyberpunk Yellow</button>
                    <button type="button" class="btn" style="background:#ccc; color:#000;"
                        onclick="applyPreset('clean-white')">Clean White</button>
                    <button type="button" class="btn" style="background:#5a189a; color:#fff;"
                        onclick="applyPreset('midnight-purple')">Midnight Purple</button>
                    <button type="button" class="btn" style="background:#ff6d00; color:#fff;"
                        onclick="applyPreset('laranja-arcade')">🟠 Laranja</button>
                </div>
            </div>
        </div>

        <script>
            function applyPreset(type) {
                const inputs = {
                    'fight-arcade': { m: 'dark', p: '#ffb703', b: '#05070a', c: '#11161f', t: '#f8f9fa' },
                    'cyberpunk': { m: 'dark', p: '#00ffea', b: '#0a0a12', c: '#14141f', t: '#ff00ff' }, // Neon mix
                    'midnight-purple': { m: 'dark', p: '#00e676', b: '#120024', c: '#240046', t: '#e0e0e0' },
                    'clean-white': { m: 'light', p: '#007bff', b: '#f8f9fa', c: '#ffffff', t: '#212529' },
                    'laranja-arcade': { m: 'light', p: '#ff6d00', b: '#ffffff', c: '#f5f5f5', t: '#000000' }
                };

                const t = inputs[type];
                if (t) {
                    document.getElementById('themeMode').value = t.m;
                    document.getElementById('col_primary').value = t.p;
                    document.getElementById('col_bg').value = t.b;
                    document.getElementById('col_card').value = t.c;
                    document.getElementById('col_text').value = t.t;
                } else {
                    console.error('Preset not found:', type);
                }
            }
        </script>

        <h4 style="margin-top:2rem; border-bottom:1px solid #333; padding-bottom:5px;">Políticas & Termos
            (Popups)</h4>
        <div style="margin-bottom:1rem;">
            <label>Política de Privacidade</label>
            <textarea name="policy_privacy" rows="5"
                style="width:100%;"><?php echo htmlspecialchars($data['policy_privacy'] ?? ''); ?></textarea>
        </div>
        <div style="margin-bottom:1rem;">
            <label>Termos de Uso / Trocas</label>
            <textarea name="policy_terms" rows="5"
                style="width:100%;"><?php echo htmlspecialchars($data['policy_terms'] ?? ''); ?></textarea>
        </div>
        <div style="margin-bottom:1rem;">
            <label>Quem Somos (Institucional)</label>
            <textarea name="policy_about" rows="5"
                style="width:100%;"><?php echo htmlspecialchars($data['policy_about'] ?? ''); ?></textarea>
        </div>

        <h4 style="margin-top:2rem; border-bottom:1px solid #333; padding-bottom:5px;">Pagamento (Exibir Selos)
        </h4>
        <div
            style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1.5rem; background:#111; padding:1.5rem; border-radius:8px; border:1px solid #333;">
            <label style="display:flex; align-items:center; cursor:pointer;">
                <input type="checkbox" name="enable_pix" value="1" <?php echo $data['enable_pix'] ? 'checked' : ''; ?>>
                <span style="margin-left:8px;">💳 Selo PIX</span>
            </label>
            <label style="display:flex; align-items:center; cursor:pointer;">
                <input type="checkbox" name="enable_card" value="1" <?php echo $data['enable_card'] ? 'checked' : ''; ?>>
                <span style="margin-left:8px;">💳 Selo Cartão</span>
            </label>
            <label
                style="display:flex; align-items:center; cursor:pointer; background:rgba(255,183,3,0.1); padding:5px 10px; border-radius:4px; border:1px solid var(--primary);">
                <input type="checkbox" name="enable_wholesale" value="1" <?php echo ($data['enable_wholesale'] ?? 1) ? 'checked' : ''; ?>>
                <span style="margin-left:8px; font-weight:bold; color:var(--primary);">🛒 ATIVAR ATACADO</span>
            </label>
        </div>

        <h4 style="margin-top:2rem; border-bottom:1px solid #333; padding-bottom:5px;">🔌 Integração & API do Catálogo</h4>
        
        <div style="background:#1a1e26; padding:1.5rem; border-radius:8px; border:1px solid #333; margin-bottom:1.5rem;">
            <p style="font-size:0.9rem; color:#ccc; margin-top:0;">Use o token de API abaixo para conectar o catálogo com ERPs externos (Bling, Tiny, etc.) ou outros sistemas para controlar estoque, preços e pedidos.</p>
            
            <div style="margin-bottom:1.5rem;">
                <label style="display:block; margin-bottom:8px; font-weight:bold; color:var(--primary);">Token de API:</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" name="api_token" id="apiTokenField" value="<?php echo htmlspecialchars($data['api_token'] ?? ''); ?>" style="flex:1; padding:10px; background:#111; color:#fff; border:1px solid #444; border-radius:6px;" readonly placeholder="Nenhum token gerado">
                    <button type="button" onclick="generateApiToken()" class="btn" style="background:#3498db; color:#fff; padding:10px 15px; border-radius:6px; font-weight:bold;">Gerar Novo Token</button>
                </div>
            </div>

            <div style="border-top:1px solid #333; padding-top:1.5rem;">
                <h5 style="color:#fff; margin:0 0 10px 0; font-size:0.95rem;">Exemplos de Integração:</h5>
                <div style="background:#111; padding:15px; border-radius:6px; border:1px solid #222; font-family:monospace; font-size:0.8rem; overflow-x:auto; max-height:250px;">
                    <strong>cURL (Listar Produtos):</strong><br>
                    <span style="color:#2ecc71;">curl -H "Authorization: Bearer <span id="curlToken"><?php echo htmlspecialchars($data['api_token'] ?: 'SEU_TOKEN'); ?></span>" <?php echo BASE_URL; ?>/api/v1.php?endpoint=products</span>
                    
                    <br><br>
                    <strong>Python (Atualizar Estoque):</strong><br>
                    <span style="color:#e67e22;">
import requests<br>
url = "<?php echo BASE_URL; ?>/api/v1.php?endpoint=stock"<br>
headers = {"Authorization": "Bearer <?php echo htmlspecialchars($data['api_token'] ?: 'SEU_TOKEN'); ?>"}<br>
payload = { "sku": "COMANDO-SANWA", "qty": 15, "type": "set" }<br>
response = requests.post(url, json=payload, headers=headers)<br>
print(response.json())
                    </span>
                </div>
            </div>
        </div>

        <script>
        function generateApiToken() {
            if (confirm("Gerar um novo token de API? O token anterior deixará de funcionar imediatamente.")) {
                const array = new Uint32Array(4);
                window.crypto.getRandomValues(array);
                let token = "";
                for (let i = 0; i < array.length; i++) {
                    token += array[i].toString(16).padStart(8, '0');
                }
                document.getElementById('apiTokenField').value = token;
                const curlTk = document.getElementById('curlToken');
                if (curlTk) curlTk.innerText = token;
                alert("Novo token gerado! Clique em 'Salvar Configurações' para persistir a alteração.");
            }
        }
        </script>

        <div style="margin-top:2rem; text-align:right;">
            <button type="submit" class="btn">💾 Salvar Configurações</button>
        </div>
        </form>
    </div>
    </div>
</body>

</html>