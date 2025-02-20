<?php
session_start();
require_once 'dbconnect.php';
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize message variables
$error_message = '';
$success_message = '';

// Check for messages in session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Add required columns to tbl_signup if they don't exist
$sql = "ALTER TABLE tbl_signup 
        ADD COLUMN IF NOT EXISTS verification_code VARCHAR(10),
        ADD COLUMN IF NOT EXISTS code_expiry DATETIME,
        ADD COLUMN IF NOT EXISTS reset_attempts INT DEFAULT 0";
$conn->query($sql);

function generateVerificationCode() {
    return strval(rand(100000, 999999));
}

function initializeMailer() {
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = 'smtp.gmail.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = 'arshaprasobh318@gmail.com';
    $mailer->Password = 'ilwf fpya pwkx pmat';
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = 587;
    $mailer->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
    $mailer->isHTML(true);
    return $mailer;
}

function sendVerificationEmail($email, $code) {
    try {
        $mailer = initializeMailer();
        $mailer->addAddress($email);
        $mailer->Subject = 'Password Reset Verification Code';
        
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Password Reset Request</h2>
                <p>Your verification code is: <strong>{$code}</strong></p>
                <p>This code will expire in 10 minutes.</p>
                <p>If you didn't request this reset, please ignore this email.</p>
            </div>";
        
        $mailer->Body = $body;
        $mailer->AltBody = strip_tags($body);
        
        return $mailer->send();
    } catch (Exception $e) {
        error_log("Email sending failed for $email: " . $mailer->ErrorInfo);
        return false;
    }
}

function processPasswordReset($conn, $email) {
    $email = mysqli_real_escape_string($conn, $email);
    
    // Check for existing user
    $sql = "SELECT * FROM tbl_signup WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Email address not found!'];
    }

    $user = $result->fetch_assoc();
    
    // Check rate limiting (max 10 attempts per hour)
    if ($user['reset_attempts'] >= 10) {
        $sql = "SELECT code_expiry FROM tbl_signup WHERE email = ? AND code_expiry > NOW() - INTERVAL 1 HOUR";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Too many attempts. Please try again later.'];
        }
        // Reset attempts after 1 hour
        $sql = "UPDATE tbl_signup SET reset_attempts = 0 WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
    }

    $code = generateVerificationCode();
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Update user with new verification code
    $sql = "UPDATE tbl_signup SET 
            verification_code = ?,
            code_expiry = ?,
            reset_attempts = COALESCE(reset_attempts, 0) + 1
            WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $code, $expiry, $email);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Database error occurred.'];
    }

    if (sendVerificationEmail($email, $code)) {
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_time'] = time();
        $_SESSION['reset_code'] = $code;
        
        return [
            'success' => true,
            'message' => 'Verification code sent successfully.',
            'email' => $email
        ];
    }

    return ['success' => false, 'message' => 'Failed to send verification code.'];
}

function verifyCode($conn, $email, $code) {
    $sql = "SELECT * FROM tbl_signup 
            WHERE email = ? 
            AND verification_code = ? 
            AND code_expiry > NOW()";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid or expired code.'];
    }

    // Clear the verification code after successful verification
    $sql = "UPDATE tbl_signup SET 
            verification_code = NULL,
            code_expiry = NULL,
            reset_attempts = 0
            WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    return ['success' => true, 'message' => 'Code verified successfully.'];
}

// Handle form submission
if (isset($_POST['send_code'])) {
    $result = processPasswordReset($conn, $_POST['email']);
    
    if ($result['success']) {
        $success_message = 'Verification code has been sent to your email.';
        $_SESSION['success_message'] = $success_message;
        
        header("Location: verify-code.php");
        exit();
    } else {
        $error_message = $result['message'];
    }
}

// Handle verification code submission
if (isset($_POST['verify_code'])) {
    $result = verifyCode($conn, $_SESSION['reset_email'], $_POST['code']);
    
    if ($result['success']) {
        header("Location: reset-password.php");
        exit();
    } else {
        $error_message = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            color: #fff;
            min-height: 100vh;
            background-image: url('image/image1.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background-color: rgba(0, 0, 0, 0.8);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            margin: 20px;
            backdrop-filter: blur(5px);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        h2 {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
            color: #fff;
        }

        .description {
            color: #999;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #333;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 16px;
            box-sizing: border-box;
            border-radius: 5px;
            transition: border-color 0.3s;
        }

        input[type="email"].error {
            border-color: #ff4444;
        }

        input[type="email"].valid {
            border-color: #00C851;
        }

        .validation-message {
            position: absolute;
            left: 0;
            bottom: -20px;
            font-size: 14px;
            display: none;
        }

        .error-message {
            color: #ff4444;
        }

        .success-message {
            color: #00C851;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            width: 100%;
            margin: 10px 0;
            background-color: #cd7f32;
            color: #fff;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(205, 127, 50, 0.3);
        }

        .error-container.show {
            display: block;
            margin: 10px 0;
        }

        .error {
            color: #ff4444;
            background: rgba(255, 68, 68, 0.1);
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
        }

        .success {
            color: #00C851;
            background: rgba(0, 200, 81, 0.1);
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-login a {
            color: #cd7f32;
            text-decoration: none;
            transition: color 0.3s;
        }

        .back-to-login a:hover {
            color: #fff;
        }

        @media (max-width: 480px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <form method="post" action="">
            <h2>Forgot Password</h2>
            <p class="description">Enter your email address and we'll send you a verification code to reset your password.</p>
            
            <?php if($error_message): ?>
            <div class="error-container show">
                <div class="error"><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>
            
            <?php if($success_message): ?>
            <div class="error-container show">
                <div class="success"><?php echo $success_message; ?></div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your email address" required>
                <div class="validation-message"></div>
            </div>
            
            <input type="submit" name="send_code" value="Send Verification Code" class="btn">
            
            <div class="back-to-login">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>

    <script>
        const form = document.querySelector('form');
        const emailInput = document.querySelector('input[name="email"]');
        const validationMessage = document.querySelector('.validation-message');

        const validateEmail = (email) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        };

        emailInput.addEventListener('input', () => {
            if (validateEmail(emailInput.value)) {
                emailInput.classList.remove('error');
                emailInput.classList.add('valid');
                validationMessage.style.display = 'none';
            } else {
                emailInput.classList.remove('valid');
                emailInput.classList.add('error');
                validationMessage.style.display = 'block';
                validationMessage.className = 'validation-message error-message';
                validationMessage.textContent = 'Please enter a valid email address';
            }
        });

        form.addEventListener('submit', (e) => {
            if (!validateEmail(emailInput.value)) {
                e.preventDefault();
                emailInput.classList.add('error');
                validationMessage.style.display = 'block';
                validationMessage.className = 'validation-message error-message';
                validationMessage.textContent = 'Please enter a valid email address';
            }
        });
    </script>
</body>
</html>