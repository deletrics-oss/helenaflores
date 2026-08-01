<?php
/**
 * import_produtos400.php — Sincronizador Direto do Catálogo Helena Flores
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<div style='font-family:sans-serif; padding:20px; background:#FFF8F9; border-radius:12px; border:1px solid #FCE4EC;'>";
echo "<h2 style='color:#C2185B;'>🌸 Sincronizador de Catálogo — Helena Flores</h2>";

try {
    $catMap = [];
    $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE name=VALUES(name)");
    
    $catList = [
        'Rosas Colombianas' => 'rosas-colombianas',
        'Cestas Personalizadas' => 'cestas-personalizadas',
        'Buquês de Luxo' => 'buques-de-luxo',
        'Arranjos & Vasos' => 'arranjos-e-vasos',
        'KITS & Presentes' => 'kits-e-presentes',
        'Orquídeas & Plantas' => 'orquideas-e-plantas',
        'Girassóis & Flores' => 'girassois-e-flores'
    ];

    foreach ($catList as $catName => $catSlug) {
        $stmtCat->execute([$catName, $catSlug]);
        $stmtGet = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmtGet->execute([$catName]);
        $catMap[$catName] = $stmtGet->fetchColumn();
    }

    echo "<p style='color:#2E7D32;'>✅ Categorias sincronizadas com sucesso.</p>";

    $jsonRaw = <<<'JSON_DATA'
[
    {
        "id": 115,
        "name": "Arranjo",
        "slug": "arranjo",
        "price": 250,
        "description": "2 gerberas laranjas 1 lírio amarelo 3 margaridas 6 astromelias 3 rosas brancas Box",
        "image_path": "102-arranjo.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 129,
        "name": "Arranjo Branco com Flores Finas",
        "slug": "arranjo-branco-com-flores-finas",
        "price": 215,
        "description": "4 Boca de Leão branca 3 hortênsia 4 astromelias 2 Galhos de margaridas 2 galhos de lisiantus Vaso de vidro",
        "image_path": "117-arranjo-branco-com-flores-finas.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 105,
        "name": "Arranjo com 2 Rosas Colombiana",
        "slug": "arranjo-com-2-rosas-colombiana",
        "price": 90,
        "description": "Arranjo com 2 Rosas Colombiana",
        "image_path": "092-arranjo-com-2-rosas-colombiana.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 46,
        "name": "Arranjo com 3 orquideas brancas",
        "slug": "arranjo-com-3-orquideas-brancas",
        "price": 1400,
        "description": "3 orquideas brancas cascatas Vaso de vidro",
        "image_path": "032-arranjo-com-3-orquideas-brancas.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 108,
        "name": "Arranjo com 3 orquídeas pink",
        "slug": "arranjo-com-3-orquideas-pink",
        "price": 1200,
        "description": "3 orquídeas pink selecionadas",
        "image_path": "095-arranjo-com-3-orquideas-pink.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 106,
        "name": "Arranjo com 3 rosas brancas",
        "slug": "arranjo-com-3-rosas-brancas",
        "price": 200,
        "description": "3 rosas colombianas brancas folhagem verde embalagem brancas",
        "image_path": "093-arranjo-com-3-rosas-brancas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 32,
        "name": "Arranjo com 3 Rosas Colombianas",
        "slug": "arranjo-com-3-rosas-colombianas",
        "price": 150,
        "description": "3 Rosas Colombianas aberta a mão Folhagem verde e tango Embalagem",
        "image_path": "018-arranjo-com-3-rosas-colombianas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 57,
        "name": "Arranjo com Chocolate",
        "slug": "arranjo-com-chocolate",
        "price": 130,
        "description": "Arranjo floral acompanhado de caixa de chocolates",
        "image_path": "043-arranjo-com-chocolate.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 53,
        "name": "Arranjo com mini orquídea brancas",
        "slug": "arranjo-com-mini-orquidea-brancas",
        "price": 700,
        "description": "4 vasos de mini orquídeas brancas Vaso de vidro Casca",
        "image_path": "039-arranjo-com-mini-orquidea-brancas.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 98,
        "name": "Arranjo com rosas",
        "slug": "arranjo-com-rosas",
        "price": 800,
        "description": "Arranjo com 7 rosas manipuladas 4 galhos de lírios rosas 6 hortensias Gypsofila Vaso de vidro",
        "image_path": "085-arranjo-com-rosas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 31,
        "name": "Arranjo com Rosas vermelhas",
        "slug": "arranjo-com-rosas-vermelhas",
        "price": 450,
        "description": "18 rosas nacionais vermelhas Folhagem de pit Vaso de vidro",
        "image_path": "017-arranjo-com-rosas-vermelhas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 10,
        "name": "Arranjo de Astromélia coloridas (Cód 95)",
        "slug": "arranjo-de-astromelia-coloridas-cod-95",
        "price": 280,
        "description": "20 galhos de astromélias coloridas selecionadas, folhagem verde e vaso de vidro transparente (cerca de 45cm de altura).",
        "image_path": "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=800&q=80",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 40,
        "name": "Arranjo de flores",
        "slug": "arranjo-de-flores",
        "price": 280,
        "description": "Lirio (verificar cores) 2 gerberas 4 Rosas 4 flores do campo 2 cravina Rosa 4 astromelias Box",
        "image_path": "026-arranjo-de-flores.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 8,
        "name": "Arranjo de Flores do Campo e Eucalipto",
        "slug": "arranjo-de-flores-do-campo-e-eucalipto",
        "price": 260,
        "description": "Delicado arranjo composto por mix de flores do campo coloridas, eucalipto perfumado e gypsofilas montado em vaso cylindro de vidro transparente.",
        "image_path": "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=800&q=80",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 41,
        "name": "Arranjo de Flores Finas",
        "slug": "arranjo-de-flores-finas",
        "price": 350,
        "description": "1 Lirio Branco 2 gerberas 6 Rosas 4 galhos de crisântemos coloridas 6 astromelias coloridas 2 lisianthus Folhagem verde Vaso de vidro",
        "image_path": "027-arranjo-de-flores-finas.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 110,
        "name": "Arranjo de Rosa Colombiana",
        "slug": "arranjo-de-rosa-colombiana",
        "price": 70,
        "description": "Arranjo individual de Rosa Colombiana",
        "image_path": "097-arranjo-de-rosa-colombiana.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 118,
        "name": "Arranjo de Rosas",
        "slug": "arranjo-de-rosas",
        "price": 220,
        "description": "12 Rosas nacionais branca Folhagem verde Vaso de acrílico verde",
        "image_path": "105-arranjo-de-rosas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 130,
        "name": "Arranjo de Rosas e Astromélia branca",
        "slug": "arranjo-de-rosas-e-astromelia-branca",
        "price": 250,
        "description": "6 Rosas colombianas vermelhas manipuladas. 6 astromelias brancas Folhagem verde e tango Cachepot e embalagem",
        "image_path": "118-arranjo-de-rosas-e-astromelia-branca.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 30,
        "name": "Arranjo de Rosas e Lírio",
        "slug": "arranjo-de-rosas-e-lirio",
        "price": 400,
        "description": "7 Rosas colombianas 2 Lirios amarelo Folhagem Verde Cachepo",
        "image_path": "109-arranjo-de-rosas-e-lirio-1.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 87,
        "name": "Arranjo grande com astromelias rosas",
        "slug": "arranjo-grande-com-astromelias-rosas",
        "price": 280,
        "description": "20 galhos de astromelias rosas Folhagem verde Vaso de vidrolaço de cetim rosa",
        "image_path": "074-arranjo-grande-com-astromelias-rosas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 114,
        "name": "Arranjo no vaso de vidro",
        "slug": "arranjo-no-vaso-de-vidro",
        "price": 450,
        "description": "Arranjo no vaso de vidro",
        "image_path": "101-arranjo-no-vaso-de-vidro.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 107,
        "name": "Arranjo Pink de Rosas e Astromelia",
        "slug": "arranjo-pink-de-rosas-e-astromelia",
        "price": 350,
        "description": "10 Rosas nacionais Pink 7 astromélias cor de rosa Folhagem verde e fantasia Vaso de vidro e laço",
        "image_path": "094-arranjo-pink-de-rosas-e-astromelia.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 90,
        "name": "Arranjo Rosa",
        "slug": "arranjo-rosa",
        "price": 300,
        "description": "6 Rosas Pink 6 astromelias Rosa 8 crisântemos Rosa 4 hortênsia Box",
        "image_path": "077-arranjo-rosa.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 123,
        "name": "Arranjo Rose",
        "slug": "arranjo-rose",
        "price": 380,
        "description": "15 Rosas nacionais Cor de Rosa, Folhagem verde Vaso de vidro Laço de cetim",
        "image_path": "111-arranjo-rose.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 103,
        "name": "Arranjo Statis",
        "slug": "arranjo-statis",
        "price": 300,
        "description": "2 Anastasia roxa 3 boca de leão 2 gerberas 4 astromelias brancas 3 lisianthus 1 galho de Eucalipto Caixa kraft Lado cetim Embalagem rosé",
        "image_path": "090-arranjo-statis.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 48,
        "name": "Begonia",
        "slug": "begonia",
        "price": 150,
        "description": "Planta Begônia floridade",
        "image_path": "034-begonia.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 91,
        "name": "Box com 12 rosas nacionais",
        "slug": "box-com-12-rosas-nacionais",
        "price": 220,
        "description": "Buque com 12 rosas vermelhas nacional Folhagem verde e tango Box parda comprida",
        "image_path": "078-box-com-12-rosas-nacionais.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 70,
        "name": "Box com Girassol e Chandon",
        "slug": "box-com-girassol-e-chandon",
        "price": 300,
        "description": "Arranjo de Girassol Chandon Baby Balão estrela Ferreiro Roche 100g Box verde com tampa Embalagem e laço",
        "image_path": "057-box-com-girassol-e-chandon.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 76,
        "name": "Box de Flores",
        "slug": "box-de-flores",
        "price": 380,
        "description": "6 Rosas brancas 6 Rosas cor de rosa 4 astromelias brancas 4 astromelias Rosas Folhagem verde Roxinha Cachepo Rosa com tampa e laço",
        "image_path": "063-box-de-flores.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 95,
        "name": "Box Mãe",
        "slug": "box-mae",
        "price": 400,
        "description": "Bujudinho com 12 rosas coloridas e gypso no vidro Balão rosé Pelúcia de coração Espumante rosé Ferreiro Rocher 150g Box personalizada Dia das mães",
        "image_path": "082-box-mae.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 39,
        "name": "Bujudinho de Astromélias coloridas",
        "slug": "bujudinho-de-astromelias-coloridas",
        "price": 130,
        "description": "9 astromelias coloridas Folhagem de tango Vaso de vidro Laço (Cerca de 25cm)",
        "image_path": "025-bujudinho-de-astromelias-coloridas.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 122,
        "name": "Bujudinho de Rosa e Girasol",
        "slug": "bujudinho-de-rosa-e-girasol",
        "price": 250,
        "description": "5 Rosas vermelhas colombiana 4 girassol Folhagem verde e aspargo 3 galhos de ruscos",
        "image_path": "110-bujudinho-de-rosa-e-girasol.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 102,
        "name": "Buquê",
        "slug": "buque",
        "price": 180,
        "description": "1 lirio 3 cravinas 1 gerbera 1 lisianthus Eucalipto 2 anastásias Folhagem verde e tango Kraft Cru e Laço de corda",
        "image_path": "089-buque.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 81,
        "name": "Buquê Amor Vibrante",
        "slug": "buque-amor-vibrante",
        "price": 260,
        "description": "8 Rosas Colombianas vermelhas 6 astromelias vermelhas Gypsofila Folhagem verde pit Embalagem e laço",
        "image_path": "068-buque-amor-vibrante.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 99,
        "name": "Buquê Angélica",
        "slug": "buque-angelica",
        "price": 200,
        "description": "Buquê com 10 gérberas Rafaello",
        "image_path": "086-buque-angelica.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 121,
        "name": "Buquê com 10 Rosas Nacional",
        "slug": "buque-com-10-rosas-nacional",
        "price": 200,
        "description": "Buquê com 10 Rosas nacionais vermelhas Folhagem verde e tango",
        "image_path": "108-buque-com-10-rosas-nacional.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 1,
        "name": "Buquê com 12 Colombianas",
        "slug": "buque-com-12-colombianas",
        "price": 300,
        "description": "Buquê de 12 Rosas Colombianas",
        "image_path": "001-buque-com-12-colombianas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 117,
        "name": "Buquê com 12 Rosas e gypsophila",
        "slug": "buque-com-12-rosas-e-gypsophila",
        "price": 260,
        "description": "Buquê com 12 rosas vermelhas nacional 2 galhos de gypsofila Folhagem verde Embalagem",
        "image_path": "104-buque-com-12-rosas-e-gypsophila.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 21,
        "name": "Buquê com 15 rosas",
        "slug": "buque-com-15-rosas",
        "price": 300,
        "description": "15 rosas nacionais",
        "image_path": "007-buque-com-15-rosas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 22,
        "name": "Buquê com 15 Rosas amarelas",
        "slug": "buque-com-15-rosas-amarelas",
        "price": 300,
        "description": "Buquê com 15 Rosas amarelas",
        "image_path": "008-buque-com-15-rosas-amarelas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 127,
        "name": "Buquê com 18 rosas nacionais",
        "slug": "buque-com-18-rosas-nacionais",
        "price": 360,
        "description": "Buquê 18 Rosas nacionais vermelha (temos outras cores *consultar*)",
        "image_path": "115-buque-com-18-rosas-nacionais.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 80,
        "name": "Buquê com 20 Rosas Colombianas",
        "slug": "buque-com-20-rosas-colombianas",
        "price": 650,
        "description": "20 rosas colombianas vermelhas Folhagem de ruscos em volta Embalagem e laço",
        "image_path": "067-buque-com-20-rosas-colombianas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 104,
        "name": "Buquê com 24 rosas colombianas",
        "slug": "buque-com-24-rosas-colombianas",
        "price": 700,
        "description": "12 rosas vermelhas 12 rosas brancas colombianas",
        "image_path": "091-buque-com-24-rosas-colombianas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 82,
        "name": "Buquê com 24 Rosas importadas",
        "slug": "buque-com-24-rosas-importadas",
        "price": 600,
        "description": "24 Rosas colombinas vermelhas Embalagem Laço",
        "image_path": "069-buque-com-24-rosas-importadas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 69,
        "name": "Buquê com 24 rosas nacionais",
        "slug": "buque-com-24-rosas-nacionais",
        "price": 360,
        "description": "Buquê com 24 rosas nacionais",
        "image_path": "056-buque-com-24-rosas-nacionais.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 74,
        "name": "Buquê com 3 lírios",
        "slug": "buque-com-3-lirios",
        "price": 210,
        "description": "Buquê de 3 lírios",
        "image_path": "061-buque-com-3-lirios.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 100,
        "name": "Buquê com 40 rosas colombianas",
        "slug": "buque-com-40-rosas-colombianas",
        "price": 950,
        "description": "40 botões de rosas Embalagem e laço personalizado Surpreenda seu amor!",
        "image_path": "087-buque-com-40-rosas-colombianas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 120,
        "name": "Buquê com Cravinas Coloridas",
        "slug": "buque-com-cravinas-coloridas",
        "price": 150,
        "description": "10 galhos de cravinas Folhagens verdes e tango Papel rosa + papel comeia rosé Laço de corda",
        "image_path": "107-buque-com-cravinas-coloridas.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 124,
        "name": "Buque com girassois",
        "slug": "buque-com-girassois",
        "price": 150,
        "description": "3 girassol eucaliptos gypso embalagem",
        "image_path": "112-buque-com-girassois.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 84,
        "name": "Buquê com Lírios coloridos",
        "slug": "buque-com-lirios-coloridos",
        "price": 380,
        "description": "5 galhos de lírios coloridos 4 astromelias coloridas Folhagem Embalagem + laço",
        "image_path": "071-buque-com-lirios-coloridos.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 71,
        "name": "Buquê com Rosa e astromélias",
        "slug": "buque-com-rosa-e-astromelias",
        "price": 300,
        "description": "12 Rosas nacionais vermelhas 6 astromelias",
        "image_path": "058-buque-com-rosa-e-astromelias.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 2,
        "name": "Buquê com rosas colombianas",
        "slug": "buque-com-rosas-colombianas",
        "price": 320,
        "description": "12 Rosas colombianas Folhagem verde e gypsofila Embalagem kraft e laço branco",
        "image_path": "002-buque-com-rosas-colombianas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 38,
        "name": "Buquê com Rosas e Girassóis",
        "slug": "buque-com-rosas-e-girassois",
        "price": 450,
        "description": "8 Rosas Colombianas manipuladas 8 girassóis",
        "image_path": "024-buque-com-rosas-e-girassois.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 23,
        "name": "Buquê com Rosas Rosé",
        "slug": "buque-com-rosas-rose",
        "price": 240,
        "description": "12 Rosas nacionais cor de rosa",
        "image_path": "009-buque-com-rosas-rose.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 61,
        "name": "Buque com tulipas e rosas inglesas",
        "slug": "buque-com-tulipas-e-rosas-inglesas",
        "price": 680,
        "description": "20 tulipas rosas 3 rosas inglesas Gypso Ruscos",
        "image_path": "047-buque-com-tulipas-e-rosas-inglesas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 125,
        "name": "Buquê de 60 rosas colombianas",
        "slug": "buque-de-60-rosas-colombianas",
        "price": 1500,
        "description": "60 rosas colombianas Embalagem branca Laço de fita vermelho e branco",
        "image_path": "113-buque-de-60-rosas-colombianas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 119,
        "name": "Buquê de flores finas",
        "slug": "buque-de-flores-finas",
        "price": 100,
        "description": "3 astromelias coloridas 3 galhos de flores do campo 2 Lisianhus 1 gerbera Folhagem verde e tango Embalagem e laço",
        "image_path": "106-buque-de-flores-finas.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 79,
        "name": "Buquê de Flores Silvestres",
        "slug": "buque-de-flores-silvestres",
        "price": 380,
        "description": "8 astromelias coloridas 10 margaridas coloridas 4 hortênsia 4 gerberas 1 lirio",
        "image_path": "066-buque-de-flores-silvestres.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 78,
        "name": "Buque de gerberas colorida",
        "slug": "buque-de-gerberas-colorida",
        "price": 480,
        "description": "6 rosas colombianas pink 3 boca de leão rosa 1 lírio rosa 3 gerberas rosa 4 margaridas lilás 4 astromelias rosa 3 lisianthus rosa",
        "image_path": "065-buque-de-gerberas-colorida.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 36,
        "name": "Buque de girassol",
        "slug": "buque-de-girassol",
        "price": 150,
        "description": "6 girassóis Folhagem verde e tango Kraft e laço",
        "image_path": "022-buque-de-girassol.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 37,
        "name": "Buquê de Girassol e Astromélias",
        "slug": "buque-de-girassol-e-astromelias",
        "price": 200,
        "description": "6 girassóis 4 astromelias brancas",
        "image_path": "023-buque-de-girassol-e-astromelias.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 11,
        "name": "Buquê de Girassol e Astromélias (Cód 61)",
        "slug": "buque-de-girassol-e-astromelias-cod-61",
        "price": 200,
        "description": "Um buquê encantador com 6 vibrantes girassóis frescos e 4 delicadas astromélias brancas envoltos em papel kraft especial.",
        "image_path": "https://images.unsplash.com/photo-1597848212624-a19eb35e2651?w=800&q=80",
        "category": "Buquês de Luxo"
    },
    {
        "id": 25,
        "name": "Buque de Mix de Flores",
        "slug": "buque-de-mix-de-flores",
        "price": 580,
        "description": "4 Rosas Colombianas 3 galhos de lirios coloridos 10 astromélias 4 gerbera colorida 4 Hortensia",
        "image_path": "011-buque-de-mix-de-flores.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 113,
        "name": "Buquê de Rosa Branca nacional",
        "slug": "buque-de-rosa-branca-nacional",
        "price": 240,
        "description": "12 Rosas Nacional brancas Folhagem verde e tango",
        "image_path": "100-buque-de-rosa-branca-nacional.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 77,
        "name": "Buquê de Rosas com astromelias",
        "slug": "buque-de-rosas-com-astromelias",
        "price": 350,
        "description": "6 Rosas Pink colombiana 7 astromelias brancas Folhagem verde e gypsophila Embalagem e laço",
        "image_path": "064-buque-de-rosas-com-astromelias.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 88,
        "name": "Buquê de Rosas Manipuladas",
        "slug": "buque-de-rosas-manipuladas",
        "price": 210,
        "description": "7 Rosas colombianas manipuladas Folhagem verde e tango Embalagem e tango (Naturais)",
        "image_path": "075-buque-de-rosas-manipuladas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 3,
        "name": "Buquê de Rosas pink colombiana",
        "slug": "buque-de-rosas-pink-colombiana",
        "price": 320,
        "description": "12 rosas colombianas Folhagem verde e tango Embalagem e laço rosa",
        "image_path": "003-buque-de-rosas-pink-colombiana.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 60,
        "name": "Buque de tulipas rosa",
        "slug": "buque-de-tulipas-rosa",
        "price": 350,
        "description": "10 tulipas, ruscus e gypso Embalagem papel jornal (Verificar cores disponíveis)",
        "image_path": "046-buque-de-tulipas-rosa.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 72,
        "name": "Buquê e Ferreiro Rocher",
        "slug": "buque-e-ferreiro-rocher",
        "price": 280,
        "description": "Buquê com 12 Rosas vermelhas nacional Ferreiro Rocher 150g",
        "image_path": "059-buque-e-ferreiro-rocher.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 101,
        "name": "Buquê Encanto Inesquecível",
        "slug": "buque-encanto-inesquecivel",
        "price": 600,
        "description": "10 rosas brancas 5 gerberas rosas 3 gerbras amarelas 4 Margaridas amarelas 4 margaridas lilás 3 margaridas brancas 2 lírio vermelho 12 astromelias coloridas",
        "image_path": "088-buque-encanto-inesquecivel.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 86,
        "name": "Buquê Flores Silvestre",
        "slug": "buque-flores-silvestre",
        "price": 180,
        "description": "3 crisântemo grande lilás 5 galhos de eucalipto 4 boca de leão Sempre viva Embalagem e laço Folhagem verde",
        "image_path": "073-buque-flores-silvestre.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 94,
        "name": "Buquê Gerberas e Rosas Brancas",
        "slug": "buque-gerberas-e-rosas-brancas",
        "price": 250,
        "description": "Buquê com Gerberas: 2 rosa e 2 vermelhas 6 Rosas brancas 3 Hortênsia Astromelias: 2 vermelhas, 2 amarelas, 2 rosas",
        "image_path": "081-buque-gerberas-e-rosas-brancas.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 109,
        "name": "Buquê Jasmine",
        "slug": "buque-jasmine",
        "price": 300,
        "description": "1 lírio rosa 6 galhos de lisianthus Statis roxinha + Caspia 10 astromelias roxa Embalagem e laço",
        "image_path": "096-buque-jasmine.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 24,
        "name": "Buquê Lily",
        "slug": "buque-lily",
        "price": 250,
        "description": "1 galhos de lírios rosa e 1 lírio branco 12 astromelias coloridas Folhagem e lirios",
        "image_path": "010-buque-lily.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 9,
        "name": "Buquê Premium com Mix de Flores (Cód 73)",
        "slug": "buque-premium-com-mix-de-flores-cod-73",
        "price": 580,
        "description": "4 Rosas Colombianas, 3 galhos de lírios coloridos, 10 astromélias, 4 gérberas coloridas e folhagens nobres em embalagem especial de presente.",
        "image_path": "https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80",
        "category": "Buquês de Luxo"
    },
    {
        "id": 112,
        "name": "Buquê Primeira",
        "slug": "buque-primeira",
        "price": 200,
        "description": "5 rosas claras 5 rosas brancas 3 astromelias rosa 3 astronelias brancas Folhagem verde",
        "image_path": "099-buque-primeira.jpg",
        "category": "Buquês de Luxo"
    },
    {
        "id": 26,
        "name": "Buquê rosa",
        "slug": "buque-rosa",
        "price": 280,
        "description": "5 Rosas cor de rosa 5 rosa amarela 4 astromelias Rosa 4 amarela 4 hortênsia",
        "image_path": "012-buque-rosa.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 12,
        "name": "Caixa Surpresa com Rosas Colombianas Glam",
        "slug": "caixa-surpresa-com-rosas-colombianas-glam",
        "price": 389.9,
        "description": "Caixa exclusiva cartonada com 18 Rosas Colombianas vermelhas selecionadas e acabamento de cetim de luxo.",
        "image_path": "https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80",
        "category": "Rosas Colombianas"
    },
    {
        "id": 93,
        "name": "Cesta",
        "slug": "cesta",
        "price": 320,
        "description": "Arranjo com 2 rosas colombianas Urso P Ferreiro Collection Cesta de vime",
        "image_path": "080-cesta.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 116,
        "name": "Cesta com Arranjo e chocolate",
        "slug": "cesta-com-arranjo-e-chocolate",
        "price": 200,
        "description": "Arranjo com Rosa colombiana Ferreiro Rocher Plaquinha Cestinha",
        "image_path": "103-cesta-com-arranjo-e-chocolate.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 4,
        "name": "Cesta com Chambinho do Amor",
        "slug": "cesta-com-chambinho-do-amor",
        "price": 350,
        "description": "Cesta especial recheada de carinho com rosas e mimos",
        "image_path": "004-cesta-com-chambinho-do-amor.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 75,
        "name": "Cesta com Kalandiva",
        "slug": "cesta-com-kalandiva",
        "price": 180,
        "description": "Cesta com flor Kalandiva",
        "image_path": "062-cesta-com-kalandiva.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 96,
        "name": "Cesta com Lirio e espumante",
        "slug": "cesta-com-lirio-e-espumante",
        "price": 380,
        "description": "1 Lirio plantado Plaquinha Ferreiro Rocher 150g Mini espumante rosé Caixa",
        "image_path": "083-cesta-com-lirio-e-espumante.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 5,
        "name": "Cesta com Rosa e Urso",
        "slug": "cesta-com-rosa-e-urso",
        "price": 320,
        "description": "Arranjo de Rosa Colombiana Urso pequeno Ferreiro Rocher 100g Cesta",
        "image_path": "005-cesta-com-rosa-e-urso.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 73,
        "name": "Cesta com Rosas e Chandon",
        "slug": "cesta-com-rosas-e-chandon",
        "price": 400,
        "description": "Arranjo com Rosas colombianas Plaquinha de coração Caixa especialmente para você! Ferreiro Rocher 150g",
        "image_path": "060-cesta-com-rosas-e-chandon.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 27,
        "name": "Cesta de café",
        "slug": "cesta-de-cafe",
        "price": 380,
        "description": "Arranjo de girassol 4 mini croissant 4 mini pão de queijo 4 Carolina",
        "image_path": "051-cesta-de-cafe-1.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 65,
        "name": "Cesta de Café com girassol",
        "slug": "cesta-de-cafe-com-girassol",
        "price": 380,
        "description": "Arranjo de girassol Mamão Maça Uva Torrada Sucrilhos Pão francês Bisnaga 4 paes de queijo Frios 4 fatias queijo, 4 fatias de presunto Suco Iorgute Sache de Cappuccino Cesta de vime Embalagem e laço",
        "image_path": "052-cesta-de-cafe-com-girassol.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 28,
        "name": "Cesta de café com rosa",
        "slug": "cesta-de-cafe-com-rosa",
        "price": 380,
        "description": "Arranjo de rosa Torrada Sucrilhos Maça Uva Mamão Sache de Cappuccino Suco Iorgute Requeijão 4 fatias de queijo, 4 fatias de presunto Pão francês Bisnaga 4 paes de queijo",
        "image_path": "014-cesta-de-cafe-com-rosa.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 29,
        "name": "Cesta de Café Premium",
        "slug": "cesta-de-cafe-premium",
        "price": 400,
        "description": "Arranjo de rosa Torrada Sucrilhos Maça Sache de Cappuccino Suco Iorgute Requeijão Nutella 4 fatias de queijo, 4 fatias de presunto Pão francês Bisnaga 4 paes de queijo 4 croissant 3 Carolina",
        "image_path": "015-cesta-de-cafe-premium.jpg",
        "category": "Cestas Personalizadas"
    },
    {
        "id": 43,
        "name": "Coração de pelúcia",
        "slug": "coracao-de-pelucia",
        "price": 45,
        "description": "13cm",
        "image_path": "029-coracao-de-pelucia.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 42,
        "name": "Emoji pelúcia",
        "slug": "emoji-pelucia",
        "price": 40,
        "description": "Cada",
        "image_path": "028-emoji-pelucia.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 85,
        "name": "Espumante Rose Monte Pascoal",
        "slug": "espumante-rose-monte-pascoal",
        "price": 60,
        "description": "Baby",
        "image_path": "072-espumante-rose-monte-pascoal.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 34,
        "name": "Ferreiro Rocher 100g",
        "slug": "ferreiro-rocher-100g",
        "price": 60,
        "description": "Ferreiro Rocher 100g",
        "image_path": "020-ferreiro-rocher-100g.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 128,
        "name": "Ferreiro Rocher 150g",
        "slug": "ferreiro-rocher-150g",
        "price": 70,
        "description": "Ferreiro Rocher 150g",
        "image_path": "116-ferreiro-rocher-150g.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 33,
        "name": "Ferreiro Rocher 50g",
        "slug": "ferreiro-rocher-50g",
        "price": 25,
        "description": "Caixa de bombons Ferreiro Rocher 50g",
        "image_path": "019-ferreiro-rocher-50g.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 35,
        "name": "Ferreiro Rocher Collection 77g",
        "slug": "ferreiro-rocher-collection-77g",
        "price": 65,
        "description": "Ferreiro Rocher Collection 77g",
        "image_path": "021-ferreiro-rocher-collection-77g.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 59,
        "name": "Girassol Solidário com Ferreiro Collection",
        "slug": "girassol-solidario-com-ferreiro-collection",
        "price": 90,
        "description": "Girassol com caixa Ferrero Collection",
        "image_path": "045-girassol-solidario-com-ferreiro-collection.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 68,
        "name": "Kit 2 Rosas e Mini Ferreiro Rocher",
        "slug": "kit-2-rosas-e-mini-ferreiro-rocher",
        "price": 80,
        "description": "2 rosas colombianas vermelhas Ferreiro Rocher 50g",
        "image_path": "055-kit-2-rosas-e-mini-ferreiro-rocher.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 111,
        "name": "Kit Amor Perfeito",
        "slug": "kit-amor-perfeito",
        "price": 220,
        "description": "1 Arranjo de Rosa 1 Plaquinha 1 Espumante rosé 2 KitKat 2 Suflair Caixa kraft",
        "image_path": "098-kit-amor-perfeito.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 20,
        "name": "Kit dia dos namorados",
        "slug": "kit-dia-dos-namorados",
        "price": 1300,
        "description": "1 buque com 12 rosas colombianas vermelhas + gypso 1 box de 6 rosas e 4 astromelias brancas e gypso 1 pacote de pétalas 1 urso médio 1 Chandon G 1 cartão de amor G Buque de balões (3 corações M e 2 pequenos)",
        "image_path": "006-kit-dia-dos-namorados.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 6,
        "name": "Kit Dia dos Namorados & Romântico",
        "slug": "kit-dia-dos-namorados-romantico",
        "price": 380,
        "description": "Kit romântico completo com arranjo especial de 18 rosas colombianas vermelhas em vaso de vidro, caixa Ferrero Rocher 12 un e vinho/espumante selecionado.",
        "image_path": "https://images.unsplash.com/photo-1533616688419-b7a585564566?w=800&q=80",
        "category": "KITS & Presentes"
    },
    {
        "id": 67,
        "name": "Kit maternidade",
        "slug": "kit-maternidade",
        "price": 530,
        "description": "1 mini orquídea (temos outras cores, consultar) Colônia Óleo Shampoo Pomada Lencinho Cesta de vime Embalagem",
        "image_path": "054-kit-maternidade.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 55,
        "name": "Kit Maternidade Clássico",
        "slug": "kit-maternidade-classico",
        "price": 380,
        "description": "Arranjo de astronelias coloridas (opções com 1 cor só) Urso Cesta de vime Colônia Lencinho Pomada Embalagem e laço",
        "image_path": "041-kit-maternidade-classico.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 56,
        "name": "Kit maternidade Premium",
        "slug": "kit-maternidade-premium",
        "price": 500,
        "description": "1 mini orquídea Urso p Colônia Lencinho Pomada Creme de hidratação Embalagem e laço",
        "image_path": "042-kit-maternidade-premium.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 64,
        "name": "Kit Natal",
        "slug": "kit-natal",
        "price": 180,
        "description": "Pinheiro Poinsettia Cesta de vime",
        "image_path": "050-kit-natal.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 63,
        "name": "Kit Natalino",
        "slug": "kit-natalino",
        "price": 180,
        "description": "Poinsettia Pinguim natal Cesta de vime",
        "image_path": "049-kit-natalino.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 50,
        "name": "Lirio amarelo",
        "slug": "lirio-amarelo",
        "price": 150,
        "description": "Lírio amarelo em vaso",
        "image_path": "036-lirio-amarelo.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 49,
        "name": "Lirio rosa",
        "slug": "lirio-rosa",
        "price": 150,
        "description": "Plantado",
        "image_path": "035-lirio-rosa.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 51,
        "name": "Mini Orquidea Branca",
        "slug": "mini-orquidea-branca",
        "price": 250,
        "description": "Cerca de 45cm",
        "image_path": "037-mini-orquidea-branca.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 52,
        "name": "Mini orquidea pink",
        "slug": "mini-orquidea-pink",
        "price": 250,
        "description": "(Imagem ilustrativa)",
        "image_path": "038-mini-orquidea-pink.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 83,
        "name": "Nutella P",
        "slug": "nutella-p",
        "price": 30,
        "description": "Nutella pote 140g",
        "image_path": "070-nutella-p.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 45,
        "name": "Orquidea",
        "slug": "orquidea",
        "price": 450,
        "description": "Orquídea Phalaenopsis selecionada",
        "image_path": "031-orquidea.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 126,
        "name": "Orquídea Branca Cascata",
        "slug": "orquidea-branca-cascata",
        "price": 350,
        "description": "(Cerca de 75cm)",
        "image_path": "114-orquidea-branca-cascata.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 7,
        "name": "Orquídea Phalaenopsis Premium em Vaso",
        "slug": "orquidea-phalaenopsis-premium-em-vaso",
        "price": 290,
        "description": "Orquídea Phalaenopsis nobre com duas hastes floridas em charmoso vaso de cerâmica artesanal e acabamento com musgo natural.",
        "image_path": "https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?w=800&q=80",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 97,
        "name": "Orquidea Phale média",
        "slug": "orquidea-phale-media",
        "price": 250,
        "description": "Orquídea Phalaenopsis média",
        "image_path": "084-orquidea-phale-media.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 47,
        "name": "Orquídea pink",
        "slug": "orquidea-pink",
        "price": 350,
        "description": "Orquídea cor de rosa vibrante",
        "image_path": "033-orquidea-pink.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 62,
        "name": "Poinssetia",
        "slug": "poinssetia",
        "price": 100,
        "description": "35cm-40cm",
        "image_path": "048-poinssetia.jpg",
        "category": "Orquídeas & Plantas"
    },
    {
        "id": 58,
        "name": "Rosa e Ferrero 50g",
        "slug": "rosa-e-ferrero-50g",
        "price": 50,
        "description": "1 rosa colombiana Ferreiro rocher 50g",
        "image_path": "044-rosa-e-ferrero-50g.jpg",
        "category": "Rosas Colombianas"
    },
    {
        "id": 89,
        "name": "Urso Grande",
        "slug": "urso-grande",
        "price": 350,
        "description": "Aproximadamente 40cm×45cm",
        "image_path": "076-urso-grande.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 44,
        "name": "Urso p",
        "slug": "urso-p",
        "price": 70,
        "description": "Mini urso 18cm",
        "image_path": "030-urso-p.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 92,
        "name": "Vaso de vidro",
        "slug": "vaso-de-vidro",
        "price": 150,
        "description": "Vaso de vidro para arranjos",
        "image_path": "079-vaso-de-vidro.jpg",
        "category": "Arranjos & Vasos"
    },
    {
        "id": 66,
        "name": "Vinho Reservado Carmenere",
        "slug": "vinho-reservado-carmenere",
        "price": 100,
        "description": "Vinho Concha y Toro Reservado Carmenere 750ml",
        "image_path": "053-vinho-reservado-carmenere.jpg",
        "category": "KITS & Presentes"
    },
    {
        "id": 54,
        "name": "Violeta na cesta",
        "slug": "violeta-na-cesta",
        "price": 70,
        "description": "Vaso de violeta na cesta",
        "image_path": "040-violeta-na-cesta.jpg",
        "category": "Cestas Personalizadas"
    }
]
JSON_DATA;

    $products = json_decode($jsonRaw, true);

    $stmtInsert = $pdo->prepare("INSERT INTO products 
        (id, category_id, name, slug, description, sku, price, image_path, active, stock_qty, featured) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 999, ?) 
        ON DUPLICATE KEY UPDATE 
            category_id = VALUES(category_id),
            name = VALUES(name),
            slug = VALUES(slug),
            price = VALUES(price),
            description = VALUES(description),
            image_path = VALUES(image_path),
            active = 1");

    $count = 0;
    foreach ($products as $idx => $p) {
        $id = $p['id'];
        $catName = $p['category'] ?? 'Rosas Colombianas';
        $catId = $catMap[$catName] ?? $catMap['Rosas Colombianas'];
        $name = trim($p['name']);
        $slug = $p['slug'];
        $desc = trim($p['description']);
        $price = floatval($p['price']);
        $imagePath = $p['image_path'];
        $sku = 'HF-WA-' . strtoupper(substr(md5($name), 0, 6));
        $featured = ($idx < 20) ? 1 : 0;

        $stmtInsert->execute([
            $id,
            $catId,
            $name,
            $slug,
            $desc,
            $sku,
            $price,
            $imagePath,
            $featured
        ]);
        $count++;
    }

    echo "<div style='background:#E8F5E9; color:#2E7D32; padding:15px; border-radius:8px; margin-top:15px;'>";
    echo "🎉 <strong>SUCESSO ABSOLUTO! {$count} Produtos Alinhados e Sincronizados com IDs e Imagens!</strong>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background:#FFEBEE; color:#C2185B; padding:15px; border-radius:8px;'>";
    echo "❌ Erro ao sincronizar: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div>";
