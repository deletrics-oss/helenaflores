<?php
/**
 * my-addresses.php — Helena Flores (Meus Endereços do Cliente com ViaCEP)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// 1. Check Authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $baseUrl . "/login.php?redirect=my-addresses.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Auto-migration for user_addresses table columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) DEFAULT 'Endereço Principal',
        recipient_name VARCHAR(255) DEFAULT '',
        zipcode VARCHAR(20) DEFAULT '',
        address VARCHAR(255) DEFAULT '',
        number VARCHAR(50) DEFAULT '',
        complement VARCHAR(100) DEFAULT '',
        neighborhood VARCHAR(100) DEFAULT '',
        city VARCHAR(100) DEFAULT 'São Paulo',
        state VARCHAR(10) DEFAULT 'SP',
        is_default TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

// Handle Delete Address
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['address_id'];
    $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    $success = "Endereço removido com sucesso.";
}

// Handle Set Default Address
if (isset($_POST['action']) && $_POST['action'] === 'set_default') {
    $id = (int)$_POST['address_id'];
    try {
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
        $success = "Endereço principal atualizado.";
    } catch (Exception $e) {
        $error = "Erro ao definir endereço padrão.";
    }
}

// Handle Add / Edit Address
if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
    $name = trim($_POST['name'] ?? 'Endereço de Entrega');
    $recipient = trim($_POST['recipient_name'] ?? '');
    $zip = trim($_POST['zipcode'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $num = trim($_POST['number'] ?? '');
    $comp = trim($_POST['complement'] ?? '');
    $bairro = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? 'São Paulo');
    $uf = trim($_POST['state'] ?? 'SP');

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
    $countStmt->execute([$user_id]);
    $count = $countStmt->fetchColumn();
    $is_default = ($count == 0) ? 1 : 0;

    if (!empty($zip) && !empty($addr) && !empty($num) && !empty($bairro)) {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO user_addresses (user_id, name, recipient_name, zipcode, address, number, complement, neighborhood, city, state, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$user_id, $name, $recipient, $zip, $addr, $num, $comp, $bairro, $city, $uf, $is_default]);
            $success = "Endereço cadastrado com sucesso!";
        } else {
            $id = (int)$_POST['address_id'];
            $sql = "UPDATE user_addresses SET name=?, recipient_name=?, zipcode=?, address=?, number=?, complement=?, neighborhood=?, city=?, state=? WHERE id=? AND user_id=?";
            $pdo->prepare($sql)->execute([$name, $recipient, $zip, $addr, $num, $comp, $bairro, $city, $uf, $id, $user_id]);
            $success = "Endereço atualizado com sucesso!";
        }
    } else {
        $error = "Por favor, preencha todos os campos obrigatórios de endereço.";
    }
}

// Fetch Customer Addresses
$stmtAddrs = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmtAddrs->execute([$user_id]);
$addresses = $stmtAddrs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Endereços | Helena Flores</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/helena_theme.css?v=<?php echo time(); ?>">
    <style>
        .addr-card {
            background: #FFF; border: 1px solid #EEE; border-radius: 16px; padding: 1.5rem;
            margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); position: relative;
        }
        .addr-card.default { border-color: var(--gf-magenta); background: #FFF9FA; }
        .badge-default {
            background: var(--gf-magenta); color: #FFF; padding: 4px 12px; border-radius: 12px;
            font-size: 0.75rem; font-weight: bold; position: absolute; top: 15px; right: 15px;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-weight: 700; font-size: 0.88rem; color: #444; margin-bottom: 6px; }
        .form-control {
            width: 100%; height: 45px; border-radius: 10px; border: 1px solid #DDD; padding: 0 14px;
            font-size: 0.95rem; box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--gf-magenta); outline: none; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div style="max-width:1240px; margin: 2rem auto; padding: 0 20px; flex:1;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap:wrap; gap:15px;">
            <div>
                <h1 style="font-size:1.8rem; font-weight:800; color:var(--gf-magenta-dark); margin:0;">
                    📍 Meus Endereços de Entrega
                </h1>
                <p style="color:#666; font-size:0.9rem; margin-top:4px;">
                    Gerencie seus endereços para entregas rápidas na Helena Flores.
                </p>
            </div>
            <a href="<?php echo $baseUrl; ?>/my-orders.php" style="color:var(--gf-magenta); font-weight:bold; text-decoration:none; font-size:0.9rem;">
                ← Voltar para Meus Pedidos
            </a>
        </div>

        <?php if ($success): ?>
            <div style="background:#E8F5E9; color:#2E7D32; padding:12px 18px; border-radius:10px; margin-bottom:1.5rem; font-weight:bold;">
                ✅ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FFEBEE; color:#C2185B; padding:12px 18px; border-radius:10px; margin-bottom:1.5rem; font-weight:bold;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;" class="form-grid">
            
            <!-- Left: List of Saved Addresses -->
            <div>
                <h2 style="font-size:1.2rem; font-weight:800; color:#333; margin-bottom:1.2rem;">
                    🏠 Endereços Cadastrados
                </h2>

                <?php if (empty($addresses)): ?>
                    <div style="background:#FFF; border:1px solid #EEE; border-radius:14px; padding:2rem; text-align:center; color:#777;">
                        Nenhum endereço cadastrado ainda. Use o formulário ao lado para adicionar seu endereço com busca automática por CEP!
                    </div>
                <?php else: ?>
                    <?php foreach ($addresses as $a): ?>
                        <div class="addr-card <?php echo $a['is_default'] ? 'default' : ''; ?>">
                            <?php if ($a['is_default']): ?>
                                <span class="badge-default">Endereço Principal</span>
                            <?php endif; ?>
                            
                            <strong style="font-size:1.05rem; color:#222; display:block; margin-bottom:6px;">
                                <?php echo htmlspecialchars($a['name']); ?>
                            </strong>
                            
                            <div style="color:#555; font-size:0.9rem; line-height:1.5;">
                                <?php echo htmlspecialchars($a['address']); ?>, nº <?php echo htmlspecialchars($a['number']); ?>
                                <?php echo !empty($a['complement']) ? ' (' . htmlspecialchars($a['complement']) . ')' : ''; ?><br>
                                Bairro <?php echo htmlspecialchars($a['neighborhood']); ?> — <?php echo htmlspecialchars($a['city'] . '/' . $a['state']); ?><br>
                                <strong>CEP: <?php echo htmlspecialchars($a['zipcode']); ?></strong>
                            </div>

                            <div style="margin-top:1rem; display:flex; gap:12px; align-items:center;">
                                <?php if (!$a['is_default']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="address_id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" style="background:none; border:none; color:var(--gf-magenta); font-weight:bold; cursor:pointer; font-size:0.85rem; padding:0;">
                                            ⭐ Definir como Principal
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="address_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" onclick="return confirm('Excluir este endereço?')" 
                                            style="background:none; border:none; color:#D32F2F; font-weight:bold; cursor:pointer; font-size:0.85rem; padding:0;">
                                        🗑️ Excluir
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Right: Add New Address Form with ViaCEP Auto-fill -->
            <div style="background:#FFF; border:1px solid #EEE; border-radius:16px; padding:1.8rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
                <h2 style="font-size:1.2rem; font-weight:800; color:var(--gf-magenta-dark); margin-bottom:1.2rem;">
                    ➕ Novo Endereço (Busca via CEP)
                </h2>

                <form method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="form-group">
                        <label>Nome do Local (ex: Minha Casa, Trabalho)</label>
                        <input type="text" name="name" class="form-control" value="Minha Casa" required>
                    </div>

                    <div class="form-group">
                        <label>CEP (Auto-preenchimento instantâneo)</label>
                        <input type="text" name="zipcode" id="zipcode" class="form-control" placeholder="01420-001" onblur="fetchViaCEP(this.value)" required>
                        <small id="cepStatus" style="color:var(--gf-magenta); font-weight:bold; font-size:0.8rem; margin-top:4px; display:block;"></small>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Rua / Endereço *</label>
                            <input type="text" name="address" id="address" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Número *</label>
                            <input type="text" name="number" id="number" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Bairro *</label>
                            <input type="text" name="neighborhood" id="neighborhood" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Complemento / Apto</label>
                            <input type="text" name="complement" class="form-control">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Cidade</label>
                            <input type="text" name="city" id="city" class="form-control" value="São Paulo" required>
                        </div>
                        <div class="form-group">
                            <label>Estado (UF)</label>
                            <input type="text" name="state" id="state" class="form-control" value="SP" required>
                        </div>
                    </div>

                    <button type="submit" class="gf-btn-buy" style="width:100%; height:48px; border-radius:24px; font-size:1.05rem; font-weight:bold; border:none; cursor:pointer; margin-top:10px;">
                        SALVAR ENDEREÇO 🌸
                    </button>
                </form>
            </div>

        </div>

    </div>

    <!-- ViaCEP JavaScript Auto-fill Script -->
    <script>
        function fetchViaCEP(cepRaw) {
            const cep = cepRaw.replace(/\D/g, '');
            const statusEl = document.getElementById('cepStatus');

            if (cep.length === 8) {
                statusEl.innerText = '🔍 Buscando endereço...';
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('address').value = data.logradouro || '';
                            document.getElementById('neighborhood').value = data.bairro || '';
                            document.getElementById('city').value = data.localidade || 'São Paulo';
                            document.getElementById('state').value = data.uf || 'SP';
                            statusEl.innerText = '✅ Endereço localizado automaticamente!';
                            document.getElementById('number').focus();
                        } else {
                            statusEl.innerText = '⚠️ CEP não encontrado. Preencha manualmente.';
                        }
                    })
                    .catch(() => {
                        statusEl.innerText = '⚠️ Erro ao consultar ViaCEP.';
                    });
            }
        }
    </script>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>

</body>
</html>