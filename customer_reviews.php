<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in and is admin or seller
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Prepare the query based on role
if ($is_admin) {
    // Admin can see all reviews
    $query = "SELECT r.*, 
              p.name as product_name, 
              p.image_path,
              u.username as customer_name,
              s.Sellername as seller_name
              FROM tbl_reviews r
              JOIN tbl_product p ON r.product_id = p.product_id
              JOIN tbl_signup u ON r.user_id = u.Signup_id
              JOIN tbl_seller s ON p.seller_id = s.seller_id
              ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($query);
} else {
    // Seller can only see reviews for their products
    $query = "SELECT r.*, 
              p.name as product_name, 
              p.image_path,
              u.username as customer_name
              FROM tbl_reviews r
              JOIN tbl_product p ON r.product_id = p.product_id
              JOIN tbl_signup u ON r.user_id = u.Signup_id
              JOIN tbl_seller s ON p.seller_id = s.seller_id
              WHERE s.Signup_id = ?
              ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$reviews = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reviews-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .review-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
        }

        .product-info {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }

        .product-details {
            flex-grow: 1;
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .customer-name {
            color: #666;
            font-size: 14px;
        }

        .rating {
            margin: 10px 0;
        }

        .star {
            color: #ffd700;
            font-size: 18px;
        }

        .review-text {
            margin: 15px 0;
            line-height: 1.6;
            color: #444;
        }

        .review-meta {
            color: #888;
            font-size: 14px;
            text-align: right;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #e8a87c;
        }

        .stat-label {
            color: #666;
            margin-top: 5px;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
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

        .sidebar h3 {
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
        .reviews-container {
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
            
            .reviews-container {
                margin-left: 0;
                max-width: 100%;
            }
            
            .sidebar a {
                float: left;
                padding: 15px;
            }
            
            .sidebar h3 {
                display: none;
            }
        }

        @media screen and (max-width: 480px) {
            .sidebar a {
                text-align: center;
                float: none;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h3>&nbsp;Perfume Paradise</h3>
        <?php if (isset($_SESSION['role'])): ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admindashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="orders.php">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
            <?php elseif ($_SESSION['role'] === 'seller'): ?>
                <a href="seller-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="sales.php">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
            <?php else: ?>
                <a href="index.php">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="orders.php">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
            <?php endif; ?>
        <?php endif; ?>
        
        <a href="profile.php">
            <i class="fas fa-user-edit"></i> Profile
        </a>
        <a href="customer_reviews.php" class="active">
            <i class="fas fa-star"></i> Customer Reviews
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="reviews-container">
        <h1>Customer Reviews</h1>

        <!-- Statistics Section -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $reviews->num_rows; ?></div>
                <div class="stat-label">Total Reviews</div>
            </div>
            <div class="stat-card">
                <?php
                $total_rating = 0;
                $reviews_count = $reviews->num_rows;
                if ($reviews_count > 0) {
                    mysqli_data_seek($reviews, 0);
                    while ($review = $reviews->fetch_assoc()) {
                        $total_rating += $review['rating'];
                    }
                    $avg_rating = $total_rating / $reviews_count;
                    mysqli_data_seek($reviews, 0);
                }
                ?>
                <div class="stat-number"><?php echo $reviews_count > 0 ? number_format($avg_rating, 1) : '0.0'; ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>

        <!-- Reviews List -->
        <?php if ($reviews->num_rows > 0): ?>
            <?php while ($review = $reviews->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="product-info">
                        <img src="<?php echo htmlspecialchars($review['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($review['product_name']); ?>" 
                             class="product-image">
                        <div class="product-details">
                            <div class="product-name"><?php echo htmlspecialchars($review['product_name']); ?></div>
                            <div class="customer-name">
                                Reviewed by: <?php echo htmlspecialchars($review['customer_name']); ?>
                                <?php if ($is_admin): ?>
                                    <br>Seller: <?php echo htmlspecialchars($review['seller_name']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star" style="color: <?php echo $i <= $review['rating'] ? '#ffd700' : '#ddd'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="review-text"><?php echo htmlspecialchars($review['comment']); ?></div>
                    <div class="review-meta">
                        Reviewed on <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="review-card">
                <p style="text-align: center; color: #666;">No reviews found.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add this to highlight current page in sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('.sidebar a');
            
            sidebarLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>