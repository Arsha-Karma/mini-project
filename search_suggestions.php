<?php
require_once 'dbconnect.php';

header('Content-Type: application/json');

if (isset($_GET['query'])) {
    $search = '%' . $_GET['query'] . '%';
    
    // First, search for matching categories
    $categoryQuery = "
        SELECT DISTINCT 'category' as type, name, NULL as image_path, NULL as price, NULL as brand_name
        FROM tbl_categories 
        WHERE name LIKE ? AND deleted = 0
        LIMIT 3
    ";
    
    // Then search for matching products
    $productQuery = "
        SELECT DISTINCT 
            'product' as type,
            p.name,
            p.image_path,
            p.price,
            b.name as brand_name,
            p.product_id,
            c.name as category_name
        FROM tbl_product p
        LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
        LEFT JOIN tbl_categories c ON p.category_id = c.category_id
        WHERE (p.name LIKE ? 
            OR b.name LIKE ?
            OR c.name LIKE ?)
            AND p.deleted = 0
            AND p.Stock_quantity > 0
        LIMIT 5
    ";
    
    $suggestions = [];
    
    // Get matching categories
    $stmt = $conn->prepare($categoryQuery);
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'type' => 'category',
            'name' => $row['name']
        ];
    }
    
    // Get matching products
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'type' => 'product',
            'id' => $row['product_id'],
            'name' => $row['name'],
            'image' => $row['image_path'],
            'price' => $row['price'],
            'brand' => $row['brand_name'],
            'category' => $row['category_name']
        ];
    }
    
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
} else {
    echo json_encode(['success' => false, 'message' => 'No search query provided']);
}
?> 