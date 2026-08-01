import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const CSV_TEXT = `ID;Nome;Link;Imagem;Preço;Categoria;Descrição
115;Arranjo;"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=115";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/102-arranjo.jpg";250,00;"Arranjos & Vasos";"2 gerberas laranjas 1 lírio amarelo 3 margaridas 6 astromelias 3 rosas brancas Box"
129;"Arranjo Branco com Flores Finas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=129";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/117-arranjo-branco-com-flores-finas.jpg";215,00;"Arranjos & Vasos";"4 Boca de Leão branca 3 hortênsia 4 astromelias 2 Galhos de margaridas 2 galhos de lisiantus Vaso de vidro"
105;"Arranjo com 2 Rosas Colombiana";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=105";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/092-arranjo-com-2-rosas-colombiana.jpg";90,00;"Rosas Colombianas";"Arranjo com 2 Rosas Colombiana"
46;"Arranjo com 3 orquideas brancas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=46";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/032-arranjo-com-3-orquideas-brancas.jpg";1.400,00;"Arranjos & Vasos";"3 orquideas brancas cascatas Vaso de vidro"
108;"Arranjo com 3 orquídeas pink";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=108";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/095-arranjo-com-3-orquideas-pink.jpg";1.200,00;"Arranjos & Vasos";"3 orquídeas pink selecionadas"
106;"Arranjo com 3 rosas brancas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=106";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/093-arranjo-com-3-rosas-brancas.jpg";200,00;"Rosas Colombianas";"3 rosas colombianas brancas folhagem verde embalagem brancas"
32;"Arranjo com 3 Rosas Colombianas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=32";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/018-arranjo-com-3-rosas-colombianas.jpg";150,00;"Rosas Colombianas";"3 Rosas Colombianas aberta a mão Folhagem verde e tango Embalagem"
57;"Arranjo com Chocolate";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=57";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/043-arranjo-com-chocolate.jpg";130,00;"Arranjos & Vasos";"Arranjo floral acompanhado de caixa de chocolates"
53;"Arranjo com mini orquídea brancas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=53";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/039-arranjo-com-mini-orquidea-brancas.jpg";700,00;"Arranjos & Vasos";"4 vasos de mini orquídeas brancas Vaso de vidro Casca"
98;"Arranjo com rosas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=98";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/085-arranjo-com-rosas.jpg";800,00;"Rosas Colombianas";"Arranjo com 7 rosas manipuladas 4 galhos de lírios rosas 6 hortensias Gypsofila Vaso de vidro"
31;"Arranjo com Rosas vermelhas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=31";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/017-arranjo-com-rosas-vermelhas.jpg";450,00;"Rosas Colombianas";"18 rosas nacionais vermelhas Folhagem de pit Vaso de vidro"
10;"Arranjo de Astromélia coloridas (Cód 95)";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=10";"https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=800&q=80";280,00;"Arranjos & Vasos";"20 galhos de astromélias coloridas selecionadas, folhagem verde e vaso de vidro transparente (cerca de 45cm de altura)."
40;"Arranjo de flores";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=40";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/026-arranjo-de-flores.jpg";280,00;"Arranjos & Vasos";"Lirio (verificar cores) 2 gerberas 4 Rosas 4 flores do campo 2 cravina Rosa 4 astromelias Box"
8;"Arranjo de Flores do Campo e Eucalipto";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=8";"https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=800&q=80";260,00;"Arranjos & Vasos";"Delicado arranjo composto por mix de flores do campo coloridas, eucalipto perfumado e gypsofilas montado em vaso cylindro de vidro transparente."
41;"Arranjo de Flores Finas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=41";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/027-arranjo-de-flores-finas.jpg";350,00;"Arranjos & Vasos";"1 Lirio Branco 2 gerberas 6 Rosas 4 galhos de crisântemos coloridas 6 astromelias coloridas 2 lisianthus Folhagem verde Vaso de vidro"
110;"Arranjo de Rosa Colombiana";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=110";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/097-arranjo-de-rosa-colombiana.jpg";70,00;"Rosas Colombianas";"Arranjo individual de Rosa Colombiana"
118;"Arranjo de Rosas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=118";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/105-arranjo-de-rosas.jpg";220,00;"Rosas Colombianas";"12 Rosas nacionais branca Folhagem verde Vaso de acrílico verde"
130;"Arranjo de Rosas e Astromélia branca";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=130";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/118-arranjo-de-rosas-e-astromelia-branca.jpg";250,00;"Rosas Colombianas";"6 Rosas colombianas vermelhas manipuladas. 6 astromelias brancas Folhagem verde e tango Cachepot e embalagem"
30;"Arranjo de Rosas e Lírio";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=30";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/109-arranjo-de-rosas-e-lirio-1.jpg";400,00;"Rosas Colombianas";"7 Rosas colombianas 2 Lirios amarelo Folhagem Verde Cachepo"
87;"Arranjo grande com astromelias rosas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=87";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/074-arranjo-grande-com-astromelias-rosas.jpg";280,00;"Rosas Colombianas";"20 galhos de astromelias rosas Folhagem verde Vaso de vidrolaço de cetim rosa"
114;"Arranjo no vaso de vidro";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=114";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/101-arranjo-no-vaso-de-vidro.jpg";450,00;"Arranjos & Vasos";"Arranjo no vaso de vidro"
107;"Arranjo Pink de Rosas e Astromelia";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=107";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/094-arranjo-pink-de-rosas-e-astromelia.jpg";350,00;"Rosas Colombianas";"10 Rosas nacionais Pink 7 astromélias cor de rosa Folhagem verde e fantasia Vaso de vidro e laço"
90;"Arranjo Rosa";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=90";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/077-arranjo-rosa.jpg";300,00;"Rosas Colombianas";"6 Rosas Pink 6 astromelias Rosa 8 crisântemos Rosa 4 hortênsia Box"
123;"Arranjo Rose";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=123";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/111-arranjo-rose.jpg";380,00;"Arranjos & Vasos";"15 Rosas nacionais Cor de Rosa, Folhagem verde Vaso de vidro Laço de cetim"
103;"Arranjo Statis";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=103";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/090-arranjo-statis.jpg";300,00;"Arranjos & Vasos";"2 Anastasia roxa 3 boca de leão 2 gerberas 4 astromelias brancas 3 lisianthus 1 galho de Eucalipto Caixa kraft Lado cetim Embalagem rosé"
48;Begonia;"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=48";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/034-begonia.jpg";150,00;"Orquídeas & Plantas";"Planta Begônia floridade"
91;"Box com 12 rosas nacionais";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=91";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/078-box-com-12-rosas-nacionais.jpg";220,00;"Rosas Colombianas";"Buque com 12 rosas vermelhas nacional Folhagem verde e tango Box parda comprida"
70;"Box com Girassol e Chandon";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=70";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/057-box-com-girassol-e-chandon.jpg";300,00;"KITS & Presentes";"Arranjo de Girassol Chandon Baby Balão estrela Ferreiro Roche 100g Box verde com tampa Embalagem e laço"
76;"Box de Flores";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=76";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/063-box-de-flores.jpg";380,00;"Rosas Colombianas";"6 Rosas brancas 6 Rosas cor de rosa 4 astromelias brancas 4 astromelias Rosas Folhagem verde Roxinha Cachepo Rosa com tampa e laço"
95;"Box Mãe";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=95";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/082-box-mae.jpg";400,00;"Rosas Colombianas";"Bujudinho com 12 rosas coloridas e gypso no vidro Balão rosé Pelúcia de coração Espumante rosé Ferreiro Rocher 150g Box personalizada Dia das mães"
39;"Bujudinho de Astromélias coloridas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=39";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/025-bujudinho-de-astromelias-coloridas.jpg";130,00;"Arranjos & Vasos";"9 astromelias coloridas Folhagem de tango Vaso de vidro Laço (Cerca de 25cm)"
122;"Bujudinho de Rosa e Girasol";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=122";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/110-bujudinho-de-rosa-e-girasol.jpg";250,00;"Rosas Colombianas";"5 Rosas vermelhas colombiana 4 girassol Folhagem verde e aspargo 3 galhos de ruscos"
102;Buquê;"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=102";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/089-buque.jpg";180,00;"Buquês de Luxo";"1 lirio 3 cravinas 1 gerbera 1 lisianthus Eucalipto 2 anastásias Folhagem verde e tango Kraft Cru e Laço de corda"
81;"Buquê Amor Vibrante";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=81";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/068-buque-amor-vibrante.jpg";260,00;"Buquês de Luxo";"8 Rosas Colombianas vermelhas 6 astromelias vermelhas Gypsofila Folhagem verde pit Embalagem e laço"
99;"Buquê Angélica";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=99";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/086-buque-angelica.jpg";200,00;"Buquês de Luxo";"Buquê com 10 gérberas Rafaello"
121;"Buquê com 10 Rosas Nacional";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=121";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/108-buque-com-10-rosas-nacional.jpg";200,00;"Rosas Colombianas";"Buquê com 10 Rosas nacionais vermelhas Folhagem verde e tango"
1;"Buquê com 12 Colombianas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=1";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/001-buque-com-12-colombianas.jpg";300,00;"Rosas Colombianas";"Buquê de 12 Rosas Colombianas"
117;"Buquê com 12 Rosas e gypsophila";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=117";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/104-buque-com-12-rosas-e-gypsophila.jpg";260,00;"Rosas Colombianas";"Buquê com 12 rosas vermelhas nacional 2 galhos de gypsofila Folhagem verde Embalagem"
21;"Buquê com 15 rosas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=21";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/007-buque-com-15-rosas.jpg";300,00;"Rosas Colombianas";"15 rosas nacionais"
22;"Buquê com 15 Rosas amarelas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=22";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/008-buque-com-15-rosas-amarelas.jpg";300,00;"Rosas Colombianas";"Buquê com 15 Rosas amarelas"
127;"Buquê com 18 rosas nacionais";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=127";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/115-buque-com-18-rosas-nacionais.jpg";360,00;"Rosas Colombianas";"Buquê 18 Rosas nacionais vermelha (temos outras cores *consultar*)"
80;"Buquê com 20 Rosas Colombianas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=80";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/067-buque-com-20-rosas-colombianas.jpg";650,00;"Rosas Colombianas";"20 rosas colombianas vermelhas Folhagem de ruscos em volta Embalagem e laço"
104;"Buquê com 24 rosas colombianas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=104";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/091-buque-com-24-rosas-colombianas.jpg";700,00;"Rosas Colombianas";"12 rosas vermelhas 12 rosas brancas colombianas"
82;"Buquê com 24 Rosas importadas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=82";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/069-buque-com-24-rosas-importadas.jpg";600,00;"Rosas Colombianas";"24 Rosas colombinas vermelhas Embalagem Laço"
69;"Buquê com 24 rosas nacionais";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=69";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/056-buque-com-24-rosas-nacionais.jpg";360,00;"Rosas Colombianas";"Buquê com 24 rosas nacionais"
74;"Buquê com 3 lírios";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=74";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/061-buque-com-3-lirios.jpg";210,00;"Buquês de Luxo";"Buquê de 3 lírios"
100;"Buquê com 40 rosas colombianas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=100";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/087-buque-com-40-rosas-colombianas.jpg";950,00;"Rosas Colombianas";"40 botões de rosas Embalagem e laço personalizado Surpreenda seu amor!"
120;"Buquê com Cravinas Coloridas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=120";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/107-buque-com-cravinas-coloridas.jpg";150,00;"Buquês de Luxo";"10 galhos de cravinas Folhagens verdes e tango Papel rosa + papel comeia rosé Laço de corda"
124;"Buque com girassois";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=124";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/112-buque-com-girassois.jpg";150,00;"Buquês de Luxo";"3 girassol eucaliptos gypso embalagem"
84;"Buquê com Lírios coloridos";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=84";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/071-buque-com-lirios-coloridos.jpg";380,00;"Buquês de Luxo";"5 galhos de lírios coloridos 4 astromelias coloridas Folhagem Embalagem + laço"
71;"Buquê com Rosa e astromélias";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=71";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/058-buque-com-rosa-e-astromelias.jpg";300,00;"Rosas Colombianas";"12 Rosas nacionais vermelhas 6 astromelias"
2;"Buquê com rosas colombianas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=2";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/002-buque-com-rosas-colombianas.jpg";320,00;"Rosas Colombianas";"12 Rosas colombianas Folhagem verde e gypsofila Embalagem kraft e laço branco"
38;"Buquê com Rosas e Girassóis";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=38";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/024-buque-com-rosas-e-girassois.jpg";450,00;"Rosas Colombianas";"8 Rosas Colombianas manipuladas 8 girassóis"
23;"Buquê com Rosas Rosé";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=23";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/009-buque-com-rosas-rose.jpg";240,00;"Rosas Colombianas";"12 Rosas nacionais cor de rosa"
61;"Buque com tulipas e rosas inglesas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=61";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/047-buque-com-tulipas-e-rosas-inglesas.jpg";680,00;"Rosas Colombianas";"20 tulipas rosas 3 rosas inglesas Gypso Ruscos"
125;"Buquê de 60 rosas colombianas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=125";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/113-buque-de-60-rosas-colombianas.jpg";1.500,00;"Rosas Colombianas";"60 rosas colombianas Embalagem branca Laço de fita vermelho e branco"
119;"Buquê de flores finas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=119";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/106-buque-de-flores-finas.jpg";100,00;"Buquês de Luxo";"3 astromelias coloridas 3 galhos de flores do campo 2 Lisianhus 1 gerbera Folhagem verde e tango Embalagem e laço"
79;"Buquê de Flores Silvestres";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=79";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/066-buque-de-flores-silvestres.jpg";380,00;"Buquês de Luxo";"8 astromelias coloridas 10 margaridas coloridas 4 hortênsia 4 gerberas 1 lirio"
78;"Buque de gerberas colorida";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=78";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/065-buque-de-gerberas-colorida.jpg";480,00;"Buquês de Luxo";"6 rosas colombianas pink 3 boca de leão rosa 1 lírio rosa 3 gerberas rosa 4 margaridas lilás 4 astromelias rosa 3 lisianthus rosa"
36;"Buque de girassol";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=36";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/022-buque-de-girassol.jpg";150,00;"Buquês de Luxo";"6 girassóis Folhagem verde e tango Kraft e laço"
37;"Buquê de Girassol e Astromélias";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=37";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/023-buque-de-girassol-e-astromelias.jpg";200,00;"Buquês de Luxo";"6 girassóis 4 astromelias brancas"
11;"Buquê de Girassol e Astromélias (Cód 61)";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=11";"https://images.unsplash.com/photo-1597848212624-a19eb35e2651?w=800&q=80";200,00;"Buquês de Luxo";"Um buquê encantador com 6 vibrantes girassóis frescos e 4 delicadas astromélias brancas envoltos em papel kraft especial."
25;"Buque de Mix de Flores";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=25";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/011-buque-de-mix-de-flores.jpg";580,00;"Buquês de Luxo";"4 Rosas Colombianas 3 galhos de lirios coloridos 10 astromélias 4 gerbera colorida 4 Hortensia"
113;"Buquê de Rosa Branca nacional";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=113";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/100-buque-de-rosa-branca-nacional.jpg";240,00;"Rosas Colombianas";"12 Rosas Nacional brancas Folhagem verde e tango"
77;"Buquê de Rosas com astromelias";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=77";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/064-buque-de-rosas-com-astromelias.jpg";350,00;"Rosas Colombianas";"6 Rosas Pink colombiana 7 astromelias brancas Folhagem verde e gypsophila Embalagem e laço"
88;"Buquê de Rosas Manipuladas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=88";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/075-buque-de-rosas-manipuladas.jpg";210,00;"Rosas Colombianas";"7 Rosas colombianas manipuladas Folhagem verde e tango Embalagem e tango (Naturais)"
3;"Buquê de Rosas pink colombiana";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=3";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/003-buque-de-rosas-pink-colombiana.jpg";320,00;"Rosas Colombianas";"12 rosas colombianas Folhagem verde e tango Embalagem e laço rosa"
60;"Buque de tulipas rosa";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=60";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/046-buque-de-tulipas-rosa.jpg";350,00;"Rosas Colombianas";"10 tulipas, ruscus e gypso Embalagem papel jornal (Verificar cores disponíveis)"
72;"Buquê e Ferreiro Rocher";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=72";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/059-buque-e-ferreiro-rocher.jpg";280,00;"Buquês de Luxo";"Buquê com 12 Rosas vermelhas nacional Ferreiro Rocher 150g"
101;"Buquê Encanto Inesquecível";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=101";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/088-buque-encanto-inesquecivel.jpg";600,00;"Buquês de Luxo";"10 rosas brancas 5 gerberas rosas 3 gerbras amarelas 4 Margaridas amarelas 4 margaridas lilás 3 margaridas brancas 2 lírio vermelho 12 astromelias coloridas"
86;"Buquê Flores Silvestre";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=86";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/073-buque-flores-silvestre.jpg";180,00;"Buquês de Luxo";"3 crisântemo grande lilás 5 galhos de eucalipto 4 boca de leão Sempre viva Embalagem e laço Folhagem verde"
94;"Buquê Gerberas e Rosas Brancas";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=94";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/081-buque-gerberas-e-rosas-brancas.jpg";250,00;"Rosas Colombianas";"Buquê com Gerberas: 2 rosa e 2 vermelhas 6 Rosas brancas 3 Hortênsia Astromelias: 2 vermelhas, 2 amarelas, 2 rosas"
109;"Buquê Jasmine";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=109";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/096-buque-jasmine.jpg";300,00;"Buquês de Luxo";"1 lírio rosa 6 galhos de lisianthus Statis roxinha + Caspia 10 astromelias roxa Embalagem e laço"
24;"Buquê Lily";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=24";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/010-buque-lily.jpg";250,00;"Buquês de Luxo";"1 galhos de lírios rosa e 1 lírio branco 12 astromelias coloridas Folhagem e lirios"
9;"Buquê Premium com Mix de Flores (Cód 73)";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=9";"https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80";580,00;"Buquês de Luxo";"4 Rosas Colombianas, 3 galhos de lírios coloridos, 10 astromélias, 4 gérberas coloridas e folhagens nobres em embalagem especial de presente."
112;"Buquê Primeira";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=112";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/099-buque-primeira.jpg";200,00;"Buquês de Luxo";"5 rosas claras 5 rosas brancas 3 astromelias rosa 3 astronelias brancas Folhagem verde"
26;"Buquê rosa";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=26";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/012-buque-rosa.jpg";280,00;"Rosas Colombianas";"5 Rosas cor de rosa 5 rosa amarela 4 astromelias Rosa 4 amarela 4 hortênsia"
12;"Caixa Surpresa com Rosas Colombianas Glam";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=12";"https://images.unsplash.com/photo-1561181286-d3fee7d55364?w=800&q=80";389,90;"Rosas Colombianas";"Caixa exclusiva cartonada com 18 Rosas Colombianas vermelhas selecionadas e acabamento de cetim de luxo."
93;Cesta;"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=93";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/080-cesta.jpg";320,00;"Cestas Personalizadas";"Arranjo com 2 rosas colombianas Urso P Ferreiro Collection Cesta de vime"
116;"Cesta com Arranjo e chocolate";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=116";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/103-cesta-com-arranjo-e-chocolate.jpg";200,00;"Cestas Personalizadas";"Arranjo com Rosa colombiana Ferreiro Rocher Plaquinha Cestinha"
4;"Cesta com Chambinho do Amor";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=4";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/004-cesta-com-chambinho-do-amor.jpg";350,00;"Cestas Personalizadas";"Cesta especial recheada de carinho com rosas e mimos"
75;"Cesta com Kalandiva";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=75";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/062-cesta-com-kalandiva.jpg";180,00;"Cestas Personalizadas";"Cesta com flor Kalandiva"
96;"Cesta com Lirio e espumante";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=96";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/083-cesta-com-lirio-e-espumante.jpg";380,00;"Cestas Personalizadas";"1 Lirio plantado Plaquinha Ferreiro Rocher 150g Mini espumante rosé Caixa"
5;"Cesta com Rosa e Urso";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=5";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/005-cesta-com-rosa-e-urso.jpg";320,00;"Cestas Personalizadas";"Arranjo de Rosa Colombiana Urso pequeno Ferreiro Rocher 100g Cesta"
73;"Cesta com Rosas e Chandon";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=73";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/060-cesta-com-rosas-e-chandon.jpg";400,00;"Cestas Personalizadas";"Arranjo com Rosas colombianas Plaquinha de coração Caixa especialmente para você! Ferreiro Rocher 150g"
27;"Cesta de café";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=27";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/051-cesta-de-cafe-1.jpg";380,00;"Cestas Personalizadas";"Arranjo de girassol 4 mini croissant 4 mini pão de queijo 4 Carolina"
65;"Cesta de Café com girassol";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=65";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/052-cesta-de-cafe-com-girassol.jpg";380,00;"Cestas Personalizadas";"Arranjo de girassol Mamão Maça Uva Torrada Sucrilhos Pão francês Bisnaga 4 paes de queijo Frios 4 fatias queijo, 4 fatias de presunto Suco Iorgute Sache de Cappuccino Cesta de vime Embalagem e laço"
28;"Cesta de café com rosa";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=28";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/014-cesta-de-cafe-com-rosa.jpg";380,00;"Cestas Personalizadas";"Arranjo de rosa Torrada Sucrilhos Maça Uva Mamão Sache de Cappuccino Suco Iorgute Requeijão 4 fatias de queijo, 4 fatias de presunto Pão francês Bisnaga 4 paes de queijo"
29;"Cesta de Café Premium";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=29";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/015-cesta-de-cafe-premium.jpg";400,00;"Cestas Personalizadas";"Arranjo de rosa Torrada Sucrilhos Maça Sache de Cappuccino Suco Iorgute Requeijão Nutella 4 fatias de queijo, 4 fatias de presunto Pão francês Bisnaga 4 paes de queijo 4 croissant 3 Carolina"
43;"Coração de pelúcia";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=43";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/029-coracao-de-pelucia.jpg";45,00;"KITS & Presentes";13cm
42;"Emoji pelúcia";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=42";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/028-emoji-pelucia.jpg";40,00;"KITS & Presentes";Cada
85;"Espumante Rose Monte Pascoal";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=85";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/072-espumante-rose-monte-pascoal.jpg";60,00;"KITS & Presentes";Baby
34;"Ferreiro Rocher 100g";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=34";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/020-ferreiro-rocher-100g.jpg";60,00;"KITS & Presentes";"Ferreiro Rocher 100g"
128;"Ferreiro Rocher 150g";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=128";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/116-ferreiro-rocher-150g.jpg";70,00;"KITS & Presentes";"Ferreiro Rocher 150g"
33;"Ferreiro Rocher 50g";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=33";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/019-ferreiro-rocher-50g.jpg";25,00;"KITS & Presentes";"Caixa de bombons Ferreiro Rocher 50g"
35;"Ferreiro Rocher Collection 77g";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=35";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/021-ferreiro-rocher-collection-77g.jpg";65,00;"KITS & Presentes";"Ferreiro Rocher Collection 77g"
59;"Girassol Solidário com Ferreiro Collection";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=59";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/045-girassol-solidario-com-ferreiro-collection.jpg";90,00;"KITS & Presentes";"Girassol com caixa Ferrero Collection"
68;"Kit 2 Rosas e Mini Ferreiro Rocher";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=68";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/055-kit-2-rosas-e-mini-ferreiro-rocher.jpg";80,00;"Rosas Colombianas";"2 rosas colombianas vermelhas Ferreiro Rocher 50g"
111;"Kit Amor Perfeito";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=111";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/098-kit-amor-perfeito.jpg";220,00;"KITS & Presentes";"1 Arranjo de Rosa 1 Plaquinha 1 Espumante rosé 2 KitKat 2 Suflair Caixa kraft"
20;"Kit dia dos namorados";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=20";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/006-kit-dia-dos-namorados.jpg";1.300,00;"KITS & Presentes";"1 buque com 12 rosas colombianas vermelhas + gypso 1 box de 6 rosas e 4 astromelias brancas e gypso 1 pacote de pétalas 1 urso médio 1 Chandon G 1 cartão de amor G Buque de balões (3 corações M e 2 pequenos)"
6;"Kit Dia dos Namorados & Romântico";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=6";"https://images.unsplash.com/photo-1533616688419-b7a585564566?w=800&q=80";380,00;"KITS & Presentes";"Kit romântico completo com arranjo especial de 18 rosas colombianas vermelhas em vaso de vidro, caixa Ferrero Rocher 12 un e vinho/espumante selecionado."
67;"Kit maternidade";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=67";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/054-kit-maternidade.jpg";530,00;"KITS & Presentes";"1 mini orquídea (temos outras cores, consultar) Colônia Óleo Shampoo Pomada Lencinho Cesta de vime Embalagem"
55;"Kit Maternidade Clássico";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=55";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/041-kit-maternidade-classico.jpg";380,00;"KITS & Presentes";"Arranjo de astronelias coloridas (opções com 1 cor só) Urso Cesta de vime Colônia Lencinho Pomada Embalagem e laço"
56;"Kit maternidade Premium";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=56";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/042-kit-maternidade-premium.jpg";500,00;"KITS & Presentes";"1 mini orquídea Urso p Colônia Lencinho Pomada Creme de hidratação Embalagem e laço"
64;"Kit Natal";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=64";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/050-kit-natal.jpg";180,00;"KITS & Presentes";"Pinheiro Poinsettia Cesta de vime"
63;"Kit Natalino";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=63";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/049-kit-natalino.jpg";180,00;"KITS & Presentes";"Poinsettia Pinguim natal Cesta de vime"
50;"Lirio amarelo";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=50";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/036-lirio-amarelo.jpg";150,00;"Arranjos & Vasos";"Lírio amarelo em vaso"
49;"Lirio rosa";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=49";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/035-lirio-rosa.jpg";150,00;"Rosas Colombianas";Plantado
51;"Mini Orquidea Branca";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=51";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/037-mini-orquidea-branca.jpg";250,00;"Orquídeas & Plantas";"Cerca de 45cm"
52;"Mini orquidea pink";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=52";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/038-mini-orquidea-pink.jpg";250,00;"Orquídeas & Plantas";"(Imagem ilustrativa)"
83;"Nutella P";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=83";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/070-nutella-p.jpg";30,00;"KITS & Presentes";"Nutella pote 140g"
45;Orquidea;"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=45";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/031-orquidea.jpg";450,00;"Orquídeas & Plantas";"Orquídea Phalaenopsis selecionada"
126;"Orquídea Branca Cascata";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=126";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/114-orquidea-branca-cascata.jpg";350,00;"Orquídeas & Plantas";"(Cerca de 75cm)"
7;"Orquídea Phalaenopsis Premium em Vaso";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=7";"https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?w=800&q=80";290,00;"Orquídeas & Plantas";"Orquídea Phalaenopsis nobre com duas hastes floridas em charmoso vaso de cerâmica artesanal e acabamento com musgo natural."
97;"Orquidea Phale média";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=97";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/084-orquidea-phale-media.jpg";250,00;"Orquídeas & Plantas";"Orquídea Phalaenopsis média"
47;"Orquídea pink";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=47";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/033-orquidea-pink.jpg";350,00;"Orquídeas & Plantas";"Orquídea cor de rosa vibrante"
62;Poinssetia;"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=62";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/048-poinssetia.jpg";100,00;"Orquídeas & Plantas";35cm-40cm
58;"Rosa e Ferrero 50g";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=58";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/044-rosa-e-ferrero-50g.jpg";50,00;"Rosas Colombianas";"1 rosa colombiana Ferreiro rocher 50g"
89;"Urso Grande";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=89";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/076-urso-grande.jpg";350,00;"KITS & Presentes";"Aproximadamente 40cm×45cm"
44;"Urso p";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=44";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/030-urso-p.jpg";70,00;"KITS & Presentes";"Mini urso 18cm"
92;"Vaso de vidro";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=92";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/079-vaso-de-vidro.jpg";150,00;"Arranjos & Vasos";"Vaso de vidro para arranjos"
66;"Vinho Reservado Carmenere";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=66";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/053-vinho-reservado-carmenere.jpg";100,00;"KITS & Presentes";"Vinho Concha y Toro Reservado Carmenere 750ml"
54;"Violeta na cesta";"https://www.fightarcade.com.br/_catalogo referencia/product.php?id=54";"https://www.fightarcade.com.br/_catalogo referencia/assets/uploads/040-violeta-na-cesta.jpg";70,00;"Cestas Personalizadas";"Vaso de violeta na cesta"
`;

