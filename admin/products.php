<?php
/**
 * admin/products.php — Helena Flores Admin (Gestão Completa e Elegante de Produtos)
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../config.php';

// Single Actions (Toggle & Delete)
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $pdo->query("UPDATE products SET active = NOT active WHERE id = $id");
    header("Location: products.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->query("DELETE FROM products WHERE id = $id");
    header("Location: products.php?msg=deleted");
    exit;
}

// Fetch Parameters
$catId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build Query
$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if ($catId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catId;
}
if (!empty($query)) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$msg = $_GET['msg'] ?? '';
?>

<div style="max-width:1300px; margin: 2rem auto; padding: 0 20px; width:100%; flex:1;">
    
    <!-- Top Action Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.8rem; flex-wrap:wrap; gap:15px;">
        <div>
            <h1 style="font-size:1.8rem; font-weight:800; color:#FFF; margin:0; display:flex; align-items:center; gap:10px;">
                🌸 Gestão de Produtos 
                <span style="font-size:0.9rem; background:#C2185B; color:#FFF; padding:4px 12px; border-radius:20px; font-weight:700;">
                    <?php echo count($products); ?> cadastrados
                </span>
            </h1>
            <p style="color:#A0AEC0; font-size:0.9rem; margin-top:4px;">Gerencie fotos, preços, categorias e estoque do catálogo Helena Flores.</p>
        </div>

        <a href="product-edit.php" style="background:#C2185B; color:#FFF; font-weight:bold; padding:12px 24px; border-radius:25px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 15px rgba(194,24,91,0.4); transition:all 0.2s ease;">
            ✨ + Cadastrar Novo Produto
        </a>
    </div>

    <?php if ($msg === 'deleted'): ?>
        <div style="background:#2D1B2E; color:#F687B3; padding:14px; border-radius:10px; margin-bottom:1.5rem; border:1px solid #702459; font-weight:bold;">
            🗑️ Produto excluído com sucesso do catálogo!
        </div>
    <?php endif; ?>

    <!-- Filter Bar Card -->
    <div style="background:#161C2E; border:1px solid #28324A; padding:18px; border-radius:14px; margin-bottom:1.8rem; box-shadow:0 4px 15px rgba(0,0,0,0.2);">
        <form method="GET" style="display:flex; gap:15px; flex-wrap:wrap;">
            <div style="flex:2; min-width:240px;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" 
                       placeholder="🔍 Buscar por nome do produto, flor ou código SKU..." 
                       style="width:100%; height:45px; background:#0E131F; border:1px solid #2D3748; color:#FFF; border-radius:10px; padding:0 15px; font-size:0.95rem;">
            </div>

            <div style="flex:1; min-width:200px;">
                <select name="cat" onchange="this.form.submit()" 
                        style="width:100%; height:45px; background:#0E131F; border:1px solid #2D3748; color:#FFF; border-radius:10px; padding:0 15px; font-size:0.95rem;">
                    <option value="">🌸 Todas as Categorias</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($catId === (int)$c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" style="background:#242D45; color:#FFF; font-weight:bold; height:45px; padding:0 25px; border-radius:10px; border:1px solid #3A4766; cursor:pointer;">
                Filtrar
            </button>

            <?php if (!empty($query) || $catId > 0): ?>
                <a href="products.php" style="background:transparent; color:#A0AEC0; font-weight:bold; height:45px; padding:0 15px; border-radius:10px; text-decoration:none; display:inline-flex; align-items:center;">
                    Limpar Filtros ✖
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Products Data Table -->
    <div style="background:#161C2E; border:1px solid #28324A; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.3);">
        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:#1F273D; border-bottom:1px solid #28324A; color:#A0AEC0; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">
                    <th style="padding:16px 20px;">Imagem</th>
                    <th style="padding:16px 20px;">Detalhes do Produto</th>
                    <th style="padding:16px 20px;">Categoria</th>
                    <th style="padding:16px 20px;">Preço</th>
                    <th style="padding:16px 20px;">Estoque</th>
                    <th style="padding:16px 20px;">Status</th>
                    <th style="padding:16px 20px; text-align:right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" style="padding:40px; text-align:center; color:#A0AEC0; font-size:1rem;">
                            🌸 Nenhum produto localizado com os critérios selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php 
                        $imgSrc = get_product_image_url($p['image_path'], $p['name']);
                        if (strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '../') !== 0) {
                            $imgSrc = '../' . $imgSrc;
                        }
                        $stockQty = (int)($p['stock_qty'] ?? 10);
                        $isActive = (bool)($p['active'] ?? true);
                        ?>
                        <tr style="border-bottom:1px solid #21293D; transition:background 0.2s ease;">
                            <!-- Thumbnail -->
                            <td style="padding:14px 20px;">
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                     alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                     style="width:65px; height:65px; object-fit:cover; border-radius:12px; border:2px solid #2D3748; background:#000;"
                                     onerror="this.src='../assets/uploads/rose_red.jpg'">
                            </td>

                            <!-- Title & SKU -->
                            <td style="padding:14px 20px;">
                                <strong style="color:#FFF; font-size:1rem; display:block; margin-bottom:3px;">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </strong>
                                <span style="font-size:0.8rem; color:#A0AEC0; background:#0E131F; padding:2px 8px; border-radius:6px; border:1px solid #28324A;">
                                    SKU: <?php echo htmlspecialchars($p['sku'] ?: $p['id']); ?>
                                </span>
                            </td>

                            <!-- Category Badge -->
                            <td style="padding:14px 20px;">
                                <span style="background:#2D1B2E; color:#F687B3; padding:5px 12px; border-radius:15px; font-size:0.85rem; font-weight:700; border:1px solid #521B43;">
                                    <?php echo htmlspecialchars($p['category_name'] ?? 'Rosas & Arranjos'); ?>
                                </span>
                            </td>

                            <!-- Price -->
                            <td style="padding:14px 20px;">
                                <span style="font-size:1.1rem; font-weight:800; color:#48BB78;">
                                    R$ <?php echo number_format($p['price'], 2, ',', '.'); ?>
                                </span>
                            </td>

                            <!-- Stock -->
                            <td style="padding:14px 20px;">
                                <?php if ($stockQty > 0): ?>
                                    <span style="color:#68D391; font-weight:bold; font-size:0.85rem;">
                                        🟢 <?php echo $stockQty; ?> em estoque
                                    </span>
                                <?php else: ?>
                                    <span style="color:#FC8181; font-weight:bold; font-size:0.85rem;">
                                        🔴 Esgotado
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Active Status -->
                            <td style="padding:14px 20px;">
                                <a href="?toggle_status=<?php echo $p['id']; ?>" title="Clique para alternar visibilidade no site" style="text-decoration:none;">
                                    <?php if ($isActive): ?>
                                        <span style="background:#1C4532; color:#68D391; padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:bold;">
                                            ✅ Ativo no Site
                                        </span>
                                    <?php else: ?>
                                        <span style="background:#4A1D1D; color:#FC8181; padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:bold;">
                                            ⛔ Oculto
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </td>

                            <!-- Action Buttons -->
                            <td style="padding:14px 20px; text-align:right;">
                                <div style="display:flex; gap:8px; justify-content:flex-end;">
                                    <a href="../product.php?id=<?php echo $p['id']; ?>" target="_blank" 
                                       style="background:#242D45; color:#63B3ED; padding:8px 12px; border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:bold; border:1px solid #3A4766;" title="Ver na loja pública">
                                        👁️ Ver
                                    </a>
                                    <a href="product-edit.php?id=<?php echo $p['id']; ?>" 
                                       style="background:#C2185B; color:#FFF; padding:8px 14px; border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:bold;" title="Editar produto">
                                        ✏️ Editar
                                    </a>
                                    <a href="?delete=<?php echo $p['id']; ?>" onclick="return confirm('Excluir permanentemente este produto?')" 
                                       style="background:#4A1D24; color:#FC8181; padding:8px 12px; border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:bold; border:1px solid #742A2A;" title="Excluir produto">
                                        🗑️
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>