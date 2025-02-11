<?php
// dbconnect.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "perfume_store";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    // Select the database
    $conn->select_db($dbname);
    
    // Create tbl_signup table with updated_at column
    $sql_signup = "CREATE TABLE IF NOT EXISTS tbl_signup (
        Signup_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        Phoneno VARCHAR(15),
        role_type ENUM('admin', 'user', 'seller') NOT NULL DEFAULT 'user',
        Verification_Status ENUM('pending', 'verified') DEFAULT 'pending',
        otp VARCHAR(6) DEFAULT NULL,
        otp_expires TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql_signup) === FALSE) {
        echo "Error creating tbl_signup table: " . $conn->error;
    }
    // Create tbl_login table
    $sql_login = "CREATE TABLE IF NOT EXISTS tbl_login (
        login_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Signup_id INT(11) NOT NULL,
        Login_status ENUM('success', 'failed') DEFAULT 'failed',
        Last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql_login) === FALSE) {
        echo "Error creating tbl_login table: " . $conn->error;
    }
}

    // Create tbl_users table with updated_at column
    $sql_users = "CREATE TABLE IF NOT EXISTS tbl_users (
        user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Signup_id INT(11) NOT NULL,
        username VARCHAR(50) NOT NULL,
        role_type ENUM('admin', 'user', 'seller') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE,
        FOREIGN KEY (username) REFERENCES tbl_signup(username),
        UNIQUE INDEX idx_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql_users) === FALSE) {
        echo "Error creating tbl_users table: " . $conn->error;
    }

    // Create tbl_seller table
    $sql_seller = "CREATE TABLE IF NOT EXISTS tbl_seller (
        seller_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        Sellername VARCHAR(50) NOT NULL,
        Signup_id INT(11) NOT NULL,
        role_type ENUM('admin', 'user', 'seller') NOT NULL,
        Status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        FOREIGN KEY (Signup_id) REFERENCES tbl_signup(Signup_id) ON DELETE CASCADE,
        FOREIGN KEY (Sellername) REFERENCES tbl_signup(username),
        UNIQUE INDEX idx_sellername (Sellername)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql_seller) === FALSE) {
        echo "Error creating tbl_seller table: " . $conn->error;
    }


// Sanitize input data
function sanitize_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to validate email format
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate phone number
function validate_phone($phone) {
    return preg_match('/^[6-9]\d{9}$/', $phone) && !preg_match('/^[6-9]0{9}$/', $phone);
}

// Function to validate password strength
function validate_password($password) {
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Function to delete user account
function deleteUserAccount($conn, $user_id) {
    try {
        // Begin transaction for atomic operation
        $conn->begin_transaction();

        // Completely disable the account by marking it as inactive in multiple tables
        $tables = [
            'tbl_signup' => "UPDATE tbl_signup 
                SET Verification_Status = 'disabled', 
                    email = CONCAT('disabled_', email), 
                    Phoneno = NULL 
                WHERE Signup_id = ?",
            
            'tbl_users' => "UPDATE tbl_users 
                SET username = CONCAT('disabled_', username) 
                WHERE Signup_id = ?"
        ];

        foreach ($tables as $table => $query) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to disable account in {$table}");
            }
        }

        // Commit transaction
        $conn->commit();
        
        return ['success' => true, 'message' => 'Account permanently disabled'];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Account deletion error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to disable account'];
    }
}

// Modify login logic to prevent disabled accounts
function loginUser($conn, $email, $password) {
    $stmt = $conn->prepare("SELECT * FROM tbl_signup 
        WHERE email = ? 
        AND Verification_Status != 'disabled'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Account is disabled or does not exist'];
    }
    
    $user = $result->fetch_assoc();
    
    // Rest of your existing login verification logic
    // ...
}
function registerUser($conn, $username, $email, $mobile, $password, $role = 'user') {
    // Sanitize inputs
    $username = sanitize_input($conn, $username);
    $email = sanitize_input($conn, $email);
    $mobile = sanitize_input($conn, $mobile);
    
    // Validate inputs
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

    // Check if user already exists
    $existingUser = checkUser($conn, $username, $email);
    if ($existingUser['exists']) {
        return ['success' => false, 'message' => $existingUser['message']];
    }

    $conn->begin_transaction();
    
    try {
        // Hash the password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt1 = $conn->prepare("INSERT INTO tbl_signup (username, email, password, Phoneno, role_type, Verification_Status) VALUES (?, ?, ?, ?, ?, 'verified')");
        $stmt1->bind_param("sssss", $username, $email, $hashed_password, $mobile, $role);
        $stmt1->execute();
        $signup_id = $conn->insert_id;
        
        $stmt2 = $conn->prepare("INSERT INTO tbl_login (Signup_id) VALUES (?)");
        $stmt2->bind_param("i", $signup_id);
        $stmt2->execute();
        
        $conn->commit();
        return ['success' => true, 'signup_id' => $signup_id];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to check if user exists
function checkUser($conn, $username, $email) {
    $username = sanitize_input($conn, $username);
    $email = sanitize_input($conn, $email);
    
    $stmt1 = $conn->prepare("SELECT Signup_id FROM tbl_signup WHERE username = ?");
    $stmt1->bind_param("s", $username);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    
    $stmt2 = $conn->prepare("SELECT Signup_id FROM tbl_signup WHERE email = ?");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($result1->num_rows > 0) {
        return ['exists' => true, 'message' => 'Username already exists'];
    }
    if ($result2->num_rows > 0) {
        return ['exists' => true, 'message' => 'Email already registered'];
    }
    
    return ['exists' => false, 'message' => ''];
}

// Function to generate and store OTP
function generateOTP($conn, $email) {
    $email = sanitize_input($conn, $email);
    
    // Verify email exists
    $stmt = $conn->prepare("SELECT Signup_id FROM tbl_signup WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        return false;
    }
    
    $otp = rand(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare("UPDATE tbl_signup SET otp = ?, otp_expires = ? WHERE email = ?");
    $stmt->bind_param("sss", $otp, $expiry, $email);

    if ($stmt->execute()) {
        return $otp;
    }
    return false;
}

// Function to verify OTP
function verifyOTP($conn, $email, $otp) {
    $email = sanitize_input($conn, $email);
    $otp = sanitize_input($conn, $otp);
    
    $stmt = $conn->prepare("
        SELECT otp, otp_expires 
        FROM tbl_signup 
        WHERE email = ? 
        AND otp = ? 
        AND otp_expires > NOW()
    ");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 1) {
        // Clear OTP after successful verification
        $clear = $conn->prepare("UPDATE tbl_signup SET otp = NULL, otp_expires = NULL WHERE email = ?");
        $clear->bind_param("s", $email);
        $clear->execute();
        return true;
    }
    return false;
}

// Function to change password
function changePassword($conn, $email, $new_password) {
    if (!validate_password($new_password)) {
        return ['success' => false, 'message' => 'Password does not meet requirements'];
    }
    
    $email = sanitize_input($conn, $email);
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE tbl_signup SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed_password, $email);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Password updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update password'];
}
?>


