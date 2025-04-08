<?php
session_start();

// Redirect if not logged in or not an admin
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
} elseif ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Database connection
require_once('dbconnect.php');
$database_name = "perfumes";
mysqli_select_db($conn, $database_name);

// Check if type parameter exists
if (!isset($_GET['type'])) {
    die("Invalid request: missing type parameter");
}

$type = $_GET['type'];
$filename = ''; 
$data = [];
$headers = [];

// Get current date for filename
$date = date('Y-m-d');

// Process based on report type
switch ($type) {
    case 'users':
        $filename = "user_report_$date.csv";
        $headers = ['Username', 'Email', 'Phone Number', 'Status', 'Registration Date'];
        
        $query = "SELECT 
            s.username,
            s.email,
            s.Phoneno,
            s.verification_status,
            s.created_at
            FROM tbl_users u
            JOIN tbl_signup s ON u.Signup_id = s.Signup_id
            WHERE s.role_type = 'user'
            ORDER BY s.created_at DESC";
        
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    $row['username'],
                    $row['email'],
                    $row['Phoneno'],
                    $row['verification_status'],
                    $row['created_at']
                ];
            }
        }
        break;
        
    case 'sellers':
        $filename = "seller_report_$date.csv";
        $headers = ['Seller Name', 'Email', 'Phone Number', 'Status', 'Registration Date'];
        
        $query = "SELECT 
            sl.Sellername,
            s.email,
            s.Phoneno,
            s.verification_status,
            s.created_at
            FROM tbl_seller sl
            JOIN tbl_signup s ON sl.Signup_id = s.Signup_id
            WHERE s.role_type = 'seller'
            ORDER BY s.created_at DESC";
        
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    $row['Sellername'],
                    $row['email'],
                    $row['Phoneno'],
                    $row['verification_status'],
                    $row['created_at']
                ];
            }
        }
        break;
        
    case 'orders':
        $filename = "order_report_$date.csv";
        $headers = ['Order ID', 'Product', 'Customer', 'Email', 'Shipping Address', 'Quantity', 'Amount', 'Status', 'Date'];
        
        $query = "SELECT 
            o.*,
            s.username as customer_name,
            s.email as customer_email,
            p.name as product_name,
            pt.payment_status
            FROM orders_table o
            LEFT JOIN tbl_signup s ON o.Signup_id = s.Signup_id
            LEFT JOIN tbl_product p ON o.product_id = p.product_id
            LEFT JOIN payment_table pt ON o.payment_id = pt.payment_id
            ORDER BY o.created_at DESC";
        
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $status = $row['order_status'] === 'Cancelled' ? 'Cancelled' : 
                          (($row['payment_status'] ?? '') === 'paid' ? 'Completed' : $row['order_status']);
                
                $data[] = [
                    $row['order_id'],
                    $row['product_name'],
                    $row['customer_name'],
                    $row['customer_email'],
                    str_replace("\n", " ", $row['shipping_address']), // Remove newlines for CSV
                    $row['quantity'],
                    $row['total_amount'],
                    $status,
                    $row['created_at']
                ];
            }
        }
        break;
        
    default:
        die("Invalid report type");
}

// If no data found
if (empty($data)) {
    die("No data available for the selected report type");
}

// Set headers to force download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output CSV
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, $headers);

// Write data rows
foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?> 