<?php
session_start();
require_once 'dbconnect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

// Fetch wishlist items with product details
$user_id = $_SESSION['user_id'];
$query = "SELECT p.*, w.wishlist_id, b.brand_name 
          FROM tbl_wishlist w 
          JOIN tbl_product p ON w.product_id = p.product_id 
          LEFT JOIN tbl_brand b ON p.brand_id = b.brand_id 
          WHERE w.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wishlist_items = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Wishlist - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        .container {
            max-width: 1200px;
            margin: 80px auto 20px;
            padding: 20px;
        }

        .wishlist-header {
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }

        .wishlist-header h1 {
            color: #212121;
            font-size: 24px;
        }

        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .wishlist-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .wishlist-item:hover {
            transform: translateY(-5px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: contain;
            margin-bottom: 15px;
        }

        .product-title {
            font-size: 16px;
            color: #212121;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .product-brand {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .product-price {
            font-size: 18px;
            font-weight: 600;
            color: #212121;
            margin-bottom: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .move-to-cart,
        .remove-from-wishlist {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            flex: 1;
        }

        .move-to-cart {
            background: #ff9f00;
            color: white;
        }

        .remove-from-wishlist {
            background: #ff4081;
            color: white;
        }

        .empty-wishlist {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }

        .empty-wishlist i {
            font-size: 48px;
            color: #666;
            margin-bottom: 20px;
        }

        .empty-wishlist p {
            color: #666;
            margin-bottom: 20px;
        }

        .shop-now-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2874f0;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="wishlist-header">
            <h1>My Wishlist</h1>
        </div>

        <?php if (empty($wishlist_items)): ?>
            <div class="empty-wishlist">
                <i class="fas fa-heart-broken"></i>
                <p>Your wishlist is empty</p>
                <a href="productslist.php" class="shop-now-btn">Shop Now</a>
            </div>
        <?php else: ?>
            <div class="wishlist-grid">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="wishlist-item">
                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="product-image">
                        <h3 class="product-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="product-brand"><?php echo htmlspecialchars($item['brand_name']); ?></p>
                        <p class="product-price">₹<?php echo number_format($item['price'], 2); ?></p>
                        <div class="action-buttons">
                            <button class="move-to-cart" 
                                    data-product-id="<?php echo $item['product_id']; ?>">
                                Move to Cart
                            </button>
                            <button class="remove-from-wishlist" 
                                    data-product-id="<?php echo $item['product_id']; ?>">
                                Remove
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle remove from wishlist
        document.querySelectorAll('.remove-from-wishlist').forEach(button => {
            button.addEventListener('click', function() {
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
                        // Remove the item from the DOM
                        this.closest('.wishlist-item').remove();
                        
                        // Check if wishlist is empty
                        if (document.querySelectorAll('.wishlist-item').length === 0) {
                            location.reload(); // Reload to show empty state
                        }
                    }
                });
            });
        });

        // Handle move to cart
        document.querySelectorAll('.move-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const wishlistItem = this.closest('.wishlist-item');
                
                // First add to cart
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('add_to_cart', '1');

                fetch('add_to_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    // Then remove from wishlist
                    return fetch('toggle_wishlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            product_id: productId
                        })
                    });
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove item from wishlist display
                        wishlistItem.remove();
                        
                        // Check if wishlist is empty
                        if (document.querySelectorAll('.wishlist-item').length === 0) {
                            window.location.reload(); // Reload to show empty state
                        }
                        
                        // Redirect to cart page
                        window.location.href = 'add_to_cart.php';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
    });
    </script>
</body>
</html>