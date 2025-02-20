<?php
// seller-dashboard.php
session_start();
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

$seller_id = $_SESSION['user_id'];

// Get seller information
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
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #343a40;
            padding-top: 20px;
        }
        .sidebar a {
            padding: 10px 15px;
            text-decoration: none;
            font-size: 18px;
            color: white;
            display: block;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="seller-dashboard.php">Dashboard</a>
        <a href="home.php">Home</a>
        <a href="edit_profile.php">Edit Profile</a>
        <a href="products.php">Products</a>
        <a href="sales.php">Sales</a>
        <a href="reviews.php">Customer Reviews</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="main-content">
        <div class="container mt-4">
            <div class="row">
                <!-- Seller Profile -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Seller Profile</h5>
                            <p>Name: <?php echo htmlspecialchars($seller_info['Sellername']); ?></p>
                            <p>Email: <?php echo htmlspecialchars($seller_info['email']); ?></p>
                            <p>Phone: <?php echo htmlspecialchars($seller_info['phoneno']); ?></p>
                            <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                        </div>
                    </div>
                </div>

                <!-- Product Management -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Product Management</h5>
                            <a href="add_product.php" class="btn btn-success mb-3">Add New Product</a>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>₹<?php echo htmlspecialchars($product['price']); ?></td>
                                            <td><?php echo htmlspecialchars($product['Stock_quantity']); ?></td>
                                            <td>
                                                <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" 
                                                   class="btn btn-sm btn-primary">Edit</a>
                                                <a href="delete_product.php?id=<?php echo $product['product_id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure?')">Delete</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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