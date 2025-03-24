<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch latest payment details
$payment_query = "SELECT * FROM payment_table 
                 WHERE Signup_id = ? AND payment_status = 'paid'
                 ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($payment_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_result = $stmt->get_result();
$payment = $payment_result->fetch_assoc();

// Get user details
$user_query = "SELECT * FROM tbl_signup WHERE Signup_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch order details with products
$order_query = "SELECT o.*, p.name as product_name, p.price, o.image_path 
                FROM orders_table o
                JOIN tbl_product p ON o.product_id = p.product_id 
                WHERE o.Signup_id = ? AND o.order_id = ?
                ORDER BY o.created_at DESC";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("is", $user_id, $payment['order_id']);
$stmt->execute();
$order_items = $stmt->get_result();

// Calculate totals
$subtotal = 0;
$items = array();
$shipping_address = '';
while ($item = $order_items->fetch_assoc()) {
    $items[] = $item;
    $subtotal += $item['total_amount'];
    if (empty($shipping_address)) {
        $shipping_address = $item['shipping_address'];
    }
}

// Calculate shipping and tax
$shipping = $subtotal >= 1000 ? 0 : 50;
$tax = $subtotal * 0.05; // 5% tax
$total_amount = $subtotal + $shipping + $tax;

// If no items found, redirect to home page
if (empty($items)) {
    header("Location: index.php");
    exit();
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
                    <div class="info-value"><?php echo htmlspecialchars($payment['order_id']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['payment_id']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Amount Paid</div>
                    <div class="info-value">₹<?php echo number_format($payment['amount'], 2); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment Status</div>
                    <div class="info-value">
                        <span class="status-paid">Paid</span>
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
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>₹<?php echo number_format($item['price'], 2); ?></td>
                            <td>₹<?php echo number_format($item['total_amount'], 2); ?></td>
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
