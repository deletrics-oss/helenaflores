<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$knowledge = [
    [
        'title' => 'Preços: Fliperama Completo (MDF)',
        'category' => 'Preços',
        'content' => "Fliperama de 1 Jogador em MDF: Mecânico (R$ 499) | Óptico (R$ 550).\nFliperama de 2 Jogadores em MDF: Mecânico (R$ 599) | Óptico (R$ 699).",
        'tags' => 'preço, valor, mdf, fliperama completo',
        'ai_instructions' => 'Destaque o custo-benefício do MDF e ofereça a versão de Metal para maior durabilidade.'
    ],
    [
        'title' => 'Preços: Fliperama Completo (Metal)',
        'category' => 'Preços',
        'content' => "Fliperama de 1 Jogador em Metal: Mecânico (R$ 599) | Óptico (R$ 650).\nFliperama de 2 Jogadores em Metal: Mecânico (R$ 699) | Óptico (R$ 799).",
        'tags' => 'preço, metal, resistente, ultra resistente',
        'ai_instructions' => 'Destaque que a versão em Metal é indestrutível e ideal para uso intenso.'
    ],
    [
        'title' => 'Preços: Controles USB (MDF)',
        'category' => 'Preços',
        'content' => "1 Jogador MDF: Mecânico (R$ 299) | Óptico (R$ 350) | Óptico Pico (R$ 450).\n2 Jogadores MDF: Mecânico (R$ 499) | Óptico (R$ 599).",
        'tags' => 'controle usb, pc, fightcade, mdf',
        'ai_instructions' => 'Destaque a Placa Pico para latência zero (menos de 1ms).'
    ],
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

echo "✅ Cérebro da IA Alimentado com Sucesso!";
?>
