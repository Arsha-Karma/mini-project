<?php
session_start();
require_once 'dbconnect.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in as seller
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'seller') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['id_type', 'id_number'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate file uploads
    $required_files = ['id_proof_front', 'id_proof_back', 'business_proof', 'address_proof'];
    foreach ($required_files as $file_field) {
        if (!isset($_FILES[$file_field]) || $_FILES[$file_field]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Missing required file: $file_field");
        }
    }

    // Validate ID number format
    $id_type = $_POST['id_type'];
    $id_number = $_POST['id_number'];
    $patterns = [
        'aadhar' => '/^\d{12}$/',
        'pan' => '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
        'voter' => '/^[A-Z]{3}\d{7}$/',
        'driving' => '/^[A-Z]{2}\d{13}$/'
    ];

    if (!isset($patterns[$id_type]) || !preg_match($patterns[$id_type], $id_number)) {
        throw new Exception("Invalid ID number format for selected ID type");
    }

    // Handle file uploads
    $upload_dir = 'uploads/verification_docs/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $uploaded_files = [];
    foreach (['id_proof_front', 'id_proof_back', 'business_proof', 'address_proof'] as $file_field) {
        $file = $_FILES[$file_field];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Invalid file type for $file_field. Allowed types: JPG, PNG, PDF");
        }

        if ($file['size'] > $max_size) {
            throw new Exception("File size too large for $file_field. Maximum size: 5MB");
        }

        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '_' . $file_field . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception("Failed to upload $file_field");
        }

        $uploaded_files[$file_field] = $upload_path;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get seller ID
        $stmt = $conn->prepare("SELECT seller_id FROM tbl_seller WHERE Signup_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $seller_id = $result->fetch_assoc()['seller_id'];

        // Update seller verification details
        $stmt = $conn->prepare("
            UPDATE tbl_seller 
            SET id_type = ?, 
                id_number = ?, 
                verified_status = 'pending',
                documents_uploaded = 'completed'
            WHERE seller_id = ?
        ");
        $stmt->bind_param("ssi", $id_type, $id_number, $seller_id);
        $stmt->execute();

        // Insert verification documents
        $stmt = $conn->prepare("
            INSERT INTO seller_verification_docs 
            (seller_id, id_proof_front, id_proof_back, business_proof, address_proof)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", 
            $seller_id,
            $uploaded_files['id_proof_front'],
            $uploaded_files['id_proof_back'],
            $uploaded_files['business_proof'],
            $uploaded_files['address_proof']
        );
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Set success message in session
        $_SESSION['verification_success'] = true;
        $_SESSION['verification_pending'] = true;

        echo json_encode([
            'success' => true,
            'message' => 'Verification documents submitted successfully. You can now access your dashboard while waiting for admin approval.',
            'redirect' => 'seller-dashboard.php',
            'close_popup' => true
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 