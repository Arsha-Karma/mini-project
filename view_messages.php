<?php
include 'dbconnect.php';
session_start();

// Update the session check to match admindashboard.php
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
} elseif ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Mark message as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $message_id = $_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    header("Location: view_messages.php");
    exit();
}

// Mark message as replied
if (isset($_GET['mark_replied']) && is_numeric($_GET['mark_replied'])) {
    $message_id = $_GET['mark_replied'];
    $stmt = $conn->prepare("UPDATE contact_messages SET is_replied = 1, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    header("Location: view_messages.php");
    exit();
}

// Mark message as deleted (frontend only)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = $_GET['delete'];
    // Change from DELETE to UPDATE - add is_deleted flag instead of removing the record
    $stmt = $conn->prepare("UPDATE contact_messages SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    header("Location: view_messages.php");
    exit();
}

// Get filter from URL, if any
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Prepare query based on filter
if ($filter === 'unread') {
    // Only show unread messages - now with JOIN to get role information
    $sql = "SELECT cm.*, ts.role_type 
            FROM contact_messages cm
            LEFT JOIN tbl_signup ts ON cm.email = ts.email
            WHERE cm.status = 'unread' AND (cm.is_deleted = 0 OR cm.is_deleted IS NULL) 
            AND cm.is_replied = 0
            ORDER BY cm.created_at DESC";
} else {
    // Show ALL messages except replied ones - now with JOIN to get role information
    $sql = "SELECT cm.*, ts.role_type 
            FROM contact_messages cm
            LEFT JOIN tbl_signup ts ON cm.email = ts.email
            WHERE (cm.is_deleted = 0 OR cm.is_deleted IS NULL) 
            AND cm.is_replied = 0
            ORDER BY cm.created_at DESC";
}

$result = $conn->query($sql);
$messages = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}

