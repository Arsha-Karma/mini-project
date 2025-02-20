<?php
// dbconnect.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "perfume_store";

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
    
    $sql_signup = "CREATE TABLE IF NOT EXISTS tbl_signup (
        Signup_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        Phoneno VARCHAR(15),
        role_type ENUM('admin', 'user', 'seller') NOT NULL DEFAULT 'user',
        Verification_Status ENUM('pending', 'verified', 'disabled') DEFAULT 'pending',
        otp VARCHAR(6) DEFAULT NULL,
        otp_expires TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_signup);

    $sql_login = "CREATE TABLE IF NOT EXISTS tbl_login (
        login_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Signup_id INT(11) NOT NULL,
        Login_status ENUM('success', 'failed') DEFAULT 'failed',
        Last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_login);

    $sql_users = "CREATE TABLE IF NOT EXISTS tbl_users (
        user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Signup_id INT(11) NOT NULL,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100),
        phoneno VARCHAR(15),
        role_type ENUM('admin', 'user', 'seller') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_users);

    $sql_seller = "CREATE TABLE IF NOT EXISTS tbl_seller (
        seller_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Sellername VARCHAR(50) NOT NULL,
        Signup_id INT(11) NOT NULL,
        email VARCHAR(100),
        phoneno VARCHAR(15),
        role_type ENUM('admin', 'user', 'seller') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_seller);

    $sql_categories = "CREATE TABLE IF NOT EXISTS tbl_categories (
        category_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        parent_id INT(11) DEFAULT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        season VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES tbl_categories(category_id) ON DELETE SET NULL,
        INDEX idx_parent (parent_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_categories);

    // New subcategories table
    $sql_subcategories = "CREATE TABLE IF NOT EXISTS tbl_subcategories (
        subcategory_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_id INT(11) NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        collection_type VARCHAR(50) DEFAULT NULL,
        price_range VARCHAR(50) DEFAULT NULL,
        target_gender ENUM('male', 'female', 'unisex') DEFAULT 'unisex',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES tbl_categories(category_id) ON DELETE CASCADE,
        INDEX idx_category (category_id),
        INDEX idx_status (status),
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_subcategories);

    $sql_product = "CREATE TABLE IF NOT EXISTS tbl_product (
        product_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        seller_id INT(11) NOT NULL,
        category_id INT(11) NOT NULL,
        subcategory_id INT(11) DEFAULT NULL,
        name VARCHAR(50) NOT NULL,
        description VARCHAR(200),
        price DECIMAL(10,2) NOT NULL,
        Stock_quantity INT NOT NULL,
        Fragrance_type VARCHAR(50),
        gender ENUM('male', 'female', 'unisex') DEFAULT 'unisex',
        image_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES tbl_seller(seller_id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES tbl_categories(category_id) ON DELETE CASCADE,
        FOREIGN KEY (subcategory_id) REFERENCES tbl_subcategories(subcategory_id) ON DELETE SET NULL,
        INDEX idx_seller (seller_id),
        INDEX idx_category (category_id),
        INDEX idx_subcategory (subcategory_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_product);

    $sql_orders = "CREATE TABLE IF NOT EXISTS tbl_orders (
        order_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        ordered_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        payment_method VARCHAR(50),
        payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        Shipping_address TEXT,
        wrapping_type VARCHAR(50),
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_product (product_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_orders);

    $sql_reviews = "CREATE TABLE IF NOT EXISTS tbl_reviews (
        review_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE,
        INDEX idx_product (product_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_reviews);
}

// Helper Functions
function sanitize_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    return preg_match('/^[6-9]\d{9}$/', $phone) && !preg_match('/^[6-9]0{9}$/', $phone);
}

function validate_password($password) {
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

function checkUser($conn, $username, $email) {
    $username = sanitize_input($conn, $username);
    $email = sanitize_input($conn, $email);
    
    $stmt = $conn->prepare("SELECT username FROM tbl_signup WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return [
            'exists' => true,
            'message' => 'Username already exists'
        ];
    }
    
    $stmt = $conn->prepare("SELECT email FROM tbl_signup WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return [
            'exists' => true,
            'message' => 'Email already exists'
        ];
    }
    
    return [
        'exists' => false,
        'message' => ''
    ];
}




