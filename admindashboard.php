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

// Determine which section to show on top
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
    background-color: #000000;
    color: #ffffff;
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
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="background-color: #000000;">Perfume Paradise</h2>
        <a href="admindashboard.php" class="<?php echo $view == 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="admindashboard.php?view=users" class="<?php echo $view == 'users' ? 'active' : ''; ?>">Manage Users</a>
        <a href="admindashboard.php?view=sellers" class="<?php echo $view == 'sellers' ? 'active' : ''; ?>">Manage Sellers</a>
        <a href="manage-categories.php">Manage Categories</a>
        <a href="customer-reviews.php">Customer Reviews</a>
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

        <div class="stats-container">
            <div class="stat-box">
                <h3>Total Users</h3>
                <div class="number"><?php echo $totalUsers; ?></div>
                <div>Active: <?php echo $activeUsers; ?></div>
                <div>Inactive: <?php echo $inactiveUsers; ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Sellers</h3>
                <div class="number"><?php echo $totalSellers; ?></div>
                <div>Active: <?php echo $activeSellers; ?></div>
                <div>Inactive: <?php echo $inactiveSellers; ?></div>
            </div>
        </div>

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
        if ($view == 'users') {
            echo $userSection;
            echo $sellerSection;
        } elseif ($view == 'sellers') {
            echo $sellerSection;
            echo $userSection;
        } else {
            // Default dashboard view
            echo $userSection;
            echo $sellerSection;
        }
        ?>
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