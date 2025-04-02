<?php
// Prevent any output before the JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to the browser

// Start a session if needed
session_start();

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Get the input data
$input = json_decode(file_get_contents('php://input'), true);

// Connect to database
require_once('dbconnect.php');
$database_name = "perfumes";
mysqli_select_db($conn, $database_name);

$response = array('success' => false, 'message' => 'Invalid request');

// Function to send email to seller
function sendSellerEmail($email, $sellerName, $status) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'arshaprasobh318@gmail.com';
        $mail->Password = 'ilwf fpya pwkx pmat';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
        $mail->addAddress($email);
        
        if ($status === 'verified') {
            $mail->Subject = 'Your Seller Account Has Been Approved';
            $mail->Body = "Dear $sellerName,\n\n"
                . "Congratulations! Your seller account verification on Perfume Paradise has been approved. "
                . "You can now log in to your account and start listing your products.\n\n"
                . "To get started, log in to your account and visit your seller dashboard.\n\n"
                . "Best regards,\n"
                . "Perfume Paradise Team";
        } else {
            $mail->Subject = 'Your Seller Account Verification Status';
            $mail->Body = "Dear $sellerName,\n\n"
                . "Thank you for your interest in becoming a seller on Perfume Paradise. "
                . "After reviewing your application, we regret to inform you that we are unable to approve your seller account at this time.\n\n"
                . "This could be due to incomplete or unclear documentation. You may reapply with updated documents or contact our support team for more information.\n\n"
                . "Best regards,\n"
                . "Perfume Paradise Team";
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Validate input
if (isset($input['seller_id']) && isset($input['status'])) {
    $seller_id = intval($input['seller_id']);
    $status = mysqli_real_escape_string($conn, $input['status']);
    
    // Only allow specific status values
    if ($status === 'verified' || $status === 'rejected') {
        try {
            // Get seller information first (for email)
            $sellerQuery = "SELECT s.email, sl.Sellername 
                          FROM tbl_seller sl 
                          JOIN tbl_signup s ON sl.Signup_id = s.Signup_id 
                          WHERE sl.seller_id = ?";
            $stmt = mysqli_prepare($conn, $sellerQuery);
            mysqli_stmt_bind_param($stmt, "i", $seller_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $sellerInfo = mysqli_fetch_assoc($result);
            
            // Update seller status
            $query = "UPDATE tbl_seller SET verified_status = ? WHERE seller_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "si", $status, $seller_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = 'Seller status updated successfully';
                
                // Send email notification
                if ($sellerInfo) {
                    $emailSent = sendSellerEmail(
                        $sellerInfo['email'], 
                        $sellerInfo['Sellername'], 
                        $status
                    );
                    $response['email_sent'] = $emailSent;
                    if (!$emailSent) {
                        $response['email_error'] = 'Failed to send notification email';
                    }
                }
            } else {
                $response['message'] = 'Database error: ' . mysqli_error($conn);
            }
        } catch (Exception $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Invalid status value';
    }
}

// Set content type to JSON
header('Content-Type: application/json');

// Output the JSON response
echo json_encode($response);
exit;
?> 