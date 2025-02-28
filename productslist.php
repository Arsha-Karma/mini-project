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

// Remove the login check since we want public access
// Only check session if we need user-specific features (like cart)
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

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
$stmt = $conn->prepare("SELECT seller_id FROM tbl_seller WHERE Signup_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$seller_info = $result->fetch_assoc();
$actual_seller_id = $seller_info['seller_id'];

// Check if image_path column exists before adding it
$checkColumnQuery = "SHOW COLUMNS FROM tbl_product LIKE 'image_path'";
$columnResult = $conn->query($checkColumnQuery);

if ($columnResult->num_rows === 0) {
    $alterTableQuery = "ALTER TABLE tbl_product ADD COLUMN image_path VARCHAR(255) AFTER description";
    if (!$conn->query($alterTableQuery)) {
        die("Failed to add image_path column: " . $conn->error);
    }
}

// Check if deleted column exists before adding it
$checkDeletedColumnQuery = "SHOW COLUMNS FROM tbl_product LIKE 'deleted'";
$deletedColumnResult = $conn->query($checkDeletedColumnQuery);

if ($deletedColumnResult->num_rows === 0) {
    $alterTableQuery = "ALTER TABLE tbl_product ADD COLUMN deleted BOOLEAN DEFAULT FALSE";
    if (!$conn->query($alterTableQuery)) {
        die("Failed to add deleted column: " . $conn->error);
    }
}

// Helper function to sanitize input
if (!function_exists('sanitize_input')) {
    function sanitize_input($conn, $input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

// Handle product operations
$message = "";
$error = "";

// Handle delete product
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    // Verify this product belongs to the seller
    $stmt = $conn->prepare("SELECT product_id FROM tbl_product WHERE product_id = ? AND seller_id = ? AND deleted = 0");
    $stmt->bind_param("ii", $product_id, $actual_seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Soft delete the product
        $stmt = $conn->prepare("UPDATE tbl_product SET deleted = 1 WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        
        if ($stmt->execute()) {
            $message = "Product deleted successfully";
        } else {
            $error = "Failed to delete product: " . $conn->error;
        }
    } else {
        $error = "You don't have permission to delete this product";
    }
}

// Get single product for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $product_id = $_GET['edit'];
    
    // Fetch product details with category, subcategory, and brand information
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name, s.name as subcategory_name, b.name as brand_name 
        FROM tbl_product p
        LEFT JOIN tbl_categories c ON p.category_id = c.category_id
        LEFT JOIN tbl_subcategories s ON p.subcategory_id = s.subcategory_id
        LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
        WHERE p.product_id = ? AND p.seller_id = ? AND p.deleted = 0
    ");
    $stmt->bind_param("ii", $product_id, $actual_seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($product);
        exit();
    } else {
        // Return error JSON
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Product not found or access denied']);
        exit();
    }
}

// 1. First, fetch categories
$categoryQuery = $conn->prepare("
    SELECT category_id, name 
    FROM tbl_categories 
    WHERE deleted = 0 
    ORDER BY name ASC
");
$categoryQuery->execute();
$categories = $categoryQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Fetch subcategories from tbl_subcategories
$subcategoryQuery = $conn->prepare("
    SELECT subcategory_id, name, category_id 
    FROM tbl_subcategories 
    WHERE deleted = 0 
    ORDER BY name ASC
");
$subcategoryQuery->execute();
$subcategories = $subcategoryQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Fetch all brands
$brandQuery = $conn->prepare("
    SELECT brand_id, name as brand_name
    FROM tbl_brands
    WHERE deleted = 0
    ORDER BY name ASC
");
$brandQuery->execute();
$brands = $brandQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Fetch all products with their related information
$productQuery = $conn->prepare("
    SELECT 
        p.*,
        c.name as category_name,
        s.name as subcategory_name,
        b.name as brand_name,
        COALESCE(p.image_path, 'default.jpg') as product_image
    FROM tbl_product p
    LEFT JOIN tbl_categories c ON p.category_id = c.category_id
    LEFT JOIN tbl_subcategories s ON p.subcategory_id = s.subcategory_id
    LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
    WHERE p.seller_id = ? AND p.deleted = 0
    ORDER BY p.created_at DESC
");
$productQuery->bind_param("i", $actual_seller_id);
$productQuery->execute();
$products = $productQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Update the form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_product') {
    // Sanitize inputs
    $name = sanitize_input($conn, $_POST['name']);
    $description = sanitize_input($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $subcategory_id = intval($_POST['subcategory_id']);
    $brand_id = intval($_POST['brand_id']);
    
    // Validation
    if (empty($name) || empty($description) || $price <= 0 || $stock < 0) {
        $error = "Please fill all required fields correctly";
    } else {
        if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
            // Update existing product
            $product_id = intval($_POST['product_id']);
            
            // Verify this product belongs to the seller
            $stmt = $conn->prepare("SELECT product_id FROM tbl_product WHERE product_id = ? AND seller_id = ? AND deleted = 0");
            $stmt->bind_param("ii", $product_id, $actual_seller_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Base update query
                $update_query = "UPDATE tbl_product SET 
                    name = ?, 
                    description = ?, 
                    price = ?, 
                    Stock_quantity = ?, 
                    category_id = ?,
                    subcategory_id = ?,
                    brand_id = ?";
                $params = [$name, $description, $price, $stock, $category_id, $subcategory_id, $brand_id];
                $types = "ssdiiii";
                
                // Handle image upload if new image is provided
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                    $image_path = handleImageUpload($_FILES['product_image']);
                    if ($image_path) {
                        $update_query .= ", image_path = ?";
                        $params[] = $image_path;
                        $types .= "s";
                    }
                }
                
                // Add WHERE clause and product_id parameter
                $update_query .= " WHERE product_id = ?";
                $params[] = $product_id;
                $types .= "i";
                
                // Prepare and execute the statement
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $message = "Product updated successfully";
                    header("Location: products.php");
                    exit();
                } else {
                    $error = "Failed to update product: " . $conn->error;
                }
            } else {
                $error = "You don't have permission to edit this product";
            }
        } else {
            // Add new product
            // Image is required for new products
            if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] != 0) {
                $error = "Please upload a product image";
            } else {
                $image_path = handleImageUpload($_FILES['product_image']);
                if ($image_path) {
                    $stmt = $conn->prepare("INSERT INTO tbl_product (seller_id, name, description, price, Stock_quantity, category_id, subcategory_id, brand_id, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issdiiiss", $actual_seller_id, $name, $description, $price, $stock, $category_id, $subcategory_id, $brand_id, $image_path);
                    
                    if ($stmt->execute()) {
                        $message = "Product added successfully";
                        header("Location: products.php");
                        exit();
                    } else {
                        $error = "Failed to add product: " . $conn->error;
                    }
                } else {
                    $error = "Failed to upload image";
                }
            }
        }
    }
}

// Helper function to handle image upload
function handleImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $upload_dir = 'uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $target_path;
    }
    
    return false;
}

