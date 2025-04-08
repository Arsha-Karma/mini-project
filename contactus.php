<?php
include 'dbconnect.php';
session_start();

// Initialize variables
$name = $email = $subject = $message = "";
$nameErr = $emailErr = $subjectErr = $messageErr = $success = "";

// Fetch user data from database when logged in
if (isset($_SESSION['logged_in']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Query to get user data from database
    $stmt = $conn->prepare("SELECT username, email FROM tbl_signup WHERE Signup_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $name = $user_data['username'];
        $email = $user_data['email'];
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if user is logged in
    if (!isset($_SESSION['logged_in'])) {
        // Redirect to login page if not logged in
        // Store the attempted form data in session to restore after login
        $_SESSION['contact_form_data'] = [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'subject' => $_POST['subject'] ?? '',
            'message' => $_POST['message'] ?? '',
            'redirect_after' => 'contactus.php'
        ];
        
        header("Location: login.php");
        exit();
    }
    
    // For logged-in users, use the database values for name and email
    if (isset($_SESSION['logged_in'])) {
        // No need to validate name and email as they're from database
        // Just use the values we fetched above
    } else {
        // This shouldn't happen due to the redirect above, but just in case
        $nameErr = "Please log in to submit this form";
        $emailErr = "Please log in to submit this form";
    }
    
    // Validate subject
    if (empty($_POST["subject"])) {
        $subjectErr = "Subject is required";
    } else {
        $subject = sanitize_input($conn, $_POST["subject"]);
    }
    
    // Validate message
    if (empty($_POST["message"])) {
        $messageErr = "Message is required";
    } else {
        $message = sanitize_input($conn, $_POST["message"]);
    }
    
    // If no errors, save to database
    if (empty($nameErr) && empty($emailErr) && empty($subjectErr) && empty($messageErr)) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'unread')");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        
        if ($stmt->execute()) {
            $success = "Your message has been sent successfully. We'll get back to you soon!";
            // Reset form fields after successful submission
            $subject = $message = "";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

// If user came from login page with stored form data, restore it
if (isset($_SESSION['contact_form_data']) && isset($_SESSION['logged_in'])) {
    // For subject and message only, name and email come from database
    $subject = $_SESSION['contact_form_data']['subject'];
    $message = $_SESSION['contact_form_data']['message'];
    
    // Remove the stored form data
    unset($_SESSION['contact_form_data']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Perfume Paradise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* General Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        /* Header/Navigation Styles */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background-color: #1a1a1a;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .logo img {
            height: 60px;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
        }
        
        .nav-links a {
            margin: 0 15px;
            text-decoration: none;
            color: #fff;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
            padding: 5px 0;
        }
        
        .nav-links a:hover, .nav-links a.active {
            color: #e8a87c;
        }
        
        .nav-links a.active::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            background: #e8a87c;
            bottom: 0;
            left: 0;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #1a1a1a;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            border-radius: 5px;
            padding: 10px 0;
            margin-top: 5px;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-content a {
            display: block;
            padding: 8px 15px;
            margin: 0;
        }
        
        /* Contact Us Section */
        .contact-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .contact-header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }
        
        .contact-header::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 3px;
            background: #e8a87c;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .contact-header h1 {
            font-size: 36px;
            color: #1a1a1a;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }
        
        .contact-header p {
            font-size: 18px;
            color: #666;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .contact-content {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .contact-info {
            flex: 1;
            min-width: 300px;
            background-color: #1a1a1a;
            color: #fff;
            padding: 40px 30px;
            position: relative;
        }
        
        .contact-info::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background: url('image/perfume-bg.jpg') center/cover no-repeat;
            opacity: 0.1;
            z-index: 0;
        }
        
        .contact-info > * {
            position: relative;
            z-index: 1;
        }
        
        .contact-info h2 {
            font-size: 28px;
            margin-bottom: 30px;
            color: #e8a87c;
            font-weight: 700;
        }
        
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        
        .contact-info-item i {
            font-size: 22px;
            color: #e8a87c;
            margin-right: 20px;
            margin-top: 3px;
            width: 22px;
            text-align: center;
        }
        
        .contact-info-item .content h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #e8a87c;
        }
        
        .contact-info-item .content p {
            color: #ddd;
            line-height: 1.8;
        }
        
        .contact-form {
            flex: 1.5;
            min-width: 350px;
            background-color: #fff;
            padding: 40px;
        }
        
        .contact-form h2 {
            font-size: 28px;
            margin-bottom: 30px;
            color: #1a1a1a;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s, box-shadow 0.3s;
            font-size: 16px;
            background-color: #f9f9f9;
        }
        
        .form-group input:focus, 
        .form-group textarea:focus {
            border-color: #e8a87c;
            outline: none;
            box-shadow: 0 0 0 3px rgba(232, 168, 124, 0.2);
        }
        
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .error-text {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #155724;
        }
        
        .submit-btn {
            background-color: #1a1a1a;
            color: #fff;
            border: none;
            padding: 14px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
            display: inline-block;
            font-weight: 600;
        }
        
        .submit-btn:hover {
            background-color: #e8a87c;
            transform: translateY(-2px);
        }
        
        /* Social Media Links */
        .social-links {
            margin-top: 30px;
        }
        
        .social-links h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #e8a87c;
        }
        
        .social-icons {
            display: flex;
            gap: 15px;
        }
        
        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: #fff;
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            background-color: #e8a87c;
            color: #fff;
            transform: translateY(-3px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            nav {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .nav-links {
                margin-top: 15px;
                flex-wrap: wrap;
            }
            
            .nav-links a {
                margin: 5px 10px;
            }
            
            .contact-content {
                flex-direction: column;
                box-shadow: none;
            }
            
            .contact-info, .contact-form {
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .contact-header h1 {
                font-size: 28px;
            }
            
            .contact-header p {
                font-size: 16px;
            }
        }
        
        /* Login/Signup Button Styles */
        .login-btn, .signup-btn {
            background-color: #e8a87c;
            color: #fff !important;
            padding: 8px 15px;
            border-radius: 4px;
            margin-left: 10px;
            transition: background-color 0.3s ease, transform 0.3s;
        }
        
        .login-btn:hover, .signup-btn:hover {
            background-color: #d6946a !important;
            transform: translateY(-2px);
        }
        
        .login-btn i, .signup-btn i {
            margin-right: 5px;
        }
        
        /* Animated Background for Form */
        .contact-form {
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(232, 168, 124, 0.03) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(232, 168, 124, 0.03) 0%, transparent 20%);
            animation: formBackground 15s ease-in-out infinite alternate;
        }
        
        @keyframes formBackground {
            0% {
                background-position: 0% 0%;
            }
            100% {
                background-position: 100% 100%;
            }
        }
        
        /* Page Transitions */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .contact-container {
            animation: fadeIn 0.6s ease-out forwards;
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo">
            <img src="image/logo.png" alt="Perfume Paradise Logo">
        </div>
        <div class="nav-links">
            <a href="index.php">Home</a> 
            <!-- Remove this entire dropdown div -->
            <!-- <div class="dropdown">
                <a href="#categories">Categories</a>
                <div class="dropdown-content">
                    <a href="men_perfumes.php">Men's Perfumes</a>
                    <a href="women_perfumes.php">Women's Perfumes</a>
                    <a href="unisex_perfumes.php">Unisex Fragrances</a>
                    <a href="gift_sets.php">Gift Sets</a>
                </div>
            </div> -->
            <a href="Aboutas.php">About Us</a>
            <a href="contactus.php" class="active">Contact Us</a>
            <?php if(isset($_SESSION['logged_in'])): ?>
                <a href="profile.php">My Account</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="signup.php" class="signup-btn"><i class="fas fa-user-plus"></i> Sign Up</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="contact-container">
        <div class="contact-header">
            <h1>Contact Us</h1>
            <p>Have questions about our products or services? We're here to help! Fill out the form below and we'll get back to you as soon as possible.</p>
        </div>
        
        <div class="contact-content">
            <div class="contact-info">
                <h2>Get in Touch</h2>
                
                <div class="contact-info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="content">
                        <h3>Our Location</h3>
                        <p>123 Perfume Street, Fragrance City, FC 12345</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <i class="fas fa-phone-alt"></i>
                    <div class="content">
                        <h3>Phone Number</h3>
                        <p>+1 (123) 456-7890</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <i class="fas fa-envelope"></i>
                    <div class="content">
                        <h3>Email Address</h3>
                        <p>info@perfumeparadise.com</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <i class="fas fa-clock"></i>
                    <div class="content">
                        <h3>Business Hours</h3>
                        <p>Monday - Friday: 9:00 AM - 6:00 PM<br>
                        Saturday: 10:00 AM - 4:00 PM<br>
                        Sunday: Closed</p>
                    </div>
                </div>
            </div>
            
            <div class="contact-form">
                <h2>Send Us a Message</h2>
                
                <?php if(!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if(!isset($_SESSION['logged_in'])): ?>
                <div class="login-required-message" style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffeeba;">
                    <i class="fas fa-exclamation-triangle"></i> Please <a href="login.php" style="color: #856404; text-decoration: underline; font-weight: bold;">login to your account</a> to fill out this contact form.
                </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" value="<?php echo $name; ?>" placeholder="Enter your full name" 
                               <?php if(isset($_SESSION['logged_in'])): ?>readonly="readonly" style="background-color: #f3f3f3; cursor: not-allowed;"
                               <?php else: ?>readonly="readonly" style="background-color: #eee; cursor: not-allowed;"<?php endif; ?>>
                        <?php if(!empty($nameErr)): ?>
                        <span class="error-text"><i class="fas fa-exclamation-circle"></i> <?php echo $nameErr; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Your Email</label>
                        <input type="email" id="email" name="email" value="<?php echo $email; ?>" placeholder="Enter your email address" 
                               <?php if(isset($_SESSION['logged_in'])): ?>readonly="readonly" style="background-color: #f3f3f3; cursor: not-allowed;"
                               <?php else: ?>readonly="readonly" style="background-color: #eee; cursor: not-allowed;"<?php endif; ?>>
                        <?php if(!empty($emailErr)): ?>
                        <span class="error-text"><i class="fas fa-exclamation-circle"></i> <?php echo $emailErr; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" value="<?php echo $subject; ?>" 
                               placeholder="What is your message about?"
                               <?php if(!isset($_SESSION['logged_in'])): ?>readonly="readonly" style="background-color: #eee; cursor: not-allowed;"<?php endif; ?>
                               onfocus="validateInput(this, 'subjectError')"
                               onchange="validateInput(this, 'subjectError')"
                               pattern="^[a-zA-Z\s\.,!?-]+$">
                        <span id="subjectError" class="error-text" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> Only letters, spaces, and basic punctuation are allowed
                        </span>
                        <?php if(!empty($subjectErr)): ?>
                        <span class="error-text"><i class="fas fa-exclamation-circle"></i> <?php echo $subjectErr; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Your Message</label>
                        <textarea id="message" name="message" 
                                  placeholder="Type your message here..."
                                  <?php if(!isset($_SESSION['logged_in'])): ?>readonly="readonly" style="background-color: #eee; cursor: not-allowed; resize: none;"<?php endif; ?>
                                  onfocus="validateInput(this, 'messageError')"
                                  onchange="validateInput(this, 'messageError')"
                                  ><?php echo $message; ?></textarea>
                        <span id="messageError" class="error-text" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> Only letters, spaces, and basic punctuation are allowed
                        </span>
                        <?php if(!empty($messageErr)): ?>
                        <span class="error-text"><i class="fas fa-exclamation-circle"></i> <?php echo $messageErr; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="submit-btn" <?php if(!isset($_SESSION['logged_in'])): ?>style="opacity: 0.7;"<?php endif; ?> onclick="return validateForm()">
                        <i class="fas fa-paper-plane"></i> <?php echo isset($_SESSION['logged_in']) ? 'Send Message' : 'Login to Send Message'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    let subjectValid = true;
    let messageValid = true;

    function validateInput(element, errorId) {
        // Only if user is logged in
        if (!<?php echo isset($_SESSION['logged_in']) ? 'true' : 'false' ?>) {
            return true;
        }

        const errorElement = document.getElementById(errorId);
        const pattern = /^[a-zA-Z\s\.,!?-]+$/;
        const value = element.value;

        if (value && !pattern.test(value)) {
            errorElement.style.display = 'block';
            element.style.borderColor = '#e74c3c';
            if (errorId === 'subjectError') {
                subjectValid = false;
            } else if (errorId === 'messageError') {
                messageValid = false;
            }
            return false;
        } else {
            errorElement.style.display = 'none';
            element.style.borderColor = value ? '#2ecc71' : '#ddd';
            if (errorId === 'subjectError') {
                subjectValid = true;
            } else if (errorId === 'messageError') {
                messageValid = true;
            }
            return true;
        }
    }

    function validateForm() {
        if (!<?php echo isset($_SESSION['logged_in']) ? 'true' : 'false' ?>) {
            return false;
        }

        // Validate both fields before submission
        const subjectResult = validateInput(document.getElementById('subject'), 'subjectError');
        const messageResult = validateInput(document.getElementById('message'), 'messageError');

        return subjectValid && messageValid;
    }
    </script>
</body>
</html>