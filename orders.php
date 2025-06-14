<?php
ob_start();


// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's name
try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $user ? htmlspecialchars($user['name']) : 'Customer';
} catch (PDOException $e) {
    error_log("Error fetching user name: " . $e->getMessage());
    $customer_name = 'Customer';
}

// Fetch user's orders
try {
    $stmt = $pdo->prepare("SELECT id, order_number, total_amount, payment_method, payment_reference, status, created_at 
                           FROM orders 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
    $error = "Unable to fetch orders. Please try again later.";
}

// Fetch order items for a specific order (for receipt)
function getOrderItems($pdo, $order_id) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, product_name, quantity, unit_price 
                               FROM order_items 
                               WHERE order_id = ?");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching order items: " . $e->getMessage());
        return [];
    }
}
?>

    <style>
        .receipt { display: none; }
        @media print {
            body * { visibility: hidden; }
            .receipt, .receipt * { visibility: visible; }
            .receipt { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/header.php'; ?>
    <div class="container mt-5">
        <h2 class="d-flex align-items-center">
            <i class="bi bi-cart4 me-2"></i> Your Previous Orders, <?php echo $customer_name; ?>
        </h2>
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle me-2"></i> 
            Please note: Delivery is scheduled for 3 days after order confirmation.
        </div>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (empty($orders)): ?>
            <p>No orders found.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></td>
                            <td>₦<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $order['status'] === 'completed' ? 'success' : 
                                         ($order['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($order['status'] === 'completed'): ?>
                                    <button class="btn btn-sm btn-primary print-receipt" 
                                            data-order-id="<?php echo $order['id']; ?>">
                                        Print Receipt
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Receipt Template -->
        <?php foreach ($orders as $order): ?>
            <div class="receipt" id="receipt-<?php echo $order['id']; ?>">
                <h3>Order Receipt</h3>
                <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                <p><strong>Date:</strong> <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></ ??
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); ?></p>
                <hr>
                <h4>Items</h4>
                <table class="table">
                    <thead>
                        <trственным
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $items = getOrderItems($pdo, $order['id']); ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>₦<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>₦<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><strong>Total Amount:</strong> ₦<?php echo number_format($order['total_amount'], 2); ?></p>
                <p>Thank you for your purchase!</p>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.querySelectorAll('.print-receipt').forEach(button => {
            button.addEventListener('click', () => {
                const orderId = button.getAttribute('data-order-id');
                const receipt = document.getElementById(`receipt-${orderId}`);
                if (receipt) {
                    window.print();
                }
            });
        });
    </script>
   
</body>
</html>
<?php require_once 'includes/footer.php'; ?>