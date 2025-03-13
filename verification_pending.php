<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verification Pending - Perfume Paradise</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }

        .pending-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 90%;
        }

        h1 {
            color: #000000;
            margin-bottom: 20px;
        }

        p {
            color: #666666;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .status-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .btn-home {
            background: #000000;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .btn-home:hover {
            background: #1a1a1a;
        }
    </style>
</head>
<body>
    <div class="pending-container">
        <div class="status-icon">⏳</div>
        <h1>Verification Pending</h1>
        <p>Your verification documents have been submitted successfully and are currently under review. You will be notified once the verification process is complete.</p>
        <a href="index.php" class="btn-home">Return to Home</a>
    </div>
</body>
</html> 