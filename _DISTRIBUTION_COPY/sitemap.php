<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Home -->
    <url>
        <loc>
            <?php echo BASE_URL; ?>/
        </loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Static Pages -->
    <url>
        <loc>
            <?php echo BASE_URL; ?>/login.php
        </loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc>
            <?php echo BASE_URL; ?>/register.php
        </loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Categories -->
    <?php
    $stmtC = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC");
    $cats = $stmtC->fetchAll();
    foreach ($cats as $c):
        ?>
        <url>
            <loc>
                <?php echo BASE_URL; ?>/?cat=
                <?php echo $c['id']; ?>
            </loc>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>

    <!-- Products -->
    <?php
    $stmtP = $pdo->query("SELECT id, updated_at FROM products WHERE active = 1 ORDER BY id DESC");
    $prods = $stmtP->fetchAll();
    foreach ($prods as $p):
        $lastmod = $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d');
        ?>
        <url>
            <loc>
                <?php echo BASE_URL; ?>/product.php?id=
                <?php echo $p['id']; ?>
            </loc>
            <lastmod>
                <?php echo $lastmod; ?>
            </lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>
        </url>
    <?php endforeach; ?>

</urlset>