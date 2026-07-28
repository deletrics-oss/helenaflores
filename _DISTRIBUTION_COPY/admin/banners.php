<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/db.php';
isAdmin();

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['banner_image']) || isset($_POST['banner_id']))) {
    require_once __DIR__ . '/../includes/image_handler.php'; // Include Helper

    // Handle Upload / Update
    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $link = $_POST['link'];

    if (isset($_POST['banner_id']) && !empty($_POST['banner_id'])) {
        // UPDATE
        $id = (int) $_POST['banner_id'];
        $sql = "UPDATE banners SET title=?, subtitle=?, link_url=? WHERE id=?";
        $params = [$title, $subtitle, $link, $id];

        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = uploadImage($_FILES['banner_image'], __DIR__ . '/../assets/banners', 'banner_');
            if (isset($upload['success'])) {
                $sql = "UPDATE banners SET title=?, subtitle=?, link_url=?, image_path=? WHERE id=?";
                $params = [$title, $subtitle, $link, 'assets/banners/' . $upload['filename'], $id];
            } else {
                $error = $upload['error'];
            }
        }
        $pdo->prepare($sql)->execute($params);
        $msg = "Banner atualizado!";
    } else {
        // INSERT
        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = uploadImage($_FILES['banner_image'], __DIR__ . '/../assets/banners', 'banner_');
            if (isset($upload['success'])) {
                $dbPath = 'assets/banners/' . $upload['filename'];
                $pdo->prepare("INSERT INTO banners (image_path, title, subtitle, link_url, active) VALUES (?, ?, ?, ?, 1)")
                    ->execute([$dbPath, $title, $subtitle, $link]);
                $msg = "Banner adicionado!";
            } else {
                $error = $upload['error'];
            }
        } else {
            $error = "Selecione uma imagem para o banner.";
        }
    }
}

// --- HANDLE GLOBAL SLIDER SETTINGS ---
$settingsFile = __DIR__ . '/../includes/site_settings.json';
$siteSettings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_global_settings'])) {
    $siteSettings['slider_speed'] = (int) ($_POST['slider_speed'] ?? 4000);
    $siteSettings['slider_height'] = (int) ($_POST['slider_height'] ?? 380);
    $siteSettings['slider_overlay'] = (float) ($_POST['slider_overlay'] ?? 0.8);

    file_put_contents($settingsFile, json_encode($siteSettings, JSON_PRETTY_PRINT));
    $msg = "Configurações globais atualizadas!";
}

// Defaults for UI
$current_speed = $siteSettings['slider_speed'] ?? 4000;
$current_height = $siteSettings['slider_height'] ?? 380;
$current_overlay = $siteSettings['slider_overlay'] ?? 0.8;

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->query("DELETE FROM banners WHERE id = $id");
    header("Location: banners.php");
    exit;
}

