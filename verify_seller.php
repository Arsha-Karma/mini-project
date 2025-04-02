<?php
<<<<<<< HEAD
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
=======
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
    
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'arshaprasobh318@gmail.com';
        $mail->Password = 'ilwf fpya pwkx pmat';
<<<<<<< HEAD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
=======
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
        $mail->Port = 587;
        
        $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
        $mail->addAddress($email);
<<<<<<< HEAD
        
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
=======

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
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
?> 