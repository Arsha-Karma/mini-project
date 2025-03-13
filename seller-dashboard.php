<?php
// Start the session
session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Include database connection
require_once 'dbconnect.php';

// Add necessary columns if they don't exist
$alter_queries = [
    "ALTER TABLE tbl_orders ADD COLUMN IF NOT EXISTS quantity INT NOT NULL DEFAULT 1",
    "ALTER TABLE tbl_orders ADD COLUMN IF NOT EXISTS product_id INT(11) NOT NULL",
    "ALTER TABLE tbl_orders ADD COLUMN IF NOT EXISTS user_id INT(11) NOT NULL",
    "ALTER TABLE tbl_orders ADD FOREIGN KEY IF NOT EXISTS (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE",
    "ALTER TABLE tbl_orders ADD FOREIGN KEY IF NOT EXISTS (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE"
];

foreach ($alter_queries as $query) {
    try {
        $conn->query($query);
    } catch (Exception $e) {
        // Continue if error occurs (e.g., if column or key already exists)
        continue;
    }
}

// Check if seller is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

// Check for session timeout (e.g., 30 minutes)
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Log out the user
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Get seller information
$seller_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM tbl_seller WHERE Signup_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_info = $stmt->get_result()->fetch_assoc();

// Get seller's products
$stmt = $conn->prepare("SELECT * FROM tbl_product WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sales data with correct JOIN
$stmt = $conn->prepare("
    SELECT o.order_id, o.quantity, o.total_amount, o.status, o.ordered_time, 
           p.name as product_name, u.username as customer_name
    FROM tbl_orders o 
    JOIN tbl_product p ON o.product_id = p.product_id 
    JOIN tbl_users u ON o.user_id = u.user_id
    WHERE p.seller_id = ?
    ORDER BY o.ordered_time DESC
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent reviews
$stmt = $conn->prepare("
    SELECT r.*, p.name as product_name, u.username 
    FROM tbl_reviews r 
    JOIN tbl_product p ON r.product_id = p.product_id 
    JOIN tbl_users u ON r.user_id = u.user_id 
    WHERE p.seller_id = ?
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
<<<<<<< HEAD

// Check verification status
$stmt = $conn->prepare("
    SELECT verified_status, documents_uploaded 
    FROM tbl_seller 
    WHERE Signup_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// Check for verification success message
$show_success = isset($_SESSION['verification_success']) && $_SESSION['verification_success'];
if ($show_success) {
    unset($_SESSION['verification_success']); // Clear the flag after use
}

// Show verification form for unverified sellers
$show_verification_popup = ($result['documents_uploaded'] !== 'completed');
=======
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Perfume Paradise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7fc;
            color: #333;
        }

        .sidebar {
            width: 250px;
<<<<<<< HEAD
            background-color: #1a1a1a !important;
            height: 100vh;
            position: fixed;
            color: white;
=======
            background-color: #2d2a4b;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
        }

        .sidebar h2 {
            text-align: center;
            color: #fff;
            padding: 20px;
            background-color: #2d2a4b;
            margin: 0;
        }

        .sidebar a {
<<<<<<< HEAD
            color: #ffffff;
            padding: 15px 20px;
            text-decoration: none;
            border-bottom: 1px solid #1a1a1a;
            display: block;
        }

        .sidebar a:hover, .sidebar .active {
            background-color: #1a1a1a;
            color: #ffffff;
=======
            display: flex;
            align-items: center;
            color: #fff;
            padding: 15px 20px;
            text-decoration: none;
            border-bottom: 1px solid #3a375f;
            transition: all 0.3s ease;
        }

        .sidebar a svg {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        .sidebar a:hover, .sidebar .active {
            background-color: #3a375f;
            color: #fff;
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
            background-color: #f4f7fc;
        }

        .header {
<<<<<<< HEAD
            background-color: #f4f7fc;
=======
            background-color: #fff;
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #2d2a4b;
            margin: 0;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
            color: #666;
        }

        .stat-box .number {
            font-size: 2em;
            font-weight: bold;
            color: #2d2a4b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #2d2a4b;
            color: #fff;
            font-weight: bold;
        }

        tr:hover {
            background-color: #f8f9ff;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }
        

        .btn-activate {
            background-color: #4CAF50;
            color: white;
        }

        .btn-deactivate {
            background-color: #ff9800;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .status-active {
            color: #4CAF50;
        }

        .status-inactive {
            color: #ff9800;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #4CAF50;
            color: white;
        }

        .alert-error {
            background-color: #f44336;
            color: white;
        }

        .logout-btn {
            background-color: #f44336;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            transition: opacity 0.3s ease;
        }

        .logout-btn:hover {
            opacity: 0.9;
        }
        thead {
    background-color: #2d2a4b; /* Dark blue background */
}

th {
    color: white; /* White text color */
    font-weight: bold;
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
<<<<<<< HEAD

.nav-link {
    color: white !important;
}

.nav-link:hover,
.nav-link.active {
    background-color: #000000;
    color: #ffffff;
}

.card {
    border: 1px solid #e0e0e0;
}

.card-header {
    background-color: #1a1a1a !important;
    color: white;
}

.btn-primary {
    background-color: #000000 !important;
    border-color: #000000 !important;
}

.btn-primary:hover {
    background-color: #1a1a1a !important;
}

/* Header Title */
.header-title {
    background-color: #000000 !important;
    color: white;
    padding: 15px;
}

/* Recent Sales Section */
.recent-sales .card-header,
.reviews-section .card-header {
    background-color: #000000 !important;
    color: white !important;
}

/* Table Headers */
.table thead th {
    background-color: #000000 !important;
    color: white !important;
}

/* Stats Cards Headers */
.stats-card .card-header {
    background-color: #000000 !important;
    color: white;
}

/* Section Headers */
.section-header {
    background-color: #000000 !important;
    color: white;
    padding: 15px;
}

/* Perfume Paradise Title in Sidebar */
.sidebar-header,
.sidebar-brand,
.brand-title {
    background-color: #000000 !important;
    color: white !important;
    padding: 20px;
    margin: 0;
}

/* Ensure the text "Perfume Paradise" is visible */
.sidebar-header h2,
.sidebar-brand h2,
.brand-title h2 {
    color: white !important;
}

/* Your existing verification popup styles */
.verification-popup {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.popup-content {
    background: #ffffff;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.popup-content h2 {
    color: #000000;
    margin-bottom: 20px;
    text-align: center;
    font-size: 24px;
    border-bottom: 2px solid #000000;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #000000;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #000000;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 2px dashed #000000;
    border-radius: 4px;
    background: #f8f9fa;
    cursor: pointer;
}

.terms-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.button-group {
    text-align: center;
    margin-top: 30px;
}

.btn-primary {
    background: #000000;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background 0.3s;
}

.btn-primary:hover {
    background: #1a1a1a;
}

.success-message {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;  /* Green background */
    color: white;
    padding: 15px 25px;
    border-radius: 4px;
    display: none;
    animation: slideIn 0.5s ease-out;
    z-index: 1002;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.id-format-hint {
    color: #666666;
    font-size: 12px;
    margin-top: 4px;
}

/* Loading indicator */
.loading {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1001;
    justify-content: center;
    align-items: center;
}

.loading::after {
    content: '';
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #000000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.dashboard-success-message {
    background-color: #28a745;
    color: white;
    padding: 15px 25px;
    margin: 20px;
    border-radius: 4px;
    display: none;
    animation: fadeIn 0.5s ease-out;
    text-align: center;
    font-weight: bold;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.error-message {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
    display: none;
}

.form-group.error input,
.form-group.error select {
    border-color: #dc3545;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
=======
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
    </style>
</head>
<body>
    <div class="sidebar"><br>
<<<<<<< HEAD
   <h4 style="color: white; text-align: center;">Perfume Paradise</h4>
=======
    <h3 style="color: white;">Perfume Paradise</h3>
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
        <a href="seller-dashboard.php">Dashboard</a>
        <a href="index.php">Home</a>
        <a href="profile.php">Edit Profile</a>
        <a href="products.php">Products</a>
        <a href="sales.php">Sales</a>
        <a href="reviews.php">Customer Reviews</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="main-content">
        <div class="header">
<<<<<<< HEAD
            <h1 style="color: #000000;" >Welcome Seller</h1>
=======
            <h1>Welcome Seller</h1>
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
        </div>

        <div class="container mt-4">
            <!-- Sales Data -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Recent Sales</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Product</th>
                                            <th>Customer</th>
                                            <th>Quantity</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sale['order_id']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                                            <td>₹<?php echo htmlspecialchars($sale['total_amount']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['status']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['ordered_time']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reviews -->
            <div class="row mt-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Recent Reviews</h5>
                            <?php foreach ($reviews as $review): ?>
                            <div class="border-bottom p-3">
                                <div class="d-flex justify-content-between">
                                    <h6><?php echo htmlspecialchars($review['product_name']); ?></h6>
                                    <div>Rating: <?php echo htmlspecialchars($review['rating']); ?>/5</div>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($review['comment']); ?></p>
                                <small class="text-muted">By <?php echo htmlspecialchars($review['username']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<<<<<<< HEAD
    <?php if ($show_verification_popup): ?>
        <div id="verificationPopup" class="verification-popup" style="display: flex;">
            <div class="popup-content">
                <h2>Complete Your Seller Verification</h2>
                <form id="verificationForm" novalidate>
                    <div class="form-group">
                        <label for="id_type">ID Type*</label>
                        <select name="id_type" id="id_type" required>
                            <option value="">Select ID Type</option>
                            <option value="aadhar">Aadhar Card</option>
                            <option value="pan">PAN Card</option>
                            <option value="voter">Voter ID</option>
                            <option value="driving">Driving License</option>
                        </select>
                        <div class="error-message">Please select an ID type</div>
                    </div>

                    <div class="form-group">
                        <label for="id_number">ID Number*</label>
                        <input type="text" id="id_number" name="id_number" required>
                        <div class="error-message">Please enter a valid ID number</div>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Front)*</label>
                        <input type="file" name="id_proof_front" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload front ID proof</div>
                    </div>

                    <div class="form-group">
                        <label>ID Proof (Back)*</label>
                        <input type="file" name="id_proof_back" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload back ID proof</div>
                    </div>

                    <div class="form-group">
                        <label>Business License/Registration*</label>
                        <input type="file" name="business_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload business proof</div>
                    </div>

                    <div class="form-group">
                        <label>Address Proof*</label>
                        <input type="file" name="address_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="error-message">Please upload address proof</div>
                    </div>

                    <div class="terms-group">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the verification terms and conditions*</label>
                        <div class="error-message">Please accept the terms</div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Submit Verification</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Dashboard Content -->
        <?php if ($show_success): ?>
            <div class="dashboard-success-message" id="dashboardSuccess" style="display: block;">
                ✅ Verification completed successfully! Welcome to your seller dashboard.
            </div>
        <?php endif; ?>
        <!-- Rest of your dashboard content -->
    <?php endif; ?>

    <div class="loading" id="loadingIndicator"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('verificationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Reset previous errors
        document.querySelectorAll('.error-message').forEach(error => error.style.display = 'none');
        document.querySelectorAll('.form-group').forEach(group => group.classList.remove('error'));
        
        // Validate form
        let isValid = true;
        const form = this;
        
        // Check required fields
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value) {
                isValid = false;
                field.closest('.form-group').classList.add('error');
                field.closest('.form-group').querySelector('.error-message').style.display = 'block';
            }
        });

        // Validate ID number format based on selected ID type
        const idType = form.querySelector('#id_type').value;
        const idNumber = form.querySelector('#id_number').value;
        if (idType && idNumber) {
            const patterns = {
                aadhar: /^\d{12}$/,
                pan: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/,
                voter: /^[A-Z]{3}\d{7}$/,
                driving: /^[A-Z]{2}\d{13}$/
            };
            
            if (!patterns[idType]?.test(idNumber)) {
                isValid = false;
                form.querySelector('#id_number').closest('.form-group').classList.add('error');
                form.querySelector('#id_number').closest('.form-group').querySelector('.error-message').style.display = 'block';
            }
        }

        if (!isValid) {
            return;
        }

        document.getElementById('loadingIndicator').style.display = 'flex';
        
        const formData = new FormData(this);
        
        fetch('process_seller_verification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingIndicator').style.display = 'none';
            
            if (data.success) {
                window.location.href = 'seller-dashboard.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            document.getElementById('loadingIndicator').style.display = 'none';
            console.error('Error:', error);
            alert('An error occurred while submitting the verification documents.');
        });
    });

    // Auto-hide success message after 5 seconds
    const successMessage = document.getElementById('dashboardSuccess');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
    </script>
=======
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
>>>>>>> be96bba731a0f91bdfdea8826c2876e147b824db
</body>
</html>