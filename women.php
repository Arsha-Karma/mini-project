<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #0a0a0a;
            color: #f0f0f0;
        }

        .cart-container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
        }

        h1 {
            color: #4395e6;
            text-align: center;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .product-card {
            background-color: #1a1a1a;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(20, 20, 20, 0.5);
            overflow: hidden;
            text-align: center;
            padding: 15px;
            border-top: 3px solid #4395e6;
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: scale(1.05);
        }

        .product-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .product-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #4395e6;
        }

        .product-reviews {
            color: #ff9900;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 18px;
            color: #f0f0f0;
            margin-bottom: 5px;
        }

        .product-price del {
            font-size: 14px;
            color: #888;
            margin-right: 5px;
        }

        .product-discount {
            font-size: 14px;
            color: #ff4500;
        }

        .product-action {
            margin-top: 10px;
        }

        .add-to-cart {
            background-color: #4395e6;
            color: #fff;
            padding: 8px 15px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        .add-to-cart:hover {
            background-color: #3a7bd5;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="cart-container">
        <h1>Women's Perfumes</h1>
        <div class="product-grid">
            <!-- Product 1 -->
            <div class="product-card">
                <img src="image/image23.jpg" alt="Product 1">
                <div class="product-name">pirple velvet perfume</div>
                <div class="product-reviews">★★★★★ 60 reviews</div>
                <div class="product-price">
                    <del>Rs. 3,499.00</del> Rs. 2,449.00
                </div>
                <div class="product-discount">Save Rs. 1,050.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="product-card">
                <img src="image/image24.jpg" alt="Product 2">
                <div class="product-name">rose gold perfume</div>
                <div class="product-reviews">★★★★★ 35 reviews</div>
                <div class="product-price">
                    <del>Rs. 2,499.00</del> Rs. 1,749.00
                </div>
                <div class="product-discount">Save Rs. 750.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="product-card">
                <img src="image/image25.jpg" alt="Product 3">
                <div class="product-name">Gilles Cantuel</div>
                <div class="product-reviews">★★★★★ 21 reviews</div>
                <div class="product-price">
                    <del>Rs. 2,999.00</del> Rs. 2,499.00
                </div>
                <div class="product-discount">Save Rs. 500.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="product-card">
                <img src="image/image26.jpg" alt="Product 4">
                <div class="product-name">nostrud perfume</div>
                <div class="product-reviews">★★★★★ 18 reviews</div>
                <div class="product-price">
                    <del>Rs. 2,800.00</del> Rs. 2,200.00
                </div>
                <div class="product-discount">Save Rs. 600.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 5 -->
            <div class="product-card">
                <img src="image/image27.jpg" alt="Product 5">
                <div class="product-name">aliquam perfume</div>
                <div class="product-reviews">★★★★★ 25 reviews</div>
                <div class="product-price">
                    <del>Rs. 3,000.00</del> Rs. 2,500.00
                </div>
                <div class="product-discount">Save Rs. 500.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 6 -->
            <div class="product-card">
                <img src="image/image28.jpg" alt="Product 6">
                <div class="product-name">nesesetabus perfume
                </div>
                <div class="product-reviews">★★★★★ 45 reviews</div>
                <div class="product-price">
                    <del>Rs. 4,000.00</del> Rs. 3,200.00
                </div>
                <div class="product-discount">Save Rs. 800.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 7 -->
            <div class="product-card">
                <img src="image/image29.jpg" alt="Product 6">
                <div class="product-name">prasentium perfume</div>
                <div class="product-reviews">★★★★★ 45 reviews</div>
                <div class="product-price">
                    <del>Rs. 4,000.00</del> Rs. 3,200.00
                </div>
                <div class="product-discount">Save Rs. 800.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 8 -->
            <div class="product-card">
                <img src="image/image31.jpg" alt="Product 6">
                <div class="product-name">nesesetabus perfume </div>
                <div class="product-reviews">★★★★★ 45 reviews</div>
                <div class="product-price">
                    <del>Rs. 4,000.00</del> Rs. 3,200.00
                </div>
                <div class="product-discount">Save Rs. 800.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>

            <!-- Product 9 -->
            <div class="product-card">
                <img src="image/image21.png" alt="Product 6">
                <div class="product-name"> Laborum Eveniet</div>
                <div class="product-reviews">★★★★★ 45 reviews</div>
                <div class="product-price">
                    <del>Rs. 4,000.00</del> Rs. 3,200.00
                </div>
                <div class="product-discount">Save Rs. 800.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>
            <!-- Product 10 -->
            <div class="product-card">
                <img src="image/image14.png" alt="Product 6">
                <div class="product-name">Commodi Consequatur </div>
                <div class="product-reviews">★★★★★ 45 reviews</div>
                <div class="product-price">
                    <del>Rs. 4,000.00</del> Rs. 3,200.00
                </div>
                <div class="product-discount">Save Rs. 800.00</div>
                <div class="product-action">
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
