<?php
/**
 * seed_helena_flores.php — Semeador Completo dos 118 Produtos do WhatsApp Business Helena Flores
 * Mapeia automaticamente fotos locais baixadas da pasta assets/uploads/
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

// Scans local assets/uploads/ for available original photos
$uploadDir = __DIR__ . '/assets/uploads/';
$localFiles = [];
if (file_exists($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && is_file($uploadDir . $f) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $f)) {
            if ($f !== 'defects') {
                $localFiles[] = $f;
            }
        }
    }
}

function getBestLocalImage($title, $index, $localFiles) {
    if (empty($localFiles)) return 'rose_red.jpg';

    // 1. Try finding a filename that matches product slug or title
    $slug = makeSlug($title);
    foreach ($localFiles as $lf) {
        if (stripos($lf, $slug) !== false || stripos($lf, str_replace('-', '_', $slug)) !== false) {
            return $lf;
        }
    }

    // 2. Try finding by keyword
    if (preg_match('/girassol/i', $title)) {
        foreach ($localFiles as $lf) { if (stripos($lf, 'girassol') !== false) return $lf; }
    }
    if (preg_match('/cesta/i', $title)) {
        foreach ($localFiles as $lf) { if (stripos($lf, 'cesta') !== false) return $lf; }
    }
    if (preg_match('/orquidea|orquídea/i', $title)) {
        foreach ($localFiles as $lf) { if (stripos($lf, 'orquidea') !== false) return $lf; }
    }
    if (preg_match('/tulipa/i', $title)) {
        foreach ($localFiles as $lf) { if (stripos($lf, 'tulipa') !== false) return $lf; }
    }

    // 3. Fallback to rotating available local files in assets/uploads/
    return $localFiles[$index % count($localFiles)];
}

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

    // 2. Raw 118 Products List
    $raw118Products = [
        ["title" => "Buque com 12 Colombianas", "price" => 300.00, "description" => "Buquê de 12 Rosas Colombianas selecionadas com fita de cetim."],
        ["title" => "Buquê com rosas colombianas", "price" => 320.00, "description" => "12 Rosas colombianas Folhagem verde e gypsofila Embalagem kraft e laço branco"],
        ["title" => "Buque de Rosas pink colombiana", "price" => 320.00, "description" => "12 rosas colombianas Folhagem verde e tango Embalagem e laço rosa"],
        ["title" => "Cesta com Chambinho do Amor", "price" => 350.00, "description" => "Cesta especial recheada de carinho com rosas e mimos"],
        ["title" => "Cesta com Rosa e Urso", "price" => 320.00, "description" => "Arranjo de Rosa Colombiana Urso pequeno Ferreiro Rocher 100g Cesta"],
        ["title" => "Kit dia dos namorados", "price" => 1300.00, "description" => "1 buque com 12 rosas colombianas vermelhas + gypso 1 box de 6 rosas e 4 astromelias brancas e gypso 1 pacote de pétalas 1 urso médio 1 Chandon G 1 cartão de amor G Buque de balões"],
        ["title" => "Buquê com 15 rosas", "price" => 300.00, "description" => "15 rosas nacionais"],
        ["title" => "Buquê com 15 Rosas amarelas", "price" => 300.00, "description" => "Buquê com 15 Rosas amarelas"],
        ["title" => "Buquê com Rosas Rosé", "price" => 240.00, "description" => "12 Rosas nacionais cor de rosa"],
        ["title" => "Buquê Lily", "price" => 250.00, "description" => "1 galhos de lírios rosa e 1 lírio branco 12 astromelias coloridas Folhagem e lirios"],
        ["title" => "Buque de Mix de Flores", "price" => 580.00, "description" => "4 Rosas Colombianas 3 galhos de lirios coloridos 10 astromélias 4 gerbera colorida 4 Hortensia"],
        ["title" => "Buquê rosa", "price" => 280.00, "description" => "5 Rosas cor de rosa 5 rosa amarela 4 astromelias Rosa 4 amarela 4 hortênsia"],
        ["title" => "Cesta de café", "price" => 400.00, "description" => "Arranjo com 4 gerberas vermelhas folhagem verde ruscos. Torrada Toddynho Pão maçã, uva, mamão Cereal chá geleia Bolo café"],
        ["title" => "Cesta de café com rosa", "price" => 380.00, "description" => "Arranjo de rosa Torrada Sucrilhos Maça Uva Mamão Cappuccino Suco Iorgute Requeijão queijo presunto Pão francês pães de queijo"],
        ["title" => "Cesta de Café Premium", "price" => 400.00, "description" => "Arranjo de rosa Torrada Sucrilhos Maça Cappuccino Suco Iorgute Requeijão Nutella frios Pão francês croissant Carolinas"],
        ["title" => "Arranjo de Rosas e Lírio", "price" => 350.00, "description" => "4 Rosas colombianas vermelhas 2 galhos de Lirios Folhagem verde e ruscos"],
        ["title" => "Arranjo com Rosas vermelhas", "price" => 450.00, "description" => "18 rosas nacionais vermelhas Folhagem de pit Vaso de vidro"],
        ["title" => "Arranjo com 3 Rosas Colombianas", "price" => 150.00, "description" => "3 Rosas Colombianas aberta a mão Folhagem verde e tango Embalagem"],
        ["title" => "Ferreiro Rocher 50g", "price" => 25.00, "description" => "Caixa de bombons Ferreiro Rocher 50g"],
        ["title" => "Ferreiro Rocher 100g", "price" => 60.00, "description" => "Ferreiro Rocher 100g"],
        ["title" => "Ferreiro Rocher Collection 77g", "price" => 65.00, "description" => "Ferreiro Rocher Collection 77g"],
        ["title" => "Buque de girassol", "price" => 150.00, "description" => "6 girassóis Folhagem verde e tango Kraft e laço"],
        ["title" => "Buquê de Girassol e Astromélias", "price" => 200.00, "description" => "6 girassóis 4 astromelias brancas"],
        ["title" => "Buquê com Rosas e Girassóis", "price" => 450.00, "description" => "8 Rosas Colombianas manipuladas 8 girassóis"],
        ["title" => "Bujudinho de Astromélias coloridas", "price" => 130.00, "description" => "9 astromelias coloridas Folhagem de tango Vaso de vidro Laço"],
        ["title" => "Arranjo de flores", "price" => 280.00, "description" => "Lirio 2 gerberas 4 Rosas 4 flores do campo 2 cravina Rosa 4 astromelias Box"],
        ["title" => "Arranjo de Flores Finas", "price" => 350.00, "description" => "1 Lirio Branco 2 gerberas 6 Rosas 4 crisântemos 6 astromelias Vaso de vidro"],
        ["title" => "Emoji pelúcia", "price" => 40.00, "description" => "Emoji pelúcia artesanal"],
        ["title" => "Coração de pelúcia", "price" => 45.00, "description" => "Coração de pelúcia 13cm"],
        ["title" => "Urso p", "price" => 70.00, "description" => "Mini urso de pelúcia 18cm"],
        ["title" => "Orquidea", "price" => 450.00, "description" => "Orquídea Phalaenopsis selecionada"],
        ["title" => "Arranjo com 3 orquideas brancas", "price" => 1400.00, "description" => "3 orquideas brancas cascatas em Vaso de vidro"],
        ["title" => "Orquídea pink", "price" => 350.00, "description" => "Orquídea cor de rosa vibrante"],
        ["title" => "Begonia", "price" => 150.00, "description" => "Planta Begônia floridade"],
        ["title" => "Lirio rosa", "price" => 150.00, "description" => "Lírio rosa plantado em vaso"],
        ["title" => "Lirio amarelo", "price" => 150.00, "description" => "Lírio amarelo plantado em vaso"],
        ["title" => "Mini Orquidea Branca", "price" => 250.00, "description" => "Mini Orquídea branca elegante (45cm)"],
        ["title" => "Mini orquidea pink", "price" => 250.00, "description" => "Mini orquídea pink em vaso"],
        ["title" => "Arranjo com mini orquídea brancas", "price" => 700.00, "description" => "4 vasos de mini orquídeas brancas Vaso de vidro Casca"],
        ["title" => "Violeta na cesta", "price" => 70.00, "description" => "Vaso de violeta montado em cesta delicada"],
        ["title" => "Kit Maternidade Clássico", "price" => 380.00, "description" => "Arranjo de astromélias, Urso, Cesta de vime, Colônia, Lencinho e Pomada"],
        ["title" => "Kit maternidade Premium", "price" => 500.00, "description" => "1 mini orquídea, Urso p, Colônia, Lencinho, Pomada e Creme de hidratação"],
        ["title" => "Arranjo com Chocolate", "price" => 130.00, "description" => "Arranjo floral acompanhado de caixa de chocolates"],
        ["title" => "Rosa e Ferrero 50g", "price" => 50.00, "description" => "1 rosa colombiana com Ferrero Rocher 50g"],
        ["title" => "Girassol Solidário com Ferreiro Collection", "price" => 90.00, "description" => "Girassol vibrante acompanhado de caixa Ferrero Collection"],
        ["title" => "Buque de tulipas rosa", "price" => 350.00, "description" => "10 tulipas, ruscus e gypso em embalagem papel jornal"],
        ["title" => "Buque com tulipas e rosas inglesas", "price" => 680.00, "description" => "20 tulipas rosas e 3 rosas inglesas com Gypso e Ruscos"],
        ["title" => "Poinssetia", "price" => 100.00, "description" => "Planta Poinsettia (Flor do Natal) 35cm-40cm"],
        ["title" => "Kit Natalino", "price" => 180.00, "description" => "Poinsettia, Pinguim natalino e Cesta de vime"],
        ["title" => "Kit Natal", "price" => 180.00, "description" => "Pinheiro, Poinsettia e Cesta de vime decorada"],
        ["title" => "Cesta de Café", "price" => 380.00, "description" => "Arranjo de girassol, mini croissants, mini pães de queijo e carolinas"],
        ["title" => "Cesta de Café com girassol", "price" => 380.00, "description" => "Arranjo de girassol, Frutas, Torrada, Sucrilhos, Pão francês, Frios, Suco, Iogurte e Cappuccino"],
        ["title" => "Vinho Reservado Carmenere", "price" => 100.00, "description" => "Garrafa de Vinho Concha y Toro Reservado Carmenere 750ml"],
        ["title" => "Kit maternidade", "price" => 530.00, "description" => "1 mini orquídea, Colônia, Óleo, Shampoo, Pomada, Lencinho em Cesta de vime"],
        ["title" => "Kit 2 Rosas e Mini Ferreiro Rocher", "price" => 80.00, "description" => "2 rosas colombianas vermelhas e Ferrero Rocher 50g"],
        ["title" => "Buquê com 24 rosas nacionais", "price" => 360.00, "description" => "Buquê volumoso com 24 rosas nacionais"],
        ["title" => "Box com Girassol e Chandon", "price" => 300.00, "description" => "Arranjo de Girassol, Chandon Baby, Balão estrela e Ferrero Rocher 100g"],
        ["title" => "Buquê com Rosa e astromélias", "price" => 300.00, "description" => "12 Rosas nacionais vermelhas e 6 astromélias"],
        ["title" => "Buquê e Ferreiro Rocher", "price" => 280.00, "description" => "Buquê com 12 Rosas vermelhas nacionais e Ferrero Rocher 150g"],
        ["title" => "Cesta com Rosas e Chandon", "price" => 400.00, "description" => "Arranjo com Rosas colombianas, Plaquinha, Caixa especial e Ferrero Rocher 150g"],
        ["title" => "Buquê com 3 lírios", "price" => 210.00, "description" => "Buquê elegante com 3 galhos de lírios abertos"],
        ["title" => "Cesta com Kalandiva", "price" => 180.00, "description" => "Flor Kalandiva em cesta decorada"],
        ["title" => "Box de Flores", "price" => 380.00, "description" => "6 Rosas brancas, 6 Rosas cor de rosa, 8 astromélias e Cachepô rosa"],
        ["title" => "Buquê de Rosas com astromelias", "price" => 350.00, "description" => "6 Rosas Pink colombianas e 7 astromélias brancas"],
        ["title" => "Buque de gerberas colorida", "price" => 480.00, "description" => "6 rosas colombianas pink, boca de leão, lírio rosa, gérberas e margaridas"],
        ["title" => "Buquê de Flores Silvestres", "price" => 380.00, "description" => "8 astromélias coloridas, 10 margaridas, 4 hortênsias, gérberas e lírio"],
        ["title" => "Buquê com 20 Rosas Colombianas", "price" => 650.00, "description" => "20 rosas colombianas vermelhas com folhagem de ruscos"],
        ["title" => "Buquê Amor Vibrante", "price" => 260.00, "description" => "8 Rosas Colombianas vermelhas e 6 astromélias vermelhas"],
        ["title" => "Buquê com 24 Rosas importadas", "price" => 600.00, "description" => "24 Rosas colombianas vermelhas em embalagem de gala"],
        ["title" => "Nutella P", "price" => 30.00, "description" => "Pote de Nutella 140g"],
        ["title" => "Buquê com Lírios coloridos", "price" => 380.00, "description" => "5 galhos de lírios coloridos e 4 astromélias coloridas"],
        ["title" => "Espumante Rose Monte Pascoal", "price" => 60.00, "description" => "Garrafa Baby Espumante Rosé Monte Pascoal"],
        ["title" => "Buquê Flores Silvestre", "price" => 180.00, "description" => "3 crisântemos lilás, 5 galhos de eucalipto e 4 boca de leão"],
        ["title" => "Arranjo grande com astromelias rosas", "price" => 280.00, "description" => "20 galhos de astromélias rosas em Vaso de vidro"],
        ["title" => "Buquê de Rosas Manipuladas", "price" => 210.00, "description" => "7 Rosas colombianas manipuladas com folhagem e tango"],
        ["title" => "Urso Grande", "price" => 350.00, "description" => "Urso de pelúcia gigante (40cm x 45cm)"],
        ["title" => "Arranjo Rosa", "price" => 300.00, "description" => "6 Rosas Pink, 6 astromélias rosa, 8 crisântemos e hortênsia em Box"],
        ["title" => "Box com 12 rosas nacionais", "price" => 220.00, "description" => "12 rosas vermelhas nacionais em Box parda comprida"],
        ["title" => "Vaso de vidro", "price" => 150.00, "description" => "Vaso de cristal trabalhado para arranjos"],
        ["title" => "Cesta", "price" => 320.00, "description" => "Arranjo com 2 rosas colombianas, Urso P e Ferrero Collection em Cesta de vime"],
        ["title" => "Buquê Gerberas e Rosas Brancas", "price" => 250.00, "description" => "Gérberas rosa/vermelha, 6 Rosas brancas e 3 Hortênsias"],
        ["title" => "Box Mãe", "price" => 400.00, "description" => "Bujudinho com 12 rosas coloridas, Balão rosé, Pelúcia de coração, Espumante e Ferrero 150g"],
        ["title" => "Cesta com Lirio e espumante", "price" => 380.00, "description" => "1 Lírio plantado, Ferrero Rocher 150g, Mini espumante rosé e Caixa decorada"],
        ["title" => "Orquidea Phale média", "price" => 250.00, "description" => "Orquídea Phalaenopsis de porte médio"],
        ["title" => "Arranjo com rosas", "price" => 800.00, "description" => "7 rosas manipuladas, 4 galhos de lírios rosas, 6 hortênsias em Vaso de vidro"],
        ["title" => "Buquê Angélica", "price" => 200.00, "description" => "Buquê com 10 gérberas e bombons Rafaello"],
        ["title" => "Buquê com 40 rosas colombianas", "price" => 950.00, "description" => "40 botões de rosas colombianas vermelhas em embalagem luxo"],
        ["title" => "Buquê Encanto Inesquecível", "price" => 600.00, "description" => "10 rosas brancas, 8 gérberas, 11 margaridas, 2 lírios e 12 astromélias"],
        ["title" => "Buquê", "price" => 180.00, "description" => "1 lírio, 3 cravinas, 1 gérbera, 1 lisianthus e eucalipto em Kraft Cru"],
        ["title" => "Arranjo Statis", "price" => 300.00, "description" => "Anastasia roxa, boca de leão, gérberas e lisianthus em Caixa kraft"],
        ["title" => "Buquê com 24 rosas colombianas", "price" => 700.00, "description" => "12 rosas vermelhas e 12 rosas brancas colombianas"],
        ["title" => "Arranjo com 2 Rosas Colombiana", "price" => 90.00, "description" => "Arranjo delicado com 2 Rosas Colombianas"],
        ["title" => "Arranjo com 3 rosas brancas", "price" => 200.00, "description" => "3 rosas colombianas brancas em embalagem branca"],
        ["title" => "Arranjo Pink de Rosas e Astromelia", "price" => 350.00, "description" => "10 Rosas nacionais Pink e 7 astromélias rosa em Vaso de vidro"],
        ["title" => "Arranjo com 3 orquídeas pink", "price" => 1200.00, "description" => "3 orquídeas pink selecionadas em vaso grande"],
        ["title" => "Buquê Jasmine", "price" => 300.00, "description" => "1 lírio rosa, 6 galhos de lisianthus e 10 astromélias roxas"],
        ["title" => "Arranjo de Rosa Colombiana", "price" => 70.00, "description" => "Arranjo individual de Rosa Colombiana"],
        ["title" => "Kit Amor Perfeito", "price" => 220.00, "description" => "1 Arranjo de Rosa, Espumante rosé, KitKat e Suflair em Caixa kraft"],
        ["title" => "Buquê Primeira", "price" => 200.00, "description" => "5 rosas claras, 5 rosas brancas e 6 astromélias"],
        ["title" => "Buquê de Rosa Branca nacional", "price" => 240.00, "description" => "12 Rosas Nacionais brancas com folhagem verde"],
        ["title" => "Arranjo no vaso de vidro", "price" => 450.00, "description" => "Arranjo misto luxuoso em Vaso de cristal"],
        ["title" => "Arranjo", "price" => 250.00, "description" => "2 gérberas laranjas, 1 lírio amarelo, 3 margaridas e 6 astromélias"],
        ["title" => "Cesta com Arranjo e chocolate", "price" => 200.00, "description" => "Arranjo com Rosa colombiana, Ferrero Rocher e Cestinha"],
        ["title" => "Buquê com 12 Rosas e gypsophila", "price" => 260.00, "description" => "12 rosas vermelhas nacionais e 2 galhos de gypsofila"],
        ["title" => "Arranjo de Rosas", "price" => 220.00, "description" => "12 Rosas nacionais brancas em Vaso de acrílico verde"],
        ["title" => "Buquê de flores finas", "price" => 100.00, "description" => "3 astromélias coloridas, flores do campo e lisianthus"],
        ["title" => "Buquê com Cravinas Coloridas", "price" => 150.00, "description" => "10 galhos de cravinas em papel rosa e laço de corda"],
        ["title" => "Buquê com 10 Rosas Nacional", "price" => 200.00, "description" => "10 Rosas nacionais vermelhas com folhagem verde"],
        ["title" => "Arranjo de Rosas e Lirio", "price" => 400.00, "description" => "7 Rosas colombianas e 2 Lírios amarelos em Cachepô"],
        ["title" => "Bujudinho de Rosa e Girasol", "price" => 250.00, "description" => "5 Rosas vermelhas colombianas e 4 girassóis"],
        ["title" => "Arranjo Rose", "price" => 380.00, "description" => "15 Rosas nacionais Cor de Rosa em Vaso de vidro"],
        ["title" => "Buque com girassois", "price" => 150.00, "description" => "3 girassóis, eucaliptos e gypso"],
        ["title" => "Buquê de 60 rosas colombianas", "price" => 1500.00, "description" => "60 rosas colombianas vermelhas em embalagem de luxo"],
        ["title" => "Orquídea Branca Cascata", "price" => 350.00, "description" => "Orquídea branca tipo cascata (75cm)"],
        ["title" => "Buquê com 18 rosas nacionais", "price" => 360.00, "description" => "Buquê com 18 Rosas nacionais vermelhas"],
        ["title" => "Ferreiro Rocher 150g", "price" => 70.00, "description" => "Caixa de bombons Ferrero Rocher 150g"],
        ["title" => "Arranjo Branco com Flores Finas", "price" => 215.00, "description" => "Boca de Leão branca, hortênsias, astromélias e lisiantus em Vaso de vidro"],
        ["title" => "Arranjo de Rosas e Astromélia branca", "price" => 250.00, "description" => "6 Rosas colombianas vermelhas e 6 astromélias brancas"]
    ];

    $inserted = 0;
    $updated = 0;

    foreach ($raw118Products as $idx => $p) {
        $name = trim($p['title']);
        $price = floatval($p['price']);
        $desc = trim($p['description']);
        $catName = detectCatName($name);
        $catId = $catMap[$catName] ?? 1;
        $slug = makeSlug($name);
        $imgFile = getBestLocalImage($name, $idx, $localFiles);

        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? OR slug = ?");
        $stmt->execute([$name, $slug]);
        $existingId = $stmt->fetchColumn();

        if (!$existingId) {
            $sku = 'HF-WA-' . strtoupper(substr(md5($name), 0, 6));
            $ins = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, sku, price, image_path, active, stock_qty, featured, show_on_site) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 50, 1, 1)");
            $ins->execute([$catId, $name, $slug, $desc, $sku, $price, $imgFile]);
            $inserted++;
        } else {
            $upd = $pdo->prepare("UPDATE products SET price = IF(? > 0, ?, price), description = IF(LENGTH(?) > 3, ?, description), image_path = ?, active = 1, show_on_site = 1 WHERE id = ?");
            $upd->execute([$price, $price, $desc, $desc, $imgFile, $existingId]);
            $updated++;
        }
    }

    echo "
    <div style='font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 35px; background: #FFF5F7; border: 2px solid #D81B60; border-radius: 16px; text-align: center;'>
        <h1 style='color: #C2185B;'>🌸 100% dos 118 Produtos do WhatsApp Cadastrados!</h1>
        <p style='font-size: 1.15rem; color: #333; line-height:1.6;'>Todos os 118 buquês, cestas de café da manhã, chocolates Ferrero Rocher, orquídeas cascatas, kits de namorados e arranjos do seu WhatsApp foram cadastrados e vinculados com fotos da pasta <code>assets/uploads/</code>!</p>
        <hr style='border: 0; border-top: 1px solid #E0D0D5; margin: 20px 0;'>
        <div style='text-align: left; background: #FFF; padding: 18px; border-radius: 10px; font-size: 1rem; color:#444;'>
            • Arquivos Locais Detectados em assets/uploads: <strong>" . count($localFiles) . "</strong><br>
            • Total de Produtos Processados: <strong>" . count($raw118Products) . "</strong><br>
            • Novos produtos inseridos: <strong>{$inserted}</strong><br>
            • Produtos atualizados: <strong>{$updated}</strong>
        </div>
        <br>
        <a href='index.php' style='display: inline-block; background: #D81B60; color: #FFF; padding: 15px 36px; border-radius: 30px; text-decoration: none; font-weight: bold; font-size:1.15rem;'>
            🌸 Ver Catálogo Completo no Site →
        </a>
    </div>
    ";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>Erro ao semear: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
