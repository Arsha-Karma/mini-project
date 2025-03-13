<?php
session_start();
require_once 'dbconnect.php';

// Remove the seller-specific code that was causing the error
// We don't need seller information for the product listing page
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Fetch all active products
$query = "
    SELECT p.*, c.name as category_name, b.name as brand_name 
    FROM tbl_product p
    LEFT JOIN tbl_categories c ON p.category_id = c.category_id
    LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
    WHERE p.deleted = 0
    ORDER BY p.created_at DESC
";
$result = $conn->query($query);
$products = $result->fetch_all(MYSQLI_ASSOC);

// Fetch categories for the navigation
$categoryQuery = "SELECT * FROM tbl_categories WHERE deleted = 0 ORDER BY name";
$categories = $conn->query($categoryQuery)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Perfume Paradise - Products</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
        }

        nav {
            background-color: #1a1a1a;
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            z-index: 1000;
        }

        .logo {
            color: #e8d1c5;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 20px 20px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
        }

        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .new-label {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ff69b4;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        .product-image {
            height: 250px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-details {
            padding: 1.5rem;
            text-align: center;
        }

        .product-title {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .product-brand {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 1.25rem;
            color: #1a1a1a;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .add-to-cart {
            background: #1a1a1a;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease;
        }

        .add-to-cart:hover {
            background: #333;
        }

        .categories-nav {
            background: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .categories-list {
            display: flex;
            list-style: none;
            overflow-x: auto;
            padding: 0 1rem;
            gap: 1rem;
        }

        .category-link {
            color: #333;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .category-link:hover,
        .category-link.active {
            background: #1a1a1a;
            color: white;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <nav>
        <a href="index.php" class="logo">Perfume Paradise</a>
    </nav>

    <div class="container">
        <div class="categories-nav">
            <ul class="categories-list">
                <li><a href="productslist.php" class="category-link active">All Perfumes</a></li>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a href="?category=<?php echo $category['category_id']; ?>" 
                           class="category-link">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="new-label">NEW</div>
                    <div class="product-image">
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="product-details">
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-brand"><?php echo htmlspecialchars($product['brand_name']); ?></p>
                        <p class="product-price">₹<?php echo number_format($product['price'], 2); ?></p>
                        <button class="add-to-cart" data-product-id="<?php echo $product['product_id']; ?>">
                            Add to Cart
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addToCartButtons = document.querySelectorAll('.add-to-cart');
        
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                
                <?php if ($is_logged_in): ?>
                    this.innerHTML = '✓ Added to Cart';
                    this.style.background = '#28a745';
                    
                    setTimeout(() => {
                        this.innerHTML = 'Add to Cart';
                        this.style.background = '#1a1a1a';
                    }, 2000);
                <?php else: ?>
                    window.location.href = 'login.php?redirect=productslist.php';
                <?php endif; ?>
            });
        });
    });
    </script>
</body>
</html>