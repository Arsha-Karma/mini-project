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
        postal_code VARCHAR(10),
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

    // Add postal_code column if it doesn't exist
    $alter_postal_code = "ALTER TABLE tbl_signup 
                         ADD COLUMN IF NOT EXISTS postal_code VARCHAR(10) 
                         AFTER city";
    $conn->query($alter_postal_code);

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
        Signup_id INT(11) NOT NULL UNIQUE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_users);

    // Drop redundant columns from tbl_users if they exist
    $drop_users_columns = "ALTER TABLE tbl_users 
        DROP COLUMN IF EXISTS username,
        DROP COLUMN IF EXISTS email,
        DROP COLUMN IF EXISTS phoneno,
        DROP COLUMN IF EXISTS role_type,
        DROP COLUMN IF EXISTS verification_status";
    $conn->query($drop_users_columns);

    // Create tbl_seller table
    $sql_seller = "CREATE TABLE IF NOT EXISTS tbl_seller (
        seller_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Signup_id INT(11) NOT NULL UNIQUE,
        Sellername VARCHAR(50) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_seller);

    // Drop redundant columns from tbl_seller if they exist
    $drop_seller_columns = "ALTER TABLE tbl_seller 
        DROP COLUMN IF EXISTS email,
        DROP COLUMN IF EXISTS phoneno,
        DROP COLUMN IF EXISTS role_type,
        DROP COLUMN IF EXISTS verification_status";
    $conn->query($drop_seller_columns);

    // Add new columns to tbl_seller
    $alter_seller_table = "ALTER TABLE tbl_seller 
        ADD COLUMN IF NOT EXISTS id_type VARCHAR(50),
        ADD COLUMN IF NOT EXISTS id_number VARCHAR(50),
        ADD COLUMN IF NOT EXISTS verified_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        ADD COLUMN IF NOT EXISTS documents_uploaded ENUM('pending', 'completed') DEFAULT 'pending'";
    $conn->query($alter_seller_table);

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

    // Create category table first (no dependencies)
    $create_category = "CREATE TABLE IF NOT EXISTS tbl_category (
        category_id INT PRIMARY KEY AUTO_INCREMENT,
        category_name VARCHAR(100) NOT NULL,
        status ENUM('Active', 'Inactive') DEFAULT 'Active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_category);

    // Create brand table (no dependencies)
    $create_brand = "CREATE TABLE IF NOT EXISTS tbl_brand (
        brand_id INT PRIMARY KEY AUTO_INCREMENT,
        brand_name VARCHAR(100) NOT NULL,
        status ENUM('Active', 'Inactive') DEFAULT 'Active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_brand);

    // Create product table (depends on category and brand)
    $create_product = "CREATE TABLE IF NOT EXISTS tbl_product (
        product_id INT PRIMARY KEY AUTO_INCREMENT,
        product_name VARCHAR(255) NOT NULL,
        category_id INT,
        brand_id INT,
        price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(255),
        Stock_quantity INT NOT NULL DEFAULT 0,
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        deleted BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES tbl_category(category_id) ON DELETE SET NULL,
        FOREIGN KEY (brand_id) REFERENCES tbl_brand(brand_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_product);

    // Drop existing tables with foreign key constraints first
    

    // Create wishlist table (depends on user and product)
    $sql_wishlist = "CREATE TABLE IF NOT EXISTS tbl_wishlist (
        wishlist_id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (wishlist_id),
        FOREIGN KEY (user_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE,
        UNIQUE KEY unique_wishlist (user_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql_wishlist)) {
        error_log("Error creating wishlist table: " . $conn->error);
    }

    // Create shipping_addresses table
    $sql_shipping = "CREATE TABLE IF NOT EXISTS shipping_addresses (
        address_id INT PRIMARY KEY AUTO_INCREMENT,
        Signup_id INT,
        address_line1 TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        state VARCHAR(100) NOT NULL,
        postal_code VARCHAR(20) NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_shipping);

    // Create orders table
    $sql_orders = "CREATE TABLE IF NOT EXISTS orders_table (
        order_id VARCHAR(100) NOT NULL PRIMARY KEY,
        Signup_id INT NOT NULL,
        payment_id VARCHAR(100),
        total_amount DECIMAL(10, 2) NOT NULL,
        shipping_address VARCHAR(255) NOT NULL,
        order_status ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Completed', 'Cancelled') DEFAULT 'Pending',
        payment_status VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_orders);

    // Add image_path column to orders_table
    $alter_orders_table = "ALTER TABLE orders_table 
        ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) AFTER product_id";

    if (!$conn->query($alter_orders_table)) {
        error_log("Error adding image_path column to orders_table: " . $conn->error);
    }

    // Create payment table
    $sql_payment = "CREATE TABLE IF NOT EXISTS payment_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(100) NOT NULL,
        Signup_id INT NOT NULL,
        payment_id VARCHAR(100) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_payment_signupid FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE,
        CONSTRAINT fk_payment_orderid FOREIGN KEY (order_id) REFERENCES orders_table(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql_payment);

    // Add size column to tbl_product
    $alter_product_size = "ALTER TABLE tbl_product 
        ADD COLUMN IF NOT EXISTS size VARCHAR(20) 
        COMMENT 'Perfume size (e.g., 100ml, 20ml)' 
        AFTER description";

    if (!$conn->query($alter_product_size)) {
        error_log("Error adding size column to tbl_product: " . $conn->error);
    }

    // Create verification_documents table if not exists
    $sql_verification_docs = "CREATE TABLE IF NOT EXISTS seller_verification_docs (
        doc_id INT PRIMARY KEY AUTO_INCREMENT,
        seller_id INT NOT NULL,
        id_proof_front VARCHAR(255),
        id_proof_back VARCHAR(255),
        business_proof VARCHAR(255),
        address_proof VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_seller_docs 
        FOREIGN KEY (seller_id) 
        REFERENCES tbl_seller(seller_id) 
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql_verification_docs)) {
        error_log("Error creating seller_verification_docs table: " . $conn->error);
    }

    // Drop existing foreign key if it exists
    $drop_fk = "ALTER TABLE seller_verification_docs 
                DROP FOREIGN KEY IF EXISTS seller_verification_docs_ibfk_1";
    $conn->query($drop_fk);

    // Add the foreign key with CASCADE
    $add_fk = "ALTER TABLE seller_verification_docs 
               ADD CONSTRAINT seller_verification_docs_ibfk_1 
               FOREIGN KEY (seller_id) REFERENCES tbl_seller(seller_id) 
               ON DELETE CASCADE";
    $conn->query($add_fk);

    // Add index for better performance
    $add_index = "CREATE INDEX IF NOT EXISTS idx_seller_id ON seller_verification_docs(seller_id)";
    $conn->query($add_index);

   

    


   

    // Create reviews table with the correct structure
    $sql_reviews = "CREATE TABLE IF NOT EXISTS tbl_reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        rating INT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES tbl_signup(Signup_id),
        FOREIGN KEY (product_id) REFERENCES tbl_product(product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_reviews);

    // Modify the orders_table structure to ensure correct status values
    $sql_orders = "ALTER TABLE orders_table MODIFY COLUMN order_status 
                   ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Completed', 'Cancelled') 
                   DEFAULT 'Pending'";
    $conn->query($sql_orders);

    // Add an index for better performance
    $sql_index = "ALTER TABLE orders_table ADD INDEX idx_order_status (order_status)";
    try {
        $conn->query($sql_index);
    } catch (Exception $e) {
        // Index might already exist
    }

    // Add new columns to orders_table for cancellation handling
    $alter_orders_table = "ALTER TABLE orders_table 
        ADD COLUMN IF NOT EXISTS cancellation_reason TEXT,
        ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS refund_id VARCHAR(100),
        ADD COLUMN IF NOT EXISTS refund_status ENUM('pending', 'processed', 'failed') DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS cancellation_processed_by VARCHAR(50) DEFAULT NULL,
        ADD INDEX IF NOT EXISTS idx_order_status (order_status),
        ADD INDEX IF NOT EXISTS idx_refund_status (refund_status)";

    try {
        $conn->query($alter_orders_table);
    } catch (Exception $e) {
        error_log("Error altering orders_table: " . $e->getMessage());
    }

    // Create refunds_table to track refund history
    $create_refunds_table = "CREATE TABLE IF NOT EXISTS refunds_table (
        refund_id VARCHAR(100) PRIMARY KEY,
        order_id VARCHAR(100) NOT NULL,
        payment_id VARCHAR(100) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('initiated', 'processed', 'failed') DEFAULT 'initiated',
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        FOREIGN KEY (order_id) REFERENCES orders_table(order_id) ON DELETE CASCADE,
        INDEX idx_order_id (order_id),
        INDEX idx_payment_id (payment_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $conn->query($create_refunds_table);
    } catch (Exception $e) {
        error_log("Error creating refunds_table: " . $e->getMessage());
    }

    // Add cancellation_policy table for future use
    $create_cancellation_policy = "CREATE TABLE IF NOT EXISTS cancellation_policy (
        policy_id INT PRIMARY KEY AUTO_INCREMENT,
        order_status VARCHAR(50) NOT NULL,
        time_limit INT NOT NULL COMMENT 'Time limit in hours',
        refund_percentage DECIMAL(5,2) NOT NULL,
        conditions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_order_status (order_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $conn->query($create_cancellation_policy);
    } catch (Exception $e) {
        error_log("Error creating cancellation_policy table: " . $e->getMessage());
    }

    // Insert default cancellation policies
    $insert_default_policies = "INSERT IGNORE INTO cancellation_policy 
        (order_status, time_limit, refund_percentage, conditions) VALUES 
        ('Pending', 24, 100.00, 'Full refund if cancelled within 24 hours'),
        ('Processing', 12, 100.00, 'Full refund if cancelled before shipping'),
        ('Shipped', 0, 0.00, 'No refund after shipping unless damaged or wrong item')";

    try {
        $conn->query($insert_default_policies);
    } catch (Exception $e) {
        error_log("Error inserting default cancellation policies: " . $e->getMessage());
    }

    // Add notification tracking for refunds
    $alter_notifications = "ALTER TABLE notifications 
        ADD COLUMN IF NOT EXISTS refund_id VARCHAR(100),
        ADD COLUMN IF NOT EXISTS notification_type ENUM('order', 'refund', 'general') DEFAULT 'general',
        ADD INDEX IF NOT EXISTS idx_notification_type (notification_type)";

    try {
        $conn->query($alter_notifications);
    } catch (Exception $e) {
        error_log("Error altering notifications table: " . $e->getMessage());
    }

    // Create a table to track refund transactions
    $create_refund_transactions = "CREATE TABLE IF NOT EXISTS refund_transactions (
        transaction_id VARCHAR(100) PRIMARY KEY,
        refund_id VARCHAR(100) NOT NULL,
        order_id VARCHAR(100) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status VARCHAR(50) NOT NULL,
        gateway_response TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (refund_id) REFERENCES refunds_table(refund_id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders_table(order_id) ON DELETE CASCADE,
        INDEX idx_refund_id (refund_id),
        INDEX idx_order_id (order_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $conn->query($create_refund_transactions);
    } catch (Exception $e) {
        error_log("Error creating refund_transactions table: " . $e->getMessage());
    }

    // Add refund columns to payment_table if they don't exist
    $alter_payment_table = "ALTER TABLE payment_table 
        ADD COLUMN IF NOT EXISTS refund_status ENUM('pending', 'processed', 'failed') DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS refund_id VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS refund_date TIMESTAMP NULL,
        ADD INDEX IF NOT EXISTS idx_refund_status (refund_status)";

    try {
        $conn->query($alter_payment_table);
    } catch (Exception $e) {
        error_log("Error altering payment_table: " . $e->getMessage());
    }

    // Add gift_option columns to orders_table
    $alter_orders_table = "ALTER TABLE orders_table 
        ADD COLUMN IF NOT EXISTS gift_option TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS gift_message TEXT,
        ADD COLUMN IF NOT EXISTS gift_wrap_type VARCHAR(50),
        ADD COLUMN IF NOT EXISTS gift_recipient_name VARCHAR(255),
        ADD COLUMN IF NOT EXISTS gift_wrap_charge DECIMAL(10,2) DEFAULT 0.00";

    try {
        $conn->query($alter_orders_table);
    } catch (Exception $e) {
        error_log("Error adding gift columns to orders_table: " . $e->getMessage());
    }

    // Add columns for price components if they don't exist
    $alter_orders_table = "ALTER TABLE orders_table 
        ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS shipping DECIMAL(10,2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS tax DECIMAL(10,2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS gift_wrap_charge DECIMAL(10,2) DEFAULT 0.00";
    $conn->query($alter_orders_table);

    // Also ensure payment_table correctly stores amount
    $alter_payment_table = "ALTER TABLE payment_table
        MODIFY amount DECIMAL(10,2) NOT NULL";
        
    if (!$conn->query($alter_payment_table)) {
        error_log("Error updating payment_table amount column: " . $conn->error);
    }

    // Create admin_profits table
    $create_admin_profits = "CREATE TABLE IF NOT EXISTS admin_profits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        month_year DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_seller_month (seller_id, month_year),
        FOREIGN KEY (seller_id) REFERENCES tbl_seller(seller_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $conn->query($create_admin_profits);
    } catch (Exception $e) {
        error_log("Error creating admin_profits table: " . $e->getMessage());
    }

    // Add index for better performance
    $add_admin_profits_index = "CREATE INDEX IF NOT EXISTS idx_month_year ON admin_profits(month_year)";
    try {
        $conn->query($add_admin_profits_index);
    } catch (Exception $e) {
        error_log("Error creating index on admin_profits: " . $e->getMessage());
    }

    // Modify the admin_profits table in the database setup section
    $alter_admin_profits = "ALTER TABLE admin_profits 
        ADD COLUMN IF NOT EXISTS order_id VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS orders_count INT DEFAULT 0,
        ADD UNIQUE INDEX IF NOT EXISTS idx_order_id (order_id)";

    try {
        $conn->query($alter_admin_profits);
    } catch (Exception $e) {
        error_log("Error altering admin_profits table: " . $e->getMessage());
    }

    // Create contact_messages table
    $sql_contact_messages = "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('read', 'unread') DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql_contact_messages);

    // Add is_deleted column to contact_messages if it doesn't exist
    $sql_add_is_deleted = "ALTER TABLE contact_messages 
                          ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0";
    $conn->query($sql_add_is_deleted);

    // Add is_replied column to contact_messages if it doesn't exist
    $sql_add_is_replied = "ALTER TABLE contact_messages 
                          ADD COLUMN IF NOT EXISTS is_replied TINYINT(1) DEFAULT 0";
    $conn->query($sql_add_is_replied);
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
            $stmt2 = $conn->prepare("INSERT INTO tbl_users (Signup_id, status) 
                VALUES (?, 'active')");
            $stmt2->bind_param("i", $signup_id);
        } else if ($role === 'seller') {
            $stmt2 = $conn->prepare("INSERT INTO tbl_seller (Signup_id, Sellername, status) 
                VALUES (?, ?, 'active')");
            $stmt2->bind_param("is", $signup_id, $username);
        }
        
        if (isset($stmt2)) {
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
                                SET status = 'active'
                                WHERE Signup_id = ?");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        
        // Also update tbl_seller if the user is a seller
        $stmt3 = $conn->prepare("UPDATE tbl_seller 
                                SET status = 'active'
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
    $stmt = $conn->prepare("SELECT u.status, s.verification_status as signup_status 
                           FROM tbl_users u 
                           JOIN tbl_signup s ON u.Signup_id = s.Signup_id 
                           WHERE u.Signup_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // If any status is 'inactive' or 'disabled', consider the user inactive
        if ($row['signup_status'] === 'disabled' || 
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

// Function to delete a seller and all related records
function deleteSeller($conn, $seller_id) {
    try {
        $conn->begin_transaction();

        // 1. First delete from seller_verification_docs
        $delete_docs = "DELETE FROM seller_verification_docs WHERE seller_id = ?";
        $stmt = $conn->prepare($delete_docs);
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();

        // 2. Get the Signup_id from tbl_seller
        $get_signup_id = "SELECT Signup_id FROM tbl_seller WHERE seller_id = ?";
        $stmt = $conn->prepare($get_signup_id);
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $signup_id = $result->fetch_assoc()['Signup_id'];

        // 3. Delete from tbl_seller
        $delete_seller = "DELETE FROM tbl_seller WHERE seller_id = ?";
        $stmt = $conn->prepare($delete_seller);
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();

        // 4. Delete from tbl_signup
        $delete_signup = "DELETE FROM tbl_signup WHERE Signup_id = ?";
        $stmt = $conn->prepare($delete_signup);
        $stmt->bind_param("i", $signup_id);
        $stmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error deleting seller: " . $e->getMessage());
        return false;
    }
}

// Add this function after your other functions
function calculateAndRecordAdminProfit($conn, $order_id) {
    // Check if profit for this order has already been recorded to prevent duplicates
    $check_query = "SELECT id FROM admin_profits WHERE order_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Profit already recorded for this order
        return false;
    }
    
    // Get order details including seller_id and amount from payment_table
    $order_query = "SELECT o.*, p.seller_id, pt.amount as payment_amount 
                   FROM orders_table o
                   JOIN tbl_product p ON o.product_id = p.product_id
                   LEFT JOIN payment_table pt ON o.order_id = pt.order_id
                   WHERE o.order_id = ? AND o.order_status != 'Cancelled'";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return false;
    }
    
    $order = $result->fetch_assoc();
    
    // Use payment_amount from payment_table or fall back to total_amount if not available
    $order_amount = $order['payment_amount'] ?? $order['total_amount'];
    
    // Calculate admin commission (30% of payment amount)
    $commission_rate = 0.30; 
    $profit_amount = $order_amount * $commission_rate;
    
    // Get current month in YYYY-MM-01 format
    $current_month = date('Y-m-01');
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First, check if there's an entry for this seller and month
        $check_query = "SELECT id, amount FROM admin_profits 
                       WHERE seller_id = ? AND month_year = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("is", $order['seller_id'], $current_month);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $record = $result->fetch_assoc();
            $update_query = "UPDATE admin_profits 
                            SET amount = amount + ?, 
                                orders_count = orders_count + 1 
                            WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("di", $profit_amount, $record['id']);
            $stmt->execute();
        } else {
            // Insert new record
            $insert_query = "INSERT INTO admin_profits 
                            (seller_id, amount, month_year, order_id, orders_count) 
                            VALUES (?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("idss", $order['seller_id'], $profit_amount, $current_month, $order_id);
            $stmt->execute();
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error calculating admin profit: " . $e->getMessage());
        return false;
    }
}

// Close the database connection when done
register_shutdown_function(function() use ($conn) {
    $conn->close();
});

// Reset admin_profits table
$conn->query("TRUNCATE TABLE admin_profits");

// Script to recalculate admin profits from existing orders
$orders_query = "SELECT order_id FROM orders_table WHERE payment_status = 'paid' AND order_status = 'Completed'";
$orders_result = $conn->query($orders_query);

while ($order = $orders_result->fetch_assoc()) {
    calculateAndRecordAdminProfit($conn, $order['order_id']);
}
?>