<?php
session_start();
require_once 'dbconnect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['in_wishlist' => false]);
    exit;
}

$product_id = (int)$_GET['product_id'];
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT wishlist_id FROM tbl_wishlist WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode(['in_wishlist' => $result->num_rows > 0]); 