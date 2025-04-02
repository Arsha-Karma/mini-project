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
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" id="phone" class="form-input" 
                           value="<?php echo htmlspecialchars($user_data['Phoneno'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" id="address" name="address" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" id="city" name="city" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" class="form-input" required>
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

        // Save address functionality
        document.getElementById('save-address').addEventListener('click', function() {
            const addressData = {
                address: document.getElementById('address').value,
                city: document.getElementById('city').value,
                postal_code: document.getElementById('postal_code').value
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
                    alert('Error saving address: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving address. Please try again.');
            });
        });

        // Checkout button functionality
        document.getElementById('checkout-btn').addEventListener('click', function() {
            const currentTotal = calculateTotal(); // Get the current total including gift wrap
            
            const options = {
                key: "<?php echo $razorpay_key_id; ?>",
                amount: currentTotal * 100, // Convert to paise for Razorpay
                currency: "INR",
                name: "Perfume Paradise",
                description: "Purchase from Perfume Paradise",
                image: "path/to/your/logo.png",
                handler: function(response) {
                    console.log('Payment ID:', response.razorpay_payment_id);
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'process_payment.php';

                    const formData = {
                        'razorpay_payment_id': response.razorpay_payment_id,
                        'order_id': '<?php echo $order_id; ?>',
                        'amount': currentTotal,
                        'fullname': document.getElementById('fullname').value,
                        'email': document.getElementById('email').value,
                        'phone': document.getElementById('phone').value,
                        'address': document.getElementById('address').value,
                        'city': document.getElementById('city').value,
                        'postal_code': document.getElementById('postal_code').value,
                        'is_buy_now': '<?php echo $is_buy_now ? "1" : "0"; ?>',
                        'gift_option': document.getElementById('gift-option').checked ? '1' : '0',
                        'gift_recipient': document.getElementById('gift-recipient').value,
                        'gift_message': document.getElementById('gift-message').value,
                        'gift_wrap': document.getElementById('gift-wrap').value,
                        'save_address': true
                        <?php if ($is_buy_now): ?>,
                        'product_id': '<?php echo $_GET['product_id']; ?>'
                        <?php endif; ?>
                    };

                    for (const [key, value] of Object.entries(formData)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }

                    document.body.appendChild(form);
                    form.submit();
                },
                prefill: {
                    name: document.getElementById('fullname').value,
                    email: document.getElementById('email').value,
                    contact: document.getElementById('phone').value
                },
                theme: {
                    color: "#2874f0"
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        });
    </script>
</body>
</html>