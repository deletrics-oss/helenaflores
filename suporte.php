<?php
// catalogo/suporte.php - Public Replacement Parts Request Page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// Ensure RMA table exists with all columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rma_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('garantia','devolucao') DEFAULT 'garantia',
        customer_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        document VARCHAR(20) NULL,
        phone VARCHAR(20) NULL,
        address VARCHAR(255) NULL,
        number VARCHAR(20) NULL,
        complement VARCHAR(100) NULL,
        neighborhood VARCHAR(100) NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(2) NULL,
        zipcode VARCHAR(10) NULL,
        product_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        issue_type VARCHAR(100) NULL,
        issue_desc TEXT NULL,
        preferred_action ENUM('enviar_peca','trazer_loja') DEFAULT 'enviar_peca',
        status ENUM('pending','shipped','received','resolved') DEFAULT 'pending',
        source ENUM('admin','customer') DEFAULT 'admin',
        me_order_id VARCHAR(255) NULL,
        tracking_code VARCHAR(100) NULL,
        qty_returned INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL
    )");
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN email VARCHAR(255) NULL AFTER customer_name"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN source ENUM('admin','customer') DEFAULT 'admin' AFTER status"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE rma_tickets ADD COLUMN preferred_action ENUM('enviar_peca','trazer_loja') DEFAULT 'enviar_peca' AFTER issue_desc"); } catch(Exception $e) {}
} catch(Exception $e) {}

