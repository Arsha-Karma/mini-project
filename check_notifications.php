<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    echo json_encode(['count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get seller_id
$seller_query = "SELECT seller_id FROM tbl_seller WHERE Signup_id = ?";
$stmt = $conn->prepare($seller_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$seller_result = $stmt->get_result();
$seller_data = $seller_result->fetch_assoc();
$seller_id = $seller_data['seller_id'];

// Get recent orders (last 24 hours)
$orders_query = "
    SELECT 
        o.order_id,
        p.name as product_name,
        o.quantity,
        o.total_amount,
        o.created_at,
        s.username as customer_name,
        p.image_path
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    WHERE p.seller_id = ? 
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY o.created_at DESC";

$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get low stock and out of stock products
$stock_query = "
    SELECT 
        product_id,
        name,
        Stock_quantity,
        image_path,
        price
    FROM tbl_product 
    WHERE seller_id = ? 
    AND Stock_quantity <= 5
    ORDER BY Stock_quantity ASC";

$stmt = $conn->prepare($stock_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$low_stock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_notifications = count($recent_orders) + count($low_stock);

echo json_encode([
    'count' => $total_notifications,
    'orders' => $recent_orders,
    'low_stock' => $low_stock
]);
?> 