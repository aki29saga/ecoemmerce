<?php
ob_start();


require_once 'config/db.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID";
    header('Location: index.php');
    exit;
}

$product_id = (int)$_GET['id'];

// Fetch product details
try {
    $stmt = $pdo->prepare("SELECT id, name, price, stock, category, description, image_path, badges FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        $_SESSION['error'] = "Product not found";
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching product: " . $e->getMessage());
    $_SESSION['error'] = "Unable to load product details.";
    header('Location: index.php');
    exit;
}

// Fetch average rating and count
try {
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count 
                           FROM product_ratings WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $rating_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
    $rating_count = $rating_data['rating_count'];
} catch (PDOException $e) {
    error_log("Error fetching ratings: " . $e->getMessage());
    $avg_rating = 0;
    $rating_count = 0;
}

// Check if user has already rated
try {
    $stmt = $pdo->prepare("SELECT id FROM product_ratings WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$product_id, $user_id]);
    $has_rated = $stmt->fetch() !== false;
} catch (PDOException $e) {
    error_log("Error checking user rating: " . $e->getMessage());
    $has_rated = false;
}

// Get additional images
try {
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $additional_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching additional images: " . $e->getMessage());
    $additional_images = [];
}

// Get related products (same category)
$related_products = [];
if (!empty($product['category'])) {
    try {
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.price, p.image_path, 
                                      COALESCE(AVG(pr.rating), 0) as avg_rating, 
                                      COUNT(pr.rating) as rating_count
                               FROM products p
                               LEFT JOIN product_ratings pr ON p.id = pr.product_id
                               WHERE p.category = ? AND p.id != ?
                               GROUP BY p.id
                               LIMIT 4");
        $stmt->execute([$product['category'], $product['id']]);
        $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching related products: " . $e->getMessage());
    }
}

$page_title = htmlspecialchars($product['name']);
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="product-image-container text-center">
                <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'assets/images/placeholder.jpg'); ?>" 
                     class="img-fluid rounded main-image" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="max-height: 600px; width: auto;">
            </div>
            <?php if (!empty($additional_images)): ?>
                <div class="d-flex flex-wrap mt-3 justify-content-center">
                    <?php foreach ($additional_images as $index => $image): ?>
                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                             class="thumbnail-img me-2 mb-2 <?php echo $index === 0 ? 'active' : ''; ?>" 
                             alt="Thumbnail <?php echo $index + 1; ?>"
                             onclick="document.querySelector('.main-image').src = this.src; 
                                      document.querySelectorAll('.thumbnail-img').forEach(img => img.classList.remove('active')); 
                                      this.classList.add('active');">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-6">
            <h1 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <?php if (!empty($product['badges'])): ?>
                <div class="mb-2">
                    <?php foreach (explode(',', $product['badges']) as $badge): ?>
                        <span class="badge bg-primary me-1"><?php echo htmlspecialchars(trim($badge)); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($product['category'])): ?>
                <p class="text-muted mb-2">Category: <?php echo htmlspecialchars($product['category']); ?></p>
            <?php endif; ?>
            
            <div class="d-flex align-items-center mb-3">
                <span class="h3 text-dark me-2">₦<?php echo number_format($product['price'], 2); ?></span>
                <?php if ($product['stock'] > 0): ?>
                    <span class="badge bg-success">In Stock (<?php echo $product['stock']; ?> available)</span>
                <?php else: ?>
                    <span class="badge bg-danger">Out of Stock</span>
                <?php endif; ?>
            </div>
            
            <!-- Rating Section -->
            <div class="rating mb-3">
                <div class="avg-rating mb-2">
                    <?php if ($rating_count > 0): ?>
                        <span>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill <?php echo $i <= round($avg_rating) ? 'text-warning' : 'text-secondary'; ?>"></i>
                            <?php endfor; ?>
                            <?php echo $avg_rating; ?> (<?php echo $rating_count; ?> reviews)
                        </span>
                    <?php else: ?>
                        <span>No ratings yet.</span>
                    <?php endif; ?>
                </div>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!$has_rated): ?>
                    <form action="submit_rating.php" method="POST" class="star-rating d-flex align-items-center">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                            <label for="star<?php echo $i; ?>" class="bi bi-star-fill me-1"></label>
                        <?php endfor; ?>
                        <button type="submit" class="btn btn-primary btn-sm ms-2">Submit Rating</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">You have already rated this product.</p>
                <?php endif; ?>
            </div>
            
            <!-- Tabs for Description and Specifications -->
            <div class="mb-4">
                <ul class="nav nav-tabs mb-2" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="desc-tab" data-bs-toggle="tab" 
                                data-bs-target="#desc" type="button" role="tab">Description</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="specs-tab" data-bs-toggle="tab" 
                                data-bs-target="#specs" type="button" role="tab">Specifications</button>
                    </li>
                </ul>
                <div class="tab-content" id="productTabsContent">
                    <div class="tab-pane fade show active" id="desc" role="tabpanel">
                        <p class="p-3"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    <div class="tab-pane fade" id="specs" role="tabpanel">
                        <ul class="list-group list-group-flush p-3">
                            <li class="list-group-item">Material: Cotton Blend</li>
                            <li class="list-group-item">Size: S, M, L, XL</li>
                            <!-- Add dynamic specs from database if available -->
                        </ul>
                    </div>
                </div>
            </div>
            
            <?php if ($product['stock'] > 0): ?>
                <form method="post" action="cart.php" class="mb-4 sticky-cart">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="row align-items-center mb-3">
                        <div class="col-md-2">
                            <label for="quantity" class="form-label">Quantity:</label>
                        </div>
                        <div class="col-md-3">
                            <input type="number" class="form-control" id="quantity" 
                                   name="quantity" min="1" max="<?php echo $product['stock']; ?>" 
                                   value="1">
                        </div>
                    </div>
                    <button type="submit" name="add_to_cart" class="btn btn-dark btn-lg w-100 py-3">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning sticky-cart">
                    <i class="bi bi-exclamation-triangle"></i> This product is currently out of stock. Check back later!
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between border-top pt-3">
                <a href="index.php" class="btn btn-outline-dark">
                    <i class="bi bi-arrow-left"></i> Continue Shopping
                </a>
                <a href="cart.php" class="btn btn-outline-dark">
                    View Cart <i class="bi bi-cart"></i>
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($related_products)): ?>
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="mb-4">You May Also Like</h3>
            <div class="row">
                <?php foreach ($related_products as $related): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <a href="view.php?id=<?php echo $related['id']; ?>">
                                <img src="<?php echo htmlspecialchars($related['image_path'] ?? 'assets/images/placeholder.jpg'); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($related['name']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            </a>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="view.php?id=<?php echo $related['id']; ?>" class="text-dark text-decoration-none">
                                        <?php echo htmlspecialchars($related['name']); ?>
                                    </a>
                                </h5>
                                <p class="card-text text-muted">
                                    ₦<?php echo number_format($related['price'], 2); ?>
                                </p>
                                <!-- Related Product Rating Display -->
                                <div class="rating mb-2">
                                    <?php
                                    $related_rating = floatval($related['avg_rating']);
                                    ?>
                                    <span>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star-fill <?php echo $i <= round($related_rating) ? 'text-warning' : 'text-secondary'; ?>"></i>
                                        <?php endfor; ?>
                                        <?php echo number_format($related_rating, 1); ?> 
                                        (<?php echo $related['rating_count']; ?> reviews)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .star-rating input[type="radio"] { display: none; }
    .star-rating label { font-size: 1.2rem; color: #ddd; cursor: pointer; }
    .star-rating input[type="radio"]:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label { color: #ffc107; }
</style>

<script>
    // Enhance star rating interactivity
    document.querySelectorAll('.star-rating label').forEach(label => {
        label.addEventListener('mouseover', () => {
            const rating = label.getAttribute('for').replace('star', '');
            document.querySelectorAll('.star-rating label').forEach(l => {
                if (l.getAttribute('for').replace('star', '') <= rating) {
                    l.classList.add('text-warning');
                } else {
                    l.classList.remove('text-warning');
                }
            });
        });
        label.addEventListener('mouseout', () => {
            const checked = document.querySelector('.star-rating input:checked');
            const checkedRating = checked ? checked.value : 0;
            document.querySelectorAll('.star-rating label').forEach(l => {
                if (l.getAttribute('for').replace('star', '') <= checkedRating) {
                    l.classList.add('text-warning');
                } else {
                    l.classList.remove('text-warning');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>