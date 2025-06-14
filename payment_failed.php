<?php
ob_start();
require_once 'includes/auth.php';
require_once 'config/db.php';

$page_title = 'Payment Failed';
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h4>Payment Failed</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5>We're sorry, your payment was not successful.</h5>
                    <p>Please try again or contact support if the problem persists.</p>
                </div>
                
                <div class="text-center">
                    <a href="checkout.php" class="btn btn-dark">Try Again</a>
                    <a href="cart.php" class="btn btn-outline-dark">Review Cart</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>