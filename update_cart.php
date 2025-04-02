<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (isset($_POST['cart_id']) && isset($_POST['quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $quantity = intval($_POST['quantity']);
    $user_id = $_SESSION['user_id'];
    
    if ($quantity > 0) {
        $update = $conn->prepare("UPDATE tbl_cart SET quantity = ? WHERE cart_id = ? AND user_id = ?");
        $update->bind_param("iii", $quantity, $cart_id, $user_id);
        
        if ($update->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
}
?> 