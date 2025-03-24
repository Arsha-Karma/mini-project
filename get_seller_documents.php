<?php
require_once 'dbconnect.php';

if (isset($_GET['seller_id'])) {
    $seller_id = intval($_GET['seller_id']);
    
    $query = "SELECT * FROM seller_verification_docs WHERE seller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($docs = $result->fetch_assoc()) {
        echo json_encode([
            'id_proof_front' => $docs['id_proof_front'],
            'id_proof_back' => $docs['id_proof_back'],
            'business_proof' => $docs['business_proof'],
            'address_proof' => $docs['address_proof']
        ]);
    } else {
        echo json_encode(['error' => 'No documents found']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?> 