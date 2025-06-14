<?php
require_once 'includes/auth.php';
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Order Confirmation';
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="card-title">Thank You!</h4>
                <p>Your payment was successful. Your order has been placed.</p>
                <a href="index.php" class="btn btn-dark">Continue Shopping</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>