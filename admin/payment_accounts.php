<?php
/**
 * admin/payment_accounts.php — Fight Arcade
 * Gestão de Contas de Recebimento (PIX / Bancário)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$success = '';
$error   = '';

// 1. Processa Exclusão
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $pdo->prepare("DELETE FROM payment_accounts WHERE id = ?")->execute([$id]);
    header("Location: payment_accounts.php?msg=deleted");
    exit;
}

// 2. Processa Cadastro/Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id'] ?? null;
    $name     = $_POST['name'] ?? '';
    $type     = $_POST['type'] ?? 'pix';
    $pix_key  = $_POST['pix_key'] ?? '';
    $bank_info= $_POST['bank_info'] ?? '';

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE payment_accounts SET name=?, type=?, pix_key=?, bank_info=? WHERE id=?");
            $stmt->execute([$name, $type, $pix_key, $bank_info, $id]);
            $success = "Conta atualizada com sucesso!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO payment_accounts (name, type, pix_key, bank_info) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $type, $pix_key, $bank_info]);
            $success = "Nova conta cadastrada!";
        }
    } catch (Exception $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

try {
    $accounts = $pdo->query("SELECT * FROM payment_accounts ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $accounts = [];
    $error = "A tabela de contas ainda não existe. Por favor, vá em **Ferramentas -> Reparar Banco** para criá-la.";
}
$msg = $_GET['msg'] ?? '';
if ($msg === 'deleted') $success = "Conta removida com sucesso!";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contas de Recebimento — Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #f1c40f; --dark: #0f172a; }
        body { background: var(--dark); color: #f8fafc; font-family: 'Inter', sans-serif; }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .card { background: #1e293b; border-radius: 16px; padding: 25px; border: 1px solid #334155; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #94a3b8; font-size: 0.9rem; }
        input, textarea, select { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: white; transition: 0.3s; }
        .btn { padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: #000; }
        .acc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .acc-item { background: #1e293b; border-radius: 12px; padding: 20px; border: 1px solid #334155; position: relative; border-left: 4px solid var(--primary); }
        .acc-actions { position: absolute; top: 15px; right: 15px; display: flex; gap: 10px; }
        .tag { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; background: #334155; color: var(--primary); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: #065f46; color: #34d399; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h1><i class="fas fa-wallet"></i> Contas de Recebimento</h1>
            <a href="customers.php?filter=debtors" class="btn" style="background:#334155; color:white;"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <?php if($success): ?> <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>
        <?php if($error): ?> <div class="alert" style="background:#7f1d1d; color:#f87171;"><?php echo $error; ?></div> <?php endif; ?>

        <div class="card">
            <h2 id="form-title" style="margin-top:0;"><?php echo isset($_GET['edit']) ? '✏️ Editar Conta' : '➕ Nova Conta de Recebimento'; ?></h2>
            <?php 
            $edit = null;
            if (isset($_GET['edit'])) {
                $eid = (int)$_GET['edit'];
                foreach ($accounts as $a) if ($a['id'] == $eid) $edit = $a;
            }
            ?>
            <form method="POST" action="payment_accounts.php">
                <?php if($edit): ?> <input type="hidden" name="id" value="<?php echo $edit['id']; ?>"> <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom:15px;">
                    <div class="form-group">
                        <label>Nome Amigável (Ex: PIX Principal - CNPJ)</label>
                        <input type="text" name="name" value="<?php echo $edit['name'] ?? ''; ?>" required placeholder="Identificação da conta">
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="type">
                            <option value="pix" <?php echo ($edit['type'] ?? '') == 'pix' ? 'selected' : ''; ?>>Chave PIX</option>
                            <option value="bank" <?php echo ($edit['type'] ?? '') == 'bank' ? 'selected' : ''; ?>>Dados Bancários</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Chave PIX (Se for PIX)</label>
                    <input type="text" name="pix_key" value="<?php echo $edit['pix_key'] ?? ''; ?>" placeholder="E-mail, CPF, CNPJ ou Aleatória">
                </div>

                <div class="form-group">
                    <label>Dados Bancários / Observações (Opcional)</label>
                    <textarea name="bank_info" rows="3" placeholder="Agência, Conta, Titular, Instruções..."><?php echo $edit['bank_info'] ?? ''; ?></textarea>
                </div>

                <div style="text-align: right;">
                    <?php if($edit): ?> <a href="payment_accounts.php" class="btn" style="background:#334155; color:white;">Cancelar</a> <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Conta</button>
                </div>
            </form>
        </div>

        <div class="acc-grid">
            <?php foreach($accounts as $acc): ?>
            <div class="acc-item">
                <div class="acc-actions">
                    <a href="?edit=<?php echo $acc['id']; ?>" style="color:#94a3b8;"><i class="fas fa-edit"></i></a>
                    <a href="?del=<?php echo $acc['id']; ?>" style="color:#ef4444;" onclick="return confirm('Excluir esta conta?')"><i class="fas fa-trash"></i></a>
                </div>
                <span class="tag"><?php echo strtoupper($acc['type']); ?></span>
                <h3 style="margin:10px 0 5px 0; color:var(--primary);"><?php echo htmlspecialchars($acc['name']); ?></h3>
                <?php if($acc['pix_key']): ?>
                    <p style="margin:0; font-size:0.9rem; color:#fff;"><strong>PIX:</strong> <?php echo htmlspecialchars($acc['pix_key']); ?></p>
                <?php endif; ?>
                <?php if($acc['bank_info']): ?>
                    <div style="font-size:0.8rem; color:#94a3b8; margin-top:10px; border-top:1px solid #334155; padding-top:10px;">
                        <?php echo nl2br(htmlspecialchars($acc['bank_info'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
