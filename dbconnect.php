<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "perfumes";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
    
    // Create tbl_signup table
    $sql_signup = "CREATE TABLE IF NOT EXISTS tbl_signup (
        Signup_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        Phoneno VARCHAR(15),
        address VARCHAR(255),
        city VARCHAR(100),
        district VARCHAR(100),
        role_type ENUM('admin', 'user', 'seller') NOT NULL DEFAULT 'user',
        verification_status ENUM('active', 'disabled') DEFAULT 'active',
        otp VARCHAR(6) DEFAULT NULL,
        otp_expires TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_signup);

    // Alter tbl_signup to modify verification_status column
    $alter_verification_status = "ALTER TABLE tbl_signup MODIFY COLUMN verification_status ENUM('active', 'disabled') DEFAULT 'active'";
    $conn->query($alter_verification_status);

    // Create tbl_login table
    $sql_login = "CREATE TABLE IF NOT EXISTS tbl_login (
        login_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Signup_id INT(11) NOT NULL,
        Login_status ENUM('success', 'failed') DEFAULT 'failed',
        Last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_login);

    // Create tbl_users table
    $sql_users = "CREATE TABLE IF NOT EXISTS tbl_users (
        user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Signup_id INT(11) NOT NULL,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100),
        phoneno VARCHAR(15),
        role_type ENUM('admin', 'user', 'seller') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        verification_status ENUM('active', 'disabled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_users);

    // Alter tbl_users to add verification_status column if it doesn't exist
    if (!columnExists($conn, 'tbl_users', 'verification_status')) {
        $alter_users = "ALTER TABLE tbl_users ADD COLUMN verification_status ENUM('active', 'disabled') DEFAULT 'active'";
        $conn->query($alter_users);
    }

    // Create tbl_seller table
    $sql_seller = "CREATE TABLE IF NOT EXISTS tbl_seller (
        seller_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Sellername VARCHAR(50) NOT NULL,
        Signup_id INT(11) NOT NULL,
        email VARCHAR(100),
        phoneno VARCHAR(15),
        role_type ENUM('admin', 'user', 'seller') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        verification_status ENUM('active', 'disabled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_seller);

    // Alter tbl_seller to add verification_status column if it doesn't exist
    if (!columnExists($conn, 'tbl_seller', 'verification_status')) {
        $alter_seller = "ALTER TABLE tbl_seller ADD COLUMN verification_status ENUM('active', 'disabled') DEFAULT 'active'";
        $conn->query($alter_seller);
    }

    // Create tbl_categories table
    $sql_categories = "CREATE TABLE IF NOT EXISTS tbl_categories (
        category_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        parent_id INT(11) DEFAULT NULL,
        name VARCHAR(100) NOT NULL,
        deleted BOOLEAN DEFAULT FALSE,
        description TEXT,
        season VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES tbl_categories(category_id) ON DELETE SET NULL,
        INDEX idx_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_categories);

    // Create tbl_subcategories table
    $sql_subcategories = "CREATE TABLE IF NOT EXISTS tbl_subcategories (
        subcategory_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_id INT(11) NOT NULL,
        name VARCHAR(100) NOT NULL,
        deleted BOOLEAN DEFAULT FALSE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES tbl_categories(category_id) ON DELETE CASCADE,
        INDEX idx_category (category_id),
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_subcategories);

    

    // Create tbl_product table
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

    // Create tbl_orders table
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

    // Create tbl_reviews table
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
        
        $stmt1 = $conn->prepare("INSERT INTO tbl_signup (username, email, password, Phoneno, role_type, verification_status) 
            VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt1->bind_param("sssss", $username, $email, $hashed_password, $mobile, $role);
        $stmt1->execute();
        $signup_id = $conn->insert_id;
        
        if ($role === 'user') {
            $stmt2 = $conn->prepare("INSERT INTO tbl_users (Signup_id, username, email, phoneno, role_type, status, verification_status) 
                VALUES (?, ?, ?, ?, ?, 'active', 'active')");
        } else if ($role === 'seller') {
            $stmt2 = $conn->prepare("INSERT INTO tbl_seller (Signup_id, Sellername, email, phoneno, role_type, status, verification_status) 
                VALUES (?, ?, ?, ?, ?, 'active', 'active')");
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

function disableUserAccount($conn, $user_id) {
    try {
        $conn->begin_transaction();
        
        // Update verification_status in tbl_signup
        $stmt1 = $conn->prepare("UPDATE tbl_signup SET verification_status = 'disabled' WHERE Signup_id = ?");
        $stmt1->bind_param("i", $user_id);
        
        // Update status in tbl_users
        $stmt2 = $conn->prepare("UPDATE tbl_users SET status = 'inactive', verification_status = 'disabled' WHERE Signup_id = ?");
        $stmt2->bind_param("i", $user_id);
        
        // Update status in tbl_seller (if applicable)
        $stmt3 = $conn->prepare("UPDATE tbl_seller SET status = 'inactive', verification_status = 'disabled' WHERE Signup_id = ?");
        $stmt3->bind_param("i", $user_id);
        
        // Execute all updates
        $stmt1->execute();
        $stmt2->execute();
        $stmt3->execute();
        
        $conn->commit();
        return [
            'success' => true,
            'message' => 'Account disabled successfully'
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error disabling account: ' . $e->getMessage()
        ];
    }
}

// Add this to your dbconnect.php or create a new file for user management functions

function activateUser($conn, $user_id) {
    try {
        $conn->begin_transaction();
        
        // Update verification_status in tbl_signup
        $stmt1 = $conn->prepare("UPDATE tbl_signup 
                                SET verification_status = 'active' 
                                WHERE Signup_id = ?");
        $stmt1->bind_param("i", $user_id);
        $stmt1->execute();
        
        // Update status in tbl_users
        $stmt2 = $conn->prepare("UPDATE tbl_users 
                                SET status = 'active', 
                                    verification_status = 'active' 
                                WHERE Signup_id = ?");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        
        // Also update tbl_seller if the user is a seller
        $stmt3 = $conn->prepare("UPDATE tbl_seller 
                                SET status = 'active', 
                                    verification_status = 'active' 
                                WHERE Signup_id = ?");
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();
        
        $conn->commit();
        return [
            'success' => true,
            'message' => 'Account activated successfully'
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => 'Error activating account: ' . $e->getMessage()
        ];
    }
}

// Function to get user status
function getUserStatus($conn, $user_id) {
    $stmt = $conn->prepare("SELECT u.status, u.verification_status, s.verification_status as signup_status 
                           FROM tbl_users u 
                           JOIN tbl_signup s ON u.Signup_id = s.Signup_id 
                           WHERE u.Signup_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // If any status is 'inactive' or 'disabled', consider the user inactive
        if ($row['verification_status'] === 'disabled' || 
            $row['signup_status'] === 'disabled' || 
            $row['status'] === 'inactive') {
            return 'Inactive';
        }
        return 'Active';
    }
    return 'Unknown';
}

// Add this to your activation endpoint (e.g., activate_user.php)
if (isset($_POST['activate_user'])) {
    $user_id = $_POST['user_id'];
    $result = activateUser($conn, $user_id);
    
    if ($result['success']) {
        // Redirect or show success message
        header('Location: manage_users.php?message=activation_success');
    } else {
        // Handle error
        header('Location: manage_users.php?error=' . urlencode($result['message']));
    }
}



// Helper function to check if a column exists in a table
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result->num_rows > 0;
}

// Close the database connection when done
register_shutdown_function(function() use ($conn) {
    $conn->close();
});
?>