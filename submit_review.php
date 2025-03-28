<?php
session_start();
require_once 'dbconnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $user_id = $_SESSION['user_id'];
        $product_id = $_POST['product_id'];
        $rating = $_POST['rating'];
        $comment = $_POST['comment'];

        // Validate data
        if (empty($product_id) || empty($rating) || empty($comment)) {
            $_SESSION['error'] = "All fields are required";
            header('Location: orders.php');
            exit();
        }

        // Insert review into database
        $insert_query = "INSERT INTO tbl_reviews (user_id, product_id, rating, comment) 
                        VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iiis", $user_id, $product_id, $rating, $comment);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Thank you! Your review has been submitted successfully.";
        } else {
            $_SESSION['error'] = "Failed to submit review. Please try again.";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred. Please try again.";
        error_log("Review submission error: " . $e->getMessage());
    }

    // Redirect back to orders page
    header('Location: orders.php');
    exit();
} else {
    // If accessed directly without POST data
    header('Location: orders.php');
    exit();
}
?>