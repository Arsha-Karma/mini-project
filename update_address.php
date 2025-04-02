<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // First check if address exists for this user
    $check_stmt = $conn->prepare("SELECT address_id FROM shipping_address WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing address
        $stmt = $conn->prepare("UPDATE shipping_address SET 
            full_name = ?,
            phone_number = ?,
            email = ?,
            address = ?,
            city = ?,
            postal_code = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?");
        
        $stmt->bind_param("ssssssi", 
            $_POST['fullname'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['address'],
            $_POST['city'],
            $_POST['pincode'],
            $user_id
        );
    } else {
        // Insert new address
        $stmt = $conn->prepare("INSERT INTO shipping_address 
            (user_id, full_name, phone_number, email, address, city, postal_code, is_default) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        
        $stmt->bind_param("issssss", 
            $user_id,
            $_POST['fullname'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['address'],
            $_POST['city'],
            $_POST['pincode']
        );
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 