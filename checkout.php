<?php
ob_start(); // Start output buffering
require_once 'includes/auth.php';
require_once 'config/db.php'; // Ensure PDO is available
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// Process checkout (handle POST before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'state', 'zip', 'payment_method'];
    $valid = true;
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $valid = false;
            break;
        }
    }
    
    if ($valid) {
        // Store user details and payment method in session
        $_SESSION['email'] = $_POST['email'];
        $_SESSION['username'] = $_POST['first_name'] . ' ' . $_POST['last_name'];
        $_SESSION['payment_method'] = $_POST['payment_method'];
        
        // Get cart total
        $total = 0;
        if (!empty($_SESSION['cart'])) {
            $product_ids = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
            $stmt->execute($product_ids);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($products as $product) {
                $total += $product['price'] * $_SESSION['cart'][$product['id']];
            }
        }
        
        // Generate a unique transaction reference
        $tx_ref = 'CD-' . time() . '-' . $_SESSION['user_id'];
        $_SESSION['tx_ref'] = $tx_ref;
        $_SESSION['checkout_total'] = $total;
        
        // Redirect to unified payment processor
        header('Location: process_payment.php');
        exit;
    } else {
        $error = "Please fill in all required fields.";
    }
}

$page_title = 'Checkout';
require_once 'includes/header.php';

// Get cart total (for display purposes, if not POST)
$total = 0;
$products = [];
if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $product) {
        $total += $product['price'] * $_SESSION['cart'][$product['id']];
    }
}
?>

<style>
.payment-methods {
    display: flex;
    gap: 20px;
    align-items: center;
}
.payment-method {
    display: flex;
    align-items: center;
    gap: 8px;
}
.payment-logo {
    width: 100px;
    height: auto;
}
</style>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Checkout</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="post" id="checkout-form">
                    <div class="mb-4">
                        <h5>1. Review Your Order</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo $_SESSION['cart'][$product['id']]; ?></td>
                                        <td>₦<?php echo number_format($product['price'], 2); ?></td>
                                        <td>₦<?php echo number_format($product['price'] * $_SESSION['cart'][$product['id']], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Total</th>
                                    <th>₦<?php echo number_format($total, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="mb-4">
                        <h5>2. Shipping Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="zip" class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" id="zip" name="zip" 
                                       value="<?php echo isset($_POST['zip']) ? htmlspecialchars($_POST['zip']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>3. Payment Method</h5>
                        <div class="payment-methods">
                            <div class="payment-method form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="flutterwave" value="flutterwave">
                                <label class="form-check-label" for="flutterwave">
                                    <img src="assets/images/flutterwave-logo.png" alt="Flutterwave" class="payment-logo">
                                </label>
                            </div>
                            <div class="payment-method form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paystack" value="paystack" checked>
                                <label class="form-check-label" for="paystack">
                                    <img src="assets/images/paystack-logo.png" alt="Paystack" class="payment-logo">
                                </label>
                            </div>
                        </div>
                        <p class="mt-2 text-muted">Note: Flutterwave is currently experiencing issues. We recommend using Paystack for payments.</p>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-dark btn-lg">4. Complete Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush(); // Flush output buffer
?>