function parseCSV(content) {
    const lines = content.split('\n');
    const result = [];
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;
        
        const parts = line.split(';');
        if (parts.length >= 7) {
            const id = parseInt(parts[0].replace(/"/g, '').trim());
            const name = parts[1].replace(/"/g, '').trim();
            const link = parts[2].replace(/"/g, '').trim();
            let img = parts[3].replace(/"/g, '').trim();
            const priceStr = parts[4].replace(/"/g, '').replace('.', '').replace(',', '.').trim();
            const category = parts[5].replace(/"/g, '').trim();
            const desc = parts[6].replace(/"/g, '').trim();

            if (img.includes('assets/uploads/')) {
                img = img.split('assets/uploads/')[1];
            }

            result.push({
                id: id,
                name: name,
                slug: name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, ''),
                price: parseFloat(priceStr) || 150,
                description: desc,
                image_path: img,
                category: category
            });
        }
    }
    return result;
}

const parsedProducts = parseCSV(CSV_TEXT);

const jsonProducts = JSON.stringify(parsedProducts, null, 4);

let phpCode = `<?php
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
${jsonProducts}
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
`;

fs.writeFileSync(path.resolve(__dirname, '../import_produtos400.php'), phpCode, 'utf-8');
fs.writeFileSync(path.resolve(__dirname, '../seed_helena_flores.php'), phpCode, 'utf-8');

console.log('✅ Gerados com sucesso import_produtos400.php e seed_helena_flores.php com JSON_DATA seguro!');
