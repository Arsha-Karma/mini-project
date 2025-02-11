<?php
// profile.php
session_start();
require_once('dbconnect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database_name = "perfume_store";
mysqli_select_db($conn, $database_name);

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle account deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_account') {
    $delete_result = deleteUserAccount($conn, $user_id);
    
    if ($delete_result['success']) {
        // Destroy session and redirect to homepage
        session_destroy();
        header("Location: index.php?message=" . urlencode("Account successfully deleted"));
        exit();
    } else {
        // Set error message
        $message = $delete_result['message'];
        $messageType = 'error';
    }
}

// Fetch user data function
function getUserData($conn, $user_id) {
    try {
        $sql = "SELECT s.*, u.username as user_username 
                FROM tbl_signup s 
                LEFT JOIN tbl_users u ON s.Signup_id = u.Signup_id 
                WHERE s.Signup_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Statement preparation failed: " . $conn->error);
            throw new Exception("Database query preparation failed");
        }
        
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            error_log("Statement execution failed: " . $stmt->error);
            throw new Exception("Database query execution failed");
        }
        
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        
        if (!$userData) {
            error_log("No user data found for user_id: " . $user_id);
            throw new Exception("User data not found");
        }
        
        return $userData;
    } catch (Exception $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        return false;
    }
}

// Validate input function
function validateInput($data) {
    $errors = [];
    
    // Username validation
    if (empty($data['username']) || strlen($data['username']) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    }
    
    // Email validation
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Phone validation 
    if (!preg_match("/^\d{10}$/", $data['Phoneno'])) {
        $errors[] = "Phone number must be exactly 10 digits";
    }
    
    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Profile update attempt - POST data: " . print_r($_POST, true));
    
    $postData = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'Phoneno' => trim($_POST['phone'])
    ];
    
    // Validate input
    $validationErrors = validateInput($postData);
    
    if (empty($validationErrors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Update signup table (removed explicit updated_at)
            $sql = "UPDATE tbl_signup SET 
                    username = ?, 
                    email = ?, 
                    Phoneno = ?
                    WHERE Signup_id = ?";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bind_param("sssi", 
                $postData['username'], 
                $postData['email'], 
                $postData['Phoneno'], 
                $user_id
            );
            
            if (!$stmt->execute()) {
                error_log("Failed to execute signup update: " . $stmt->error);
                throw new Exception("Error updating signup table");
            }
            
            // Update users table
            $update_user = "UPDATE tbl_users SET 
                           username = ?
                           WHERE Signup_id = ?";
            $user_stmt = $conn->prepare($update_user);
            
            $user_stmt->bind_param("si", $postData['username'], $user_id);
            
            if (!$user_stmt->execute()) {
                error_log("Failed to execute users update: " . $user_stmt->error);
                throw new Exception("Error updating users table");
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['username'] = $postData['username'];
            $message = "Profile updated successfully!";
            $messageType = 'success';
            
            error_log("Profile update successful for user_id: " . $user_id);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "Error updating profile: " . $e->getMessage();
            $messageType = 'error';
            error_log("Profile update failed for user_id: " . $user_id . ". Error: " . $e->getMessage());
        }
    } else {
        $message = "Please correct the following errors: " . implode(", ", $validationErrors);
        $messageType = 'error';
        error_log("Validation errors during profile update: " . implode(", ", $validationErrors));
    }
}

