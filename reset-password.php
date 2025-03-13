<?php
session_start();
require_once 'dbconnect.php';

// Check if user is verified
if(!isset($_SESSION['verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

$error_message = '';
$success_message = '';

if(isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } elseif(strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long!";
    } else {
        $email = $_SESSION['reset_email'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE tbl_signup SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if($stmt->execute()) {
            // Clear all session variables
            session_unset();
            session_destroy();
            
            // Start new session for success message
            session_start();
            $_SESSION['password_reset_success'] = true;
            
            header("Location: login.php");
            exit();
        } else {
            $error_message = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
      body {
    background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.9)),url('background.jpg');
    background-size: cover;
    background-image: url('image/image1.jpg');
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    font-family: system-ui, -apple-system, sans-serif;
    color: #fff;
}

form {
    background: rgba(18, 18, 18, 0.8);
    backdrop-filter: blur(10px);
    max-width: 450px;
    width: 90%;
    margin: 20px auto;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

h2 {
    color: #fff;
    text-align: center;
    margin-bottom: 40px;
    font-size: 32px;
    font-weight: 500;
}

.input-group {
    margin-bottom: 25px;
    position: relative;
}

.input-label {
    display: block;
    color: #999;
    margin-bottom: 8px;
    font-size: 16px;
}

.required {
    color: #e74c3c;
    margin-right: 4px;
}

input {
    width: 100%;
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    color: #fff;
    font-size: 16px;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

input:focus {
    outline: none;
    border-color: #cd853f;
    background: rgba(255, 255, 255, 0.15);
}

input[type="password"] {
    padding-right: 40px;
}

.submit-btn {
    width: 100%;
    padding: 14px;
    background: #cd853f;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 30px;
}

.submit-btn:hover {
    background: #b47333;
}

.error-container.show {
    background: rgba(231, 76, 60, 0.1);
    border: 1px solid rgba(231, 76, 60, 0.3);
    color: #e74c3c;
    padding: 12px;
    border-radius: 4px;
    margin: 20px 0;
    text-align: center;
    font-size: 14px;
}

.password-info {
    color: #999;
    font-size: 13px;
    text-align: center;
    margin: 15px 0;
    padding: 10px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.container {
    text-align: left;
}

@media (max-width: 480px) {
    form {
        padding: 30px 20px;
    }
    
    h2 {
        font-size: 24px;
        margin-bottom: 30px;
    }
    
    input, .submit-btn {
        padding: 10px;
        font-size: 14px;
    }
}
    </style>
</head>
<body>
    <form method="post" action="">
        <div class="container">
           
            
            <h2>Reset Password</h2>
            <!-- <p class="description">Please enter your new password</p> -->
            
            <?php if($error_message): ?>
            <div class="error-container show">
                <div class="error"><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>
            
            <!-- <div class="password-info">
                Password must be at least 8 characters long
            </div> -->
            
            <div class="input-group">
                <input type="password" name="password" placeholder="New Password" required>
            </div>
            
            <div class="input-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            
            <input type="submit" name="reset_password" value="Reset Password" class="submit-btn">
        </div>
    </form>
</body>
</html>