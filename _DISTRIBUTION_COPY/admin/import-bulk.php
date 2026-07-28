<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Scraper.php';
isAdmin(); // Security Check

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urls'])) {
    $urls = explode("\n", $_POST['urls']);
    $count = 0;

    foreach ($urls as $url) {
        $url = trim($url);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL))
            continue;

        try {
            $data = Scraper::fetch($url);

            if ($data && !empty($data['name'])) {
                // Download Image
                $image_path = '';
                if ($data['image']) {
                    $imgContent = @file_get_contents($data['image']);
                    if ($imgContent) {
                        $ext = pathinfo(parse_url($data['image'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                        $filename = 'imported_' . uniqid() . '.' . $ext;
                        file_put_contents(__DIR__ . '/../assets/uploads/' . $filename, $imgContent);
                        $image_path = $filename;
                    }
                }

                // Insert into Database (Active = 1 for immediate visibility)
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
                // Default Active = 1
                $stmt = $pdo->prepare("INSERT INTO products (name, slug, description, price, image_path, sku, active, category_id) VALUES (?, ?, ?, ?, ?, ?, 1, NULL)");

                $price = $data['price'] ?: 0;
                $sku = $data['sku'] ?: 'IMP-' . strtoupper(uniqid());

                try {
                    $stmt->execute([
                        $data['name'],
                        $slug,
                        $data['description'],
                        $price,
                        $image_path,
                        $sku
                    ]);
                } catch (PDOException $e) {
                    // Handle Duplicate Slug
                    if ($e->getCode() == 23000) {
                        $slug = $slug . '-' . uniqid();
                        $sku = $sku . '-DUPL';
                        // Retry once
                        $stmt->execute([
                            $data['name'],
                            $slug,
                            $data['description'],
                            $price,
                            $image_path,
                            $sku
                        ]);
                    } else {
                        throw $e;
                    }
                }

                $results[] = ['status' => 'success', 'msg' => "Importado: {$data['name']}"];
                $count++;
            } else {
                // TRY DISCOVERY MODE
                $discovered = Scraper::discoverLinks($url);
                if (!empty($discovered)) {
                    $count_d = count($discovered);
                    $list = implode("\n", $discovered);
                    $results[] = [
                        'status' => 'info',
                        'msg' => "Link de Categoria detectado! Encontrei $count_d possíveis produtos.",
                        'discovery_list' => $list
                    ];
                } else {
                    $results[] = ['status' => 'error', 'msg' => "Falha ao ler: $url (Não é produto nem categoria reconhecida)"];
                }
            }
        } catch (Exception $e) {
            $results[] = ['status' => 'error', 'msg' => "Erro: " . $e->getMessage()];
        }

        // Prevent Timeouts
        if ($count > 5)
            sleep(1);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Importação em Massa | Fight Arcade</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .log-box {
            background: #000;
            color: #0f0;
            padding: 1rem;
            border-radius: 6px;
            font-family: monospace;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 2rem;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:3rem;">
        <div class="auth-box" style="max-width:800px; margin:0 auto;">
            <h2 style="font-family: var(--font-display);">🚀 Importador em Massa (Beta)</h2>
            <p>Cole uma lista de links (Mercado Livre, Lojas Concorrentes, etc). Um por linha.</p>
            <p style="color:yellow; font-size:0.9rem;">⚠️ Nota: Os produtos serão importados como "Inativos". Revise-os
                depois em "Produtos".</p>

            <form method="POST">
                <textarea name="urls" id="urlInput" rows="10"
                    placeholder="https://mercadolivre.com.br/produto1&#10;https://concorrente.com/produto2"
                    required></textarea>
                <div style="display:flex; justify-content:space-between; margin-top:10px;">
                    <button type="button" class="btn btn-secondary" onclick="cleanList()">🧹 Limpar Links Inválidos
                        (ML)</button>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn">🚀 Iniciar Importação em Massa</button>
                    </div>
                </div>
            </form>

            <script>
                function cleanList() {
                    const textarea = document.getElementById('urlInput');
                    const lines = textarea.value.split('\n');
                    const validLines = lines.filter(line => {
                        const l = line.trim().toLowerCase();
                        if (!l) return false;
                        // ML Specific Filter
                        if (l.includes('mercadolivre.com.br')) {
                            if (!l.includes('mlb') && !l.includes('/p/')) return false;
                            if (l.match(/(ajuda|assinaturas|promo|ofertas|minha-conta|login|faq)/)) return false;
                        }
                        return true;
                    });
                    textarea.value = validLines.join('\n');
                    alert('Lista limpa! Links suspeitos removidos.');
                }
            </script>

            <?php if (!empty($results)): ?>
                <div class="log-box">
                    <?php foreach ($results as $res): ?>
                        <div
                            style="color: <?php echo $res['status'] == 'success' ? '#2ecc71' : ($res['status'] == 'info' ? '#f1c40f' : '#e74c3c'); ?>; margin-bottom:5px;">
                            [<?php echo strtoupper($res['status']); ?>] <?php echo htmlspecialchars($res['msg']); ?>

                            <?php if (isset($res['discovery_list'])): ?>
                                <div style="background:#222; padding:10px; margin-top:5px; border-left:3px solid #f1c40f;">
                                    <p style="color:#fff; font-size:0.85rem; margin-bottom:5px;">👇 Sugestão de Links extraídos
                                        deste erro:</p>
                                    <form method="POST">
                                        <textarea name="urls" rows="5"
                                            style="width:100%; font-size:0.8rem; background:#000; color:#ccc; border:1px solid #444;"><?php echo htmlspecialchars($res['discovery_list']); ?></textarea>
                                        <button type="submit" class="btn btn-sm"
                                            style="margin-top:5px; background:#f1c40f; color:#000;">🔄 Importar Estes Links
                                            Agora</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:1rem; text-align:center;">
                    <a href="products.php" class="btn">Ver Produtos Importados</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>