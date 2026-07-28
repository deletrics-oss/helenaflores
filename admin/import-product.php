<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
isAdmin();

$error = '';

// Handle File Upload (Drag & Drop) OR URL
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. FILE UPLOAD (Priority)
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $filename = 'import_' . uniqid() . '.' . $ext;
        $dest = __DIR__ . '/../assets/uploads/' . $filename;

        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
            // "AI" Mock Analysis (Extract basic info from filename as a feature)
            $simulatedName = ucfirst(str_replace(['-', '_', '.jpg', '.png'], ' ', $_FILES['image_file']['name']));

            $query = http_build_query([
                'name' => "Produto Importado (IA)", // Placeholder for user to fill
                'description' => "Produto importado via imagem. Verifique as especificações.",
                'local_image' => $filename
            ]);
            header("Location: product-edit.php?" . $query);
            exit;
        } else {
            $error = "Erro ao salvar imagem no servidor.";
        }
    }
    // 2. URL SCRAPER
    elseif (!empty($_POST['url'])) {
        require_once __DIR__ . '/../includes/Scraper.php';

        $url = $_POST['url'];
        // Try scraping
        $data = Scraper::fetch($url);

        if ($data) {
            $query = http_build_query([
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'img_url' => $data['image']
            ]);
            header("Location: product-edit.php?" . $query);
            exit;
        } else {
            $error = "Não foi possível importar. O site pode ter bloqueio anti-bot ou o link é inválido.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Importador Inteligente | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .drop-zone {
            border: 2px dashed var(--primary);
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            background: rgba(255, 183, 3, 0.05);
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 2rem;
            position: relative;
        }

        .drop-zone:hover,
        .drop-zone.dragover {
            background: rgba(255, 183, 3, 0.15);
            transform: scale(1.01);
        }

        .drop-zone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .ai-pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 183, 3, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(255, 183, 3, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(255, 183, 3, 0);
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:3rem;">
        <div class="auth-box" style="max-width:700px; margin:0 auto; padding:2rem;">
            <h2 style="font-family: var(--font-display);"><span style="font-size:1.5em;">🧠</span> Importador
                Inteligente IA</h2>
            <p style="margin-bottom:2rem; color:#888;">Arraste uma foto ou cole um link. Nossa IA prepara o rascunho do
                produto para você.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="importForm">

                <!-- DROP ZONE -->
                <div class="drop-zone" id="dropZone">
                    <input type="file" name="image_file" accept="image/*" onchange="this.form.submit(); showLoading();">
                    <div style="font-size:3rem; margin-bottom:1rem;">📷</div>
                    <h3 style="color:var(--primary); margin-bottom:0.5rem;">Arraste sua Foto Aqui</h3>
                    <p>Ou clique para selecionar</p>
                    <small style="color:#666;">O sistema preparará um rascunho com a imagem.</small>
                </div>

                <div style="display:flex; align-items:center; gap:1rem; margin: 2rem 0;">
                    <hr style="flex:1; border-color:#333;">
                    <span style="color:#666;">OU POR LINK</span>
                    <hr style="flex:1; border-color:#333;">
                </div>

                <!-- URL INPUT -->
                <label>URL do Produto (Concorrente/Fornecedor)</label>
                <div style="display:flex; gap:10px;">
                    <input type="url" name="url" placeholder="https://..." style="margin:0;">
                    <button type="submit" class="btn" style="flex-shrink:0;" onclick="showLoading()">🚀 Analisar
                        Site</button>
                </div>

            </form>

            <!-- Loading Overlay (Hidden by default) -->
            <div id="loading" style="display:none; text-align:center; margin-top:2rem;">
                <div class="ai-pulse"
                    style="width:60px; height:60px; background:var(--primary); border-radius:50%; margin:0 auto 1rem auto;">
                </div>
                <h3 style="color:var(--text-main);">Analisando Imagem...</h3>
                <p style="color:var(--text-muted);">A IA está extraindo as informações...</p>
            </div>

            <div style="margin-top:20px; text-align:center;">
                <a href="products.php" style="color:#ccc;">Voltar</a>
            </div>
        </div>
    </div>

    <script>
        const dz = document.getElementById('dropZone');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dz.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

        ['dragenter', 'dragover'].forEach(eventName => {
            dz.addEventListener(eventName, () => dz.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dz.addEventListener(eventName, () => dz.classList.remove('dragover'), false);
        });

        function showLoading() {
            document.getElementById('importForm').style.opacity = '0.5';
            document.getElementById('importForm').style.pointerEvents = 'none';
            document.getElementById('loading').style.display = 'block';
        }

        // PASTE SUPPORT (Ctrl+V)
        document.onpaste = function (event) {
            var items = (event.clipboardData || event.originalEvent.clipboardData).items;
            for (index in items) {
                var item = items[index];
                if (item.kind === 'file' && item.type.includes('image')) {
                    var blob = item.getAsFile();
                    var file = new File([blob], "pasted_image.jpg", { type: "image/jpeg" });

                    // Assign to Input
                    let container = new DataTransfer();
                    container.items.add(file);
                    document.querySelector('input[name="image_file"]').files = container.files;

                    // Trigger visual feedback and auto-submit
                    dz.classList.add('dragover');
                    document.querySelector('.drop-zone h3').innerText = "Imagem Colada! Processando...";
                    setTimeout(() => {
                        document.getElementById('importForm').submit();
                        showLoading();
                    }, 500);
                }
            }
        }
    </script>
</body>

</html>