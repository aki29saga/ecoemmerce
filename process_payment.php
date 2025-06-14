<?php
ob_start();
session_start();
require_once 'includes/auth.php';
require_once 'config/db.php';

// Define payment gateway keys
define("FLW_SECRET_KEY", "FLWSECK_TEST-26f60667a4159d9e75f25eb2bbd29675-X"); // Replace with your secret key
define("PAYSTACK_SECRET_KEY", "sk_live_73275df8ad0ac0236eb46f1a937a4a82909f23a3"); // Replace with your Paystack secret key

if (!isLoggedIn() || empty($_SESSION['cart']) || !isset($_SESSION['tx_ref']) || !isset($_SESSION['checkout_total'])) {
    header('Location: login.php');
    exit;
}

// Get payment method from session
$payment_method = $_SESSION['payment_method'] ?? 'paystack'; // Default to Paystack
$amount = $_SESSION['checkout_total'];
$tx_ref = $_SESSION['tx_ref'];
$user_id = $_SESSION['user_id'];

// Process payment based on selected method
if ($payment_method === 'flutterwave') {
    // Process Flutterwave payment
    $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . "/crazydej1/payment_callback.php";
    
    // Generate Flutterwave payment link
    $payment_data = [
        'tx_ref' => $tx_ref,
        'amount' => $amount,
        'currency' => 'NGN',
        'payment_options' => 'card,account,ussd',
        'redirect_url' => $redirect_url,
        'customer' => [
            'email' => $_SESSION['email'],
            'name' => $_SESSION['username']
        ],
        'customizations' => [
            'title' => 'crazydej Store Payment',
            'description' => 'Payment for items in cart'
        ],
        'meta' => [
            'user_id' => $user_id,
            'cart_items' => json_encode($_SESSION['cart'])
        ]
    ];

    // Initialize Flutterwave payment
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.flutterwave.com/v3/payments");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . FLW_SECRET_KEY,
        "Content-Type: application/json",
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if (isset($response_data['status']) && $response_data['status'] === 'success') {
        // Redirect to Flutterwave payment page
        header('Location: ' . $response_data['data']['link']);
        exit;
    } else {
        // Handle Flutterwave initialization error
        $_SESSION['payment_error'] = "Failed to initialize Flutterwave payment: " . 
            ($response_data['message'] ?? 'Unknown error');
        header('Location: checkout.php');
        exit;
    }
} else {
    // Process Paystack payment (default)
    $callback_url = "https://" . $_SERVER['HTTP_HOST'] . "/payment_callback.php";
    
    // Initialize Paystack payment
    $payment_data = [
        'email' => $_SESSION['email'],
        'amount' => $amount * 100, // Convert to kobo
        'reference' => $tx_ref,
        'callback_url' => $callback_url,
        'metadata' => [
            'user_id' => $user_id,
            'cart_items' => $_SESSION['cart']
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/initialize");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json",
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if (isset($response_data['status']) && $response_data['status'] === true) {
        // Redirect to Paystack payment page
        header('Location: ' . $response_data['data']['authorization_url']);
        exit;
    } else {
        // Handle Paystack initialization error
        $_SESSION['payment_error'] = "Failed to initialize Paystack payment: " . 
            ($response_data['message'] ?? 'Unknown error');
        header('Location: checkout.php');
        exit;
    }
}

// If we get here, something went wrong
$_SESSION['payment_error'] = "Unable to process payment";
header('Location: checkout.php');
exit;?>