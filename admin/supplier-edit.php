<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$id = (int)$_GET['id'];
$supplier = $pdo->query("SELECT * FROM suppliers WHERE id = $id")->fetch();

if (!$supplier) {
    header("Location: suppliers.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $notes = $_POST['notes'];
    
    $catalog_path = $supplier['catalog_path'];
    if (!empty($_FILES['catalog']['name'])) {
        $ext = pathinfo($_FILES['catalog']['name'], PATHINFO_EXTENSION);
        $filename = 'catalog_' . time() . '_' . rand(100,999) . '.' . $ext;
        if (move_uploaded_file($_FILES['catalog']['tmp_name'], __DIR__ . '/../assets/uploads/' . $filename)) {
            $catalog_path = 'assets/uploads/' . $filename;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE suppliers SET name=?, contact_name=?, phone=?, address=?, email=?, lat=?, lng=?, notes=?, catalog_path=? WHERE id=?");
    $stmt->execute([$name, $contact, $phone, $address, $email, $lat, $lng, $notes, $catalog_path, $id]);
    header("Location: suppliers.php?msg=updated");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Fornecedor | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container" style="padding-top:2rem; max-width:800px;">
        <a href="suppliers.php" style="color:#888; text-decoration:none; font-size:0.9rem;">← Voltar para lista</a>
        <h1 style="margin-top:1rem; margin-bottom:2rem;">Editar Fornecedor: <?php echo htmlspecialchars($supplier['name']); ?></h1>

        <form method="POST" enctype="multipart/form-data" style="background:#161b22; padding:30px; border-radius:12px; border:1px solid #333;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div>
                    <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Nome da Empresa / Fantasia *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($supplier['name']); ?>" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Nome do Contato</label>
                    <input type="text" name="contact" value="<?php echo htmlspecialchars($supplier['contact_name']); ?>" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div>
                    <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">WhatsApp (com DDI) *</label>
                    <input type="text" name="phone" required value="<?php echo htmlspecialchars($supplier['phone']); ?>" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">E-mail</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($supplier['email']); ?>" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;">
                </div>
            </div>

            <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Catálogo / Lista de Preços (PDF/Excel)</label>
            <?php if($supplier['catalog_path']): ?>
                <div style="margin-bottom:10px;"><a href="../<?php echo $supplier['catalog_path']; ?>" target="_blank" style="color:#f1c40f; font-size:0.8rem;">📄 Ver Catálogo Atual</a></div>
            <?php endif; ?>
            <input type="file" name="catalog" accept=".pdf,.xlsx,.xls,.csv" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;">

            <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Endereço Completo (Para Lalamove)</label>
            <textarea name="address" rows="2" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;"><?php echo htmlspecialchars($supplier['address']); ?></textarea>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div>
                    <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Latitude (Lalamove)</label>
                    <input type="text" name="lat" value="<?php echo htmlspecialchars($supplier['lat']); ?>" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Longitude (Lalamove)</label>
                    <input type="text" name="lng" value="<?php echo htmlspecialchars($supplier['lng']); ?>" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;">
                </div>
            </div>

            <label style="display:block; font-size:0.8rem; color:#8b949e; margin-bottom:5px;">Observações Internas</label>
            <textarea name="notes" rows="3" style="width:100%; padding:12px; background:#0d1117; border:1px solid #333; color:#fff; border-radius:8px; margin-bottom:15px;"><?php echo htmlspecialchars($supplier['notes']); ?></textarea>

            <div style="text-align:right; margin-top:10px;">
                <button type="submit" class="btn" style="background:var(--primary); color:#000; font-weight:bold; padding:12px 30px;">💾 SALVAR ALTERAÇÕES</button>
            </div>
        </form>

        <!-- Histórico de Compras -->
        <div style="margin-top:3rem;">
            <h2 style="margin-bottom:1.5rem; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-history" style="color:var(--primary)"></i> 
                Itens Comprados deste Fornecedor
            </h2>
            
            <div style="background:#161b22; border-radius:12px; border:1px solid #333; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; text-align:left;">
                    <thead>
                        <tr style="background:#0d1117; border-bottom:1px solid #333;">
                            <th style="padding:15px; font-size:0.8rem; color:#8b949e;">Produto</th>
                            <th style="padding:15px; font-size:0.8rem; color:#8b949e;">Custo Unit.</th>
                            <th style="padding:15px; font-size:0.8rem; color:#8b949e;">Qtd</th>
                            <th style="padding:15px; font-size:0.8rem; color:#8b949e;">Data Compra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT 
                                COALESCE(p.name, CONCAT('Prod #', poi.product_id)) as name, 
                                poi.unit_cost, 
                                poi.quantity, 
                                po.created_at 
                            FROM purchase_order_items poi 
                            JOIN purchase_orders po ON poi.purchase_order_id = po.id 
                            LEFT JOIN products p ON poi.product_id = p.id 
                            WHERE po.supplier_id = ? 
                            ORDER BY po.created_at DESC 
                            LIMIT 50
                        ");
                        $stmt->execute([$id]);
                        $purchases = $stmt->fetchAll();

                        if (!$purchases): ?>
                            <tr>
                                <td colspan="4" style="padding:30px; text-align:center; color:#555;">Nenhuma compra registrada para este fornecedor.</td>
                            </tr>
                        <?php else: 
                            foreach($purchases as $p): ?>
                            <tr style="border-bottom:1px solid #222;">
                                <td style="padding:15px; font-weight:bold; color:#fff;"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td style="padding:15px; color:var(--primary);">R$ <?php echo number_format($p['unit_cost'], 2, ',', '.'); ?></td>
                                <td style="padding:15px;"><?php echo $p['quantity']; ?></td>
                                <td style="padding:15px; font-size:0.8rem; color:#8b949e;"><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
