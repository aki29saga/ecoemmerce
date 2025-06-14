<?php
ob_start();

require_once 'config/db.php';
require_once 'includes/auth.php';

$page_title = 'Our Products';

// Get products from database with ratings
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = "SELECT p.id, p.name, p.price, p.stock, p.description, p.image_path, p.category, 
                 COALESCE(AVG(pr.rating), 0) as avg_rating, 
                 COUNT(pr.rating) as rating_count
          FROM products p
          LEFT JOIN product_ratings pr ON p.id = pr.product_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND p.category = ?";
    $params[] = $category;
}

$query .= " GROUP BY p.id ORDER BY p.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
}

require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="text-white bg-dark p-2 rounded">Our Collection</h2>
        </div>
        <div class="col-md-6">
            <form class="d-flex">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search products..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-dark" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-info">
            No products found. <?php if (!empty($search)): ?>
                Try a different search term.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <a href="view.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'assets/images/placeholder.jpg'); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 style="height: 300px; object-fit: cover;">
                        </a>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="view.php?id=<?php echo $product['id']; ?>" class="text-dark text-decoration-none">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted">
                                <?php echo substr(htmlspecialchars($product['description']), 0, 100); ?>...
                            </p>
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
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h6 mb-0">₦<?php echo number_format($product['price'], 2); ?></span>
                                <?php if ($product['stock'] > 0): ?>
                                    <a href="view.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-dark">
                                        View Details
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .rating .bi-star-fill { font-size: 1rem; }
</style>


<?php require_once 'includes/footer.php'; ?>