// Count unread messages - only count non-deleted ones
$unread_query = "SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread' AND (is_deleted = 0 OR is_deleted IS NULL)";
$unread_result = $conn->query($unread_query);
$unread_count = $unread_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Messages - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom styles -->
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7fc;
            color: #333;
        }
        
        /* Add header styles */
        .header {
            background-color: #fff;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #2d2a4b;
            margin: 0;
        }
        
        /* Sidebar styles */
        .sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1a1a1a;
            padding-top: 20px;
            color: white;
            z-index: 1;
        }

        .sidebar h2 {
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            border-bottom: 1px solid #333;
            background-color: #000000;
        }

        .sidebar a {
            padding: 15px 25px;
            text-decoration: none;
            font-size: 16px;
            color: #fff;
            display: block;
            transition: 0.3s;
        }

        .sidebar a:hover {
            background-color: #333;
            color: #fff;
        }

        .sidebar a.active {
            background-color: #333;
            border-left: 4px solid #fff;
        }

        .sidebar i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        /* Message styles */
        .message-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        
        .message-card:hover {
            transform: translateY(-5px);
        }
        
        .unread {
            border-left: 4px solid #e8a87c;
            background-color: #f8f9fa;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
        }
        
        .timestamp {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .badge-unread {
            background-color: #e8a87c;
            color: white;
        }
        
        /* Main content area */
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        /* Responsive design */
        @media screen and (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar a {
                float: left;
                padding: 15px;
            }
            
            .sidebar h2 {
                display: none;
            }
        }

        @media screen and (max-width: 480px) {
            .sidebar a {
                text-align: center;
                float: none;
            }
        }
        
        .notification-badge {
            background-color: #ff4444;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            min-width: 18px;
            text-align: center;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4>&nbsp;&nbsp;&nbsp;&nbsp;Perfume Paradise</h4>
        <a href="admindashboard.php">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        
       
        <a href="manage-categories.php">
            <i class="fas fa-tags"></i> Manage Categories
        </a>
        <a href="customer_reviews.php">
            <i class="fas fa-star"></i> Customer Reviews
        </a>
        <a href="index.php">
            <i class="fas fa-home"></i> Home
        </a>
        <a href="view_messages.php" class="active">
            <i class="fas fa-headset"></i> Customer Support
            <?php if ($unread_count > 0): ?>
                <span class="notification-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
    
    <!-- Main content -->
    <div class="main-content">
        <div class="header">
            <h1 style="color: #000000;">Welcome Admin!</h1>
        </div>
        
        <div class="container mt-4">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h2>Customer Messages</h2>
                    <p>Manage customer inquiries and support requests</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="view_messages.php" class="btn <?php echo !isset($_GET['filter']) ? 'btn-secondary' : 'btn-outline-secondary'; ?> me-2">
                        All Messages
                    </a>
                    <a href="view_messages.php?filter=unread" class="btn <?php echo $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Unread Messages <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                    </a>
                </div>
            </div>
            
            <?php if (empty($messages)): ?>
            <div class="alert alert-info">
                No messages found.
            </div>
            <?php else: ?>
            
            <div class="row">
                <?php foreach ($messages as $message): ?>
                <div class="col-md-6">
                    <div class="card message-card <?php echo $message['status'] === 'unread' ? 'unread' : ''; ?>">
                        <div class="card-header message-header">
                            <div>
                                <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                                <?php if (isset($message['role_type'])): ?>
                                    <span class="badge bg-secondary ms-2"><?php echo ucfirst(htmlspecialchars($message['role_type'])); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2">Guest</span>
                                <?php endif; ?>
                                <?php if ($message['status'] === 'unread'): ?>
                                    <span class="badge badge-unread ms-2">New</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-actions">
                                <?php if ($message['status'] === 'unread'): ?>
                                <a href="view_messages.php?mark_read=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-success" title="Mark as Read">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="view_messages.php?delete=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                   title="Delete" onclick="return confirm('Are you sure you want to delete this message?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($message['subject']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($message['email']); ?></h6>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                            <p class="timestamp">
                                <i class="far fa-clock"></i> 
                                <?php echo date('F j, Y, g:i a', strtotime($message['created_at'])); ?>
                            </p>
                            
                            <div class="d-grid gap-2">
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo htmlspecialchars($message['email']); ?>&su=Re: <?php echo htmlspecialchars($message['subject']); ?>" 
                                   class="btn btn-primary reply-btn" target="_blank" data-message-id="<?php echo $message['id']; ?>">
                                    <i class="fas fa-reply"></i> Reply via Email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript to handle email replies -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all reply buttons
            const replyButtons = document.querySelectorAll('.reply-btn');
            
            // Add click event listener to each reply button
            replyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get the message ID from data attribute
                    const messageId = this.getAttribute('data-message-id');
                    
                    // Make an AJAX request to mark the message as replied
                    fetch(`view_messages.php?mark_replied=${messageId}`, {
                        method: 'GET'
                    })
                    .then(response => {
                        if (response.ok) {
                            // Remove the message card from the UI after a small delay
                            // to allow the email client to open first
                            setTimeout(() => {
                                const cardElement = this.closest('.col-md-6');
                                if (cardElement) {
                                    cardElement.style.opacity = '0';
                                    cardElement.style.transition = 'opacity 0.5s ease';
                                    
                                    setTimeout(() => {
                                        cardElement.remove();
                                        
                                        // Check if there are no more messages
                                        const remainingMessages = document.querySelectorAll('.message-card');
                                        if (remainingMessages.length === 0) {
                                            const rowElement = document.querySelector('.row');
                                            if (rowElement) {
                                                rowElement.innerHTML = '<div class="col-12"><div class="alert alert-info">No messages found.</div></div>';
                                            }
                                        }
                                    }, 500);
                                }
                            }, 500);
                        }
                    })
                    .catch(error => {
                        console.error('Error marking message as replied:', error);
                    });
                });
            });
        });
    </script>
</body>
</html>