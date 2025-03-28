<?php
session_start();
require_once 'dbconnect.php';

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    // Update order status
    $update_query = "UPDATE orders_table SET order_status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ss", $status, $order_id);
    
    if ($stmt->execute()) {
        header("Location: view_order_details.php?order_id=" . urlencode($order_id) . "&success=1");
    } else {
        header("Location: view_order_details.php?order_id=" . urlencode($order_id) . "&error=1");
    }
} else {
    header("Location: admindashboard.php");
}
exit();
?> 