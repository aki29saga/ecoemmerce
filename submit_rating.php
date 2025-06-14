<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['rating'])) {
    $product_id = (int)$_POST['product_id'];
    $user_id = $_SESSION['user_id'];
    $rating = floatval($_POST['rating']);

    // Check if user already rated
    $stmt = $pdo->prepare("SELECT id FROM product_ratings WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$product_id, $user_id]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "You have already rated this product.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO product_ratings (product_id, user_id, rating) VALUES (?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $rating]);
        $_SESSION['success'] = "Rating submitted successfully!";
    }
}
header('Location: view.php?id=' . $product_id);
exit;
?>