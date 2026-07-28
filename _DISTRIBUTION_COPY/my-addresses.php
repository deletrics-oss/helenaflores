<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';

checkUser();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Delete
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int) $_POST['address_id'];
    $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    $success = "Endereço removido com sucesso.";
}

// Handle Set Default
if (isset($_POST['action']) && $_POST['action'] === 'set_default') {
    $id = (int) $_POST['address_id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
        $pdo->commit();
        $success = "Endereço padrão atualizado.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao definir padrão.";
    }
}

// Handle Add/Edit
if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
    $name = trim($_POST['name']);
    $recipient = trim($_POST['recipient_name']);
    $zip = trim($_POST['zipcode']);
    $addr = trim($_POST['address']);
    $num = trim($_POST['number']);
    $comp = trim($_POST['complement']);
    $bairro = trim($_POST['neighborhood']);
    $city = trim($_POST['city']);
    $uf = trim($_POST['state']);

    // Check if it's the first address, if so, make it default
    $count = $pdo->query("SELECT count(*) FROM user_addresses WHERE user_id = $user_id")->fetchColumn();
    $is_default = ($count == 0) ? 1 : 0;

    if ($name && $zip && $addr && $num && $bairro && $city && $uf) {
        if ($_POST['action'] === 'add') {
            $sql = "INSERT INTO user_addresses (user_id, name, recipient_name, zipcode, address, number, complement, neighborhood, city, state, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$user_id, $name, $recipient, $zip, $addr, $num, $comp, $bairro, $city, $uf, $is_default]);
            $success = "Endereço adicionado com sucesso!";
        } else {
            $id = (int) $_POST['address_id'];
            $sql = "UPDATE user_addresses SET name=?, recipient_name=?, zipcode=?, address=?, number=?, complement=?, neighborhood=?, city=?, state=? WHERE id=? AND user_id=?";
            $pdo->prepare($sql)->execute([$name, $recipient, $zip, $addr, $num, $comp, $bairro, $city, $uf, $id, $user_id]);
            $success = "Endereço atualizado com sucesso!";
        }
    } else {
        $error = "Preencha todos os campos obrigatórios.";
    }
}

