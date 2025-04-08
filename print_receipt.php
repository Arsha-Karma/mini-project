<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'];

// Fetch order details with payment information
$order_query = "SELECT o.*, p.payment_id, p.payment_method, p.payment_status, u.username, u.email, u.Phoneno
                FROM orders_table o
                LEFT JOIN payment_table p ON o.order_id = p.order_id
                LEFT JOIN tbl_signup u ON o.Signup_id = u.Signup_id
                WHERE o.order_id = ? AND o.Signup_id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("si", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch shipping address from shipping_addresses table
$address_query = "SELECT sa.*, s.username, s.Phoneno 
                 FROM shipping_addresses sa
                 JOIN tbl_signup s ON sa.Signup_id = s.Signup_id
                 WHERE sa.Signup_id = ? 
                 ORDER BY sa.created_at DESC LIMIT 1";
$stmt = $conn->prepare($address_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$address_result = $stmt->get_result();

$shipping_address = '';
if ($address_result->num_rows > 0) {
    $address = $address_result->fetch_assoc();
    // Format the address like in the image
    $shipping_address = htmlspecialchars($address['username']) . "\n" .
                       htmlspecialchars($address['address_line1']) . "\n" .
                       htmlspecialchars($address['city']) . " - " . 
                       htmlspecialchars($address['postal_code']) . "\n" .
                       "Phone: " . htmlspecialchars($address['Phoneno']);
    
    // Also update the order's shipping_address field for consistency
    $order['shipping_address'] = $shipping_address;
}

// Fetch order items
$items_query = "SELECT o.*, p.name as product_name, p.price, p.image_path, o.gift_wrap_charge, o.gift_option, o.gift_wrap_type 
                FROM orders_table o
                JOIN tbl_product p ON o.product_id = p.product_id 
                WHERE o.order_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("s", $order_id);
$stmt->execute();
$items = $stmt->get_result();

// Calculate totals
$subtotal = 0;
$items_array = array();
$gift_wrap_charge = 0;

while ($item = $items->fetch_assoc()) {
    $items_array[] = $item;
    // Calculate item total without gift wrap
    $item_total = $item['price'] * $item['quantity'];
    $subtotal += $item_total;
    
    // Add gift wrap charge if applicable
    if ($item['gift_option'] && !empty($item['gift_wrap_charge'])) {
        $gift_wrap_charge = floatval($item['gift_wrap_charge']);
    }
}

// Calculate shipping and tax
$shipping = $subtotal >= 1000 ? 0 : 50;
$tax = $subtotal * 0.05; // 5% tax
$total_amount = $subtotal + $shipping + $tax + $gift_wrap_charge;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt - #<?php echo htmlspecialchars($order_id); ?></title>
    <style>
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .store-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .info-group {
            flex: 1;
            margin: 0 10px;
        }

        .info-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .totals {
            float: right;
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .total-row.final {
            font-weight: bold;
            font-size: 1.1em;
            border-top: 2px solid #ddd;
            margin-top: 10px;
            padding-top: 10px;
        }

        .shipping-address {
            margin-bottom: 30px;
        }

        .print-button {
            background-color: #2874f0;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .print-button:hover {
            background-color: #1c5ac7;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            color: #666;
        }

        .product-image {
            width: 100px;
            height: 100px;
            border-radius: 4px;
            overflow: hidden;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 5px;
            background-color: #fff;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Print-specific styles for images */
        @media print {
            .product-image {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .product-image img {
                max-width: 100%;
                height: auto;
            }
        }

        /* Ensure table cells with images have enough width */
        th:first-child,
        td:first-child {
            width: 120px;
            min-width: 120px;
        }
    </style>
</head>
<body>
    <button onclick="window.print();" class="print-button no-print">
        Print Receipt
    </button>

    <div class="receipt-header">
        <div class="store-name">Perfume Paradise</div>
        <div>Order Receipt</div>
    </div>

    <div class="receipt-info">
        <div class="info-group">
            <div class="info-label">Order Details</div>
            <div>Order ID: #<?php echo htmlspecialchars($order_id); ?></div>
            <div>Date: <?php echo date('d M Y', strtotime($order['created_at'])); ?></div>
            <div>Payment Method: <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></div>
            <div>Payment Status: 
                <span class="status-badge status-<?php echo strtolower($order['payment_status']); ?>">
                    <?php echo ucfirst($order['payment_status']); ?>
                </span>
            </div>
        </div>
        <div class="info-group">
            <div class="info-label">Customer Details</div>
            <div>Name: <?php echo htmlspecialchars($order['username']); ?></div>
            <div>Email: <?php echo htmlspecialchars($order['email']); ?></div>
            <div>Phone: <?php echo htmlspecialchars($order['Phoneno']); ?></div>
        </div>
    </div>

    <?php if (!empty($order['shipping_address'])): ?>
    <div class="shipping-address">
        <div class="info-label">Shipping Address</div>
        <div><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Product Image</th>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items_array as $item): ?>
            <tr>
                <td>
                    <div class="product-image">
                        <?php 
                        $image_path = $item['image_path'];
                        if (!empty($image_path)) {
                            $image_path = ltrim($image_path, '/');
                            $image_path = str_replace('uploads/products/', '', $image_path);
                            $image_path = "uploads/products/" . $image_path;
                        } else {
                            $image_path = "images/default-product.jpg";
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                             onerror="this.src='images/default-product.jpg'"
                             style="width: 100px; height: 100px; object-fit: cover;">
                    </div>
                </td>
                <td>
                    <?php echo htmlspecialchars($item['product_name']); ?>
                    <?php if ($item['gift_option']): ?>
                        <br><small>(Gift Wrapped - <?php echo ucfirst($item['gift_wrap_type']); ?>)</small>
                    <?php endif; ?>
                </td>
                <td>₹<?php echo number_format($item['price'], 2); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>₹<?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="total-row">
            <span>Shipping:</span>
            <span><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></span>
        </div>
        <div class="total-row">
            <span>Tax (5%):</span>
            <span>₹<?php echo number_format($tax, 2); ?></span>
        </div>
        <?php if ($gift_wrap_charge > 0): ?>
        <div class="total-row">
            <span>Gift Wrap Charge:</span>
            <span>₹<?php echo number_format($gift_wrap_charge, 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row final">
            <span>Total Amount:</span>
            <span>₹<?php echo number_format($total_amount, 2); ?></span>
        </div>
    </div>

    <div style="clear: both;"></div>

    <div class="footer">
        <p>Thank you for shopping with Perfume Paradise!</p>
        <p>For any queries, please contact our customer support.</p>
        <p>This is a computer-generated receipt and does not require a signature.</p>
    </div>

    <script>
        // Automatically open print dialog when page loads
        window.onload = function() {
            // Small delay to ensure styles are properly loaded
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html> 