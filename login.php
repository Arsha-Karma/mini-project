<<<<<<< HEAD
=======

>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
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

<<<<<<< HEAD
// Handle Google login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['google_login'])) {
    $google_email = isset($_POST['google_email']) ? sanitize_input($conn, $_POST['google_email']) : '';
    $google_name = isset($_POST['google_name']) ? sanitize_input($conn, $_POST['google_name']) : '';
    $google_uid = isset($_POST['google_uid']) ? sanitize_input($conn, $_POST['google_uid']) : '';
    
    if (empty($google_email) || empty($google_uid)) {
        $error_message = "Invalid Google account data";
    } else {
        try {
            // Check if user already exists
            $stmt = $conn->prepare("SELECT Signup_id, username, role_type, verification_status 
                                  FROM tbl_signup 
                                  WHERE email = ? OR google_uid = ?");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
            
            $stmt->bind_param("ss", $google_email, $google_uid);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute statement: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                // User exists, login the user
                $user = $result->fetch_assoc();
                
                if ($user['verification_status'] === 'disabled') {
                    $show_deactivation_modal = true;
                    // If you have the recordLoginAttempt function
                    if (function_exists('recordLoginAttempt')) {
                        recordLoginAttempt($conn, $user['Signup_id'], 'failed');
                    }
                } else {
                    // If you have the recordLoginAttempt function
                    if (function_exists('recordLoginAttempt')) {
                        recordLoginAttempt($conn, $user['Signup_id'], 'success');
                    }
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['Signup_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role_type'];
                    $_SESSION['logged_in'] = true;
                    
                    // Redirect based on role
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
                }
            } else {
                // User doesn't exist, register the user
                // Generate username from Google name or email
                $username = preg_replace('/[^a-zA-Z0-9]/', '', $google_name);
                $username = strtolower($username ?: strtok($google_email, '@')) . rand(100, 999);
                $role_type = 'user'; // Default role
                
                // Insert new user into the database
                // Note: You need to have email and google_uid columns in your tbl_signup table
                $stmt = $conn->prepare("INSERT INTO tbl_signup (username, email, google_uid, role_type, verification_status, created_at) 
                                     VALUES (?, ?, ?, ?, 'verified', NOW())");
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                
                $stmt->bind_param("ssss", $username, $google_email, $google_uid, $role_type);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute statement: " . $stmt->error);
                }
                
                $userId = $conn->insert_id;
                
                // If you have the recordLoginAttempt function
                if (function_exists('recordLoginAttempt')) {
                    recordLoginAttempt($conn, $userId, 'success');
                }
                
                // Set session variables
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role_type;
                $_SESSION['logged_in'] = true;
                
                // Redirect to the appropriate page
                header("Location: index.php");
                exit();
            }
        } catch (Exception $e) {
            error_log("Google login error: " . $e->getMessage());
            $error_message = "An error occurred during Google login. Please try again later.";
        }
    }
}

// Regular login handling
=======
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
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
<<<<<<< HEAD
    <!-- Add Google Sign-In API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
=======
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
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

<<<<<<< HEAD
        /* Google Sign In Button */
        .or-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #aaa;
        }

        .or-divider::before,
        .or-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .or-divider::before {
            margin-right: 10px;
        }

        .or-divider::after {
            margin-left: 10px;
        }

        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px;
            background-color: white;
            border: 1px solid #dadce0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #757575;
            font-weight: 500;
            text-align: center;
            margin-top: 10px;
        }

        .google-btn:hover {
            background-color: #f5f5f5;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .google-btn svg {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }

=======
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
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
<<<<<<< HEAD
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
=======
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
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
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
<<<<<<< HEAD
            
            <div class="or-divider">or</div>
            
            <!-- Google Sign-In Button -->
            <button type="button" id="google-login-btn" class="google-btn">
                <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    <path fill="none" d="M0 0h48v48H0z"/>
                </svg>
                 &nbsp; &nbsp; &nbsp;Continue with Google
            </button>
=======
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db

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
<<<<<<< HEAD

        // Firebase configuration for Google Authentication
        document.addEventListener('DOMContentLoaded', function() {
            // Load Firebase scripts dynamically
            const loadScript = (src) => {
                return new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = src;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            };

            // Load Firebase scripts in sequence
            loadScript('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js')
                .then(() => loadScript('https://www.gstatic.com/firebasejs/9.22.0/firebase-auth-compat.js'))
                .then(() => {
                    // Initialize Firebase
                    const firebaseConfig = {
                        apiKey: "AIzaSyCqfvYqt5NsPZH6Ib93tDOmWHHG0CEXkQw",
                        authDomain: "login-6ff35.firebaseapp.com",
                        projectId: "login-6ff35",
                        storageBucket: "login-6ff35.firebasestorage.app",
                        messagingSenderId: "355096580337",
                        appId: "1:355096580337:web:96cefdea88d02cf07f9950"
                    };

                    firebase.initializeApp(firebaseConfig);
                    const auth = firebase.auth();
                    auth.languageCode = 'en';
                    const provider = new firebase.auth.GoogleAuthProvider();
                    
                    // Get Google login button
                    const googleLogin = document.getElementById("google-login-btn");
                    
                    if (googleLogin) {
                        googleLogin.addEventListener("click", function() {
                            auth.signInWithPopup(provider)
                                .then((result) => {
                                    const user = result.user;
                                    console.log(user);
                                    
                                    // Send the user data to the server via a form submission
                                    const form = document.createElement('form');
                                    form.method = 'POST';
                                    form.action = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';
                                    form.style.display = 'none';
                                    
                                    // Add hidden fields with user data
                                    const fields = {
                                        'google_login': '1',
                                        'google_email': user.email,
                                        'google_name': user.displayName,
                                        'google_uid': user.uid
                                    };
                                    
                                    for (const key in fields) {
                                        const input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = key;
                                        input.value = fields[key];
                                        form.appendChild(input);
                                    }
                                    
                                    document.body.appendChild(form);
                                    form.submit();
                                })
                                .catch((error) => {
                                    const errorCode = error.code;
                                    const errorMessage = error.message;
                                    console.error(errorCode, errorMessage);
                                    
                                    // Display error to user
                                    const errorDiv = document.createElement('div');
                                    errorDiv.className = 'error-message';
                                    errorDiv.textContent = "Google login failed: " + errorMessage;
                                    
                                    const container = document.querySelector('.container');
                                    const existingError = container.querySelector('.error-message');
                                    
                                    if (existingError) {
                                        container.replaceChild(errorDiv, existingError);
                                    } else {
                                        container.insertBefore(errorDiv, container.firstChild.nextSibling);
                                    }
                                });
                        });
                    }
                })
                .catch(error => {
                    console.error("Error loading Firebase scripts:", error);
                });
        });
=======
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
    </script>
</body>
</html>