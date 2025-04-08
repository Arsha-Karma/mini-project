<?php
session_start();
require_once 'dbconnect.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT SUM(quantity) as count FROM tbl_cart WHERE user_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'] ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode(['count' => $count]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['count' => 0]);
}
?> 
