<?php
// Start the session
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
    "ALTER TABLE tbl_orders ADD COLUMN IF NOT EXISTS quantity INT NOT NULL DEFAULT 1",
    "ALTER TABLE tbl_orders ADD COLUMN IF NOT EXISTS product_id INT(11) NOT NULL",
    "ALTER TABLE tbl_orders ADD COLUMN IF NOT EXISTS user_id INT(11) NOT NULL",
    "ALTER TABLE tbl_orders ADD FOREIGN KEY IF NOT EXISTS (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE",
    "ALTER TABLE tbl_orders ADD FOREIGN KEY IF NOT EXISTS (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE"
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

// Get seller information
$seller_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM tbl_seller WHERE Signup_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_info = $stmt->get_result()->fetch_assoc();

// Get seller's products
$stmt = $conn->prepare("SELECT * FROM tbl_product WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sales data with correct JOIN
$stmt = $conn->prepare("
    SELECT o.order_id, o.quantity, o.total_amount, o.status, o.ordered_time, 
           p.name as product_name, u.username as customer_name
    FROM tbl_orders o 
    JOIN tbl_product p ON o.product_id = p.product_id 
    JOIN tbl_users u ON o.user_id = u.user_id
    WHERE p.seller_id = ?
    ORDER BY o.ordered_time DESC
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent reviews
$stmt = $conn->prepare("
    SELECT r.*, p.name as product_name, u.username 
    FROM tbl_reviews r 
    JOIN tbl_product p ON r.product_id = p.product_id 
    JOIN tbl_users u ON r.user_id = u.user_id 
    WHERE p.seller_id = ?
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Perfume Paradise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        thead {
    background-color: #2d2a4b; /* Dark blue background */
}

th {
    color: white; /* White text color */
    font-weight: bold;
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
    </style>
</head>
<body>
    <div class="sidebar"><br>
    <h3 style="color: white;">Perfume Paradise</h3>
        <a href="seller-dashboard.php">Dashboard</a>
        <a href="index.php">Home</a>
        <a href="profile.php">Edit Profile</a>
        <a href="products.php">Products</a>
        <a href="sales.php">Sales</a>
        <a href="reviews.php">Customer Reviews</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Welcome Seller</h1>
        </div>

        <div class="container mt-4">
            <!-- Sales Data -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Recent Sales</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Product</th>
                                            <th>Customer</th>
                                            <th>Quantity</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sale['order_id']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                                            <td>₹<?php echo htmlspecialchars($sale['total_amount']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['status']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['ordered_time']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reviews -->
            <div class="row mt-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Recent Reviews</h5>
                            <?php foreach ($reviews as $review): ?>
                            <div class="border-bottom p-3">
                                <div class="d-flex justify-content-between">
                                    <h6><?php echo htmlspecialchars($review['product_name']); ?></h6>
                                    <div>Rating: <?php echo htmlspecialchars($review['rating']); ?>/5</div>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($review['comment']); ?></p>
                                <small class="text-muted">By <?php echo htmlspecialchars($review['username']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>