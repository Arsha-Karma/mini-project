<?php
session_start();
require_once 'dbconnect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function
function logError($message) {
    error_log(date('Y-m-d H:i:s') . ": " . $message . "\n", 3, "payment_errors.log");
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logError("User not logged in");
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Add this at the start of your payment processing
    if (isset($_POST['save_address']) && $_POST['save_address']) {
        // Insert shipping address
        $shipping_query = "INSERT INTO shipping_addresses 
                          (Signup_id, address_line1, city, state, postal_code, is_default, created_at) 
                          VALUES (?, ?, ?, ?, ?, 1, NOW())";
        
        // Create variables for binding
        $default_state = 'default_state';
        $is_default = 1;
        
        $stmt = $conn->prepare($shipping_query);
        $stmt->bind_param("issss", 
            $_SESSION['user_id'],
            $_POST['address'],
            $_POST['city'],
            $default_state,  // Now using a variable instead of literal string
            $_POST['postal_code']
        );
        
        try {
            $stmt->execute();
        } catch (Exception $e) {
            // Handle any errors, such as duplicate addresses
            error_log("Error saving shipping address: " . $e->getMessage());
        }
    }

    // Begin transaction
    $conn->begin_transaction();

    // Get payment details from the form
    $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
    $order_id = $_POST['order_id'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $is_buy_now = isset($_POST['is_buy_now']) && $_POST['is_buy_now'] === '1';
    $product_id = $_POST['product_id'] ?? null;
    
    // Get gift options
    $gift_option = isset($_POST['gift_option']) && $_POST['gift_option'] === '1';
    $gift_recipient = $_POST['gift_recipient'] ?? '';
    $gift_message = $_POST['gift_message'] ?? '';
    $gift_wrap_type = $_POST['gift_wrap'] ?? '';
    
    // Calculate gift wrap charge
    $gift_wrap_charge = 0;
    if ($gift_option) {
        switch ($gift_wrap_type) {
            case 'premium':
                $gift_wrap_charge = 50;
                break;
            case 'themed':
                $gift_wrap_charge = 100;
                break;
        }
    }

    // Validate required fields
    if (empty($razorpay_payment_id) || empty($order_id) || $amount <= 0) {
        throw new Exception("Missing required payment information");
    }

    // Create shipping address string
    $shipping_address = "$fullname\n$address\n$city - $postal_code\nPhone: $phone";

    // Insert into payment_table with gift wrap details
    $insert_payment = $conn->prepare("
        INSERT INTO payment_table (
            order_id, 
            Signup_id, 
            payment_id, 
            amount, 
            payment_method, 
            payment_status
        ) VALUES (?, ?, ?, ?, 'Razorpay', 'paid')
    ");

    $insert_payment->bind_param("sssd", 
        $order_id,
        $user_id,
        $razorpay_payment_id,
        $amount  // This now includes the gift wrap charge from frontend
    );

    if (!$insert_payment->execute()) {
        throw new Exception("Failed to insert payment record: " . $conn->error);
    }

    // Get the most recent shipping address for this user
    $address_query = "SELECT sa.*, s.username, s.Phoneno 
                     FROM shipping_addresses sa
                     JOIN tbl_signup s ON sa.Signup_id = s.Signup_id
                     WHERE sa.Signup_id = ? 
                     ORDER BY sa.created_at DESC LIMIT 1";
    $stmt = $conn->prepare($address_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $address_result = $stmt->get_result();

    $shipping_address = '';
    if ($address_result->num_rows > 0) {
        $address = $address_result->fetch_assoc();
        // Format the address as a single string
        $shipping_address = $address['username'] . "\n" .
                           $address['address_line1'] . "\n" .
                           $address['city'] . " - " . 
                           $address['postal_code'] . "\n" .
                           "Phone: " . $address['Phoneno'];
    }

    if ($is_buy_now && $product_id) {
        // Handle "Buy Now" purchase
        $product_query = "
            SELECT p.*, p.name, p.price, p.Stock_quantity, p.image_path
            FROM tbl_product p
            WHERE p.product_id = ? AND p.deleted = 0
        ";
        
        $stmt = $conn->prepare($product_query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new Exception("Product not found");
        }

        // Calculate total with gift wrap
        $item_total = $product['price'] + $gift_wrap_charge;

        // Insert into orders_table for "Buy Now" with gift details
        $insert_order = $conn->prepare("
            INSERT INTO orders_table (
                order_id,
                Signup_id,
                payment_id,
                total_amount,
                shipping_address,
                order_status,
                payment_status,
                product_id,
                image_path,
                quantity,
                gift_option,
                gift_message,
                gift_wrap_type,
                gift_recipient_name,
                gift_wrap_charge
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $order_status = 'processing';
        $payment_status = 'paid';
        $quantity = 1;

        $insert_order->bind_param("sssdsssississsd",
            $order_id,
            $user_id,
            $razorpay_payment_id,
            $item_total,
            $shipping_address,
            $order_status,
            $payment_status,
            $product_id,
            $product['image_path'],
            $quantity,
            $gift_option,
            $gift_message,
            $gift_wrap_type,
            $gift_recipient,
            $gift_wrap_charge
        );

        if (!$insert_order->execute()) {
            throw new Exception("Failed to insert order for product: " . $product['name']);
        }

        // Update product stock
        $update_stock = $conn->prepare("
            UPDATE tbl_product 
            SET Stock_quantity = Stock_quantity - 1 
            WHERE product_id = ? AND Stock_quantity >= 1
        ");
        
        $update_stock->bind_param("i", $product_id);

        if (!$update_stock->execute()) {
            throw new Exception("Failed to update stock for product: " . $product['name']);
        }
    } else {
        // Handle cart checkout
        $cart_query = "
            SELECT c.*, p.name, p.price, p.Stock_quantity, p.image_path
            FROM tbl_cart c
            JOIN tbl_product p ON c.product_id = p.product_id
            WHERE c.user_id = ? AND c.status = 'pending'
        ";
        
        $stmt = $conn->prepare($cart_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cart_items = $stmt->get_result();

        // Process each cart item
        while ($item = $cart_items->fetch_assoc()) {
            // Insert into orders_table
            $insert_order = $conn->prepare("
                INSERT INTO orders_table (
                    order_id,
                    Signup_id,
                    payment_id,
                    total_amount,
                    shipping_address,
                    order_status,
                    payment_status,
                    product_id,
                    image_path,
                    quantity
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $item_total = $item['quantity'] * $item['price'];
            $order_status = 'processing';
            $payment_status = 'paid';

            $insert_order->bind_param("sssdsssisd",
                $order_id,
                $user_id,
                $razorpay_payment_id,
                $item_total,
                $shipping_address,
                $order_status,
                $payment_status,
                $item['product_id'],
                $item['image_path'],
                $item['quantity']
            );

            if (!$insert_order->execute()) {
                throw new Exception("Failed to insert order for product: " . $item['name']);
            }

            // Update product stock
            $update_stock = $conn->prepare("
                UPDATE tbl_product 
                SET Stock_quantity = Stock_quantity - ? 
                WHERE product_id = ? AND Stock_quantity >= ?
            ");
            
            $update_stock->bind_param("iii", 
                $item['quantity'], 
                $item['product_id'], 
                $item['quantity']
            );

            if (!$update_stock->execute()) {
                throw new Exception("Failed to update stock for product: " . $item['name']);
            }

            // Update cart item status to completed
            $update_cart = $conn->prepare("
                UPDATE tbl_cart 
                SET status = 'completed' 
                WHERE cart_id = ? AND user_id = ?
            ");
            
            $update_cart->bind_param("ii", $item['cart_id'], $user_id);
            
            if (!$update_cart->execute()) {
                throw new Exception("Failed to update cart status for item: " . $item['name']);
            }
        }
    }

    // Commit transaction
    $conn->commit();
    
    // Store order ID in session for confirmation page
    $_SESSION['last_order_id'] = $order_id;
    
    // Clear cart count from session if it was a cart checkout
    if (!$is_buy_now) {
        $_SESSION['cart_count'] = 0;
    }
    
    // Redirect to success page
    header("Location: order_confirmation.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    logError("Error processing payment: " . $e->getMessage());
    logError("POST Data: " . print_r($_POST, true));
    
    // Redirect to error page
    header("Location: checkout.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>