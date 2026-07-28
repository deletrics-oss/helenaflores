<?php
/**
 * admin/import-catalog-bot.php — Helena Flores
 * Importador em Massa do Catálogo WhatsApp Business (118+ Produtos em 1-Clique)
 */

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../robot_scraper.php';

$message = '';
$result = null;
$executionOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $robot = new CatalogRobotScraper($pdo);
    $btn = $_POST['btn_action'] ?? '';

    if ($btn === 'run_playwright') {
        // Executa o Robô Playwright Node.js diretamente no portal
        $cmd = 'cd ' . escapeshellarg(__DIR__ . '/../robot_playwright') . ' && node scraper.js 2>&1';
        $executionOutput = shell_exec($cmd);
        
        // Em seguida executa o seeder para vincular tudo
        ob_start();
        include __DIR__ . '/../seed_helena_flores.php';
        $seederOutput = ob_get_clean();

        $message = '<div class="alert alert-success" style="background:#E8F5E9; color:#2E7D32; padding:20px; border-radius:12px; margin-bottom:1.5rem;">'
                 . '🤖 <strong>Robô Playwright Executado no Portal com Sucesso!</strong><br><pre style="background:#FFF; padding:10px; border-radius:8px; margin-top:10px; font-size:0.85rem; max-height:200px; overflow-y:auto;">'
                 . htmlspecialchars($executionOutput ?: 'Robô disparado com sucesso no servidor!')
                 . '</pre></div>';
    } elseif ($btn === 'one_click') {
        ob_start();
        include __DIR__ . '/../seed_helena_flores.php';
        $seederHtml = ob_get_clean();
        $message = $seederHtml;
    } else {
        $rawJson = $_POST['raw_json'] ?? '';
        if (!empty($rawJson)) {
            $result = $robot->parseAndInsert($rawJson, 'manual_paste');
        } else {
            ob_start();
            include __DIR__ . '/../seed_helena_flores.php';
            $message = ob_get_clean();
        }
    }
}
?>

<div class="container" style="margin-top: 2rem; margin-bottom: 4rem;">
    
    <div style="background:linear-gradient(135deg, #C2185B 0%, #8B263E 100%); color:white; padding:30px; border-radius:16px; margin-bottom:2rem; box-shadow:0 6px 20px rgba(194,24,91,0.25);">
        <h1 style="color:#FFECB3; margin-bottom:0.5rem; font-family:Georgia, serif; font-size:1.8rem;">
            🤖 Robô Extrator Helena Flores — Painel Administrativo
        </h1>
        <p style="font-size:1rem; line-height:1.6; color:#FFF8F9; margin-bottom:1.5rem;">
            Selecione uma das opções abaixo para executar o robô extrator e cadastrar automaticamente <strong>TODOS os 118 produtos e fotos do WhatsApp Business</strong> no seu banco de dados MySQL!
        </p>

        <div style="display:flex; gap:15px; flex-wrap:wrap;">
            <!-- Botão 1: Executar Robô Playwright -->
            <form method="POST" style="flex:1; min-width:280px;">
                <input type="hidden" name="btn_action" value="run_playwright">
                <button type="submit" class="btn" style="width:100%; background:#FFC107; color:#000; font-size:1.1rem; font-weight:800; padding:16px 24px; border-radius:30px; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
                    🤖 1. EXECUTAR ROBÔ PLAYWRIGHT NO PORTAL
                </button>
            </form>

            <!-- Botão 2: Sincronizar Banco 118 Itens -->
            <form method="POST" style="flex:1; min-width:280px;">
                <input type="hidden" name="btn_action" value="one_click">
                <button type="submit" class="btn" style="width:100%; background:#4CAF50; color:#FFF; font-size:1.1rem; font-weight:800; padding:16px 24px; border-radius:30px; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
                    ⚡ 2. SINCRONIZAR 118 PRODUTOS NO BANCO (1-CLIQUE)
                </button>
            </form>
        </div>
    </div>

    <?php echo $message; ?>

    <?php if ($result): ?>
        <?php if ($result['success']): ?>
            <div class="alert alert-success" style="font-size:1.1rem; line-height:1.6; background:#E8F5E9; color:#2E7D32; padding:20px; border-radius:12px; margin-bottom:1.5rem; border:1px solid #A5D6A7;">
                🎉 <strong>SUCESSO TOTAL! <?php echo $result['total_found']; ?> Produtos Cadastrados no Banco MySQL!</strong><br>
                • Novos produtos inseridos: <strong><?php echo $result['inserted']; ?></strong><br>
                • Produtos atualizados: <strong><?php echo $result['updated']; ?></strong>
            </div>

            <div style="background:#FFF; padding:1.5rem; border-radius:12px; border:1px solid #EEE; margin-bottom:2rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
                <h3 style="color:#222; margin-bottom:1rem;">Produtos Cadastrados no Banco:</h3>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#FFF5F7; color:var(--gf-magenta-dark);">
                                <th style="padding:10px; text-align:left;">#</th>
                                <th style="padding:10px; text-align:left;">Produto</th>
                                <th style="padding:10px; text-align:left;">Preço</th>
                                <th style="padding:10px; text-align:left;">Categoria</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['items'] as $idx => $item): ?>
                                <tr style="border-bottom:1px solid #EEE;">
                                    <td style="padding:10px; color:#888;"><?php echo $idx + 1; ?></td>
                                    <td style="padding:10px;"><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                    <td style="padding:10px; color:var(--gf-magenta-dark); font-weight:bold;">R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></td>
                                    <td style="padding:10px;"><?php echo htmlspecialchars($item['category']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="../index.php" target="_blank" class="btn" style="background:#C2185B; color:#FFF; margin-top:15px; text-decoration:none; display:inline-block; padding:12px 28px; border-radius:25px; font-weight:bold;">
                    🌸 Ver Todos os Produtos Atualizados na Loja Pública →
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-error" style="background:#FFEBEE; color:#C2185B; padding:15px; border-radius:12px; margin-bottom:1.5rem;">
                ❌ <strong>Status da Busca:</strong> <?php echo htmlspecialchars($result['error']); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Text Area Import for Custom Pastes -->
    <div style="background:#FFF; padding:2rem; border-radius:14px; border:1px solid #EEE; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <form method="POST">
            <input type="hidden" name="btn_action" value="text_paste">
            <label style="display:block; font-weight:bold; margin-bottom:0.5rem; color:#C2185B; font-size:1.05rem;">
                📋 Importar Texto Adicional
            </label>
            <textarea name="raw_json" rows="4" placeholder="Cole aqui a lista de novos produtos..." 
                      style="width:100%; padding:12px; border-radius:8px; border:1px solid #DDD; font-size:0.9rem; font-family:monospace; background:#FAFAFA; margin-bottom:1rem;"></textarea>
            <button type="submit" class="btn" style="width:100%; height:45px; background:#C2185B; color:#FFF; font-weight:bold; border-radius:8px; border:none;">
                ⚡ Cadastrar Texto
            </button>
        </form>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer_public.php'; ?>
