<?php
session_start();
require_once 'dbconnect.php';

// Create cart table if it doesn't exist
$create_cart_table = "CREATE TABLE IF NOT EXISTS tbl_cart (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES tbl_signup(Signup_id),
    FOREIGN KEY (product_id) REFERENCES tbl_product(product_id)
)";

if (!$conn->query($create_cart_table)) {
    die("Error creating cart table: " . $conn->error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=add_to_cart.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Add to Cart action
if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    
    // Check if product exists and has stock
    $check_product = $conn->prepare("SELECT Stock_quantity FROM tbl_product WHERE product_id = ?");
    $check_product->bind_param("i", $product_id);
    $check_product->execute();
    $product_result = $check_product->get_result();
    $product = $product_result->fetch_assoc();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }

    if ($product['Stock_quantity'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product is out of stock']);
        exit();
    }

    // Check if product already in cart
    $check_cart = $conn->prepare("SELECT cart_id, quantity FROM tbl_cart WHERE user_id = ? AND product_id = ? AND status = 'pending'");
    $check_cart->bind_param("ii", $user_id, $product_id);
    $check_cart->execute();
    $result = $check_cart->get_result();

    if ($result->num_rows > 0) {
        // Update quantity
        $cart_item = $result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + 1;
        
        if ($new_quantity <= $product['Stock_quantity']) {
            $update_cart = $conn->prepare("UPDATE tbl_cart SET quantity = ? WHERE cart_id = ?");
            $update_cart->bind_param("ii", $new_quantity, $cart_item['cart_id']);
            if ($update_cart->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cart updated successfully']);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating cart']);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
            exit();
        }
    } else {
        // Add new item to cart
        $add_cart = $conn->prepare("INSERT INTO tbl_cart (user_id, product_id, quantity, status) VALUES (?, ?, 1, 'pending')");
        $add_cart->bind_param("ii", $user_id, $product_id);
        if ($add_cart->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product added to cart']);
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding to cart']);
            exit();
        }
    }
}

