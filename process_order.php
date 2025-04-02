<?php

// Assuming this is where you process orders
// After an order status is updated to "completed" or payment is confirmed:

if ($order_status === 'Completed' || $payment_status === 'paid') {
    // Calculate and record admin profit
    calculateAndRecordAdminProfit($conn, $order_id);

    // Update admin_profits table
    $update_admin_profit = "INSERT INTO admin_profits (seller_id, amount, month_year)
        VALUES (?, ?, DATE_FORMAT(NOW(), '%Y-%m-01'))
        ON DUPLICATE KEY UPDATE amount = amount + ?";

    $stmt = $conn->prepare($update_admin_profit);
    $stmt->bind_param("idd", $seller_info['seller_id'], $admin_profit, $admin_profit);
    $stmt->execute();
} 