// User Management Functions
function recordLoginAttempt($conn, $signup_id, $status) {
    if ($signup_id === null) {
        error_log("Login attempt with null Signup_id");
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO tbl_login (Signup_id, Login_status, Last_login) 
        VALUES (?, ?, CURRENT_TIMESTAMP)");
    $stmt->bind_param("is", $signup_id, $status);
    return $stmt->execute();
}

function loginUser($conn, $email, $password) {
    $email = sanitize_input($conn, $email);
    
    $stmt = $conn->prepare("SELECT Signup_id, password, role_type, Verification_Status 
        FROM tbl_signup WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    $user = $result->fetch_assoc();
    
    if ($user['Verification_Status'] === 'disabled') {
        recordLoginAttempt($conn, $user['Signup_id'], 'failed');
        return ['success' => false, 'message' => 'Account is disabled'];
    }
    
    if (password_verify($password, $user['password'])) {
        recordLoginAttempt($conn, $user['Signup_id'], 'success');
        
        session_start();
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['Signup_id'];
        $_SESSION['role'] = $user['role_type'];
        
        return [
            'success' => true,
            'signup_id' => $user['Signup_id'],
            'role' => $user['role_type']
        ];
    }
    
    recordLoginAttempt($conn, $user['Signup_id'], 'failed');
    return ['success' => false, 'message' => 'Invalid credentials'];
}

function registerUser($conn, $username, $email, $mobile, $password, $role = 'user') {
    $username = sanitize_input($conn, $username);
    $email = sanitize_input($conn, $email);
    $mobile = sanitize_input($conn, $mobile);
    
    if (empty($username) || empty($email) || empty($mobile) || empty($password)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    if (!validate_email($email)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    if (!validate_phone($mobile)) {
        return ['success' => false, 'message' => 'Invalid phone number'];
    }
    
    if (!validate_password($password)) {
        return ['success' => false, 'message' => 'Password does not meet requirements'];
    }

    $existingUser = checkUser($conn, $username, $email);
    if ($existingUser['exists']) {
        return ['success' => false, 'message' => $existingUser['message']];
    }

    $conn->begin_transaction();
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt1 = $conn->prepare("INSERT INTO tbl_signup (username, email, password, Phoneno, role_type, Verification_Status) 
            VALUES (?, ?, ?, ?, ?, 'verified')");
        $stmt1->bind_param("sssss", $username, $email, $hashed_password, $mobile, $role);
        $stmt1->execute();
        $signup_id = $conn->insert_id;
        
        if ($role === 'user') {
            $stmt2 = $conn->prepare("INSERT INTO tbl_users (Signup_id, username, email, phoneno, role_type) 
                VALUES (?, ?, ?, ?, ?)");
        } else if ($role === 'seller') {
            $stmt2 = $conn->prepare("INSERT INTO tbl_seller (Signup_id, Sellername, email, phoneno, role_type) 
                VALUES (?, ?, ?, ?, ?)");
        }
        
        if (isset($stmt2)) {
            $stmt2->bind_param("issss", $signup_id, $username, $email, $mobile, $role);
            $stmt2->execute();
        }
        
        recordLoginAttempt($conn, $signup_id, 'success');
        
        $conn->commit();
        return ['success' => true, 'signup_id' => $signup_id];
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

// Product and Order Management Functions
function addProduct($conn, $seller_id, $category_id, $name, $description, $price, $stock, $fragrance_type, $gender, $image_url) {
    $stmt = $conn->prepare("INSERT INTO tbl_product (seller_id, category_id, name, description, price, Stock_quantity, Fragrance_type, gender, image_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissdisss", $seller_id, $category_id, $name, $description, $price, $stock, $fragrance_type, $gender, $image_url);
    
    if ($stmt->execute()) {
        return ['success' => true, 'product_id' => $conn->insert_id];
    }
    return ['success' => false, 'message' => 'Failed to add product'];
}

function createOrder($conn, $user_id, $product_id, $quantity, $total_amount, $payment_method, $shipping_address, $wrapping_type = null) {
    $stmt = $conn->prepare("INSERT INTO tbl_orders (user_id, product_id, quantity, total_amount, payment_method, Shipping_address, wrapping_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiidsss", $user_id, $product_id, $quantity, $total_amount, $payment_method, $shipping_address, $wrapping_type);
    
    if ($stmt->execute()) {
        return ['success' => true, 'order_id' => $conn->insert_id];
    }
    return ['success' => false, 'message' => 'Failed to create order'];
}

function addReview($conn, $user_id, $product_id, $rating, $comment) {
    if ($rating < 1 || $rating > 5) {
        return ['success' => false, 'message' => 'Invalid rating'];
    }
    
    $stmt = $conn->prepare("INSERT INTO tbl_reviews (user_id, product_id, rating, comment) 
        VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $product_id, $rating, $comment);
    
    if ($stmt->execute()) {
        return ['success' => true, 'review_id' => $conn->insert_id];
    }
    return ['success' => false, 'message' => 'Failed to add review'];
}

function updateOrderStatus($conn, $order_id, $status) {
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        return ['success' => false, 'message' => 'Invalid status'];
    }
    
    $stmt = $conn->prepare("UPDATE tbl_orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $status, $order_id);
    
    if ($stmt->execute()) {
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Failed to update order status'];
}

function updateProductStock($conn, $product_id, $quantity) {
    $stmt = $conn->prepare("UPDATE tbl_product SET Stock_quantity = Stock_quantity + ? WHERE product_id = ?");
    $stmt->bind_param("ii", $quantity, $product_id);
    
    if ($stmt->execute()) {
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Failed to update stock'];
}
function disableUserAccount($conn, $user_id) {
    try {
        $conn->begin_transaction();
        
        // Update Verification_Status in tbl_signup
        $stmt1 = $conn->prepare("UPDATE tbl_signup SET Verification_Status = 'disabled' WHERE Signup_id = ?");
        $stmt1->bind_param("i", $user_id);
        
        // Update status in tbl_users
        $stmt2 = $conn->prepare("UPDATE tbl_users SET status = 'inactive' WHERE Signup_id = ?");
        $stmt2->bind_param("i", $user_id);
        
        $result1 = $stmt1->execute();
        $result2 = $stmt2->execute();
        
        if ($result1 && $result2) {
            $conn->commit();
            return [
                'success' => true,
                'message' => 'Account disabled successfully'
            ];
        } else {
            throw new Exception("Error disabling account");
        }
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error disabling account: ' . $e->getMessage()
        ];
    }
}

function isAccountDisabled($conn, $user_id) {
    $stmt = $conn->prepare("SELECT Verification_Status FROM tbl_signup WHERE Signup_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['Verification_Status'] === 'disabled';
    }
    return false;
}
// Close the database connection when done
register_shutdown_function(function() use ($conn) {
    $conn->close();
});
?>