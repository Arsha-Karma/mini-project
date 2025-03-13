<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

try {
    $conn->begin_transaction();

    // Get seller_id
    $stmt = $conn->prepare("SELECT seller_id FROM tbl_seller WHERE Signup_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $seller_id = $stmt->get_result()->fetch_assoc()['seller_id'];

    // Update seller details
    $stmt = $conn->prepare("
        UPDATE tbl_seller 
        SET id_type = ?, 
            id_number = ?,
            documents_uploaded = 'completed',
            verified_status = 'verified'
        WHERE Signup_id = ?
    ");
    $stmt->bind_param("ssi", $_POST['id_type'], $_POST['id_number'], $_SESSION['user_id']);
    $stmt->execute();

    // Handle file uploads
    $upload_dir = "uploads/seller_verification/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Process each uploaded file
    $files = ['id_proof_front', 'id_proof_back', 'business_proof', 'address_proof'];
    $file_paths = [];
    
    foreach ($files as $file) {
        if (isset($_FILES[$file]) && $_FILES[$file]['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES[$file]['tmp_name'];
            $file_name = $seller_id . '_' . $file . '_' . time() . '_' . $_FILES[$file]['name'];
            $file_destination = $upload_dir . $file_name;
            
            if (!move_uploaded_file($file_tmp, $file_destination)) {
                throw new Exception("Failed to upload " . $file);
            }
            $file_paths[$file] = $file_destination;
        }
    }

    // Store document paths in verification table
    $stmt = $conn->prepare("
        INSERT INTO seller_verification_docs 
        (seller_id, id_proof_front, id_proof_back, business_proof, address_proof)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", 
        $seller_id, 
        $file_paths['id_proof_front'],
        $file_paths['id_proof_back'],
        $file_paths['business_proof'],
        $file_paths['address_proof']
    );
    $stmt->execute();

    $conn->commit();
    
    // Set session flag for success message
    $_SESSION['verification_success'] = true;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Verification successful!'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 