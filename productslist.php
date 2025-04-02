<?php
session_start();
require_once 'dbconnect.php';

// Fetch categories first
$categoryQuery = "SELECT * FROM tbl_categories WHERE deleted = 0 ORDER BY name";
$categoryResult = $conn->query($categoryQuery);
$categories = $categoryResult->fetch_all(MYSQLI_ASSOC);

// Fetch unique brands (preventing duplicate brand names)
$brandQuery = "
    SELECT MIN(b.brand_id) as brand_id, b.name 
    FROM tbl_brands b 
    LEFT JOIN tbl_product p ON b.brand_id = p.brand_id
    WHERE b.deleted = 0 
        AND p.deleted = 0
        AND p.Stock_quantity > 0
    GROUP BY LOWER(b.name)  -- Group by lowercase name to handle case variations
    ORDER BY b.name ASC
";
$brandResult = $conn->query($brandQuery);
$brands = [];

if ($brandResult) {
    while ($row = $brandResult->fetch_assoc()) {
        $brands[] = [
            'brand_id' => $row['brand_id'],
            'name' => ucwords(strtolower($row['name'])) // Ensure consistent capitalization
        ];
    }
}

// Fetch unique sizes from products
$sizeQuery = "
    SELECT DISTINCT size 
    FROM tbl_product 
    WHERE deleted = 0 
        AND size IS NOT NULL 
        AND size != ''
        AND Stock_quantity > 0
    ORDER BY 
        CASE 
            WHEN size LIKE '%ml' THEN CAST(SUBSTRING_INDEX(size, 'ml', 1) AS DECIMAL)
            ELSE 0 
        END ASC";
$sizeResult = $conn->query($sizeQuery);
$sizes = [];

if ($sizeResult) {
    while ($row = $sizeResult->fetch_assoc()) {
        if (!empty($row['size'])) {
            $sizes[] = $row['size'];
        }
    }
}

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$subcategory_id = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : null;
$brand_id = isset($_GET['brand']) ? (int)$_GET['brand'] : null;

// Build the WHERE clause based on filters
$where_conditions = ['p.deleted = 0'];
if ($category_id) {
    $where_conditions[] = "p.category_id = " . $category_id;
}
if ($subcategory_id) {
    $where_conditions[] = "p.subcategory_id = " . $subcategory_id;
}
if ($brand_id) {
    $where_conditions[] = "p.brand_id = " . $brand_id;
}

$where_clause = implode(' AND ', $where_conditions);

// Modify your product query to include the filters
$query = "SELECT p.*, c.name as category_name, b.name as brand_name 
          FROM tbl_product p
          LEFT JOIN tbl_categories c ON p.category_id = c.category_id
          LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
          WHERE $where_clause
          ORDER BY p.created_at DESC";

$result = $conn->query($query);
if (!$result) {
    die("Query failed: " . $conn->error);
}
$products = $result->fetch_all(MYSQLI_ASSOC);

