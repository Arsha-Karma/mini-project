<?php
require_once 'dbconnect.php';

header('Content-Type: application/json');

if (isset($_GET['query'])) {
<<<<<<< HEAD
    $original_query = $_GET['query'];
    $search = '%' . $original_query . '%';
=======
<<<<<<< HEAD
    $original_query = $_GET['query'];
    $search = '%' . $original_query . '%';
=======
    $search = '%' . $_GET['query'] . '%';
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
    
    // First, search for matching categories
    $categoryQuery = "
        SELECT DISTINCT 'category' as type, name, NULL as image_path, NULL as price, NULL as brand_name
        FROM tbl_categories 
        WHERE name LIKE ? AND deleted = 0
        LIMIT 3
    ";
    
<<<<<<< HEAD
    // Then search for matching products with fuzzy matching
=======
<<<<<<< HEAD
    // Then search for matching products with fuzzy matching
=======
    // Then search for matching products
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
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
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
        WHERE (
            p.name LIKE ? 
            OR b.name LIKE ?
            OR c.name LIKE ?
            OR SOUNDEX(p.name) = SOUNDEX(?)
            OR SOUNDEX(b.name) = SOUNDEX(?)
            OR SOUNDEX(c.name) = SOUNDEX(?)
            OR LEVENSHTEIN(LOWER(p.name), LOWER(?)) <= 3
            OR LEVENSHTEIN(LOWER(b.name), LOWER(?)) <= 3
            OR LEVENSHTEIN(LOWER(c.name), LOWER(?)) <= 3
        )
        AND p.deleted = 0
        AND p.Stock_quantity > 0
        ORDER BY 
            CASE 
                WHEN p.name LIKE ? THEN 1  /* Exact matches first */
                WHEN b.name LIKE ? THEN 2  /* Brand matches second */
                WHEN c.name LIKE ? THEN 3  /* Category matches third */
                WHEN SOUNDEX(p.name) = SOUNDEX(?) THEN 4 /* Sound-alike matches */
                ELSE 5 /* Levenshtein distance matches last */
            END
<<<<<<< HEAD
=======
=======
        WHERE (p.name LIKE ? 
            OR b.name LIKE ?
            OR c.name LIKE ?)
            AND p.deleted = 0
            AND p.Stock_quantity > 0
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
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
    
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
    // Check if MySQL has LEVENSHTEIN function installed
    // If not, we'll use a simplified query without it
    $hasLevenshtein = false;
    $testQuery = "SELECT LEVENSHTEIN('test', 'test') as result";
    try {
        $result = $conn->query($testQuery);
        if ($result) {
            $hasLevenshtein = true;
        }
    } catch (Exception $e) {
        // Levenshtein function not available
    }
    
    // Get matching products
    if ($hasLevenshtein) {
        // Use the full query with Levenshtein
        $stmt = $conn->prepare($productQuery);
        $stmt->bind_param("sssssssssssss", 
            $search, $search, $search, 
            $original_query, $original_query, $original_query,
            $original_query, $original_query, $original_query,
            $search, $search, $search, $original_query);
    } else {
        // Simplified query without Levenshtein
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
            WHERE (
                p.name LIKE ? 
                OR b.name LIKE ?
                OR c.name LIKE ?
                OR SOUNDEX(p.name) = SOUNDEX(?)
                OR SOUNDEX(b.name) = SOUNDEX(?)
                OR SOUNDEX(c.name) = SOUNDEX(?)
            )
            AND p.deleted = 0
            AND p.Stock_quantity > 0
            ORDER BY 
                CASE 
                    WHEN p.name LIKE ? THEN 1
                    WHEN b.name LIKE ? THEN 2
                    WHEN c.name LIKE ? THEN 3
                    ELSE 4
                END
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($productQuery);
        $stmt->bind_param("sssssssss", 
            $search, $search, $search, 
            $original_query, $original_query, $original_query,
            $search, $search, $search);
    }
    
<<<<<<< HEAD
=======
=======
    // Get matching products
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param("sss", $search, $search, $search);
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
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
    
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
    // If we didn't find enough suggestions, try an even more flexible search
    if (count($suggestions) < 3 && strlen($original_query) >= 3) {
        // Break the query into individual characters and search for products containing most of them
        $chars = str_split($original_query);
        $flexibleSearch = '%' . implode('%', $chars) . '%';
        
        $flexibleQuery = "
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
            WHERE p.name LIKE ?
            AND p.deleted = 0
            AND p.Stock_quantity > 0
            AND p.product_id NOT IN (SELECT product_id FROM tbl_product WHERE name LIKE ?)
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($flexibleQuery);
        $stmt->bind_param("ss", $flexibleSearch, $search);
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
    }
    
<<<<<<< HEAD
=======
=======
>>>>>>> 44b83f47263f36e84352386ff3b8d1b42f4b87ef
>>>>>>> bc6d503dcef2e4b397dbc83c8a531df1bfb282cf
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
} else {
    echo json_encode(['success' => false, 'message' => 'No search query provided']);
}
?> 