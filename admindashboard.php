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
$database_name = "perfume_store";
mysqli_select_db($conn, $database_name);

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
        $mail->Host       = 'smtp.gmail.com'; // Replace with your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'arshaprasobh318@gmail.com'; // Replace with your email
        $mail->Password   = 'ilwf fpya pwkx pmat'; // Replace with your email password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise ');
        $mail->addAddress($email);
        $mail->Subject = 'Account Deactivation Notice';
        $mail->Body    = "Dear User,\n\nYour account has been deactivated by the administrator. If you believe this was done in error, please contact our support team.\n\nBest regards,\nPerfume Paradise Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Add missing columns to tbl_users and tbl_seller if they don't exist
$alterQueries = [
    "ALTER TABLE tbl_users ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER username",
    "ALTER TABLE tbl_users DROP COLUMN IF EXISTS phone_number",
    "ALTER TABLE tbl_users ADD COLUMN IF NOT EXISTS phoneno VARCHAR(15) AFTER email",
    "ALTER TABLE tbl_users ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' AFTER role_type",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER Sellername",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS phoneno VARCHAR(15) AFTER email",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' AFTER role_type",
    "ALTER TABLE tbl_seller ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status"
];

foreach ($alterQueries as $query) {
    if (!mysqli_query($conn, $query)) {
        error_log("Error executing query: " . mysqli_error($conn));
    }
}

