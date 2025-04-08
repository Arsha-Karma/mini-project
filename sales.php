<?php
session_start();
require_once 'dbconnect.php';

// Check if seller is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

// Get seller ID from the logged-in user
$user_id = $_SESSION['user_id'];

// First get the seller_id from tbl_seller
$seller_query = "SELECT seller_id FROM tbl_seller WHERE Signup_id = ?";
$stmt = $conn->prepare($seller_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$seller_result = $stmt->get_result();
$seller_data = $seller_result->fetch_assoc();
$seller_id = $seller_data['seller_id'];

// Fetch gift orders with shipping information
$gift_orders_query = "
    SELECT o.*, 
           p.name as product_name,
           p.image_path as product_image,
           s.username as customer_name,
           s.email as customer_email,
           s.Phoneno as customer_phone,
           s.Signup_id,
           COALESCE(pt.amount, o.total_amount) as payment_amount
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    LEFT JOIN payment_table pt ON o.order_id = pt.order_id
    WHERE p.seller_id = ? AND o.gift_option = 1
    ORDER BY o.created_at DESC";

// Fetch regular orders with shipping information
$regular_orders_query = "
    SELECT o.*, 
           p.name as product_name,
           p.image_path as product_image,
           s.username as customer_name,
           s.email as customer_email,
           s.Phoneno as customer_phone,
           s.Signup_id,
           COALESCE(pt.amount, o.total_amount) as payment_amount
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    LEFT JOIN payment_table pt ON o.order_id = pt.order_id
    WHERE p.seller_id = ? AND (o.gift_option = 0 OR o.gift_option IS NULL)
    ORDER BY o.created_at DESC";

$stmt = $conn->prepare($gift_orders_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$gift_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare($regular_orders_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$regular_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process order status update
if (isset($_POST['mark_completed']) && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    
    // Update order status
    $update_query = "UPDATE orders_table SET order_status = 'Completed' WHERE order_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("s", $order_id);
    
    if ($stmt->execute()) {
        // Calculate and record admin profit after order is completed
        calculateAndRecordAdminProfit($conn, $order_id);
        
        // Redirect to refresh the page
        header("Location: sales.php?success=order_completed");
        exit;
    } else {
        // Handle error
        $error_message = "Failed to update order status";
    }
}

// If you want to automatically mark orders as completed when they've been processing for more than X days
// Add this after your database connection

$days_to_auto_complete = 7; // Change this to your preferred number of days

$auto_complete_query = "
    UPDATE orders_table 
    SET order_status = 'Completed' 
    WHERE order_status = 'Processing' 
    AND payment_status = 'paid'
    AND DATEDIFF(NOW(), created_at) > ?
";

$stmt = $conn->prepare($auto_complete_query);
$stmt->bind_param("i", $days_to_auto_complete);
$stmt->execute();

// Calculate profits for newly completed orders
$get_new_completed = "
    SELECT order_id FROM orders_table 
    WHERE order_status = 'Completed' 
    AND order_id NOT IN (SELECT order_id FROM admin_profits WHERE order_id IS NOT NULL)
";

$result = $conn->query($get_new_completed);
while ($row = $result->fetch_assoc()) {
    calculateAndRecordAdminProfit($conn, $row['order_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Seller Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Copy all styles from seller-dashboard.php */
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #000000;
            padding-top: 20px;
        }

        .sidebar h2 {
            color: white;
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .sidebar a {
            padding: 12px 25px;
            text-decoration: none;
            font-size: 15px;
            color: white;
            display: block;
            transition: 0.3s;
        }

        .sidebar a:hover {
            background-color: #1a1a1a;
        }

        .sidebar a.active {
            background-color: #1a1a1a;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-body {
            padding: 20px;
        }

        .card-title {
            margin: 0 0 20px 0;
            color: #333;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .badge {
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 12px;
        }

        .bg-success {
            background-color: #28a745 !important;
            color: white;
        }

        .bg-warning {
            background-color: #ffc107 !important;
            color: black;
        }

        .table td {
            vertical-align: middle;
            font-size: 14px;
        }

        .table td:nth-child(4) {
            max-width: 200px;
            white-space: pre-line;
            line-height: 1.2;
        }

        /* Update table header styles */
        .table thead th {
            background-color: #000000;
            color: white;
            padding: 15px;
            font-weight: 500;
            border: none;
        }

        /* Add rounded corners to the first and last header cells */
        .table thead th:first-child {
            border-top-left-radius: 8px;
        }

        .table thead th:last-child {
            border-top-right-radius: 8px;
        }

        .bg-danger {
            background-color: #dc3545 !important;
            color: white;
        }

        /* Add new styles */
        .orders-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            border-bottom: 2px solid #eee;
        }

        .section-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .gift-orders {
            border-top: 4px solid #17a2b8;
        }

        .regular-orders {
            border-top: 4px solid #28a745;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
        }

        .table td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        /* Set column widths */
        .table th:nth-child(1) { width: 12%; }  /* Order ID */
        .table th:nth-child(2) { width: 16%; } /* Product */
        .table th:nth-child(3) { width: 12%; } /* Customer */
        .table th:nth-child(4) { width: 18%; } /* Shipping */
        .table th:nth-child(5) { width: 7%; }  /* Quantity */
        .table th:nth-child(6) { width: 10%; } /* Amount */
        .table th:nth-child(7) { width: 15%; } /* Gift Info/Date */
        .table th:nth-child(8) { width: 10%; } /* Status */

        .gift-wrap-info {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #17a2b8;
        }

        .order-count {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
            color: #666;
            margin-left: 10px;
        }

        /* Update table styles for better alignment */
        .table {
            width: 100%;
            table-layout: fixed;
            font-size: 14px;
        }

        .table th, .table td {
            padding: 12px 8px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Update shipping address styles */
        .shipping-address {
            line-height: 1.4;
            padding: 8px 0;
            white-space: pre-line;
            max-height: none; /* Remove height restriction */
            overflow: visible; /* Remove scroll */
        }

        /* Update order ID styles */
        .order-id {
            font-family: monospace;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            letter-spacing: 0.5px;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .product-info img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }

        .gift-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .gift-wrap-info {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #17a2b8;
            font-size: 13px;
        }

        .gift-message {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 4px;
        }

        .recipient-info {
            font-size: 12px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="sidebar"><br>
        <h2 style="color: white; text-align: center;">Perfume Paradise</h2>
        <a href="seller-dashboard.php">Dashboard</a>
        <a href="index.php">Home</a>
        <a href="profile.php">Edit Profile</a>
        <a href="products.php">Products</a>
        <a href="sales.php" class="active">Orders</a>
        <a href="customer_reviews.php">Customer Reviews</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1 style="color: #000000;">Orders</h1>
        </div>

        <!-- Gift Orders Section -->
        <div class="orders-section gift-orders">
            <div class="section-header">
                <h2>
                    <i class="fas fa-gift"></i>
                    Gift Orders
                    <span class="order-count"><?php echo count($gift_orders); ?> orders</span>
                </h2>
            </div>
            <div class="card-body">
                <?php if (empty($gift_orders)): ?>
                    <div class="text-center p-4">
                        <i class="fas fa-gift fa-3x mb-3" style="color: #17a2b8;"></i>
                        <p>No gift orders found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Product</th>
                                    <th>Customer</th>
                                    <th>Shipping</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                    <th>Gift Details</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gift_orders as $order): ?>
                                    <tr>
                                        <td class="order-id"><?php echo htmlspecialchars($order['order_id']); ?></td>
                                        <td>
                                            <div class="product-info">
                                                <img src="<?php echo htmlspecialchars($order['product_image']); ?>" alt="Product">
                                                <span><?php echo htmlspecialchars($order['product_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td>
                                            <div class="shipping-address">
                                                <?php 
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
                                                
                                                $formatted_address = '';
                                                
                                                if ($address_result->num_rows > 0) {
                                                    // Use the address from shipping_addresses table
                                                    $address = $address_result->fetch_assoc();
                                                    $formatted_address = $order['customer_name'] . "\n" .
                                                                        $address['address_line1'] . "\n" .
                                                                        $address['city'] . (!empty($address['postal_code']) ? " - " . $address['postal_code'] : "") . "\n" .
                                                                        "Phone: " . (!empty($order['customer_phone']) ? $order['customer_phone'] : $address['Phoneno']);
                                                } elseif (!empty($order['shipping_address'])) {
                                                    // Fallback to shipping_address from orders_table if available
                                                    $formatted_address = $order['shipping_address'];
                                                    
                                                    // Add customer name if not already present
                                                    if (strpos($formatted_address, $order['customer_name']) === false) {
                                                        $formatted_address = $order['customer_name'] . "\n" . $formatted_address;
                                                    }
                                                    
                                                    // Add phone if not already present
                                                    if (!empty($order['customer_phone']) && strpos($formatted_address, 'Phone:') === false) {
                                                        $formatted_address .= "\nPhone: " . $order['customer_phone'];
                                                    }
                                                } else {
                                                    // Last resort - just show the customer name and phone
                                                    $formatted_address = $order['customer_name'] . "\n" .
                                                                        (!empty($order['customer_phone']) ? "Phone: " . $order['customer_phone'] : "");
                                                }
                                                
                                                echo nl2br(htmlspecialchars($formatted_address));
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo $order['quantity']; ?></td>
                                        <td>₹<?php echo number_format($order['payment_amount'], 2); ?></td>
                                        <td>
                                            <div class="gift-details">
                                                <div class="gift-wrap-info">
                                                    <i class="fas fa-gift"></i>
                                                    <?php echo htmlspecialchars($order['gift_wrap_type']); ?>
                                                </div>
                                                <?php if (!empty($order['gift_recipient_name'])): ?>
                                                    <div class="recipient-info">
                                                        <i class="fas fa-user"></i> To: <?php echo htmlspecialchars($order['gift_recipient_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($order['gift_message'])): ?>
                                                    <div class="gift-message">
                                                        "<?php echo htmlspecialchars($order['gift_message']); ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($order['order_status'] === 'Cancelled'): ?>
                                                <span class="badge bg-danger">Cancelled</span>
                                            <?php elseif ($order['order_status'] === 'Completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($order['order_status'] === 'Processing'): ?>
                                                <span class="badge bg-warning">Processing</span>
                                                <form method="post" style="margin-top: 5px;">
                                                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                                                    <button type="submit" name="mark_completed" class="btn btn-sm btn-success" style="font-size: 11px; padding: 2px 5px;">
                                                        Mark as Completed
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-info">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Regular Orders Section -->
        <div class="orders-section regular-orders">
            <div class="section-header">
                <h2>
                    <i class="fas fa-shopping-cart"></i>
                    Regular Orders
                    <span class="order-count"><?php echo count($regular_orders); ?> orders</span>
                </h2>
            </div>
            <div class="card-body">
                <?php if (empty($regular_orders)): ?>
                    <div class="text-center p-4">
                        <i class="fas fa-shopping-cart fa-3x mb-3" style="color: #28a745;"></i>
                        <p>No regular orders found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Product</th>
                                    <th>Customer</th>
                                    <th>Shipping</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($regular_orders as $order): ?>
                                    <tr>
                                        <td class="order-id">
                                            <?php echo htmlspecialchars($order['order_id']); ?>
                                        </td>
                                        <td>
                                            <div class="product-info">
                                                <img src="<?php echo htmlspecialchars($order['product_image']); ?>" alt="Product">
                                                <span><?php echo htmlspecialchars($order['product_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td>
                                            <div class="shipping-address">
                                                <?php 
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
                                                
                                                $formatted_address = '';
                                                
                                                if ($address_result->num_rows > 0) {
                                                    // Use the address from shipping_addresses table
                                                    $address = $address_result->fetch_assoc();
                                                    $formatted_address = $order['customer_name'] . "\n" .
                                                                        $address['address_line1'] . "\n" .
                                                                        $address['city'] . (!empty($address['postal_code']) ? " - " . $address['postal_code'] : "") . "\n" .
                                                                        "Phone: " . (!empty($order['customer_phone']) ? $order['customer_phone'] : $address['Phoneno']);
                                                } elseif (!empty($order['shipping_address'])) {
                                                    // Fallback to shipping_address from orders_table if available
                                                    $formatted_address = $order['shipping_address'];
                                                    
                                                    // Add customer name if not already present
                                                    if (strpos($formatted_address, $order['customer_name']) === false) {
                                                        $formatted_address = $order['customer_name'] . "\n" . $formatted_address;
                                                    }
                                                    
                                                    // Add phone if not already present
                                                    if (!empty($order['customer_phone']) && strpos($formatted_address, 'Phone:') === false) {
                                                        $formatted_address .= "\nPhone: " . $order['customer_phone'];
                                                    }
                                                } else {
                                                    // Last resort - just show the customer name and phone
                                                    $formatted_address = $order['customer_name'] . "\n" .
                                                                        (!empty($order['customer_phone']) ? "Phone: " . $order['customer_phone'] : "");
                                                }
                                                
                                                echo nl2br(htmlspecialchars($formatted_address));
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $order['quantity']; ?>
                                        </td>
                                        <td>
                                            ₹<?php echo number_format($order['payment_amount'], 2); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($order['order_status'] === 'Cancelled'): ?>
                                                <span class="badge bg-danger">Cancelled</span>
                                            <?php elseif ($order['order_status'] === 'Completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($order['order_status'] === 'Processing'): ?>
                                                <span class="badge bg-warning">Processing</span>
                                                <form method="post" style="margin-top: 5px;">
                                                    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                                                    <button type="submit" name="mark_completed" class="btn btn-sm btn-success" style="font-size: 11px; padding: 2px 5px;">
                                                        Mark as Completed
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-info">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>