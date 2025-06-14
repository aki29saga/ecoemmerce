<?php
ob_start();

require_once 'config/db.php'; // Make sure this file properly initializes $conn
require_once 'includes/auth.php';

// Define payment gateway keys
define("FLW_SECRET_KEY", "FLWSECK_TEST-26f60667a4159d9e75f25eb2bbd29675-X");
define("PAYSTACK_SECRET_KEY", "sk_live_73275df8ad0ac0236eb46f1a937a4a82909f23a3");

// Modify the function to accept $conn as a parameter
function processSuccessfulPayment($conn, $user_id, $reference, $amount, $cart_items) {
    try {
        // 1. Create order record
        $sql_order = "INSERT INTO orders (user_id, order_number, total_amount, payment_method, payment_reference, status) 
                     VALUES (?, ?, ?, ?, ?, 'completed')";
        $stmt = mysqli_prepare($conn, $sql_order);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        
        $payment_method = strpos($reference, 'FLW') !== false ? 'flutterwave' : 'paystack';
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $reference, $amount, $payment_method, $reference);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed: " . mysqli_error($conn));
        }
        
        $order_id = mysqli_insert_id($conn);
        
        // 2. Create order items
        if (!empty($cart_items)) {
            $product_ids = array_keys($cart_items);
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $types = str_repeat('i', count($product_ids));
            
            // Get product details
            $sql_products = "SELECT id, name, price FROM products WHERE id IN ($placeholders)";
            $stmt_products = mysqli_prepare($conn, $sql_products);
            
            if (!$stmt_products) {
                throw new Exception("Products prepare failed: " . mysqli_error($conn));
            }
            
            // Bind parameters dynamically
            $bind_params = array_merge([$types], $product_ids);
            $bind_params_references = [];
            foreach ($bind_params as $key => $value) {
                $bind_params_references[$key] = &$bind_params[$key];
            }
            
            call_user_func_array([$stmt_products, 'bind_param'], $bind_params_references);
            
            if (!mysqli_stmt_execute($stmt_products)) {
                throw new Exception("Products execute failed: " . mysqli_error($conn));
            }
            
            $result = mysqli_stmt_get_result($stmt_products);
            $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
            
            // Insert order items
            $sql_items = "INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price)
                         VALUES (?, ?, ?, ?, ?)";
            $stmt_items = mysqli_prepare($conn, $sql_items);
            
            if (!$stmt_items) {
                throw new Exception("Items prepare failed: " . mysqli_error($conn));
            }
            
            foreach ($products as $product) {
                $quantity = $cart_items[$product['id']];
                mysqli_stmt_bind_param($stmt_items, "iisid", $order_id, $product['id'], $product['name'], $quantity, $product['price']);
                
                if (!mysqli_stmt_execute($stmt_items)) {
                    throw new Exception("Items execute failed: " . mysqli_error($conn));
                }
                
                // Update product stock
                $sql_stock = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stmt_stock = mysqli_prepare($conn, $sql_stock);
                
                if (!$stmt_stock) {
                    throw new Exception("Stock prepare failed: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt_stock, "ii", $quantity, $product['id']);
                
                if (!mysqli_stmt_execute($stmt_stock)) {
                    throw new Exception("Stock update failed: " . mysqli_error($conn));
                }
            }
        }
        
        // 3. Clear the cart
        if (isset($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }
        
        // Store order ID in session for success page
        $_SESSION['last_order_id'] = $order_id;
        
        return true;
        
    } catch (Exception $e) {
        error_log("Order processing failed: " . $e->getMessage());
        return false;
    }
}

// Make sure $conn is properly initialized from db.php
if (!isset($conn)) {
    die("Database connection failed");
}

// Handle Flutterwave callback
if (isset($_GET['transaction_id']) || isset($_GET['tx_ref'])) {
    $transaction_id = $_GET['transaction_id'] ?? '';
    $tx_ref = $_GET['tx_ref'] ?? '';
    
    // Verify payment via Flutterwave API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.flutterwave.com/v3/transactions/$transaction_id/verify");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . FLW_SECRET_KEY,
        "Content-Type: application/json",
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if (isset($response_data['status']) && $response_data['status'] === 'success' && 
        $response_data['data']['status'] === 'successful') {
        
        $amount = floatval($response_data['data']['amount']);
        $user_id = intval($response_data['data']['meta']['user_id'] ?? $_SESSION['user_id'] ?? 0);
        $cart_items = json_decode($response_data['data']['meta']['cart_items'] ?? '[]', true);
        $reference = $response_data['data']['tx_ref'];

        if (processSuccessfulPayment($conn, $user_id, $reference, $amount, $cart_items)) {
            header('Location: payment_success.php?reference=' . $reference);
            exit;
        }
    }
}
// Handle Paystack callback
elseif (isset($_GET['reference'])) {
    $reference = $_GET[''];
    
    // Verify payment via Paystack API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json",
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if (isset($response_data['status']) && $response_data['status'] === true && 
        $response_data['data']['status'] === 'success') {
        
        $amount = floatval($response_data['data']['amount']) / 100; // Convert from kobo
        $user_id = intval($response_data['data']['metadata']['user_id'] ?? $_SESSION['user_id'] ?? 0);
        $cart_items = $response_data['data']['metadata']['cart_items'] ?? $_SESSION['cart'] ?? [];
        $reference = $response_data['data']['reference'];

        if (processSuccessfulPayment($conn, $user_id, $reference, $amount, $cart_items)) {
            header('Location: payment_success.php?reference=' . $reference);
            exit;
        }
    }
}

// If we get here, payment verification failed
header('Location: payment_failed.php');
exit;?>