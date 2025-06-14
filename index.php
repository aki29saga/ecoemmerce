<?php
ob_start();


require_once 'includes/auth.php';
require_once 'config/db.php';

$page_title = 'Home';

// Get featured products with ratings
try {
    $stmt = $pdo->query("SELECT p.id, p.name, p.price, p.image_path, 
                                COALESCE(AVG(pr.rating), 0) as avg_rating, 
                                COUNT(pr.rating) as rating_count
                         FROM products p
                         LEFT JOIN product_ratings pr ON p.id = pr.product_id
                         GROUP BY p.id
                         LIMIT 6");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
}

require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="jumbotron bg-white p-5 mb-4 rounded">
            <div class="mb-4">
                <img src="assets/images/new.png" alt="Crazy Dej Logo" class="img-fluid" style="max-width: 400px;">
            </div>
            <h1 class="display-4">Welcome to Crazy Dej</h1>
            <p class="lead">Discover our exclusive collection of stylish clothing.</p>
            <hr class="my-4">
            <a class="btn btn-dark btn-lg" href="products.php" role="button">Shop Now</a>
        </div>
    </div>
</div>

<h2 class="mb-4">Quick sale</h2>
<div class="row">
    <?php foreach ($products as $product): ?>
        <div class="col-6 col-lg-3 mb-4">
            <div class="card h-100">
                <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'assets/images/placeholder.jpg'); ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                    <p class="card-text">₦<?php echo number_format($product['price'], 2); ?></p>
                    <!-- Rating Display -->
                    <div class="rating mb-2">
                        <?php if ($product['rating_count'] > 0): ?>
                            <span>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star-fill <?php echo $i <= round($product['avg_rating']) ? 'text-warning' : 'text-secondary'; ?>"></i>
                                <?php endfor; ?>
                                <?php echo number_format($product['avg_rating'], 1); ?> 
                                (<?php echo $product['rating_count']; ?> reviews)
                            </span>
                        <?php else: ?>
                            <span>No ratings yet.</span>
                        <?php endif; ?>
                    </div>
                    <a href="view.php?id=<?php echo $product['id']; ?>" class="btn btn-dark">View Details</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
    .rating .bi-star-fill { font-size: 1rem; }
</style>


<?php require_once 'includes/footer.php'; ?>