// Improved sync data between tables function for users
function syncUserData($conn) {
    $insertQuery = "
        INSERT INTO tbl_users (Signup_id, username, email, phoneno, role_type, status)
        SELECT 
            s.Signup_id,
            s.username,
            s.email,
            s.Phoneno,
            s.role_type,
            IF(s.Verification_Status = 'verified', 'active', 'inactive') AS status
        FROM tbl_signup s
        LEFT JOIN tbl_users u ON s.Signup_id = u.Signup_id
        WHERE u.Signup_id IS NULL AND s.role_type = 'user'
    ";

    $updateQuery = "
        UPDATE tbl_users u
        INNER JOIN tbl_signup s ON u.Signup_id = s.Signup_id
        SET 
            u.email = s.email,
            u.phoneno = COALESCE(s.Phoneno, u.phoneno),
            u.username = s.username,
            u.role_type = s.role_type,
            u.status = IF(s.Verification_Status = 'verified', 'active', 'inactive')
        WHERE u.role_type = 'user'
    ";

    try {
        if (!mysqli_query($conn, $insertQuery)) {
            error_log("Error inserting new users: " . mysqli_error($conn));
        }
        if (!mysqli_query($conn, $updateQuery)) {
            error_log("Error updating existing users: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        error_log("Error syncing user data: " . $e->getMessage());
    }
}

// Improved sync data between tables function for sellers
function syncSellerData($conn) {
    $insertQuery = "
        INSERT INTO tbl_seller (Sellername, Signup_id, email, phoneno, role_type, status)
        SELECT 
            s.username,
            s.Signup_id,
            s.email,
            s.Phoneno,
            s.role_type,
            IF(s.Verification_Status = 'verified', 'active', 'inactive') AS status
        FROM tbl_signup s
        LEFT JOIN tbl_seller sl ON s.Signup_id = sl.Signup_id
        WHERE sl.Signup_id IS NULL AND s.role_type = 'seller'
    ";

    $updateQuery = "
        UPDATE tbl_seller sl
        INNER JOIN tbl_signup s ON sl.Signup_id = s.Signup_id
        SET 
            sl.email = s.email,
            sl.phoneno = COALESCE(s.Phoneno, sl.phoneno),
            sl.Sellername = s.username,
            sl.role_type = s.role_type,
            sl.status = IF(s.Verification_Status = 'verified', 'active', 'inactive')
        WHERE sl.role_type = 'seller'
    ";

    try {
        if (!mysqli_query($conn, $insertQuery)) {
            error_log("Error inserting new sellers: " . mysqli_error($conn));
        }
        if (!mysqli_query($conn, $updateQuery)) {
            error_log("Error updating existing sellers: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        error_log("Error syncing seller data: " . $e->getMessage());
    }
}

// Run the sync functions
syncUserData($conn);
syncSellerData($conn);

// Fetch total users and sellers
$totalUsersQuery = "SELECT COUNT(*) AS total_users FROM tbl_users WHERE role_type = 'user'";
$totalSellersQuery = "SELECT COUNT(*) AS total_sellers FROM tbl_seller";

$totalUsersResult = mysqli_query($conn, $totalUsersQuery);
$totalSellersResult = mysqli_query($conn, $totalSellersQuery);

$totalUsers = mysqli_fetch_assoc($totalUsersResult)['total_users'];
$totalSellers = mysqli_fetch_assoc($totalSellersResult)['total_sellers'];

// Improved handle user and seller actions
if (isset($_POST['action']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    try {
        switch($action) {
            case 'activate_user':
                // Begin transaction
                mysqli_begin_transaction($conn);
                
                // Get current phone number
                $phoneQuery = "SELECT phoneno FROM tbl_users WHERE Signup_id = $id";
                $phoneResult = mysqli_query($conn, $phoneQuery);
                $phone = mysqli_fetch_assoc($phoneResult)['phoneno'];
                
                if (!mysqli_query($conn, "UPDATE tbl_users SET status = 'active' WHERE Signup_id = $id")) {
                    throw new Exception(mysqli_error($conn));
                }
                if (!mysqli_query($conn, "UPDATE tbl_signup SET 
                    Verification_Status = 'verified',
                    Phoneno = '$phone'
                    WHERE Signup_id = $id")) {
                    throw new Exception(mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                break;

                case 'deactivate_user':
                    // Begin transaction
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Get current phone number and email
                        $userQuery = "SELECT phoneno, email FROM tbl_users WHERE Signup_id = $id";
                        $userResult = mysqli_query($conn, $userQuery);
                        
                        if (!$userResult) {
                            throw new Exception("Failed to retrieve user information: " . mysqli_error($conn));
                        }
                        
                        $userData = mysqli_fetch_assoc($userResult);
                        $phone = $userData['phoneno'];
                        $email = $userData['email']; // Get email directly from tbl_users
                        
                        // Update user status
                        if (!mysqli_query($conn, "UPDATE tbl_users SET status = 'inactive' WHERE Signup_id = $id")) {
                            throw new Exception("Failed to update user status: " . mysqli_error($conn));
                        }
                        
                        // Update signup status
                        if (!mysqli_query($conn, "UPDATE tbl_signup SET 
                            Verification_Status = 'disabled',
                            Phoneno = '$phone'
                            WHERE Signup_id = $id")) {
                            throw new Exception("Failed to update signup status: " . mysqli_error($conn));
                        }
                        
                        // Send deactivation email
                        if (!sendDeactivationEmail($email)) {
                            // Instead of throwing exception, just log the error
                            error_log("Warning: Failed to send deactivation email to $email, but user was still deactivated");
                        }
                        
                        mysqli_commit($conn);
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        error_log("Error in deactivate_user action: " . $e->getMessage());
                        header("Location: admindashboard.php?error=1");
                        exit();
                    }
                    
                    header("Location: admindashboard.php?success=1");
                    exit();
                    break;

            case 'activate_seller':
                // Begin transaction
                mysqli_begin_transaction($conn);
                
                // Get current phone number
                $phoneQuery = "SELECT phoneno FROM tbl_seller WHERE seller_id = $id";
                $phoneResult = mysqli_query($conn, $phoneQuery);
                $phone = mysqli_fetch_assoc($phoneResult)['phoneno'];
                
                if (!mysqli_query($conn, "UPDATE tbl_seller SET status = 'active' WHERE seller_id = $id")) {
                    throw new Exception(mysqli_error($conn));
                }
                if (!mysqli_query($conn, "UPDATE tbl_signup SET 
                    Verification_Status = 'verified',
                    Phoneno = '$phone'
                    WHERE Signup_id = (SELECT Signup_id FROM tbl_seller WHERE seller_id = $id)")) {
                    throw new Exception(mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                break;

            case 'deactivate_seller':
                // Begin transaction
                mysqli_begin_transaction($conn);
                
                // Get current phone number
                $phoneQuery = "SELECT phoneno FROM tbl_seller WHERE seller_id = $id";
                $phoneResult = mysqli_query($conn, $phoneQuery);
                $phone = mysqli_fetch_assoc($phoneResult)['phoneno'];
                
                if (!mysqli_query($conn, "UPDATE tbl_seller SET status = 'inactive' WHERE seller_id = $id")) {
                    throw new Exception(mysqli_error($conn));
                }
                if (!mysqli_query($conn, "UPDATE tbl_signup SET 
                    Verification_Status = 'disabled',
                    Phoneno = '$phone'
                    WHERE Signup_id = (SELECT Signup_id FROM tbl_seller WHERE seller_id = $id)")) {
                    throw new Exception(mysqli_error($conn));
                }
                
                // Get seller email
                $emailQuery = "SELECT email FROM tbl_signup WHERE Signup_id = (SELECT Signup_id FROM tbl_seller WHERE seller_id = $id)";
                $emailResult = mysqli_query($conn, $emailQuery);
                $email = mysqli_fetch_assoc($emailResult)['email'];
                
                // Send deactivation email
                if (!sendDeactivationEmail($email)) {
                    throw new Exception("Failed to send deactivation email");
                }
                
                mysqli_commit($conn);
                break;
        }
        header("Location: admindashboard.php?success=1");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error in action: " . $e->getMessage());
        header("Location: admindashboard.php?error=1");
        exit();
    }
}

// Improved fetch user data with proper joins
$usersQuery = "
    SELECT 
        u.username,
        u.email,
        COALESCE(u.phoneno, s.Phoneno) as phoneno,
        u.status,
        u.role_type,
        s.created_at,
        u.Signup_id
    FROM tbl_users u
    LEFT JOIN tbl_signup s ON u.Signup_id = s.Signup_id
    WHERE u.role_type = 'user'
    ORDER BY s.created_at DESC
";

$usersResult = mysqli_query($conn, $usersQuery);

if (!$usersResult) {
    error_log("Error fetching users: " . mysqli_error($conn));
}

// Improved fetch seller data with proper joins
$sellersQuery = "
    SELECT 
        sl.seller_id,
        sl.Sellername,
        sl.email,
        COALESCE(sl.phoneno, s.Phoneno) as phoneno,
        sl.status,
        sl.role_type,
        s.created_at
    FROM tbl_seller sl
    LEFT JOIN tbl_signup s ON sl.Signup_id = s.Signup_id
    WHERE sl.role_type = 'seller'
    ORDER BY s.created_at DESC
";

$sellersResult = mysqli_query($conn, $sellersQuery);

if (!$sellersResult) {
    error_log("Error fetching sellers: " . mysqli_error($conn));
}
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
    background-color: #2d2a4b;
    height: 100vh;
    position: fixed;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.sidebar h2 {
    text-align: center;
    color: #fff;
    padding: 20px;
    background-color: #2d2a4b;
    margin: 0;
}

.sidebar a {
    display: flex;
    align-items: center;
    color: #fff;
    padding: 15px 20px;
    text-decoration: none;
    border-bottom: 1px solid #3a375f;
    transition: all 0.3s ease;
}

.sidebar a svg {
    width: 20px;
    height: 20px;
    margin-right: 10px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
}

.sidebar a:hover, .sidebar .active {
    background-color: #3a375f;
    color: #fff;
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
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background-color: #2d2a4b;
    color: #fff;
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
    background-color: #4CAF50;
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
    color: #4CAF50;
}

.status-inactive {
    color: #ff9800;
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
    </style>
</head>
<body>
<div class="sidebar">
    <h2>Perfume Paradise</h2>
    <a href="admindashboard.php" class="active">
        <svg viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
        Dashboard
    </a>
    <a href="index.html">
        <svg viewBox="0 0 24 24">
            <path d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10M12 3v3"></path>
        </svg>
        Home
    </a>
    <a href="manage-users.php">
    <svg viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4"/>
            <path d="M18 21a6 6 0 00-12 0"/>
        </svg>
        Manage Users
    </a>
    <a href="manage-sellers.php">
        <svg viewBox="0 0 24 24">
            <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
        Manage Sellers
    </a>
    <a href="manage-categories.php">
        <svg viewBox="0 0 24 24">
            <rect x="3" y="3" width="7" height="7"/>
            <rect x="14" y="3" width="7" height="7"/>
            <rect x="3" y="14" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/>
        </svg>
        Manage Categories
    </a>
    <a href="customer-reviews.php">
        <svg viewBox="0 0 24 24">
            <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
        </svg>
        Customer Reviews
    </a>
    <a href="logout.php">
        <svg viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
        Logout
    </a>
</div>

<div class="main-content">
    <div class="header">
        <h1>Welcome Admin!</h1>
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
        </div>
        <div class="stat-box">
            <h3>Total Sellers</h3>
            <div class="number"><?php echo $totalSellers; ?></div>
        </div>
    </div>

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
            <?php while ($user = mysqli_fetch_assoc($usersResult)) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phoneno']); ?></td>
                    <td class="status-<?php echo strtolower($user['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                    <td class="actions">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="id" value="<?php echo $user['Signup_id']; ?>">
                            <?php if ($user['status'] == 'inactive') { ?>
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
    <br><br>

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
            <?php while ($seller = mysqli_fetch_assoc($sellersResult)) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($seller['Sellername']); ?></td>
                    <td><?php echo htmlspecialchars($seller['email']); ?></td>
                    <td><?php echo htmlspecialchars($seller['phoneno']); ?></td>
                    <td class="status-<?php echo strtolower($seller['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($seller['status'])); ?>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($seller['created_at'])); ?></td>
                    <td class="actions">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="id" value="<?php echo $seller['seller_id']; ?>">
                            <?php if ($seller['status'] == 'inactive') { ?>
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