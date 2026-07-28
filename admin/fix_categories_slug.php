<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Helper function to create slug
function generateSlug($string)
{
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-zA-Z0-9 -]/', '', $string);
    $string = strtolower(trim($string));
    $string = preg_replace('/-+/', '-', $string);
    return $string;
}

echo "<h1>Corrigindo Slugs de Categorias...</h1>";

// 1. Fetch all categories
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

foreach ($categories as $cat) {
    $baseSlug = generateSlug($cat['name']);
    $finalSlug = $baseSlug;
    $counter = 1;

    // Check availability (exclude self)
    while (true) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
        $check->execute([$finalSlug, $cat['id']]);
        if ($check->fetchColumn() == 0) {
            break;
        }
        $finalSlug = $baseSlug . '-' . $counter;
        $counter++;
    }

    // Update if different
    if ($cat['slug'] !== $finalSlug) {
        $pdo->prepare("UPDATE categories SET slug = ? WHERE id = ?")->execute([$finalSlug, $cat['id']]);
        echo "Corrigido: <b>{$cat['name']}</b> ({$cat['slug']}) -> <b>{$finalSlug}</b><br>";
    } else {
        echo "OK: {$cat['name']} ({$cat['slug']})<br>";
    }
}

echo "<hr><h3>Processo Concluído!</h3>";
echo "<a href='categories.php'>Voltar para Categorias</a>";
?>