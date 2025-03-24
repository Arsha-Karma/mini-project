<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$product_id = (int)$data['product_id'];
$user_id = (int)$_SESSION['user_id'];

// Check if product exists in wishlist
$check_stmt = $conn->prepare("SELECT wishlist_id FROM tbl_wishlist WHERE user_id = ? AND product_id = ?");
$check_stmt->bind_param("ii", $user_id, $product_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Remove from wishlist
    $delete_stmt = $conn->prepare("DELETE FROM tbl_wishlist WHERE user_id = ? AND product_id = ?");
    $delete_stmt->bind_param("ii", $user_id, $product_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing from wishlist']);
    }
} else {
    // Add to wishlist
    $insert_stmt = $conn->prepare("INSERT INTO tbl_wishlist (user_id, product_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $product_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding to wishlist']);
    }
} 