// Get current category name if category is selected
$current_category_name = '';
if ($category_id) {
    foreach ($categories as $category) {
        if ($category['category_id'] == $category_id) {
            $current_category_name = $category['name'];
            break;
        }
    }
}
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
            font-family: 'Roboto', sans-serif;
        }

        body {
            background-color: #f1f3f6;
            color: #212121;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 60px 10px 20px;
        }

        .main-content {
            width: 100%;
        }

        .categories-nav {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }

        .categories-list {
            display: flex;
            list-style: none;
            overflow-x: auto;
            gap: 15px;
            padding-bottom: 5px;
        }

        .category-link {
            color: #212121;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s;
            font-size: 14px;
        }

        .category-link:hover,
        .category-link.active {
            background: #2874f0;
            color: white;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .product-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }

        .product-link {
            text-decoration: none;
            color: inherit;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-image {
            width: 100%;
            aspect-ratio: 1;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-details {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 16px;
            margin: 0 0 8px;
            color: #212121;
            font-weight: 500;
        }

        .product-brand {
            font-size: 14px;
            color: #666;
            margin: 0 0 8px;
        }

        .product-price {
            font-size: 18px;
            color: #212121;
            font-weight: 600;
            margin: 0 0 8px;
        }

        .stock-warning {
            color: #ff6b6b;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: auto;
        }

        .add-to-cart-form {
            padding: 15px;
            margin-top: auto;
        }

        .add-to-cart-btn, 
        .out-of-stock-btn {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-to-cart-btn {
            background: #ff9f00;
            color: white;
        }

        .add-to-cart-btn:hover {
            background: #ff9100;
        }

        .out-of-stock-btn {
            background: #e0e0e0;
            color: #666;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
                padding: 15px;
            }

            .product-title {
                font-size: 14px;
            }

            .product-brand {
                font-size: 12px;
            }

            .product-price {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 10px;
                padding: 10px;
            }
        }

        .stock-warning {
            color: #B12704;
            font-size: 0.9em;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stock-warning i {
            color: #B12704;
        }

        .out-of-stock-btn {
            width: 100%;
            padding: 12px;
            background: #dddddd;
            color: #666666;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: not-allowed;
            margin-bottom: 8px;
        }

        .notify-btn {
            width: 100%;
            padding: 10px;
            background: #f8f9fa;
            color: #2874f0;
            border: 1px solid #2874f0;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .notify-btn:hover {
            background: #e8f0fe;
        }

        .add-to-cart-btn:disabled {
            background: #dddddd;
            cursor: not-allowed;
        }

        .no-products {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }

        .restricted-btn {
            width: 100%;
            padding: 12px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .restricted-btn:hover {
            background: #e0e0e0;
        }

        .login-message {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .login-message-content {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .login-message-content p {
            margin-bottom: 15px;
            font-size: 16px;
            color: #333;
        }

        .login-message-content button {
            padding: 8px 20px;
            background: #2874f0;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .login-message-content button:hover {
            background: #1c5ac7;
        }

        .main-content-wrapper {
            display: flex;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 10px;
        }

        .filters-sidebar {
            width: 280px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .filter-section {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .filter-section:last-child {
            border-bottom: none;
        }

        .filter-section h3 {
            font-size: 18px;
            color: #212121;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-section h4 {
            font-size: 14px;
            color: #212121;
            margin-bottom: 10px;
        }

        .clear-filters {
            background: none;
            border: none;
            color: #2874f0;
            cursor: pointer;
            font-size: 14px;
        }

        .price-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .price-inputs input {
            width: 100px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .apply-filter {
            background: #2874f0;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }

        .filter-search {
            margin-bottom: 10px;
        }

        .filter-search input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .filter-options {
            max-height: 200px;
            overflow-y: auto;
        }

        .filter-option {
            display: flex;
            align-items: center;
            padding: 8px 0;
            cursor: pointer;
            font-size: 14px;
            color: #212121;
            position: relative;
        }

        .filter-option:hover {
            background-color: #f5f5f5;
        }

        .filter-option input[type="checkbox"],
        .filter-option input[type="radio"] {
            margin-right: 10px;
        }

        .checkmark {
            position: relative;
            display: inline-block;
            width: 18px;
            height: 18px;
            margin-right: 10px;
            border: 2px solid #2874f0;
            border-radius: 2px;
        }

        .filter-option input:checked + .checkmark:after {
            content: '';
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid #2874f0;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .products-grid {
            flex: 1;
        }

        /* Mobile responsiveness */
        @media (max-width: 992px) {
            .main-content-wrapper {
                flex-direction: column;
            }

            .filters-sidebar {
                width: 100%;
                position: static;
            }
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            display: none;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2874f0;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .subcategory-options {
            margin-left: 20px;
            display: none;
        }

        .subcategory-options.active {
            display: block;
        }

        .filter-option.subcategory {
            font-size: 13px;
            padding: 6px 0;
        }

        .category-arrow {
            float: right;
            transition: transform 0.3s;
        }

        .category-arrow.active {
            transform: rotate(180deg);
        }

        .view-only-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .view-only-btn:hover {
            background-color: #5a6268;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 8px 16px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <main class="main-content">
            <div class="categories-nav">
                <ul class="categories-list">
                    <li>
                        <a href="productslist.php" 
                           class="category-link <?php echo !$category_id ? 'active' : ''; ?>">
                            All Perfumes
                        </a>
                    </li>
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="?category=<?php echo $category['category_id']; ?>" 
                               class="category-link <?php echo $category_id == $category['category_id'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="main-content-wrapper">
                <aside class="filters-sidebar">
                    <div class="filter-section">
                        <h3>Filters</h3>
                        <button id="clearFilters" class="clear-filters">Clear All</button>
                    </div>

                    <!-- Add Categories Filter -->
                    <div class="filter-section">
                        <h4>Categories</h4>
                        <div class="filter-options" id="categoryFilters">
                            <?php foreach ($categories as $category): ?>
                                <label class="filter-option">
                                    <input type="checkbox" name="category" value="<?php echo $category['category_id']; ?>"
                                        <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </label>
                                <!-- Subcategories for this category -->
                                <div class="subcategory-options" data-parent="<?php echo $category['category_id']; ?>">
                                    <?php 
                                    // Fetch subcategories for this category
                                    $subQuery = "SELECT * FROM tbl_subcategories WHERE category_id = ? AND deleted = 0 ORDER BY name";
                                    $stmt = $conn->prepare($subQuery);
                                    $stmt->bind_param("i", $category['category_id']);
                                    $stmt->execute();
                                    $subResult = $stmt->get_result();
                                    while ($sub = $subResult->fetch_assoc()): 
                                    ?>
                                        <label class="filter-option subcategory">
                                            <input type="checkbox" name="subcategory" value="<?php echo $sub['subcategory_id']; ?>">
                                            <span class="checkmark"></span>
                                            <?php echo htmlspecialchars($sub['name']); ?>
                                        </label>
                                    <?php endwhile; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Price Range</h4>
                        <div class="price-inputs">
                            <input type="number" id="minPrice" placeholder="Min" min="0">
                            <span>to</span>
                            <input type="number" id="maxPrice" placeholder="Max" min="0">
                        </div>
                        <button id="applyPriceFilter" class="apply-filter">Apply</button>
                    </div>

                    <div class="filter-section">
                        <h4>Brand</h4>
                        <div class="filter-search">
                            <input type="text" placeholder="Search Brands" id="brandSearch">
                        </div>
                        <div class="filter-options" id="brandFilters">
                            <?php foreach ($brands as $brand): ?>
                            <label class="filter-option">
                                <input type="checkbox" name="brand" value="<?php echo $brand['brand_id']; ?>">
                                <span class="checkmark"></span>
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Size</h4>
                        <div class="filter-options">
                            <?php foreach ($sizes as $size): ?>
                                <label class="filter-option">
                                    <input type="checkbox" name="size" value="<?php echo htmlspecialchars($size); ?>">
                                    <span class="checkmark"></span>
                                    <?php echo htmlspecialchars($size); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h4>Sort By</h4>
                        <div class="sort-options">
                            <label class="filter-option">
                                <input type="radio" name="sort" value="price_asc">
                                <span class="checkmark"></span>
                                Price: Low to High
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="sort" value="price_desc">
                                <span class="checkmark"></span>
                                Price: High to Low
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="sort" value="newest">
                                <span class="checkmark"></span>
                                Newest First
                            </label>
                        </div>
                    </div>
                </aside>

                <div class="products-grid">
                    <?php if (empty($products)): ?>
                        <div class="no-products" style="grid-column: 1 / -1; text-align: center; padding: 50px 0;">
                            <i class="fas fa-box-open" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 20px;"></i>
                            <p style="font-size: 18px; color: #666;">No products found<?php echo $current_category_name ? ' in ' . $current_category_name : ''; ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                                    <div class="product-image">
                                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </div>
                                    <div class="product-details">
                                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <p class="product-brand"><?php echo htmlspecialchars($product['brand_name']); ?></p>
                                        <p class="product-price">₹<?php echo number_format($product['price'], 2); ?></p>
                                        
                                        <?php if ($product['Stock_quantity'] <= 5 && $product['Stock_quantity'] > 0): ?>
                                            <p class="stock-warning">
                                                <i class="fas fa-exclamation-circle"></i>
                                                Only <?php echo $product['Stock_quantity']; ?> left in stock
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </a>

                                <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'seller' || $_SESSION['role'] === 'admin')): ?>
                                    <button class="add-to-cart-btn" onclick="showLoginMessage(); return false;">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                <?php elseif (isset($_SESSION['logged_in'])): ?>
                                    <form action="add_to_cart.php" method="POST" class="add-to-cart-form">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <input type="hidden" name="add_to_cart" value="1">
                                        <button type="submit" class="add-to-cart-btn">
                                            <i class="fas fa-shopping-cart"></i> Add to Cart
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="add-to-cart-btn" onclick="showLoginMessage(); return false;">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="loginMessage" class="login-message" style="display: none;">
        <div class="login-message-content">
            <p>Please login as a user to buy products.</p>
            <button onclick="closeLoginMessage()">OK</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add loading overlay to body if not exists
        if (!document.querySelector('.loading-overlay')) {
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(loadingOverlay);
        }

        // Get all filter elements
        const filterInputs = document.querySelectorAll('.filter-option input');
        const priceFilterBtn = document.getElementById('applyPriceFilter');
        const clearFiltersBtn = document.getElementById('clearFilters');
        const brandSearch = document.getElementById('brandSearch');
        const minPriceInput = document.getElementById('minPrice');
        const maxPriceInput = document.getElementById('maxPrice');

        // Add category toggle functionality
        document.querySelectorAll('.filter-option input[name="category"]').forEach(categoryInput => {
            const parentLabel = categoryInput.closest('.filter-option');
            const arrow = document.createElement('span');
            arrow.innerHTML = '▼';
            arrow.className = 'category-arrow';
            parentLabel.appendChild(arrow);

            parentLabel.addEventListener('click', (e) => {
                if (e.target !== categoryInput) { // Don't toggle if clicking the checkbox
                    const subcategoryDiv = parentLabel.nextElementSibling;
                    subcategoryDiv.classList.toggle('active');
                    arrow.classList.toggle('active');
                }
            });
        });

        // Function to update products based on filters
        function updateProducts() {
            const loadingOverlay = document.querySelector('.loading-overlay');
            loadingOverlay.style.display = 'flex';

            // Collect all active filters
            const filters = new URLSearchParams();
            
            // Add selected categories
            const selectedCategories = Array.from(document.querySelectorAll('input[name="category"]:checked'))
                .map(input => input.value);
            if (selectedCategories.length) filters.set('categories', selectedCategories.join(','));

            // Add selected subcategories
            const selectedSubcategories = Array.from(document.querySelectorAll('input[name="subcategory"]:checked'))
                .map(input => input.value);
            if (selectedSubcategories.length) filters.set('subcategories', selectedSubcategories.join(','));

            // Add price range
            const minPrice = minPriceInput.value;
            const maxPrice = maxPriceInput.value;
            if (minPrice) filters.set('min_price', minPrice);
            if (maxPrice) filters.set('max_price', maxPrice);

            // Add selected brands
            const selectedBrands = Array.from(document.querySelectorAll('input[name="brand"]:checked'))
                .map(input => input.value);
            if (selectedBrands.length) filters.set('brands', selectedBrands.join(','));

            // Add selected sizes
            const selectedSizes = Array.from(document.querySelectorAll('input[name="size"]:checked'))
                .map(input => input.value);
            if (selectedSizes.length) filters.set('sizes', selectedSizes.join(','));

            // Add sort option
            const sortOption = document.querySelector('input[name="sort"]:checked');
            if (sortOption) filters.set('sort', sortOption.value);

            // Fetch filtered products
            fetch(`get_filtered_products.php?${filters.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProductsGrid(data.products);
                    } else {
                        console.error('Error:', data.error);
                        alert('Error filtering products. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error filtering products. Please try again.');
                })
                .finally(() => {
                    loadingOverlay.style.display = 'none';
                });
        }

        // Function to update the products grid
        function updateProductsGrid(products) {
            const productsGrid = document.querySelector('.products-grid');
            
            if (!products || products.length === 0) {
                productsGrid.innerHTML = `
                    <div class="no-products" style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        text-align: center;
                        width: 100%;
                        padding: 20px;">
                        <i class="fas fa-box-open" style="
                            font-size: 64px;
                            color: #ccc;
                            display: block;
                            margin-bottom: 20px;"></i>
                        <p style="
                            font-size: 18px;
                            color: #666;
                            margin: 0;">No products found matching your filters</p>
                    </div>
                `;
                
                // Make sure the products grid has relative positioning
                productsGrid.style.position = 'relative';
                productsGrid.style.minHeight = '400px';
                return;
            }

            // Reset the styles when products are found
            productsGrid.style.position = '';
            productsGrid.style.minHeight = '';

            productsGrid.innerHTML = products.map(product => `
                <div class="product-card">
                    <a href="product_details.php?id=${product.product_id}" class="product-link">
                        <div class="product-image">
                            <img src="${product.image_path}" alt="${product.name}">
                        </div>
                        <div class="product-details">
                            <h3 class="product-title">${product.name}</h3>
                            <p class="product-brand">${product.brand_name}</p>
                            <p class="product-price">₹${parseFloat(product.price).toFixed(2)}</p>
                            ${product.Stock_quantity <= 5 && product.Stock_quantity > 0 ? `
                                <p class="stock-warning">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Only ${product.Stock_quantity} left in stock
                                </p>
                            ` : ''}
                        </div>
                    </a>
                    ${product.Stock_quantity > 0 ? `
                        <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'seller' || $_SESSION['role'] === 'admin')): ?>
                            <button class="add-to-cart-btn" onclick="showLoginMessage(); return false;">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        <?php elseif (isset($_SESSION['logged_in'])): ?>
                            <form action="add_to_cart.php" method="POST" class="add-to-cart-form">
                                <input type="hidden" name="product_id" value="${product.product_id}">
                                <input type="hidden" name="add_to_cart" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="add-to-cart-btn" onclick="window.location.href='login.php'">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        <?php endif; ?>
                    ` : `
                        <button class="out-of-stock-btn" disabled>
                            <i class="fas fa-times-circle"></i> Out of Stock
                        </button>
                    `}
                </div>
            `).join('');

            // Reattach cart event listeners
            attachCartEventListeners();
        }

        // Event listeners for filters
        filterInputs.forEach(input => {
            input.addEventListener('change', updateProducts);
        });

        priceFilterBtn.addEventListener('click', updateProducts);

        clearFiltersBtn.addEventListener('click', () => {
            document.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
                input.checked = false;
            });
            document.querySelectorAll('.subcategory-options').forEach(sub => {
                sub.classList.remove('active');
            });
            document.querySelectorAll('.category-arrow').forEach(arrow => {
                arrow.classList.remove('active');
            });
            minPriceInput.value = '';
            maxPriceInput.value = '';
            brandSearch.value = '';
            updateProducts();
        });

        // Brand search functionality
        brandSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('#brandFilters .filter-option').forEach(option => {
                const brandName = option.textContent.trim().toLowerCase();
                option.style.display = brandName.includes(searchTerm) ? '' : 'none';
            });
        });

        // Initial load of products
        updateProducts();

        // Function to attach cart event listeners
        function attachCartEventListeners() {
            document.querySelectorAll('.add-to-cart-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Check if user is logged in
                    <?php if (!isset($_SESSION['logged_in'])): ?>
                        // If not logged in, redirect to login page
                        window.location.href = 'login.php';
                        return;
                    <?php else: ?>
                        const button = this.querySelector('.add-to-cart-btn');
                        button.disabled = true;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

                        fetch('add_to_cart.php', {
                            method: 'POST',
                            body: new FormData(this)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                button.innerHTML = '<i class="fas fa-check"></i> Added to Cart';
                                setTimeout(() => {
                                    window.location.href = 'add_to_cart.php';
                                }, 500);
                            } else {
                                button.innerHTML = '<i class="fas fa-times"></i> ' + (data.message || 'Error');
                                button.disabled = false;
                                setTimeout(() => {
                                    button.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
                                }, 2000);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            button.disabled = false;
                            button.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
                            alert('Error adding to cart. Please try again.');
                        });
                    <?php endif; ?>
                });
            });
        }
    });

    function notifyWhenAvailable(productId) {
        // You can implement the notification functionality here
        alert('You will be notified when this product becomes available.');
    }

    function showLoginMessage() {
        document.getElementById('loginMessage').style.display = 'flex';
        return false;
    }

    function closeLoginMessage() {
        document.getElementById('loginMessage').style.display = 'none';
    }
    </script>
</body>
</html>