<?php
session_start();
require_once 'dbconnect.php';

// Check if seller is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'seller') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_product_name') {
    $name = trim($_POST['name']);
    $seller_id = $_SESSION['user_id'];
    
    // Get actual seller_id from tbl_seller
    $stmt = $conn->prepare("SELECT seller_id FROM tbl_seller WHERE Signup_id = ?");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $seller_info = $result->fetch_assoc();
    $actual_seller_id = $seller_info['seller_id'];
    
    // Build query to check for existing product name
    $query = "SELECT product_id FROM tbl_product WHERE name = ? AND seller_id = ? AND deleted = 0";
    $params = [$name, $actual_seller_id];
    $types = "si";
    
    // If editing, exclude current product
    if (isset($_POST['product_id'])) {
        $query .= " AND product_id != ?";
        $params[] = intval($_POST['product_id']);
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['exists' => $result->num_rows > 0]);
    exit();
}

echo json_encode(['error' => 'Invalid request']);
exit(); 