// Fetch Addresses
$addresses = $pdo->query("SELECT * FROM user_addresses WHERE user_id = $user_id ORDER BY is_default DESC, created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Endereços | Fight Arcade</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=1.3">
    <style>
        .addr-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            position: relative;
        }

        .addr-card.default {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(0, 255, 136, 0.2);
        }

        .badge-default {
            background: var(--primary);
            color: #000;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .addr-actions {
            margin-top: 1rem;
            display: flex;
            gap: 10px;
            border-top: 1px solid #333;
            padding-top: 10px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 999;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #222;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <?php include __DIR__ . '/includes/header_public.php'; ?>

    <div class="container" style="padding: 2rem 0 4rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <h1 style="color:var(--primary); margin:0;">📍 Meus Endereços</h1>
            <button onclick="openModal('add')" class="btn">Novo Endereço</button>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:1rem;">
            <?php foreach ($addresses as $addr): ?>
                <div class="addr-card <?php echo $addr['is_default'] ? 'default' : ''; ?>">
                    <?php if ($addr['is_default']): ?><span class="badge-default">PADRÃO</span>
                    <?php endif; ?>
                    <h3 style="margin:0 0 0.5rem; font-size:1.1rem;">
                        <?php echo htmlspecialchars($addr['name']); ?>
                        <small style="font-weight:normal; color:#888;">(
                            <?php echo htmlspecialchars($addr['recipient_name']); ?>)
                        </small>
                    </h3>
                    <p style="font-size:0.9rem; color:#ccc; margin-bottom:5px;">
                        <?php echo htmlspecialchars($addr['address']); ?>,
                        <?php echo htmlspecialchars($addr['number']); ?>
                        <?php echo $addr['complement'] ? ' - ' . htmlspecialchars($addr['complement']) : ''; ?>
                    </p>
                    <p style="font-size:0.9rem; color:#ccc;">
                        <?php echo htmlspecialchars($addr['neighborhood']); ?> -
                        <?php echo htmlspecialchars($addr['city']); ?>/
                        <?php echo htmlspecialchars($addr['state']); ?><br>
                        CEP:
                        <?php echo htmlspecialchars($addr['zipcode']); ?>
                    </p>

                    <div class="addr-actions">
                        <button onclick='editAddress(<?php echo json_encode($addr); ?>)' class="btn-sm"
                            style="background:#333;">Editar</button>

                        <?php if (!$addr['is_default']): ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="set_default">
                                <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                <button class="btn-sm" style="background:#555;">Definir Padrão</button>
                            </form>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Tem certeza?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                <button class="btn-sm btn-danger">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($addresses)): ?>
                <p>Nenhum endereço cadastrado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL FORM -->
    <div id="addrModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Novo Endereço</h2>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="address_id" id="addrId">

                <label>Nome do Local (Ex: Casa, Trabalho)</label>
                <input type="text" name="name" id="f_name" required placeholder="Minha Casa">

                <label>Nome do Destinatário</label>
                <input type="text" name="recipient_name" id="f_recipient" required placeholder="Quem vai receber?">

                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                    <div>
                        <label>CEP</label>
                        <input type="text" name="zipcode" id="f_zip" required onblur="fetchCep(this.value)">
                    </div>
                    <div>
                        <label>Rua</label>
                        <input type="text" name="address" id="f_addr" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div>
                        <label>Número</label>
                        <input type="text" name="number" id="f_num" required>
                    </div>
                    <div>
                        <label>Complemento</label>
                        <input type="text" name="complement" id="f_comp">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                    <div>
                        <label>Bairro</label>
                        <input type="text" name="neighborhood" id="f_bairro" required>
                    </div>
                    <div>
                        <label>Cidade</label>
                        <input type="text" name="city" id="f_city" required>
                    </div>
                    <div>
                        <label>UF</label>
                        <input type="text" name="state" id="f_state" required maxlength="2">
                    </div>
                </div>

                <button type="submit" class="btn" style="width:100%; margin-top:1rem;">Salvar Endereço</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(mode) {
            document.getElementById('addrModal').style.display = 'flex';
            if (mode === 'add') {
                document.getElementById('modalTitle').innerText = 'Novo Endereço';
                document.getElementById('formAction').value = 'add';
                document.getElementById('addrId').value = '';
                // clear fields
                document.querySelectorAll('#addrModal input').forEach(i => { if (i.type != 'hidden') i.value = ''; });
            }
        }

        function closeModal() {
            document.getElementById('addrModal').style.display = 'none';
        }

        function editAddress(addr) {
            openModal('edit');
            document.getElementById('modalTitle').innerText = 'Editar Endereço';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('addrId').value = addr.id;

            document.getElementById('f_name').value = addr.name;
            document.getElementById('f_recipient').value = addr.recipient_name;
            document.getElementById('f_zip').value = addr.zipcode;
            document.getElementById('f_addr').value = addr.address;
            document.getElementById('f_num').value = addr.number;
            document.getElementById('f_comp').value = addr.complement;
            document.getElementById('f_bairro').value = addr.neighborhood;
            document.getElementById('f_city').value = addr.city;
            document.getElementById('f_state').value = addr.state;
        }

        function fetchCep(cep) {
            cep = cep.replace(/\D/g, '');
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('f_addr').value = data.logradouro;
                            document.getElementById('f_bairro').value = data.bairro;
                            document.getElementById('f_city').value = data.localidade;
                            document.getElementById('f_state').value = data.uf;
                            document.getElementById('f_num').focus();
                        }
                    });
            }
        }
    </script>

    <?php include __DIR__ . '/includes/footer_public.php'; ?>
</body>

</html>