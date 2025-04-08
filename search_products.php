<?php
require_once 'dbconnect.php';

if (isset($_GET['query'])) {
    $search = '%' . $_GET['query'] . '%';
    
    $stmt = $conn->prepare("
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.image_path,
            c.name as category_name,
            b.name as brand_name
        FROM tbl_product p
        LEFT JOIN tbl_categories c ON p.category_id = c.category_id
        LEFT JOIN tbl_brands b ON p.brand_id = b.brand_id
        WHERE 
            (p.name LIKE ? OR 
            c.name LIKE ? OR 
            b.name LIKE ?) 
            AND p.deleted = 0
        LIMIT 10
    ");
    
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = array();
    while ($row = $result->fetch_assoc()) {
        $products[] = array(
            'product_id' => $row['product_id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'image_path' => $row['image_path'],
            'category_name' => $row['category_name'],
            'brand_name' => $row['brand_name']
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode($products);
}
?> 