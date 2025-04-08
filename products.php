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

// Add this after your session and database connection code
$stmt = $conn->prepare("SELECT verified_status FROM tbl_seller WHERE Signup_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$seller_status = $result->fetch_assoc();

if ($seller_status['verified_status'] !== 'verified') {
    $_SESSION['error_message'] = "Your account needs to be verified before you can manage products.";
    header('Location: seller-dashboard.php');
    exit();
}

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

// Helper function to check if product name exists
function checkProductNameExists($conn, $name, $seller_id, $product_id = null) {
    $query = "SELECT product_id FROM tbl_product WHERE name = ? AND seller_id = ? AND deleted = 0";
    $params = [$name, $seller_id];
    $types = "si";
    
    if ($product_id) {
        // Exclude current product when editing
        $query .= " AND product_id != ?";
        $params[] = $product_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Update the form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_product') {
    $name = sanitize_input($conn, $_POST['name']);
    $description = sanitize_input($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $size = sanitize_input($conn, $_POST['size']);
    
    // For editing existing product
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
    
    if (empty($name) || empty($description) || $price <= 0 || $stock <= 0 || empty($category_id) || empty($size)) {
        $error = "Please fill all required fields";
    } else {
        $image_path = '';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $image_path = handleImageUpload($_FILES['product_image']);
            if ($image_path === false) {
                $error = "Failed to upload image";
            }
        }
        
        if (empty($error)) {
            if ($product_id) {
                // Update existing product
                if (!empty($image_path)) {
                    // Update with new image
                    $update_query = "UPDATE tbl_product SET 
                        name = ?, 
                        description = ?, 
                        price = ?, 
                        Stock_quantity = ?, 
                        category_id = ?,
                        size = ?,
                        image_path = ?
                        WHERE product_id = ? AND seller_id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("ssdiissii", 
                        $name, 
                        $description, 
                        $price, 
                        $stock, 
                        $category_id, 
                        $size,
                        $image_path, 
                        $product_id, 
                        $actual_seller_id
                    );
                } else {
                    // Update without changing image
                    $update_query = "UPDATE tbl_product SET 
                        name = ?, 
                        description = ?, 
                        price = ?, 
                        Stock_quantity = ?, 
                        category_id = ?,
                        size = ?
                        WHERE product_id = ? AND seller_id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("ssdiisii", 
                        $name, 
                        $description, 
                        $price, 
                        $stock, 
                        $category_id,
                        $size, 
                        $product_id, 
                        $actual_seller_id
                    );
                }
            } else {
                // Insert new product
                $insert_query = "INSERT INTO tbl_product (
                    name, 
                    description, 
                    price, 
                    Stock_quantity, 
                    category_id, 
                    size,
                    image_path, 
                    seller_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("ssdiissi", 
                    $name, 
                    $description, 
                    $price, 
                    $stock, 
                    $category_id,
                    $size, 
                    $image_path, 
                    $actual_seller_id
                );
            }
            
            if ($stmt->execute()) {
                $message = $product_id ? "Product updated successfully" : "Product added successfully";
                header("Location: products.php?success=" . urlencode($message));
                exit;
            } else {
                $error = "Failed to " . ($product_id ? "update" : "add") . " product: " . $conn->error;
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

        .form-control:focus {
            border-color: #000000;
            box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.25);
        }

        .form-control.is-valid {
            border-color: #198754;
            padding-right: 0.75rem !important;
            background-image: none !important;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            padding-right: 0.75rem !important;
            background-image: none !important;
        }

        .validation-message {
            display: none;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            color: #dc3545;
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
        <a href="sales.php">Orders</a>
        <a href="customer_reviews.php">Customer Reviews</a>
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
                                <th>Size</th>
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
                                <td><?php echo htmlspecialchars($product['size']); ?></td>
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
                                    <input type="text" 
                                           class="form-control" 
                                           id="name" 
                                           name="name" 
                                           required
                                           pattern="[A-Za-z\s]+"
                                           onfocus="showValidationMessage(this, 'Product name must contain only letters and spaces (3-50 characters)')"
                                           onblur="validateProductName(this)"
                                           oninput="validateProductName(this)">
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="description" class="form-label required">Description</label>
                                </td>
                                <td>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="3"
                                              required
                                              onfocus="showValidationMessage(this, 'Description must contain only letters and spaces (10-500 characters)')"
                                              onblur="validateDescription(this)"
                                              oninput="validateDescription(this)"></textarea>
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
                                           max="8000" 
                                           required
                                           onfocus="showValidationMessage(this, 'Price must be greater than 0 and less than 8000')"
                                           onblur="validateOnBlur(this, 0, 8000, 'Price')"
                                           oninput="validateInput(this, 0, 8000, 'Price')">
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
                                           max="300" 
                                           required
                                           onfocus="showValidationMessage(this, 'Stock quantity must be greater than 0 and less than 300')"
                                           onblur="validateOnBlur(this, 0, 300, 'Stock quantity')"
                                           oninput="validateInput(this, 0, 300, 'Stock quantity')">
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
                                            onchange="validateCategory(this)"
                                            onfocus="showValidationMessage(this, 'Please select a category')">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['category_id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="validation-message"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="subcategory_id" class="form-label">Subcategory (Optional)</label>
                                </td>
                                <td>
                                    <select class="form-control" id="subcategory_id" name="subcategory_id">
                                        <option value="">Select Category First</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="brand_id" class="form-label">Brand (Optional)</label>
                                </td>
                                <td>
                                    <select class="form-control" id="brand_id" name="brand_id">
                                        <option value="">Select Subcategory First</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="size" class="form-label required">Size</label>
                                </td>
                                <td>
                                    <select class="form-control" id="size" name="size" required>
                                        <option value="">Select Size</option>
                                        <option value="10ml">10 ML</option>
                                        <option value="20ml">20 ML</option>
                                        <option value="50ml">50 ML</option>
                                        <option value="100ml">100 ML</option>
                                        <option value="200ml">200 ML</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <label for="product_image" class="form-label required">Product Image</label>
                                </td>
                                <td>
                                    <input type="file" 
                                           class="form-control" 
                                           id="product_image" 
                                           name="product_image" 
                                           required
                                           onchange="validateImage(this)"
                                           onfocus="showValidationMessage(this, 'Please select an image file (JPEG, PNG, or GIF, max 5MB)')">
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
            input.classList.add('is-invalid');
        }

        function hideValidationMessage(input) {
            const validationDiv = input.nextElementSibling;
            validationDiv.style.display = 'none';
        }

        function validateInput(input, minValue, maxValue, fieldName) {
            const value = parseFloat(input.value);
            const validationDiv = input.nextElementSibling;
            
            // Remove any existing validation states first
            input.classList.remove('is-valid', 'is-invalid');
            
            // Check if value is empty
            if (!input.value) {
                validationDiv.style.display = 'block';
                validationDiv.textContent = `${fieldName} is required`;
                validationDiv.style.color = '#dc3545';
                input.classList.add('is-invalid');
                return false;
            }
            
            // Check if value is within the valid range
            if (isNaN(value) || value <= minValue || value > maxValue) {
                validationDiv.style.display = 'block';
                validationDiv.textContent = `${fieldName} must be greater than ${minValue} and less than ${maxValue}`;
                validationDiv.style.color = '#dc3545';
                input.classList.add('is-invalid');
                return false;
            }
            
            // Valid input - hide error message
            validationDiv.style.display = 'none';
            input.classList.remove('is-invalid');
            return true;
        }

        function validateOnBlur(input, minValue, maxValue, fieldName) {
            const value = parseFloat(input.value);
            
            // Remove any existing validation states
            input.classList.remove('is-valid', 'is-invalid');
            
            // Check for empty or invalid number
            if (!input.value || isNaN(value)) {
                showValidationMessage(input, `${fieldName} is required`);
                input.classList.add('is-invalid');
                return false;
            }
            
            // Check range
            if (value <= minValue || value > maxValue) {
                showValidationMessage(input, `${fieldName} must be greater than ${minValue} and less than ${maxValue}`);
                input.classList.add('is-invalid');
                return false;
            }
            
            // Valid input - hide error message
            input.nextElementSibling.style.display = 'none';
            input.classList.remove('is-invalid');
            return true;
        }

        function validateForm() {
            let isValid = true;
            
            // Validate price (0-8000)
            const price = document.getElementById('price');
            isValid = validateInput(price, 0, 8000, 'Price') && isValid;
            
            // Validate stock (0-300)
            const stock = document.getElementById('stock');
            isValid = validateInput(stock, 0, 300, 'Stock quantity') && isValid;
            
            // Validate category
            const category = document.getElementById('category_id');
            isValid = validateCategory(category) && isValid;
            
            // Validate image
            const productImage = document.getElementById('product_image');
            isValid = validateImage(productImage) && isValid;
            
            // Validate size
            const size = document.getElementById('size');
            if (!size.value) {
                showValidationMessage(size, 'Perfume size is required');
                isValid = false;
            }
            
            return isValid;
        }

        // Add event listeners when the document loads
        document.addEventListener('DOMContentLoaded', function() {
            const priceInput = document.getElementById('price');
            const stockInput = document.getElementById('stock');
            
            if (priceInput) {
                priceInput.addEventListener('focus', function() {
                    showValidationMessage(this, 'Price must be greater than 0 and less than 8000');
                });

                priceInput.addEventListener('input', function() {
                    const value = parseFloat(this.value);
                    if (value > 0 && value <= 8000) {
                        this.nextElementSibling.style.display = 'none';
                        this.classList.remove('is-invalid');
                    } else {
                        showValidationMessage(this, 'Price must be greater than 0 and less than 8000');
                    }
                });

                priceInput.addEventListener('blur', function() {
                    validateOnBlur(this, 0, 8000, 'Price');
                });
            }
            
            if (stockInput) {
                stockInput.addEventListener('focus', function() {
                    showValidationMessage(this, 'Stock quantity must be greater than 0 and less than 300');
                });

                stockInput.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    if (value > 0 && value <= 300) {
                        this.nextElementSibling.style.display = 'none';
                        this.classList.remove('is-invalid');
                    } else {
                        showValidationMessage(this, 'Stock quantity must be greater than 0 and less than 300');
                    }
                });

                stockInput.addEventListener('blur', function() {
                    validateOnBlur(this, 0, 300, 'Stock quantity');
                });
            }

            const categorySelect = document.getElementById('category_id');
            const productImage = document.getElementById('product_image');
            
            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    validateCategory(this);
                });
            }
            
            if (productImage) {
                productImage.addEventListener('change', function() {
                    validateImage(this);
                });
            }

            // Add validation for size select
            const sizeSelect = document.getElementById('size');
            if (sizeSelect) {
                sizeSelect.addEventListener('focus', function() {
                    showValidationMessage(this, 'Please select a perfume size');
                });

                sizeSelect.addEventListener('change', function() {
                    validateSize(this);
                });

                sizeSelect.addEventListener('blur', function() {
                    validateSize(this);
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

                    // Set the size value
                    document.getElementById('size').value = product.size;

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

        // Add these validation functions
        function validateLettersAndSpaces(input, minLength, maxLength, fieldName) {
            const value = input.value.trim();
            const errorDiv = input.nextElementSibling;
            const pattern = /^[A-Za-z\s]+$/;
            
            // Check if empty
            if (value.length === 0) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = `${fieldName} is required`;
                errorDiv.style.display = 'block';
                return false;
            }
            
            // Check length
            if (value.length < minLength || value.length > maxLength) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = `${fieldName} must be between ${minLength} and ${maxLength} characters`;
                errorDiv.style.display = 'block';
                return false;
            }
            
            // Check for letters and spaces only
            if (!pattern.test(value)) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                errorDiv.textContent = `${fieldName} can only contain letters and spaces`;
                errorDiv.style.display = 'block';
                return false;
            }
            
            // All validations passed
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
            errorDiv.style.display = 'none';
            return true;
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

        // Add these new validation functions
        function validateCategory(select) {
            const validationDiv = select.nextElementSibling;
            
            if (select.value) {
                // Category is selected
                validationDiv.style.display = 'none';
                select.classList.add('is-valid');
                select.classList.remove('is-invalid');
                return true;
            } else {
                // No category selected
                validationDiv.style.display = 'block';
                validationDiv.textContent = 'Please select a category';
                validationDiv.style.color = '#dc3545';
                select.classList.add('is-invalid');
                select.classList.remove('is-valid');
                return false;
            }
        }

        function validateImage(input) {
            const validationDiv = input.nextElementSibling;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                if (!allowedTypes.includes(file.type)) {
                    validationDiv.style.display = 'block';
                    validationDiv.textContent = 'Please select a valid image file (JPEG, PNG, or GIF)';
                    validationDiv.style.color = '#dc3545';
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    return false;
                }
                
                if (file.size > maxSize) {
                    validationDiv.style.display = 'block';
                    validationDiv.textContent = 'Image size must be less than 5MB';
                    validationDiv.style.color = '#dc3545';
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    return false;
                }
                
                // Valid image file
                validationDiv.style.display = 'none';
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
                return true;
            }
            
            // No file selected
            validationDiv.style.display = 'block';
            validationDiv.textContent = 'Please select an image file';
            validationDiv.style.color = '#dc3545';
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        }

        // Add this new function to check product name via AJAX
        function checkProductNameExists(name, productId = null) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'check_product_name');
                formData.append('name', name);
                if (productId) {
                    formData.append('product_id', productId);
                }

                fetch('check_product_name.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => resolve(data.exists))
                .catch(error => reject(error));
            });
        }

        // Update the validateProductName function
        async function validateProductName(input) {
            const value = input.value.trim();
            const validationDiv = input.nextElementSibling;
            const pattern = /^[A-Za-z\s]+$/;
            
            // Remove any existing validation states
            input.classList.remove('is-invalid');
            
            // Check if empty
            if (!value) {
                showValidationMessage(input, 'Product name is required');
                return false;
            }
            
            // Check length
            if (value.length < 3 || value.length > 50) {
                showValidationMessage(input, 'Product name must be between 3 and 50 characters');
                return false;
            }
            
            // Check for letters and spaces only
            if (!pattern.test(value)) {
                showValidationMessage(input, 'Product name can only contain letters and spaces');
                return false;
            }

            try {
                // Get the product ID if editing
                const productId = document.getElementById('edit_product_id')?.value;
                
                // Check if name exists
                const exists = await checkProductNameExists(value, productId);
                if (exists) {
                    showValidationMessage(input, 'A product with this name already exists');
                    return false;
                }
                
                // Valid input - hide error message
                validationDiv.style.display = 'none';
                input.classList.remove('is-invalid');
                return true;
            } catch (error) {
                console.error('Error checking product name:', error);
                showValidationMessage(input, 'Error checking product name');
                return false;
            }
        }

        function validateDescription(input) {
            const value = input.value.trim();
            const validationDiv = input.nextElementSibling;
            // Updated pattern to allow letters, whitespaces, and special characters
            const pattern = /^[A-Za-z\s\.,!@#$%^&*()_\-+=\[\]{};:'"\\|/<>?`~]+$/;
            
            // Remove any existing validation states
            input.classList.remove('is-invalid');
            
            // Check if empty
            if (!value) {
                showValidationMessage(input, 'Description is required');
                return false;
            }
            
            // Check length
            if (value.length < 10 || value.length > 500) {
                showValidationMessage(input, 'Description must be between 10 and 500 characters');
                return false;
            }
            
            // Check for allowed characters
            if (!pattern.test(value)) {
                showValidationMessage(input, 'Description can only contain letters, spaces, and special characters');
                return false;
            }
            
            // Valid input - hide error message
            validationDiv.style.display = 'none';
            input.classList.remove('is-invalid');
            return true;
        }

        function validateSize(select) {
            const validationDiv = select.nextElementSibling;
            
            if (select.value) {
                // Size is selected
                validationDiv.style.display = 'none';
                select.classList.add('is-valid');
                select.classList.remove('is-invalid');
                return true;
            } else {
                // No size selected
                validationDiv.style.display = 'block';
                validationDiv.textContent = 'Please select a perfume size';
                validationDiv.style.color = '#dc3545';
                select.classList.add('is-invalid');
                select.classList.remove('is-valid');
                return false;
            }
        }
    </script>
</body>
</html>