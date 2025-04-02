<?php
session_start();
require_once 'dbconnect.php';

// Get product ID from URL with validation
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header("Location: productslist.php");
    exit();
}

// Fetch product details with proper joins and error handling
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name, b.name as brand_name,
           s.Sellername as seller_name, p.size as product_size
    FROM tbl_product p
    LEFT JOIN tbl_categories c ON p.category_id = c.category_id
    LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
    LEFT JOIN tbl_seller s ON p.seller_id = s.seller_id
    WHERE p.product_id = ? AND p.deleted = 0
");

if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}

$stmt->bind_param("i", $product_id);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: productslist.php");
    exit();
}

// Fetch related products in the same category
$related_stmt = $conn->prepare("
    SELECT p.*, b.name as brand_name 
    FROM tbl_product p
    LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
    WHERE p.category_id = ? 
    AND p.product_id != ? 
    AND p.deleted = 0 
    LIMIT 4
");
$related_stmt->bind_param("ii", $product['category_id'], $product_id);
$related_stmt->execute();
$related_products = $related_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            background-color: #f1f3f6;
        }

        .product-container {
            max-width: 1200px;
            margin: 80px auto 20px;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .product-image-main {
            width: 100%;
            height: 400px;
            object-fit: contain;
            padding: 20px;
        }

        .product-info {
            padding: 20px;
        }

        .product-title {
            font-size: 24px;
            margin-bottom: 10px;
            color: #212121;
        }

        .product-meta {
            color: #878787;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .product-price {
            font-size: 28px;
            color: #212121;
            margin: 20px 0;
            font-weight: 500;
        }

        .product-description {
            line-height: 1.6;
            color: #212121;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .product-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .action-button {
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
        }

        .add-to-cart-btn {
            background: #ff9f00;
            color: white;
        }

        .buy-now-btn {
            background: #fb641b;
            color: white;
        }

        .wishlist-btn {
            background: white;
            border: 1px solid #d4d5d9;
            color: #212121;
            padding: 15px;
            flex: 0 0 50px;
        }

        .wishlist-btn.active {
            color: #ff4081;
        }

        .wishlist-btn:hover {
            background: #f5f5f5;
        }

        /* Related Products Styling */
        .related-products {
            max-width: 1200px;
            margin: 0 auto 40px;
            background: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .related-products h2 {
            font-size: 20px;
            color: #212121;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .product-card {
            padding: 15px;
            transition: box-shadow 0.3s ease;
            cursor: pointer;
            position: relative;
            border: 1px solid #f0f0f0;
            border-radius: 4px;
        }

        .product-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .product-link {
            text-decoration: none;
            color: inherit;
        }

        .product-card .product-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .product-card .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .product-card .product-title {
            font-size: 14px;
            color: #212121;
            margin: 10px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 40px;
        }

        .product-card .product-brand {
            color: #878787;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .product-card .product-price {
            font-size: 16px;
            color: #212121;
            margin: 10px 0;
            font-weight: 500;
        }

        .out-of-stock {
            color: #ff6161;
            font-size: 16px;
            font-weight: 500;
            margin-top: 10px;
        }

        @media (max-width: 1200px) {
            .related-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .product-container {
                grid-template-columns: 1fr;
            }
            .product-image-main {
                height: 300px;
            }
        }

        @media (max-width: 576px) {
            .related-grid {
                grid-template-columns: 1fr;
            }
            .product-container,
            .related-products {
                margin: 60px 10px 20px;
            }
        }

        .restricted-btn {
            background: #f0f0f0 !important;
            color: #666 !important;
            cursor: pointer;
            width: 100%;
        }

        .restricted-btn:hover {
            background: #e0e0e0 !important;
        }

        .login-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            text-align: center;
            display: none;
        }

        .login-message button {
            margin-top: 15px;
            padding: 8px 16px;
            background: #2874f0;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .login-message button:hover {
            background: #1c5dc9;
        }

        .product-meta p {
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .product-meta p:last-child {
            margin-bottom: 20px;
        }

        /* Add these styles to your existing CSS */
        .reviews-summary {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .average-rating {
            text-align: center;
        }

        .rating-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .stars {
            color: #ffd700;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .total-reviews {
            color: #666;
            font-size: 14px;
        }

        .reviews-list {
            margin-top: 30px;
        }

        .review-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .reviewer-info {
            flex-grow: 1;
        }

        .reviewer-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .review-date {
            color: #666;
            font-size: 12px;
        }

        .review-rating {
            color: #ffd700;
        }

        .review-content {
            color: #444;
            line-height: 1.6;
        }

        .no-reviews {
            text-align: center;
            padding: 30px;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .review-header {
                flex-direction: column;
            }

            .review-rating {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body class="product-page">
    <?php include 'header.php'; ?>

    <div class="product-container">
        <div class="product-image">
            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                 class="product-image-main">
        </div>

        <div class="product-info">
            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="product-meta">
                <p>Brand: <?php echo htmlspecialchars($product['brand_name'] ?? 'N/A'); ?></p>
                <p>Category: <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></p>
                <p>Seller: <?php echo htmlspecialchars($product['seller_name'] ?? 'N/A'); ?></p>
                <p>Size: <?php echo htmlspecialchars($product['product_size'] ?? 'N/A'); ?> </p>
            </div>

            <div class="product-price">
                ₹<?php echo number_format($product['price'], 2); ?>
            </div>

            <div class="product-description">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </div>

            <?php if ($product['Stock_quantity'] > 0): ?>
                <div class="product-actions">
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'seller')): ?>
                        <!-- View Only button for admin and seller -->
                        <button class="action-button restricted-btn" onclick="showLoginMessage()">
                            <i class="fas fa-eye"></i> VIEW ONLY
                        </button>
                    <?php else: ?>
                        <button class="wishlist-btn" id="wishlistBtn" data-product-id="<?php echo $product['product_id']; ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                        
                        <form action="add_to_cart.php" method="POST" class="add-to-cart-form">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <input type="hidden" name="add_to_cart" value="1">
                            <button type="submit" class="action-button add-to-cart-btn">
                                <i class="fas fa-shopping-cart"></i> ADD TO CART
                            </button>
                        </form>
                        
                        <form action="buy_now.php" method="POST" class="buy-now-form">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <button type="submit" class="action-button buy-now-btn">
                                <i class="fas fa-bolt"></i> BUY NOW
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="out-of-stock">Out of Stock</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Reviews Section - Moved above related products -->
    <div class="related-products">
        <h2>Customer Reviews</h2>
        <?php
        // Fetch reviews for this product
        $review_query = "SELECT r.*, u.username 
                        FROM tbl_reviews r 
                        JOIN tbl_signup u ON r.user_id = u.Signup_id 
                        WHERE r.product_id = ? 
                        ORDER BY r.created_at DESC";
        $review_stmt = $conn->prepare($review_query);
        $review_stmt->bind_param("i", $product_id);
        $review_stmt->execute();
        $reviews = $review_stmt->get_result();

        // Calculate average rating
        $avg_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                     FROM tbl_reviews 
                     WHERE product_id = ?";
        $avg_stmt = $conn->prepare($avg_query);
        $avg_stmt->bind_param("i", $product_id);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result()->fetch_assoc();
        $average_rating = round($avg_result['avg_rating'], 1);
        $total_reviews = $avg_result['total_reviews'];
        ?>

        <div class="reviews-summary">
            <div class="average-rating">
                <div class="rating-number"><?php echo $average_rating; ?> / 5</div>
                <div class="stars">
                    <?php
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $average_rating) {
                            echo '<i class="fas fa-star"></i>';
                        } elseif ($i - $average_rating < 1) {
                            echo '<i class="fas fa-star-half-alt"></i>';
                        } else {
                            echo '<i class="far fa-star"></i>';
                        }
                    }
                    ?>
                </div>
                <div class="total-reviews"><?php echo $total_reviews; ?> reviews</div>
            </div>
        </div>

        <div class="reviews-list">
            <?php if ($reviews->num_rows > 0): ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-name"><?php echo htmlspecialchars($review['username']); ?></div>
                                <div class="review-date">
                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                </div>
                            </div>
                            <div class="review-rating">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $review['rating']) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="review-content">
                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-reviews">
                    <p>No reviews yet. Be the first to review this product!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($related_products)): ?>
    <!-- Related Products Section - Moved below reviews -->
    <div class="related-products">
        <h2>Other Products</h2>
        <div class="related-grid">
            <?php foreach ($related_products as $related): ?>
                <div class="product-card">
                    <a href="product_details.php?id=<?php echo $related['product_id']; ?>" class="product-link">
                        <div class="product-image">
                            <img src="<?php echo htmlspecialchars($related['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>">
                        </div>
                        <div class="product-details">
                            <h3 class="product-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                            <p class="product-brand"><?php echo htmlspecialchars($related['brand_name']); ?></p>
                            <p class="product-price">₹<?php echo number_format($related['price'], 2); ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function showLoginMessage() {
        // Create modal element
        const modal = document.createElement('div');
        modal.className = 'login-message';
        modal.innerHTML = `
            <p>Please login as a user to buy products.</p>
            <button onclick="closeLoginMessage(this)">OK</button>
        `;
        document.body.appendChild(modal);
        
        // Show with fade effect
        setTimeout(() => {
            modal.style.display = 'block';
        }, 10);
    }

    function closeLoginMessage(button) {
        const modal = button.parentElement;
        modal.style.display = 'none';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!(isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'seller'))): ?>
            // Only add these event listeners for regular users
            const wishlistBtn = document.getElementById('wishlistBtn');
            if (wishlistBtn) {
                // Check if product is in wishlist
                checkWishlistStatus();
                
                wishlistBtn.addEventListener('click', function() {
                    if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
                        window.location.href = 'login.php';
                        return;
                    }
                    
                    const productId = this.dataset.productId;
                    
                    fetch('toggle_wishlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            product_id: productId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            wishlistBtn.classList.toggle('active');
                            // Show success message
                            alert(data.message);
                        } else {
                            // Show error message
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
                });
                
                function checkWishlistStatus() {
                    if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
                        return;
                    }
                    
                    const productId = wishlistBtn.dataset.productId;
                    
                    fetch('check_wishlist.php?product_id=' + productId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.in_wishlist) {
                            wishlistBtn.classList.add('active');
                        }
                    });
                }
            }

            const addToCartForm = document.querySelector('.add-to-cart-form');
            if (addToCartForm) {
                addToCartForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Check if user is logged in
                    if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
                        window.location.href = 'login.php';
                        return;
                    }
                    
                    const formData = new FormData(this);
                    
                    fetch('add_to_cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(() => {
                        // Redirect to cart page after successful addition
                        window.location.href = 'add_to_cart.php';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
                });
            }

            const buyNowForm = document.querySelector('.buy-now-form');
            if (buyNowForm) {
                buyNowForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
                        window.location.href = 'login.php';
                        return;
                    }
                    
                    const productId = this.querySelector('input[name="product_id"]').value;
                    window.location.href = `checkout.php?buy_now=1&product_id=${productId}`;
                });
            }
        <?php endif; ?>
    });
    </script>
</body>
</html> 