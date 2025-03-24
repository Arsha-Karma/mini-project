<?php
// Add this temporarily for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Redirect if not logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

require_once('dbconnect.php');
$database_name = "perfumes";
mysqli_select_db($conn, $database_name);

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle account disabling
if (isset($_POST['delete_account'])) {
    $result = disableUserAccount($conn, $user_id);
    if ($result['success']) {
        session_destroy();
        header("Location: login.php?message=account_disabled");
        exit();
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
}

// Alter tbl_signup to add business_name column if it doesn't exist
$check_business_name = "SHOW COLUMNS FROM tbl_signup LIKE 'business_name'";
$result = $conn->query($check_business_name);
if ($result->num_rows == 0) {
    $alter_query = "ALTER TABLE tbl_signup ADD COLUMN business_name VARCHAR(100)";
    if (!$conn->query($alter_query)) {
        $message = "Error adding business_name column: " . $conn->error;
        $messageType = 'error';
    }
}

// Fetch user data function
function getUserData($conn, $user_id) {
    // Modified query to get all necessary information
    $stmt = $conn->prepare("
        SELECT 
            s.*,                    /* All fields from signup */
            s.username,             /* Username from signup */
            s.email,               /* Email from signup */
            s.Phoneno,             /* Phone from signup */
            s.address,             /* Address from signup */
            s.city,                /* City from signup */
            s.district,            /* District from signup */
            s.role_type,           /* Role type from signup */
            sel.Sellername,        /* Seller name if seller */
            sel.seller_id,         /* Seller ID if seller */
            sel.id_type,           /* ID type if seller */
            sel.id_number,         /* ID number if seller */
            sel.verified_status,    /* Verification status if seller */
            doc.id_proof_front,    /* Document proofs if seller */
            doc.id_proof_back,
            doc.business_proof,
            doc.address_proof
        FROM tbl_signup s 
        LEFT JOIN tbl_seller sel ON s.Signup_id = sel.Signup_id 
        LEFT JOIN seller_verification_docs doc ON sel.seller_id = doc.seller_id
        WHERE s.Signup_id = ?
    ");
    
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
    $userData = $result->fetch_assoc();
    
    // For debugging
    if ($userData) {
        error_log("User data found: " . print_r($userData, true));
    } else {
        error_log("No user data found for ID: " . $user_id);
    }
    
    return $userData;
}

// Fetch user role
function getUserRole($conn, $user_id) {
    $stmt = $conn->prepare("SELECT role_type FROM tbl_signup WHERE Signup_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['role_type'];
    }
    return null;
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
    
    try {
        $conn->begin_transaction();
        
        // Update signup table
        $stmt = $conn->prepare("UPDATE tbl_signup SET 
            Phoneno = ?,
            address = ?,
            city = ?,
            district = ?
            WHERE Signup_id = ?");
        
        $stmt->bind_param("ssssi", 
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
        
        // If user is a seller, update business name
        if ($_SESSION['role'] === 'seller') {
            $business_name = trim($_POST['business_name']);
            $updateSeller = $conn->prepare("UPDATE tbl_signup SET business_name = ? WHERE Signup_id = ?");
            $updateSeller->bind_param("si", $business_name, $user_id);
            
            if (!$updateSeller->execute()) {
                throw new Exception("Error updating business name");
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
}

// Fetch user data
$userData = getUserData($conn, $user_id);
if (!$userData) {
    $message = "Error loading user data. Please try again later.";
    $messageType = 'error';
}

// After getting user data
if ($userData) {
    echo "<!-- Debug: ";
    print_r($userData);
    echo " -->";
}

// Fetch user role
$userRole = getUserRole($conn, $user_id);
$_SESSION['role'] = $userRole;

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
            background-color: #000000;
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
            background-color: #1a1a1a;
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
            color: #000000 !important;
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
            border-color: #000000;
        }

        .button-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .btn-submit {
            background-color: #000000;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn-submit:hover {
            background-color: #1a1a1a;
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

        .field-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }

        input.is-valid, select.is-valid {
            border-color: #000000;
        }

        .verification-section {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .documents-list {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .documents-list h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1rem;
        }

        .document-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .document-links li {
            margin-bottom: 12px;
            padding: 8px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .document-links li:hover {
            background-color: #f1f3f5;
        }

        .document-links i {
            margin-right: 10px;
            color: #6c757d;
        }

        .doc-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .doc-link:hover {
            text-decoration: underline;
            color: #0056b3;
        }

        /* Existing form-grid styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group input[readonly] {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">Perfume Paradise</div>
            <ul class="nav-links">
                <li>
                    <?php if (isset($_SESSION['role'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="admindashboard.php">
                                <i class="fas fa-home"></i>
                                <span class="link-name">Home</span>
                            </a>
                        <?php elseif ($_SESSION['role'] === 'seller'): ?>
                            <a href="seller-dashboard.php">
                                <i class="fas fa-home"></i>
                                <span class="link-name">Home</span>
                            </a>
                        <?php else: ?>
                            <a href="index.php">
                                <i class="fas fa-home"></i>
                                <span class="link-name">Home</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span class="link-name">Profile</span>
                    </a>
                </li>
                <li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'seller'): ?>
                        <a href="sales.php">
                            <i class="fas fa-shopping-bag"></i>
                            <span class="link-name">Orders</span>
                        </a>
                    <?php else: ?>
                        <a href="orders.php">
                            <i class="fas fa-shopping-bag"></i>
                            <span class="link-name">Orders</span>
                        </a>
                    <?php endif; ?>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="link-name">Logout</span>
                    </a>
                </li>
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
                               readonly>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" 
                               value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" 
                               readonly>
                    </div>

                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="tel" name="phone" 
                               value="<?php echo htmlspecialchars($userData['Phoneno'] ?? ''); ?>" 
                               required>
                        <div id="phone-error" class="field-error"></div>
                    </div>

                    <?php if ($userRole === 'seller'): ?>
                        <div class="form-group">
                            <label>Business Name</label>
                            <input type="text" name="business_name" 
                                   value="<?php echo htmlspecialchars($userData['Sellername'] ?? ''); ?>" 
                                   required>
                            <div id="business-name-error" class="field-error"></div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" 
                               value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>" 
                               required>
                        <div id="address-error" class="field-error"></div>
                    </div>

                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" 
                               value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>" 
                               required>
                        <div id="city-error" class="field-error"></div>
                    </div>

                    <div class="form-group">
                        <label>District</label>
                        <select name="district" required>
                            <option value="">Select District</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo htmlspecialchars($district); ?>"
                                    <?php echo (($userData['district'] ?? '') === $district) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($district); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="district-error" class="field-error"></div>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password">
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password">
                    </div>

                    <?php if ($userRole === 'seller'): ?>
                        <div class="verification-section" style="grid-column: 1 / -1;">
                            <h3>Verification Documents</h3><br>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>ID Type</label>
                                    <input type="text" value="<?php echo htmlspecialchars($userData['id_type'] ?? ''); ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label>ID Number</label>
                                    <input type="text" value="<?php echo htmlspecialchars($userData['id_number'] ?? ''); ?>" readonly>
                                </div>

                                <div class="form-group">
                                    <label>Verification Status</label>
                                    <input type="text" value="<?php echo htmlspecialchars($userData['verified_status'] ?? ''); ?>" readonly>
                                </div>
                            </div>

                            <div class="uploaded-documents">
                                <h4>Uploaded Documents</h4>
                                <div style="background: #f5f5f5; padding: 15px; border-radius: 4px;">
                                    <?php if (!empty($userData['id_proof_front'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <i class="fas fa-file-image" style="margin-right: 10px; color: #666;"></i>
                                            <a href="<?php echo htmlspecialchars($userData['id_proof_front']); ?>" 
                                               target="_blank" 
                                               style="color: #000; text-decoration: none;">
                                                ID Proof (Front) - <?php echo basename($userData['id_proof_front']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($userData['id_proof_back'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <i class="fas fa-file-image" style="margin-right: 10px; color: #666;"></i>
                                            <a href="<?php echo htmlspecialchars($userData['id_proof_back']); ?>" 
                                               target="_blank" 
                                               style="color: #000; text-decoration: none;">
                                                ID Proof (Back) - <?php echo basename($userData['id_proof_back']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($userData['business_proof'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <i class="fas fa-file-image" style="margin-right: 10px; color: #666;"></i>
                                            <a href="<?php echo htmlspecialchars($userData['business_proof']); ?>" 
                                               target="_blank" 
                                               style="color: #000; text-decoration: none;">
                                                Business Proof - <?php echo basename($userData['business_proof']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($userData['address_proof'])): ?>
                                        <div style="margin-bottom: 10px;">
                                            <i class="fas fa-file-image" style="margin-right: 10px; color: #666;"></i>
                                            <a href="<?php echo htmlspecialchars($userData['address_proof']); ?>" 
                                               target="_blank" 
                                               style="color: #000; text-decoration: none;">
                                                Address Proof - <?php echo basename($userData['address_proof']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
            <h2 style="text-align: center;">Confirmation</h2><br><br>
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
    // Function to show "Field is required" message on focus
    function showRequiredMessage(inputField, errorElement) {
        errorElement.textContent = 'Field is required';
    }

    // Add onfocus event listeners for required fields
    const addressInput = document.querySelector('input[name="address"]');
    const cityInput = document.querySelector('input[name="city"]');
    const districtInput = document.querySelector('select[name="district"]');

    const addressError = document.getElementById('address-error');
    const cityError = document.getElementById('city-error');
    const districtError = document.getElementById('district-error');

    if (addressInput) {
        addressInput.addEventListener('focus', function() {
            showRequiredMessage(addressInput, addressError);
        });
    }

    if (cityInput) {
        cityInput.addEventListener('focus', function() {
            showRequiredMessage(cityInput, cityError);
        });
    }

    if (districtInput) {
        districtInput.addEventListener('focus', function() {
            showRequiredMessage(districtInput, districtError);
        });
    }

    // Phone number validation
    const phoneInput = document.querySelector('input[name="phone"]');
    const phoneError = document.getElementById('phone-error');

    if (phoneInput) {
        phoneInput.addEventListener('focus', function() {
            phoneError.textContent = 'Phone number must be 10 digits, not start with 0-5, and not be all zeros or start with any digit followed by zeros.';
        });

        phoneInput.addEventListener('input', function() {
            const phoneValue = this.value;

            // Validate phone number
            const isValid = /^[6-9]\d{9}$/.test(phoneValue) && 
                           !/^(\d)\0{9}$/.test(phoneValue) && 
                           phoneValue !== '0000000000';

            if (!isValid) {
                this.style.borderColor = '#dc3545';
                phoneError.textContent = 'Invalid phone number.';
            } else {
                this.style.borderColor = '#28a745';
                phoneError.textContent = '';
            }
        });
    }

    // Business Name Validation
    const businessNameInput = document.querySelector('input[name="business_name"]');
    const businessNameError = document.getElementById('business-name-error');

    if (businessNameInput) {
        businessNameInput.addEventListener('focus', function() {
            businessNameError.textContent = 'Only letters and whitespaces are allowed.';
        });

        businessNameInput.addEventListener('input', function() {
            const businessNameValue = this.value;

            // Validate business name
            const isValid = /^[A-Za-z\s]+$/.test(businessNameValue);

            if (!isValid) {
                this.style.borderColor = '#dc3545';
                businessNameError.textContent = 'Invalid business name. Only letters and whitespaces are allowed.';
            } else {
                this.style.borderColor = '#28a745';
                businessNameError.textContent = '';
            }
        });
    }

    // Prevent form submission if validation fails
    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;

            // Phone number validation
            if (phoneInput) {
                const phoneValue = phoneInput.value;
                const isPhoneValid = /^[6-9]\d{9}$/.test(phoneValue) && 
                                   !/^(\d)\0{9}$/.test(phoneValue) && 
                                   phoneValue !== '0000000000';

                if (!isPhoneValid) {
                    isValid = false;
                    phoneError.textContent = 'Invalid phone number.';
                    phoneInput.style.borderColor = '#dc3545';
                }
            }

            // Business name validation
            if (businessNameInput && businessNameInput.value) {
                const businessNameValue = businessNameInput.value;
                const isBusinessNameValid = /^[A-Za-z\s]+$/.test(businessNameValue);

                if (!isBusinessNameValid) {
                    isValid = false;
                    businessNameError.textContent = 'Invalid business name. Only letters and whitespaces are allowed.';
                    businessNameInput.style.borderColor = '#dc3545';
                }
            }

            // Prevent form submission if validation fails
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the errors before submitting the form.');
            }
        });
    }

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