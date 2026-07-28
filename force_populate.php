<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// --- 1. GARANTE A TABELA ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        category VARCHAR(50) DEFAULT 'Geral',
        image_url VARCHAR(255),
        link_url VARCHAR(255),
        video_url VARCHAR(255),
        tags TEXT,
        related_products TEXT,
        ai_instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    die("Erro ao criar tabela: " . $e->getMessage());
}

$knowledge = [
    [
        'title' => 'Manual Visual de Inicialização',
        'category' => 'Tutoriais',
        'content' => "Veja como ligar e configurar seu Fight Arcade pela primeira vez com este guia visual completo.",
        'link_url' => 'https://www.canva.com/design/DAFB8VIBPXU/xyySxdmR19FY8lknZ3gJLg/watch?utm_content=DAFB8VIBPXU&utm_campaign=designshare&utm_medium=link&utm_source=editor',
        'tags' => 'como ligar, primeiro uso, inicialização, canva, tutorial',
        'ai_instructions' => 'Sempre envie este link para novos clientes que acabaram de receber o produto.'
    ],
    [
        'title' => 'Central de Vídeo Manuais',
        'category' => 'Manuais',
        'content' => "Todos os nossos tutoriais em vídeo estão centralizados em nosso portal oficial.",
        'video_url' => 'https://www.fightarcade.com.br/videomanual/',
        'tags' => 'vídeo, aula, tutorial, youtube, como fazer',
        'ai_instructions' => 'Direcione o cliente para este link se ele preferir aprender por vídeo.'
    ],
    [
        'title' => 'Catálogo de Estampas e Artes',
        'category' => 'Personalização',
        'content' => "Escolha sua arte favorita ou envie a sua. Temos opções de diversos jogos clássicos.",
        'link_url' => 'https://acesse.one/fightarcadeestampa',
        'tags' => 'estampa, arte, personalização, catálogo',
        'ai_instructions' => 'ITEM CRÍTICO: Ofereça este link proativamente quando o cliente demonstrar interesse em comprar.'
    ],
    [
        'title' => 'Comando não funciona',
        'category' => 'Suporte Técnico',
        'content' => "Verifique os fios na parte traseira (pode ter soltado). Com o fliperama ligado, veja se a placa principal tem um LED aceso.",
        'tags' => 'comando, parou, não mexe, alavanca',
        'ai_instructions' => 'Instrua o cliente a abrir a tampa traseira com cuidado.'
    ],
    [
        'title' => 'Comando andando sozinho',
        'category' => 'Suporte Técnico',
        'content' => "Mecânico: Micro-switch desalinhada. Óptico: Pode ser interferência de luz interna. Veja como ajustar no vídeo manual.",
        'video_url' => 'https://www.fightarcade.com.br/videomanual',
        'tags' => 'andando sozinho, fantasma, mexe sozinho',
        'ai_instructions' => 'Explique a diferença entre o ajuste mecânico e o óptico.'
    ],
    [
        'title' => 'Como alterar configurações dos botões',
        'category' => 'Tutoriais',
        'content' => "A configuração deve ser feita dentro de cada jogo. Siga nosso guia passo a passo.",
        'link_url' => 'https://sl1nk.com/alterarbotoesdentrodojogo',
        'tags' => 'configurar botões, mapear, trocar botões',
        'ai_instructions' => 'Envie o link do tutorial direto.'
    ],
    [
        'title' => 'Adicionar ou Remover Jogos',
        'category' => 'Tutoriais',
        'content' => "É possível gerenciar os jogos, mas exige cuidado para não danificar o sistema.",
        'link_url' => 'https://sl1nk.com/adicionarouremoverjogos',
        'tags' => 'mais jogos, colocar jogos, pendrive',
        'ai_instructions' => 'Alerte que o procedimento incorreto pode corromper o sistema.'
    ],
    [
        'title' => 'Manuais Oficiais (Texto e Vídeo)',
        'category' => 'Manuais',
        'content' => "Acesse nossos guias completos em formato de texto ou vídeo aulas.",
        'link_url' => 'https://l1nq.com/manualfightarcade',
        'video_url' => 'https://www.fightarcade.com.br/videomanual',
        'tags' => 'manual, guia, instrução, como usar',
        'ai_instructions' => 'Envie ambos os links para o cliente escolher o formato preferido.'
    ],
    [
        'title' => 'Política de Garantia e Pagamento',
        'category' => 'Institucional',
        'content' => "Garantia: 6 meses (placas) e 90 dias (componentes). Arrependimento: 7 dias. Pagamento: 12x no Cartão, PIX com desconto ou Boleto.",
        'tags' => 'garantia, pagamento, pix, parcelamento, troca',
        'ai_instructions' => 'Destaque sempre os 5% de desconto no PIX.'
    ]
];

foreach ($knowledge as $item) {
    $stmt = $pdo->prepare("INSERT INTO ai_knowledge (title, category, content, link_url, video_url, tags, ai_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $item['title'],
        $item['category'],
        $item['content'],
        $item['link_url'] ?? null,
        $item['video_url'] ?? null,
        $item['tags'],
        $item['ai_instructions']
    ]);
}

echo "<h1>✅ Cérebro da IA Alimentado com Sucesso!</h1>";
echo "<p>Agora você pode voltar para <a href='support.php'>support.php</a> para ver o resultado.</p>";
?>