// Function to fetch products by category
function getProductsByCategory($conn, $category_id = null) {
    $query = "
        SELECT p.*, c.name as category_name, b.name as brand_name 
        FROM tbl_product p
        LEFT JOIN tbl_categories c ON p.category_id = c.category_id
        LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
        WHERE p.deleted = 0 ";
    
    if ($category_id) {
        $query .= "AND p.category_id = ? ";
    }
    
    $query .= "ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($query);
    
    if ($category_id) {
        $stmt->bind_param("i", $category_id);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch all categories
$categories_query = "SELECT * FROM tbl_categories WHERE deleted = 0";
$categories = $conn->query($categories_query)->fetch_all(MYSQLI_ASSOC);

// Get products for selected category or all products
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : null;
$products = getProductsByCategory($conn, $selected_category);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            background-color: #000;
            color: #fff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            padding: 20px;
        }

        .product-card {
            background: #111;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image {
            height: 250px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-details {
            padding: 20px;
        }

        .product-title {
            color: #e8a87c;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .product-brand {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .product-description {
            color: #ddd;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .product-price {
            color: #e8a87c;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .add-to-cart {
            background: #e8a87c;
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            font-weight: bold;
        }

        .add-to-cart:hover {
            background: #d69668;
            transform: scale(1.02);
        }

        .categories-nav {
            background: #111;
            padding: 15px 0;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .categories-list {
            display: flex;
            justify-content: center;
            gap: 20px;
            list-style: none;
            flex-wrap: wrap;
        }

        .category-link {
            color: #fff;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .category-link:hover,
        .category-link.active {
            background: #e8a87c;
            color: #000;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }
        }

        /* Add navigation styles */
        nav {
            background-color: #000;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid #222;
        }

        .logo {
            color: #e8a87c;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            position: relative;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #111;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1001;
            border-radius: 4px;
            margin-top: 0.5rem;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-content a {
            color: white;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
        }

        .dropdown-content a:hover {
            background-color: #222;
            color: #e8a87c;
        }

        /* Adjust main content padding for fixed nav */
        .container {
            padding-top: 80px;
        }

        /* Add these styles for user menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu .fas {
            margin-right: 5px;
        }

        .dropdown-content {
            right: 0; /* Align dropdown to the right for user menu */
        }
    </style>
</head>
<body>
    <!-- Modified navigation bar to show login/register or user menu -->
    <nav>
        <a href="index.php" class="logo">Perfume Paradise</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <div class="dropdown">
                <a href="#" class="dropbtn">Categories</a>
                <div class="dropdown-content">
                    <?php foreach ($categories as $category): ?>
                        <a href="products.php?category=<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="#">Brands</a>
            <a href="#">Our Story</a>
            <?php if ($is_logged_in): ?>
                <div class="dropdown">
                    <a href="#" class="dropbtn">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <div class="dropdown-content">
                        <a href="profile.php">Profile</a>
                        <a href="orders.php">Orders</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <nav class="categories-nav">
            <ul class="categories-list">
                <li>
                    <a href="products.php" class="category-link <?php echo !$selected_category ? 'active' : ''; ?>">
                        All Products
                    </a>
                </li>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a href="products.php?category=<?php echo $category['category_id']; ?>" 
                           class="category-link <?php echo $selected_category == $category['category_id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="products-grid">
            <?php if (empty($products)): ?>
                <div style="text-align: center; grid-column: 1/-1; padding: 20px;">
                    <p>No products found in this category.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        <div class="product-details">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-brand"><?php echo htmlspecialchars($product['brand_name']); ?></p>
                            <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                            <p class="product-price">₹<?php echo number_format($product['price'], 2); ?></p>
                            <button class="add-to-cart" data-product-id="<?php echo $product['product_id']; ?>">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addToCartButtons = document.querySelectorAll('.add-to-cart');
        
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                
                <?php if ($is_logged_in): ?>
                // Add to cart functionality for logged-in users
                // Add your cart functionality here
                console.log('Adding product to cart:', productId);
                
                // Visual feedback
                this.innerHTML = '✓ Added to Cart';
                this.style.background = '#28a745';
                
                setTimeout(() => {
                    this.innerHTML = 'Add to Cart';
                    this.style.background = '#e8a87c';
                }, 2000);
                <?php else: ?>
                // Redirect to login for non-logged-in users
                window.location.href = 'login.php?redirect=products.php';
                <?php endif; ?>
            });
        });
    });
    </script>
</body>
</html>