<?php
session_start();
require_once 'dbconnect.php';

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    header("Location: admindashboard.php");
    exit();
}

$order_id = $_GET['order_id'];

// Fetch order details with customer information
$order_query = "SELECT o.*, 
                s.username as customer_name,
                s.email as customer_email,
                s.Signup_id,
                p.payment_id,
                p.payment_status,
                p.payment_method,
                p.created_at as payment_date
                FROM orders_table o
                LEFT JOIN tbl_signup s ON o.Signup_id = s.Signup_id
                LEFT JOIN payment_table p ON o.payment_id = p.payment_id
                WHERE o.order_id = ?";

$stmt = $conn->prepare($order_query);
$stmt->bind_param("s", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

if (!$order) {
    header("Location: admindashboard.php");
    exit();
}

// Fetch shipping address from shipping_addresses table
$address_query = "SELECT sa.*, s.username, s.Phoneno 
                 FROM shipping_addresses sa
                 JOIN tbl_signup s ON sa.Signup_id = s.Signup_id
                 WHERE sa.Signup_id = ? 
                 ORDER BY sa.created_at DESC LIMIT 1";
$stmt = $conn->prepare($address_query);
$stmt->bind_param("i", $order['Signup_id']);
$stmt->execute();
$address_result = $stmt->get_result();

$shipping_address = '';
if ($address_result->num_rows > 0) {
    $address = $address_result->fetch_assoc();
    // Format the address like in the image
    $shipping_address = htmlspecialchars($address['username']) . "\n" .
                       htmlspecialchars($address['address_line1']) . "\n" .
                       htmlspecialchars($address['city']) . " - " . 
                       htmlspecialchars($address['postal_code']) . "\n" .
                       "Phone: " . htmlspecialchars($address['Phoneno']);
    
    // Also update the order's shipping_address field for consistency
    $order['shipping_address'] = $shipping_address;
}

// Fetch order items
$items_query = "SELECT o.*, p.name as product_name, p.price
                FROM orders_table o
                JOIN tbl_product p ON o.product_id = p.product_id
                WHERE o.order_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("s", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

// Calculate totals
$subtotal = 0;
$items = array();
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
    $subtotal += $item['total_amount'];
}

$shipping = $subtotal >= 1000 ? 0 : 50;
$tax = $subtotal * 0.05; // 5% tax
$total = $subtotal + $shipping + $tax;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .back-button {
            text-decoration: none;
            color: #333;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .info-box h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }

        .status-processing { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-paid { background: #d4edda; color: #155724; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 4px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .totals {
            margin-left: auto;
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .total-row.final {
            font-weight: bold;
            border-bottom: none;
            padding-top: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        @media (max-width: 768px) {
            .order-grid {
                grid-template-columns: 1fr;
            }

            .totals {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="admindashboard.php?view=orders" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
            <h1>Order Details</h1>
        </div>

        <div class="order-grid">
            <div class="info-box">
                <h3>Order Information</h3>
                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_id']); ?></p>
                <p><strong>Order Date:</strong> <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></p>
                <p><strong>Status:</strong> 
                    <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                </p>
            </div>

            <div class="info-box">
                <h3>Customer Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
            </div>

            <div class="info-box">
                <h3>Payment Information</h3>
                <p><strong>Payment ID:</strong> <?php echo htmlspecialchars($order['payment_id']); ?></p>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                <p><strong>Payment Status:</strong> 
                    <span class="status-badge status-<?php echo strtolower($order['payment_status']); ?>">
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </p>
            </div>

            <div class="info-box">
                <h3>Shipping Address</h3>
                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
            </div>
        </div>

        <h2>Order Items</h2>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Quantity</th>
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
                        <td>₹<?php echo number_format($item['price'], 2); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>₹<?php echo number_format($item['total_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₹<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="total-row">
                <span>Shipping</span>
                <span><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></span>
            </div>
            <div class="total-row">
                <span>Tax (5%)</span>
                <span>₹<?php echo number_format($tax, 2); ?></span>
            </div>
            <div class="total-row final">
                <span>Total</span>
                <span>₹<?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <div class="action-buttons">
            <?php if ($order['order_status'] === 'processing'): ?>
                <form method="POST" action="update_order_status.php">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <button type="submit" name="status" value="completed" class="btn btn-success">
                        Mark as Completed
                    </button>
                </form>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-primary">Print Order</button>
        </div>
    </div>
</body>
</html> 