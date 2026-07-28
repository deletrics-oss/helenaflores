<?php
/**
 * seed_helena_flores.php — Semeador Completo dos 118 Produtos com Nomes Exatos de Fotos
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

function makeSlug($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'item-' . rand(10000, 99999) : $text;
}

function detectCatName($title) {
    if (preg_match('/cesta|café|cafe/i', $title)) return 'Cestas Personalizadas';
    if (preg_match('/rosa|colombiana/i', $title)) return 'Rosas Colombianas';
    if (preg_match('/buquê|buque|tulipa|tulipas|lily|angelica|margarida/i', $title)) return 'Buquês de Luxo';
    if (preg_match('/arranjo|vaso|lírio|lirio|bujudinho|statis/i', $title)) return 'Arranjos & Vasos';
    if (preg_match('/kit|namorados|ferreiro|rocher|chandon|urso|bombom|pelúcia|pelucia|emoji|nutella|vinho|espumante|maternidade|natal/i', $title)) return 'KITS & Presentes';
    if (preg_match('/orquídea|orquidea|planta|girassol|girassois|girasol|begonia|poinssetia|violeta/i', $title)) return 'Orquídeas & Plantas';
    return 'Rosas Colombianas';
}

$uploadDir = __DIR__ . '/assets/uploads/';

try {
    // 1. Categories
    $categories = [
        ['name' => 'Rosas Colombianas', 'slug' => 'rosas-colombianas', 'sort_order' => 1],
        ['name' => 'Cestas Personalizadas', 'slug' => 'cestas-personalizadas', 'sort_order' => 2],
        ['name' => 'Buquês de Luxo', 'slug' => 'buques-de-luxo', 'sort_order' => 3],
        ['name' => 'Arranjos & Vasos', 'slug' => 'arranjos-e-vasos', 'sort_order' => 4],
        ['name' => 'KITS & Presentes', 'slug' => 'kits-e-presentes', 'sort_order' => 5],
        ['name' => 'Orquídeas & Plantas', 'slug' => 'orquideas-e-plantas', 'sort_order' => 6],
    ];

    $catMap = [];
    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
        $stmt->execute([$cat['name'], $cat['slug']]);
        $existing = $stmt->fetch();
        if ($existing) {
            $catMap[$cat['name']] = $existing['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$cat['name'], $cat['slug'], $cat['sort_order']]);
            $catMap[$cat['name']] = $pdo->lastInsertId();
        }
    }

    // 2. Exact 118 Products List with Exact Image File Paths provided by User
    $raw118Products = [
        ["title" => "Buque com 12 Colombianas", "price" => "BRL 300,00", "description" => "Buquê de 12 Rosas Colombianas", "image" => "001-buque-com-12-colombianas.jpg"],
        ["title" => "Buquê com rosas colombianas", "price" => "BRL 320,00", "description" => "12 Rosas colombianas Folhagem verde e gypsofila Embalagem kraft e laço branco", "image" => "002-buque-com-rosas-colombianas.jpg"],
        ["title" => "Buque de Rosas pink colombiana", "price" => "BRL 320,00", "description" => "12 rosas colombianas Folhagem verde e tango Embalagem e laço rosa", "image" => "003-buque-de-rosas-pink-colombiana.jpg"],
        ["title" => "Cesta com Chambinho do Amor", "price" => "BRL 350,00", "description" => "Cesta especial recheada de carinho com rosas e mimos", "image" => "004-cesta-com-chambinho-do-amor.jpg"],
        ["title" => "Cesta com Rosa e Urso", "price" => "BRL 320,00", "description" => "Arranjo de Rosa Colombiana Urso pequeno Ferreiro Rocher 100g Cesta", "image" => "005-cesta-com-rosa-e-urso.jpg"],
        ["title" => "Kit dia dos namorados", "price" => "BRL 1.300,00", "description" => "1 buque com 12 rosas colombianas vermelhas + gypso 1 box de 6 rosas e 4 astromelias brancas e gypso 1 pacote de pétalas 1 urso médio 1 Chandon G 1 cartão de amor G Buque de balões (3 corações M e 2 pequenos)", "image" => "006-kit-dia-dos-namorados.jpg"],
        ["title" => "Buquê com 15 rosas", "price" => "BRL 300,00", "description" => "15 rosas nacionais", "image" => "007-buque-com-15-rosas.jpg"],
        ["title" => "Buquê com 15 Rosas amarelas", "price" => "BRL 300,00", "description" => "Buquê com 15 Rosas amarelas", "image" => "008-buque-com-15-rosas-amarelas.jpg"],
        ["title" => "Buquê com Rosas Rosé", "price" => "BRL 240,00", "description" => "12 Rosas nacionais cor de rosa", "image" => "009-buque-com-rosas-rose.jpg"],
        ["title" => "Buquê Lily", "price" => "BRL 250,00", "description" => "1 galhos de lírios rosa e 1 lírio branco 12 astromelias coloridas Folhagem e lirios", "image" => "010-buque-lily.jpg"],
        ["title" => "Buque de Mix de Flores", "price" => "BRL 580,00", "description" => "4 Rosas Colombianas 3 galhos de lirios coloridos 10 astromélias 4 gerbera colorida 4 Hortensia", "image" => "011-buque-de-mix-de-flores.jpg"],
        ["title" => "Buquê rosa", "price" => "BRL 280,00", "description" => "5 Rosas cor de rosa 5 rosa amarela 4 astromelias Rosa 4 amarela 4 hortênsia", "image" => "012-buque-rosa.jpg"],
        ["title" => "Cesta de café", "price" => "BRL 400,00", "description" => "Arranjo com 4 gerberas vermelhas folhagem verde ruscos e junto. Torrada Toddynho Pão maçã, uva, mamão Cereal chá geleia Bolo Bolacha waffer, Bolacha salgada café e açúcar", "image" => "013-cesta-de-cafe.jpg"],
        ["title" => "Cesta de café com rosa", "price" => "BRL 380,00", "description" => "Arranjo de rosa Torrada Sucrilhos Maça Uva Mamão Sache de Cappuccino Suco Iorgute Requeijão 4 fatias de queijo, 4 fatias de presunto Pão francês Bisnaga 4 paes de queijo", "image" => "014-cesta-de-cafe-com-rosa.jpg"],
        ["title" => "Cesta de Café Premium", "price" => "BRL 400,00", "description" => "Arranjo de rosa Torrada Sucrilhos Maça Sache de Cappuccino Suco Iorgute Requeijão Nutella 4 fatias de queijo, 4 fatias de presunto Pão francês Bisnaga 4 paes de queijo 4 croissant 3 Carolina", "image" => "015-cesta-de-cafe-premium.jpg"],
        ["title" => "Arranjo de Rosas e Lírio", "price" => "BRL 350,00", "description" => "4 Rosas colombianas vermelhas 2 galhos de Lirios Folhagem verde e ruscos", "image" => "016-arranjo-de-rosas-e-lirio.jpg"],
        ["title" => "Arranjo com Rosas vermelhas", "price" => "BRL 450,00", "description" => "18 rosas nacionais vermelhas Folhagem de pit Vaso de vidro", "image" => "017-arranjo-com-rosas-vermelhas.jpg"],
        ["title" => "Arranjo com 3 Rosas Colombianas", "price" => "BRL 150,00", "description" => "3 Rosas Colombianas aberta a mão Folhagem verde e tango Embalagem", "image" => "018-arranjo-com-3-rosas-colombianas.jpg"],
        ["title" => "Ferreiro Rocher 50g", "price" => "BRL 25,00", "description" => "Caixa de bombons Ferreiro Rocher 50g", "image" => "019-ferreiro-rocher-50g.jpg"],
        ["title" => "Ferreiro Rocher 100g", "price" => "BRL 60,00", "description" => "Ferreiro Rocher 100g", "image" => "020-ferreiro-rocher-100g.jpg"],
        ["title" => "Ferreiro Rocher Collection 77g", "price" => "BRL 65,00", "description" => "Ferreiro Rocher Collection 77g", "image" => "021-ferreiro-rocher-collection-77g.jpg"],
        ["title" => "Buque de girassol", "price" => "BRL 150,00", "description" => "6 girassóis Folhagem verde e tango Kraft e laço", "image" => "022-buque-de-girassol.jpg"],
        ["title" => "Buquê de Girassol e Astromélias", "price" => "BRL 200,00", "description" => "6 girassóis 4 astromelias brancas", "image" => "023-buque-de-girassol-e-astromelias.jpg"],
        ["title" => "Buquê com Rosas e Girassóis", "price" => "BRL 450,00", "description" => "8 Rosas Colombianas manipuladas 8 girassóis", "image" => "024-buque-com-rosas-e-girassois.jpg"],
        ["title" => "Bujudinho de Astromélias coloridas", "price" => "BRL 130,00", "description" => "9 astromelias coloridas Folhagem de tango Vaso de vidro Laço (Cerca de 25cm)", "image" => "025-bujudinho-de-astromelias-coloridas.jpg"],
        ["title" => "Arranjo de flores", "price" => "BRL 280,00", "description" => "Lirio (verificar cores) 2 gerberas 4 Rosas 4 flores do campo 2 cravina Rosa 4 astromelias Box", "image" => "026-arranjo-de-flores.jpg"],
        ["title" => "Arranjo de Flores Finas", "price" => "BRL 350,00", "description" => "1 Lirio Branco 2 gerberas 6 Rosas 4 galhos de crisântemos coloridas 6 astromelias coloridas 2 lisianthus Folhagem verde Vaso de vidro", "image" => "027-arranjo-de-flores-finas.jpg"],
        ["title" => "Emoji pelúcia", "price" => "BRL 40,00", "description" => "Cada", "image" => "028-emoji-pelucia.jpg"],
        ["title" => "Coração de pelúcia", "price" => "BRL 45,00", "description" => "13cm", "image" => "029-coracao-de-pelucia.jpg"],
        ["title" => "Urso p", "price" => "BRL 70,00", "description" => "Mini urso 18cm", "image" => "030-urso-p.jpg"],
        ["title" => "Orquidea", "price" => "BRL 450,00", "description" => "Orquídea Phalaenopsis selecionada", "image" => "031-orquidea.jpg"],
        ["title" => "Arranjo com 3 orquideas brancas", "price" => "BRL 1.400,00", "description" => "3 orquideas brancas cascatas Vaso de vidro", "image" => "032-arranjo-com-3-orquideas-brancas.jpg"],
        ["title" => "Orquídea pink", "price" => "BRL 350,00", "description" => "Orquídea cor de rosa vibrante", "image" => "033-orquidea-pink.jpg"],
        ["title" => "Begonia", "price" => "BRL 150,00", "description" => "Planta Begônia floridade", "image" => "034-begonia.jpg"],
        ["title" => "Lirio rosa", "price" => "BRL 150,00", "description" => "Plantado", "image" => "035-lirio-rosa.jpg"],
        ["title" => "Lirio amarelo", "price" => "BRL 150,00", "description" => "Lírio amarelo em vaso", "image" => "036-lirio-amarelo.jpg"],
        ["title" => "Mini Orquidea Branca", "price" => "BRL 250,00", "description" => "Cerca de 45cm", "image" => "037-mini-orquidea-branca.jpg"],
        ["title" => "Mini orquidea pink", "price" => "BRL 250,00", "description" => "(Imagem ilustrativa)", "image" => "038-mini-orquidea-pink.jpg"],
        ["title" => "Arranjo com mini orquídea brancas", "price" => "BRL 700,00", "description" => "4 vasos de mini orquídeas brancas Vaso de vidro Casca", "image" => "039-arranjo-com-mini-orquidea-brancas.jpg"],
        ["title" => "Violeta na cesta", "price" => "BRL 70,00", "description" => "Vaso de violeta na cesta", "image" => "040-violeta-na-cesta.jpg"],
        ["title" => "Kit Maternidade Clássico", "price" => "BRL 380,00", "description" => "Arranjo de astronelias coloridas (opções com 1 cor só) Urso Cesta de vime Colônia Lencinho Pomada Embalagem e laço", "image" => "041-kit-maternidade-classico.jpg"],
        ["title" => "Kit maternidade Premium", "price" => "BRL 500,00", "description" => "1 mini orquídea Urso p Colônia Lencinho Pomada Creme de hidratação Embalagem e laço", "image" => "042-kit-maternidade-premium.jpg"],
        ["title" => "Arranjo com Chocolate", "price" => "BRL 130,00", "description" => "Arranjo floral acompanhado de caixa de chocolates", "image" => "043-arranjo-com-chocolate.jpg"],
        ["title" => "Rosa e Ferrero 50g", "price" => "BRL 50,00", "description" => "1 rosa colombiana Ferreiro rocher 50g", "image" => "044-rosa-e-ferrero-50g.jpg"],
        ["title" => "Girassol Solidário com Ferreiro Collection", "price" => "BRL 90,00", "description" => "Girassol com caixa Ferrero Collection", "image" => "045-girassol-solidario-com-ferreiro-collection.jpg"],
        ["title" => "Buque de tulipas rosa", "price" => "BRL 350,00", "description" => "10 tulipas, ruscus e gypso Embalagem papel jornal (Verificar cores disponíveis)", "image" => "046-buque-de-tulipas-rosa.jpg"],
        ["title" => "Buque com tulipas e rosas inglesas", "price" => "BRL 680,00", "description" => "20 tulipas rosas 3 rosas inglesas Gypso Ruscos", "image" => "047-buque-com-tulipas-e-rosas-inglesas.jpg"],
        ["title" => "Poinssetia", "price" => "BRL 100,00", "description" => "35cm-40cm", "image" => "048-poinssetia.jpg"],
        ["title" => "Kit Natalino", "price" => "BRL 180,00", "description" => "Poinsettia Pinguim natal Cesta de vime", "image" => "049-kit-natalino.jpg"],
        ["title" => "Kit Natal", "price" => "BRL 180,00", "description" => "Pinheiro Poinsettia Cesta de vime", "image" => "050-kit-natal.jpg"],
        ["title" => "Cesta de Café", "price" => "BRL 380,00", "description" => "Arranjo de girassol 4 mini croissant 4 mini pão de queijo 4 Carolina", "image" => "051-cesta-de-cafe-1.jpg"],
        ["title" => "Cesta de Café com girassol", "price" => "BRL 380,00", "description" => "Arranjo de girassol Mamão Maça Uva Torrada Sucrilhos Pão francês Bisnaga 4 paes de queijo Frios 4 fatias queijo, 4 fatias de presunto Suco Iorgute Sache de Cappuccino Cesta de vime Embalagem e laço", "image" => "052-cesta-de-cafe-com-girassol.jpg"],
        ["title" => "Vinho Reservado Carmenere", "price" => "BRL 100,00", "description" => "Vinho Concha y Toro Reservado Carmenere 750ml", "image" => "053-vinho-reservado-carmenere.jpg"],
        ["title" => "Kit maternidade", "price" => "BRL 530,00", "description" => "1 mini orquídea (temos outras cores, consultar) Colônia Óleo Shampoo Pomada Lencinho Cesta de vime Embalagem", "image" => "054-kit-maternidade.jpg"],
        ["title" => "Kit 2 Rosas e Mini Ferreiro Rocher", "price" => "BRL 80,00", "description" => "2 rosas colombianas vermelhas Ferreiro Rocher 50g", "image" => "055-kit-2-rosas-e-mini-ferreiro-rocher.jpg"],
        ["title" => "Buquê com 24 rosas nacionais", "price" => "BRL 360,00", "description" => "Buquê com 24 rosas nacionais", "image" => "056-buque-com-24-rosas-nacionais.jpg"],
        ["title" => "Box com Girassol e Chandon", "price" => "BRL 300,00", "description" => "Arranjo de Girassol Chandon Baby Balão estrela Ferreiro Roche 100g Box verde com tampa Embalagem e laço", "image" => "057-box-com-girassol-e-chandon.jpg"],
        ["title" => "Buquê com Rosa e astromélias", "price" => "BRL 300,00", "description" => "12 Rosas nacionais vermelhas 6 astromelias", "image" => "058-buque-com-rosa-e-astromelias.jpg"],
        ["title" => "Buquê e Ferreiro Rocher", "price" => "BRL 280,00", "description" => "Buquê com 12 Rosas vermelhas nacional Ferreiro Rocher 150g", "image" => "059-buque-e-ferreiro-rocher.jpg"],
        ["title" => "Cesta com Rosas e Chandon", "price" => "BRL 400,00", "description" => "Arranjo com Rosas colombianas Plaquinha de coração Caixa especialmente para você! Ferreiro Rocher 150g", "image" => "060-cesta-com-rosas-e-chandon.jpg"],
        ["title" => "Buquê com 3 lírios", "price" => "BRL 210,00", "description" => "Buquê de 3 lírios", "image" => "061-buque-com-3-lirios.jpg"],
        ["title" => "Cesta com Kalandiva", "price" => "BRL 180,00", "description" => "Cesta com flor Kalandiva", "image" => "062-cesta-com-kalandiva.jpg"],
        ["title" => "Box de Flores", "price" => "BRL 380,00", "description" => "6 Rosas brancas 6 Rosas cor de rosa 4 astromelias brancas 4 astromelias Rosas Folhagem verde Roxinha Cachepo Rosa com tampa e laço", "image" => "063-box-de-flores.jpg"],
        ["title" => "Buquê de Rosas com astromelias", "price" => "BRL 350,00", "description" => "6 Rosas Pink colombiana 7 astromelias brancas Folhagem verde e gypsophila Embalagem e laço", "image" => "064-buque-de-rosas-com-astromelias.jpg"],
        ["title" => "Buque de gerberas colorida", "price" => "BRL 480,00", "description" => "6 rosas colombianas pink 3 boca de leão rosa 1 lírio rosa 3 gerberas rosa 4 margaridas lilás 4 astromelias rosa 3 lisianthus rosa", "image" => "065-buque-de-gerberas-colorida.jpg"],
        ["title" => "Buquê de Flores Silvestres", "price" => "BRL 380,00", "description" => "8 astromelias coloridas 10 margaridas coloridas 4 hortênsia 4 gerberas 1 lirio", "image" => "066-buque-de-flores-silvestres.jpg"],
        ["title" => "Buquê com 20 Rosas Colombianas", "price" => "BRL 650,00", "description" => "20 rosas colombianas vermelhas Folhagem de ruscos em volta Embalagem e laço", "image" => "067-buque-com-20-rosas-colombianas.jpg"],
        ["title" => "Buquê Amor Vibrante", "price" => "BRL 260,00", "description" => "8 Rosas Colombianas vermelhas 6 astromelias vermelhas Gypsofila Folhagem verde pit Embalagem e laço", "image" => "068-buque-amor-vibrante.jpg"],
        ["title" => "Buquê com 24 Rosas importadas", "price" => "BRL 600,00", "description" => "24 Rosas colombinas vermelhas Embalagem Laço", "image" => "069-buque-com-24-rosas-importadas.jpg"],
        ["title" => "Nutella P", "price" => "BRL 30,00", "description" => "Nutella pote 140g", "image" => "070-nutella-p.jpg"],
        ["title" => "Buquê com Lírios coloridos", "price" => "BRL 380,00", "description" => "5 galhos de lírios coloridos 4 astromelias coloridas Folhagem Embalagem + laço", "image" => "071-buque-com-lirios-coloridos.jpg"],
        ["title" => "Espumante Rose Monte Pascoal", "price" => "BRL 60,00", "description" => "Baby", "image" => "072-espumante-rose-monte-pascoal.jpg"],
        ["title" => "Buquê Flores Silvestre", "price" => "BRL 180,00", "description" => "3 crisântemo grande lilás 5 galhos de eucalipto 4 boca de leão Sempre viva Embalagem e laço Folhagem verde", "image" => "073-buque-flores-silvestre.jpg"],
        ["title" => "Arranjo grande com astromelias rosas", "price" => "BRL 280,00", "description" => "20 galhos de astromelias rosas Folhagem verde Vaso de vidrolaço de cetim rosa", "image" => "074-arranjo-grande-com-astromelias-rosas.jpg"],
        ["title" => "Buquê de Rosas Manipuladas", "price" => "BRL 210,00", "description" => "7 Rosas colombianas manipuladas Folhagem verde e tango Embalagem e tango (Naturais)", "image" => "075-buque-de-rosas-manipuladas.jpg"],
        ["title" => "Urso Grande", "price" => "BRL 350,00", "description" => "Aproximadamente 40cm×45cm", "image" => "076-urso-grande.jpg"],
        ["title" => "Arranjo Rosa", "price" => "BRL 300,00", "description" => "6 Rosas Pink 6 astromelias Rosa 8 crisântemos Rosa 4 hortênsia Box", "image" => "077-arranjo-rosa.jpg"],
        ["title" => "Box com 12 rosas nacionais", "price" => "BRL 220,00", "description" => "Buque com 12 rosas vermelhas nacional Folhagem verde e tango Box parda comprida", "image" => "078-box-com-12-rosas-nacionais.jpg"],
        ["title" => "Vaso de vidro", "price" => "BRL 150,00", "description" => "Vaso de vidro para arranjos", "image" => "079-vaso-de-vidro.jpg"],
        ["title" => "Cesta", "price" => "BRL 320,00", "description" => "Arranjo com 2 rosas colombianas Urso P Ferreiro Collection Cesta de vime", "image" => "080-cesta.jpg"],
        ["title" => "Buquê Gerberas e Rosas Brancas", "price" => "BRL 250,00", "description" => "Buquê com Gerberas: 2 rosa e 2 vermelhas 6 Rosas brancas 3 Hortênsia Astromelias: 2 vermelhas, 2 amarelas, 2 rosas", "image" => "081-buque-gerberas-e-rosas-brancas.jpg"],
        ["title" => "Box Mãe", "price" => "BRL 400,00", "description" => "Bujudinho com 12 rosas coloridas e gypso no vidro Balão rosé Pelúcia de coração Espumante rosé Ferreiro Rocher 150g Box personalizada Dia das mães", "image" => "082-box-mae.jpg"],
        ["title" => "Cesta com Lirio e espumante", "price" => "BRL 380,00", "description" => "1 Lirio plantado Plaquinha Ferreiro Rocher 150g Mini espumante rosé Caixa", "image" => "083-cesta-com-lirio-e-espumante.jpg"],
        ["title" => "Orquidea Phale média", "price" => "BRL 250,00", "description" => "Orquídea Phalaenopsis média", "image" => "084-orquidea-phale-media.jpg"],
        ["title" => "Arranjo com rosas", "price" => "BRL 800,00", "description" => "Arranjo com 7 rosas manipuladas 4 galhos de lírios rosas 6 hortensias Gypsofila Vaso de vidro", "image" => "085-arranjo-com-rosas.jpg"],
        ["title" => "Buquê Angélica", "price" => "BRL 200,00", "description" => "Buquê com 10 gérberas Rafaello", "image" => "086-buque-angelica.jpg"],
        ["title" => "Buquê com 40 rosas colombianas", "price" => "BRL 950,00", "description" => "40 botões de rosas Embalagem e laço personalizado Surpreenda seu amor!", "image" => "087-buque-com-40-rosas-colombianas.jpg"],
        ["title" => "Buquê Encanto Inesquecível", "price" => "BRL 600,00", "description" => "10 rosas brancas 5 gerberas rosas 3 gerbras amarelas 4 Margaridas amarelas 4 margaridas lilás 3 margaridas brancas 2 lírio vermelho 12 astromelias coloridas", "image" => "088-buque-encanto-inesquecivel.jpg"],
        ["title" => "Buquê", "price" => "BRL 180,00", "description" => "1 lirio 3 cravinas 1 gerbera 1 lisianthus Eucalipto 2 anastásias Folhagem verde e tango Kraft Cru e Laço de corda", "image" => "089-buque.jpg"],
        ["title" => "Arranjo Statis", "price" => "BRL 300,00", "description" => "2 Anastasia roxa 3 boca de leão 2 gerberas 4 astromelias brancas 3 lisianthus 1 galho de Eucalipto Caixa kraft Lado cetim Embalagem rosé", "image" => "090-arranjo-statis.jpg"],
        ["title" => "Buquê com 24 rosas colombianas", "price" => "BRL 700,00", "description" => "12 rosas vermelhas 12 rosas brancas colombianas", "image" => "091-buque-com-24-rosas-colombianas.jpg"],
        ["title" => "Arranjo com 2 Rosas Colombiana", "price" => "BRL 90,00", "description" => "Arranjo com 2 Rosas Colombiana", "image" => "092-arranjo-com-2-rosas-colombiana.jpg"],
        ["title" => "Arranjo com 3 rosas brancas", "price" => "BRL 200,00", "description" => "3 rosas colombianas brancas folhagem verde embalagem brancas", "image" => "093-arranjo-com-3-rosas-brancas.jpg"],
        ["title" => "Arranjo Pink de Rosas e Astromelia", "price" => "BRL 350,00", "description" => "10 Rosas nacionais Pink 7 astromélias cor de rosa Folhagem verde e fantasia Vaso de vidro e laço", "image" => "094-arranjo-pink-de-rosas-e-astromelia.jpg"],
        ["title" => "Arranjo com 3 orquídeas pink", "price" => "BRL 1.200,00", "description" => "3 orquídeas pink selecionadas", "image" => "095-arranjo-com-3-orquideas-pink.jpg"],
        ["title" => "Buquê Jasmine", "price" => "BRL 300,00", "description" => "1 lírio rosa 6 galhos de lisianthus Statis roxinha + Caspia 10 astromelias roxa Embalagem e laço", "image" => "096-buque-jasmine.jpg"],
        ["title" => "Arranjo de Rosa Colombiana", "price" => "BRL 70,00", "description" => "Arranjo individual de Rosa Colombiana", "image" => "097-arranjo-de-rosa-colombiana.jpg"],
        ["title" => "Kit Amor Perfeito", "price" => "BRL 220,00", "description" => "1 Arranjo de Rosa 1 Plaquinha 1 Espumante rosé 2 KitKat 2 Suflair Caixa kraft", "image" => "098-kit-amor-perfeito.jpg"],
        ["title" => "Buquê Primeira", "price" => "BRL 200,00", "description" => "5 rosas claras 5 rosas brancas 3 astromelias rosa 3 astronelias brancas Folhagem verde", "image" => "099-buque-primeira.jpg"],
        ["title" => "Buquê de Rosa Branca nacional", "price" => "BRL 240,00", "description" => "12 Rosas Nacional brancas Folhagem verde e tango", "image" => "100-buque-de-rosa-branca-nacional.jpg"],
        ["title" => "Arranjo no vaso de vidro", "price" => "BRL 450,00", "description" => "Arranjo no vaso de vidro", "image" => "101-arranjo-no-vaso-de-vidro.jpg"],
        ["title" => "Arranjo", "price" => "BRL 250,00", "description" => "2 gerberas laranjas 1 lírio amarelo 3 margaridas 6 astromelias 3 rosas brancas Box", "image" => "102-arranjo.jpg"],
        ["title" => "Cesta com Arranjo e chocolate", "price" => "BRL 200,00", "description" => "Arranjo com Rosa colombiana Ferreiro Rocher Plaquinha Cestinha", "image" => "103-cesta-com-arranjo-e-chocolate.jpg"],
        ["title" => "Buquê com 12 Rosas e gypsophila", "price" => "BRL 260,00", "description" => "Buquê com 12 rosas vermelhas nacional 2 galhos de gypsofila Folhagem verde Embalagem", "image" => "104-buque-com-12-rosas-e-gypsophila.jpg"],
        ["title" => "Arranjo de Rosas", "price" => "BRL 220,00", "description" => "12 Rosas nacionais branca Folhagem verde Vaso de acrílico verde", "image" => "105-arranjo-de-rosas.jpg"],
        ["title" => "Buquê de flores finas", "price" => "BRL 100,00", "description" => "3 astromelias coloridas 3 galhos de flores do campo 2 Lisianhus 1 gerbera Folhagem verde e tango Embalagem e laço", "image" => "106-buque-de-flores-finas.jpg"],
        ["title" => "Buquê com Cravinas Coloridas", "price" => "BRL 150,00", "description" => "10 galhos de cravinas Folhagens verdes e tango Papel rosa + papel comeia rosé Laço de corda", "image" => "107-buque-com-cravinas-coloridas.jpg"],
        ["title" => "Buquê com 10 Rosas Nacional", "price" => "BRL 200,00", "description" => "Buquê com 10 Rosas nacionais vermelhas Folhagem verde e tango", "image" => "108-buque-com-10-rosas-nacional.jpg"],
        ["title" => "Arranjo de Rosas e Lirio", "price" => "BRL 400,00", "description" => "7 Rosas colombianas 2 Lirios amarelo Folhagem Verde Cachepo", "image" => "109-arranjo-de-rosas-e-lirio-1.jpg"],
        ["title" => "Bujudinho de Rosa e Girasol", "price" => "BRL 250,00", "description" => "5 Rosas vermelhas colombiana 4 girassol Folhagem verde e aspargo 3 galhos de ruscos", "image" => "110-bujudinho-de-rosa-e-girasol.jpg"],
        ["title" => "Arranjo Rose", "price" => "BRL 380,00", "description" => "15 Rosas nacionais Cor de Rosa, Folhagem verde Vaso de vidro Laço de cetim", "image" => "111-arranjo-rose.jpg"],
        ["title" => "Buque com girassois", "price" => "BRL 150,00", "description" => "3 girassol eucaliptos gypso embalagem", "image" => "112-buque-com-girassois.jpg"],
        ["title" => "Buquê de 60 rosas colombianas", "price" => "BRL 1.500,00", "description" => "60 rosas colombianas Embalagem branca Laço de fita vermelho e branco", "image" => "113-buque-de-60-rosas-colombianas.jpg"],
        ["title" => "Orquídea Branca Cascata", "price" => "BRL 350,00", "description" => "(Cerca de 75cm)", "image" => "114-orquidea-branca-cascata.jpg"],
        ["title" => "Buquê com 18 rosas nacionais", "price" => "BRL 360,00", "description" => "Buquê 18 Rosas nacionais vermelha (temos outras cores *consultar*)", "image" => "115-buque-com-18-rosas-nacionais.jpg"],
        ["title" => "Ferreiro Rocher 150g", "price" => "BRL 70,00", "description" => "Ferreiro Rocher 150g", "image" => "116-ferreiro-rocher-150g.jpg"],
        ["title" => "Arranjo Branco com Flores Finas", "price" => "BRL 215,00", "description" => "4 Boca de Leão branca 3 hortênsia 4 astromelias 2 Galhos de margaridas 2 galhos de lisiantus Vaso de vidro", "image" => "117-arranjo-branco-com-flores-finas.jpg"],
        ["title" => "Arranjo de Rosas e Astromélia branca", "price" => "BRL 250,00", "description" => "6 Rosas colombianas vermelhas manipuladas. 6 astromelias brancas Folhagem verde e tango Cachepot e embalagem", "image" => "118-arranjo-de-rosas-e-astromelia-branca.jpg"]
    ];

    $inserted = 0;
    $updated = 0;

    foreach ($raw118Products as $p) {
        $name = trim($p['title']);
        $rawPrice = str_replace(['BRL', 'R$', ' ', '.'], '', $p['price']);
        $rawPrice = str_replace(',', '.', $rawPrice);
        $price = floatval($rawPrice);
        $desc = trim($p['description']);
        $catName = detectCatName($name);
        $catId = $catMap[$catName] ?? 1;
        $slug = makeSlug($name);
        $imgFile = basename($p['image']);

        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? OR slug = ?");
        $stmt->execute([$name, $slug]);
        $existingId = $stmt->fetchColumn();

        if (!$existingId) {
            $sku = 'HF-WA-' . strtoupper(substr(md5($name), 0, 6));
            $ins = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, sku, price, image_path, active, stock_qty, featured) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 50, 1)");
            $ins->execute([$catId, $name, $slug, $desc, $sku, $price, $imgFile]);
            $inserted++;
        } else {
            $upd = $pdo->prepare("UPDATE products SET price = IF(? > 0, ?, price), description = IF(LENGTH(?) > 3, ?, description), image_path = ?, active = 1 WHERE id = ?");
            $upd->execute([$price, $price, $desc, $desc, $imgFile, $existingId]);
            $updated++;
        }
    }

    echo "
    <div style='font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 25px; background: #FFF5F7; border: 2px solid #4CAF50; border-radius: 16px; text-align: center;'>
        <h1 style='color: #2E7D32;'>🌸 100% dos 118 Produtos Sincronizados com Sucesso!</h1>
        <p style='font-size: 1.1rem; color: #333; line-height:1.6;'>Todos os 118 buquês, cestas de café da manhã, chocolates e kits do WhatsApp foram cadastrados no MySQL com os nomes das imagens de <code>001-...jpg</code> até <code>118-...jpg</code>!</p>
        <hr style='border: 0; border-top: 1px solid #E0D0D5; margin: 20px 0;'>
        <div style='text-align: left; background: #FFF; padding: 18px; border-radius: 10px; font-size: 1rem; color:#444;'>
            • Total de Produtos com Mapeamento Exato: <strong>" . count($raw118Products) . "</strong><br>
            • Novos produtos inseridos no MySQL: <strong>{$inserted}</strong><br>
            • Produtos atualizados no MySQL: <strong>{$updated}</strong>
        </div>
        <br>
        <a href='../index.php' target='_blank' style='display: inline-block; background: #C2185B; color: #FFF; padding: 14px 32px; border-radius: 30px; text-decoration: none; font-weight: bold; font-size:1.1rem;'>
            🌸 Ver Catálogo na Loja Pública →
        </a>
    </div>
    ";

} catch (Exception $e) {
    echo "<div style='background:#FFEBEE; color:#C2185B; padding:20px; border-radius:12px; margin:20px 0;'><h3>Erro ao semear: " . htmlspecialchars($e->getMessage()) . "</h3></div>";
}
