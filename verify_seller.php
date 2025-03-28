<?php
session_start();
require_once('dbconnect.php');

// Ensure no output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON content type
header('Content-Type: application/json');

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['seller_id']) || !isset($data['status'])) {
        throw new Exception('Missing required parameters');
    }

    $seller_id = intval($data['seller_id']);
    $status = $data['status'];

    // Validate status
    if (!in_array($status, ['verified', 'rejected'])) {
        throw new Exception('Invalid status value');
    }

    // Start transaction
    $conn->begin_transaction();

    // Update seller verification status
    $update_seller = "UPDATE tbl_seller 
                     SET verified_status = ? 
                     WHERE seller_id = ?";
    $stmt = $conn->prepare($update_seller);
    $stmt->bind_param("si", $status, $seller_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update seller status');
    }

    // Get seller email
    $email_query = "SELECT email FROM tbl_seller WHERE seller_id = ?";
    $stmt = $conn->prepare($email_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Seller not found');
    }

    $email = $result->fetch_assoc()['email'];

    // Send email notification
    require 'PHPMailer-master/src/Exception.php';
    require 'PHPMailer-master/src/PHPMailer.php';
    require 'PHPMailer-master/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'arshaprasobh318@gmail.com';
        $mail->Password = 'ilwf fpya pwkx pmat';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
        $mail->addAddress($email);

        if ($status === 'verified') {
            $mail->Subject = 'Seller Verification Approved';
            $mail->Body = "Congratulations! Your seller account has been verified. You can now start adding products and selling on Perfume Paradise.";
        } else {
            $mail->Subject = 'Seller Verification Rejected';
            $mail->Body = "We regret to inform you that your seller verification request has been rejected. Please contact support for more information.";
        }

        $mail->send();
    } catch (Exception $e) {
        // Log email error but don't stop the process
        error_log("Email sending failed: " . $e->getMessage());
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Seller status updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction if active
    if ($conn->inTransaction()) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 