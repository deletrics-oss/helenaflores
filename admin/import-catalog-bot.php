<?php
/**
 * admin/import-catalog-bot.php — Helena Flores
 * Interface Web do Robô Extrator de Catálogo
 */

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../robot_scraper.php';

$message = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['url'] ?? '';
    $rawText = $_POST['raw_text'] ?? '';

    $robot = new CatalogRobotScraper($pdo);

    if (!empty($url)) {
        $result = $robot->scrapeFromUrl($url);
    } elseif (!empty($rawText)) {
        $result = $robot->parseAndInsert($rawText, 'manual_paste');
    } else {
        $message = '<div class="alert alert-error">Por favor, informe uma URL ou cole o conteúdo do catálogo.</div>';
    }
}
?>

<div class="container" style="margin-top: 2rem; margin-bottom: 4rem;">
    <h1 style="color:var(--primary); margin-bottom:0.5rem;">🤖 Robô Extrator de Catálogo</h1>
    <p style="color:var(--text-muted); margin-bottom:2rem;">
        Extraia automaticamente produtos, preços, fotos e descrições do seu catálogo do WhatsApp Business ou site e cadastre instantaneamente no banco de dados.
    </p>

    <?php echo $message; ?>

    <?php if ($result): ?>
        <?php if ($result['success']): ?>
            <div class="alert alert-success">
                🎉 <strong>Extração Concluída com Sucesso!</strong><br>
                • Produtos encontrados: <strong><?php echo $result['total_found']; ?></strong><br>
                • Novos produtos cadastrados: <strong><?php echo $result['inserted']; ?></strong><br>
                • Produtos atualizados: <strong><?php echo $result['updated']; ?></strong>
            </div>

            <div style="background:var(--bg-card); padding:1.5rem; border-radius:8px; border:1px solid var(--border); margin-bottom:2rem;">
                <h3 style="color:#fff; margin-bottom:1rem;">Produtos Processados:</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Preço</th>
                                <th>Categoria</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['items'] as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                    <td style="color:var(--primary);">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                ❌ <strong>Erro no Processamento:</strong> <?php echo htmlspecialchars($result['error']); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="background:var(--bg-card); padding:2rem; border-radius:12px; border:1px solid var(--border);">
        <form method="POST">
            <div style="margin-bottom:1.5rem;">
                <label style="display:block; font-weight:bold; margin-bottom:0.5rem; color:#fff;">
                    🌐 Opção 1: Digite a URL do Catálogo para Extrair
                </label>
                <input type="url" name="url" placeholder="https://helenafloresjardins.com.br/helena_flores" 
                       style="width:100%; padding:12px; border-radius:6px; background:#1a1a1a; border:1px solid #444; color:#fff;">
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block; font-weight:bold; margin-bottom:0.5rem; color:#fff;">
                    📋 Opção 2: Ou cole o código HTML/JSON do Catálogo
                </label>
                <textarea name="raw_text" rows="6" placeholder="Cole aqui o texto ou código extraído do catálogo..."
                          style="width:100%; padding:12px; border-radius:6px; background:#1a1a1a; border:1px solid #444; color:#fff; font-family:monospace;"></textarea>
            </div>

            <button type="submit" class="btn" style="width:100%; height:50px; font-size:1.1rem;">
                ⚡ EXECUTAR EXTRAÇÃO E CADASTRAR PRODUTOS
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer_public.php'; ?>
