<?php
session_start();
require_once 'dbconnect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details from tbl_signup
$get_user_query = "SELECT * FROM tbl_signup WHERE Signup_id = ?";
$stmt = $conn->prepare($get_user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Check if this is a "Buy Now" request
$is_buy_now = isset($_GET['buy_now']) && isset($_GET['product_id']);

if ($is_buy_now) {
    // Fetch single product details for "Buy Now" with all necessary fields
    $product_query = "
        SELECT p.*, 
               p.name,
               p.price,
               p.image_path,
               p.Stock_quantity,
               (p.price * 1) as total_price
        FROM tbl_product p
        WHERE p.product_id = ? AND p.deleted = 0
    ";
    $stmt = $conn->prepare($product_query);
    $stmt->bind_param("i", $_GET['product_id']);
    $stmt->execute();
    $product_result = $stmt->get_result();
    $product = $product_result->fetch_assoc();

    // Store product in cart_products array
    $cart_products = array();
    if ($product) {
        $product['quantity'] = 1; // Set quantity to 1 for direct buy
        $cart_products[] = $product;
        $subtotal = $product['total_price'];
        $total_items = 1;
    } else {
        // Redirect if product not found
        header("Location: productslist.php");
        exit();
    }
} else {
    // Fetch cart items with product details (existing cart functionality)
    $cart_query = "
        SELECT c.*, p.name, p.price, p.image_path, p.Stock_quantity,
               (p.price * c.quantity) as total_price
        FROM tbl_cart c
        JOIN tbl_product p ON c.product_id = p.product_id
        WHERE c.user_id = ? AND c.status = 'pending'
        ORDER BY c.created_at DESC
    ";
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_items = $stmt->get_result();

    // Calculate totals
    $subtotal = 0;
    $total_items = 0;

    // Store cart items in array for reuse
    $cart_products = array();
    while ($item = $cart_items->fetch_assoc()) {
        $cart_products[] = $item;
        $subtotal += $item['total_price'];
        $total_items += $item['quantity'];
    }
}

// Calculate shipping and tax (same for both cases)
$shipping = $subtotal >= 1000 ? 0 : 50;
$tax = $subtotal * 0.05; // 5% tax
$total = $subtotal + $shipping + $tax;

// Create unique order ID
$order_id = 'PERF' . time() . $user_id;

// Razorpay API key
$razorpay_key_id = "rzp_test_LFEA5QeDc3uh7A";

$insert_payment = $conn->prepare("
    INSERT INTO payment_table (
        order_id, 
        Signup_id, 
        payment_id, 
        amount, 
        payment_method, 
        payment_status
    ) VALUES (?, ?, ?, ?, 'Razorpay', 'paid')
");

$insert_payment->bind_param("sssd", 
    $order_id,
    $user_id,
    $razorpay_payment_id,
    $amount
);

$update_cart = $conn->prepare("
    UPDATE tbl_cart 
    SET status = 'completed' 
    WHERE cart_id = ? AND user_id = ?
");

// Insert or update shipping address
$shipping_query = "INSERT INTO shipping_addresses 
                  (Signup_id, address_line1, city, state, postal_code, is_default, created_at) 
                  VALUES (?, ?, ?, ?, ?, 1, NOW())";

// Create variables for binding                  
$default_state = 'default_state';
$is_default = 1;

$stmt = $conn->prepare($shipping_query);
$stmt->bind_param("issss", 
    $user_id,
    $_POST['address'],
    $_POST['city'],
    $default_state,  // Now using a variable instead of literal string
    $_POST['postal_code']
);

try {
    $conn->begin_transaction();
    // ... payment processing ...
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    // ... error handling ...
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        /* Add validation styles */
        .form-input.error {
            border-color: #dc3545;
        }

        .form-input.success {
            border-color: #28a745;
        }

        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .success-message {
            color: #28a745;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-group {
            position: relative;
        }

        .validation-icon {
            position: absolute;
            right: 10px;
            top: 35px;
            display: none;
        }

        .validation-icon i {
            font-size: 16px;
        }

        .validation-icon.error i {
            color: #dc3545;
        }

        .validation-icon.success i {
            color: #28a745;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .checkout-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-input:focus {
            outline: none;
            border-color: #2874f0;
        }

        .order-summary {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }

        .cart-items {
            margin-bottom: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .item-image {
            width: 60px;
            height: 60px;
            margin-right: 15px;
            border-radius: 4px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }

        .item-details {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-name {
            flex: 1;
            padding-right: 15px;
            font-size: 14px;
        }

        .item-quantity {
            color: #666;
            font-size: 0.9em;
            margin-left: 5px;
        }

        .item-price {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-size: 16px;
        }

        .total-row {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: #2874f0;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }

        .checkout-btn:hover {
            background: #1c5ac7;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-secondary i {
            font-size: 16px;
        }

        .gift-options-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .gift-options-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }

        .gift-option-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .gift-option-toggle input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .gift-option-toggle label {
            font-weight: 600;
            cursor: pointer;
        }

        .gift-confirmation {
            padding: 10px 15px;
            background-color: #e8f4ff;
            border-left: 4px solid #2874f0;
            margin-bottom: 15px;
            font-size: 14px;
            color: #333;
        }

        #gift-message {
            min-height: 80px;
            resize: vertical;
        }

        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            .item-image {
                width: 50px;
                height: 50px;
            }
            
            .item-name {
                font-size: 13px;
            }
            
            .item-price {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="checkout-container">
        <div class="checkout-form">
            <h2 class="form-title">Shipping Information</h2>
            <form id="shipping-form">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="fullname" class="form-input" 
                           value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" required>
                    <div class="validation-icon error"><i class="fas fa-times-circle"></i></div>
                    <div class="validation-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="error-message">Please enter a valid name (only letters and spaces)</div>
                    <div class="success-message">Valid name format</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                    <div class="validation-icon error"><i class="fas fa-times-circle"></i></div>
                    <div class="validation-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="error-message">Please enter a valid email address</div>
                    <div class="success-message">Valid email format</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" id="phone" class="form-input" 
                           value="<?php echo htmlspecialchars($user_data['Phoneno'] ?? ''); ?>" required>
                    <div class="validation-icon error"><i class="fas fa-times-circle"></i></div>
                    <div class="validation-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="error-message">Please enter a valid mobile number</div>
                    <div class="success-message">Valid mobile number</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" id="address" name="address" class="form-input" required>
                    <div class="validation-icon error"><i class="fas fa-times-circle"></i></div>
                    <div class="validation-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="error-message">Please enter your complete address</div>
                    <div class="success-message">Valid address</div>
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" id="city" name="city" class="form-input" required>
                    <div class="validation-icon error"><i class="fas fa-times-circle"></i></div>
                    <div class="validation-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="error-message">Please enter a valid city name</div>
                    <div class="success-message">Valid city name</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" class="form-input" required>
                    <div class="validation-icon error"><i class="fas fa-times-circle"></i></div>
                    <div class="validation-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="error-message">Please enter a valid 6-digit postal code</div>
                    <div class="success-message">Valid postal code</div>
                </div>
                
                <button type="button" id="save-address" class="btn btn-secondary">
                    <i class="fas fa-save"></i> Save Address
                </button>
            </form>
        </div>

        <div class="order-summary">
            <h2 class="summary-title">Order Summary</h2>
            <div class="cart-items">
                <?php if ($is_buy_now): ?>
                    <?php if ($product): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="item-details">
                                <div class="item-name">
                                    <?php echo htmlspecialchars($product['name']); ?> 
                                    <span class="item-quantity">× 1</span>
                                </div>
                                <div class="item-price">₹<?php echo number_format($product['price'], 2); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php foreach ($cart_products as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="item-details">
                                <div class="item-name">
                                    <?php echo htmlspecialchars($item['name']); ?> 
                                    <span class="item-quantity">× <?php echo $item['quantity']; ?></span>
                                </div>
                                <div class="item-price">₹<?php echo number_format($item['total_price'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Gift Options Section -->
            <div class="gift-options-section">
                <h3 class="gift-options-title">Gift Options</h3>
                <div class="gift-option-toggle">
                    <input type="checkbox" id="gift-option" name="gift_option">
                    <label for="gift-option">Send as a Gift</label>
                </div>
                
                <div id="gift-options-container" style="display: none;">
                    <div class="gift-message">
                        <p class="gift-confirmation">This product will be wrapped as a gift for your favorite one!</p>
                        
                        <div class="form-group">
                            <label class="form-label">Recipient's Name</label>
                            <input type="text" id="gift-recipient" name="gift_recipient" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Personal Message</label>
                            <textarea id="gift-message" name="gift_message" class="form-input" 
                                      placeholder="Add a personal message to your gift..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Gift Wrapping</label>
                            <select id="gift-wrap" name="gift_wrap" class="form-input">
                                <option value="standard">Standard Gift Wrap (Free)</option>
                                <option value="premium">Premium Gift Wrap (₹50)</option>
                                <option value="themed">Themed Luxury Packaging (₹100)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="summary-row">
                <span>Subtotal</span>
                <span>₹<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></span>
            </div>
            <div class="summary-row">
                <span>Tax (5%)</span>
                <span>₹<?php echo number_format($tax, 2); ?></span>
            </div>
            <div class="summary-row total-row">
                <span>Total</span>
                <span id="order-total">₹<?php echo number_format($total, 2); ?></span>
            </div>

            <button id="checkout-btn" class="checkout-btn">Proceed to Payment</button>
        </div>
    </div>

    <script>
        // Form validation functions
        function validateName(name) {
            // Only letters and single spaces between words, no numbers
            const nameRegex = /^[A-Za-z]+(?:\s[A-Za-z]+)*$/;
            const value = name.trim();
            if (!nameRegex.test(value)) {
                document.getElementById('fullname').classList.add('error');
                return false;
            }
            return value.length >= 2 && value.length <= 50;
        }

        function validateEmail(email) {
            // No spaces allowed in email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email.trim()) && !email.includes(' ');
        }

        function validatePhone(phone) {
            // Backend validation for phone number:
            // 1. Must start with 6, 7, 8, or 9
            // 2. Must be exactly 10 digits
            // 3. Cannot start with 0-5
            // 4. Cannot be all zeros after first digit
            const phoneValue = phone.trim();
            
            // Check if it's exactly 10 digits
            if (!/^\d{10}$/.test(phoneValue)) {
                return false;
            }
            
            // Check if starts with 6, 7, 8, or 9
            if (!/^[6-9]/.test(phoneValue)) {
                return false;
            }
            
            // Check if all digits after first are not zeros
            if (/^[6-9]0{9}$/.test(phoneValue)) {
                return false;
            }
            
            return true;
        }

        function validateAddress(address) {
            // No leading/trailing spaces, minimum 10 characters
            return address.trim().length >= 10 && address.trim().length <= 200;
        }

        function validateCity(city) {
            // No leading/trailing spaces, only letters and spaces, 2-50 characters
            const cityRegex = /^[a-zA-Z]+(?: [a-zA-Z]+)*$/;
            return cityRegex.test(city.trim()) && city.trim().length >= 2 && city.trim().length <= 50;
        }

        function validatePostalCode(postalCode) {
            // Only numbers allowed, exactly 6 digits, no spaces or special characters
            const postalRegex = /^[0-9]{6}$/;
            return postalRegex.test(postalCode) && postalCode.length === 6;
        }

        // Function to show validation message with strict enforcement and null checks
        function showValidation(input, isValid) {
            if (!input) {
                console.error('Input element is null');
                return false;
            }

            const formGroup = input.parentElement;
            if (!formGroup) {
                console.error('Form group element is null');
                return false;
            }

            const errorIcon = formGroup.querySelector('.validation-icon.error');
            const successIcon = formGroup.querySelector('.validation-icon.success');
            const errorMessage = formGroup.querySelector('.error-message');
            const successMessage = formGroup.querySelector('.success-message');

            // Check if all required elements exist
            if (!errorIcon || !successIcon || !errorMessage || !successMessage) {
                console.error('One or more validation elements are missing');
                return false;
            }

            try {
                if (isValid) {
                    input.classList.remove('error');
                    input.classList.add('success');
                    errorIcon.style.display = 'none';
                    successIcon.style.display = 'block';
                    errorMessage.style.display = 'none';
                    successMessage.style.display = 'block';
                    input.setAttribute('data-valid', 'true');
                } else {
                    input.classList.remove('success');
                    input.classList.add('error');
                    errorIcon.style.display = 'block';
                    successIcon.style.display = 'none';
                    errorMessage.style.display = 'block';
                    successMessage.style.display = 'none';
                    input.setAttribute('data-valid', 'false');
                }
                return isValid;
            } catch (error) {
                console.error('Error in showValidation:', error);
                return false;
            }
        }

        // Function to validate form with null checks
        function validateForm() {
            let isValid = true;
            const requiredFields = ['fullname', 'email', 'phone', 'address', 'city', 'postal_code'];
            
            for (const fieldId of requiredFields) {
                const input = document.getElementById(fieldId);
                if (!input) {
                    console.error(`Input element ${fieldId} not found`);
                    isValid = false;
                    continue;
                }

                let fieldValid = false;
                const value = input.value.trim();

                try {
                    switch(fieldId) {
                        case 'fullname':
                            fieldValid = validateName(value);
                            break;
                        case 'email':
                            fieldValid = validateEmail(value);
                            break;
                        case 'phone':
                            fieldValid = validatePhone(value);
                            break;
                        case 'address':
                            fieldValid = validateAddress(value);
                            break;
                        case 'city':
                            fieldValid = validateCity(value);
                            break;
                        case 'postal_code':
                            fieldValid = validatePostalCode(value);
                            break;
                    }

                    const validationResult = showValidation(input, fieldValid);
                    if (!validationResult) {
                        isValid = false;
                    }
                } catch (error) {
                    console.error(`Error validating ${fieldId}:`, error);
                    isValid = false;
                }
            }
            
            return isValid;
        }

        // Ensure DOM is loaded before adding event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for form inputs
            const requiredFields = ['fullname', 'email', 'phone', 'address', 'city', 'postal_code'];
            
            requiredFields.forEach(fieldId => {
                const input = document.getElementById(fieldId);
                if (input) {
                    input.addEventListener('input', function() {
                        let fieldValid = false;
                        try {
                            switch(this.id) {
                                case 'fullname':
                                    fieldValid = validateName(this.value);
                                    break;
                                case 'email':
                                    fieldValid = validateEmail(this.value);
                                    break;
                                case 'phone':
                                    fieldValid = validatePhone(this.value);
                                    break;
                                case 'address':
                                    fieldValid = validateAddress(this.value);
                                    break;
                                case 'city':
                                    fieldValid = validateCity(this.value);
                                    break;
                                case 'postal_code':
                                    fieldValid = validatePostalCode(this.value);
                                    break;
                            }
                            showValidation(this, fieldValid);
                        } catch (error) {
                            console.error(`Error in input validation for ${this.id}:`, error);
                        }
                    });
                }
            });

            // Add checkout button event listener
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', async function(e) {
                    e.preventDefault();

                    try {
                        // First validate all fields
                        if (!validateForm()) {
                            alert('Please correct all validation errors before proceeding.');
                            return;
                        }

                        // Show loading state
                        showLoading();

                        // Save address first
                        const addressData = {
                            fullname: document.getElementById('fullname').value.trim(),
                            email: document.getElementById('email').value.trim(),
                            phone: document.getElementById('phone').value.trim(),
                            address: document.getElementById('address').value.trim(),
                            city: document.getElementById('city').value.trim(),
                            postal_code: document.getElementById('postal_code').value,
                            validation_passed: true
                        };

                        // Save address
                        const saveAddressResponse = await fetch('save_address.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(addressData)
                        });

                        const addressResult = await saveAddressResponse.json();

                        if (!addressResult.success) {
                            hideLoading();
                            alert('Error saving address: ' + (addressResult.message || 'Please try again'));
                            return;
                        }

                        // Calculate total amount including gift wrap if selected
                        const currentTotal = calculateTotal();
                        
                        // Initialize Razorpay payment
                        const options = {
                            key: "<?php echo $razorpay_key_id; ?>",
                            amount: currentTotal * 100, // Convert to paise
                            currency: "INR",
                            name: "Perfume Paradise",
                            description: "Purchase from Perfume Paradise",
                            image: "path/to/your/logo.png",
                            prefill: {
                                name: document.getElementById('fullname').value.trim(),
                                email: document.getElementById('email').value.trim(),
                                contact: document.getElementById('phone').value.trim()
                            },
                            notes: {
                                address_id: addressResult.address_id,
                                order_id: '<?php echo $order_id; ?>'
                            },
                            theme: {
                                color: "#2874f0"
                            },
                            handler: function(response) {
                                // Payment successful
                                if (response.razorpay_payment_id) {
                                    // Create form for payment processing
                                    const form = document.createElement('form');
                                    form.method = 'POST';
                                    form.action = 'process_payment.php';

                                    const formData = {
                                        'razorpay_payment_id': response.razorpay_payment_id,
                                        'order_id': '<?php echo $order_id; ?>',
                                        'amount': currentTotal,
                                        'address_id': addressResult.address_id,
                                        'is_buy_now': '<?php echo $is_buy_now ? "1" : "0"; ?>',
                                        'gift_option': document.getElementById('gift-option').checked ? '1' : '0',
                                        'gift_recipient': document.getElementById('gift-recipient')?.value.trim() || '',
                                        'gift_message': document.getElementById('gift-message')?.value.trim() || '',
                                        'gift_wrap': document.getElementById('gift-wrap')?.value || 'standard'
                                        <?php if ($is_buy_now): ?>,
                                        'product_id': '<?php echo $_GET['product_id']; ?>'
                                        <?php endif; ?>
                                    };

                                    // Add form fields
                                    for (const [key, value] of Object.entries(formData)) {
                                        const input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = key;
                                        input.value = value;
                                        form.appendChild(input);
                                    }

                                    // Submit form
                                    document.body.appendChild(form);
                                    form.submit();
                                }
                            },
                            modal: {
                                ondismiss: function() {
                                    hideLoading();
                                    console.log('Payment cancelled');
                                }
                            }
                        };

                        // Create Razorpay instance and open payment modal
                        const rzp = new Razorpay(options);
                        rzp.on('payment.failed', function(response) {
                            hideLoading();
                            alert('Payment failed: ' + response.error.description);
                            console.error('Payment failed:', response.error);
                        });

                        rzp.open();

                    } catch (error) {
                        hideLoading();
                        console.error('Error in checkout process:', error);
                        alert('An error occurred. Please try again.');
                    }
                });
            } else {
                console.error('Checkout button not found');
            }
        });

        // Toggle gift options visibility
        document.getElementById('gift-option').addEventListener('change', function() {
            const giftOptionsContainer = document.getElementById('gift-options-container');
            giftOptionsContainer.style.display = this.checked ? 'block' : 'none';
            calculateTotal();
        });

        // Recalculate total when gift wrap option changes
        document.getElementById('gift-wrap').addEventListener('change', calculateTotal);

        // Calculate total including gift wrap charges
        function calculateTotal() {
            let total = <?php echo $total; ?>;
            
            // Add gift wrap cost if applicable
            if (document.getElementById('gift-option').checked) {
                const giftWrapType = document.getElementById('gift-wrap').value;
                if (giftWrapType === 'premium') {
                    total += 50;
                } else if (giftWrapType === 'themed') {
                    total += 100;
                }
            }
            
            // Update displayed total
            document.getElementById('order-total').textContent = '₹' + total.toFixed(2);
            
            return total;
        }

        // Save address functionality with strict validation
        document.getElementById('save-address').addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            // Validate all required fields first
            const requiredFields = ['fullname', 'email', 'phone', 'address', 'city', 'postal_code'];
            let isValid = true;
            let invalidFields = [];

            // Clear any previous validation states
            requiredFields.forEach(fieldId => {
                const input = document.getElementById(fieldId);
                if (!input) return;
                
                let fieldValid = false;
                const value = input.value.trim();

                switch(fieldId) {
                    case 'fullname':
                        fieldValid = validateName(value);
                        if (!fieldValid) invalidFields.push('Full Name');
                        break;
                    case 'email':
                        fieldValid = validateEmail(value);
                        if (!fieldValid) invalidFields.push('Email');
                        break;
                    case 'phone':
                        fieldValid = validatePhone(value);
                        if (!fieldValid) invalidFields.push('Phone');
                        break;
                    case 'address':
                        fieldValid = validateAddress(value);
                        if (!fieldValid) invalidFields.push('Address');
                        break;
                    case 'city':
                        fieldValid = validateCity(value);
                        if (!fieldValid) invalidFields.push('City');
                        break;
                    case 'postal_code':
                        fieldValid = validatePostalCode(value);
                        if (!fieldValid) invalidFields.push('Postal Code');
                        break;
                }

                showValidation(input, fieldValid);
                if (!fieldValid) {
                    isValid = false;
                }
            });

            if (!isValid) {
                alert('Please correct the following fields before saving:\n' + invalidFields.join('\n'));
                return false; // Prevent form submission
            }

            // Additional validation check before proceeding
            const allInputsValid = requiredFields.every(fieldId => {
                const input = document.getElementById(fieldId);
                return input && input.getAttribute('data-valid') === 'true';
            });

            if (!allInputsValid) {
                alert('Please ensure all fields are properly filled before saving.');
                return false;
            }

            // Only proceed if all validations pass
            const addressData = {
                fullname: document.getElementById('fullname').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                address: document.getElementById('address').value.trim(),
                city: document.getElementById('city').value.trim(),
                postal_code: document.getElementById('postal_code').value,
                validation_passed: true // Add flag to indicate validation passed
            };

            fetch('save_address.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(addressData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Address saved successfully!');
                } else {
                    alert('Error saving address: ' + (data.message || 'Validation failed'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving address. Please ensure all fields are valid and try again.');
            });

            return false; // Prevent form submission
        });

        // Prevent form submission on enter key
        document.getElementById('shipping-form').addEventListener('submit', function(e) {
            e.preventDefault();
            return false;
        });

        // Add strict input validation for postal code
        document.getElementById('postal_code').addEventListener('input', function(e) {
            // Remove any non-numeric characters immediately
            const sanitizedValue = this.value.replace(/[^0-9]/g, '');
            
            // Update input value with only numbers
            this.value = sanitizedValue;
            
            // Limit to exactly 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
            
            // Show validation status
            const isValid = validatePostalCode(this.value);
            showValidation(this, isValid);
            
            // Set custom validity for form validation
            this.setCustomValidity(isValid ? '' : 'Please enter exactly 6 digits');
        });

        // Prevent paste of invalid characters in postal code
        document.getElementById('postal_code').addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const sanitizedText = pastedText.replace(/[^0-9]/g, '').slice(0, 6);
            this.value = sanitizedText;
            showValidation(this, validatePostalCode(sanitizedText));
        });

        // Add strict input validation for phone
        document.getElementById('phone').addEventListener('input', function(e) {
            // Remove any non-numeric characters immediately
            const sanitizedValue = this.value.replace(/[^0-9]/g, '');
            
            // Update input value with only numbers
            this.value = sanitizedValue;
            
            // Limit to exactly 10 digits
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
            
            // Validate but don't show specific error messages
            const isValid = validatePhone(this.value);
            showValidation(this, isValid);
        });

        // Prevent paste of invalid characters in phone
        document.getElementById('phone').addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const sanitizedText = pastedText.replace(/[^0-9]/g, '').slice(0, 10);
            this.value = sanitizedText;
            showValidation(this, validatePhone(sanitizedText));
        });

        // Loading state functions
        function showLoading() {
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.disabled = true;
                checkoutBtn.textContent = 'Processing...';
            }
        }

        function hideLoading() {
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.disabled = false;
                checkoutBtn.textContent = 'Proceed to Payment';
            }
        }
    </script>
</body>
</html>