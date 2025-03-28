<?php
session_start();

// Redirect if not logged in or not an admin
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
} elseif ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once('dbconnect.php');
$database_name = "perfumes";
mysqli_select_db($conn, $database_name);

<<<<<<< HEAD
// Get the current view from URL parameter, default to 'dashboard'
=======
// Determine which section to show
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// Validate the view parameter to prevent unauthorized values
$allowed_views = ['dashboard', 'users', 'sellers', 'verify-sellers', 'orders'];
if (!in_array($view, $allowed_views)) {
    $view = 'dashboard'; // Set to default if invalid view is provided
}

// Add this query near the top of the file after database connection
$pending_notifications_query = "SELECT COUNT(*) as pending_count FROM tbl_seller WHERE verified_status = 'pending'";
$pending_result = mysqli_query($conn, $pending_notifications_query);
$pending_count = mysqli_fetch_assoc($pending_result)['pending_count'];

// Count unread customer support messages
$unread_messages_query = "SELECT COUNT(*) as unread_count FROM contact_messages WHERE status = 'unread' AND (is_deleted = 0 OR is_deleted IS NULL)";
$unread_messages_result = mysqli_query($conn, $unread_messages_query);
$unread_messages_count = mysqli_fetch_assoc($unread_messages_result)['unread_count'];

// Calculate total notifications
$total_notifications = $pending_count + $unread_messages_count;

// Calculate total counts
$totalUsersQuery = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN verification_status = 'disabled' THEN 1 ELSE 0 END) as inactive_count
    FROM tbl_users WHERE role_type = 'user'";

$totalSellersQuery = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN verification_status = 'disabled' THEN 1 ELSE 0 END) as inactive_count
    FROM tbl_seller WHERE role_type = 'seller'";

$totalUsersResult = mysqli_query($conn, $totalUsersQuery);
$totalSellersResult = mysqli_query($conn, $totalSellersQuery);

$userStats = mysqli_fetch_assoc($totalUsersResult);
$sellerStats = mysqli_fetch_assoc($totalSellersResult);

$totalUsers = $userStats['total'] ?? 0;
$activeUsers = $userStats['active_count'] ?? 0;
$inactiveUsers = $userStats['inactive_count'] ?? 0;

$totalSellers = $sellerStats['total'] ?? 0;
$activeSellers = $sellerStats['active_count'] ?? 0;
$inactiveSellers = $sellerStats['inactive_count'] ?? 0;

// Add order statistics query after line 65
<<<<<<< HEAD
$totalOrdersQuery = "SELECT
=======
$totalOrdersQuery = "SELECT 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
    COUNT(*) as total,
    SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing_count,
    SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM orders_table";

$totalOrdersResult = mysqli_query($conn, $totalOrdersQuery);
$orderStats = mysqli_fetch_assoc($totalOrdersResult);

$totalOrders = $orderStats['total'] ?? 0;
$processingOrders = $orderStats['processing_count'] ?? 0;
$completedOrders = $orderStats['completed_count'] ?? 0;

<<<<<<< HEAD
// Add this query after your existing queries
$admin_profits_query = "SELECT 
    SUM(amount) as total_profit,
    SUM(CASE WHEN month_year = DATE_FORMAT(NOW(), '%Y-%m-01') THEN amount ELSE 0 END) as current_month_profit
    FROM admin_profits";

$profits_result = mysqli_query($conn, $admin_profits_query);
$profits_data = mysqli_fetch_assoc($profits_result);

$total_admin_profit = $profits_data['total_profit'] ?? 0;
$current_month_profit = $profits_data['current_month_profit'] ?? 0;

