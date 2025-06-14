<?php
ob_start();
require_once 'includes/auth.php';
require_once 'config/db.php';

if (!isLoggedIn() || !isset($_SESSION['last_order_id'])) {
    header('Location: index.php');
    exit;
}

// Get order details
$order_id = $_SESSION['last_order_id'];
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: index.php');
    exit;
}

// Get order items
$stmt = $pdo->prepare("
    SELECT * FROM order_items WHERE order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Order Confirmation';
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h4>Order Confirmation</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h5>Thank you for your order!</h5>
                    <p>Your payment was successful and your order is being processed.</p>
                </div>
                
                <div class="mb-4">
                    <h5>Order Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                            <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></p>
                            <p><strong>Total Paid:</strong> ₦<?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5>Items Ordered</h5>
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
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>₦<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>₦<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center">
                    <a href="orders.php" class="btn btn-dark">View All Orders</a>
                    <a href="index.php" class="btn btn-outline-dark">Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Clear the last order ID from session to prevent showing this page again
unset($_SESSION['last_order_id']);
require_once 'includes/footer.php';
ob_end_flush();
?>