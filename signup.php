<?php
session_start();
require_once 'dbconnect.php';

$registrationSuccess = false;
$registrationError = "";
$errors = [];
$showOTPForm = false;
$email = "";

function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validatePhoneNumber($number) {
    if (!preg_match('/^[6-9]\d{9}$/', $number)) {
        return [
            'isValid' => false,
            'message' => "Must be 10 digits and start with 9, 8, 7, or 6"
        ];
    }
    if (preg_match('/^[6-9]0{9}$/', $number)) {
        return [
            'isValid' => false,
            'message' => "Invalid number format"
        ];
    }
    return ['isValid' => true, 'message' => ""];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $number = trim($_POST['number']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role_type = isset($_POST["role_type"]) ? test_input($_POST["role_type"]) : 'user';

        // Validation checks
        if (empty($username)) {
            $errors[] = "Username is required";
        }

        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        if (empty($number)) {
            $errors[] = "Mobile number is required";
        } else {
            $phoneValidation = validatePhoneNumber($number);
            if (!$phoneValidation['isValid']) {
                $errors[] = $phoneValidation['message'];
            }
        }

        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }

        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }

        // Prevent multiple admin accounts
        if ($role_type === 'admin') {
            $check_admin_sql = "SELECT COUNT(*) as admin_count FROM tbl_signup WHERE role_type = 'admin'";
            $check_admin_result = $conn->query($check_admin_sql);
            $check_admin_row = $check_admin_result->fetch_assoc();

            if ($check_admin_row['admin_count'] > 0) {
                $errors[] = "Error: Only one admin account is allowed.";
            }
        }

        // If no errors, proceed with registration using transaction
        if (empty($errors)) {
            // Start transaction to ensure data consistency
            $conn->begin_transaction();
            
            try {
                // Call the registerUser function from dbconnect.php
                $registrationResult = registerUser($conn, $username, $email, $number, $password, $role_type);
                
                if ($registrationResult['success']) {
                    $Signup_id = $registrationResult['signup_id'];
                    
                    // Handle role-specific operations within the same transaction
                    if ($role_type === 'user') {
                        // Insert into tbl_users
                        $user_sql = "INSERT INTO tbl_users (Signup_id, username, role_type) VALUES (?, ?, ?)";
                        $user_stmt = $conn->prepare($user_sql);
                        $user_stmt->bind_param("iss", $Signup_id, $username, $role_type);
                        $user_stmt->execute();
                    }
                    elseif ($role_type === 'seller') {
                        // Insert into tbl_seller
                        $seller_sql = "INSERT INTO tbl_seller (Signup_id, Sellername, role_type, Status) VALUES (?, ?, ?, 'pending')";
                        $seller_stmt = $conn->prepare($seller_sql);
                        $seller_stmt->bind_param("iss", $Signup_id, $username, $role_type);
                        $seller_stmt->execute();
                    }
                    
                    // Commit the transaction
                    $conn->commit();
                    $registrationSuccess = true;
                } else {
                    // Roll back if registration failed
                    $conn->rollback();
                    $registrationError = $registrationResult['message'];
                }
            } catch (Exception $e) {
                // Roll back on any error
                $conn->rollback();
                $registrationError = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: system-ui, sans-serif;
    }

    body {
        min-height: 100vh;
        background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)),url('login.jpg');
        background-repeat: no-repeat;
        background-size: cover;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 15px;
        color: white;
        background-image: url('image/image1.jpg');
    }

    .container {
        background: rgba(0, 0, 0, 0.8);
        padding: 20px 30px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        width: 100%;
        max-width: 500px;
        text-align: center;
        backdrop-filter: blur(10px);
        min-height: 550px;
    }

    h2 {
        color: white;
        margin-bottom: 20px;
        font-size: 2rem;
    }

    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #ccc;
        font-size: 0.9rem;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="tel"],
    select {
        width: 100%;
        padding: 12px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        font-size: 14px;
        color: white;
        transition: all 0.3s ease;
    }

    input:focus,
    select:focus {
        border-color: rgb(231, 111, 81);
        outline: none;
        background: rgba(255, 255, 255, 0.15);
    }

    .helper-text {
        display: block;
        color: #ccc;
        font-size: 12px;
        margin-top: 4px;
    }

    .submit-btn {
        width: 100%;
        padding: 12px;
        background: rgb(231, 111, 81);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 20px;
    }

    .submit-btn:hover {
        background: rgb(211, 91, 61);
    }

    .switch-form {
        margin-top: 20px;
        color: #ccc;
        font-size: 14px;
    }

    .switch-form a {
        color: rgb(231, 111, 81);
        text-decoration: none;
    }

    .error {
        color: #e74c3c;
        padding: 8px;
        margin-bottom: 15px;
        font-size: 13px;
    }

    .success {
        color: #2ecc71;
        padding: 8px;
        margin-bottom: 15px;
        font-size: 13px;
    }

    .feedback {
        font-size: 12px;
        margin-top: 4px;
        text-align: left;
        transition: all 0.3s ease;
    }

    .otp-form {
        display: none;
        margin-top: 20px;
    }
    
    .otp-form.active {
        display: block;
    }
    
    .otp-input {
        letter-spacing: 8px;
        font-size: 20px;
        text-align: center;
    }

    .password-container {
        position: relative;
    }

    .password-container input {
        padding-right: 40px;
    }

    .password-container .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #ccc;
    }

    select[name="role_type"] {
        background-color: rgba(33, 33, 33, 0.5); 
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        padding: 12px;
        width: 100%;
        appearance: none; 
    }

    select[name="role_type"]:focus {
        background-color: rgba(33, 33, 33, 0.5); 
        border-color: rgba(255, 255, 255, 0.2);
        outline: none;
    }

    select[name="role_type"] option {
        background-color: rgb(33, 33, 33);
        color: white;
        padding: 12px;
    }

    @media (max-width: 680px) {
        .container {
            padding: 25px;
            max-width: 90%;
            min-height: 600px;
        }
        
        h2 {
            font-size: 1.8rem;
        }
        
        input, .submit-btn {
            padding: 10px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div id="success-message" style="display: none; color: rgb(231, 111, 81);">
            Registration successful! You can now login.
        </div>

        <!-- Registration Form -->
        <form method="post" action="" id="signup-form" style="<?php echo $showOTPForm ? 'display: none;' : ''; ?>">
            <h2>Sign Up</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required>
                <div class="feedback"></div>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter email" required>
                <div class="feedback"></div>
            </div>
            
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="tel" id="number" name="number" placeholder="Enter mobile number" required>
                <div class="feedback"></div>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" placeholder="Enter password" required>
                <div class="feedback"></div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>
                <div class="feedback"></div>
            </div>
            
            <div class="form-group">
                <label>Role Type</label>
                <select name="role_type" required>
                    <option value="user">User</option>
                    <option value="seller">Seller</option>
                    
                </select>
            </div>
            
            <button type="submit" name="register" class="submit-btn">Sign Up</button>
            
            <div class="switch-form">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </form>
    </div>

    <script>
        const form = document.querySelector('form');
        const usernameInput = document.querySelector('input[name="username"]');
        const emailInput = document.querySelector('input[name="email"]');
        const numberInput = document.getElementById('number');
        const passwordInput = document.querySelector('input[name="password"]');
        const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');

        const validateUsername = (username) => {
            const minLength = username.length >= 3;
            const validChars = /^[a-zA-Z0-9_]+$/.test(username);
            const hasAtLeastTwoLetters = (username.match(/[a-zA-Z]/g) || []).length >= 2;
            
            return {
                isValid: minLength && validChars && hasAtLeastTwoLetters,
                requirements: {
                    minLength,
                    validChars,
                    hasAtLeastTwoLetters
                }
            };
        };

        const validateEmail = (email) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        };

        const validatePassword = (password) => {
            const minLength = password.length >= 8;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            return {
                isValid: minLength && hasUpperCase && hasLowerCase && hasNumber,
                requirements: {
                    minLength,
                    hasUpperCase,
                    hasLowerCase,
                    hasNumber
                }
            };
        };

        const validateConfirmPassword = (password, confirmPassword) => {
            return password === confirmPassword;
        };

        const validateMobileNumber = (number) => {
            const startsWithValidDigit = /^[6-9]/.test(number);
            const isExactlyTenDigits = number.length === 10;
            const hasNoInvalidDigits = !/^[0-5]/.test(number);
            const isNotRepeatedZeros = !/^[6-9]0{9}$/.test(number);
            
            return {
                isValid: startsWithValidDigit && isExactlyTenDigits && hasNoInvalidDigits && isNotRepeatedZeros,
                messages: [
                    {
                        valid: startsWithValidDigit,
                        text: 'Must start with 6, 7, 8, or 9'
                    },
                    {
                        valid: isExactlyTenDigits,
                        text: 'Must be exactly 10 digits'
                    },
                    {
                        valid: hasNoInvalidDigits,
                        text: 'Cannot start with 0-5'
                    },
                    {
                        valid: isNotRepeatedZeros,
                        text: 'Cannot be all zeros after first digit'
                    }
                ]
            };
        };

        const updateFeedback = (element, validation, type) => {
            const feedbackDiv = element.nextElementSibling;
            feedbackDiv.innerHTML = '';
            
            if (type === 'username') {
                feedbackDiv.innerHTML = `
                    <div style="color: ${validation.isValid ? 'green' : 'red'}">
                        ✓ At least 3 characters<br>
                        ✓ Only letters, numbers, and underscores<br>
                        ✓ At least 2 letters
                    </div>
                `;
            } else if (type === 'email') {
                feedbackDiv.innerHTML = `
                    <div style="color: ${validation ? 'green' : 'red'}">
                        ${validation ? '✓ Valid email format' : '✗ Invalid email format'}
                    </div>
                `;
            } else if (type === 'password') {
                feedbackDiv.innerHTML = `
                    <div style="color: ${validation.isValid ? 'green' : 'red'}">
                        ${validation.requirements.minLength ? '✓' : '✗'} At least 8 characters<br>
                        ${validation.requirements.hasUpperCase ? '✓' : '✗'} One uppercase letter<br>
                        ${validation.requirements.hasLowerCase ? '✓' : '✗'} One lowercase letter<br>
                        ${validation.requirements.hasNumber ? '✓' : '✗'} One number
                    </div>
                `;
            } else if (type === 'mobile') {
                let feedbackHtml = '<div style="color: green">Mobile number requirements:</div>';
                validation.messages.forEach(msg => {
                    feedbackHtml += `
                        <div style="color: ${msg.valid ? 'green' : 'red'}">
                            ${msg.valid ? '✓' : '✗'} ${msg.text}
                        </div>
                    `;
                });
                feedbackDiv.innerHTML = feedbackHtml;
            }
        };

        usernameInput.addEventListener('input', () => {
            const validation = validateUsername(usernameInput.value);
            updateFeedback(usernameInput, validation, 'username');
        });

        emailInput.addEventListener('input', () => {
            const isValid = validateEmail(emailInput.value);
            updateFeedback(emailInput, isValid, 'email');
        });

        passwordInput.addEventListener('input', () => {
            const validation = validatePassword(passwordInput.value);
            updateFeedback(passwordInput, validation, 'password');
        });

        numberInput.addEventListener('input', (e) => {
            const number = e.target.value;
            if (!/^\d*$/.test(number)) {
                e.target.value = number.replace(/\D/g, '');
                return;
            }
            const validation = validateMobileNumber(number);
            updateFeedback(numberInput, validation, 'mobile');
        });

        confirmPasswordInput.addEventListener('input', () => {
            const isMatch = validateConfirmPassword(passwordInput.value, confirmPasswordInput.value);
            confirmPasswordInput.nextElementSibling.innerHTML = `
                <div style="color: ${isMatch ? 'green' : 'red'}">
                    ${isMatch ? '✓ Passwords match' : '✗ Passwords do not match'}
                </div>
            `;
        });

        form.addEventListener('submit', (e) => {
            const usernameValid = validateUsername(usernameInput.value).isValid;
            const emailValid = validateEmail(emailInput.value);
            const numberValid = validateMobileNumber(numberInput.value).isValid;
            const passwordValid = validatePassword(passwordInput.value).isValid;
            const confirmPasswordValid = validateConfirmPassword(
                passwordInput.value,
                confirmPasswordInput.value
            );
            
            if (!usernameValid || !emailValid || !numberValid || !passwordValid || !confirmPasswordValid) {
                e.preventDefault();
                alert("Please fix all validation errors before submitting.");
            } else {
                const successMessage = document.getElementById('success-message');
                successMessage.style.display = 'block';
                
                setTimeout(() => {
                    successMessage.style.display = 'none';
                    form.reset();
                    document.querySelectorAll('.feedback').forEach(div => div.innerHTML = '');
                }, 3000);
            }
        });

        <?php if ($registrationSuccess): ?>
            document.getElementById('success-message').style.display = 'block';
            document.getElementById('signup-form').reset();
            setTimeout(() => {
                document.getElementById('success-message').style.display = 'none';
                window.location.href = 'login.php';
            }, 3000);
        <?php endif; ?>

        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }

        function toggleConfirmPasswordVisibility() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
            } else {
                confirmPasswordInput.type = 'password';
            }
        }
    </script>
</body>
</html>