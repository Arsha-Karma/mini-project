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
    SELECT b.brand_id, b.name as brand_name, b.subcategory_id
    FROM tbl_brands b
    WHERE b.deleted = 0
    ORDER BY b.name ASC
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Perfume Paradise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
            background-color: #1a1a1a !important;
            height: 100vh;
            position: fixed;
            color: white;
        }

        .sidebar h2, .sidebar h3 {
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

        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        thead {
            background-color: #1a1a1a !important;
            color: white;
        }

        th {
            color: white;
            font-weight: bold;
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .btn-action {
            margin-right: 5px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .form-label {
            font-weight: bold;
        }

        .required::after {
            content: " *";
            color: red;
        }

        .product-image-preview {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
        }
        
        /* Validation styles */
        .error-message {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: none;
        }

        .is-invalid {
            border-color: #dc3545 !important;
            padding-right: calc(1.5em + 0.75rem) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right calc(0.375em + 0.1875rem) center !important;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
        }

        .is-valid {
            border-color: #198754 !important;
            padding-right: calc(1.5em + 0.75rem) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right calc(0.375em + 0.1875rem) center !important;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
        }

        .validation-message {
            display: none;
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .form-control:focus {
            border-color: #000000;
            box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.25);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: none;
        }

        /* Add some smooth transition */
        #productFormCard {
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #000000 !important;
            border-color: #000000 !important;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #1a1a1a !important;
            border-color: #1a1a1a !important;
        }

        /* Form table styles */
        .table-borderless td {
            padding: 10px;
            vertical-align: middle;
        }

        .form-label.required:after {
            content: " *";
            color: red;
        }

        .validation-message {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 4px;
        }

        /* Ensure consistent width for form controls */
        .form-control {
            width: 100%;
            max-width: 100%;
        }

        /* Style for the buttons container */
        .text-end {
            text-align: right;
            padding: 15px 0;
        }

        .text-end button {
            margin-left: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table-borderless td {
                display: block;
                width: 100%;
            }
            
            .table-borderless td:first-child {
                padding-bottom: 0;
            }
            
            .text-end {
                text-align: center;
            }
            
            .text-end button {
                margin: 5px;
            }
        }

        .nav-link {
            color: white !important;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: #000000;
        }

        .modal-header {
            background-color: #000000 !important;
            color: white;
        }

        /* Header Title */
        .header-title {
            background-color: #000000 !important;
            color: white;
            padding: 15px;
        }

        /* Product Section Headers */
        .product-section .card-header {
            background-color: #000000 !important;
            color: white !important;
        }

        /* Form Section Headers */
        .form-section-header {
            background-color: #000000 !important;
            color: white;
            padding: 10px 15px;
        }

        /* Table Headers */
        thead th {
            background-color: #000000 !important;
            color: white !important;
        }

        /* Modal Headers */
        .modal-header {
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
    </style>
</head>
<body>
    <div class="sidebar"><br>
    <h4 style="color: white; text-align: center;">Perfume Paradise</h4>
        <a href="seller-dashboard.php">Dashboard</a>
        <a href="index.php">Home</a>
        <a href="profile.php">Edit Profile</a>
        <a href="products.php" class="active">Products</a>
        <a href="sales.php">Sales</a>
        <a href="reviews.php">Customer Reviews</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1 style="color: #000000;" >Product Management</h1>
            <div class="mb-4">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Product List -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Your Products</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Category</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($product['Stock_quantity']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editProduct(<?php echo $product['product_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" 
                                            onclick="deleteProduct(<?php echo $product['product_id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                        <input type="hidden" name="action" value="save_product">
                        <table class="table table-borderless">
                            <tr>
                                <td width="25%">
                                    <label for="name" class="form-label required">Product Name</label>
                                </td>
                                <td width="75%">
                                    <input type="text" class="form-control" id="name" name="name" 
                                           onfocus="showValidationMessage(this, 'Product name must be between 3 and 50 characters')"
                                           onblur="validateField(this, 3, 50)">
                                    <div class="error-message"></div>
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="description" class="form-label required">Description</label>
                                </td>
                                <td>
                                    <textarea class="form-control" id="description" name="description" rows="3"
                                              onfocus="showValidationMessage(this, 'Description must be between 10 and 200 characters')"
                                              onblur="validateField(this, 10, 200)"></textarea>
                                    <div class="error-message"></div>
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="price" class="form-label required">Price (₹)</label>
                                </td>
                                <td>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           step="0.01" 
                                           min="0.01" 
                                           required
                                           onfocus="showValidationMessage(this, 'Price must be greater than 0')"
                                           onblur="validateNumericField(this, 0, 'Price')">
                                    <div class="error-message"></div>
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="stock" class="form-label required">Stock Quantity</label>
                                </td>
                                <td>
                                    <input type="number" 
                                           class="form-control" 
                                           id="stock" 
                                           name="stock" 
                                           min="1" 
                                           required
                                           onfocus="showValidationMessage(this, 'Stock quantity must be greater than 0')"
                                           onblur="validateNumericField(this, 0, 'Stock quantity')">
                                    <div class="error-message"></div>
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="category_id" class="form-label required">Category</label>
                                </td>
                                <td>
                                    <select class="form-control" 
                                            id="category_id" 
                                            name="category_id" 
                                            required
                                            onfocus="showValidationMessage(this, 'Please select a category')"
                                            onblur="validateSelect(this)">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['category_id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="error-message"></div>
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="subcategory_id" class="form-label">Subcategory</label>
                                </td>
                                <td>
                                    <select class="form-control" 
                                            id="subcategory_id" 
                                            name="subcategory_id">
                                        <option value="">Select Category First</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="brand_id" class="form-label">Brand</label>
                                </td>
                                <td>
                                    <select class="form-control" 
                                            id="brand_id" 
                                            name="brand_id">
                                        <option value="">Select Subcategory First</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="product_image" class="form-label required">Product Image</label>
                                </td>
                                <td>
                                    <input type="file" class="form-control" id="product_image" name="product_image" required
                                           onfocus="showValidationMessage(this, 'Please select an image file (JPEG, PNG, or GIF, max 5MB)')"
                                           onchange="validateImage(this)">
                                    <div class="error-message"></div>
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2" class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Product</button>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        function showValidationMessage(input, message) {
            const validationDiv = input.nextElementSibling;
            validationDiv.style.display = 'block';
            validationDiv.textContent = message;
            validationDiv.style.color = '#dc3545';
        }

        function hideValidationMessage(input) {
            const validationDiv = input.nextElementSibling;
            validationDiv.style.display = 'none';
        }

        function validateField(input, minLength, maxLength) {
            const value = input.value.trim();
            const errorDiv = input.nextElementSibling;
            
            if (value.length < minLength || value.length > maxLength) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = `Length must be between ${minLength} and ${maxLength} characters`;
                errorDiv.style.display = 'block';
                return false;
            } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                errorDiv.style.display = 'none';
                return true;
            }
        }

        function validateNumericField(input, minValue, fieldName) {
            const value = parseFloat(input.value);
            const errorDiv = input.nextElementSibling;
            
            if (isNaN(value) || value <= minValue) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = `${fieldName} must be greater than ${minValue}`;
                errorDiv.style.display = 'block';
                return false;
            }
            
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            errorDiv.style.display = 'none';
            return true;
        }

        function validateSelect(select) {
            const errorDiv = select.nextElementSibling;
            
            if (!select.value) {
                select.classList.add('is-invalid');
                select.classList.remove('is-valid');
                errorDiv.textContent = 'This field is required';
                errorDiv.style.display = 'block';
                return false;
            }
            
            select.classList.add('is-valid');
            select.classList.remove('is-invalid');
            errorDiv.style.display = 'none';
            return true;
        }

        function validateImage(input) {
            const errorDiv = input.nextElementSibling;
            const file = input.files[0];
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!file) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = 'Please select an image';
                errorDiv.style.display = 'block';
                return false;
            }
            
            if (!validTypes.includes(file.type)) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = 'Please select a valid image file (JPEG, PNG, or GIF)';
                errorDiv.style.display = 'block';
                return false;
            }
            
            if (file.size > maxSize) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = 'Image size must be less than 5MB';
                errorDiv.style.display = 'block';
                return false;
            }
            
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            errorDiv.style.display = 'none';
            return true;
        }

        function validateForm() {
            let isValid = true;
            const name = document.getElementById('name');
            const description = document.getElementById('description');
            const price = document.getElementById('price');
            const stock = document.getElementById('stock');
            const category = document.getElementById('category_id');
            const subcategory = document.getElementById('subcategory_id');
            const brand = document.getElementById('brand_id');
            const image = document.getElementById('product_image');
            
            // Validate product name
            if (name.value.length < 3 || name.value.length > 50) {
                showValidationMessage(name, 'Product name must be between 3 and 50 characters');
                name.classList.add('is-invalid');
                name.classList.remove('is-valid');
                isValid = false;
            } else {
                name.classList.add('is-valid');
                name.classList.remove('is-invalid');
                hideValidationMessage(name);
            }
            
            // Validate description
            if (description.value.length < 10 || description.value.length > 200) {
                showValidationMessage(description, 'Description must be between 10 and 200 characters');
                description.classList.add('is-invalid');
                description.classList.remove('is-valid');
                isValid = false;
            } else {
                description.classList.add('is-valid');
                description.classList.remove('is-invalid');
                hideValidationMessage(description);
            }
            
            // Validate price
            if (parseFloat(price.value) <= 0) {
                showValidationMessage(price, 'Price must be greater than 0');
                price.classList.add('is-invalid');
                price.classList.remove('is-valid');
                isValid = false;
            } else {
                price.classList.add('is-valid');
                price.classList.remove('is-invalid');
                hideValidationMessage(price);
            }
            
            // Validate stock
            if (parseInt(stock.value) < 0) {
                showValidationMessage(stock, 'Stock quantity must be 0 or greater');
                stock.classList.add('is-invalid');
                stock.classList.remove('is-valid');
                isValid = false;
            } else {
                stock.classList.add('is-valid');
                stock.classList.remove('is-invalid');
                hideValidationMessage(stock);
            }
            
            // Validate category
            if (!category.value) {
                showValidationMessage(category, 'Please select a category');
                category.classList.add('is-invalid');
                category.classList.remove('is-valid');
                isValid = false;
            } else {
                category.classList.add('is-valid');
                category.classList.remove('is-invalid');
                hideValidationMessage(category);
            }
            
            // Validate subcategory
            if (!subcategory.value) {
                showValidationMessage(subcategory, 'Please select a subcategory');
                subcategory.classList.add('is-invalid');
                subcategory.classList.remove('is-valid');
                isValid = false;
            } else {
                subcategory.classList.add('is-valid');
                subcategory.classList.remove('is-invalid');
                hideValidationMessage(subcategory);
            }
            
            // Validate brand
            if (!brand.value) {
                showValidationMessage(brand, 'Please select a brand');
                brand.classList.add('is-invalid');
                brand.classList.remove('is-valid');
                isValid = false;
            } else {
                brand.classList.add('is-valid');
                brand.classList.remove('is-invalid');
                hideValidationMessage(brand);
            }
            
            // Validate image
            if (image.value === '' && !document.getElementById('edit_product_id')) {
                showValidationMessage(image, 'Please select an image');
                image.classList.add('is-invalid');
                image.classList.remove('is-valid');
                isValid = false;
            } else if (image.files.length > 0) {
                const file = image.files[0];
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!validTypes.includes(file.type)) {
                    showValidationMessage(image, 'Please select a valid image file (JPEG, PNG, or GIF)');
                    image.classList.add('is-invalid');
                    image.classList.remove('is-valid');
                    isValid = false;
                } else if (file.size > maxSize) {
                    showValidationMessage(image, 'Image size must be less than 5MB');
                    image.classList.add('is-invalid');
                    image.classList.remove('is-valid');
                    isValid = false;
                } else {
                    image.classList.add('is-valid');
                    image.classList.remove('is-invalid');
                    hideValidationMessage(image);
                }
            }
            
            return isValid;
        }

        // Add event listeners for real-time validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('productForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const isValid = validateForm();
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
            }
        });

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                window.location.href = `products.php?delete=${productId}`;
            }
        }

        function editProduct(productId) {
            fetch(`products.php?edit=${productId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(product => {
                    if (product.error) {
                        throw new Error(product.error);
                    }
                    
                    // Clear any existing validation states
                    const formElements = document.getElementById('productForm').elements;
                    for (let element of formElements) {
                        element.classList.remove('is-valid', 'is-invalid');
                        const errorDiv = element.nextElementSibling;
                        if (errorDiv && errorDiv.classList.contains('error-message')) {
                            errorDiv.style.display = 'none';
                        }
                    }

                    // Remove any existing image preview
                    const existingPreview = document.querySelector('.image-preview');
                    if (existingPreview) {
                        existingPreview.remove();
                    }

                    // Populate the form
                    document.getElementById('name').value = product.name;
                    document.getElementById('description').value = product.description;
                    document.getElementById('price').value = product.price;
                    document.getElementById('stock').value = product.Stock_quantity;
                    
                    // Set category and trigger change event
                    const categorySelect = document.getElementById('category_id');
                    categorySelect.value = product.category_id;
                    categorySelect.dispatchEvent(new Event('change'));

                    // Set subcategory after a small delay to ensure categories are loaded
                    setTimeout(() => {
                        const subcategorySelect = document.getElementById('subcategory_id');
                        subcategorySelect.value = product.subcategory_id;
                        subcategorySelect.dispatchEvent(new Event('change'));

                        // Set brand after another small delay
                        setTimeout(() => {
                            const brandSelect = document.getElementById('brand_id');
                            brandSelect.value = product.brand_id;
                        }, 100);
                    }, 100);

                    // Add hidden product ID field
                    let productIdInput = document.getElementById('edit_product_id');
                    if (!productIdInput) {
                        productIdInput = document.createElement('input');
                        productIdInput.type = 'hidden';
                        productIdInput.id = 'edit_product_id';
                        productIdInput.name = 'product_id';
                        document.getElementById('productForm').appendChild(productIdInput);
                    }
                    productIdInput.value = productId;

                    // Show current image if exists
                    if (product.image_path) {
                        const imagePreview = document.createElement('div');
                        imagePreview.className = 'image-preview mt-2';
                        imagePreview.innerHTML = `
                            <img src="${product.image_path}" alt="Current product image" 
                                 style="max-width: 100px; max-height: 100px;">
                            <p class="small text-muted mt-1">Current image</p>
                        `;
                        document.getElementById('product_image').parentNode.appendChild(imagePreview);
                    }

                    // Make image upload optional for editing
                    document.getElementById('product_image').removeAttribute('required');

                    // Update form title and button text
                    document.querySelector('#productModalLabel').textContent = 'Edit Product';
                    document.querySelector('button[type="submit"]').textContent = 'Update Product';

                    // Show the modal
                    const productModal = new bootstrap.Modal(document.getElementById('productModal'));
                    productModal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load product details: ' + error.message);
                });
        }

        // Update the form reset handling when modal is closed
        document.getElementById('productModal').addEventListener('hidden.bs.modal', function () {
            // Reset form
            document.getElementById('productForm').reset();
            
            // Remove edit product ID if exists
            const editProductId = document.getElementById('edit_product_id');
            if (editProductId) {
                editProductId.remove();
            }
            
            // Remove image preview if exists
            const imagePreview = document.querySelector('.image-preview');
            if (imagePreview) {
                imagePreview.remove();
            }
            
            // Reset form title and button text
            document.querySelector('#productModalLabel').textContent = 'Add New Product';
            document.querySelector('button[type="submit"]').textContent = 'Save Product';
            
            // Make image upload required for new products
            document.getElementById('product_image').setAttribute('required', 'required');
            
            // Reset validation states
            const formElements = document.getElementById('productForm').elements;
            for (let element of formElements) {
                element.classList.remove('is-valid', 'is-invalid');
                const errorDiv = element.nextElementSibling;
                if (errorDiv && errorDiv.classList.contains('error-message')) {
                    errorDiv.style.display = 'none';
                }
            }
        });

        // Update the validation function to handle edit mode
        function validateForm() {
            let isValid = true;
            const isEditMode = document.getElementById('edit_product_id') !== null;
            
            // Validate all fields
            isValid = validateField(document.getElementById('name'), 3, 50) && isValid;
            isValid = validateField(document.getElementById('description'), 10, 200) && isValid;
            isValid = validateNumericField(document.getElementById('price'), 0, 'Price') && isValid;
            isValid = validateNumericField(document.getElementById('stock'), 0, 'Stock quantity') && isValid;
            isValid = validateSelect(document.getElementById('category_id')) && isValid;
            
            // Validate image only if uploaded in edit mode or if it's a new product
            const imageInput = document.getElementById('product_image');
            if (imageInput.files.length > 0 || !isEditMode) {
                isValid = validateImage(imageInput) && isValid;
            }
            
            return isValid;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Get the select elements
            const categorySelect = document.getElementById('category_id');
            const subcategorySelect = document.getElementById('subcategory_id');
            const brandSelect = document.getElementById('brand_id');

            // Store all subcategories and brands data
            const allSubcategories = <?php echo json_encode($subcategories); ?>;
            const allBrands = <?php echo json_encode($brands); ?>;

            // Function to update subcategories based on selected category
            function updateSubcategories() {
                const selectedCategoryId = parseInt(categorySelect.value);
                
                // Clear current options
                subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                
                // Filter subcategories for selected category
                const filteredSubcategories = allSubcategories.filter(
                    sub => parseInt(sub.category_id) === selectedCategoryId
                );

                // Add filtered subcategories to select
                filteredSubcategories.forEach(sub => {
                    const option = new Option(sub.name, sub.subcategory_id);
                    subcategorySelect.add(option);
                });

                // Reset brands when category changes
                brandSelect.innerHTML = '<option value="">Select Brand</option>';
            }

            // Function to update brands based on selected subcategory
            function updateBrands() {
                const selectedSubcategoryId = parseInt(subcategorySelect.value);
                
                // Clear current options
                brandSelect.innerHTML = '<option value="">Select Brand</option>';
                
                // Filter brands for selected subcategory
                const filteredBrands = allBrands.filter(
                    brand => parseInt(brand.subcategory_id) === selectedSubcategoryId
                );

                // Add filtered brands to select
                filteredBrands.forEach(brand => {
                    const option = new Option(brand.brand_name, brand.brand_id);
                    brandSelect.add(option);
                });
            }

            // Add event listeners
            categorySelect.addEventListener('change', updateSubcategories);
            subcategorySelect.addEventListener('change', updateBrands);

            // Update validation functions
            function validateNumericField(input, minValue, fieldName) {
                const value = parseFloat(input.value);
                const errorMessage = input.nextElementSibling;
                
                if (isNaN(value) || value <= minValue) {
                    showValidationMessage(input, `${fieldName} must be greater than ${minValue}`);
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    return false;
                }
                
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                hideValidationMessage(input);
                return true;
            }

            // Add input event listeners for real-time validation
            document.getElementById('price').addEventListener('input', function() {
                validateNumericField(this, 0, 'Price');
            });

            document.getElementById('stock').addEventListener('input', function() {
                validateNumericField(this, 0, 'Stock quantity');
            });
        });
    </script>
</body>
</html>