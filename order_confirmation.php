<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided in URL
if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // Fetch specific order payment details
    $payment_query = "SELECT * FROM payment_table 
                     WHERE order_id = ? AND Signup_id = ?";
    $stmt = $conn->prepare($payment_query);
    $stmt->bind_param("si", $order_id, $user_id);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payment = $payment_result->fetch_assoc();
} else {
    // Fallback to latest payment if no order_id provided
    $payment_query = "SELECT * FROM payment_table 
                     WHERE Signup_id = ? AND payment_status = 'paid'
                     ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($payment_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payment = $payment_result->fetch_assoc();
}

// If no payment found, redirect to orders page
if (!$payment) {
    header("Location: orders.php");
    exit();
}

// Get user details
$user_query = "SELECT * FROM tbl_signup WHERE Signup_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch order details with products - use the order_id from URL or from payment
$order_id_to_use = isset($_GET['order_id']) ? $_GET['order_id'] : $payment['order_id'];

$order_query = "SELECT o.*, p.name as product_name, p.price, o.image_path, o.gift_wrap_charge, o.gift_option, o.gift_wrap_type 
                FROM orders_table o
                JOIN tbl_product p ON o.product_id = p.product_id 
                WHERE o.order_id = ? AND o.Signup_id = ?
                ORDER BY o.created_at DESC";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("si", $order_id_to_use, $user_id);
$stmt->execute();
$order_items = $stmt->get_result();

// Initialize variables
$subtotal = 0;
$shipping = 0;
$tax = 0;
$gift_wrap_charge = 0;
$gift_wrap_type = '';
$shipping_address = '';
$items = array();

// Calculate order components
while ($item = $order_items->fetch_assoc()) {
    $items[] = $item;
    // Add item price to subtotal (excluding tax, shipping, and gift wrap)
    $item_price = $item['price'] * $item['quantity'];
    $subtotal += $item_price;
    
    // Get shipping_address from first item (all should have same)
    if (empty($shipping_address)) {
        $shipping_address = $item['shipping_address'];
    }
    
    // Get gift wrap details
    if (empty($gift_wrap_type) && !empty($item['gift_wrap_type'])) {
        $gift_wrap_type = $item['gift_wrap_type'];
        
        // Set gift wrap charge based on type
        switch($gift_wrap_type) {
            case 'premium':
                $gift_wrap_charge = 50;
                break;
            case 'themed':
                $gift_wrap_charge = 100;
                break;
            default:
                $gift_wrap_charge = 0;
        }
    }
}

// If no items found, redirect to orders page
if (empty($items)) {
    header("Location: orders.php");
    exit();
}

// Calculate shipping (free for orders over ₹1000)
$shipping = $subtotal >= 1000 ? 0 : 50;

// Calculate tax (5%)
$tax = $subtotal * 0.05;

// Calculate total
$total_amount = $subtotal + $shipping + $tax + $gift_wrap_charge;

// Ensure total matches payment amount
if (abs($total_amount - $payment['amount']) > 0.01) {
    error_log("Amount mismatch: Order total: {$total_amount}, Payment amount: {$payment['amount']}");
    $total_amount = $payment['amount']; // Use payment amount as source of truth
}

// Debug logging
error_log("Calculation - Subtotal: {$subtotal}, Shipping: {$shipping}, Tax: {$tax}, Gift: {$gift_wrap_charge}, Total: {$total_amount}, Payment: {$payment['amount']}");

// Fetch shipping address for this user
$address_query = "SELECT sa.*, s.username, s.Phoneno 
                 FROM shipping_addresses sa
                 JOIN tbl_signup s ON sa.Signup_id = s.Signup_id
                 WHERE sa.Signup_id = ? 
                 ORDER BY sa.created_at DESC LIMIT 1";
$stmt = $conn->prepare($address_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$address_result = $stmt->get_result();

if ($address_result->num_rows > 0) {
    $address = $address_result->fetch_assoc();
    // Format the address like in the image
    $shipping_address = htmlspecialchars($address['username']) . "\n" .
                       htmlspecialchars($address['address_line1']) . "\n" .
                       htmlspecialchars($address['city']) . " - " . 
                       htmlspecialchars($address['postal_code']) . "\n" .
                       "Phone: " . htmlspecialchars($address['Phoneno']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .success-icon {
            color: #28a745;
            font-size: 48px;
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .order-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
        }

        .status-paid {
            color: #28a745;
            font-weight: bold;
        }

        .order-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: #2874f0;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .product-image {
            width: 60px;
            height: 60px;
            overflow: hidden;
            border-radius: 4px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 768px) {
            .product-image {
                width: 50px;
                height: 50px;
            }
            
            th, td {
                padding: 8px;
                font-size: 0.9em;
            }
        }

        .text-right {
            text-align: right;
            font-weight: 500;
            color: #666;
        }
        
        .total-row {
            background-color: #f8f9fa;
            font-size: 1.1em;
        }
        
        .total-row td {
            padding: 15px 12px;
        }
        
        table tr:not(.total-row):not(:first-child):hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-check-circle success-icon"></i>
            <h1>Order Confirmed!</h1>
            <p>Thank you for shopping with Perfume Paradise</p>
        </div>

        <div class="order-info">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Order ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['order_id'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['payment_id'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Amount Paid</div>
                    <div class="info-value">₹<?php echo number_format($payment['amount'] ?? 0, 2); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment Status</div>
                    <div class="info-value">
                        <span class="status-paid"><?php echo htmlspecialchars($payment['payment_status'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($shipping_address)): ?>
        <div class="order-section">
            <h2 class="section-title">Shipping Address</h2>
            <p><?php echo nl2br(htmlspecialchars($shipping_address)); ?></p>
        </div>
        <?php endif; ?>

        <div class="order-section">
            <h2 class="section-title">Order Items</h2>
            <table>
                <thead>
                    <tr>
                        <th>Product Image</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="product-image">
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                                <?php if ($item['gift_option']): ?>
                                    <br><small>(Gift Wrapped - <?php echo ucfirst($item['gift_wrap_type']); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>₹<?php echo number_format($item['price'], 2); ?></td>
                            <td>₹<?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4" class="text-right">Subtotal</td>
                        <td>₹<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-right">Shipping</td>
                        <td><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-right">Tax (5%)</td>
                        <td>₹<?php echo number_format($tax, 2); ?></td>
                    </tr>
                    <?php if ($gift_wrap_charge > 0): ?>
                        <tr>
                            <td colspan="4" class="text-right">Gift Wrap (<?php echo htmlspecialchars($gift_wrap_type); ?>)</td>
                            <td>₹<?php echo number_format($gift_wrap_charge, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td colspan="4" class="text-right"><strong>Total Amount</strong></td>
                        <td><strong>₹<?php echo number_format($total_amount, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="action-buttons">
            <a href="print_receipt.php?order_id=<?php echo urlencode($payment['order_id']); ?>" class="btn btn-primary" target="_blank">
                <i class="fas fa-print"></i> Print Receipt
            </a>
            <a href="productslist.php" class="btn btn-secondary">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
        </div>
    </div>
</body>
</html>