=======
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Function to send deactivation email
function sendDeactivationEmail($email) {
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
        $mail->Subject = 'Account Deactivation Notice';
        $mail->Body = "Dear User,\n\nYour account has been deactivated by the administrator. If you believe this was done in error, please contact our support team.\n\nBest regards,\nPerfume Paradise Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle user/seller activation/deactivation
if (isset($_POST['action']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    try {
        mysqli_begin_transaction($conn);
       
        switch($action) {
            case 'activate_user':
                $updateSignup = "UPDATE tbl_signup SET verification_status = 'active' WHERE Signup_id = ?";
                $stmt = mysqli_prepare($conn, $updateSignup);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $updateUser = "UPDATE tbl_users SET status = 'active', verification_status = 'active' WHERE Signup_id = ?";
                $stmt = mysqli_prepare($conn, $updateUser);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                break;

            case 'deactivate_user':
                $emailQuery = "SELECT email FROM tbl_users WHERE Signup_id = ?";
                $stmt = mysqli_prepare($conn, $emailQuery);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $email = mysqli_fetch_assoc($result)['email'];

                $updateSignup = "UPDATE tbl_signup SET verification_status = 'disabled' WHERE Signup_id = ?";
                $stmt = mysqli_prepare($conn, $updateSignup);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $updateUser = "UPDATE tbl_users SET status = 'inactive', verification_status = 'disabled' WHERE Signup_id = ?";
                $stmt = mysqli_prepare($conn, $updateUser);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                sendDeactivationEmail($email);
                break;

            case 'activate_seller':
                $updateSignup = "UPDATE tbl_signup SET verification_status = 'active'
                               WHERE Signup_id = (SELECT Signup_id FROM tbl_seller WHERE seller_id = ?)";
                $stmt = mysqli_prepare($conn, $updateSignup);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $updateSeller = "UPDATE tbl_seller SET status = 'active', verification_status = 'active' WHERE seller_id = ?";
                $stmt = mysqli_prepare($conn, $updateSeller);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                break;

            case 'deactivate_seller':
                $emailQuery = "SELECT email FROM tbl_seller WHERE seller_id = ?";
                $stmt = mysqli_prepare($conn, $emailQuery);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $email = mysqli_fetch_assoc($result)['email'];

                $updateSignup = "UPDATE tbl_signup SET verification_status = 'disabled'
                               WHERE Signup_id = (SELECT Signup_id FROM tbl_seller WHERE seller_id = ?)";
                $stmt = mysqli_prepare($conn, $updateSignup);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $updateSeller = "UPDATE tbl_seller SET status = 'inactive', verification_status = 'disabled' WHERE seller_id = ?";
                $stmt = mysqli_prepare($conn, $updateSeller);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                sendDeactivationEmail($email);
                break;
        }
       
        mysqli_commit($conn);
        // Maintain the current view after action
        if (strpos($action, 'user') !== false) {
            header("Location: admindashboard.php?view=users&success=1");
        } else {
            header("Location: admindashboard.php?view=sellers&success=1");
        }
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error in action: " . $e->getMessage());
        header("Location: admindashboard.php?error=1");
        exit();
    }
}

if (isset($_POST['action']) && isset($_POST['seller_id'])) {
    $seller_id = intval($_POST['seller_id']);
<<<<<<< HEAD
   
    switch($_POST['action']) {
        case 'approve_seller':
            $updateSeller = "UPDATE tbl_seller
                SET verified_status = 'verified'
=======
    
    switch($_POST['action']) {
        case 'approve_seller':
            $updateSeller = "UPDATE tbl_seller 
                SET verified_status = 'verified' 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                WHERE seller_id = ?";
            $stmt = mysqli_prepare($conn, $updateSeller);
            mysqli_stmt_bind_param($stmt, "i", $seller_id);
            mysqli_stmt_execute($stmt);
<<<<<<< HEAD
           
=======
            
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
            // Send approval email to seller
            $emailQuery = "SELECT email FROM tbl_seller WHERE seller_id = ?";
            $stmt = mysqli_prepare($conn, $emailQuery);
            mysqli_stmt_bind_param($stmt, "i", $seller_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $email = mysqli_fetch_assoc($result)['email'];
<<<<<<< HEAD
           
=======
            
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
            // Use the existing email function
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'arshaprasobh318@gmail.com';
                $mail->Password = 'ilwf fpya pwkx pmat';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
<<<<<<< HEAD
               
=======
                
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
                $mail->addAddress($email);
                $mail->Subject = 'Seller Verification Approved';
                $mail->Body = "Congratulations! Your seller account has been verified. You can now start adding products and selling on Perfume Paradise.";
<<<<<<< HEAD
               
=======
                
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                $mail->send();
            } catch (Exception $e) {
                error_log("Email sending failed: " . $mail->ErrorInfo);
            }
            break;
<<<<<<< HEAD
           
        case 'reject_seller':
            $updateSeller = "UPDATE tbl_seller
                SET verified_status = 'rejected'
=======
            
        case 'reject_seller':
            $updateSeller = "UPDATE tbl_seller 
                SET verified_status = 'rejected' 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                WHERE seller_id = ?";
            $stmt = mysqli_prepare($conn, $updateSeller);
            mysqli_stmt_bind_param($stmt, "i", $seller_id);
            mysqli_stmt_execute($stmt);
            break;
    }
<<<<<<< HEAD
   
=======
    
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
    header("Location: admindashboard.php?view=verify-sellers&success=1");
    exit();
}

// Fetch user data
$usersQuery = "
    SELECT
        u.username,
        COALESCE(u.email, s.email) as email,
        COALESCE(u.phoneno, s.Phoneno) as phoneno,
        u.verification_status,
        u.role_type,
        s.created_at,
        u.Signup_id
    FROM tbl_users u
    LEFT JOIN tbl_signup s ON u.Signup_id = s.Signup_id
    WHERE u.role_type = 'user'
    ORDER BY s.created_at DESC
";

$usersResult = mysqli_query($conn, $usersQuery);

// Fetch seller data
$sellersQuery = "
    SELECT
        sl.seller_id,
        sl.Sellername,
        COALESCE(sl.email, s.email) as email,
        COALESCE(sl.phoneno, s.Phoneno) as phoneno,
        sl.verification_status,
        sl.role_type,
        s.created_at
    FROM tbl_seller sl
    LEFT JOIN tbl_signup s ON sl.Signup_id = s.Signup_id
    WHERE sl.role_type = 'seller'
    ORDER BY s.created_at DESC
";

$sellersResult = mysqli_query($conn, $sellersQuery);

// Fetch pending verification requests
$pending_verification_query = "
    SELECT
        s.seller_id,
        s.Sellername,
        s.email,
        s.phoneno,
        s.created_at,
        svd.id_proof_front,
        svd.id_proof_back,
        svd.business_proof,
        svd.address_proof
    FROM tbl_seller s
    LEFT JOIN seller_verification_docs svd ON s.seller_id = svd.seller_id
    WHERE s.verified_status = 'pending'
    ORDER BY s.created_at DESC";

$pending_verification_result = $conn->query($pending_verification_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Perfume Paradise</title>
    <style>
     body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f7fc;
    color: #333;
}

.sidebar {
    height: 100%;
    width: 250px;
<<<<<<< HEAD
    position: fixed;
    top: 0;
    left: 0;
    background-color: #1a1a1a;
    padding-top: 20px;
    color: white;
=======
    background-color: #1a1a1a !important;  /* Dark black for sidebar */
    height: 100vh;
    position: fixed;
    color: #ffffff;
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
}

.sidebar h2 {
    color: #fff;
    text-align: center;
    margin-bottom: 30px;
    padding: 15px;
    border-bottom: 1px solid #333;
}

.sidebar a {
<<<<<<< HEAD
    padding: 15px 25px;
=======
    color: #ffffff;
    padding: 15px 20px;
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
    text-decoration: none;
    font-size: 16px;
    color: #fff;
    display: block;
    transition: 0.3s;
}

<<<<<<< HEAD
.sidebar a:hover {
    background-color: #333;
    color: #fff;
}

.sidebar a.active {
    background-color: #333;
    border-left: 4px solid #fff;
}

/* Icon Styles */
.sidebar i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
    font-size: 16px;
=======
.sidebar a:hover, .sidebar .active {
    background-color: #1a1a1a;
    color: #ffffff;
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
}

/* Adjust main content to accommodate sidebar */
.main-content {
    margin-left: 250px;
    padding: 20px;
}

/* Responsive design */
@media screen and (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        margin-bottom: 20px;
    }
   
    .main-content {
        margin-left: 0;
    }
   
    .sidebar a {
        float: left;
        padding: 15px;
    }
   
    .sidebar h2 {
        display: none;
    }
}

@media screen and (max-width: 480px) {
    .sidebar a {
        text-align: center;
        float: none;
    }
}

.header {
    background-color: #fff;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header h1 {
    color: #2d2a4b;
    margin: 0;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 1.1em;
    color: #666;
}

.stat-box .number {
    font-size: 2em;
    font-weight: bold;
    color: #2d2a4b;
}

table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background-color: #1a1a1a !important;
    color: white;
    font-weight: bold;
}

tr:hover {
    background-color: #f8f9ff;
}

.actions {
    display: flex;
    gap: 5px;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9em;
}

.btn-activate {
    background-color: #28a745;
    color: white;
}

.btn-deactivate {
    background-color: #ff9800;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}

.status-active {
    color: #28a745;
}

.status-inactive {
    color: #dc3545;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #4CAF50;
    color: white;
}

.alert-error {
    background-color: #f44336;
    color: white;
}

.logout-btn {
    background-color: #f44336;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    transition: opacity 0.3s ease;
}

.logout-btn:hover {
    opacity: 0.9;
}

.section {
    margin-bottom: 30px;
}

.section-hidden {
    display: none;
}

.nav-link {
    color: #ffffff;
}

.nav-link:hover,
.nav-link.active {
    background-color: #1a1a1a;
    color: #ffffff;
}

.card {
    border: 1px solid #e0e0e0;
}

.card-header {
    background-color: white;
    border-bottom: 1px solid #dee2e6;
}

.stats-card {
    background-color: white;
    border: 1px solid #e0e0e0;
}

.stats-number {
    color: #000000;  /* Black for numbers */
}

.btn-primary {
    background-color: #000000;
    border-color: #000000;
}

.btn-primary:hover {
    background-color: #1a1a1a;
    border-color: #1a1a1a;
}

/* Header Title Section */
.header-title {
    background-color: #000000 !important;
    color: white;
    padding: 15px;
}

/* Perfume Paradise Title */
.brand-title {
    background-color: #000000 !important;
    color: white;
    padding: 20px;
    margin: 0;
}

/* Sidebar */
.sidebar {
    background-color: #1a1a1a !important;
}

/* Table Headers and Section Headers */
.section-header,
.card-header,
thead th {
    background-color: #000000 !important;
    color: white !important;
}

/* Stats Cards Headers */
.stats-card .card-header {
    background-color: #000000 !important;
    color: white;
}

/* Perfume Paradise Title in Sidebar */
.sidebar-header,
.sidebar-brand,
.brand-title {
    background-color: #000000 !important;
    color: white !important;
    padding: 20px;
    margin: 0;
}

/* Ensure the text "Perfume Paradise" is visible */
.sidebar-header h2,
.sidebar-brand h2,
.brand-title h2 {
    color: white !important;
}

.status-processing {
    color: #856404;
    background-color: #fff3cd;
    padding: 4px 8px;
    border-radius: 4px;
}

.status-completed {
    color: #155724;
    background-color: #d4edda;
    padding: 4px 8px;
    border-radius: 4px;
}

.status-paid {
    color: #155724;
    background-color: #d4edda;
    padding: 4px 8px;
    border-radius: 4px;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    background-color: transparent;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
    background-color: #f8f9fa;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 12px;
}

.bg-success {
    background-color: #28a745;
    color: white;
}

.bg-warning {
    background-color: #ffc107;
    color: #000;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}

.text-center {
    text-align: center;
}

small {
    font-size: 85%;
    color: #6c757d;
}

.document-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    padding: 15px;
}

.document-item {
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 4px;
}

.document-item h6 {
    margin-bottom: 10px;
    color: #333;
}

.document-item img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
}

.badge {
    padding: 8px 12px;
    font-size: 0.9em;
}

.btn-sm {
    margin: 2px;
}

.table td {
    vertical-align: middle;
}

.bg-dark {
    background-color: #000000 !important;
}

.table thead th {
    border-color: #000000;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
    color: white;
}

<<<<<<< HEAD
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
    z-index: 1000;
}

