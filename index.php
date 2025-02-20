<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in'])) { 
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta charset="UTF-8">
    <title>Perfume Paradise</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #000;
            color: #fff;
        }

        nav {
            background-color: #000;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid #222;
        }

        .logo {
            color: #e8a87c;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            position: relative;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #111;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1001;
            border-radius: 4px;
            margin-top: 0.5rem;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-content a {
            color: white;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
        }

        .dropdown-content a:hover {
            background-color: #222;
            color: #e8a87c;
        }

        .nav-icons {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-icons a {
            color: white;
            text-decoration: none;
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
        }

        .icon {
            width: 20px;
            height: 20px;
            display: inline-block;
            vertical-align: middle;
        }

        .hero {
    height: 100vh;
    background: #000;
    position: relative;
    overflow: hidden;
}

.slider {
    width: 100%;
    height: 100%;
    position: relative;
}

.slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1s ease-in-out;
}

.slide.active {
    opacity: 1;
}

.slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.slider-nav {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 10;
}

.slider-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.slider-dot.active {
    background: #e8a87c;
}

.slider-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    color: white;
    font-size: 24px;
}

.slider-prev {
    left: 20px;
}

.slider-next {
    right: 20px;
}
        .hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .story-section {
      padding: 4rem 2rem;
      display: flex;
      flex-direction: column;
      gap: 4rem;
      background-color: #000;
      position: relative;
      overflow: hidden;
      min-height: 100vh;
      align-items: center;
    }
/* Base styles */
body {
    margin: 0;
    padding: 0;
    background-color: #000000;
}

/* Container styles */
.perfume-container {
    width: 100%;
    padding: 20px;
    box-sizing: border-box;
    background-color: #000000;
}

/* Grid layout */
.perfume-grid {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 2px;
    max-width: 1200px;
    margin: 0 auto;
}

/* Card styles */
.perfume-card {
    flex: 0 1 400px;
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    background-color: #000000;
}

/* Image styles */
.perfume-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

/* Accent frame styles */
.accent-frame {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
}

.accent-line {
    position: absolute;
    background: #0066ff;
    box-shadow: 0 0 15px #0066ff;
    opacity: 0;
    transition: opacity 0.5s ease;
}

.accent-line.top {
    height: 2px;
    width: 40%;
    top: 0;
    right: 0;
}

.accent-line.right {
    width: 2px;
    height: 40%;
    top: 0;
    right: 0;
}

.accent-line.bottom {
    height: 2px;
    width: 40%;
    bottom: 0;
    left: 0;
}

.accent-line.left {
    width: 2px;
    height: 40%;
    bottom: 0;
    left: 0;
}

/* Hover effects */
.perfume-card:hover .accent-line {
    opacity: 1;
}

.perfume-card:hover .perfume-image {
    transform: scale(1.05);
}

/* Info overlay styles */
.perfume-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 2rem;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
}

.perfume-card:hover .perfume-info {
    opacity: 1;
    transform: translateY(0);
}

.perfume-name {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    font-family: 'Times New Roman', serif;
}

