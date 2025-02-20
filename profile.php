<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

require_once('dbconnect.php');
$database_name = "perfume_store";
mysqli_select_db($conn, $database_name);

// Add new columns to tbl_signup if they don't exist
$alterQueries = [
    "ALTER TABLE tbl_signup ADD COLUMN IF NOT EXISTS address VARCHAR(255)",
    "ALTER TABLE tbl_signup ADD COLUMN IF NOT EXISTS city VARCHAR(100)",
    "ALTER TABLE tbl_signup ADD COLUMN IF NOT EXISTS district VARCHAR(100)"
];

foreach ($alterQueries as $query) {
    if (!$conn->query($query)) {
        error_log("Error adding column: " . $conn->error);
    }
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle account deletion
if (isset($_POST['delete_account'])) {
    try {
        $conn->begin_transaction();
        
        // Delete from tbl_users first (if it exists)
        $deleteUser = $conn->prepare("DELETE FROM tbl_users WHERE Signup_id = ?");
        $deleteUser->bind_param("i", $user_id);
        $deleteUser->execute();
        
        // Then delete from tbl_signup
        $deleteSignup = $conn->prepare("DELETE FROM tbl_signup WHERE Signup_id = ?");
        $deleteSignup->bind_param("i", $user_id);
        
        if ($deleteSignup->execute()) {
            $conn->commit();
            session_destroy();
            header("Location: login.php?message=account_deleted");
            exit();
        } else {
            throw new Exception("Error deleting account");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error deleting account: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch user data function
function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT s.*, u.username as user_username 
            FROM tbl_signup s 
            LEFT JOIN tbl_users u ON s.Signup_id = u.Signup_id 
            WHERE s.Signup_id = ?");
    
    if (!$stmt) {
        error_log("Statement preparation failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        error_log("Statement execution failed: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Validate input function
function validateInput($data) {
    $errors = [];
    
    if (empty($data['username']) || strlen($data['username']) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (!preg_match("/^\d{10}$/", $data['phone'])) {
        $errors[] = "Phone number must be exactly 10 digits";
    }
    
    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'address' => trim($_POST['address']),
        'city' => trim($_POST['city']),
        'district' => trim($_POST['district'])
    ];
    
    // Handle password update if provided
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate input
    $validationErrors = validateInput($postData);
    
    if (empty($validationErrors)) {
        try {
            $conn->begin_transaction();
            
            // Check for duplicate username
            $checkUsername = $conn->prepare("SELECT Signup_id FROM tbl_signup WHERE username = ? AND Signup_id != ?");
            $checkUsername->bind_param("si", $postData['username'], $user_id);
            $checkUsername->execute();
            if ($checkUsername->get_result()->num_rows > 0) {
                throw new Exception("Username already exists");
            }
            
            // Update signup table
            $stmt = $conn->prepare("UPDATE tbl_signup SET 
                username = ?, 
                email = ?, 
                Phoneno = ?,
                address = ?,
                city = ?,
                district = ?
                WHERE Signup_id = ?");
            
            $stmt->bind_param("ssssssi", 
                $postData['username'],
                $postData['email'],
                $postData['phone'],
                $postData['address'],
                $postData['city'],
                $postData['district'],
                $user_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating profile");
            }
            
            // Update password if provided
            if (!empty($new_password) && $new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $updatePassword = $conn->prepare("UPDATE tbl_signup SET password = ? WHERE Signup_id = ?");
                $updatePassword->bind_param("si", $hashed_password, $user_id);
                
                if (!$updatePassword->execute()) {
                    throw new Exception("Error updating password");
                }
            }
            
            $conn->commit();
            $_SESSION['username'] = $postData['username'];
            $message = "Profile updated successfully!";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error updating profile: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "Please correct the following errors: " . implode(", ", $validationErrors);
        $messageType = 'error';
    }
}

// Fetch user data
$userData = getUserData($conn, $user_id);
if (!$userData) {
    $message = "Error loading user data. Please try again later.";
    $messageType = 'error';
}

// Array of districts
$districts = [
    'Thiruvananthapuram',
    'Kollam',
    'Pathanamthitta',
    'Alappuzha',
    'Kottayam',
    'Idukki',
    'Ernakulam',
    'Thrissur',
    'Palakkad',
    'Malappuram',
    'Kozhikode',
    'Wayanad',
    'Kannur',
    'Kasaragod'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #282842;
            padding: 20px;
            position: fixed;
            height: 100vh;
        }

        .logo {
            color: white;
            font-size: 24px;
            margin-bottom: 30px;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin-bottom: 15px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            display: block;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .profile-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            margin: 0 auto;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #8B2323;
        }

        .button-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .btn-submit {
            background-color: #006400;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn-submit:hover {
            background-color: #005000;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-buttons button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .modal-confirm {
            background-color: #dc3545;
            color: white;
        }

        .modal-cancel {
            background-color: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                padding: 10px;
            }

            .logo {
                font-size: 0;
            }

            .nav-links a span {
                display: none;
            }

            .main-content {
                margin-left: 60px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">Perfume Paradise</div>
            <ul class="nav-links">
                <li><a href="#" class="active"><span>Edit Profile</span></a></li>
                <li><a href="#"><span>Settings</span></a></li>
                <li><a href="index.php"><span>Home</span></a></li>
                <li><a href="logout.php"><span>Logout</span></a></li>
            </ul>
        </div>

        <div class="main-content"><br><br>
            <h1 style="color:#282842;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Edit Profile</h1>
            <form class="profile-form" method="POST" id="profileForm">
                <?php if ($message): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" 
                               value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" 
                               required minlength="3">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" 
                               value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="tel" name="phone" 
                               value="<?php echo htmlspecialchars($userData['Phoneno'] ?? ''); ?>" 
                               required pattern="^\d{10}$">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" 
                               value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" 
                               value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>District</label>
                        <select name="district">
                            <option value="">Select District</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo htmlspecialchars($district); ?>"
                                    <?php echo (($userData['district'] ?? '') === $district) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($district); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password">
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password">
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" class="btn-submit">Update Profile</button>
                    <button type="button" class="btn-delete" onclick="showDeleteConfirmation()">Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Account Deletion</h2>
            <p>Are you sure you want to delete your account? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button class="modal-cancel" onclick="hideDeleteConfirmation()">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="delete_account" class="modal-confirm">Delete Account</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        const form = document.getElementById('profileForm');
        const passwordInput = document.querySelector('input[name="new_password"]');
        const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');

        form.addEventListener('submit', function(e) {
            // Reset previous error styles
            const inputs = form.querySelectorAll('input');
            inputs.forEach(input => input.style.borderColor = '#ddd');

            // Validate required fields
            let hasError = false;
            
            if (passwordInput.value || confirmPasswordInput.value) {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    passwordInput.style.borderColor = '#dc3545';
                    confirmPasswordInput.style.borderColor = '#dc3545';
                    alert('Passwords do not match!');
                    hasError = true;
                }
            }

            const phoneInput = document.querySelector('input[name="phone"]');
            if (!/^\d{10}$/.test(phoneInput.value)) {
                phoneInput.style.borderColor = '#dc3545';
                alert('Phone number must be exactly 10 digits!');
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });

        // Real-time password matching validation
        function validatePasswords() {
            if (passwordInput.value || confirmPasswordInput.value) {
                if (passwordInput.value === confirmPasswordInput.value) {
                    passwordInput.style.borderColor = '#28a745';
                    confirmPasswordInput.style.borderColor = '#28a745';
                } else {
                    passwordInput.style.borderColor = '#dc3545';
                    confirmPasswordInput.style.borderColor = '#dc3545';
                }
            } else {
                passwordInput.style.borderColor = '#ddd';
                confirmPasswordInput.style.borderColor = '#ddd';
            }
        }

        passwordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);

        // Phone number formatting
        const phoneInput = document.querySelector('input[name="phone"]');
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });

        // Delete account modal functions
        function showDeleteConfirmation() {
            document.getElementById('deleteModal').style.display = 'block';
        }

        function hideDeleteConfirmation() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>