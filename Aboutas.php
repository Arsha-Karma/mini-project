<?php
session_start();

// Example: Check if a user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfume Paradise - Our Story</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: #000;
            color: #fff;
            padding-top: 80px; /* Adjust this value based on your header height */
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 50px;
            background-color: #000;
            border-bottom: 1px solid #222;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        .logo {
            flex: 0 0 200px;
        }
        
        .logo img {
            width: 200px;
            height: auto;
            display: block;
        }
        
        .nav-menu {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav-menu a {
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s ease;
        }
        
        .nav-menu a:hover {
            color: #e8a87c;
        }
        
        .nav-icons {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-icons a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s ease;
        }
        
        .nav-icons a:hover {
            color: #e8a87c;
        }
        
        .cart-icon {
            position: relative;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e8a87c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
            min-width: 18px;
            text-align: center;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: #111;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 4px;
            padding: 8px 0;
            z-index: 1001;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-content a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .dropdown-content a:hover {
            background-color: #222;
            color: #e8a87c;
        }
        
        .story-section {
            padding: 60px 20px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .story-title {
            font-size: 48px;
            margin-bottom: 40px;
            font-weight: normal;
        }

        .story-content p {
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .dot {
            height: 8px;
            width: 8px;
            background-color: #e0a87e;
            border-radius: 50%;
            display: inline-block;
            margin-bottom: 30px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
            }

            .logo {
                flex: 0 0 150px;
            }

            .logo img {
                width: 150px;
            }

            .nav-menu {
                gap: 15px;
            }

            .nav-menu a, .nav-icons a {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
        <img src="image/logo.png" alt="Perfume Paradise Logo" style="width: 500px; height: auto;">

        </div>
        <nav class="nav-menu">
            <a href="index.php">Home</a>
            
            <a href="Aboutas.php">Our Story</a>
        </nav>
        
    </header>
    
    <section class="story-section">
        <div class="story-content">
            <div class="dot"></div>
            <h2 class="story-title">Our Little Story</h2>
            
            <p>This perfume description highlights its essence by detailing its top, heart, and base notes.</p>
            
            <p>The top notes offer a fresh, fleeting introduction, like citrus or fruity scents. Heart notes, often floral or spicy, form the core that defines the fragrance's character. Finally, the base notes, with warm and lasting elements like woods or musk, provide depth and longevity.</p>
            
            <p>These layers create a sensory and emotional journey that captures the perfume's unique personality. Together, these layers evoke emotions and set the mood, whether fresh and playful, romantic and elegant, or bold and mysterious.</p>
            
            <p>Each perfume tells a unique story, designed to captivate the senses and leave a lasting impression.</p>
        </div>
    </section>
</body>
</html>
