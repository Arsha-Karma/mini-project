<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['address']) || empty($data['city']) || empty($data['postal_code'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

try {
    // Insert shipping address
    $shipping_query = "INSERT INTO shipping_addresses 
                      (Signup_id, address_line1, city, state, postal_code, is_default, created_at) 
                      VALUES (?, ?, ?, ?, ?, 1, NOW())";
    
    $stmt = $conn->prepare($shipping_query);
    $default_state = 'default_state'; // You can modify this as needed
    $is_default = 1;
    
    $stmt->bind_param("issss", 
        $_SESSION['user_id'],
        $data['address'],
        $data['city'],
        $default_state,
        $data['postal_code']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Address saved successfully']);
    } else {
        throw new Exception($conn->error);
    }
    
} catch (Exception $e) {
    error_log("Error saving shipping address: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error saving address']);
}
?> 