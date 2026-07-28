<?php
/**
 * admin/import-catalog-bot.php — Helena Flores
 * Interface Web do Robô Extrator de Catálogo (Clonador WhatsApp Business / Web)
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
        $message = '<div class="alert alert-error">Por favor, informe a URL do catálogo ou cole o conteúdo extraído.</div>';
    }
}
?>

<div class="container" style="margin-top: 2rem; margin-bottom: 4rem;">
    <div style="background:#1B3B2B; color:white; padding:20px; border-radius:12px; margin-bottom:2rem;">
        <h1 style="color:#C5A059; margin-bottom:0.5rem; font-family:Georgia, serif;">🤖 Robô Extrator (Clonador de Catálogo WhatsApp Business / Web)</h1>
        <p style="font-size:1rem; line-height:1.6; color:#EFECE6;">
            Como funciona: O robô varre o catálogo do WhatsApp Business ou site de referência, extrai todos os nomes, descrições, preços e imagens dos buquês e cestas, salva as fotos na pasta <code>assets/uploads/</code> e cadastra os produtos automaticamente no banco de dados MySQL!
        </p>
    </div>

    <?php echo $message; ?>

    <?php if ($result): ?>
        <?php if ($result['success']): ?>
            <div class="alert alert-success" style="font-size:1.1rem; line-height:1.6;">
                🎉 <strong>Extração & Clonagem Concluída com Sucesso!</strong><br>
                • Produtos encontrados: <strong><?php echo $result['total_found']; ?></strong><br>
                • Novos produtos cadastrados: <strong><?php echo $result['inserted']; ?></strong><br>
                • Produtos atualizados: <strong><?php echo $result['updated']; ?></strong>
            </div>

            <div style="background:var(--bg-card); padding:1.5rem; border-radius:12px; border:1px solid var(--border); margin-bottom:2rem;">
                <h3 style="color:#fff; margin-bottom:1rem;">Produtos Clonados e Cadastrados:</h3>
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
                                    <td style="color:var(--primary); font-weight:bold;">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="../index.php" target="_blank" class="btn" style="background:#C5A059; color:#FFF; margin-top:10px; text-decoration:none;">
                    🌸 Ver Produtos Atualizados na Loja →
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                ❌ <strong>Erro no Processamento:</strong> <?php echo htmlspecialchars($result['error']); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="background:var(--bg-card); padding:2rem; border-radius:12px; border:1px solid var(--border);">
        <form method="POST">
            <div style="margin-bottom:2rem;">
                <label style="display:block; font-weight:bold; margin-bottom:0.5rem; color:#C5A059; font-size:1.1rem;">
                    🌐 Opção 1: Digite a URL do Catálogo do WhatsApp Business ou Site
                </label>
                <input type="url" name="url" placeholder="https://helenafloresjardins.com.br/helena_flores" 
                       style="width:100%; padding:14px; border-radius:8px; background:#1a1a1a; border:1px solid #444; color:#fff; font-size:1rem;">
                <small style="color:#888;">Cole o link do catálogo online para que o robô varra e salve tudo automaticamente.</small>
            </div>

            <div style="margin-bottom:2rem;">
                <label style="display:block; font-weight:bold; margin-bottom:0.5rem; color:#C5A059; font-size:1.1rem;">
                    📋 Opção 2: Cole o Texto ou Código do Catálogo do WhatsApp
                </label>
                <textarea name="raw_text" rows="6" placeholder="Exemplo:
Buquê Premium com Mix de Flores (Cód 73) - R$ 580,00
Arranjo de Astromélia coloridas (Cód 95) - R$ 280,00
Buquê de Girassol e Astromélias (Cód 61) - R$ 200,00"
                          style="width:100%; padding:14px; border-radius:8px; background:#1a1a1a; border:1px solid #444; color:#fff; font-size:1rem; font-family:monospace;"></textarea>
                <small style="color:#888;">Você pode copiar a lista de produtos do WhatsApp e colar aqui direto.</small>
            </div>

            <button type="submit" class="btn" style="width:100%; height:55px; font-size:1.2rem; background:#8B263E; color:#FFF; font-weight:bold;">
                ⚡ EXTRAIR, CLONAR FOTOS E CADASTRAR TUDO NO BANCO
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer_public.php'; ?>