// Fetch user data
$userData = getUserData($conn, $user_id);
if (!$userData) {
    $message = "Error loading user data. Please try again later.";
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Rest of your existing CSS remains the same -->
    
        /* Existing CSS from previous submission */
        <style>
    :root {
    --primary-color: #121212;
    --accent-color: #ff6b4a;
    --text-color: #f0f0f0;
    --border-radius: 15px;
    --error-color: #ff6b6b;
    --success-color: #48dbfb;
    --input-bg: #1e1e1e;
    --border-color: #2c2c2c;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background-image: url('image/image1.jpg');
    background-color: #121212;
    background-blend-mode: overlay;
    min-height: 100vh;
    background-attachment: fixed;
    background-size: cover;
}

.container {
    max-width: 700px;
    margin: 30px auto;
    background-color: var(--primary-color);
    border-radius: var(--border-radius);
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    color: var(--text-color);
}

.header {
    display: flex;
    justify-content: space-between;
    padding: 20px 40px;
    align-items: center;
    background-color: rgba(18, 18, 18, 0.9);
    border-bottom: 1px solid var(--border-color);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.logo {
    font-size: 24px;
    font-weight: bold;
    color: var(--text-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-links {
    display: flex;
    gap: 30px;
}

.nav-links a {
    color: var(--text-color);
    text-decoration: none;
    transition: color 0.3s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.nav-links a:hover {
    color: var(--accent-color);
}

.profile-section {
    padding: 10px;
}

.profile-header {
    margin-bottom: 30px;
    text-align: center;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: var(--input-bg);
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: var(--accent-color);
}

.message {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.success {
    background-color: rgba(72, 219, 251, 0.2);
    color: var(--success-color);
}

.error {
    background-color: rgba(255, 107, 107, 0.2);
    color: var(--error-color);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 20px;
}

.form-group {
    position: relative;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #a0a0b0;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    background: var(--input-bg);
    color: var(--text-color);
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(255, 107, 74, 0.2);
}

.form-group .error-message {
    color: var(--error-color);
    font-size: 12px;
    margin-top: 5px;
    display: none;
}

.form-group input:invalid + .error-message {
    display: block;
}

.buttons {
    display: flex;
    gap: 20px;
    margin-top: 40px;
    justify-content: center;
}

.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background-color: var(--accent-color);
    color: white;
}

.btn-primary:hover {
    background-color: #ff5733;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.bottom-nav {
    display: flex;
    border-top: 1px solid var(--border-color);
}

.bottom-nav a {
    flex: 1;
    padding: 15px;
    text-align: center;
    text-decoration: none;
    color: var(--text-color);
    background: rgba(30, 30, 30, 0.8);
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.bottom-nav a:hover {
    background: rgba(40, 40, 40, 0.9);
    color: var(--accent-color);
}

.delete-account-section {
    margin-top: 10px;
    padding-top: 5px;
    text-align: center;
}

.divider {
    border: none;
    border-top: 1px solid var(--border-color);
    margin: 20px 0;
}

.warning-text {
    color: #ff6b6b;
    font-size: 14px;
    margin: 10px 0 20px 0;
}

.btn-danger {
    background-color: #ff6b6b;
    color: white;
}

.btn-danger:hover {
    background-color: #ff5252;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .container {
        margin: 15px;
        max-width: 95%;
    }
    
    .header {
        flex-direction: column;
        gap: 15px;
        padding: 15px;
    }
    
    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
    }

    .profile-section {
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .btn {
        width: 100%;
        justify-content: center;
    }
}

.loading {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(18, 18, 18, 0.8);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.loading.active {
    display: flex;
}
    </style>


</head>
<body>
    <div class="loading">
        <i class="fas fa-spinner fa-spin fa-3x"></i>
    </div>

    <div class="container">
        <header class="header">
            <a href="index.php" class="logo">
                Perfume Paradise
            </a>
            <nav class="nav-links">
                <a href="#"><i class="fas fa-shop"></i> Shop</a>
                <a href="#"><i class="fas fa-address-book"></i> Contacts</a>
                <a href="#"><i class="fas fa-shopping-cart"></i> Cart</a>
            </nav>
        </header>

        <section class="profile-section">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h1>My Profile</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="profileForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" 
                               value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" 
                               required minlength="3" 
                               pattern="[A-Za-z0-9_]+"
                               title="Username can only contain letters, numbers, and underscores">
                        <div class="error-message">Username must be at least 3 characters long</div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" 
                               value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" 
                               required>
                        <div class="error-message">Please enter a valid email address</div>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" 
                               value="<?php echo htmlspecialchars($userData['Phoneno'] ?? ''); ?>" 
                               required pattern="^\d{10}$"
                               title="Phone number must be exactly 10 digits">
                        <div class="error-message">Phone number must be exactly 10 digits</div>
                    </div>
                    <div class="form-group">
                        <label>Registration Type</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($userData['role_type'] ?? ''); ?>" 
                               disabled>
                    </div>
                </div>
                
                <div class="buttons">
                    <button type="submit" class="btn btn-primary" id="saveButton">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
               <div class="delete-account-section">
                <hr class="divider">
                <h3>Delete Account</h3>
                <p class="warning-text">Warning: This action cannot be undone. All your data will be permanently deleted.</p>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i>
                    Delete Account
                </button>
            </div>
        </section>

        <div class="bottom-nav">
            <a href="#"><i class="fas fa-heart"></i> Wishlist</a>
            <a href="#"><i class="fas fa-cog"></i> Settings</a>
        </div>
    </div>

    <script>
        // Form validation and submission handling
        const form = document.getElementById('profileForm');
        const saveButton = document.getElementById('saveButton');
        const loadingIndicator = document.querySelector('.loading');

        // Input validation
        function validateForm() {
            const username = form.username.value;
            const email = form.email.value;
            const phone = form.phone.value;
            
            let isValid = true;
            
            // Username validation
            if (username.length < 3 || !/^[A-Za-z0-9_]+$/.test(username)) {
                isValid = false;
                form.username.classList.add('invalid');
            } else {
                form.username.classList.remove('invalid');
            }
            
            // Email validation
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                isValid = false;
                form.email.classList.add('invalid');
            } else {
                form.email.classList.remove('invalid');
            }
            
            // Phone validation
            if (!/^\d{10}$/.test(phone)) {
                isValid = false;
                form.phone.classList.add('invalid');
            } else {
                form.phone.classList.remove('invalid');
            }
            
            return isValid;
        }

        // Phone number formatting
        form.phone.addEventListener('input', function(e) {
            let number = e.target.value.replace(/\D/g, '');
            if (number.length > 10) {
                number = number.substr(0, 10);
            }
            e.target.value = number;
        });

        // Real-time validation
        form.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                validateForm();
                const isValid = form.checkValidity();
                saveButton.disabled = !isValid;
            });
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }
            
            loadingIndicator.classList.add('active');
            saveButton.disabled = true;
            
            // Enable button after submission (in case of error)
            setTimeout(() => {
                loadingIndicator.classList.remove('active');
                saveButton.disabled = false;
            }, 2000);
        });

        // Hide message after 5 seconds
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        }

        // Confirm before leaving with unsaved changes
        let formChanged = false;
        const originalData = new FormData(form);

        form.addEventListener('input', () => {
            const currentData = new FormData(form);
            formChanged = false;
            
            for (let pair of originalData.entries()) {
                if (currentData.get(pair[0]) !== pair[1]) {
                    formChanged = true;
                    break;
                }
            }
        });

        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Account deletion confirmation
        function confirmDelete() {
            Swal.fire({
                title: 'Are you sure?',
                text: "Your account will be permanently deleted. This cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete my account'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `profile.php?action=delete_account&id=<?php echo $user_id; ?>`;
                }
            });
        }

        
    </script>
</body>
</html>