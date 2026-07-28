<?php
/**
 * Google Merchant Center Product Feed
 * URL: https://fightarcade.com.br/catalogo/google-merchant-feed.php
 * 
 * Cadastre esta URL no Google Merchant Center para listar
 * seus produtos gratuitamente no Google Shopping.
 * 
 * Formato: RSS 2.0 + Google Shopping namespace
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(str_replace('/catalogo', '', 'https://www.fightarcade.com.br/catalogo'), '/');
// Use full domain URL
$base = 'https://www.fightarcade.com.br/catalogo';

// Fetch active products
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.active = 1 
    AND (p.show_on_site = 1 OR p.show_on_site IS NULL)
    AND (c.show_on_site = 1 OR c.show_on_site IS NULL OR p.category_id IS NULL)
    ORDER BY p.featured DESC, p.id DESC
");
$products = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
    <title>Fight Arcade - Peças Arcade e Fliperama</title>
    <link><?php echo $base; ?></link>
    <description>A maior loja especializada em peças para arcade e fliperama do Brasil. Botões Sanwa, controles fightstick, placas JAMMA e kits DIY.</description>

<?php foreach ($products as $p): 
    $title = htmlspecialchars(strip_tags($p['name']), ENT_XML1, 'UTF-8');
    $desc = htmlspecialchars(strip_tags($p['description'] ?: $p['name'] . ' - Peça arcade profissional. Fight Arcade.'), ENT_XML1, 'UTF-8');
    $desc = mb_strimwidth($desc, 0, 5000, '...');
    $price = number_format($p['price'], 2, '.', '');
    $link = $base . '/product.php?id=' . $p['id'];
    $image = '';
    if (!empty($p['image_path'])) {
        if (strpos($p['image_path'], 'http') === 0) {
            $image = $p['image_path'];
        } else {
            $image = $base . '/assets/uploads/' . $p['image_path'];
        }
    }
    $sku = !empty($p['sku']) ? $p['sku'] : 'FA-' . $p['id'];
    $ean = !empty($p['ean']) ? $p['ean'] : '';
    $category = htmlspecialchars($p['category_name'] ?? 'Peças Arcade', ENT_XML1, 'UTF-8');
    $stock = isset($p['stock']) ? (int)$p['stock'] : 99;
    $availability = ($stock > 0) ? 'in_stock' : 'out_of_stock';
    $condition = 'new';
    $brand = 'Fight Arcade';
?>
    <item>
        <g:id><?php echo $sku; ?></g:id>
        <g:title><?php echo $title; ?></g:title>
        <g:description><?php echo $desc; ?></g:description>
        <g:link><?php echo htmlspecialchars($link, ENT_XML1, 'UTF-8'); ?></g:link>
        <?php if ($image): ?>
        <g:image_link><?php echo htmlspecialchars($image, ENT_XML1, 'UTF-8'); ?></g:image_link>
        <?php endif; ?>
        <g:price><?php echo $price; ?> BRL</g:price>
        <?php if ($p['price'] > 50): ?>
        <g:sale_price><?php echo number_format($p['price'] * 0.95, 2, '.', ''); ?> BRL</g:sale_price>
        <?php endif; ?>
        <g:availability><?php echo $availability; ?></g:availability>
        <g:condition><?php echo $condition; ?></g:condition>
        <g:brand><?php echo $brand; ?></g:brand>
        <g:product_type><?php echo $category; ?></g:product_type>
        <g:google_product_category>Electronics > Arcade Equipment</g:google_product_category>
        <?php if ($ean): ?>
        <g:gtin><?php echo htmlspecialchars($ean, ENT_XML1, 'UTF-8'); ?></g:gtin>
        <?php else: ?>
        <g:identifier_exists>false</g:identifier_exists>
        <?php endif; ?>
        <g:mpn><?php echo htmlspecialchars($sku, ENT_XML1, 'UTF-8'); ?></g:mpn>
        <g:shipping>
            <g:country>BR</g:country>
            <g:service>Correios</g:service>
            <g:price>0 BRL</g:price>
        </g:shipping>
    </item>
<?php endforeach; ?>

</channel>
</rss>
