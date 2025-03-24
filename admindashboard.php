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

// Determine which section to show
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// Add missing columns to tbl_users and tbl_seller if they don't exist
$alterQueries = [
    "ALTER TABLE tbl_users ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER username",
    "ALTER TABLE tbl_users DROP COLUMN IF EXISTS phone_number",
    "ALTER TABLE tbl_users ADD COLUMN IF NOT EXISTS phoneno VARCHAR(15) AFTER email",
    "ALTER TABLE tbl_users ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' AFTER role_type",
    "ALTER TABLE tbl_users ADD COLUMN IF NOT EXISTS verification_status ENUM('active', 'disabled') DEFAULT 'active' AFTER status",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER Sellername",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS phoneno VARCHAR(15) AFTER email",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' AFTER role_type",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS verification_status ENUM('active', 'disabled') DEFAULT 'active' AFTER status",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status"
];

foreach ($alterQueries as $query) {
    if (!mysqli_query($conn, $query)) {
        error_log("Error executing query: " . mysqli_error($conn));
    }
}

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
$totalOrdersQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing_count,
    SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM orders_table";

$totalOrdersResult = mysqli_query($conn, $totalOrdersQuery);
$orderStats = mysqli_fetch_assoc($totalOrdersResult);

$totalOrders = $orderStats['total'] ?? 0;
$processingOrders = $orderStats['processing_count'] ?? 0;
$completedOrders = $orderStats['completed_count'] ?? 0;

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
    
    switch($_POST['action']) {
        case 'approve_seller':
            $updateSeller = "UPDATE tbl_seller 
                SET verified_status = 'verified' 
                WHERE seller_id = ?";
            $stmt = mysqli_prepare($conn, $updateSeller);
            mysqli_stmt_bind_param($stmt, "i", $seller_id);
            mysqli_stmt_execute($stmt);
            
            // Send approval email to seller
            $emailQuery = "SELECT email FROM tbl_seller WHERE seller_id = ?";
            $stmt = mysqli_prepare($conn, $emailQuery);
            mysqli_stmt_bind_param($stmt, "i", $seller_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $email = mysqli_fetch_assoc($result)['email'];
            
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
                
                $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
                $mail->addAddress($email);
                $mail->Subject = 'Seller Verification Approved';
                $mail->Body = "Congratulations! Your seller account has been verified. You can now start adding products and selling on Perfume Paradise.";
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Email sending failed: " . $mail->ErrorInfo);
            }
            break;
            
        case 'reject_seller':
            $updateSeller = "UPDATE tbl_seller 
                SET verified_status = 'rejected' 
                WHERE seller_id = ?";
            $stmt = mysqli_prepare($conn, $updateSeller);
            mysqli_stmt_bind_param($stmt, "i", $seller_id);
            mysqli_stmt_execute($stmt);
            break;
    }
    
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
    width: 250px;
    background-color: #1a1a1a !important;  /* Dark black for sidebar */
    height: 100vh;
    position: fixed;
    color: #ffffff;
}

.sidebar h2 {
    text-align: center;
    color: #fff;
    padding: 20px;
    background-color: #2d2a4b;
    margin: 0;
}

.sidebar a {
    color: #ffffff;
    padding: 15px 20px;
    text-decoration: none;
    border-bottom: 1px solid #1a1a1a;
    display: block;
}

.sidebar a:hover, .sidebar .active {
    background-color: #1a1a1a;
    color: #ffffff;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    width: calc(100% - 250px);
    background-color: #f4f7fc;
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
}

.bg-danger {
    background-color: #dc3545 !important;
    color: white;
}
    </style>
</head>
<body>
    <div class="sidebar">
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
            <h2>Manage Users</h2>
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
            <h2>Manage Sellers</h2>
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
                            // Corrected query to use payment_table for payment_status
                            $orders_query = "SELECT DISTINCT 
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
                            
                            $orders_result = mysqli_query($conn, $orders_query);
                            
                            if (!$orders_result) {
                                echo "<tr><td colspan='9' class='text-center'>Error fetching orders: " . mysqli_error($conn) . "</td></tr>";
                            } elseif (mysqli_num_rows($orders_result) == 0) {
                                echo "<tr><td colspan='9' class='text-center'>No orders found</td></tr>";
                            } else {
                                while ($order = mysqli_fetch_assoc($orders_result)): 
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
            </div>
        <?php endif; ?>

        <?php if ($view == 'verify-sellers'): ?>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Seller Management</h1>
                
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
                                    SELECT 
                                        s.seller_id,
                                        s.Sellername,
                                        s.verification_status,
                                        s.created_at,
                                        sg.username, 
                                        sg.email, 
                                        sg.phoneno,
                                        svd.id_proof_front,
                                        svd.id_proof_back,
                                        svd.business_proof,
                                        svd.address_proof,
                                        (SELECT COUNT(*) FROM tbl_product WHERE seller_id = s.seller_id) as total_products,
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
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No verified sellers found</td>
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
                        fetch('verify_seller.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                seller_id: sellerId,
                                status: status
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while processing your request.');
                        });
                    }
                }

                function toggleDocuments(sellerId) {
                    const docSection = document.getElementById(`documents-${sellerId}`);
                    if (docSection.style.display === 'none') {
                        docSection.style.display = 'block';
                    } else {
                        docSection.style.display = 'none';
                    }
                }
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
</body>
</html>