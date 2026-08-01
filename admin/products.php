<?php
/**
 * admin/products.php — Helena Flores Admin (Com Busca Inteligente de Imagens)
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../config.php';

// 1. SINGLE ACTIONS
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $pdo->query("UPDATE products SET active = NOT active WHERE id = $id");
    header("Location: products.php");
    exit;
}
if (isset($_GET['toggle_site'])) {
    $id = (int)$_GET['toggle_site'];
    $pdo->query("UPDATE products SET show_on_site = NOT show_on_site WHERE id = $id");
    header("Location: products.php");
    exit;
}
if (isset($_GET['toggle_export'])) {
    $id = (int)$_GET['toggle_export'];
    $pdo->query("UPDATE products SET allow_export = NOT allow_export WHERE id = $id");
    header("Location: products.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->query("DELETE FROM products WHERE id = $id");
    header("Location: products.php?msg=deleted");
    exit;
}

// 2. BULK ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    $ids = implode(',', array_map('intval', $_POST['selected_ids']));

    if (isset($_POST['bulk_delete'])) {
        $pdo->query("DELETE FROM products WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_deleted");
        exit;
    }
    if (isset($_POST['bulk_site_show'])) {
        $pdo->query("UPDATE products SET show_on_site = 1 WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_updated");
        exit;
    }
    if (isset($_POST['bulk_site_hide'])) {
        $pdo->query("UPDATE products SET show_on_site = 0 WHERE id IN ($ids)");
        header("Location: products.php?msg=bulk_updated");
        exit;
    }
}

// FETCH PRODUCTS
$catId = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

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
?>

<div class="container" style="margin-top: 2rem; margin-bottom: 4rem;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h1 style="color:#FFF; font-size:1.6rem; margin:0;">🛍️ Gerenciar Produtos (<?php echo count($products); ?>)</h1>
        <a href="product-edit.php" class="btn" style="background:#C2185B; color:#FFF; font-weight:bold; padding:10px 20px; border-radius:20px; text-decoration:none;">
            + Novo Produto
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" style="display:flex; gap:12px; margin-bottom:1.5rem; background:#1A1A1A; padding:15px; border-radius:12px; border:1px solid #333;">
        <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Buscar produto por nome..." 
               style="flex:1; height:40px; background:#000; border:1px solid #444; color:#FFF; border-radius:8px; padding:0 12px;">
        <select name="cat" onchange="this.form.submit()" style="height:40px; background:#000; color:#FFF; border:1px solid #444; border-radius:8px; padding:0 12px;">
            <option value="">Todas Categorias</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ($catId === $c['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" style="background:#4CAF50; color:#FFF; font-weight:bold; padding:0 20px; border-radius:8px; border:none;">
            Filtrar
        </button>
    </form>

    <!-- Table -->
    <div style="background:#111; border:1px solid #333; border-radius:14px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; color:#EEE;">
            <thead>
                <tr style="background:#222; color:#FFECB3; text-align:left; border-bottom:1px solid #333;">
                    <th style="padding:12px 15px;">Foto</th>
                    <th style="padding:12px 15px;">Produto</th>
                    <th style="padding:12px 15px;">Categoria</th>
                    <th style="padding:12px 15px;">Preço</th>
                    <th style="padding:12px 15px; text-align:center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5" style="padding:30px; text-align:center; color:#888;">Nenhum produto encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php 
                        $imgSrc = get_product_image_url($p['image_path'], $p['name']);
                        if (strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '../') !== 0) {
                            $imgSrc = '../' . $imgSrc;
                        }
                        ?>
                        <tr style="border-bottom:1px solid #222;">
                            <td style="padding:12px 15px;">
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                     alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                     style="width:50px; height:50px; object-fit:cover; border-radius:8px; border:1px solid #444;"
                                     onerror="this.src='../assets/uploads/rose_red.jpg'">
                            </td>
                            <td style="padding:12px 15px;">
                                <strong style="color:#FFF; font-size:0.95rem;"><?php echo htmlspecialchars($p['name']); ?></strong><br>
                                <span style="font-size:0.8rem; color:#888;">Cód: <?php echo htmlspecialchars($p['sku'] ?: $p['id']); ?></span>
                            </td>
                            <td style="padding:12px 15px; font-size:0.9rem; color:#DDD;">
                                <?php echo htmlspecialchars($p['category_name'] ?? 'Rosas'); ?>
                            </td>
                            <td style="padding:12px 15px; font-weight:bold; color:#4CAF50;">
                                R$ <?php echo number_format($p['price'], 2, ',', '.'); ?>
                            </td>
                            <td style="padding:12px 15px; text-align:center;">
                                <a href="../product.php?id=<?php echo $p['id']; ?>" target="_blank" style="color:#4FC3F7; text-decoration:none; margin-right:10px; font-weight:bold;">
                                    👁️ Ver
                                </a>
                                <a href="product-edit.php?id=<?php echo $p['id']; ?>" style="color:#FFB74D; text-decoration:none; margin-right:10px; font-weight:bold;">
                                    ✏️ Editar
                                </a>
                                <a href="?delete=<?php echo $p['id']; ?>" onclick="return confirm('Excluir este produto?')" style="color:#E57373; text-decoration:none; font-weight:bold;">
                                    🗑️ Excluir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer_public.php'; ?>