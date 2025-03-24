<?php
// Start the session
session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Include database connection
require_once 'dbconnect.php';

// Add necessary columns if they don't exist
$alter_queries = [
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS quantity INT NOT NULL DEFAULT 1",
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS product_id INT(11) NOT NULL",
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS user_id INT(11) NOT NULL",
    "ALTER TABLE orders_table ADD FOREIGN KEY IF NOT EXISTS (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE",
    "ALTER TABLE orders_table ADD FOREIGN KEY IF NOT EXISTS (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE",
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS is_notified TINYINT DEFAULT 0",
    "ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS is_stock_notified TINYINT DEFAULT 0"
];

foreach ($alter_queries as $query) {
    try {
        $conn->query($query);
    } catch (Exception $e) {
        // Continue if error occurs (e.g., if column or key already exists)
        continue;
    }
}

// Check if seller is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

// Check for session timeout (e.g., 30 minutes)
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Log out the user
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Get seller information including verification status
$seller_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT s.*, v.*, s.verified_status
    FROM tbl_seller s
    LEFT JOIN seller_verification_docs v ON s.seller_id = v.seller_id
    WHERE s.Signup_id = ?
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_info = $stmt->get_result()->fetch_assoc();

// Get verification status
$is_verified = ($seller_info['verified_status'] === 'verified');
$verification_status = $seller_info['verified_status'];

