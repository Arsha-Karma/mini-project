<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $payment_id = $_POST['payment_id'];
    $amount = $_POST['amount'];
    $reason = $_POST['cancellation_reason'];
    $other_reason = isset($_POST['other_reason']) ? $_POST['other_reason'] : '';
    
    if ($reason === 'Other' && !empty($other_reason)) {
        $reason = $other_reason;
    }
    
    // Razorpay API credentials
    $key_id = 'rzp_test_LFEA5QeDc3uh7A';
    $key_secret = 'UcSEiMzamwDuhxLKkpPz1VUj';
    
    try {
        $conn->begin_transaction();
        
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
        // Get product and quantity from the order
        $order_query = "SELECT product_id, quantity FROM orders_table WHERE order_id = ?";
        $stmt = $conn->prepare($order_query);
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order_data = $result->fetch_assoc();
        
        if (!$order_data) {
            throw new Exception("Order not found");
        }
        
        $product_id = $order_data['product_id'];
        $quantity_to_restock = $order_data['quantity'];
        
        // Restock the product
        $update_product = $conn->prepare("UPDATE tbl_product SET Stock_quantity = Stock_quantity + ? WHERE product_id = ?");
        $update_product->bind_param("ii", $quantity_to_restock, $product_id);
        $update_product->execute();
        
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
        // First, check the payment status and details
        $check_url = 'https://api.razorpay.com/v1/payments/' . $payment_id;
        
        $ch = curl_init($check_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_status !== 200) {
            throw new Exception("Payment verification failed: " . $response);
        }
        
        $payment_data = json_decode($response, true);
        error_log("Payment status: " . $payment_data['status']); // Log payment status for debugging
        
        // Attempt to process refund regardless of current status
        $refund_id = null;
        $refund_status = 'failed';
        $refund_message = '';
        
        // Create refund request to Razorpay
        $url = 'https://api.razorpay.com/v1/payments/' . $payment_id . '/refund';
        $amount_in_paise = $amount * 100; // Convert to paise
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['amount' => $amount_in_paise, 'speed' => 'optimum']));
        curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $response_data = json_decode($response, true);
        
        if ($http_status === 200) {
            $refund_id = $response_data['id'];
            $refund_status = 'processed';
            $refund_message = "Refund has been initiated with ID: " . $refund_id;
        } else {
            // If direct refund fails, try to capture payment first if it's authorized
            if ($payment_data['status'] === 'authorized') {
                // Try to capture the payment first
                $capture_url = 'https://api.razorpay.com/v1/payments/' . $payment_id . '/capture';
                $ch = curl_init($capture_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['amount' => $amount_in_paise]));
                curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                
                $capture_response = curl_exec($ch);
                $capture_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($capture_status === 200) {
                    // Now try to refund again
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['amount' => $amount_in_paise]));
                    curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    
                    $retry_response = curl_exec($ch);
                    $retry_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($retry_status === 200) {
                        $refund_data = json_decode($retry_response, true);
                        $refund_id = $refund_data['id'];
                        $refund_status = 'processed';
                        $refund_message = "Payment captured and refunded with ID: " . $refund_id;
                    } else {
                        $refund_message = "Payment captured but refund failed: " . $retry_response;
                    }
                } else {
                    $refund_message = "Could not capture payment: " . $capture_response;
                }
            } else {
                $refund_message = "Refund failed. Payment status: " . $payment_data['status'] . ", Error: " . $response;
            }
        }
        
<<<<<<< HEAD
        // Update order status regardless of refund status
=======
<<<<<<< HEAD
        // Update order status regardless of refund status
=======
<<<<<<< HEAD
        // Update order status regardless of refund status
=======
        // Update order status to Cancelled
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
        $update_order = $conn->prepare("UPDATE orders_table SET order_status = 'Cancelled', 
                                      cancellation_reason = ?, cancelled_at = NOW() 
                                      WHERE order_id = ?");
        $update_order->bind_param("ss", $reason, $order_id);
        $update_order->execute();
        
<<<<<<< HEAD
        // Update payment status (removed refund_notes)
=======
<<<<<<< HEAD
        // Update payment status (removed refund_notes)
=======
<<<<<<< HEAD
        // Update payment status (removed refund_notes)
=======
        // Update payment table
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
        $update_payment = $conn->prepare("UPDATE payment_table SET 
                                        refund_status = ?,
                                        refund_id = ?,
                                        refund_amount = ?,
                                        refund_date = NOW() 
                                        WHERE payment_id = ?");
        $update_payment->bind_param("ssds", $refund_status, $refund_id, $amount, $payment_id);
        $update_payment->execute();
        
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
        // Commit transaction
        $conn->commit();
        
        // Provide detailed message to user
        if ($refund_status === 'processed') {
            $_SESSION['success'] = "Order cancelled successfully and refund has been initiated.";
        } else {
            // Order cancelled but refund processing will be handled manually
            $_SESSION['success'] = "Order cancelled successfully. The refund will be processed manually.";
            
            // Log for admin reference
            error_log("Manual refund needed for Order #$order_id (Payment ID: $payment_id). Amount: $amount");
        }
        
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
=======
        $conn->commit();
        
        $_SESSION['success'] = "Order cancelled successfully.The refund will be processed manually .";
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
        header('Location: orders.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Failed to cancel order: " . $e->getMessage();
        error_log("Order cancellation error: " . $e->getMessage());
        header('Location: orders.php');
        exit();
    }
} else {
    header('Location: orders.php');
    exit();
}
?> 