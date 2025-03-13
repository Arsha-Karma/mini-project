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

// Check if this is a resend request via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['reset_email'])) {
        echo json_encode(['success' => false, 'message' => 'No email in session']);
        exit;
    }

    $newCode = generateVerificationCode();
    if (sendVerificationEmail($_SESSION['reset_email'], $newCode)) {
        $_SESSION['reset_code'] = $newCode;
        $_SESSION['reset_time'] = time();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }
    exit;
}

// Regular form verification
if(!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot password.php");
    exit();
}

// Check if verification code has expired (10 minutes)
if(time() - $_SESSION['reset_time'] > 600) {
    unset($_SESSION['reset_code']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_time']);
    header("Location: forgot-password.php");
    exit();
}

// Function to generate verification code
function generateVerificationCode($length = 6) {
    $digits = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $digits[random_int(0, strlen($digits) - 1)];
    }
    return $code;
}

// Function to send email
function sendVerificationEmail($recipientEmail, $verificationCode) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'arshaprasobh318@gmail.com'; // Your email
        $mail->Password   = 'ilwf fpya pwkx pmat';       // Your password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('arshaprasobh318@gmail.com', 'Perfume Paradise');
        $mail->addAddress($recipientEmail);
        $mail->Subject = 'New Password Reset Verification Code';
        $mail->Body    = "Your new verification code is: $verificationCode\n\nThis code will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle regular code verification
if(isset($_POST['verify_code'])) {
    $entered_code = $_POST['verification_code'];
    
    if($entered_code == $_SESSION['reset_code']) {
        $_SESSION['verified'] = true;
        header("Location: reset-password.php");
        exit();
    } else {
        $error_message = "Invalid verification code!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Code</title>
    <style>
        /* Same styles as forgot-password.php */
        body {
    background: linear-gradient(rgba(0,0,0,0.8), rgba(0,0,0,0.9)),url('background.jpg');
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
    background: rgba(25, 25, 25, 0.95);
    max-width: 450px;
    width: 90%;
    margin: 20px auto;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.logo {
    text-align: center;
    margin-bottom: 30px;
}

.logo img {
    max-width: 200px;
    height: auto;
}

h2 {
    color: #fff;
    text-align: center;
    margin-bottom: 20px;
    font-size: 32px;
    font-weight: 500;
}

.description {
    text-align: center;
    color: #999;
    margin-bottom: 30px;
    font-size: 15px;
    line-height: 1.5;
}

.description strong {
    color: #fff;
    font-weight: normal;
}

.input-group {
    margin-bottom: 25px;
}

.verification-code-input {
    width: 100%;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    color: #fff;
    font-size: 18px;
    letter-spacing: 4px;
    text-align: center;
    transition: all 0.3s ease;
}

.verification-code-input:focus {
    outline: none;
    border-color: #cd853f;
    background: rgba(255, 255, 255, 0.15);
}

.submit-btn {
    width: 100%;
    padding: 14px;
    background: #cd853f;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.submit-btn:hover {
    background: #b47333;
}

.timer {
    text-align: center;
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
    margin-bottom: 15px;
}

.resend-code {
    text-align: center;
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
    margin-bottom: 20px;
}

.resend-link {
    color: #cd853f;
    text-decoration: none;
    transition: color 0.3s ease;
}

.resend-link:hover {
    color: #b47333;
}

.back-button {
    text-align: center;
}

.back-button a {
    color: #2196f3;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.back-button a:hover {
    color: #1976d2;
}

.error-container.show {
    background: rgba(231, 76, 60, 0.1);
    border: 1px solid rgba(231, 76, 60, 0.3);
    color: #e74c3c;
    padding: 12px;
    border-radius: 6px;
    margin: 20px 0;
    text-align: center;
    font-size: 14px;
}

@media (max-width: 480px) {
    form {
        padding: 30px 20px;
    }
    
    h2 {
        font-size: 24px;
    }
    
    .verification-code-input {
        font-size: 16px;
        letter-spacing: 3px;
    }
    
    .logo img {
        max-width: 150px;
    }
}
    </style>
</head>
<body>
    <form method="post" action="">
        <div class="container">
            
            
            <h2>Verify Code</h2>
            <p class="description">Please enter the verification code sent to<br><strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong></p>
            
            <?php if($error_message): ?>
            <div class="error-container show">
                <div class="error"><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>
            
            <div class="input-group">
                <input type="text" 
                       name="verification_code" 
                       class="verification-code-input" 
                       placeholder="Enter code" 
                       maxlength="6" 
                       pattern="[0-9]{6}" 
                       required>
            </div>
            
            <input type="submit" name="verify_code" value="Verify Code" class="submit-btn">
            
            <div class="timer">
                Code expires in: <span id="countdown">10:00</span>
            </div>
            
            <div class="resend-code">
                Didn't receive the code? 
                <a href="#" id="resend-link" style="display: none; text-decoration: none; color: orange;">Resend Code</a>
                <span id="resend-timer">Wait <span id="resend-countdown">30</span>s to resend</span>
            </div>
            
            <div class="back-button">
                <a href="forgot-password.php">← Back to Forgot Password</a>
            </div>
        </div>
    </form>

    <script>
       // Timer for code expiration
       function startExpirationTimer() {
        let timeLeft = 600;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                window.location.href = 'forgot-password.php';
            }
            
            timeLeft--;
        }, 1000);
        return timer;
    }

    function startResendTimer() {
        let timeLeft = 30;
        const resendLink = document.getElementById('resend-link');
        const resendTimer = document.getElementById('resend-timer');
        const resendCountdown = document.getElementById('resend-countdown');
        
        resendLink.style.display = 'none';
        resendTimer.style.display = 'inline';
        
        const timer = setInterval(() => {
            resendCountdown.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                resendLink.style.display = 'inline';
                resendTimer.style.display = 'none';
            }
            
            timeLeft--;
        }, 1000);
        return timer;
    }

    // Updated resend code functionality
    document.getElementById('resend-link').addEventListener('click', async (e) => {
        e.preventDefault();
        
        try {
            const formData = new FormData();
            formData.append('resend', 'true');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                startResendTimer();
                // Show success message
                const successDiv = document.createElement('div');
                successDiv.className = 'error-container show';
                successDiv.innerHTML = '<div class="success">New code has been sent!</div>';
                document.querySelector('.input-group').before(successDiv);
                
                // Remove success message after 5 seconds
                setTimeout(() => {
                    successDiv.remove();
                }, 5000);
            } else {
                throw new Error(data.message || 'Failed to resend code');
            }
        } catch (error) {
            console.error('Error resending code:', error);
            
            // Show error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-container show';
            errorDiv.innerHTML = `<div class="error">${error.message || 'Failed to resend code. Please try again.'}</div>`;
            document.querySelector('.input-group').before(errorDiv);
            
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
    });

    // Format verification code input
    const codeInput = document.querySelector('.verification-code-input');
    codeInput.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
    });

    // Initialize timers when page loads
    let expirationTimer;
    let resendTimer;

    document.addEventListener('DOMContentLoaded', () => {
        if (expirationTimer) clearInterval(expirationTimer);
        if (resendTimer) clearInterval(resendTimer);
        
        expirationTimer = startExpirationTimer();
        resendTimer = startResendTimer();
    });
    </script>
</body>
</html>