// Get seller's products
$stmt = $conn->prepare("
    SELECT * FROM tbl_product 
    WHERE seller_id = (
        SELECT seller_id FROM tbl_seller WHERE Signup_id = ?
    ) AND deleted = 0
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get seller's orders
$sales_query = "
    SELECT 
        o.order_id,
        s.username as customer_name,
        p.name as product_name,
        p.image_path as product_image,
        o.quantity,
        o.total_amount,
        o.created_at as order_date,
        o.payment_status,
        o.order_status
    FROM orders_table o
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    JOIN tbl_product p ON o.product_id = p.product_id
    WHERE p.seller_id = (
        SELECT seller_id FROM tbl_seller WHERE Signup_id = ?
    )
    ORDER BY o.created_at DESC
    LIMIT 10";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug information
error_log("User ID: " . $_SESSION['user_id']);
error_log("Products found: " . count($products));
error_log("Sales found: " . count($sales));

if (empty($products)) {
    error_log("No products found for seller_id: " . $seller_info['seller_id']);
}

if (empty($sales)) {
    error_log("No sales found for seller_id: " . $seller_info['seller_id']);
}

// Update order status to completed if payment is successful
$update_status = $conn->prepare("
    UPDATE orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    SET o.order_status = 'completed'
    WHERE p.seller_id = ? AND o.payment_status = 'paid' AND o.order_status != 'completed'
");
$update_status->bind_param("i", $seller_info['seller_id']);
$update_status->execute();

// Get recent reviews
$stmt = $conn->prepare("
    SELECT r.*, p.name as product_name, u.username 
    FROM tbl_reviews r 
    JOIN tbl_product p ON r.product_id = p.product_id 
    JOIN tbl_users u ON r.user_id = u.user_id 
    WHERE p.seller_id = ?
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check verification status
$stmt = $conn->prepare("
    SELECT verified_status, documents_uploaded 
    FROM tbl_seller 
    WHERE Signup_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// Check for verification success message
$show_success = isset($_SESSION['verification_success']) && $_SESSION['verification_success'];
if ($show_success) {
    unset($_SESSION['verification_success']); // Clear the flag after use
}

// Show verification form for unverified sellers
$show_verification_popup = ($result['documents_uploaded'] !== 'completed');

// Fetch notifications for new orders
$new_orders_query = "
    SELECT COUNT(*) as new_orders 
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    WHERE p.seller_id = ? AND o.is_notified = 0";
$stmt = $conn->prepare($new_orders_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$new_orders_result = $stmt->get_result();
$new_orders_count = $new_orders_result->fetch_assoc()['new_orders'];

// Fetch out of stock products
$out_of_stock_query = "
    SELECT COUNT(*) as out_of_stock 
    FROM tbl_product 
    WHERE seller_id = ? AND Stock_quantity <= 5 AND is_stock_notified = 0";
$stmt = $conn->prepare($out_of_stock_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$out_of_stock_result = $stmt->get_result();
$out_of_stock_count = $out_of_stock_result->fetch_assoc()['out_of_stock'];

$total_notifications = $new_orders_count + $out_of_stock_count;

// Fetch recent orders details with more specific information
$recent_orders_query = "
    SELECT 
        o.order_id,
        o.created_at,
        o.quantity,
        o.total_amount,
        o.payment_status,
        p.name as product_name,
        p.Stock_quantity,
        s.username as customer_name,
        s.email as customer_email
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    WHERE p.seller_id = ? 
    AND o.created_at >= NOW() - INTERVAL 24 HOUR
    ORDER BY o.created_at DESC";

$stmt = $conn->prepare($recent_orders_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch low stock products
$low_stock_query = "
    SELECT 
        product_id,
        name,
        Stock_quantity,
        price
    FROM tbl_product
    WHERE seller_id = ? 
    AND Stock_quantity <= 5
    ORDER BY Stock_quantity ASC";

$stmt = $conn->prepare($low_stock_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$low_stock_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Perfume Paradise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f4f6f9;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .verification-banner {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .verification-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .verification-verified {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .verification-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .info-item label {
            display: block;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .info-item span {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-info img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-edit {
            background: #ffc107;
            color: #000;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .status-active {
            padding: 4px 8px;
            background: #28a745;
            color: white;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-pending {
            padding: 4px 8px;
            background: #ffc107;
            color: #000;
            border-radius: 4px;
            font-size: 12px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #999;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .low-stock {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 5px;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .table {
                display: block;
                overflow-x: auto;
            }

            .section-header {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Add sidebar styles */
        .sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1a1a1a;
            padding-top: 20px;
            color: white;
        }

        .sidebar h2 {
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            border-bottom: 1px solid #333;
        }

        .sidebar a {
            padding: 15px 25px;
            text-decoration: none;
            font-size: 16px;
            color: #fff;
            display: block;
            transition: 0.3s;
        }

        .sidebar a:hover {
            background-color: #333;
            color: #fff;
        }

        .sidebar a.active {
            background-color: #333;
            border-left: 4px solid #fff;
        }

        .sidebar i {
            margin-right: 10px;
        }

        /* Adjust main content to accommodate sidebar */
        .container {
            margin-left: 250px; /* Same as sidebar width */
            max-width: calc(100% - 250px);
            padding: 20px;
        }

        /* Responsive design */
        @media screen and (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 20px;
            }
            
            .container {
                margin-left: 0;
                max-width: 100%;
            }
            
            .sidebar a {
                float: left;
                padding: 15px;
            }
            
            .sidebar h2 {
                display: none;
            }
        }

        @media screen and (max-width: 480px) {
            .sidebar a {
                text-align: center;
                float: none;
            }
        }

        /* Add these styles for the status badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.processing {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-badge.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-info img {
            border-radius: 4px;
        }

        .table td {
            vertical-align: middle;
        }

        .welcome-section {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-section h1 {
            color: #333;
            font-size: 28px;
            margin: 0;
            font-weight: 600;
        }

        .notification-area {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .notification-icon {
            background: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            min-width: 18px;
            text-align: center;
            display: none; /* Hidden by default */
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }

        .notification-section {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .notification-section h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }

        .notification-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification-item {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
        }

        .notification-item.order {
            border-left: 4px solid #007bff;
        }

        .notification-item.stock {
            border-left: 4px solid #dc3545;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .notification-details {
            font-size: 12px;
            color: #666;
        }

        .notification-details p {
            margin: 3px 0;
        }

        .notification-details small {
            color: #888;
            font-style: italic;
        }

        .notification-item.stock {
            border-left: 4px solid #dc3545;
        }

        .out-of-stock {
            color: #dc3545;
            font-weight: bold;
            margin-top: 5px;
        }

        .low-stock-warning {
            color: #ffc107;
            font-weight: bold;
            margin-top: 5px;
        }

        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast-notification {
            background: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .toast-notification i {
            font-size: 18px;
        }

        .order-toast {
            border-left: 4px solid #007bff;
        }

        .order-toast i {
            color: #007bff;
        }

        .stock-toast {
            border-left: 4px solid #dc3545;
        }

        .stock-toast i {
            color: #dc3545;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h3>&nbsp;Perfume Paradise</h3>
        <a href="seller-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'seller-dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Home
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-edit"></i> Edit Profile
        </a>
        <a href="products.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="sales.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Orders
        </a>
        <a href="customer_reviews.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'customer-reviews.php' ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> Customer Reviews
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Message -->
        <div class="welcome-section">
            <h1>Welcome <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        </div>

        <!-- Add this right after the welcome section and before other content -->
        <div class="notification-area">
            <div class="notification-icon" onclick="toggleNotifications(event)">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationCount">0</span>
            </div>
            
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                </div>
                <div class="notification-content">
                    <div class="notification-section" id="orderNotifications">
                        <h4>Recent Orders</h4>
                        <div class="notification-items" id="recentOrdersList">
                            <!-- Orders will be populated here -->
                        </div>
                    </div>
                    <div class="notification-section" id="stockNotifications">
                        <h4>Low Stock Products</h4>
                        <div class="notification-items" id="lowStockList">
                            <!-- Low stock items will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Status Banner -->
        <?php if ($verification_status === 'pending'): ?>
            <div class="verification-banner verification-pending">
                <i class="fas fa-clock"></i>
                Your seller account verification is pending. You'll be able to add products once verified.
            </div>
        <?php elseif ($verification_status === 'verified'): ?>
            <div class="verification-banner verification-verified">
                <i class="fas fa-check-circle"></i>
                Your seller account is verified. You can now add and manage products.
            </div>
        <?php elseif ($verification_status === 'rejected'): ?>
            <div class="verification-banner verification-rejected">
                <i class="fas fa-times-circle"></i>
                Your seller verification was rejected. Please contact support for more information.
            </div>
        <?php endif; ?>

        <!-- Orders Section (Now First) -->
        <div class="section">
            <h2><i class="fas fa-shopping-cart"></i> Recent Orders</h2>
            <?php if (empty($sales)): ?>
                <div class="no-data">
                    <i class="fas fa-shopping-cart"></i>
                    <p>No orders yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Total Amount</th>
                                <th>Order Date</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td>
                                        <div class="product-info">
                                            <img src="<?php echo htmlspecialchars($order['product_image']); ?>" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">
                                            <span><?php echo htmlspecialchars($order['product_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <?php 
                                        // Payment Status Column
                                        echo '<span class="badge ' . ($order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning') . '">';
                                        echo htmlspecialchars($order['payment_status']);
                                        echo '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Order Status Column
                                        if ($order['order_status'] === 'Cancelled') {
                                            echo '<span class="badge bg-danger">Cancelled</span>';
                                            if (!empty($order['cancellation_reason'])) {
                                                echo '<br><small class="text-muted">(' . htmlspecialchars($order['cancellation_reason']) . ')</small>';
                                            }
                                        } else {
                                            echo '<span class="badge bg-success">completed</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Products Section (Now Second) -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-box"></i> Your Products</h2>
            </div>

            <?php if (empty($products)): ?>
                <div class="no-data">
                    <i class="fas fa-box-open"></i>
                    <p>No products added yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-info">
                                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="Product">
                                            <span><?php echo htmlspecialchars($product['name']); ?></span>
                                        </div>
                                    </td>
                                    <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php echo $product['Stock_quantity']; ?>
                                        <?php if ($product['Stock_quantity'] <= 5): ?>
                                            <span class="low-stock">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-active">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($show_verification_popup): ?>
        <div id="verificationPopup" class="verification-popup" style="display: flex;">
            <div class="popup-content">
                <h2>Complete Your Seller Verification</h2>
                <form id="verificationForm" novalidate>
                    <div class="form-group">
                        <label for="id_type">ID Type*</label>
                        <select name="id_type" id="id_type" required>
                            <option value="">Select ID Type</option>
                            <option value="aadhar">Aadhar Card</option>
                            <option value="pan">PAN Card</option>
                            <option value="voter">Voter ID</option>
                            <option value="driving">Driving License</option>
                        </select>
                        <div class="error-message">Please select an ID type</div>
                    </div>

                    <div class="form-group">
                        <label for="id_number">ID Number*</label>
                        <input type="text" id="id_number" name="id_number" required>
                        <div class="error-message">Please enter a valid ID number</div>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Front)*</label>
                        <input type="file" name="id_proof_front" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload front ID proof</div>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Back)*</label>
                        <input type="file" name="id_proof_back" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload back ID proof</div>
                    </div>

                    <div class="form-group">
                        <label>Business License/Registration*</label>
                        <input type="file" name="business_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload business proof</div>
                    </div>

                    <div class="form-group">
                        <label>Address Proof*</label>
                        <input type="file" name="address_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload address proof</div>
                    </div>

                    <div class="terms-group">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the verification terms and conditions*</label>
                        <div class="error-message">Please accept the terms</div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Submit Verification</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Dashboard Content -->
        <?php if ($show_success): ?>
            <div class="dashboard-success-message" id="dashboardSuccess" style="display: block;">
                ✅ Verification completed successfully! Welcome to your seller dashboard.
            </div>
        <?php endif; ?>
        <!-- Rest of your dashboard content -->
    <?php endif; ?>

    <div class="loading" id="loadingIndicator"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let notificationsViewed = false;
    let eventSource = null;

    // Initialize SSE connection
    function initializeEventSource() {
        if (eventSource) {
            eventSource.close();
        }

        eventSource = new EventSource('notification_stream.php');
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            
            if (data.new_orders > 0 || data.low_stock > 0) {
                // Reset notification viewed status
                notificationsViewed = false;
                
                // Show notification badge
                const badge = document.getElementById('notificationCount');
                badge.style.display = 'block';
                
                // Update notifications content
                checkNotifications();
                
                // Show toast notification
                showToast(data);
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource failed:', error);
            eventSource.close();
            // Retry connection after 5 seconds
            setTimeout(initializeEventSource, 5000);
        };
    }

    // Show toast notification
    function showToast(data) {
        const toastContainer = document.getElementById('toastContainer');
        
        if (data.new_orders > 0) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification order-toast';
            toast.innerHTML = `
                <i class="fas fa-shopping-cart"></i>
                New order received!
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
        
        if (data.low_stock > 0) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification stock-toast';
            toast.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                Product stock alert!
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
    }

    function toggleNotifications(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        const isVisible = dropdown.style.display === 'block';
        
        dropdown.style.display = isVisible ? 'none' : 'block';
        
        // Clear notification count when opening dropdown
        if (!isVisible && !notificationsViewed) {
            document.getElementById('notificationCount').style.display = 'none';
            notificationsViewed = true;
        }
    }

    function updateNotificationContent(data) {
        // Only update notification count if notifications haven't been viewed
        if (!notificationsViewed) {
            const notificationBadge = document.getElementById('notificationCount');
            notificationBadge.textContent = data.count;
            notificationBadge.style.display = data.count > 0 ? 'block' : 'none';
        }

        // Update Recent Orders
        const ordersList = document.getElementById('recentOrdersList');
        if (data.orders.length > 0) {
            ordersList.innerHTML = data.orders.map(order => {
                const orderDate = new Date(order.created_at);
                const timeAgo = getTimeAgo(orderDate);
                
                return `
                    <div class="notification-item order">
                        <div class="notification-title">
                            New order #${order.order_id}
                        </div>
                        <div class="notification-details">
                            <div class="product-info">
                                <img src="${order.image_path}" alt="${order.product_name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <div>
                                    <p><strong>Product:</strong> ${order.product_name}</p>
                                    <p><strong>Customer:</strong> ${order.customer_name}</p>
                                    <p><strong>Quantity:</strong> ${order.quantity}</p>
                                    <p><strong>Amount:</strong> ₹${order.total_amount}</p>
                                    <p><small>${timeAgo}</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            ordersList.innerHTML = '<div class="notification-item">No new orders in the last 24 hours</div>';
        }

        // Update Low Stock Products
        const stockList = document.getElementById('lowStockList');
        if (data.low_stock.length > 0) {
            stockList.innerHTML = data.low_stock.map(product => `
                <div class="notification-item stock">
                    <div class="notification-title">
                        ${product.Stock_quantity === 0 ? 'Out of Stock' : 'Low Stock Alert'}
                    </div>
                    <div class="notification-details">
                        <div class="product-info">
                            <img src="${product.image_path}" alt="${product.name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <div>
                                <p><strong>Product:</strong> ${product.name}</p>
                                <p><strong>Current Stock:</strong> ${product.Stock_quantity} units</p>
                                <p><strong>Price:</strong> ₹${product.price}</p>
                                ${product.Stock_quantity === 0 ? 
                                    '<p class="out-of-stock">Product is out of stock!</p>' : 
                                    '<p class="low-stock-warning">Stock running low!</p>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            stockList.innerHTML = '<div class="notification-item">All products are well stocked</div>';
        }
    }

    function checkNotifications() {
        fetch('check_notifications.php')
            .then(response => response.json())
            .then(data => {
                updateNotificationContent(data);
            })
            .catch(error => console.error('Error:', error));
    }

    // Reset notifications viewed status when new notifications arrive
    function resetNotificationsStatus() {
        notificationsViewed = false;
        checkNotifications();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown.style.display === 'block' && !event.target.closest('.notification-area')) {
            dropdown.style.display = 'none';
        }
    });

    // Initial check and periodic updates
    checkNotifications();
    setInterval(resetNotificationsStatus, 60000); // Check every minute

    // Helper function to show relative time
    function getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        let interval = Math.floor(seconds / 3600);
        if (interval < 24) {
            if (interval < 1) {
                interval = Math.floor(seconds / 60);
                if (interval < 1) {
                    return 'Just now';
                }
                return `${interval} minute${interval === 1 ? '' : 's'} ago`;
            }
            return `${interval} hour${interval === 1 ? '' : 's'} ago`;
        }
        return date.toLocaleString();
    }

    document.getElementById('verificationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Reset previous errors
        document.querySelectorAll('.error-message').forEach(error => error.style.display = 'none');
        document.querySelectorAll('.form-group').forEach(group => group.classList.remove('error'));
        
        // Validate form
        let isValid = true;
        const form = this;
        
        // Check required fields
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value) {
                isValid = false;
                field.closest('.form-group').classList.add('error');
                field.closest('.form-group').querySelector('.error-message').style.display = 'block';
            }
        });

        // Validate ID number format based on selected ID type
        const idType = form.querySelector('#id_type').value;
        const idNumber = form.querySelector('#id_number').value;
        if (idType && idNumber) {
            const patterns = {
                aadhar: /^\d{12}$/,
                pan: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/,
                voter: /^[A-Z]{3}\d{7}$/,
                driving: /^[A-Z]{2}\d{13}$/
            };
            
            if (!patterns[idType]?.test(idNumber)) {
                isValid = false;
                form.querySelector('#id_number').closest('.form-group').classList.add('error');
                form.querySelector('#id_number').closest('.form-group').querySelector('.error-message').style.display = 'block';
            }
        }

        if (!isValid) {
            return;
        }

        document.getElementById('loadingIndicator').style.display = 'flex';
        
        const formData = new FormData(this);
        
        fetch('process_seller_verification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingIndicator').style.display = 'none';
            
            if (data.success) {
                window.location.href = 'seller-dashboard.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            document.getElementById('loadingIndicator').style.display = 'none';
            console.error('Error:', error);
            alert('An error occurred while submitting the verification documents.');
        });
    });

    // Auto-hide success message after 5 seconds
    const successMessage = document.getElementById('dashboardSuccess');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }

    // Highlight current page in sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const sidebarLinks = document.querySelectorAll('.sidebar a');
        
        sidebarLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });

        initializeEventSource();
    });
    </script>

    <!-- Add toast container -->
    <div id="toastContainer"></div>
</body>
</html>