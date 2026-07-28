<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int) $_POST['product_id'];
    $name = strip_tags(trim($_POST['user_name']));
    $rating = (int) $_POST['rating'];
    $comment = strip_tags(trim($_POST['comment']));

    if ($pid && $name && $rating) {
        $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_name, rating, comment, approved) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$pid, $name, $rating, $comment]);
    }

    // Redirect Back
    header("Location: product.php?id=$pid#reviews-section");
    exit;
}
header("Location: index.php");
?>