.perfume-description {
    font-size: 0.9rem;
    color: #ccc;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .perfume-grid {
        flex-direction: column;
        align-items: center;
    }
    
    .perfume-card {
        width: 100%;
        max-width: 300px;
    }
}

    /* New styles for story content */
    .story-content {
      max-width: 800px;
      text-align: center;
      color: white;
      margin-top: 4rem;
    }

    .story-title {
      font-size: 3rem;
      margin-bottom: 2rem;
      font-family: 'Times New Roman', serif;
      color: white;
    }

    .story-content p {
      line-height: 1.6;
      color: #ccc;
      margin-bottom: 1rem;
      font-size: 1.1rem;
    }

    .button {
      display: inline-block;
      padding: 12px 30px;
      background-color: #e8a87c;
      color: white;
      text-decoration: none;
      border-radius: 25px;
      transition: background-color 0.3s ease;
      margin-top: 2rem;
    }

    .button:hover {
      background-color: #d89668;
    }
    .products-section {
            padding: 4rem 2rem;
        }
        .section-title {
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 2rem;
            font-family: 'Times New Roman', serif;
        }


        .product-filters {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .filter-btn {
            padding: 0.5rem 1.5rem;
            border: none;
            background: transparent;
            color: white;
            cursor: pointer;
        }

        .filter-btn.active {
            background: #e8a87c;
            border-radius: 20px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            padding: 2rem 0;
        }

        .product-card {
            background: #111;
            padding: 1rem;
            position: relative;
        }

        .product-card img {
            width: 100%;
            height: auto;
        }

        .product-discount {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: #e8a87c;
        }

        .blog-section {
            padding: 4rem 2rem;
        }

        .blog-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .blog-date {
            background: #333;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .service-section {
            display: flex;
            justify-content: space-between;
            padding: 4rem 2rem;
            background-color: #000;
            color: #fff;
            text-align: center;
        }
        
        .service-item {
            flex: 1;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        
        .service-item h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        
        .service-item p {
            color: #666;
            line-height: 1.5;
        }

        .service-icon {
            width: 50px;
            height: 50px;
            margin-bottom: 1rem;
        }

        .service-icon svg {
            width: 100%;
            height: 100%;
            fill: #e8a87c;
        }

        footer {
            background: #111;
            padding: 4rem 2rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .footer-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: #666;
            text-decoration: none;
        }

        .button {
            background: #e8a87c;
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .search-container {
    position: relative;
    display: flex;
    align-items: center;
}

.search-button {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 10px;
}

.search-button img {
    width: 18px;
    height: 18px;
}

.search-bar {
    position: absolute;
    top: 0;
    right: 100%;
    width: 0;
    visibility: hidden;
    opacity: 0;
    transition: all 0.3s ease;
    background: #333;
    border: none;
    padding: 10px;
    color: white;
}

.search-bar.active {
    width: 300px;
    visibility: visible;
    opacity: 1;
    margin-right: 10px;
}

.search-bar::placeholder {
    color: #999;
}

.search-bar:focus {
    outline: none;
} 
.profile-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e8a87c;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: bold;
    cursor: pointer;
}

.dropdown-menu {
    display: none;
    position: absolute;
    top: 80px;
    right: 0;
    left:auto;
    background-color: #111;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    z-index: 1000;
}

.dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 10px; /* Space between icon and text */
    padding: 10px 20px;
    color: white;
    text-decoration: none;
    transition: background-color 0.3s;
}

.dropdown-menu a:hover {
    background-color: #222;
    color: #e8a87c;
}

.dropdown-menu i {
    width: 20px; /* Fixed width for icons */
    text-align: center;
}
.profile-menu:hover .dropdown-menu {
    display: block;
}

    </style>
</head>
<body>
<nav>
    <div class="logo"><img src="image/logo.png" alt="Perfume Paradise Logo"></div>

    <div class="nav-links">
        <a href="#perfume">Home</a>
        
        <div class="dropdown">
            <a href="#categories">Categories</a>
            <div class="dropdown-content">
                <a href="mens.php">Men's Perfumes</a>
                <a href="women.php">Women's Perfumes</a>
                <a href="#unisex-perfumes">Unisex Perfumes</a>
                <a href="#luxury-perfumes">Luxury Perfumes</a>
                <a href="#niche-perfumes">Niche Perfumes</a>
                <a href="#body-mists">Body Mists & Colognes</a>
                <a href="#skincare-fragrances">Skincare Fragrances</a>
                <a href="#perfume-oils">Perfume Oils</a>
            </div>
        </div>
        <a href="#body-spray">Brands</a>
        <a href="#categories">About As</a>
            </div>
    </div>

    <div class="nav-icons">
        <div class="search-container">
            <input type="text" class="search-bar" placeholder="Search Products Here">
            <button class="search-button">
                <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PGNpcmNsZSBjeD0iMTEiIGN5PSIxMSIgcj0iOCIvPjxsaW5lIHgxPSIyMSIgeTE9IjIxIiB4Mj0iMTYuNjUiIHkyPSIxNi42NSIvPjwvc3ZnPg==" alt="Search">
            </button>
        </div>
        <div class="cart-icon">
            <a href="cart.php">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </a>
            
        </div>

        <?php if (isset($_SESSION['username'])): ?>
            <div class="profile-menu">
            <div class="profile-circle" onclick="toggleDropdown()">
                    <div class="profile-circle"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                </div>
                <div class="dropdown-menu">
    <a href="profile.php">
        <i class="fas fa-user"></i> Profile
    </a>
    <a href="settings.php">
        <i class="fas fa-cog"></i> Settings
    </a>
    <a href="orders.php">
        <i class="fas fa-shopping-bag"></i> Orders
    </a>
    <a href="wishlist.php">
        <i class="fas fa-heart"></i> Wishlist
    </a>
    <a href="logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
            </div>
        <?php else: ?>
            <a href="login.php" class="profile-icon">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                </svg>
                
            </a>
        <?php endif; ?>

        
    </div>
</nav>


    <section class="hero">
    <div class="slider">
        <div class="slide active">
            <img src="image/image1.jpg" alt="Luxury perfume bottles with dramatic lighting" />
        </div>
        <div class="slide">
            <img src="image/image2.jpg" alt="Luxury perfume bottles with dramatic lighting" />
        </div>
    </div>
    <div class="slider-nav"></div>
    <div class="slider-arrow slider-prev">‹</div>
    <div class="slider-arrow slider-next">›</div>
</section>

<section class="story-section">
    <div class="story-content">
      <h2 class="story-title">Our Little Story</h2>
      <p>This perfume description highlights its essence by detailing its top, heart, and base notes.</p>
      <p>The top notes offer a fresh, fleeting introduction, like citrus or fruity scents.heart notes,</p>
      <p>often floral or spicy, form the core that defines the fragrance's character.Finally,the</p>
      <p>base notes, with warm and lasting elements like woods or musk, provide depth and longevity.these layers create a sensory and emotional journey that captures the</p>
      <p>perfume's unique personality. Together, these layers evoke emotions and set the mood, whether fresh and playful, romantic and elegant, or bold and mysterious.Each perfume</p>
      <p>tells a unique story, designed to captivate the senses and leave a lasting impression.</p>
      <br><br><br>
      <a href="#" class="button">Read More</a>
    </div>

    <div class="perfume-container">
        <div class="perfume-grid">
            <div class="perfume-card">
                <img src="image/image3.png" alt="Black Opium perfume" class="perfume-image">
                <div class="perfume-info">
                    <h3 class="perfume-name">Black Opium</h3>
                    <p class="perfume-description">A captivating blend of black coffee, white flowers, and vanilla.</p>
                </div>
            </div>
            
            <div class="perfume-card">
                <img src="image/image4.png" alt="John Varvatos Vintage perfume" class="perfume-image">
                <div class="perfume-info">
                    <h3 class="perfume-name">John Varvatos Vintage</h3>
                    <p class="perfume-description">A timeless masculine fragrance with warm woods and tobacco.</p>
                </div>
            </div>
            
            <div class="perfume-card">
                <img src="image/image5.png" alt="Third perfume" class="perfume-image">
                <div class="perfume-info">
                    <h3 class="perfume-name">Dior Homme Intense</h3>
                    <p class="perfume-description">An elegant fragrance with sophisticated notes.</p>
                </div>
            </div>
        </div>
    </div>
    </section>

<section class="products-section">
        <h2 class="section-title">Popular Products</h2>
        <div class="product-filters">
            <button class="filter-btn active">New Arrival</button>
            <button class="filter-btn ">Bestseller</button>
            <button class="filter-btn ">Special</button>
        </div>
        <div class="product-grid">
            <div class="product-card">
                <span class="product-discount">46% Off</span>
                <img src="image/image6.png" alt="Perfume product" />
                <h3>Shalimar Luxury</h3>
                <p>$140.00</p>
            </div>
            <div class="product-card">
                <span class="product-discount">16% Off</span>
                <img src="image/image7.png" alt="Perfume product" />
                <h3>Neque Porro Quisquam</h3>
                <p>$64.00</p>
            </div>
            <div class="product-card">
                <span class="product-discount">8% Off</span>
                <img src=" image/image7.png" alt="Perfume product" />
                <h3>Consectetur Hampden</h3>
                <p>$110.00</p>
            </div>
            <div class="product-card">
                <span class="product-discount">5% Off</span>
                <img src="image/image8.png" alt="Perfume product" />
                <h3>Praesentium Voluptatum</h3>
                <p>$122.00</p>
            </div>
            <div class="product-card">
                <span class="product-discount">18% Off</span>
                <img src="image/image9.png" alt="Perfume product" />
                <h3>Accusantium Doloremque</h3>
                <p>$86.00</p>
            </div>
            <div class="product-card">
                <span class="product-discount">5% Off</span>
                <img src="image/image10.png" alt="Perfume product" />
                <h3>Aliquam Quaerat</h3>
                <p>$108.80</p>
            </div>
            <div class="product-card">
                <span class="product-discount">6% Off</span>
                <img src="image/image11.png" alt="Perfume product" />
                <h3>Nostrud Exercitation</h3>
                <p>$78.80</p>
            </div>
            <div class="product-card">
                <span class="product-discount">3% Off</span>
                <img src="image/image12.png" alt="Perfume product" />
                <h3>Necessitatibus</h3>
                <p>$166.00</p>
            </div>
            
        </div>
    </section>
 
  
    <section class="service-section">
        <div class="service-item">
            <div class="service-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.33l6 3-6 3-6-3 6-3zM4 16.5v-7.18l8 4v7.18l-8-4zm16 0l-8 4v-7.18l8-4v7.18z"/>
                </svg>
            </div>
            <h2>Quality Products</h2>
            <p>We offer only the best fragrances from top brands.</p>
        </div>
        
        <div class="service-item">
            <div class="service-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                </svg>
            </div>
            <h2>Fast Shipping</h2>
            <p>Get your orders delivered quickly and safely.</p>
        </div>
        
        <div class="service-item">
            <div class="service-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M19.44 13c-.22 0-.45-.07-.67-.12a9.44 9.44 0 0 1-1.31-.39 2 2 0 0 0-2.48 1l-.22.45a12.18 12.18 0 0 1-2.66-2 12.18 12.18 0 0 1-2-2.66l.42-.28a2 2 0 0 0 1-2.48 10.33 10.33 0 0 1-.39-1.31c-.05-.22-.09-.45-.12-.67a3 3 0 0 0-3-2.49H5a3 3 0 0 0-3 3.41 19 19 0 0 0 16.52 16.46h.38a3 3 0 0 0 2.41-1.31l.09-.2a3 3 0 0 0-.2-3.2 3 3 0 0 0-1.76-.87zM5 4h3a1 1 0 0 1 1 .89c.03.27.08.54.14.8.15.67.35 1.32.59 1.96.36 1.17-.28 2.14-1.02 2.57l-.5.33c-.44.32-.52.92-.18 1.34a14.67 14.67 0 0 0 3.19 3.19c.42.34 1.02.26 1.34-.18l.33-.5c.43-.74 1.4-1.38 2.57-1.02.64.24 1.29.44 1.96.59.26.06.53.11.8.14A1 1 0 0 1 19 15v3a1 1 0 0 1-.89 1A17 17 0 0 1 4 4.89 1 1 0 0 1 5 4z"/>
                </svg>
            </div>
            <h2>24/7 Support</h2>
            <p>Our customer service is here to help you anytime.</p>
        </div>
    </section>

    <footer>
        <div class="footer-grid">
            <div>
                <h3 class="footer-title">Our Testimonial</h3>
                <p>"Our perfume store is a haven for fragrance lovers! With a stunning collection and expert staff,
                     we make finding your perfect scent a delightful experience. "</p><br>
                    <p>Jacob Joeckno </p>
            </div>
            <div>
                <h3 class="footer-title">Information</h3>
                <ul class="footer-links">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Delivery Information</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms & Conditions</a></li>
                </ul>
            </div>
            <div>
                <h3 class="footer-title">My Account</h3>
                <ul class="footer-links">
                    <li><a href="#">My Account</a></li>
                    <li><a href="#">Order History</a></li>
                    <li><a href="#">Wish List</a></li>
                    <li><a href="#">Newsletter</a></li>
                </ul>
            </div>
            <div>
                <h3 class="footer-title">Store Information</h3>
                <ul class="footer-links">
                    <li>Perfume Paradise - Perfume Store</li>
                    <li>United States</li>
                    <li>123-456-789</li>
                    <li>sales@perfumeparadise.com</li>
                </ul>
            </div>
        </div>
    </footer>
    <script>
        // Product data
const products = {
    newArrival: [
        {
            id: 1,
            name: "Shalimar Luxury",
            price: 140.00,
            discount: 46,
            image: "image/image6.png"
        },
        {
            id: 2,
            name: "Neque Porro Quisquam",
            price: 64.00,
            discount: 16,
            image: "image/image7.png"
        },
        {
            id: 3,
            name: "Commodi Consequatur",
            price: 92.00,
            discount: 33,
            image: "image/image8.png"
        },
        {
            id: 4,
            name: "Laborum Eveniet",
            price: 97.00,
            discount: 12,
            image: "image/image9.png"
        },
        {
            id: 5,
            name: "Necessitatibus",
            price: 116.00,
            discount: 6,
            image: "image/image10.png"
        },
        {
            id: 6,
            name: "Occasion Praesentium",
            price: 104.00,
            discount: 7,
            image: "image/image11.png"
        },
        {
            id: 7,
            name: "Voluptas Assumenda",
            price: 122.00,
            discount: 16,
            image: "image/image12.png"
        },
        {
            id: 8,
            name: "Exercitat Virginia",
            price: 104.00,
            discount: 11,
            image: "image/image13.png"
        },
        
    ],
    bestseller: [
        {
            id: 7,
            name: "Necessitatibus",
            price: 180.00,
            discount: 25,
            image: "image/image10.png" 
        },
        {
            id: 8,
            name: "Exercitat Virginia",
            price: 95.00,
            discount: 20,
            image: "image/image15.png"
        },
        {
            id: 9,
            name: "Ocean Breeze",
            price: 95.00,
            discount: 20,
            image: "image/image16.png"
        },
        {
            id: 10,
            name: "Rose Garden Elite",
            price: 95.00,
            discount: 20,
            image: "image/image17.png"
        },
        {
            id: 11,
            name: "Neque Porro Quisquam",
            price: 95.00,
            discount: 20,
            image: "image/image18.png"
        },
        {
            id: 12,
            name: "Nostrud Exercitation",
            price: 95.00,
            discount: 20,
            image: "image/image19.png"
        },
        {
            id: 13,
            name: "Commodi Consequatur",
            price: 95.00,
            discount: 20,
            image: "image/image8.png"
        },
        {
            id: 14,
            name: "Neque Porro Quisquam",
            price: 95.00,
            discount: 20,
            image: "image/image7.png"
        },
        // Add more bestseller products here
    ],
    special: [
        {
            id: 15,
            name: "Midnight Mystery",
            price: 220.00,
            discount: 30,
            image: "image/image15.png"
        },
        {
            id: 16,
            name: "Exercitat Virginia",
            price: 150.00,
            discount: 15,
            image: "image/image13.png"
        },
        {
            id: 17,
            name: "Aliquam Quaerat",
            price: 95.00,
            discount: 20,
            image: "image/image20.png"
        },
        {
            id: 18,
            name: "Accusantium Doloremque",
            price: 95.00,
            discount: 20,
            image: "image/image21.png"
        },
        {
            id: 19,
            name: "Ocean Breeze",
            price: 95.00,
            discount: 20,
            image: "image/image22.png"
        },
        {
            id: 20,
            name: "Consectetur Hampden",
            price: 95.00,
            discount: 20,
            image: "image/image17.png"
        },
        {
            id: 21,
            name: "Laborum Eveniet",
            price: 95.00,
            discount: 20,
            image: "image/image9.png"
        },
        {
            id: 22,
            name: "Aliquam Quaerat",
            price: 95.00,
            discount: 20,
            image: "image/image18.png"
        },
        // Add more special products here
    ]
};

// Function to create product card HTML
function createProductCard(product) {
    return `
        <div class="product-card">
            <span class="product-discount">${product.discount}% Off</span>
            <img src="${product.image}" alt="${product.name}" />
            <h3>${product.name}</h3>
            <p>$${product.price.toFixed(2)}</p>
        </div>
    `;
}

// Get all dropdown elements
const dropdowns = document.querySelectorAll('.dropdown');

// Function to close all dropdowns
function closeAllDropdowns() {
    const dropdownContents = document.querySelectorAll('.dropdown-content');
    dropdownContents.forEach(content => {
        content.style.display = 'none';
    });
}

// Add click event listeners to each dropdown
dropdowns.forEach(dropdown => {
    const link = dropdown.querySelector('a');
    const content = dropdown.querySelector('.dropdown-content');
    
    link.addEventListener('click', (e) => {
        e.preventDefault(); // Prevent default link behavior
        
        // Close all other dropdowns first
        closeAllDropdowns();
        
        // Toggle current dropdown
        const isCurrentlyOpen = content.style.display === 'block';
        content.style.display = isCurrentlyOpen ? 'none' : 'block';
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.dropdown')) {
        closeAllDropdowns();
    }
});
    </script>
   <script>
document.querySelector('.search-button').addEventListener('click', function() {
    const searchBar = document.querySelector('.search-bar');
    searchBar.classList.toggle('active');
    if (searchBar.classList.contains('active')) {
        searchBar.focus();
    }
});

// Close search bar when clicking outside
document.addEventListener('click', function(event) {
    const searchContainer = document.querySelector('.search-container');
    const searchBar = document.querySelector('.search-bar');
    
    if (!searchContainer.contains(event.target)) {
        searchBar.classList.remove('active');
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.slide');
    const sliderNav = document.querySelector('.slider-nav');
    const prevButton = document.querySelector('.slider-prev');
    const nextButton = document.querySelector('.slider-next');
    let currentSlide = 0;
    let slideInterval;

    // Create navigation dots
    slides.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.classList.add('slider-dot');
        if (index === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(index));
        sliderNav.appendChild(dot);
    });

    function updateSlides() {
        slides.forEach((slide, index) => {
            slide.classList.remove('active');
            document.querySelectorAll('.slider-dot')[index].classList.remove('active');
        });
        slides[currentSlide].classList.add('active');
        document.querySelectorAll('.slider-dot')[currentSlide].classList.add('active');
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        updateSlides();
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + slides.length) % slides.length;
        updateSlides();
    }

    function goToSlide(index) {
        currentSlide = index;
        updateSlides();
        resetInterval();
    }

    function resetInterval() {
        clearInterval(slideInterval);
        slideInterval = setInterval(nextSlide, 5000);
    }

    // Event listeners
    prevButton.addEventListener('click', () => {
        prevSlide();
        resetInterval();
    });

    nextButton.addEventListener('click', () => {
        nextSlide();
        resetInterval();
    });

    // Start automatic slideshow
    slideInterval = setInterval(nextSlide, 5000);

    // Pause on hover
    const slider = document.querySelector('.slider');
    slider.addEventListener('mouseenter', () => clearInterval(slideInterval));
    slider.addEventListener('mouseleave', () => {
        slideInterval = setInterval(nextSlide, 5000);
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
      const perfumeCards = document.querySelectorAll('.perfume-card');
      
      perfumeCards.forEach(card => {
        const accentLines = card.querySelectorAll('.accent-line');
        
        card.addEventListener('mouseenter', () => {
          accentLines.forEach((line, index) => {
            setTimeout(() => {
              line.style.opacity = '1';
            }, index * 100);
          });
        });
        
        card.addEventListener('mouseleave', () => {
          accentLines.forEach((line, index) => {
            setTimeout(() => {
              line.style.opacity = '0';
            }, index * 100);
          });
        });
      });
    });
  </script>
  <script>
    document.querySelector('.profile-icon').addEventListener('click', (event) => {
    event.preventDefault();
    const dropdownMenu = document.querySelector('.dropdown-menu');
    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
});

// Hide dropdown when clicking outside
window.addEventListener('click', (event) => {
    const profileMenu = document.querySelector('.profile-menu');
    if (!profileMenu.contains(event.target)) {
        document.querySelector('.dropdown-menu').style.display = 'none';
    }
});

   
function toggleDropdown() {
    const dropdownMenu = document.getElementById('dropdownMenu');
    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
}

// Close the dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdownMenu = document.getElementById('dropdownMenu');
    const profileCircle = document.querySelector('.profile-circle');

    // Check if the click is outside the dropdown and profile circle
    if (!dropdownMenu.contains(event.target) && !profileCircle.contains(event.target)) {
        dropdownMenu.style.display = 'none';
    }
});
</script>

</body>
</html>