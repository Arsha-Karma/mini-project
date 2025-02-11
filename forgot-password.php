<?php
session_start();
require_once 'dbconnect.php';
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error_message = '';
$success_message = '';

function generateVerificationCode($length = 6) {
    $digits = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $digits[random_int(0, strlen($digits) - 1)];
    }
    return $code;
}

function sendVerificationEmail($recipientEmail, $verificationCode) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'arshaprasobh318@gmail.com';
        $mail->Password   = 'ilwf fpya pwkx pmat';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('arshakarma2027@mca.ajce.in', 'Perfume');
        $mail->addAddress($recipientEmail);
        $mail->Subject = 'Password Reset Verification Code';
        $mail->Body    = "Your verification code is: $verificationCode\n\nThis code will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

if(isset($_POST['send_code'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists in database
    $sql = "SELECT * FROM tbl_signup WHERE email = '$email'";
    $result = $conn->query($sql);

    if($result->num_rows > 0) {
        $verificationCode = generateVerificationCode();
        
        // Store verification code in session
        $_SESSION['reset_code'] = $verificationCode;
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_time'] = time();

        if(sendVerificationEmail($email, $verificationCode)) {
            $success_message = "Verification code has been sent to your email.";
            header("Location: verify-code.php");
            exit();
        } else {
            $error_message = "Failed to send verification code. Please try again.";
        }
    } else {
        $error_message = "Email address not found!";
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

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #999;
        }

        .required {
            color: red;
            margin-right: 5px;
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

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
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
        }

        .btn-back {
            background-color: #cd7f32;
            color: #fff;
        }

        .btn-continue {
            background-color: #cd7f32;
            color: #fff;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(205, 127, 50, 0.3);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo img {
            max-width: 300px;
            height: auto;
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
            
            .logo img {
                max-width: 200px;
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
            
            <input type="submit" name="send_code" value="Send Verification Code" class="btn btn-continue">
            
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