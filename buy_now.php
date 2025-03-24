<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if product_id is provided
if (!isset($_POST['product_id'])) {
    header('Location: productslist.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];

// Verify product exists and is in stock
$check_product = $conn->prepare("
    SELECT product_id, Stock_quantity 
    FROM tbl_product 
    WHERE product_id = ? AND deleted = 0
");
$check_product->bind_param("i", $product_id);
$check_product->execute();
$result = $check_product->get_result();
$product = $result->fetch_assoc();

// If product doesn't exist or is out of stock, redirect to products list
if (!$product || $product['Stock_quantity'] <= 0) {
    header('Location: productslist.php?error=product_unavailable');
    exit();
}

// Redirect to checkout with buy now parameters
header("Location: checkout.php?buy_now=1&product_id=" . $product_id);
exit(); 