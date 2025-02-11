<?php
require_once('dbconnect.php'); 
$database_name = "perfume_store";
mysqli_select_db($conn, $database_name);

// Fetch total users and sellers from the database
$totalUsersQuery = "SELECT COUNT(*) AS total_users FROM tbl_users";
$totalSellersQuery = "SELECT COUNT(*) AS total_sellers FROM tbl_seller";

$totalUsersResult = mysqli_query($conn, $totalUsersQuery);
$totalSellersResult = mysqli_query($conn, $totalSellersQuery);

$totalUsers = mysqli_fetch_assoc($totalUsersResult)['total_users'];
$totalSellers = mysqli_fetch_assoc($totalSellersResult)['total_sellers'];

// Handle user activation/deactivation or deletion
if (isset($_POST['action']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    if ($action == 'activate_user') {
        mysqli_query($conn, "UPDATE tbl_users SET status = 'active' WHERE id = $id");
    } elseif ($action == 'deactivate_user') {
        mysqli_query($conn, "UPDATE tbl_users SET status = 'inactive' WHERE id = $id");
    } elseif ($action == 'delete_user') {
        mysqli_query($conn, "DELETE FROM tbl_users WHERE id = $id");
    }

    if ($action == 'activate_seller') {
        mysqli_query($conn, "UPDATE tbl_seller SET status = 'active' WHERE id = $id");
    } elseif ($action == 'deactivate_seller') {
        mysqli_query($conn, "UPDATE tbl_seller SET status = 'inactive' WHERE id = $id");
    } elseif ($action == 'delete_seller') {
        mysqli_query($conn, "DELETE FROM tbl_seller WHERE id = $id");
    }

    header("Location: admindashboard.php");
    exit();
}

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Fetch user and seller data
$usersQuery = "SELECT * FROM tbl_users";
$sellersQuery = "SELECT * FROM tbl_seller";

$usersResult = mysqli_query($conn, $usersQuery);
$sellersResult = mysqli_query($conn, $sellersQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
   body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #060a15; 
    color: #e0e0e0;
}

.sidebar {
    width: 250px;
    background-color: #0c0f1a; 
    height: 100vh;
    position: fixed;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.4);
}

.sidebar h2 {
    text-align: center;
    color: #00b3ff; 
    padding: 20px;
    background-color: #111726; 
    margin: 0;
}

.sidebar a {
    display: block;
    color: #7a8aaa; 
    padding: 15px 20px;
    text-decoration: none;
    border-bottom: 1px solid #1c2333; 
    transition: all 0.3s ease;
}

.sidebar a:hover, .sidebar .active {
    background-color: #111726;
    color: #00b3ff;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    width: calc(100% - 250px);
}

.header {
    background-color: #0c0f1a;
    padding: 15px;
    display: flex;
    justify-content: center; 
    align-items: center;
    margin-bottom: 20px;
    text-align: center;
    color: #00b3ff;
    font-size: 24px;
    font-weight: bold;
    position: relative;}

.logout-btn {
    background-color:red;
    color: white;
    padding: 6px 12px; 
    text-decoration: none;
    border-radius: 4px;
    transition: opacity 0.3s ease;
    font-size: 14px; 
    position: absolute; 
    right: 15px; 
}

.logout-btn:hover {
    opacity: 0.9;
}
.search-bar input {
    background-color: #111726;
    border: 1px solid #00b3ff;
    color: #e0e0e0;
    padding: 8px;
}

.search-bar button {
    background-color: #00b3ff;
    color: #060a15;
    border: none;
    padding: 8px 15px;
}

.stats-container {
    display: flex;
    justify-content: space-between;
    gap: 20px;
}

.stat-box {
    background-color: #111726;
    padding: 20px;
    border-radius: 8px;
    flex: 1;
    text-align: center;
    color: #00b3ff;
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.4);
    border-top: 3px solid #00b3ff;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background-color: #111726;
}

th, td {
    border-bottom: 1px solid #1c2333;
    padding: 12px;
    text-align: left;
    color: #d0d0d0;
}

th {
    background-color: #0c0f1a;
    color: #00b3ff;
    text-transform: uppercase;
    font-weight: bold;
}

.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 2px;
}

.btn-green { 
    background-color: #00b3ff; 
    color: #060a15;
}

.btn-red { 
    background-color: #ff3333; 
    color: white;
}

.btn-orange { 
    background-color: #ff8c00; 
    color: #060a15;
}

.btn:hover {
    opacity: 0.9;
}
</style>

</head>
<body>
    <div class="sidebar">
        <h2>Perfume Paradise</h2>
        <a href="#dashboard" class="active">Dashboard</a>
        <a href="#manage-users">Manage Users</a>
        <a href="#manage-sellers">Manage Sellers</a>
    </div>

    <div class="main-content">
        <div class="header">
            Welcome, Admin!
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        <div class="stats-container">
            <div class="stat-box">Total Users: <strong><?php echo $totalUsers; ?></strong></div>
            <div class="stat-box">Total Sellers: <strong><?php echo $totalSellers; ?></strong></div>
        </div>

        <h3>Manage Users</h3>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($usersResult)) { ?>
                    <tr>
                        <td><?php echo $user['username']; ?></td>
                        <td><?php echo isset($user['email']) ? $user['email'] : 'N/A'; ?></td>
                        <td><?php echo isset($user['status']) ? $user['status'] : 'N/A'; ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button class="btn btn-green" name="action" value="activate_user">Activate</button>
                                <button class="btn btn-orange" name="action" value="deactivate_user">Deactivate</button>
                                <button class="btn btn-red" name="action" value="delete_user">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>