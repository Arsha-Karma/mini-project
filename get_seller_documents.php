<?php
session_start();
require_once('dbconnect.php');

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['seller_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Seller ID not provided']);
    exit();
}

$seller_id = intval($_GET['seller_id']);

// Fetch seller details and documents
$query = "SELECT 
    s.seller_id,
    s.Sellername,
    sg.username,
    sg.email,
    sg.phoneno,
    svd.id_proof_front,
    svd.id_proof_back,
    svd.business_proof,
    svd.address_proof
FROM tbl_seller s
LEFT JOIN tbl_signup sg ON s.Signup_id = sg.Signup_id
LEFT JOIN seller_verification_docs svd ON s.seller_id = svd.seller_id
WHERE s.seller_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Seller not found']);
    exit();
}

$seller = $result->fetch_assoc();

// Prepare response data
$response = [
    'seller' => [
        'username' => $seller['username'] ?? $seller['Sellername'],
        'email' => $seller['email'],
        'phoneno' => $seller['phoneno']
    ],
    'documents' => [
        'id_proof_front' => $seller['id_proof_front'],
        'id_proof_back' => $seller['id_proof_back'],
        'business_proof' => $seller['business_proof'],
        'address_proof' => $seller['address_proof']
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
?> 