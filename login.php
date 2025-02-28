
<?php
session_start();
require_once('dbconnect.php'); // Include the database connection file

$error_message = '';
$success_message = '';
$show_deactivation_modal = false;

// Check for message from account disabling
if (isset($_GET['message']) && $_GET['message'] === 'account_disabled') {
    $success_message = "Your account has been disabled successfully.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = sanitize_input($conn, $_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password";
    } else {
        try {
            $stmt = $conn->prepare("SELECT Signup_id, username, password, role_type, verification_status 
                                  FROM tbl_signup 
                                  WHERE username = ?");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            $stmt->bind_param("s", $username);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute statement: " . $stmt->error);
            }

            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if ($user['verification_status'] === 'disabled') {
                    $show_deactivation_modal = true;
                    recordLoginAttempt($conn, $user['Signup_id'], 'failed');
                } else if (password_verify($password, $user['password'])) {
                    recordLoginAttempt($conn, $user['Signup_id'], 'success');
                    
                    $_SESSION['user_id'] = $user['Signup_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role_type'];
                    $_SESSION['logged_in'] = true;

                    switch ($user['role_type']) {
                        case 'admin':
                            header("Location: admindashboard.php");
                            break;
                        case 'seller':
                            header("Location: seller-dashboard.php");
                            break;
                        default:
                            header("Location: index.php");
                            break;
                    }
                    exit();
                } else {
                    recordLoginAttempt($conn, $user['Signup_id'], 'failed');
                    $error_message = "Invalid password";
                }
            } else {
                $error_message = "Invalid username";
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "An error occurred during login. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            background-image: url('image/image1.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .container {
            max-width: 400px;
            width: 100%;
            padding: 25px;
            background-color: rgba(17, 17, 17, 0.85);
            border: 1px solid rgba(51, 51, 51, 0.5);
            border-radius: 8px;
            position: relative;
            z-index: 2;
            backdrop-filter: blur(5px);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #fff;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 10px;
            background-color: rgba(34, 34, 34, 0.8);
            border: 1px solid rgba(51, 51, 51, 0.8);
            color: #fff;
            border-radius: 4px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #e17055;
            box-shadow: 0 0 5px rgba(225, 112, 85, 0.3);
        }

        .error-message {
            color: #ff6b6b;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }

        .success-message {
            color: #4CAF50;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }

        .login-btn {
            background-color: #e17055;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background-color: #d65d45;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(225, 112, 85, 0.4);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 400px;
            z-index: 1001;
            text-align: center;
        }

        .modal-header {
            position: relative;
            margin-bottom: 20px;
        }

        .warning-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            border: 2px solid #ff6b6b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .warning-icon span {
            color: #ff6b6b;
            font-size: 24px;
            font-weight: bold;
        }

        .modal-title {
            color: #333;
            font-size: 24px;
            margin: 0;
            padding: 0;
        }

        .modal-content {
            color: #666;
            margin: 20px 0;
            font-size: 16px;
            line-height: 1.5;
        }

        .modal-button {
            background-color: #4299e1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .modal-button:hover {
            background-color: #3182ce;
        }
        h2 { 
    color: #fff; 
    margin-bottom: 25px; 
    text-align: center; 
}

a {
    color: #e17055;
    text-decoration: none;
}

a:hover {
    color: #d65d45;
}
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>

        <?php if (!empty($error_message) && !$show_deactivation_modal): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
            <div class="form-group">
                <label for="login-username">Username</label>
                <input type="text" 
                       name="username" 
                       id="login-username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       placeholder="Enter your username"
                       required>
            </div>

            <div class="form-group">
                <label for="login-password">Password</label>
                <input type="password" 
                       name="password" 
                       id="login-password"
                       placeholder="Enter your password"
                       required>
            </div>

            <div class="form-group">
                <a href="forgot-password.php">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="login-btn">Login</button>

            <p style="text-align: center; margin-top: 20px;">
                Don't have an account? <a href="signup.php">Sign Up</a>
            </p>
        </form>
    </div>

    <!-- Deactivation Modal -->
    <div id="deactivationModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="warning-icon">
                    <span>!</span>
                </div>
                <h2 class="modal-title">Alert</h2>
            </div>
            <div class="modal-content">
                Your account has been disabled. If you didn't request this action, please contact support for assistance.
            </div>
            <button class="modal-button" onclick="closeModal()">OK</button>
        </div>
    </div>

    <script>
        // Show modal if PHP sets the flag
        <?php if ($show_deactivation_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('deactivationModal').style.display = 'block';
        });
        <?php endif; ?>

        // Function to close the modal
        function closeModal() {
            document.getElementById('deactivationModal').style.display = 'none';
            window.location.href = 'login.php';
        }

        // Close modal if clicking outside
        document.addEventListener('click', function(event) {
            var modal = document.querySelector('.modal');
            var overlay = document.getElementById('deactivationModal');
            if (event.target === overlay) {
                overlay.style.display = 'none';
                window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>