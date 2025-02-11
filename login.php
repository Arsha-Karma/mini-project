<?php
session_start();
require_once 'dbconnect.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
     $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password";
    } else {
        $stmt = $conn->prepare("SELECT * FROM tbl_signup WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['Signup_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role_type'];
                $_SESSION['logged_in'] = true;

                // Comprehensive login tracking
                $login_stmt = $conn->prepare("INSERT INTO tbl_login (
                    Signup_id, 
                    Login_status, 
                    Last_login
                ) VALUES (?, 'success', NOW())");
                $login_stmt->bind_param("i", $user['Signup_id']);
                $login_stmt->execute();

                switch ($user['role_type']) {
                    case 'admin':
                        header("Location: admindashboard.php");
                        break;
                    case 'seller':
                        header("Location: sellerdashboard.php");
                        break;
                    default:
                        header("Location: index.php");
                        break;
                }
                exit();
            } else {
                // Track failed login attempt
                $failed_login_stmt = $conn->prepare("INSERT INTO tbl_login (
                    Signup_id, 
                    Login_status, 
                    Last_login
                ) VALUES (?, 'failed', NOW())");
                $failed_login_stmt->bind_param("i", $user['Signup_id']);
                $failed_login_stmt->execute();

                $error_message = "Invalid password";
            }
        } else {
            $error_message = "Invalid username";
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

        <?php if (!empty($error_message)): ?>
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
</body>
</html>