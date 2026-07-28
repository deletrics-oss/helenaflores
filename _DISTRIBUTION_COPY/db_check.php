<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$products = $pdo->query("SELECT id, name, image_path FROM products LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Product Images Check</h1>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>Image Path Value</th><th>File Exists?</th></tr>";

foreach ($products as $p) {
    if (empty($p['image_path'])) {
        $status = "<span style='color:red;'>NULL or Empty</span>";
        $exists = "-";
    } else {
        $status = "<code>" . htmlspecialchars($p['image_path']) . "</code>";
        $filePath = __DIR__ . '/assets/uploads/' . $p['image_path'];
        $exists = file_exists($filePath) ? "<span style='color:green;'>YES</span>" : "<span style='color:red;'>NO</span>";
    }
    echo "<tr><td>{$p['id']}</td><td>" . htmlspecialchars($p['name']) . "</td><td>$status</td><td>$exists</td></tr>";
}
echo "</table>";
