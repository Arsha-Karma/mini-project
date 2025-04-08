<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Fetch all orders for the user with payment details
$order_query = "SELECT o.*, p.payment_status, p.payment_id, p.amount,
                       pr.product_id, pr.name as product_name, o.gift_wrap_charge, o.gift_option, o.gift_wrap_type 
                FROM orders_table o
                LEFT JOIN payment_table p ON o.order_id = p.order_id
                LEFT JOIN tbl_product pr ON o.product_id = pr.product_id
                WHERE o.Signup_id = ? ";

// Add date filter conditions
switch($filter) {
    case '24h':
        $order_query .= "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ";
        break;
    case 'week':
        $order_query .= "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK) ";
        break;
    case 'month':
        $order_query .= "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) ";
        break;
    case 'year':
        $order_query .= "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR) ";
        break;
    default:
        // 'all' - no additional date filtering
        break;
}

$order_query .= "ORDER BY o.created_at DESC";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .orders-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 2em;
        }

        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .order-label {
            font-size: 0.9em;
            color: #666;
        }

        .order-value {
            font-weight: bold;
            color: #333;
        }

        .order-status {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-processing {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .order-items {
            padding: 20px;
        }

        .item-grid {
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 15px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 4px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background-color: #2874f0;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .empty-orders {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .empty-orders i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .item-grid {
                grid-template-columns: 60px 1fr;
                gap: 10px;
            }

            .item-grid > *:nth-child(3),
            .item-grid > *:nth-child(4) {
                grid-column: 2;
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }

        .close:hover {
            color: #000;
        }

        .rating-container {
            margin: 20px 0;
            text-align: center;
        }

        .stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 10px;
        }

        .stars input {
            display: none;
        }

        .stars label {
            color: #ddd;
            font-size: 30px;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .stars label:hover,
        .stars label:hover ~ label,
        .stars input:checked ~ label {
            color: #ffd700;
        }

        .form-group {
            margin: 20px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .form-group textarea:focus {
            border-color: #e8a87c;
            outline: none;
            box-shadow: 0 0 5px rgba(232, 168, 124, 0.3);
        }

        .btn-primary {
            background-color: #e8a87c;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #d69a6f;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .btn-cancel {
            background-color: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-cancel:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.65;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn-review {
            background-color: #ffd700;
            color: #000;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-review:hover {
            background-color: #ffcd00;
        }

        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            cursor: pointer;
            color: #ddd;
            font-size: 24px;
        }

        .star-rating input:checked ~ label {
            color: #ffd700;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffcd00;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 1em;
            padding-right: 30px !important;
            cursor: pointer;
        }

        select:hover {
            border-color: #2874f0;
        }

        select:focus {
            outline: none;
            border-color: #2874f0;
            box-shadow: 0 0 0 2px rgba(40, 116, 240, 0.2);
        }

        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-container h1 {
            margin: 0;
        }

        .order-total {
            margin-top: 20px;
            padding: 15px;
            text-align: right;
            font-size: 1.1em;
            border-top: 2px solid #eee;
        }

        .order-summary {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
            font-weight: bold;
            font-size: 1.1em;
        }

        .summary-label {
            color: #666;
        }

        .summary-value {
            font-weight: 500;
            text-align: right;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="orders-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title">My Orders</h1>

        <div style="text-align: right; margin-bottom: 20px;">
            <form action="" method="GET" style="display: inline-block;">
                <select name="filter" onchange="this.form.submit()" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                    <option value="24h" <?php echo $filter === '24h' ? 'selected' : ''; ?>>Last 24 Hours</option>
                    <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>Last Week</option>
                    <option value="month" <?php echo $filter === 'month' ? 'selected' : ''; ?>>Last Month</option>
                    <option value="year" <?php echo $filter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                </select>
            </form>
        </div>

        <?php if ($orders_result->num_rows > 0): ?>
            <?php while ($order = $orders_result->fetch_assoc()): 
                // Fetch order items with all necessary details
                $items_query = "SELECT o.*, p.name as product_name, p.price, o.gift_wrap_charge, o.gift_option, o.gift_wrap_type 
                              FROM orders_table o
                              JOIN tbl_product p ON o.product_id = p.product_id 
                              WHERE o.order_id = ?";
                $stmt = $conn->prepare($items_query);
                $stmt->bind_param("s", $order['order_id']);
                $stmt->execute();
                $items = $stmt->get_result();

                // Initialize variables for calculations
                $subtotal = 0;
                $total_gift_wrap = 0;
            ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <h3>Order ID: <?php echo htmlspecialchars($order['order_id']); ?></h3>
                            <p>Date: <?php echo date('d M Y', strtotime($order['created_at'])); ?></p>
                        </div>
                        <span class="order-status status-<?php echo strtolower($order['order_status']); ?>">
                            <?php echo htmlspecialchars($order['order_status']); ?>
                        </span>
                    </div>

                    <div class="order-items">
                        <?php while ($item = $items->fetch_assoc()): 
                            $item_total = $item['price'] * $item['quantity'];
                            $subtotal += $item_total;
                            
                            // Add gift wrap charge if applicable
                            if ($item['gift_option'] && !empty($item['gift_wrap_charge'])) {
                                $total_gift_wrap += floatval($item['gift_wrap_charge']);
                            }
                        ?>
                            <div class="item-grid">
                                <div class="product-image">
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    <div>Quantity: <?php echo $item['quantity']; ?></div>
                                    <?php if ($item['gift_option']): ?>
                                        <div class="gift-wrap-info">
                                            <small>(Gift Wrapped - <?php echo ucfirst($item['gift_wrap_type']); ?>)</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; 

                        // Calculate final totals
                        $shipping = $subtotal >= 1000 ? 0 : 50;
                        $tax = $subtotal * 0.05; // 5% tax
                        $total_amount = $subtotal + $shipping + $tax + $total_gift_wrap;
                        ?>
                        
                        <div class="order-summary">
                            <div class="summary-row">
                                <span class="summary-label">Subtotal:</span>
                                <span class="summary-value">₹<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <?php if ($total_gift_wrap > 0): ?>
                            <div class="summary-row">
                                <span class="summary-label">Gift Wrap Charges:</span>
                                <span class="summary-value">₹<?php echo number_format($total_gift_wrap, 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="summary-row">
                                <span class="summary-label">Shipping:</span>
                                <span class="summary-value"><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Tax (5%):</span>
                                <span class="summary-value">₹<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="summary-row total">
                                <span class="summary-label">Total Amount Paid:</span>
                                <span class="summary-value">₹<?php echo number_format($total_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="order-actions">
                        <?php if ($order['order_status'] === 'Processing'): ?>
                            <button class="btn-cancel" 
                                    onclick="showCancelModal('<?php echo $order['order_id']; ?>', 
                                                           '<?php echo $order['payment_id']; ?>', 
                                                           <?php echo $order['amount']; ?>)">
                                <i class="fas fa-times"></i> Cancel Order
                            </button>
                        <?php endif; ?>

                        <?php
                        // Check if order is delivered and not reviewed yet
                        $review_check = $conn->prepare("SELECT review_id FROM tbl_reviews WHERE user_id = ? AND product_id = ?");
                        $review_check->bind_param("ii", $_SESSION['user_id'], $order['product_id']);
                        $review_check->execute();
                        $existing_review = $review_check->get_result()->fetch_assoc();
                        ?>

                        <!-- Review button - show for completed orders -->
                        <?php if ($order['order_status'] === 'Completed' && !$existing_review): ?>
                            <button class="btn btn-warning" onclick="showReviewModal('<?php echo $order['product_id']; ?>', '<?php echo htmlspecialchars($order['product_name']); ?>')">
                                <i class="fas fa-star"></i> Write Review
                            </button>
                        <?php endif; ?>

                        <?php if ($order['order_status'] !== 'Cancelled'): ?>
                            <a href="order_confirmation.php?order_id=<?php echo urlencode($order['order_id']); ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="print_receipt.php?order_id=<?php echo urlencode($order['order_id']); ?>" 
                               class="btn btn-secondary" target="_blank">
                                <i class="fas fa-print"></i> Print Receipt
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
           &nbsp; <div class="empty-orders"> &nbsp; &nbsp;
                <i class="fas fa-shopping-bag"></i>
                <h2>No Orders Yet</h2>
                <p>Looks like you haven't made any orders yet.</p>&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;
                <a href="productslist.php" class="btn btn-primary">
                &nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;
                &nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;  
                &nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;
                &nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;
                &nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp; &nbsp; <i class="fas fa-shopping-cart"></i> Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideReviewModal()">&times;</span>
            <h2>Write a Review</h2>
            <form id="reviewForm" method="POST" action="submit_review.php">
                <input type="hidden" name="product_id" id="review_product_id">
                
                <div class="form-group">
                    <label>Rating:</label>
                    <div class="star-rating">
                        <?php for($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                        <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="comment">Your Review:</label>
                    <textarea name="comment" id="comment" required></textarea>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="hideReviewModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div id="cancelOrderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideCancelModal()">&times;</span>
            <h2 style="text-align: center;">Cancel Order</h2><br>
            <p>Are you sure you want to cancel this order? The refund will be processed to your original payment method.</p>
            
            <form id="cancelOrderForm" method="POST" action="process_cancellation.php">
                <input type="hidden" name="order_id" id="cancel_order_id">
                <input type="hidden" name="payment_id" id="cancel_payment_id">
                <input type="hidden" name="amount" id="cancel_amount">
                
                <div class="form-group">
                    <label for="cancellation_reason">Reason for Cancellation:</label>
                    <select name="cancellation_reason" id="cancellation_reason" required>
                        <option value="">Select a reason</option>
                        <option value="Changed mind">Changed my mind</option>
                        <option value="Found better price">Found better price elsewhere</option>
                        <option value="Ordered by mistake">Ordered by mistake</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group" id="other_reason_group" style="display: none;">
                    <label for="other_reason">Please specify:</label>
                    <textarea name="other_reason" id="other_reason"></textarea>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="hideCancelModal()">No, Keep Order</button>
                    <button type="submit" class="btn btn-danger">Yes, Cancel Order</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Function to show the review modal
    function showReviewModal(productId, productName) {
        document.getElementById('review_product_id').value = productId;
        document.getElementById('reviewModal').style.display = 'block';
    }

    // Close modal when clicking the X
    document.querySelector('.close').onclick = function() {
        document.getElementById('reviewModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const reviewModal = document.getElementById('reviewModal');
        const cancelModal = document.getElementById('cancelOrderModal');
        if (event.target == reviewModal) {
            hideReviewModal();
        }
        if (event.target == cancelModal) {
            hideCancelModal();
        }
    }

    // Form validation before submission
    document.getElementById('reviewForm').onsubmit = function(e) {
        const rating = document.querySelector('input[name="rating"]:checked');
        const comment = document.getElementById('comment').value.trim();
        
        if (!rating) {
            e.preventDefault();
            alert('Please select a rating');
            return false;
        }
        
        if (!comment) {
            e.preventDefault();
            alert('Please write your review');
            return false;
        }
        
        return true;
    }

    // Star rating hover effect
    document.querySelectorAll('.stars label').forEach(star => {
        star.addEventListener('mouseover', function() {
            this.style.transform = 'scale(1.1)';
        });
        
        star.addEventListener('mouseout', function() {
            this.style.transform = 'scale(1)';
        });
    });
    </script>

    <script>
    // Show/hide cancel modal
    function showCancelModal(orderId, paymentId, amount) {
        document.getElementById('cancel_order_id').value = orderId;
        document.getElementById('cancel_payment_id').value = paymentId;
        document.getElementById('cancel_amount').value = amount;
        document.getElementById('cancelOrderModal').style.display = 'block';
    }

    function hideCancelModal() {
        document.getElementById('cancelOrderModal').style.display = 'none';
    }

    // Handle 'Other' reason selection
    document.getElementById('cancellation_reason').addEventListener('change', function() {
        const otherReasonGroup = document.getElementById('other_reason_group');
        otherReasonGroup.style.display = this.value === 'Other' ? 'block' : 'none';
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('cancelOrderModal');
        if (event.target == modal) {
            hideCancelModal();
        }
    }
    </script>

    <script>
    function hideReviewModal() {
        document.getElementById('reviewModal').style.display = 'none';
    }
    </script>
</body>
</html> 