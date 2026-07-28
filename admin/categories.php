<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

// 1. DELETE
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    // Check if products use this category
    $count = $pdo->query("SELECT COUNT(*) FROM products WHERE category_id = $id")->fetchColumn();

    if ($count > 0) {
        header("Location: categories.php?msg=error_used&count=$count");
    } else {
        $pdo->query("DELETE FROM categories WHERE id = $id");
        header("Location: categories.php?msg=deleted");
    }
    exit;
}

// 2. CREATE / EDIT
$editMode = false;
$catData = ['name' => '', 'id' => '', 'show_on_site' => 1];

if (isset($_GET['edit'])) {
    $editMode = true;
    $id = (int) $_GET['edit'];
    $catData = $pdo->query("SELECT * FROM categories WHERE id = $id")->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $show_on_site = isset($_POST['show_on_site']) ? 1 : 0;

    // Validation: Prevent empty or invalid names like "."
    if (empty($name) || $name === '.') {
        header("Location: categories.php?msg=error_invalid");
        exit;
    }

    // Helper for Slug
    function makeSlug($str)
    {
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $str = preg_replace('/[^a-zA-Z0-9 -]/', '', $str);
        $str = strtolower(trim($str));
        return preg_replace('/-+/', '-', $str);
    }

    $baseSlug = makeSlug($name);

    // Ensure uniqueness
    // Note: This simple check might fail in high concurrency but sufficient for admin panel
    // We will append -1, -2 if needed logic could be added, but for now let's trust the unique constraint or add simple suffix
    // Better: let's try to find if it exists

    // Logic to handle unique slug:
    $finalSlug = $baseSlug;
    $counter = 1;
    $idCheck = isset($_POST['id']) ? $_POST['id'] : 0;

    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
        $stmt->execute([$finalSlug, $idCheck]);
        if ($stmt->fetchColumn() == 0)
            break;
        $finalSlug = $baseSlug . '-' . $counter++;
    }

    try {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, show_on_site = ? WHERE id = ?");
            $stmt->execute([$name, $finalSlug, $show_on_site, $_POST['id']]);
            header("Location: categories.php?msg=updated");
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, show_on_site) VALUES (?, ?, ?)");
            $stmt->execute([$name, $finalSlug, $show_on_site]);
            header("Location: categories.php?msg=created");
        }
        exit;
    } catch (Exception $e) {
        // Log error and redirect with friendly message
        error_log("Erro ao salvar categoria: " . $e->getMessage());
        header("Location: categories.php?msg=error_db&debug=" . urlencode($e->getMessage()));
        exit;
    }
}

// Fetch All
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Categorias | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:2rem;">

        <!-- MESSAGES -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'created'): ?>
                <div class="alert alert-success">✅ Categoria criada com sucesso!</div>
            <?php elseif ($_GET['msg'] == 'updated'): ?>
                <div class="alert alert-success">✅ Categoria atualizada!</div>
            <?php elseif ($_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success">🗑️ Categoria excluída.</div>
            <?php elseif ($_GET['msg'] == 'error_used'): ?>
                <div class="alert alert-danger">⚠️ Não é possível excluir: Existem <?php echo $_GET['count']; ?> produtos nesta
                    categoria.</div>
            <?php elseif ($_GET['msg'] == 'error_invalid'): ?>
                <div class="alert alert-danger">⚠️ Nome inválido ou muito curto.</div>
            <?php elseif ($_GET['msg'] == 'error_db'): ?>
                <div class="alert alert-danger">❌ Erro no Banco de Dados:
                    <?php echo htmlspecialchars($_GET['debug'] ?? 'Desconhecido'); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="display:flex; gap:2rem; flex-wrap:wrap;">

            <!-- FORM SECTION -->
            <div style="flex:1; min-width:300px;">
                <div style="background:#111; padding:1.5rem; border-radius:8px; border:1px solid #333;">
                    <h2 style="margin-bottom:1rem;">
                        <?php echo $editMode ? '✏️ Editar Categoria' : '➕ Nova Categoria'; ?>
                    </h2>

                    <form method="POST">
                        <?php if ($editMode): ?>
                            <input type="hidden" name="id" value="<?php echo $catData['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom:1rem;">
                            <label>Nome da Categoria</label>
                            <input type="text" name="name" class="form-control"
                                value="<?php echo htmlspecialchars($catData['name']); ?>" required
                                placeholder="Ex: Joysticks, Botões...">
                        </div>

                        <div class="form-group" style="margin-bottom:1rem;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="show_on_site" value="1" <?php echo ($catData['show_on_site'] ?? 1) ? 'checked' : ''; ?>>
                                🌐 Mostrar no Catálogo Público
                            </label>
                        </div>

                        <button type="submit" class="btn" style="width:100%;">
                            <?php echo $editMode ? 'Salvar Alterações' : 'Cadastrar Categoria'; ?>
                        </button>

                        <?php if ($editMode): ?>
                            <a href="categories.php" class="btn btn-secondary"
                                style="display:block; text-align:center; margin-top:10px;">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- LIST SECTION -->
            <div style="flex:2; min-width:300px;">
                <h2>📂 Categorias Existentes</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th style="text-align:center;">Visibilidade</th>
                                <th style="text-align:right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td>#<?php echo $c['id']; ?></td>
                                    <td style="font-weight:bold; color:var(--primary);">
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($c['show_on_site'] ?? 1): ?>
                                            <span style="color:#2ecc71;" title="Visível">🌐</span>
                                        <?php else: ?>
                                            <span style="color:#e74c3c;" title="Oculto">🌑</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="?edit=<?php echo $c['id']; ?>" class="btn-sm"
                                            style="background:#3498db; color:white; text-decoration:none;">✏️ Editar</a>
                                        <a href="?delete=<?php echo $c['id']; ?>" class="btn-sm"
                                            style="background:var(--danger); color:white; text-decoration:none;"
                                            onclick="return confirm('Tem certeza?')">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

</body>

</html>