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

// Now fetch orders for this seller's products
$sales_query = "
    SELECT o.*, 
           p.name as product_name,
           s.username as customer_name,
           s.email as customer_email
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    WHERE p.seller_id = ?
    ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

        <div class="card">
            <div class="card-body">
                <h4 class="card-title">All Orders</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Customer Details</th>
                                <th>Shipping Address</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sales)): ?>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sale['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($sale['customer_name']); ?><br>
                                            <small><?php echo htmlspecialchars($sale['customer_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $address = nl2br(htmlspecialchars($sale['shipping_address']));
                                            echo $address;
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                                        <td>₹<?php echo htmlspecialchars($sale['total_amount']); ?></td>
                                        <td>
                                            <?php 
                                            if ($sale['order_status'] === 'Cancelled') {
                                                echo '<span class="badge bg-danger">Cancelled</span>';
                                                if (!empty($sale['cancellation_reason'])) {
                                                    echo '<br><small class="text-muted">Reason: ' . htmlspecialchars($sale['cancellation_reason']) . '</small>';
                                                }
                                            } else {
                                                echo '<span class="badge ' . ($sale['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning') . '">';
                                                echo htmlspecialchars($sale['order_status']);
                                                echo '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($sale['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No orders found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>