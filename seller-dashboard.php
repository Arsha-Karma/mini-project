<?php
// Keep all PHP initialization code at the top
session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Include database connection
require_once 'dbconnect.php';

// Add necessary columns if they don't exist
$alter_queries = [
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS quantity INT NOT NULL DEFAULT 1",
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS product_id INT(11) NOT NULL",
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS user_id INT(11) NOT NULL",
    "ALTER TABLE orders_table ADD FOREIGN KEY IF NOT EXISTS (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE",
    "ALTER TABLE orders_table ADD FOREIGN KEY IF NOT EXISTS (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE",
    "ALTER TABLE orders_table ADD COLUMN IF NOT EXISTS is_notified TINYINT DEFAULT 0",
    "ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS is_stock_notified TINYINT DEFAULT 0"
];

foreach ($alter_queries as $query) {
    try {
        $conn->query($query);
    } catch (Exception $e) {
        // Continue if error occurs (e.g., if column or key already exists)
        continue;
    }
}

// Check if seller is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

// Check for session timeout (e.g., 30 minutes)
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Log out the user
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// At the top of your file, after session_start()
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get seller ID from the database
$stmt = $conn->prepare("SELECT seller_id FROM tbl_seller WHERE Signup_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$seller_data = $result->fetch_assoc();
$seller_id = $seller_data ? $seller_data['seller_id'] : null;

if (!$seller_id) {
    // Handle case where seller ID is not found
    $_SESSION['error'] = "Seller account not found. Please contact support.";
    header('Location: error.php');
    exit();
}

// Get seller information including verification status
$seller_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT s.*, v.*, s.verified_status
    FROM tbl_seller s
    LEFT JOIN seller_verification_docs v ON s.seller_id = v.seller_id
    WHERE s.Signup_id = ?
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_info = $stmt->get_result()->fetch_assoc();

// Get verification status
$is_verified = ($seller_info['verified_status'] === 'verified');
$verification_status = $seller_info['verified_status'];

// Get seller's products only if verified
if ($is_verified) {
    $stmt = $conn->prepare("
        SELECT * FROM tbl_product
        WHERE seller_id = (
            SELECT seller_id FROM tbl_seller WHERE Signup_id = ?
        ) AND deleted = 0
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $products = [];
}

// Get seller's orders
$recent_orders_query = "
    SELECT 
        o.order_id, 
        s.username as customer_name, 
        p.name as product_name,
        p.image_path as product_image,
        o.quantity, 
        pt.amount as total_amount, 
        o.created_at, 
        o.gift_option,
        o.gift_wrap_type,
        o.payment_status,
        o.order_status
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    JOIN payment_table pt ON o.order_id = pt.order_id
    WHERE p.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
";

$stmt = $conn->prepare($recent_orders_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug information
error_log("User ID: " . $_SESSION['user_id']);
error_log("Products found: " . count($products));
error_log("Sales found: " . count($sales));

if (empty($products)) {
    error_log("No products found for seller_id: " . $seller_info['seller_id']);
}

if (empty($sales)) {
    error_log("No sales found for seller_id: " . $seller_info['seller_id']);
}

// Get recent reviews
$stmt = $conn->prepare("
    SELECT r.*, p.name as product_name, s.username
    FROM tbl_reviews r
    JOIN tbl_product p ON r.product_id = p.product_id
    JOIN tbl_signup s ON r.user_id = s.Signup_id
    WHERE p.seller_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check verification status
$stmt = $conn->prepare("
    SELECT verified_status, documents_uploaded
    FROM tbl_seller
    WHERE Signup_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// Check for verification success message
$show_success = isset($_SESSION['verification_success']) && $_SESSION['verification_success'];
if ($show_success) {
    unset($_SESSION['verification_success']); // Clear the flag after use
}

// Show verification form for unverified sellers
$show_verification_popup = ($result['verified_status'] === 'pending' || $result['documents_uploaded'] !== 'completed');

// If seller is not verified and hasn't uploaded documents, redirect to verification form
if ($show_verification_popup) {
    // Only show the verification form, hide other dashboard content
    $show_dashboard_content = false;
} else {
    $show_dashboard_content = true;
}

// Fetch notifications for new orders
$new_orders_query = "
    SELECT COUNT(*) as new_orders
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    WHERE p.seller_id = ? AND o.is_notified = 0";
$stmt = $conn->prepare($new_orders_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$new_orders_result = $stmt->get_result();
$new_orders_count = $new_orders_result->fetch_assoc()['new_orders'];

// Fetch out of stock products
$out_of_stock_query = "
    SELECT COUNT(*) as out_of_stock
    FROM tbl_product
    WHERE seller_id = ? AND Stock_quantity <= 5 AND is_stock_notified = 0";
$stmt = $conn->prepare($out_of_stock_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$out_of_stock_result = $stmt->get_result();
$out_of_stock_count = $out_of_stock_result->fetch_assoc()['out_of_stock'];

$total_notifications = $new_orders_count + $out_of_stock_count;

// Fetch recent orders details with more specific information
$recent_orders_query = "
    SELECT 
        o.order_id, 
        s.username as customer_name, 
        p.name as product_name,
        p.image_path as product_image,
        o.quantity, 
        pt.amount as total_amount, 
        o.created_at, 
        o.gift_option,
        o.gift_wrap_type,
        o.payment_status,
        o.order_status
    FROM orders_table o
    JOIN tbl_product p ON o.product_id = p.product_id
    JOIN tbl_signup s ON o.Signup_id = s.Signup_id
    JOIN payment_table pt ON o.order_id = pt.order_id
    WHERE p.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($recent_orders_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch low stock products
$low_stock_query = "
    SELECT
        product_id,
        name,
        Stock_quantity,
        price
    FROM tbl_product
    WHERE seller_id = ?
    AND Stock_quantity <= 5
    ORDER BY Stock_quantity ASC";

$stmt = $conn->prepare($low_stock_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$low_stock_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get seller's order statistics
$order_stats_query = "
    SELECT 
        COUNT(DISTINCT o.order_id) as total_orders,
        COALESCE(SUM(
            CASE 
                WHEN o.order_status = 'Completed' 
                AND o.payment_status = 'paid'
                AND DATE_FORMAT(o.created_at, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')
                THEN pt.amount 
                ELSE 0 
            END
        ), 0) as monthly_revenue
    FROM orders_table o
    INNER JOIN tbl_product p ON o.product_id = p.product_id
    LEFT JOIN payment_table pt ON o.order_id = pt.order_id
    WHERE p.seller_id = ?
    AND o.order_status != 'Cancelled'";

$stmt = $conn->prepare($order_stats_query);
$stmt->bind_param("i", $seller_info['seller_id']);
$stmt->execute();
$order_stats = $stmt->get_result()->fetch_assoc();

// Calculate total orders and revenue
$total_orders = $order_stats['total_orders'] ?? 0;
$monthly_revenue = floatval($order_stats['monthly_revenue'] ?? 0);
$seller_profit = $monthly_revenue * 0.7; // 70% of revenue goes to seller

// Debug information
error_log("Total Orders: " . $total_orders);
error_log("Monthly Revenue (Raw): " . $monthly_revenue);
error_log("Seller Profit (Raw): " . $seller_profit);

// Format the numbers for display
$formatted_monthly_revenue = number_format($monthly_revenue, 2);
$formatted_seller_profit = number_format($seller_profit, 2);

// Add this function after the existing PHP code at the top of the file
function sendAdminNotification($sellerName, $sellerId) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'arshaprasobh318@gmail.com'; // Your email
        $mail->Password = 'ilwf fpya pwkx pmat'; // Your email app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
        $mail->addAddress('arshakarma2027@mca.ajce.in', 'Admin'); // Admin email

        $mail->isHTML(true);
        $mail->Subject = 'New Seller Verification Request';
        $mail->Body = "
            <h2>New Seller Verification Request</h2>
            <p>A new seller has submitted verification documents.</p>
            <p><strong>Seller Name:</strong> {$sellerName}</p>
            <p><strong>Seller ID:</strong> {$sellerId}</p>
            <p>Please review the verification documents in the admin dashboard.</p>
            <p><a href='http://localhost/your-project-path/admindashboard.php?view=verify-sellers'>Click here to review</a></p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Add these at the top of the file with other includes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Modify the form submission handling code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_verification') {
    try {
        // ... existing verification document upload code ...

        // After successfully saving the documents, send email to admin
        $mail = new PHPMailer(true);
       
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'arshaprasobh318@gmail.com';
        $mail->Password = 'ilwf fpya pwkx pmat';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
        $mail->addAddress('arshakarma2027@mca.ajce.in', 'Admin');

        $mail->isHTML(true);
        $mail->Subject = 'New Seller Verification Request';
       
        // Get seller details for the email
        $sellerName = $_SESSION['username'];
        $sellerId = $seller_info['seller_id'];
       
        $mail->Body = "
            <h2>New Seller Verification Request</h2>
            <p>A new seller has submitted verification documents.</p>
            <p><strong>Seller Name:</strong> {$sellerName}</p>
            <p><strong>Seller ID:</strong> {$sellerId}</p>
            <p>Please review the verification documents in the admin dashboard.</p>
            <p><a href='http://localhost/your-project-path/admindashboard.php?view=verify-sellers'>Click here to review</a></p>
        ";

        $mail->send();
       
        // Set success message and redirect
        $_SESSION['verification_success'] = true;
       
        // Return success response for AJAX
        echo json_encode(['success' => true, 'message' => 'Verification documents submitted successfully']);
        exit();

    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        echo json_encode(['success' => false, 'message' => 'Error sending notification: ' . $e->getMessage()]);
        exit();
    }
}

// Update this query to directly fetch amounts from payment_table with no status filtering
$admin_profits_query = "SELECT 
    SUM(pt.amount) as total_payments,
    SUM(CASE WHEN DATE_FORMAT(pt.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') 
        THEN pt.amount ELSE 0 END) as current_month_payments
    FROM payment_table pt";

$profits_result = mysqli_query($conn, $admin_profits_query);
$profits_data = mysqli_fetch_assoc($profits_result);

// Raw payment amounts
$total_payments = $profits_data['total_payments'] ?? 0;
$current_month_payments = $profits_data['current_month_payments'] ?? 0;

// Calculate admin profit (30% of amounts)
$total_admin_profit = $total_payments * 0.30;
$current_month_profit = $current_month_payments * 0.30;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Perfume Paradise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f4f6f9;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .verification-banner {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .verification-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .verification-verified {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .verification-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .info-item label {
            display: block;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .info-item span {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-info img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-edit {
            background: #ffc107;
            color: #000;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .status-active {
            padding: 4px 8px;
            background: #28a745;
            color: white;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-pending {
            padding: 4px 8px;
            background: #ffc107;
            color: #000;
            border-radius: 4px;
            font-size: 12px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #999;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .low-stock {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 5px;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .table {
                display: block;
                overflow-x: auto;
            }

            .section-header {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Add sidebar styles */
        .sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1a1a1a;
            padding-top: 20px;
            color: white;
        }

        .sidebar h2 {
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            border-bottom: 1px solid #333;
        }

        .sidebar a {
            padding: 15px 25px;
            text-decoration: none;
            font-size: 16px;
            color: #fff;
            display: block;
            transition: 0.3s;
        }

        .sidebar a:hover {
            background-color: #333;
            color: #fff;
        }

        .sidebar a.active {
            background-color: #333;
            border-left: 4px solid #fff;
        }

        .sidebar i {
            margin-right: 10px;
        }

        /* Adjust main content to accommodate sidebar */
        .container {
            margin-left: 250px; /* Same as sidebar width */
            max-width: calc(100% - 250px);
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
           
            .container {
                margin-left: 0;
                max-width: 100%;
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

        /* Add these styles for the status badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.processing {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-badge.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-info img {
            border-radius: 4px;
        }

        .table td {
            vertical-align: middle;
        }

        .welcome-section {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-section h1 {
            color: #333;
            font-size: 28px;
            margin: 0;
            font-weight: 600;
        }

        .notification-area {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .notification-icon {
            background: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            min-width: 18px;
            text-align: center;
            display: none; /* Hidden by default */
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }

        .notification-section {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .notification-section h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }

        .notification-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification-item {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
        }

        .notification-item.order {
            border-left: 4px solid #007bff;
        }

        .notification-item.stock {
            border-left: 4px solid #dc3545;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .notification-details {
            font-size: 12px;
            color: #666;
        }

        .notification-details p {
            margin: 3px 0;
        }

        .notification-details small {
            color: #888;
            font-style: italic;
        }

        .notification-item.stock {
            border-left: 4px solid #dc3545;
        }

        .out-of-stock {
            color: #dc3545;
            font-weight: bold;
            margin-top: 5px;
        }

        .low-stock-warning {
            color: #ffc107;
            font-weight: bold;
            margin-top: 5px;
        }

        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast-notification {
            background: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .toast-notification i {
            font-size: 18px;
        }

        .order-toast {
            border-left: 4px solid #007bff;
        }

        .order-toast i {
            color: #007bff;
        }

        .stock-toast {
            border-left: 4px solid #dc3545;
        }

        .stock-toast i {
            color: #dc3545;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Add these styles for the verification popup */
        .verification-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .popup-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .popup-content h2 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .verification-notice {
            color: #666;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group select,
        .form-group input[type="text"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group input[type="file"] {
            padding: 8px;
        }

        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-group.error input,
        .form-group.error select {
            border-color: #dc3545;
        }

        .terms-group {
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terms-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .terms-group label {
            font-size: 14px;
            color: #666;
        }

        .button-group {
            text-align: center;
            margin-top: 30px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .button-group button {
            padding: 12px 30px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .loading::after {
            content: '';
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .verification-banner {
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: left;
            font-size: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .verification-banner i {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .verification-banner h4 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .verification-banner p {
            margin: 0;
        }

        .verification-verified {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .verification-verified i {
            color: #28a745;
        }

        .verification-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .verification-pending i {
            color: #ffc107;
        }

        .verification-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .verification-rejected i {
            color: #dc3545;
        }

        .badge.bg-info {
            background-color: #17a2b8 !important;
            color: white;
        }

        .badge.bg-secondary {
            background-color: #6c757d !important;
            color: white;
        }

        .badge i {
            margin-right: 4px;
        }

        .gift-wrap-info {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .table th {
            white-space: nowrap;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-5px);
        }

        .stat-box i {
            font-size: 24px;
            color: #007bff;
            margin-bottom: 10px;
        }

        .stat-box h3 {
            color: #666;
            font-size: 16px;
            margin: 10px 0;
        }

        .stat-box .number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }

        .stat-box small {
            color: #666;
            font-size: 12px;
            display: block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h3>&nbsp;Perfume Paradise</h3>
        <a href="seller-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'seller-dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Home
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-edit"></i> Edit Profile
        </a>
        <a href="products.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="sales.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Orders
        </a>
        <a href="customer_reviews.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'customer-reviews.php' ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> Customer Reviews
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Message -->
        <div class="welcome-section">
            <h1>Welcome <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        </div>

        <?php if ($verification_status === 'verified'): ?>
            <div class="verification-banner verification-verified">
                <i class="fas fa-check-circle"></i>
                <h4>Verified Seller</h4>
                <p>Congratulations! You are now a verified seller on Perfume Paradise. You can add and manage your products.</p>
            </div>
        <?php elseif ($verification_status === 'pending'): ?>
            <div class="verification-banner verification-pending">
                <i class="fas fa-clock"></i>
                <h4>Verification Pending</h4>
                <p>Your seller verification is currently pending admin approval. You can view your dashboard but cannot add or manage products until your verification is approved.</p>
                <p>Please check back later for updates on your verification status.</p>
            </div>
        <?php elseif ($verification_status === 'rejected'): ?>
            <div class="verification-banner verification-rejected">
                <i class="fas fa-times-circle"></i>
                <h4>Verification Rejected</h4>
                <p>Your seller verification has been rejected. You cannot add or manage products.</p>
                <p>Please contact support for more information.</p>
            </div>
        <?php endif; ?>

        <!-- Notification Area -->
        <div class="notification-area">
            <div class="notification-icon" onclick="toggleNotifications(event)">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationCount">0</span>
            </div>
           
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                </div>
                <div class="notification-content">
                    <div class="notification-section" id="orderNotifications">
                        <h4>Recent Orders</h4>
                        <div class="notification-items" id="recentOrdersList">
                            <!-- Orders will be populated here -->
                        </div>
                    </div>
                    <div class="notification-section" id="stockNotifications">
                        <h4>Low Stock Products</h4>
                        <div class="notification-items" id="lowStockList">
                            <!-- Low stock items will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($show_dashboard_content): ?>
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-box">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Total Orders</h3>
                    <div class="number"><?php echo $total_orders; ?></div>
                </div>
               
                <div class="stat-box">
                    <i class="fas fa-chart-line"></i>
                    <h3>Monthly Revenue</h3>
                    <div class="number">₹<?php echo $formatted_monthly_revenue; ?></div>
                    <small>Total earnings this month</small>
                </div>
               
                <div class="stat-box">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Your Profit (70%)</h3>
                    <div class="number">₹<?php echo $formatted_seller_profit; ?></div>
                    <small>After admin commission</small>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="section">
                <h2><i class="fas fa-shopping-cart"></i> Recent Orders</h2>
                <?php if (empty($sales)): ?>
                    <div class="no-data">
                        <i class="fas fa-shopping-cart"></i>
                        <p>No orders yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer Name</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Total Amount</th>
                                    <th>Order Date</th>
                                    <th>Gift Status</th>
                                    <th>Payment Status</th>
                                    <th>Order Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>
                                            <div class="product-info">
                                                <img src="<?php echo htmlspecialchars($order['product_image']); ?>" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">
                                                <span><?php echo htmlspecialchars($order['product_name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $order['quantity']; ?></td>
                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <?php if ($order['gift_option']): ?>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-gift"></i>
                                                    <?php echo htmlspecialchars($order['gift_wrap_type']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Gift Wrap</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo htmlspecialchars($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($order['order_status'] === 'Cancelled'): ?>
                                                <span class="badge bg-danger">Cancelled</span>
                                            <?php elseif ($order['order_status'] === 'Completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($order['order_status'] === 'Processing'): ?>
                                                <span class="badge bg-warning">Processing</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Products Section -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-box"></i> Your Products</h2>
                </div>

                <?php if ($verification_status === 'pending'): ?>
                    <div class="no-data">
                        <i class="fas fa-box-open"></i>
                        <p>No products added yet. You can add products once your verification is approved.</p>
                    </div>
                <?php elseif ($verification_status === 'rejected'): ?>
                    <div class="no-data">
                        <i class="fas fa-box-open"></i>
                        <p>No products available. Verification status: Rejected</p>
                        </div>
                    <?php else: ?>
                    <?php if (empty($products)): ?>
                        <div class="no-data">
                            <i class="fas fa-box-open"></i>
                            <p>No products added yet</p>
                                                </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <?php if (!empty($product['image_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>"
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($product['name']); ?></span>
                                                </div>
                                            </td>
                                            <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($product['Stock_quantity']); ?>
                                                <?php if ($product['Stock_quantity'] <= 5): ?>
                                                    <span class="low-stock">Low Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($show_verification_popup): ?>
        <div id="verificationPopup" class="verification-popup" style="display: flex;">
            <div class="popup-content">
                <h2>Complete Your Seller Verification</h2>
                <p class="verification-notice">Please complete your verification to access the seller dashboard.</p>
                <form id="verificationForm" novalidate>
                    <div class="form-group">
                        <label for="id_type">ID Type*</label>
                        <select name="id_type" id="id_type" required>
                            <option value="">Select ID Type</option>
                            <option value="aadhar">Aadhar Card</option>
                            <option value="pan">PAN Card</option>
                            <option value="voter">Voter ID</option>
                            <option value="driving">Driving License</option>
                        </select>
                        <div class="error-message">Please select an ID type</div>
                    </div>

                    <div class="form-group">
                        <label for="id_number">ID Number*</label>
                        <input type="text" id="id_number" name="id_number" required>
                        <div class="error-message">Please enter a valid ID number</div>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Front)*</label>
                        <input type="file" name="id_proof_front" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload front ID proof</div>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Back)*</label>
                        <input type="file" name="id_proof_back" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload back ID proof</div>
                    </div>

                    <div class="form-group">
                        <label>Business License/Registration*</label>
                        <input type="file" name="business_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload business proof</div>
                    </div>

                    <div class="form-group">
                        <label>Address Proof*</label>
                        <input type="file" name="address_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload address proof</div>
                    </div>

                    <div class="terms-group">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the verification terms and conditions*</label>
                        <div class="error-message">Please accept the terms</div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Submit Verification</button>
                        <button type="button" class="btn btn-secondary" onclick="hideVerificationPopup()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="loading" id="loadingIndicator"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let notificationsViewed = false;

    // Replace the existing EventSource-related code with this polling solution
    function initializeEventSource() {
        // Clear any existing intervals
        if (window.notificationInterval) {
            clearInterval(window.notificationInterval);
        }

        // Use regular polling instead of EventSource
        function pollNotifications() {
            fetch('check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        // Reset notification viewed status
                        notificationsViewed = false;
                       
                        // Show notification badge
                        const badge = document.getElementById('notificationCount');
                        badge.style.display = 'block';
                        badge.textContent = data.count;
                       
                        // Update notifications content
                        updateNotificationContent(data);
                       
                        // Show toast for new notifications
                        if (data.orders.length > 0) {
                            showToast({ new_orders: data.orders.length });
                        }
                        if (data.low_stock.length > 0) {
                            showToast({ low_stock: data.low_stock.length });
                        }
                    }
                })
                .catch(error => {
                    console.error('Notification check failed:', error);
                });
        }

        // Start polling every 30 seconds
        window.notificationInterval = setInterval(pollNotifications, 30000);
       
        // Initial check
        pollNotifications();
    }

    // Update the showToast function to handle both types of notifications
    function showToast(data) {
        const toastContainer = document.getElementById('toastContainer');
       
        if (data.new_orders > 0) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification order-toast';
            toast.innerHTML = `
                <i class="fas fa-shopping-cart"></i>
                New order received!
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
       
        if (data.low_stock > 0) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification stock-toast';
            toast.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                Low stock alert!
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
    }

    function toggleNotifications(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        const isVisible = dropdown.style.display === 'block';
       
        dropdown.style.display = isVisible ? 'none' : 'block';
       
        // Clear notification count when opening dropdown
        if (!isVisible && !notificationsViewed) {
            document.getElementById('notificationCount').style.display = 'none';
            notificationsViewed = true;
        }
    }

    function updateNotificationContent(data) {
        // Only update notification count if notifications haven't been viewed
        if (!notificationsViewed) {
            const notificationBadge = document.getElementById('notificationCount');
            notificationBadge.textContent = data.count;
            notificationBadge.style.display = data.count > 0 ? 'block' : 'none';
        }

        // Update Recent Orders
        const ordersList = document.getElementById('recentOrdersList');
        if (data.orders.length > 0) {
            ordersList.innerHTML = data.orders.map(order => {
                const orderDate = new Date(order.created_at);
                const timeAgo = getTimeAgo(orderDate);
               
                return `
                    <div class="notification-item order">
                        <div class="notification-title">
                            New order #${order.order_id}
                        </div>
                        <div class="notification-details">
                            <div class="product-info">
                                <img src="${order.image_path}" alt="${order.product_name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <div>
                                    <p><strong>Product:</strong> ${order.product_name}</p>
                                    <p><strong>Customer:</strong> ${order.customer_name}</p>
                                    <p><strong>Quantity:</strong> ${order.quantity}</p>
                                    <p><strong>Amount:</strong> ₹${order.total_amount}</p>
                                    <p><small>${timeAgo}</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            ordersList.innerHTML = '<div class="notification-item">No new orders in the last 24 hours</div>';
        }

        // Update Low Stock Products
        const stockList = document.getElementById('lowStockList');
        if (data.low_stock.length > 0) {
            stockList.innerHTML = data.low_stock.map(product => `
                <div class="notification-item stock">
                    <div class="notification-title">
                        ${product.Stock_quantity === 0 ? 'Out of Stock' : 'Low Stock Alert'}
                    </div>
                    <div class="notification-details">
                        <div class="product-info">
                            <img src="${product.image_path}" alt="${product.name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <div>
                                <p><strong>Product:</strong> ${product.name}</p>
                                <p><strong>Current Stock:</strong> ${product.Stock_quantity} units</p>
                                <p><strong>Price:</strong> ₹${product.price}</p>
                                ${product.Stock_quantity === 0 ?
                                    '<p class="out-of-stock">Product is out of stock!</p>' :
                                    '<p class="low-stock-warning">Stock running low!</p>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            stockList.innerHTML = '<div class="notification-item">All products are well stocked</div>';
        }
    }

    // Reset notifications viewed status when new notifications arrive
    function resetNotificationsStatus() {
        notificationsViewed = false;
        checkNotifications();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown.style.display === 'block' && !event.target.closest('.notification-area')) {
            dropdown.style.display = 'none';
        }
    });

    // Add this function before it's called
    function checkNotifications() {
        fetch('check_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    // Reset notification viewed status
                    notificationsViewed = false;
                   
                    // Show notification badge
                    const badge = document.getElementById('notificationCount');
                    badge.style.display = 'block';
                    badge.textContent = data.count;
                   
                    // Update notifications content
                    updateNotificationContent(data);
                }
            })
            .catch(error => {
                console.error('Error checking notifications:', error);
            });
    }

    // Then keep your existing code that calls this function
    // Initial check and periodic updates
    checkNotifications();
    setInterval(resetNotificationsStatus, 60000); // Check every minute

    // Helper function to show relative time
    function getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
       
        let interval = Math.floor(seconds / 3600);
        if (interval < 24) {
            if (interval < 1) {
                interval = Math.floor(seconds / 60);
                if (interval < 1) {
                    return 'Just now';
                }
                return `${interval} minute${interval === 1 ? '' : 's'} ago`;
            }
            return `${interval} hour${interval === 1 ? '' : 's'} ago`;
        }
        return date.toLocaleString();
    }

    document.getElementById('verificationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
       
        // Reset previous errors
        document.querySelectorAll('.error-message').forEach(error => error.style.display = 'none');
        document.querySelectorAll('.form-group').forEach(group => group.classList.remove('error'));
       
        // Validate form
        let isValid = true;
        const form = this;
       
        // Check required fields
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value) {
                isValid = false;
                field.closest('.form-group').classList.add('error');
                field.closest('.form-group').querySelector('.error-message').style.display = 'block';
            }
        });

        // Validate ID number format based on selected ID type
        const idType = form.querySelector('#id_type').value;
        const idNumber = form.querySelector('#id_number').value;
        if (idType && idNumber) {
            const patterns = {
                aadhar: /^\d{12}$/,
                pan: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/,
                voter: /^[A-Z]{3}\d{7}$/,
                driving: /^[A-Z]{2}\d{13}$/
            };
           
            if (!patterns[idType]?.test(idNumber)) {
                isValid = false;
                form.querySelector('#id_number').closest('.form-group').classList.add('error');
                form.querySelector('#id_number').closest('.form-group').querySelector('.error-message').style.display = 'block';
            }
        }

        // Validate file sizes and types
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
       
        form.querySelectorAll('input[type="file"]').forEach(input => {
            const file = input.files[0];
            if (file) {
                if (file.size > maxSize) {
                    isValid = false;
                    input.closest('.form-group').classList.add('error');
                    input.closest('.form-group').querySelector('.error-message').textContent = 'File size must be less than 5MB';
                    input.closest('.form-group').querySelector('.error-message').style.display = 'block';
                }
               
                if (!allowedTypes.includes(file.type)) {
                    isValid = false;
                    input.closest('.form-group').classList.add('error');
                    input.closest('.form-group').querySelector('.error-message').textContent = 'Invalid file type. Allowed types: JPG, PNG, PDF';
                    input.closest('.form-group').querySelector('.error-message').style.display = 'block';
                }
            }
        });

        if (!isValid) {
            return;
        }

        // Show loading indicator
        const loadingIndicator = document.getElementById('loadingIndicator');
        loadingIndicator.style.display = 'flex';
       
        // Create FormData object
        const formData = new FormData(this);
        formData.append('action', 'submit_verification'); // Add action parameter
       
        // Send the form data
        fetch('process_seller_verification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Hide the verification popup
                const verificationPopup = document.getElementById('verificationPopup');
                if (verificationPopup) {
                    verificationPopup.style.display = 'none';
                }

                // Show success message
                const successMessage = document.createElement('div');
                successMessage.className = 'alert alert-success';
                successMessage.textContent = data.message || 'Verification documents submitted successfully. Admin will review your application.';
                document.querySelector('.container').insertBefore(successMessage, document.querySelector('.container').firstChild);

                // Show verification pending message
                const pendingMessage = document.createElement('div');
                pendingMessage.className = 'verification-banner verification-pending';
                pendingMessage.innerHTML = `
                    <i class="fas fa-clock"></i>
                    <h4>Verification Pending</h4>
                    <p>Your seller verification is currently pending admin approval. You can view your dashboard but cannot add or manage products until your verification is approved.</p>
                    <p>Please check back later for updates on your verification status.</p>
                `;
                document.querySelector('.container').insertBefore(pendingMessage, successMessage.nextSibling);

                // Reload page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                throw new Error(data.message || 'Verification submission failed');
            }
        })
        .catch(error => {
            // Show error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'alert alert-danger';
            errorMessage.textContent = error.message;
            document.querySelector('.container').insertBefore(errorMessage, document.querySelector('.container').firstChild);
           
            // Remove error message after 5 seconds
            setTimeout(() => {
                errorMessage.remove();
            }, 5000);
        })
        .finally(() => {
            // Hide loading indicator
            loadingIndicator.style.display = 'none';
        });
    });

    // Auto-hide success message after 5 seconds
    const successMessage = document.getElementById('dashboardSuccess');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }

    // Highlight current page in sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop();
        const sidebarLinks = document.querySelectorAll('.sidebar a');
       
        sidebarLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });

        initializeEventSource();
    });

    // Add this function to your existing JavaScript
    function hideVerificationPopup() {
        const verificationPopup = document.getElementById('verificationPopup');
        if (verificationPopup) {
            verificationPopup.style.display = 'none';
           
            // Add verification pending message if it doesn't exist
            if (!document.querySelector('.verification-banner.verification-pending')) {
                const pendingMessage = document.createElement('div');
                pendingMessage.className = 'verification-banner verification-pending';
                pendingMessage.innerHTML = `
                    <i class="fas fa-clock"></i>
                    <h4>Verification Pending</h4>
                    <p>Your seller verification is currently pending admin approval. You can view your dashboard but cannot add or manage products until your verification is approved.</p>
                    <p>Please check back later for updates on your verification status.</p>
                `;
               
                // Insert the pending message after the welcome section
                const welcomeSection = document.querySelector('.welcome-section');
                if (welcomeSection) {
                    welcomeSection.insertAdjacentElement('afterend', pendingMessage);
                }
            }
        }
    }

    // Add cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (window.notificationInterval) {
            clearInterval(window.notificationInterval);
        }
    });
    </script>

    <!-- Add toast container -->
    <div id="toastContainer"></div>
</body>
</html>