<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header("Content-Type: application/xml; charset=utf-8");

$base = rtrim(BASE_URL, '/');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <!-- Home -->
    <url>
        <loc><?php echo $base; ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Static Pages -->
    <url>
        <loc><?php echo $base; ?>/login.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?php echo $base; ?>/register.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?php echo $base; ?>/about.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>
    <url>
        <loc><?php echo $base; ?>/politica.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>

    <!-- Categories -->
    <?php
    $stmtC = $pdo->query("SELECT id, name FROM categories WHERE (show_on_site = 1 OR show_on_site IS NULL) ORDER BY id ASC");
    $cats = $stmtC->fetchAll();
    foreach ($cats as $c):
    ?>
    <url>
        <loc><?php echo $base; ?>/?cat=<?php echo $c['id']; ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>

    <!-- Products -->
    <?php
    $stmtP = $pdo->query("SELECT id, name, image_path, updated_at FROM products WHERE active = 1 AND (show_on_site = 1 OR show_on_site IS NULL) ORDER BY id DESC");
    $prods = $stmtP->fetchAll();
    foreach ($prods as $p):
        $lastmod = $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d');
    ?>
    <url>
        <loc><?php echo $base; ?>/product.php?id=<?php echo $p['id']; ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <?php if (!empty($p['image_path'])): ?>
        <image:image>
            <image:loc><?php echo $base; ?>/assets/uploads/<?php echo $p['image_path']; ?></image:loc>
            <image:title><?php echo htmlspecialchars(strip_tags($p['name'])); ?></image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>

</urlset>