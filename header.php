<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <a href="index.php">Perfume Store</a>
            </div>
            
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="productslist.php">Products</a></li>
                    
                    <li><a href="wishlist.php">Wishlist</a></li>
                    <li><a href="orders.php" class="nav-link">My Orders</a></li>
                </ul>
            </nav>

            <div class="header-right">
                <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <div class="user-menu">
                        <a href="profile.php" class="user-link">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                    </div>
                    
                    <a href="add_to_cart.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-counter">0</span>
                    </a>
                    
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="login-link">Login</a>
                    <a href="signup.php" class="signup-link">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

<style>
.main-header {
    background-color: #000;
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo a {
    color: #e8a87c;
    text-decoration: none;
    font-size: 1.5em;
    font-weight: bold;
}

.main-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    gap: 20px;
}

.main-nav a {
    color: white;
    text-decoration: none;
    padding: 5px 10px;
    transition: color 0.3s;
}

.main-nav a:hover {
    color: #e8a87c;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.cart-link {
    position: relative;
    color: white;
    text-decoration: none;
}

.cart-counter {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #e8a87c;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 0.8em;
    min-width: 15px;
    text-align: center;
}

.user-link,
.logout-link {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
}

.login-link,
.signup-link {
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 4px;
    transition: all 0.3s;
}

.login-link {
    background-color: transparent;
    border: 1px solid #e8a87c;
}

.signup-link {
    background-color: #e8a87c;
}

.login-link:hover,
.signup-link:hover {
    background-color: #d89666;
    border-color: #d89666;
}

@media (max-width: 768px) {
    .main-nav {
        display: none; /* Add a hamburger menu for mobile */
    }
    
    .header-right {
        gap: 10px;
    }
    
    .user-menu span {
        display: none;
    }
}
</style>

<script>
// Update cart counter on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCartCounter();
});

function updateCartCounter() {
    fetch('get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            const counter = document.querySelector('.cart-counter');
            if (counter) {
                counter.textContent = data.count;
            }
        });
}
</script>

</body>
</html>