// Handle quantity updates
if (isset($_POST['update_quantity']) && isset($_POST['cart_id']) && isset($_POST['quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $quantity = intval($_POST['quantity']);
    
    // Verify stock availability before updating
    $check_stock = $conn->prepare("
        SELECT p.Stock_quantity 
        FROM tbl_cart c 
        JOIN tbl_product p ON c.product_id = p.product_id 
        WHERE c.cart_id = ? AND c.user_id = ?
    ");
    $check_stock->bind_param("ii", $cart_id, $user_id);
    $check_stock->execute();
    $stock_result = $check_stock->get_result();
    $stock_data = $stock_result->fetch_assoc();

    if ($stock_data && $quantity > 0 && $quantity <= $stock_data['Stock_quantity']) {
        $update = $conn->prepare("UPDATE tbl_cart SET quantity = ? WHERE cart_id = ? AND user_id = ? AND status = 'pending'");
        $update->bind_param("iii", $quantity, $cart_id, $user_id);
        $update->execute();

        if (isset($_POST['is_ajax'])) {
            echo json_encode(['success' => true]);
            exit();
        }
    }
}

// Handle remove from cart
if (isset($_GET['remove'])) {
    $cart_id = intval($_GET['remove']);
    $delete = $conn->prepare("DELETE FROM tbl_cart WHERE cart_id = ? AND user_id = ? AND status = 'pending'");
    $delete->bind_param("ii", $cart_id, $user_id);
    $delete->execute();
}

// Fetch cart items with product details
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
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate cart totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['total_price'];
    $total_items += $item['quantity'];
}

// Calculate shipping (free over ₹1000)
$shipping = $subtotal >= 1000 ? 0 : 50;
$total = $subtotal + $shipping;

// Update cart count in session
$_SESSION['cart_count'] = $total_items;

// If this is an AJAX request, return JSON response
if (isset($_POST['is_ajax'])) {
    echo json_encode([
        'success' => true,
        'cart_count' => $total_items
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="cart-container">
        <h1>Shopping Cart (<?php echo $total_items; ?> items)</h1>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Your cart is empty</p>
                <a href="productslist.php" class="continue-shopping">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-grid">
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                            
                            <div class="item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="price">₹<?php echo number_format($item['price'], 2); ?></p>
                                
                                <?php if ($item['Stock_quantity'] > 0): ?>
                                    <div class="quantity-controls">
                                        <form method="post" class="quantity-form">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            <button type="button" class="quantity-btn minus" 
                                                    onclick="updateQuantity(<?php echo $item['cart_id']; ?>, -1, <?php echo $item['Stock_quantity']; ?>)">-</button>
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="<?php echo $item['Stock_quantity']; ?>" 
                                                   class="quantity-input" readonly>
                                            <button type="button" class="quantity-btn plus" 
                                                    onclick="updateQuantity(<?php echo $item['cart_id']; ?>, 1, <?php echo $item['Stock_quantity']; ?>)">+</button>
                                        </form>
                                    </div>
                                    
                                    <div class="stock-status">
                                        <?php if ($item['Stock_quantity'] <= 5): ?>
                                            <span class="low-stock">
                                                <i class="fas fa-exclamation-circle"></i>
                                                Only <?php echo $item['Stock_quantity']; ?> left in stock - order soon
                                            </span>
                                        <?php elseif ($item['Stock_quantity'] <= 10): ?>
                                            <span class="medium-stock">
                                                <i class="fas fa-info-circle"></i>
                                                Only <?php echo $item['Stock_quantity']; ?> left in stock
                                            </span>
                                        <?php else: ?>
                                            <span class="in-stock">
                                                <i class="fas fa-check-circle"></i>
                                                In Stock
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="stock-status">
                                        <span class="out-of-stock">
                                            <i class="fas fa-times-circle"></i>
                                            Out of Stock
                                        </span>
                                        <button class="notify-btn" onclick="notifyWhenAvailable(<?php echo $item['product_id']; ?>)">
                                            <i class="fas fa-bell"></i> Notify When Available
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-total">
                                <p class="total-price">₹<?php echo number_format($item['total_price'], 2); ?></p>
                                <a href="?remove=<?php echo $item['cart_id']; ?>" class="remove-item">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    <div class="summary-item">
                        <span>Subtotal</span>
                        <span>₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Shipping</span>
                        <span><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></span>
                    </div>
                    <div class="summary-total">
                        <span>Total</span>
                        <span>₹<?php echo number_format($total, 2); ?></span>
                    </div>
                    <button onclick="location.href='checkout.php'" class="checkout-btn">
                        Proceed to Checkout
                    </button>
                    <a href="productslist.php" class="continue-shopping">Continue Shopping</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

<style>
.cart-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.cart-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-top: 30px;
}

.cart-item {
    display: flex;
    background: white;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.cart-item img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 4px;
}

.item-details {
    flex-grow: 1;
    padding: 0 20px;
}

.quantity-controls {
    display: flex;
    align-items: center;
    margin-top: 10px;
    margin-bottom: 5px;
}

.quantity-btn {
    padding: 5px 10px;
    border: 1px solid #D5D9D9;
    background: linear-gradient(to bottom, #F7F8FA, #E7E9EC);
    cursor: pointer;
    border-radius: 3px;
}

.quantity-btn:hover {
    background: linear-gradient(to bottom, #E7E9EC, #D5D9D9);
}

.quantity-btn:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.quantity-input {
    width: 60px;
    text-align: center;
    margin: 0 10px;
    padding: 5px;
}

.update-btn {
    margin-left: 10px;
    padding: 5px 10px;
    background: #e8a87c;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.item-total {
    text-align: right;
    min-width: 100px;
}

.remove-item {
    color: #dc3545;
    text-decoration: none;
    margin-top: 10px;
    display: inline-block;
}

.cart-summary {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    position: sticky;
    top: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin: 10px 0;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #333;
    font-weight: bold;
    font-size: 1.2em;
}

.checkout-btn {
    width: 100%;
    padding: 15px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    margin-top: 20px;
    cursor: pointer;
    font-size: 1.1em;
}

.continue-shopping {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #666;
    text-decoration: none;
}

.empty-cart {
    text-align: center;
    padding: 50px;
}

.empty-cart i {
    font-size: 4em;
    color: #ddd;
    margin-bottom: 20px;
}

.stock-status {
    margin-top: 10px;
    font-size: 0.9em;
}

.stock-status span {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
}

.stock-status i {
    font-size: 1.1em;
}

.in-stock {
    color: #007600;
}

.in-stock i {
    color: #007600;
}

.medium-stock {
    color: #FF9900;
}

.medium-stock i {
    color: #FF9900;
}

.low-stock {
    color: #B12704;
    font-weight: 500;
}

.low-stock i {
    color: #B12704;
}

.out-of-stock {
    color: #B12704;
    font-weight: 500;
}

.out-of-stock i {
    color: #B12704;
}

.notify-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding: 8px 16px;
    background: #F0F2F2;
    border: 1px solid #D5D9D9;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9em;
    color: #0F1111;
    transition: all 0.2s ease;
}

.notify-btn:hover {
    background: #E3E6E6;
}

.notify-btn i {
    color: #666;
}

@media (max-width: 768px) {
    .cart-grid {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        flex-direction: column;
        text-align: center;
    }
    
    .cart-item img {
        margin: 0 auto 20px;
    }
    
    .item-total {
        text-align: center;
        margin-top: 20px;
    }
}
</style>

<script>
function updateQuantity(cartId, change, maxStock) {
    const form = event.target.closest('.quantity-form');
    const input = form.querySelector('.quantity-input');
    const currentValue = parseInt(input.value);
    let newValue = currentValue + change;
    
    if (newValue >= 1 && newValue <= maxStock) {
        const formData = new FormData();
        formData.append('cart_id', cartId);
        formData.append('quantity', newValue);
        formData.append('update_quantity', '1');
        formData.append('is_ajax', '1');
        
        fetch('add_to_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating quantity. Please try again.');
        });
    }
}

// Update cart counter in header
function updateCartCounter() {
    const counter = document.querySelector('.cart-counter');
    if (counter) {
        counter.textContent = '<?php echo $total_items; ?>';
    }
}

document.addEventListener('DOMContentLoaded', updateCartCounter);

function notifyWhenAvailable(productId) {
    // You can implement the notification functionality here
    alert('You will be notified when this product becomes available.');
}
</script>

</body>
</html> 