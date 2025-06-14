<?php
ob_start();


require_once 'config/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Add to cart logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    
    header('Location: cart.php');
    exit;
}

// Remove from cart logic
if (isset($_GET['remove'])) {
    $product_id = $_GET['remove'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
    header('Location: cart.php');
    exit;
}

// Update cart logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $product_id => $quantity) {
        if ($quantity > 0) {
            $_SESSION['cart'][$product_id] = $quantity;
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    header('Location: cart.php');
    exit;
}

$page_title = 'Shopping Cart';

// Get cart products with ratings
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.price, p.image_path, 
                                      COALESCE(AVG(pr.rating), 0) as avg_rating, 
                                      COUNT(pr.rating) as rating_count
                               FROM products p
                               LEFT JOIN product_ratings pr ON p.id = pr.product_id
                               WHERE p.id IN ($placeholders)
                               GROUP BY p.id");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $quantity = $_SESSION['cart'][$product['id']];
            $subtotal = $product['price'] * $quantity;
            $total += $subtotal;
            
            $cart_items[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'image' => $product['image_path'] ?? 'assets/images/new.png',
                'avg_rating' => $product['avg_rating'],
                'rating_count' => $product['rating_count']
            ];
        }
    } catch (PDOException $e) {
        error_log("Error retrieving cart products: " . $e->getMessage());
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-lg-8">
            <h2>Order Items</h2>
            
            <?php if (empty($cart_items)): ?>
                <div class="alert alert-danger">Your cart is Empty.</div>
                <a href="products.php" class="btn btn-dark">Continue Shopping</a>
            <?php else: ?>
                <form method="POST" action="cart.php">
                    <div class="row">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="col-12 col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="me-3" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                            <div class="flex-grow-1">
                                                <h5 class="card-title">
                                                    <a href="view.php?id=<?php echo $item['id']; ?>" 
                                                       class="text-dark text-decoration-none">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </a>
                                                </h5>
                                                <!-- Rating Display -->
                                                <div class="rating mb-2">
                                                    <?php if ($item['rating_count'] > 0): ?>
                                                        <span>
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star-fill <?php echo $i <= round($item['avg_rating']) ? 'text-warning' : 'text-secondary'; ?>"></i>
                                                            <?php endfor; ?>
                                                            <?php echo number_format($item['avg_rating'], 1); ?> 
                                                            (<?php echo $item['rating_count']; ?> reviews)
                                                        </span>
                                                    <?php else: ?>
                                                        <span>No ratings yet.</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="card-text mb-1">
                                                    <strong>Price:</strong> ₦<?php echo number_format($item['price'], 2); ?>
                                                </p>
                                                <div class="mb-2">
                                                    <label for="quantity-<?php echo $item['id']; ?>" class="form-label">Quantity:</label>
                                                    <input type="number" 
                                                           name="quantity[<?php echo $item['id']; ?>]" 
                                                           id="quantity-<?php echo $item['id']; ?>" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" 
                                                           class="form-control" 
                                                           style="width: 70px;">
                                                </div>
                                                <p class="card-text">
                                                    <strong>Subtotal:</strong> ₦<?php echo number_format($item['subtotal'], 2); ?>
                                                </p>
                                                <a href="cart.php?remove=<?php echo $item['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger">Remove</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-between mb-4">
                        <a href="products.php" class="btn btn-outline-dark">Continue Shopping</a>
                        <button type="submit" name="update_cart" class="btn btn-dark">Update Cart</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($cart_items)): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Order Summary</h5>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span>₦<?php echo number_format($total, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <span>Free</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total:</span>
                        <span>₦<?php echo number_format($total, 2); ?></span>
                    </div>
                    <a href="checkout.php" class="btn btn-dark w-100 mt-3">Proceed to Checkout</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .rating .bi-star-fill { font-size: 1rem; }
    .card img { border-radius: 5px; }
</style>


<?php require_once 'includes/footer.php'; ?>