$msg = '';
$success = false;
$ticketId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $doc = preg_replace('/\D/', '', $_POST['document'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $zip = preg_replace('/\D/', '', $_POST['zipcode'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $num = trim($_POST['number'] ?? '');
    $comp = trim($_POST['complement'] ?? '');
    $bairro = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = strtoupper(trim($_POST['state'] ?? ''));
    $product = trim($_POST['product_name'] ?? '');
    $issueType = $_POST['issue_type'] ?? 'Outro';
    $issueDesc = trim($_POST['issue_desc'] ?? '');
    $action = $_POST['preferred_action'] ?? 'enviar_peca';

    // Validation
    if (empty($name) || empty($doc) || empty($phone) || empty($zip) || empty($addr) || empty($num) || empty($city) || empty($state) || empty($product)) {
        $msg = '<div class="alert alert-error">❌ Preencha todos os campos obrigatórios (*).</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO rma_tickets (type, customer_name, email, document, phone, address, number, complement, neighborhood, city, state, zipcode, product_name, issue_type, issue_desc, preferred_action, source) VALUES ('garantia', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer')");
            $stmt->execute([$name, $email, $doc, $phone, $addr, $num, $comp, $bairro, $city, $state, $zip, $product, $issueType, $issueDesc, $action]);
            $ticketId = $pdo->lastInsertId();
            $success = true;
        } catch(Exception $e) {
            $msg = '<div class="alert alert-error">Erro ao enviar solicitação. Tente novamente.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte & Peças de Reposição | Fight Arcade</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .suporte-hero { background:linear-gradient(135deg, #0a0e17 0%, #1a1e26 100%); padding:3rem 0; text-align:center; border-bottom:2px solid #f39c12; }
        .suporte-hero h1 { font-size:2rem; color:#f39c12; margin-bottom:.5rem; }
        .suporte-hero p { color:#888; max-width:600px; margin:0 auto; }
        .suporte-box { max-width:750px; margin:2rem auto; background:#11161f; border:1px solid #2b2d42; border-radius:16px; padding:2rem; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .form-row.col3 { grid-template-columns:1fr 1fr 1fr; }
        .form-group { margin-bottom:1rem; }
        .form-group label { display:block; color:#aaa; font-size:.85rem; margin-bottom:4px; }
        .form-group label .req { color:#e74c3c; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:12px; background:#0a0e17; border:1px solid #333; color:#fff; border-radius:8px; font-size:.95rem; height:auto; }
        .form-group textarea { min-height:80px; resize:vertical; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:#f39c12; outline:none; }
        .action-choice { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem; }
        .action-card { background:#0a0e17; border:2px solid #333; border-radius:12px; padding:1.5rem; text-align:center; cursor:pointer; transition:all .3s; }
        .action-card:hover { border-color:#f39c12; }
        .action-card.selected { border-color:#2ecc71; background:rgba(46,204,113,.05); }
        .action-card .icon { font-size:2rem; margin-bottom:.5rem; }
        .action-card h4 { color:#fff; margin-bottom:.3rem; font-family:'Inter',sans-serif; }
        .action-card p { color:#888; font-size:.8rem; }
        .submit-btn { width:100%; padding:16px; background:#2ecc71; color:#000; border:none; border-radius:10px; font-size:1.1rem; font-weight:bold; cursor:pointer; transition:all .3s; text-transform:uppercase; letter-spacing:1px; }
        .submit-btn:hover { background:#27ae60; transform:translateY(-2px); box-shadow:0 8px 25px rgba(46,204,113,.3); }
        .support-banner { background:linear-gradient(135deg,#1a1e26,#222); border:1px solid #333; border-radius:12px; padding:1.5rem; display:flex; align-items:center; gap:1rem; margin-bottom:2rem; }
        .support-banner .phone { font-size:1.3rem; font-weight:bold; color:#2ecc71; font-family:'Orbitron',sans-serif; }
        .success-card { text-align:center; padding:3rem 2rem; }
        .success-card .check { font-size:5rem; margin-bottom:1rem; }
        .success-card h2 { color:#2ecc71; margin-bottom:1rem; }
        .success-card .ticket-num { font-size:2rem; font-weight:bold; color:#f39c12; font-family:'Orbitron',sans-serif; }
        #cep-status { display:block; font-size:.8rem; margin-top:-8px; margin-bottom:8px; }
        .section-title { color:#f39c12; font-size:1rem; font-family:'Orbitron',sans-serif; border-bottom:1px solid #333; padding-bottom:.5rem; margin:1.5rem 0 1rem; }
        @media(max-width:600px) {
            .form-row, .form-row.col3, .action-choice { grid-template-columns:1fr; }
            .suporte-box { margin:1rem; padding:1.5rem; }
        }
    </style>
</head>
<body>
    <div class="suporte-hero">
        <div class="container">
            <a href="index.php" style="display:inline-block;margin-bottom:1rem;">
                <img src="assets/images/logo.png" alt="Fight Arcade" style="max-height:50px;margin:0 auto;" onerror="this.style.display='none'">
            </a>
            <h1><i class="fas fa-tools"></i> Suporte & Peças de Reposição</h1>
            <p>Preencha o formulário abaixo para solicitar peças de reposição, reparos ou devoluções. Nossa equipe cuidará de tudo para você!</p>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
        <div class="suporte-box">
            <div class="success-card">
                <div class="check">✅</div>
                <h2>Solicitação Enviada!</h2>
                <p style="color:#aaa;margin-bottom:1rem;">Sua solicitação foi registrada com sucesso.</p>
                <div>Protocolo:</div>
                <div class="ticket-num">#<?php echo $ticketId; ?></div>
                <p style="color:#888;margin-top:1.5rem;font-size:.9rem;">
                    Entraremos em contato pelo WhatsApp ou e-mail informado para dar andamento.<br>
                    Anote o número do protocolo para acompanhamento.
                </p>
                <div style="margin-top:2rem;">
                    <a href="https://api.whatsapp.com/send?phone=5511988121976&text=Olá! Meu protocolo é %23<?php echo $ticketId; ?>" target="_blank" style="display:inline-block;background:#25d366;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">
                        <i class="fab fa-whatsapp"></i> Falar no WhatsApp
                    </a>
                    <a href="suporte.php" style="display:inline-block;background:#333;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;margin-left:10px;">
                        Nova Solicitação
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>

        <div class="suporte-box">
            <div class="support-banner">
                <div style="font-size:2rem;">📞</div>
                <div>
                    <div style="color:#888;font-size:.85rem;">Suporte por WhatsApp</div>
                    <div class="phone">(11) 98812-1976</div>
                </div>
                <a href="https://api.whatsapp.com/send?phone=5511988121976&text=Olá! Preciso de suporte técnico." target="_blank" style="margin-left:auto;background:#25d366;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:.9rem;">
                    <i class="fab fa-whatsapp"></i> Chamar
                </a>
            </div>

            <?php echo $msg; ?>

            <form method="POST">
                <div class="section-title">👤 Seus Dados</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome Completo <span class="req">*</span></label>
                        <input type="text" name="customer_name" required placeholder="Seu nome completo" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" placeholder="seuemail@exemplo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>CPF ou CNPJ <span class="req">*</span></label>
                        <input type="text" name="document" required placeholder="000.000.000-00" value="<?php echo htmlspecialchars($_POST['document'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp / Telefone <span class="req">*</span></label>
                        <input type="text" name="phone" required placeholder="(00) 00000-0000" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="section-title">📍 Endereço de Entrega</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP <span class="req">*</span></label>
                        <input type="text" name="zipcode" id="f_zip" required placeholder="00000-000" oninput="buscarCep(this.value)" value="<?php echo htmlspecialchars($_POST['zipcode'] ?? ''); ?>">
                        <small id="cep-status" style="color:#888;"></small>
                    </div>
                    <div class="form-group">
                        <label>Rua / Logradouro <span class="req">*</span></label>
                        <input type="text" name="address" id="f_addr" required value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row col3">
                    <div class="form-group">
                        <label>Número <span class="req">*</span></label>
                        <input type="text" name="number" id="f_num" required value="<?php echo htmlspecialchars($_POST['number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complement" placeholder="Apto, Bloco, Sala..." value="<?php echo htmlspecialchars($_POST['complement'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="neighborhood" id="f_bairro" value="<?php echo htmlspecialchars($_POST['neighborhood'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade <span class="req">*</span></label>
                        <input type="text" name="city" id="f_city" required value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado (UF) <span class="req">*</span></label>
                        <input type="text" name="state" id="f_uf" maxlength="2" required placeholder="SP" value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
                    </div>
                </div>

                <div class="section-title">🔧 Peça / Reparo</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Produto / Peça <span class="req">*</span></label>
                        <input type="text" name="product_name" required placeholder="Ex: Botão Sanwa 30mm Vermelho" value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Motivo</label>
                        <select name="issue_type">
                            <option value="Peça de Reposição">Peça de Reposição</option>
                            <option value="Item Defeito (Fábrica)">Item com Defeito de Fábrica</option>
                            <option value="Item Quebrou (Transporte)">Quebrou no Transporte</option>
                            <option value="Faltou Item (Falta de Atenção)">Faltou Item no Pedido</option>
                            <option value="Troca/Arrependimento">Troca ou Arrependimento</option>
                            <option value="Reparo Técnico">Reparo Técnico</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Descreva o problema ou o que precisa</label>
                    <textarea name="issue_desc" placeholder="Ex: O botão parou de funcionar após 2 meses de uso. Preciso de reposição..."><?php echo htmlspecialchars($_POST['issue_desc'] ?? ''); ?></textarea>
                </div>

                <div class="section-title">📦 Como prefere resolver?</div>
                <div class="action-choice">
                    <div class="action-card selected" onclick="selectAction('enviar_peca', this)">
                        <div class="icon">📬</div>
                        <h4>Enviar Peça para Mim</h4>
                        <p>Receba a peça de reposição no seu endereço</p>
                    </div>
                    <div class="action-card" onclick="selectAction('trazer_loja', this)">
                        <div class="icon">🏪</div>
                        <h4>Levar à Loja</h4>
                        <p>Trago o produto para reparo na loja</p>
                    </div>
                </div>
                <input type="hidden" name="preferred_action" id="preferred_action" value="enviar_peca">

                <button type="submit" name="submit_request" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Enviar Solicitação
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <footer style="text-align:center;padding:2rem;color:#555;font-size:.8rem;border-top:1px solid #222;margin-top:3rem;">
        © <?php echo date('Y'); ?> Fight Arcade - Todos os direitos reservados.
    </footer>

    <script>
    function buscarCep(cep) {
        const clean = cep.replace(/\D/g, '');
        if (clean.length === 8) {
            document.getElementById('cep-status').innerText = 'Buscando...';
            document.getElementById('cep-status').style.color = '#888';
            fetch(`https://viacep.com.br/ws/${clean}/json/`)
                .then(r => r.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('f_addr').value = data.logradouro || '';
                        document.getElementById('f_bairro').value = data.bairro || '';
                        document.getElementById('f_city').value = data.localidade || '';
                        document.getElementById('f_uf').value = data.uf || '';
                        document.getElementById('f_num').focus();
                        document.getElementById('cep-status').innerText = '✅ ' + data.localidade + '/' + data.uf;
                        document.getElementById('cep-status').style.color = '#2ecc71';
                    } else {
                        document.getElementById('cep-status').innerText = '❌ CEP não encontrado';
                        document.getElementById('cep-status').style.color = '#e74c3c';
                    }
                })
                .catch(() => {
                    document.getElementById('cep-status').innerText = '❌ Erro ao buscar CEP';
                    document.getElementById('cep-status').style.color = '#e74c3c';
                });
        }
    }

    function selectAction(action, el) {
        document.querySelectorAll('.action-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('preferred_action').value = action;
    }
    </script>
</body>
</html>