.modal-content {
    position: relative;
    background-color: #fff;
    margin: 5% auto;
    padding: 20px;
    width: 80%;
    max-width: 900px;
    border-radius: 8px;
    max-height: 85vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.modal-header h2 {
    margin: 0;
    color: #333;
}

.close-modal {
    font-size: 28px;
    font-weight: bold;
    color: #666;
    cursor: pointer;
}

.close-modal:hover {
    color: #000;
}

.seller-info {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 6px;
}

.seller-info h3 {
    margin-top: 0;
    color: #333;
}

.document-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-top: 20px;
}

.document-item {
    border: 1px solid #dee2e6;
    padding: 15px;
    border-radius: 6px;
}

.document-item h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.document-item img {
    width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: contain;
    border-radius: 4px;
}

.modal-footer {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
    text-align: right;
}

@media (max-width: 768px) {
    .document-grid {
        grid-template-columns: 1fr;
    }
}

.badge {
    padding: 8px 12px;
    font-size: 0.9em;
    font-weight: 500;
}

.bg-success {
    background-color: #28a745 !important;
}

.bg-warning {
    background-color: #ffc107 !important;
    color: #000 !important;
=======
.modal-lg {
    max-width: 800px;
}

.document-links {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.doc-link {
    color: #17a2b8;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 0;
}

.doc-link:hover {
    color: #138496;
    text-decoration: underline;
}

.doc-link i {
    font-size: 14px;
}

.document-section {
    margin-top: 10px;
}

.document-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.doc-link {
    color: #17a2b8;
    text-decoration: none;
}

.doc-link:hover {
    text-decoration: underline;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.card-header {
    padding: 1rem;
}

.card-header h4 {
    margin: 0;
    font-size: 1.2rem;
}

.card {
    margin-bottom: 2rem;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.bg-dark {
    background-color: #000000 !important;
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
}

.bg-danger {
    background-color: #dc3545 !important;
<<<<<<< HEAD
}

.bg-secondary {
    background-color: #6c757d !important;
}

.export-buttons {
    display: flex;
    gap: 10px;
    margin: 20px 0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.btn-success {
    background-color: #28a745;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-success:hover {
    background-color: #218838;
}

/* Add these styles to the existing <style> section */
.notification-bell {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    cursor: pointer;
}

.notification-icon {
    font-size: 24px;
    color: #333;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #ff4444;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    min-width: 18px;
    text-align: center;
}

.notification-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background-color: white;
    min-width: 300px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.notification-dropdown h5 {
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}

.notification-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item strong {
    color: #000;
}

.view-all-link {
    display: block;
    text-align: center;
    padding: 8px;
    margin: 5px 0;
    color: #007bff;
    text-decoration: none;
    font-size: 14px;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.view-all-link:hover {
    background-color: #e9ecef;
=======
    color: white;
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
}
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
<<<<<<< HEAD
        <h3 style="background-color: #000000;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Perfume Paradise</h3>
        <a href="admindashboard.php" class="<?php echo $view == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="admindashboard.php?view=users" class="<?php echo $view == 'users' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Manage Users
        </a>
        <a href="admindashboard.php?view=sellers" class="<?php echo $view == 'sellers' ? 'active' : ''; ?>">
            <i class="fas fa-store"></i> Manage Sellers
        </a>
        <a href="admindashboard.php?view=verify-sellers" class="<?php echo $view == 'verify-sellers' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i> Verify Sellers
        </a>
        <a href="admindashboard.php?view=orders" class="<?php echo $view == 'orders' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> View Orders
        </a>
        <a href="manage-categories.php">
            <i class="fas fa-tags"></i> Manage Categories
        </a>
        <a href="customer_reviews.php">
            <i class="fas fa-star"></i> Customer Reviews
        </a>
        <a href="index.php">
            <i class="fas fa-home"></i> Home
        </a>
        <a href="view_messages.php" class="<?php echo $view == 'messages' ? 'active' : ''; ?>">
            <i class="fas fa-headset"></i> Customer Support
            <!-- <?php
            // Add unread messages count badge if there are any
            $unread_query = "SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread'";
            $unread_result = $conn->query($unread_query);
            if ($unread_result && $unread_count = $unread_result->fetch_assoc()['count']) {
                if ($unread_count > 0) {
                    echo '<span class="notification-badge">' . $unread_count . '</span>';
                }
            }
            ?> -->
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
=======
        <h2 style="background-color: #000000;">Perfume Paradise</h2>
        <a href="admindashboard.php" class="<?php echo $view == 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="admindashboard.php?view=users" class="<?php echo $view == 'users' ? 'active' : ''; ?>">Manage Users</a>
        <a href="admindashboard.php?view=sellers" class="<?php echo $view == 'sellers' ? 'active' : ''; ?>">Manage Sellers</a>
        <a href="admindashboard.php?view=verify-sellers" class="<?php echo $view == 'verify-sellers' ? 'active' : ''; ?>">Verify Sellers</a>
        <a href="admindashboard.php?view=orders" class="<?php echo $view == 'orders' ? 'active' : ''; ?>">View Orders</a>
        <a href="manage-categories.php">Manage Categories</a>
        <a href="customer_reviews.php">Customer Reviews</a>
        <a href="index.php">Home</a>
        <a href="logout.php">Logout</a>
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
    </div>

    <div class="main-content">
        <div class="header">
            <h1 style="color: #000000;">Welcome Admin!</h1>
        </div>

        <?php if (isset($_GET['success'])) { ?>
            <div class="alert alert-success">Action completed successfully!</div>
        <?php } ?>
       
        <?php if (isset($_GET['error'])) { ?>
            <div class="alert alert-error">An error occurred. Please try again.</div>
        <?php } ?>

<<<<<<< HEAD
        <div class="notification-bell">
            <i class="fas fa-bell notification-icon"></i>
            <?php if ($total_notifications > 0): ?>
                <span class="notification-badge"><?php echo $total_notifications; ?></span>
            <?php endif; ?>
            <div class="notification-dropdown">
                <h4 style="margin: 0 0 10px 10px;">Notifications</h4>
                
                <?php if ($pending_count > 0 || $unread_messages_count > 0): ?>
                    <!-- Seller verification requests section -->
                    <?php if ($pending_count > 0): ?>
                        <h5 style="margin: 5px 0 5px 10px; color: #666;">Seller Verification Requests</h5>
                        <?php
                        $notifications_query = "SELECT seller_id, Sellername, created_at FROM tbl_seller WHERE verified_status = 'pending' ORDER BY created_at DESC LIMIT 3";
                        $notifications_result = mysqli_query($conn, $notifications_query);
                        
                        while ($notification = mysqli_fetch_assoc($notifications_result)) {
                            echo '<div class="notification-item">';
                            echo '<strong>' . htmlspecialchars($notification['Sellername']) . '</strong> has requested verification<br>';
                            echo '<small>Requested on: ' . date('M d, Y', strtotime($notification['created_at'])) . '</small>';
                            echo '</div>';
                        }
                        ?>
                        <a href="admindashboard.php?view=verify-sellers" class="view-all-link">View All Seller Requests</a>
                    <?php endif; ?>
                    
                    <!-- Customer support messages section -->
                    <?php if ($unread_messages_count > 0): ?>
                        <h5 style="margin: 15px 0 5px 10px; color: #666;">Unread Customer Messages</h5>
                        <?php
                        $messages_query = "SELECT id, name, subject, created_at FROM contact_messages WHERE status = 'unread' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY created_at DESC LIMIT 3";
                        $messages_result = mysqli_query($conn, $messages_query);
                        
                        while ($message = mysqli_fetch_assoc($messages_result)) {
                            echo '<div class="notification-item">';
                            echo '<strong>' . htmlspecialchars($message['name']) . '</strong>: ' . htmlspecialchars(substr($message['subject'], 0, 30)) . (strlen($message['subject']) > 30 ? '...' : '') . '<br>';
                            echo '<small>Received on: ' . date('M d, Y', strtotime($message['created_at'])) . '</small>';
                            echo '</div>';
                        }
                        ?>
                        <a href="view_messages.php" class="view-all-link">View All Messages</a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="notification-item">No new notifications</div>
                <?php endif; ?>
=======
        <?php if ($view == 'dashboard'): ?>
            <!-- Dashboard Stats -->
            <div class="stats-container">
                <div class="stat-box">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $totalUsers; ?></div>
                    <div class="details">
                        <span>Active: <?php echo $activeUsers; ?></span>
                        <span>Inactive: <?php echo $inactiveUsers; ?></span>
                    </div>
                </div>

                <div class="stat-box">
                    <h3>Total Sellers</h3>
                    <div class="number"><?php echo $totalSellers; ?></div>
                    <div class="details">
                        <span>Active: <?php echo $activeSellers; ?></span>
                        <span>Inactive: <?php echo $inactiveSellers; ?></span>
                    </div>
                </div>

                <div class="stat-box">
                    <h3>Total Orders</h3>
                    <div class="number"><?php echo $totalOrders; ?></div>
                    <div class="details">
                        <span>Processing: <?php echo $processingOrders; ?></span>
                        <span>Completed: <?php echo $completedOrders; ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Section -->
            <div class="section">
                <h2>View Orders</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Customer Details</th>
                                <th>Shipping Address</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Query for recent orders (limited to 5)
                            $recent_orders_query = "SELECT DISTINCT 
                                o.*,
                                s.username as customer_name,
                                s.email as customer_email,
                                p.name as product_name,
                                pt.payment_status
                                FROM orders_table o
                                LEFT JOIN tbl_signup s ON o.Signup_id = s.Signup_id
                                LEFT JOIN tbl_product p ON o.product_id = p.product_id
                                LEFT JOIN payment_table pt ON o.payment_id = pt.payment_id
                                ORDER BY o.created_at DESC
                                LIMIT 5";
                            
                            $recent_orders_result = mysqli_query($conn, $recent_orders_query);
                            
                            if (!$recent_orders_result) {
                                echo "<tr><td colspan='9' class='text-center'>Error fetching orders: " . mysqli_error($conn) . "</td></tr>";
                            } elseif (mysqli_num_rows($recent_orders_result) == 0) {
                                echo "<tr><td colspan='9' class='text-center'>No orders found</td></tr>";
                            } else {
                                while ($order = mysqli_fetch_assoc($recent_orders_result)): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $address = nl2br(htmlspecialchars($order['shipping_address']));
                                            echo $address;
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                        <td>₹<?php echo htmlspecialchars($order['total_amount']); ?></td>
                                        <td>
                                            <?php 
                                            if ($order['order_status'] === 'Cancelled') {
                                                // Red badge for cancelled orders
                                                echo '<span class="badge bg-danger">Cancelled</span>';
                                            } else {
                                                // Original logic for other statuses
                                                echo '<span class="badge ' . (($order['payment_status'] ?? '') === 'paid' ? 'bg-success' : 'bg-warning') . '">';
                                                echo ($order['payment_status'] ?? '') === 'paid' ? 'completed' : htmlspecialchars($order['order_status']);
                                                echo '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($order['created_at']))); ?></td>
                                        <td class="actions">
                                            <a href="view_order_details.php?order_id=<?php echo urlencode($order['order_id']); ?>" 
                                               class="btn btn-primary btn-sm">View Details</a>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- View All Orders Link -->
                <div class="text-right mt-3">
                    <a href="admindashboard.php?view=orders" class="btn btn-primary">
                        View All Orders
                    </a>
                </div>
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
            </div>

            <!-- Additional styles for the dashboard -->
            <style>
                .section {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-top: 30px;
                }

                .section h2 {
                    margin-bottom: 20px;
                    color: #333;
                }

                .text-right {
                    text-align: right;
                }

                .mt-3 {
                    margin-top: 15px;
                }

                .btn-primary {
                    background-color: #007bff;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 4px;
                    text-decoration: none;
                    display: inline-block;
                }

                .btn-primary:hover {
                    background-color: #0056b3;
                }
            </style>
        <?php endif; ?>

        <?php if ($view == 'dashboard'): ?>
            <!-- Dashboard Stats -->
            <div class="stats-container">
                <div class="stat-box">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $totalUsers; ?></div>
                    <div class="details">
                        <span>Active: <?php echo $activeUsers; ?></span>
                        <span>Inactive: <?php echo $inactiveUsers; ?></span>
                    </div>
                </div>

                <div class="stat-box">
                    <h3>Total Sellers</h3>
                    <div class="number"><?php echo $totalSellers; ?></div>
                    <div class="details">
                        <span>Active: <?php echo $activeSellers; ?></span>
                        <span>Inactive: <?php echo $inactiveSellers; ?></span>
                    </div>
                </div>

                <div class="stat-box">
                    <h3>Total Orders</h3>
                    <div class="number"><?php echo $totalOrders; ?></div>
                    <div class="details">
                        <span>Processing: <?php echo $processingOrders; ?></span>
                        <span>Completed: <?php echo $completedOrders; ?></span>
                    </div>
                </div>

                <div class="stat-box">
                    <h3>Total Profits</h3>
                    <div class="number">₹<?php echo number_format($total_admin_profit, 2); ?></div>
                    <div class="details">
                        <span>This Month: ₹<?php echo number_format($current_month_profit, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Section -->
            <div class="section">
                <h2>View Orders</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Customer Details</th>
                                <th>Shipping Address</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query for recent orders (limited to 5)
                            $recent_orders_query = "SELECT DISTINCT
                                o.*,
                                s.username as customer_name,
                                s.email as customer_email,
                                p.name as product_name,
                                pt.payment_status
                                FROM orders_table o
                                LEFT JOIN tbl_signup s ON o.Signup_id = s.Signup_id
                                LEFT JOIN tbl_product p ON o.product_id = p.product_id
                                LEFT JOIN payment_table pt ON o.payment_id = pt.payment_id
                                ORDER BY o.created_at DESC
                                LIMIT 5";
                           
                            $recent_orders_result = mysqli_query($conn, $recent_orders_query);
                           
                            if (!$recent_orders_result) {
                                echo "<tr><td colspan='9' class='text-center'>Error fetching orders: " . mysqli_error($conn) . "</td></tr>";
                            } elseif (mysqli_num_rows($recent_orders_result) == 0) {
                                echo "<tr><td colspan='9' class='text-center'>No orders found</td></tr>";
                            } else {
                                while ($order = mysqli_fetch_assoc($recent_orders_result)):
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $address = nl2br(htmlspecialchars($order['shipping_address']));
                                            echo $address;
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                        <td>₹<?php echo htmlspecialchars($order['total_amount']); ?></td>
                                        <td>
                                            <?php
                                            if ($order['order_status'] === 'Cancelled') {
                                                // Red badge for cancelled orders
                                                echo '<span class="badge bg-danger">Cancelled</span>';
                                            } else {
                                                // Original logic for other statuses
                                                echo '<span class="badge ' . (($order['payment_status'] ?? '') === 'paid' ? 'bg-success' : 'bg-warning') . '">';
                                                echo ($order['payment_status'] ?? '') === 'paid' ? 'completed' : htmlspecialchars($order['order_status']);
                                                echo '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($order['created_at']))); ?></td>
                                        <td class="actions">
                                            <a href="view_order_details.php?order_id=<?php echo urlencode($order['order_id']); ?>"
                                               class="btn btn-primary btn-sm">View Details</a>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
               
                <!-- View All Orders Link -->
                <div class="text-right mt-3">
                    <a href="admindashboard.php?view=orders" class="btn btn-primary">
                        View All Orders
                    </a>
                </div>
            </div>

            <!-- Additional styles for the dashboard -->
            <style>
                .section {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-top: 30px;
                }

                .section h2 {
                    margin-bottom: 20px;
                    color: #333;
                }

                .text-right {
                    text-align: right;
                }

                .mt-3 {
                    margin-top: 15px;
                }

                .btn-primary {
                    background-color: #007bff;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 4px;
                    text-decoration: none;
                    display: inline-block;
                }

                .btn-primary:hover {
                    background-color: #0056b3;
                }
            </style>
        <?php endif; ?>

        <?php
        // Determine the display order based on the view parameter
        $sections = array();
       
        // User Management Section
        ob_start();
        ?>
        <div id="users-section" class="section <?php echo ($view != 'users' && $view != 'dashboard') ? 'section-hidden' : ''; ?>">
            <div class="section-header">
                <h2 style="color:white;">&nbsp;Manage Users</h2>
                <a href="export.php?type=users" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Download Users Report
                </a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Reset the result pointer to beginning
                    mysqli_data_seek($usersResult, 0);
                    while ($user = mysqli_fetch_assoc($usersResult)) {
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phoneno']); ?></td>
                            <td class="status-<?php echo strtolower($user['verification_status']); ?>">
                                <?php echo $user['verification_status'] === 'active' ? 'Active' : 'Disabled'; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td class="actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $user['Signup_id']; ?>">
                                    <?php if ($user['verification_status'] === 'disabled') { ?>
                                        <button type="submit" name="action" value="activate_user" class="btn btn-activate">Activate</button>
                                    <?php } else { ?>
                                        <button type="submit" name="action" value="deactivate_user" class="btn btn-deactivate">Deactivate</button>
                                    <?php } ?>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
        $userSection = ob_get_clean();
       
        // Seller Management Section
        ob_start();
        ?>
        <div id="sellers-section" class="section <?php echo ($view != 'sellers' && $view != 'dashboard') ? 'section-hidden' : ''; ?>">
            <div class="section-header">
                <h2 style="color:white;">&nbsp;Manage Sellers</h2>
                <a href="export.php?type=sellers" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Download Sellers Report
                </a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Seller Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Reset the result pointer to beginning
                    mysqli_data_seek($sellersResult, 0);
                    while ($seller = mysqli_fetch_assoc($sellersResult)) {
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($seller['Sellername']); ?></td>
                            <td><?php echo htmlspecialchars($seller['email']); ?></td>
                            <td><?php echo htmlspecialchars($seller['phoneno']); ?></td>
                            <td class="status-<?php echo strtolower($seller['verification_status']); ?>">
                                <?php echo $seller['verification_status'] === 'active' ? 'Active' : 'Disabled'; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($seller['created_at'])); ?></td>
                            <td class="actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $seller['seller_id']; ?>">
                                    <?php if ($seller['verification_status'] === 'disabled') { ?>
                                        <button type="submit" name="action" value="activate_seller" class="btn btn-activate">Activate</button>
                                    <?php } else { ?>
                                        <button type="submit" name="action" value="deactivate_seller" class="btn btn-deactivate">Deactivate</button>
                                    <?php } ?>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
        $sellerSection = ob_get_clean();
       
        // Display the sections in the appropriate order based on view
        if ($view == 'orders') {
            echo $sellerSection;
            echo $userSection;
        } elseif ($view == 'users') {
            echo $userSection;
            echo $sellerSection;
        } else {
            // Default dashboard view
            echo $userSection;
            echo $sellerSection;
        }
        ?>

        <?php if ($view == 'orders'): ?>
            <div class="section">
<<<<<<< HEAD
                <div class="section-header">
                    <h2>&nbsp;View Orders</h2>
                    <a href="export.php?type=orders" class="btn btn-success">
                        <i class="fas fa-file-excel"></i>Download Orders Report
                    </a>
                </div>
=======
                <h2>View Orders</h2>
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Customer Details</th>
                                <th>Shipping Address</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
<<<<<<< HEAD
                            <?php
                            // Corrected query to use payment_table for payment_status
                            $orders_query = "SELECT DISTINCT
=======
                            <?php 
                            // Corrected query to use payment_table for payment_status
                            $orders_query = "SELECT DISTINCT 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                o.*,
                                s.username as customer_name,
                                s.email as customer_email,
                                p.name as product_name,
                                pt.payment_status
                                FROM orders_table o
                                LEFT JOIN tbl_signup s ON o.Signup_id = s.Signup_id
                                LEFT JOIN tbl_product p ON o.product_id = p.product_id
                                LEFT JOIN payment_table pt ON o.payment_id = pt.payment_id
                                ORDER BY o.created_at DESC";
<<<<<<< HEAD
                           
                            $orders_result = mysqli_query($conn, $orders_query);
                           
=======
                            
                            $orders_result = mysqli_query($conn, $orders_query);
                            
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                            if (!$orders_result) {
                                echo "<tr><td colspan='9' class='text-center'>Error fetching orders: " . mysqli_error($conn) . "</td></tr>";
                            } elseif (mysqli_num_rows($orders_result) == 0) {
                                echo "<tr><td colspan='9' class='text-center'>No orders found</td></tr>";
                            } else {
<<<<<<< HEAD
                                while ($order = mysqli_fetch_assoc($orders_result)):
=======
                                while ($order = mysqli_fetch_assoc($orders_result)): 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td>
<<<<<<< HEAD
                                            <?php
=======
                                            <?php 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                            $address = nl2br(htmlspecialchars($order['shipping_address']));
                                            echo $address;
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                        <td>₹<?php echo htmlspecialchars($order['total_amount']); ?></td>
                                        <td>
<<<<<<< HEAD
                                            <?php
=======
                                            <?php 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                            if ($order['order_status'] === 'Cancelled') {
                                                // Red badge for cancelled orders
                                                echo '<span class="badge bg-danger">Cancelled</span>';
                                            } else {
                                                // Original logic for other statuses
                                                echo '<span class="badge ' . (($order['payment_status'] ?? '') === 'paid' ? 'bg-success' : 'bg-warning') . '">';
                                                echo ($order['payment_status'] ?? '') === 'paid' ? 'completed' : htmlspecialchars($order['order_status']);
                                                echo '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($order['created_at']))); ?></td>
                                        <td class="actions">
<<<<<<< HEAD
                                            <a href="view_order_details.php?order_id=<?php echo urlencode($order['order_id']); ?>"
=======
                                            <a href="view_order_details.php?order_id=<?php echo urlencode($order['order_id']); ?>" 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                               class="btn btn-primary btn-sm">View Details</a>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($view == 'verify-sellers'): ?>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Seller Management</h1>
<<<<<<< HEAD
               
=======
                
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                <!-- Pending Verification Requests -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Pending Verification Requests</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Seller Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Documents</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
<<<<<<< HEAD
                                <?php if ($pending_verification_result->num_rows > 0): ?>
                                    <?php while ($seller = $pending_verification_result->fetch_assoc()): ?>
                                        <tr data-seller-id="<?php echo $seller['seller_id']; ?>">
                                            <td><?php echo htmlspecialchars($seller['Sellername']); ?></td>
                                            <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                            <td><?php echo htmlspecialchars($seller['phoneno']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="toggleDocuments(<?php echo $seller['seller_id']; ?>)">
                                                    <i class="fas fa-eye"></i> View Documents
                                                </button>
                                                <div id="documents-<?php echo $seller['seller_id']; ?>" style="display: none; margin-top: 10px;">
                                                    <?php if ($seller['id_proof_front']): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['id_proof_front']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-id-card"></i> ID Proof Front
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($seller['id_proof_back']): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['id_proof_back']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-id-card"></i> ID Proof Back
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($seller['business_proof']): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['business_proof']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-file-alt"></i> Business Proof
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($seller['address_proof']): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['address_proof']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-map-marker-alt"></i> Address Proof
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="verifySeller(<?php echo $seller['seller_id']; ?>, 'verified')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="verifySeller(<?php echo $seller['seller_id']; ?>, 'rejected')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
=======
                                <?php
                                $pending_query = "SELECT s.*, sg.username, sg.email, sg.phoneno 
                                                FROM tbl_seller s 
                                                JOIN tbl_signup sg ON s.Signup_id = sg.Signup_id 
                                                WHERE s.verification_status = 'pending'";
                                $pending_result = $conn->query($pending_query);
                                
                                if ($pending_result->num_rows > 0):
                                    while($seller = $pending_result->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($seller['username']); ?></td>
                                        <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                        <td><?php echo htmlspecialchars($seller['phoneno']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($seller['document_path']); ?>" 
                                               target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-file-alt"></i> View Documents
                                            </a>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="verifySeller(<?php echo $seller['seller_id']; ?>, 'verified')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="verifySeller(<?php echo $seller['seller_id']; ?>, 'rejected')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                    <tr>
                                        <td colspan="5" class="text-center">No pending verification requests</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Verified Sellers -->
                <div class="card">
                    <div class="card-header">
                        <h4>Verified Sellers</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th>Seller Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Total Products</th>
                                    <th>Total Orders</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Documents</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $verified_query = "
<<<<<<< HEAD
                                    SELECT
                                        s.seller_id,
                                        s.Sellername,
                                        s.verified_status,
                                        s.created_at,
                                        sg.username,
                                        sg.email,
=======
                                    SELECT 
                                        s.seller_id,
                                        s.Sellername,
                                        s.verification_status,
                                        s.created_at,
                                        sg.username, 
                                        sg.email, 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                        sg.phoneno,
                                        svd.id_proof_front,
                                        svd.id_proof_back,
                                        svd.business_proof,
                                        svd.address_proof,
                                        (SELECT COUNT(*) FROM tbl_product WHERE seller_id = s.seller_id) as total_products,
<<<<<<< HEAD
                                        (SELECT COUNT(*) FROM orders_table o
                                         JOIN tbl_product p ON o.product_id = p.product_id
                                         WHERE p.seller_id = s.seller_id) as total_orders
                                    FROM tbl_seller s
                                    JOIN tbl_signup sg ON s.Signup_id = sg.Signup_id
                                    LEFT JOIN seller_verification_docs svd ON s.seller_id = svd.seller_id
                                    ORDER BY s.created_at DESC";
                               
                                $verified_result = $conn->query($verified_query);
                               
                                if ($verified_result && $verified_result->num_rows > 0):
                                    while($seller = $verified_result->fetch_assoc()):
=======
                                        (SELECT COUNT(*) FROM orders_table o 
                                         JOIN tbl_product p ON o.product_id = p.product_id 
                                         WHERE p.seller_id = s.seller_id) as total_orders
                                    FROM tbl_seller s 
                                    JOIN tbl_signup sg ON s.Signup_id = sg.Signup_id 
                                    LEFT JOIN seller_verification_docs svd ON s.seller_id = svd.seller_id
                                    WHERE s.verification_status = 'active'
                                    ORDER BY s.created_at DESC";
                                
                                $verified_result = $conn->query($verified_query);
                                
                                if ($verified_result && $verified_result->num_rows > 0):
                                    while($seller = $verified_result->fetch_assoc()):
                                        // Check if any documents exist
                                        $hasDocuments = !empty($seller['id_proof_front']) || 
                                                      !empty($seller['id_proof_back']) || 
                                                      !empty($seller['business_proof']) || 
                                                      !empty($seller['address_proof']);
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($seller['username']); ?></td>
                                        <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                        <td><?php echo htmlspecialchars($seller['phoneno']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $seller['total_products']; ?> Products
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo $seller['total_orders']; ?> Orders
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($seller['created_at'])); ?></td>
                                        <td>
<<<<<<< HEAD
                                            <?php
                                            switch($seller['verified_status']) {
                                                case 'verified':
                                                    echo '<span class="badge bg-success">Active</span>';
                                                    break;
                                                case 'pending':
                                                    echo '<span class="badge bg-warning">Inactive</span>';
                                                    break;
                                                case 'rejected':
                                                    echo '<span class="badge bg-danger">Rejected</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">Unknown</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button"
                                                    class="btn btn-info btn-sm view-details"
                                                    onclick="toggleDocuments(<?php echo $seller['seller_id']; ?>)">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php
=======
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-info btn-sm view-details" 
                                                    onclick="toggleDocuments(<?php echo $seller['seller_id']; ?>)">
                                                View Details
                                            </button>
                                            
                                            <div id="documents-<?php echo $seller['seller_id']; ?>" class="document-section" style="display: none;">
                                                <h6 class="mt-3">Seller Documents</h6>
                                                <div class="document-links">
                                                    <?php if (!empty($seller['id_proof_front'])): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['id_proof_front']); ?>" 
                                                           target="_blank" 
                                                           class="doc-link">
                                                            ID Proof Front
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (!empty($seller['id_proof_back'])): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['id_proof_back']); ?>" 
                                                           target="_blank" 
                                                           class="doc-link">
                                                            ID Proof Back
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (!empty($seller['business_proof'])): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['business_proof']); ?>" 
                                                           target="_blank" 
                                                           class="doc-link">
                                                            Business Proof
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if (!empty($seller['address_proof'])): ?>
                                                        <a href="<?php echo htmlspecialchars($seller['address_proof']); ?>" 
                                                           target="_blank" 
                                                           class="doc-link">
                                                            Address Proof
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" 
                                                            class="btn btn-secondary btn-sm mt-2" 
                                                            onclick="toggleDocuments(<?php echo $seller['seller_id']; ?>)">
                                                        Close
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                    endwhile;
                                else:
                                ?>
                                    <tr>
<<<<<<< HEAD
                                        <td colspan="8" class="text-center">No sellers found</td>
=======
                                        <td colspan="8" class="text-center">No verified sellers found</td>
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                function verifySeller(sellerId, status) {
                    if (confirm('Are you sure you want to ' + status + ' this seller?')) {
<<<<<<< HEAD
                        // Show loading indicator
                        const row = document.querySelector(`tr[data-seller-id="${sellerId}"]`);
                        if (row) {
                            row.style.opacity = '0.5';
                        }

=======
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                        fetch('verify_seller.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
<<<<<<< HEAD
                                'Accept': 'application/json'
=======
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                            },
                            body: JSON.stringify({
                                seller_id: sellerId,
                                status: status
                            })
                        })
<<<<<<< HEAD
                        .then(response => {
                            // Check if response is JSON
                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                // If not JSON, get the text and throw it as an error
                                return response.text().then(text => {
                                    throw new Error('Server returned non-JSON response: ' + text);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Remove the row from the pending verification table
                                const row = document.querySelector(`tr[data-seller-id="${sellerId}"]`);
                                if (row) {
                                    row.remove();
                                }
                               
                                // Show success message
                                const alertDiv = document.createElement('div');
                                alertDiv.className = 'alert alert-success';
                                alertDiv.textContent = `Seller ${status} successfully`;
                                document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                               
                                // Remove alert after 3 seconds
                                setTimeout(() => alertDiv.remove(), 3000);
                               
                                // If no more pending requests, show "No pending verification requests"
                                const tbody = document.querySelector('.table tbody');
                                if (tbody && !tbody.querySelector('tr')) {
                                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No pending verification requests</td></tr>';
                                }
                            } else {
                                throw new Error(data.message || 'Failed to update seller status');
=======
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
<<<<<<< HEAD
                            // Show error message to user
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-danger';
                            alertDiv.textContent = 'Error processing request: ' + error.message;
                            document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                           
                            // Remove alert after 5 seconds
                            setTimeout(() => alertDiv.remove(), 5000);
                           
                            // Reset row opacity
                            if (row) {
                                row.style.opacity = '1';
                            }
=======
                            alert('An error occurred while processing your request.');
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
                        });
                    }
                }

                function toggleDocuments(sellerId) {
<<<<<<< HEAD
                    // Get the modal
                    const modal = document.getElementById('documentModal');
                   
                    // If the modal is already visible, hide it
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                        return;
                    }

                    // Fetch seller details and documents
                    fetch(`get_seller_documents.php?seller_id=${sellerId}`)
                        .then(response => response.json())
                        .then(data => {
                            // Update seller details
                            document.getElementById('sellerName').textContent = data.seller.username || data.seller.Sellername;
                            document.getElementById('sellerEmail').textContent = data.seller.email;
                            document.getElementById('sellerPhone').textContent = data.seller.phoneno;

                            // Update document images
                            document.getElementById('idProofFront').src = data.documents.id_proof_front || 'placeholder.png';
                            document.getElementById('idProofBack').src = data.documents.id_proof_back || 'placeholder.png';
                            document.getElementById('businessProof').src = data.documents.business_proof || 'placeholder.png';
                            document.getElementById('addressProof').src = data.documents.address_proof || 'placeholder.png';

                            // Show the modal
                            modal.style.display = 'block';
                        })
                        .catch(error => {
                            console.error('Error fetching seller documents:', error);
                            alert('Error loading seller documents');
                        });
                }

                // Close modal when clicking the close button or outside the modal
                document.addEventListener('DOMContentLoaded', function() {
                    const modal = document.getElementById('documentModal');
                    const closeButtons = document.getElementsByClassName('close-modal');

                    // Close modal when clicking close button
                    Array.from(closeButtons).forEach(button => {
                        button.onclick = function() {
                            modal.style.display = 'none';
                        }
                    });

                    // Close modal when clicking outside
                    window.onclick = function(event) {
                        if (event.target === modal) {
                            modal.style.display = 'none';
                        }
                    }
                });
=======
                    const docSection = document.getElementById(`documents-${sellerId}`);
                    if (docSection.style.display === 'none') {
                        docSection.style.display = 'block';
                    } else {
                        docSection.style.display = 'none';
                    }
                }
>>>>>>> 9f0a29f027f586f039655aa259fce1bf1090d34e
            </script>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.getElementsByClassName('alert');
                for(let alert of alerts) {
                    alert.style.display = 'none';
                }
            }, 5000);
        });
    </script>

    <!-- Add this modal HTML structure just before the closing </body> tag -->
    <div id="documentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Seller Documents</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="seller-info">
                    <h3>Seller Details</h3>
                    <p><strong>Name:</strong> <span id="sellerName"></span></p>
                    <p><strong>Email:</strong> <span id="sellerEmail"></span></p>
                    <p><strong>Phone:</strong> <span id="sellerPhone"></span></p>
                </div>
                <div class="document-grid">
                    <div class="document-item">
                        <h4>ID Proof (Front)</h4>
                        <img id="idProofFront" src="" alt="ID Proof Front">
                    </div>
                    <div class="document-item">
                        <h4>ID Proof (Back)</h4>
                        <img id="idProofBack" src="" alt="ID Proof Back">
                    </div>
                    <div class="document-item">
                        <h4>Business Proof</h4>
                        <img id="businessProof" src="" alt="Business Proof">
                    </div>
                    <div class="document-item">
                        <h4>Address Proof</h4>
                        <img id="addressProof" src="" alt="Address Proof">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary close-modal">Close</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificationBell = document.querySelector('.notification-bell');
        const notificationDropdown = document.querySelector('.notification-dropdown');
        
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.style.display = notificationDropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationBell.contains(e.target)) {
                notificationDropdown.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>