// Handle Clone
if (isset($_GET['clone'])) {
    $id = (int) $_GET['clone'];
    $original = $pdo->query("SELECT * FROM banners WHERE id = $id")->fetch();
    if ($original) {
        $pdo->prepare("INSERT INTO banners (image_path, title, subtitle, link_url, active) VALUES (?, ?, ?, ?, 1)")
            ->execute([$original['image_path'], $original['title'] . ' (Cópia)', $original['subtitle'], $original['link_url']]);
    }
    header("Location: banners.php");
    exit;
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Banners | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <div class="auth-box" style="max-width:900px; margin:0 auto;">
            <h2>🖼️ Gerenciar Banners (Carrossel)</h2>

            <?php if (isset($msg))
                echo "<p style='color:green'>$msg</p>"; ?>
            <?php if (isset($error))
                echo "<p style='color:red'>$error</p>"; ?>

            <form method="POST" enctype="multipart/form-data" id="bannerForm"
                style="background:#1a1a1a; padding:1.5rem; border-radius:8px; margin-bottom:2rem; border:1px solid #333;">
                <h4 id="formTitle">Novo Banner</h4>
                <input type="hidden" name="banner_id" id="banner_id">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <input type="text" name="title" id="title" placeholder="Título Principal" required>
                    <input type="text" name="subtitle" id="subtitle" placeholder="Subtítulo">
                    <input type="text" name="link" id="link" placeholder="Link (Ex: ?cat=1)">
                    <div style="display:flex; flex-direction:column;">
                        <input type="file" name="banner_image" id="banner_image" accept="image/*">
                        <small style="color:#666;">Deixe vazio para manter a imagem atual (ao editar).</small>
                    </div>
                </div>

                <div style="margin-top:10px; display:flex; gap:10px;">
                    <button type="submit" class="btn" id="btnSubmit">➕ Adicionar Banner</button>
                    <button type="button" class="btn btn-secondary" id="btnCancel" style="display:none;"
                        onclick="resetForm()">Cancelar Edição</button>
                </div>
                <div style="margin-top:10px;">
                    <small style="color:var(--primary);">💡 <strong>Dica de Resolução:</strong> Para preencher bem a
                        tela, use imagens de <strong>1920x600px</strong> ou proporções similares.</small>
                </div>
            </form>

            <!-- Global Slider Settings -->
            <form method="POST"
                style="background:#111; padding:1.5rem; border-radius:8px; margin-bottom:2rem; border:1px solid var(--primary-glow);">
                <h4 style="color:var(--primary); margin-bottom:1rem;">⚙️ Personalizar Exibição (Global)</h4>
                <input type="hidden" name="save_global_settings" value="1">

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">
                    <div>
                        <label style="display:block; font-size:0.85rem; margin-bottom:5px;">Velocidade (ms)</label>
                        <input type="number" name="slider_speed" value="<?php echo $current_speed; ?>" step="500"
                            min="1000" style="margin-bottom:0;">
                        <small style="color:#666;">1000ms = 1 segundo</small>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.85rem; margin-bottom:5px;">Altura do Banner
                            (px)</label>
                        <input type="number" name="slider_height" value="<?php echo $current_height; ?>" step="10"
                            min="200" max="800" style="margin-bottom:0;">
                        <small style="color:#666;">Altura na área de trabalho</small>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.85rem; margin-bottom:5px;">Escuridão do Filtro (0 a
                            1)</label>
                        <input type="number" name="slider_overlay" value="<?php echo $current_overlay; ?>" step="0.1"
                            min="0" max="1" style="margin-bottom:0;">
                        <small style="color:#666;">0 = Foto Pura, 1 = Todo Preto</small>
                    </div>
                </div>

                <div style="margin-top:15px; text-align:right;">
                    <button type="submit" class="btn-sm"
                        style="background:var(--success); color:#fff; border:none; padding:8px 20px; border-radius:4px; cursor:pointer;">💾
                        Salvar Ajustes Visuais</button>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Imagem</th>
                        <th>Texto</th>
                        <th>Link</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $b): ?>
                        <tr>
                            <td><img src="../<?php echo htmlspecialchars($b['image_path']); ?>"
                                    style="height:50px; border-radius:4px;"></td>
                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($b['title']); ?>
                                </strong><br>
                                <small>
                                    <?php echo htmlspecialchars($b['subtitle']); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($b['link_url']); ?>
                            </td>
                            <td>
                                <button type="button" class="btn-sm"
                                    style="background:#f1c40f; color:#000; border:none; cursor:pointer;"
                                    onclick='editBanner(<?php echo json_encode($b); ?>)'>✏️</button>
                                <a href="?clone=<?php echo $b['id']; ?>" class="btn-sm"
                                    style="background:#3498db; color:#fff; text-decoration:none;">📄</a>
                                <a href="?delete=<?php echo $b['id']; ?>" class="btn-sm btn-danger"
                                    onclick="return confirm('Excluir?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function editBanner(b) {
            document.getElementById('formTitle').innerText = 'Editar Banner #' + b.id;
            document.getElementById('banner_id').value = b.id;
            document.getElementById('title').value = b.title;
            document.getElementById('subtitle').value = b.subtitle;
            document.getElementById('link').value = b.link_url;

            document.getElementById('btnSubmit').innerText = '💾 Salvar Alterações';
            document.getElementById('btnCancel').style.display = 'inline-block';

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('formTitle').innerText = 'Novo Banner';
            document.getElementById('banner_id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('subtitle').value = '';
            document.getElementById('link').value = '';
            document.getElementById('banner_image').value = '';

            document.getElementById('btnSubmit').innerText = '➕ Adicionar Banner';
            document.getElementById('btnCancel').style.display = 'none';
        }
    </script>
</body>

</html>