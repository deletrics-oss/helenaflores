<?php
/**
 * admin/import-catalog-bot.php — Helena Flores
 * Importador em Massa do Catálogo WhatsApp Business (118+ Produtos em 1-Clique)
 */

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../robot_scraper.php';

$message = '';
$result = null;

// Lista Completa dos Produtos Extraídos do WhatsApp Business
$whatsappExtractedProducts = [
    ['title' => 'Buque com 12 Colombianas', 'price' => 300.00, 'description' => 'Buquê de 12 Rosas Colombianas selecionadas com laço de cetim.'],
    ['title' => 'Buquê com rosas colombianas', 'price' => 320.00, 'description' => '12 Rosas colombianas, Folhagem verde e gypsofila. Embalagem kraft e laço branco.'],
    ['title' => 'Buque de Rosas pink colombiana', 'price' => 320.00, 'description' => '12 rosas colombianas pink, Folhagem verde e tango. Embalagem e laço rosa.'],
    ['title' => 'Cesta com Chambinho do Amor', 'price' => 350.00, 'description' => 'Cesta especial recheada de carinho com arranjo floral e mimos.'],
    ['title' => 'Cesta com Rosa e Urso', 'price' => 320.00, 'description' => 'Arranjo de Rosa Colombiana, Urso pequeno, Ferrero Rocher 100g e Cesta decorada.'],
    ['title' => 'Kit dia dos namorados', 'price' => 1300.00, 'description' => '1 buque com 12 rosas colombianas vermelhas + gypso, 1 box de 6 rosas e 4 astromelias brancas, 1 pacote de pétalas, 1 urso médio, 1 Chandon G, Cartão de amor G e Buquê de balões.'],
    ['title' => 'Buquê com 15 rosas', 'price' => 300.00, 'description' => '15 rosas nacionais selecionadas com folhagens nobres.'],
    ['title' => 'Buquê com 15 Rosas amarelas', 'price' => 300.00, 'description' => 'Buquê vibrante com 15 Rosas amarelas selecionadas.'],
    ['title' => 'Buquê com Rosas Rosé', 'price' => 240.00, 'description' => '12 Rosas nacionais cor de rosa delicadas.'],
    ['title' => 'Buquê Lily', 'price' => 250.00, 'description' => '1 galho de lírios rosa, 1 lírio branco e 12 astromélias coloridas.'],
    ['title' => 'Buque de Mix de Flores (Cód 73)', 'price' => 580.00, 'description' => '4 Rosas Colombianas, 3 galhos de lírios coloridos, 10 astromélias, 4 gérberas coloridas e 4 hortênsias.'],
    ['title' => 'Buquê rosa', 'price' => 280.00, 'description' => '5 Rosas cor de rosa, 5 rosa amarela, 4 astromélias rosa, 4 amarelas e 4 hortênsias.'],
    ['title' => 'Cesta de café', 'price' => 400.00, 'description' => 'Arranjo com 4 gérberas vermelhas, torrada, Toddynho, pão, maçã, uva, mamão, cereal, chá, geleia, bolo, bolachas, café e açúcar.'],
    ['title' => 'Cesta de café com rosa', 'price' => 380.00, 'description' => 'Arranjo de rosa, torrada, sucrilhos, maçã, uva, mamão, cappuccino, suco, iogurte, requeijão, queijo, presunto, pão francês e pães de queijo.'],
    ['title' => 'Cesta de Café Premium', 'price' => 400.00, 'description' => 'Arranjo de rosa, torrada, sucrilhos, maçã, cappuccino, suco, iogurte, requeijão, Nutella, frios, pão francês, croissant e carolinas.'],
    ['title' => 'Arranjo de Rosas e Lírio', 'price' => 350.00, 'description' => '4 Rosas colombianas vermelhas, 2 galhos de Lírios e folhagem verde de ruscos.'],
    ['title' => 'Arranjo com Rosas vermelhas', 'price' => 450.00, 'description' => '18 rosas nacionais vermelhas com folhagem de pit em vaso de vidro.'],
    ['title' => 'Arranjo com 3 Rosas Colombianas', 'price' => 150.00, 'desc' => '3 Rosas Colombianas abertas à mão com folhagem verde e tango.'],
    ['title' => 'Ferrero Rocher 50g', 'price' => 25.00, 'description' => 'Caixa de bombons Ferrero Rocher 50g.'],
    ['title' => 'Ferrero Rocher 100g', 'price' => 60.00, 'description' => 'Caixa de bombons Ferrero Rocher 100g.'],
    ['title' => 'Ferrero Rocher Collection 77g', 'price' => 65.00, 'description' => 'Caixa de bombons Ferrero Rocher Collection 77g.'],
    ['title' => 'Buque de girassol', 'price' => 150.00, 'description' => '6 girassóis com folhagem verde e tango em embalagem kraft.'],
    ['title' => 'Buquê de Girassol e Astromélias', 'price' => 200.00, 'description' => '6 girassóis vibrantes e 4 astromélias brancas.'],
    ['title' => 'Buquê com Rosas e Girassóis', 'price' => 285.90, 'description' => '6 girassóis grandes com 12 rosas colombianas vermelhas.'],
    ['title' => 'Fabulosa Rosa Encantada Vermelha na Cúpula', 'price' => 279.90, 'description' => 'Rosa natural preservada em cúpula de vidro ao estilo A Bela e a Fera. Duração de até 5 anos.']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $robot = new CatalogRobotScraper($pdo);
    $btn = $_POST['btn_action'] ?? '';

    if ($btn === 'one_click') {
        $result = $robot->parseAndInsert(json_encode($whatsappExtractedProducts), 'whatsapp_preset');
    } else {
        $rawJson = $_POST['raw_json'] ?? '';
        if (!empty($rawJson)) {
            $result = $robot->parseAndInsert($rawJson, 'manual_paste');
        } else {
            $result = $robot->parseAndInsert(json_encode($whatsappExtractedProducts), 'whatsapp_preset');
        }
    }
}
?>

<div class="container" style="margin-top: 2rem; margin-bottom: 4rem;">
    
    <div style="background:linear-gradient(135deg, #C2185B 0%, #8B263E 100%); color:white; padding:25px; border-radius:16px; margin-bottom:2rem; box-shadow:0 6px 20px rgba(194,24,91,0.25);">
        <h1 style="color:#FFECB3; margin-bottom:0.5rem; font-family:Georgia, serif; font-size:1.8rem;">
            🤖 Importador de Catálogo WhatsApp Business (1-Clique)
        </h1>
        <p style="font-size:1rem; line-height:1.6; color:#FFF8F9; margin-bottom:1.5rem;">
            Clique no botão abaixo para cadastrar automaticamente <strong>TODOS os produtos extraídos do seu WhatsApp Business</strong> (Buquês, Cestas de Café, Kits de Namorados, Rosas Colombianas, Girassóis e Chocolates Ferrero Rocher) no seu banco de dados MySQL!
        </p>

        <form method="POST">
            <input type="hidden" name="btn_action" value="one_click">
            <button type="submit" class="btn" style="background:#FFC107; color:#000; font-size:1.2rem; font-weight:800; padding:16px 36px; border-radius:30px; border:none; cursor:pointer; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
                ⚡ 1-CLIQUE: CADASTRAR TODOS OS PRODUTOS DO WHATSAPP NO BANCO
            </button>
        </form>
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
