<?php
// includes/seo_engine.php

function generate_powerful_seo($data = [])
{
  // Configurações Padrão
  $default_title = "Fight Arcade | Peças Arcade, Botões Sanwa, Controles Fightstick - Compre Online";
  $default_desc = "A maior loja especializada em peças para fliperama e arcade do Brasil. Botões Sanwa, comandos, controles fightstick e kits DIY. Envio para todo Brasil.";
  $site_name = "Fight Arcade";
  // Check if BASE_URL is defined, else fallback
  $base_url = defined('BASE_URL') ? BASE_URL : 'http://localhost/catalogo';
  $current_url = $base_url . $_SERVER['REQUEST_URI'];

  // Merge Data
  $title = !empty($data['seo_title']) ? $data['seo_title'] : ($data['name'] ?? $default_title);
  $description = !empty($data['seo_description']) ? mb_strimwidth($data['seo_description'], 0, 160, "...") : $default_desc;

  // Image Handling
  if (!empty($data['image_path'])) {
    $image = $base_url . '/assets/uploads/' . $data['image_path'];
  } else {
    $image = $base_url . '/assets/img/og-default.jpg'; // Ensure this image exists roughly or use logo
  }

  $type = $data['type'] ?? 'website';

  $title_clean = htmlspecialchars(strip_tags($title));
  $desc_clean = htmlspecialchars(strip_tags($description));

  ob_start();
  ?>

  <title>
    <?php echo $title_clean; ?>
  </title>
  <meta name="description" content="<?php echo $desc_clean; ?>">
  <link rel="canonical" href="<?php echo $current_url; ?>" />
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Open Graph -->
  <meta property="og:locale" content="pt_BR" />
  <meta property="og:type" content="<?php echo $type; ?>" />
  <meta property="og:title" content="<?php echo $title_clean; ?>" />
  <meta property="og:description" content="<?php echo $desc_clean; ?>" />
  <meta property="og:url" content="<?php echo $current_url; ?>" />
  <meta property="og:site_name" content="<?php echo $site_name; ?>" />
  <meta property="og:image" content="<?php echo $image; ?>" />
  <meta property="og:image:secure_url" content="<?php echo $image; ?>" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?php echo $title_clean; ?>" />
  <meta name="twitter:description" content="<?php echo $desc_clean; ?>" />
  <meta name="twitter:image" content="<?php echo $image; ?>" />

  <!-- Schema.org JSON-LD -->
  <?php if ($type === 'product' && isset($data['product_info'])):
    $p = $data['product_info'];
    $price = number_format($p['price'], 2, '.', '');
    $inStock = isset($p['active']) && $p['active'] == 1;
    $availability = $inStock ? "https://schema.org/InStock" : "https://schema.org/OutOfStock";

    $condition_map = [
      'new' => 'https://schema.org/NewCondition',
      'used' => 'https://schema.org/UsedCondition',
      'refurbished' => 'https://schema.org/RefurbishedCondition'
    ];
    $itemCondition = $condition_map[$p['condition_status'] ?? 'new'];

    // Fetch Ratings (Aggregate) if DB connection is available
    $ratingJSON = "";
    global $pdo; // Ensure we can access DB
    if ($pdo && isset($p['id'])) {
      $stmtR = $pdo->prepare("SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM product_reviews WHERE product_id = ? AND approved = 1");
      $stmtR->execute([$p['id']]);
      $rData = $stmtR->fetch();
      if ($rData && $rData['total'] > 0) {
        $avg = number_format($rData['avg_rating'], 1, '.', '');
        $ratingJSON = ',
                "aggregateRating": {
                    "@type": "AggregateRating",
                    "ratingValue": "' . $avg . '",
                    "reviewCount": "' . $rData['total'] . '"
                }';
      }
    }
    ?>
    <script type="application/ld+json">
                {
                  "@context": "https://schema.org/",
                  "@type": "Product",
                  "name": "<?php echo htmlspecialchars(strip_tags($p['name'])); ?>",
                  "image": [ "<?php echo $image; ?>" ],
                  "description": "<?php echo htmlspecialchars(strip_tags($p['description'])); ?>",
                  "sku": "<?php echo $p['sku']; ?>",
                  <?php if (!empty($p['mpn'])): ?>"mpn": "<?php echo $p['mpn']; ?>",<?php endif; ?>
                  <?php if (!empty($p['gtin'])): ?>"gtin": "<?php echo $p['gtin']; ?>",<?php endif; ?>
                  <?php if (!empty($p['brand'])): ?>
                        "brand": {
                          "@type": "Brand",
                          "name": "<?php echo htmlspecialchars($p['brand']); ?>"
                        },
                  <?php endif; ?>
              
                  <?php echo $ratingJSON; ?>

                  "offers": {
                    "@type": "Offer",
                    "url": "<?php echo $current_url; ?>",
                    "priceCurrency": "BRL",
                    "price": "<?php echo $price; ?>",
                    "priceValidUntil": "<?php echo date('Y-12-31', strtotime('+1 year')); ?>",
                    "itemCondition": "<?php echo $itemCondition; ?>",
                    "availability": "<?php echo $availability; ?>",
                    "seller": {
                      "@type": "Organization",
                      "name": "<?php echo $site_name; ?>"
                    }
                  }
                }
            </script>

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "BreadcrumbList",
              "itemListElement": [{
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "<?php echo $base_url; ?>"
              },{
                "@type": "ListItem",
                "position": 2,
                "name": "Catálogo",
                "item": "<?php echo $base_url; ?>/?cat=<?php echo $p['category_id'] ?? 0; ?>"
              },{
                "@type": "ListItem",
                "position": 3,
                "name": "<?php echo htmlspecialchars(strip_tags($p['name'])); ?>",
                "item": "<?php echo $current_url; ?>"
              }]
            }
            </script>

  <?php else: ?>
    <script type="application/ld+json">
                {
                  "@context": "https://schema.org",
                  "@type": "WebSite",
                  "name": "<?php echo $site_name; ?>",
                  "url": "<?php echo $base_url; ?>",
                  "potentialAction": {
                    "@type": "SearchAction",
                    "target": "<?php echo $base_url; ?>/?q={search_term_string}",
                    "query-input": "required name=search_term_string"
                  }
                }
            </script>

    <!-- Local Business / Organization -->
    <script type="application/ld+json">
                {
                  "@context": "https://schema.org",
                  "@type": ["Organization", "OnlineStore"],
                  "name": "<?php echo $site_name; ?>",
                  "url": "<?php echo $base_url; ?>",
                  "logo": "<?php echo $base_url; ?>/assets/logo.png",
                  "description": "<?php echo $default_desc; ?>",
                  "telephone": "+5511988121976",
                  "email": "contato@fightarcade.com.br",
                  "address": {
                    "@type": "PostalAddress",
                    "streetAddress": "Av. São João, 802",
                    "addressLocality": "São Paulo",
                    "addressRegion": "SP",
                    "postalCode": "01036-000",
                    "addressCountry": "BR"
                  },
                  "sameAs": [
                    "https://instagram.com/fightarcade",
                    "https://facebook.com/fightarcade",
                    "https://youtube.com/fightarcade"
                  ],
                  "priceRange": "$$",
                  "openingHours": "Mo-Fr 09:00-18:00, Sa 09:00-13:00"
                }
            </script>

    <!-- FAQ Schema (Rich Snippets) -->
    <script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "FAQPage",
              "mainEntity": [{
                "@type": "Question",
                "name": "Quais peças de arcade vocês vendem?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "Vendemos botões Sanwa, Seimitsu, alavancas, placas JAMMA, controles fightstick, kits DIY completos, cabos, conectores e todas as peças para montar ou restaurar um fliperama."
                }
              },{
                "@type": "Question",
                "name": "Vocês entregam para todo o Brasil?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "Sim! Enviamos para todo o Brasil via Correios, Loggi, J&T, Jadlog e Melhor Envio. Para São Paulo capital e Rio de Janeiro, oferecemos entrega expressa no mesmo dia via Lalamove."
                }
              },{
                "@type": "Question",
                "name": "Como faço para montar um arcade em casa?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "Oferecemos kits DIY completos com placa, botões, alavancas e cabeamento. Basta escolher o kit adequado ao seu projeto, seguir nosso guia de montagem e conectar à sua TV ou monitor. Temos suporte técnico via WhatsApp."
                }
              },{
                "@type": "Question",
                "name": "Quais formas de pagamento são aceitas?",
                "acceptedAnswer": {
                  "@type": "Answer",
                  "text": "Aceitamos PIX (5% de desconto), cartão de crédito em até 12x (3x sem juros), boleto bancário e transferência bancária."
                }
              }]
            }
            </script>
  <?php endif; ?>
  <?php
  return ob_get_clean();
}
?>