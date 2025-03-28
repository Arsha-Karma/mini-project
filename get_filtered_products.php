<?php
require_once 'dbconnect.php';

header('Content-Type: application/json');

try {
    // Build the base query
    $query = "
        SELECT 
            p.*, 
            c.name as category_name,
            b.name as brand_name
        FROM tbl_product p
        LEFT JOIN tbl_categories c ON p.category_id = c.category_id
        LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
        WHERE p.deleted = 0
    ";
    
    $params = array();
    $types = "";

    // Category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $query .= " AND p.category_id = ?";
        $params[] = $_GET['category'];
        $types .= "i";
    }

    // Price range filter
    if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
        $query .= " AND p.price >= ?";
        $params[] = $_GET['min_price'];
        $types .= "d";
    }
    if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
        $query .= " AND p.price <= ?";
        $params[] = $_GET['max_price'];
        $types .= "d";
    }

    // Brand filter
    if (isset($_GET['brands']) && !empty($_GET['brands'])) {
        $brands = explode(',', $_GET['brands']);
        $placeholders = str_repeat('?,', count($brands) - 1) . '?';
        $query .= " AND p.brand_id IN ($placeholders)";
        foreach ($brands as $brand) {
            $params[] = $brand;
            $types .= "i";
        }
    }

    // Size filter
    if (isset($_GET['sizes']) && !empty($_GET['sizes'])) {
        $sizes = explode(',', $_GET['sizes']);
        $placeholders = str_repeat('?,', count($sizes) - 1) . '?';
        $query .= " AND p.size IN ($placeholders)";
        foreach ($sizes as $size) {
            $params[] = $size;
            $types .= "s";
        }
    }

    // Add these conditions to your existing query building
    if (isset($_GET['categories']) && !empty($_GET['categories'])) {
        $categories = explode(',', $_GET['categories']);
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';
        $query .= " AND p.category_id IN ($placeholders)";
        foreach ($categories as $category) {
            $params[] = $category;
            $types .= "i";
        }
    }

    if (isset($_GET['subcategories']) && !empty($_GET['subcategories'])) {
        $subcategories = explode(',', $_GET['subcategories']);
        $placeholders = str_repeat('?,', count($subcategories) - 1) . '?';
        $query .= " AND p.subcategory_id IN ($placeholders)";
        foreach ($subcategories as $subcategory) {
            $params[] = $subcategory;
            $types .= "i";
        }
    }

    // Sorting
    if (isset($_GET['sort'])) {
        switch ($_GET['sort']) {
            case 'price_asc':
                $query .= " ORDER BY p.price ASC";
                break;
            case 'price_desc':
                $query .= " ORDER BY p.price DESC";
                break;
            case 'newest':
                $query .= " ORDER BY p.created_at DESC";
                break;
            default:
                $query .= " ORDER BY p.name ASC";
        }
    } else {
        $query .= " ORDER BY p.name ASC";
    }

    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = array();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 