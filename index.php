<?php
session_start();

// Include database connection
require_once 'dbconnect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in'])) {
    // Don't redirect - allow anonymous users to view the homepage
    $logged_in = false;
} else {
    $logged_in = true;
}

// Fetch categories from database
$categoryQuery = "SELECT category_id, name, description, image_path FROM tbl_categories WHERE deleted = 0 ORDER BY name";
$categoryResult = $conn->query($categoryQuery);
$categories = [];

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Function to get category type from name
function getCategoryType($name) {
    $name = strtolower($name);
    return preg_replace('/[^a-z0-9]+/', '-', $name);
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
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            height: 80px;
        }

        .logo {
            color: #e8a87c;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 400;
            text-transform: none;
            letter-spacing: normal;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #000;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
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
            align-items: center;
            gap: 30px; /* Equal spacing between icons */
        }

        .nav-icons .icon {
            width: 24px;
            height: 24px;
            stroke: white;
            cursor: pointer;
        }

        .search-button,
        .cart-icon,
        .profile-icon {
            background: none;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Cart count badge */
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e8a87c;
            color: white;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Hover effect for all icons */
        .search-button:hover .icon,
        .cart-icon:hover .icon,
        .profile-icon:hover .icon {
            stroke: #e8a87c;
            transition: stroke 0.3s ease;
        }

        .hero {
            position: relative;
            width: 100%;
            height: 100vh;
            margin-top: 0;
            overflow: hidden;
        }

        .slider {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }

        .slide.active {
            opacity: 1;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slide-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
            text-align: center;
        }

        .shop-now-btn {
            display: inline-block;
            padding: 15px 40px;
            background-color: #e8a87c;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            border: 2px solid #e8a87c;
        }

        .shop-now-btn:hover {
            background-color: transparent;
            color: #fff;
        }

        .slider-nav {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 2;
        }

        .slider-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
        }

        .slider-dot.active {
            background-color: #fff;
        }

        .slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 24px;
            border-radius: 50%;
            z-index: 2;
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
            flex-grow: 1;
            max-width: 500px;
            margin: 0 20px;
        }

        .search-bar {
            width: 100%;
            background: white;
            border: none;
            padding: 12px 15px;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .search-bar:focus {
            outline: none;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 2px;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }

        .search-result-item:hover {
            background: #f8f8f8;
        }

        .search-result-image {
            width: 40px;
            height: 40px;
            object-fit: contain;
            margin-right: 15px;
        }

        .search-result-info {
            flex: 1;
        }

        .search-result-title {
            font-size: 14px;
            color: #212121;
            margin-bottom: 4px;
        }

        .search-result-price {
            font-size: 12px;
            color: #388e3c;
            font-weight: 500;
        }

        .search-result-category {
            font-size: 12px;
            color: #878787;
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
            gap: 10px;
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
            width: 20px;
            text-align: center;
        }

        .profile-menu:hover .dropdown-menu {
            display: block;
        }

        .slide {
            position: relative;
        }

        .slide-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
        }

        .shop-now-btn {
            display: inline-block;
            background-color: #e8a87c;
            color: #000;
            text-decoration: none;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .shop-now-btn:hover {
            background-color: #fff;
            color: #000;
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .slide::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            pointer-events: none;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translate(-50%, 20px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .slide.active .slide-content {
            animation: fadeInUp 0.8s ease forwards;
        }

        .categories-section {
            padding: 4rem 2rem;
            background-color: #000;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .category-card {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            height: 300px;
            background: #000;
        }

        .category-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .category-card:hover img {
            transform: scale(1.1);
        }

        .category-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            text-align: center;
        }

        .category-content h3 {
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .view-collection {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            background-color: #e8a87c;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: background-color 0.3s ease;
        }

        .view-collection:hover {
            background-color: #d89666;
        }

        .categories-section h2 {
            text-align: center;
            font-size: 2.5rem;
            color: white;
            margin-bottom: 3rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .category-dropdown {
            position: relative;
        }

        .category-dropdown > a {
            display: block;
            padding: 12px 16px;
            text-decoration: none;
            color: #333;
        }

        .category-dropdown:hover {
            background-color: #f1f1f1;
        }

        .subcategory-dropdown {
            display: none;
            position: absolute;
            left: 100%;
            top: 0;
            background-color: #f9f9f9;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        }

        .category-dropdown:hover .subcategory-dropdown {
            display: block;
        }

        .subcategory-dropdown a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }

        .subcategory-dropdown a:hover {
            background-color: #f1f1f1;
        }

        /* Profile Dropdown Styles */
        .profile-icon {
            position: relative;
            cursor: pointer;
        }

        .user-circle {
            width: 35px;
            height: 35px;
            background: #e8a87c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a1a1a;
            font-weight: bold;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .user-circle:hover {
            background: #d69a6f;
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 220px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            display: none;
            z-index: 1000;
            margin-top: 10px;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
            border-bottom: 1px solid #333;
        }

        .profile-dropdown a:last-child {
            border-bottom: none;
        }

        .profile-dropdown a i {
            margin-right: 10px;
            width: 20px;
            color: #e8a87c;
        }

        .profile-dropdown a:hover {
            background: #2a2a2a;
        }

        .logout-btn {
            color: #ff6b6b !important;
        }

        .logout-btn i {
            color: #ff6b6b !important;
        }

        /* Add arrow to dropdown */
        .profile-dropdown::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 15px;
            width: 10px;
            height: 10px;
            background: #1a1a1a;
            border-left: 1px solid #333;
            border-top: 1px solid #333;
            transform: rotate(45deg);
        }

        @media (max-width: 768px) {
            .profile-dropdown {
                width: 200px;
                right: -10px;
            }
        }

        /* Search Styles */
        .search-container {
            position: relative;
        }

        .search-bar {
            display: none;
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            width: 200px;
            padding: 8px 12px;
            background-color: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            color: white;
        }

        .search-bar.active {
            display: block;
        }

        .search-results {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 250px;
            background-color: #1a1a1a;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            margin-top: 5px;
            z-index: 1000;
        }

        .search-result-item {
            padding: 12px 15px;
            color: white;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .search-result-item:hover {
            background-color: #333;
        }

        .no-results {
            padding: 15px;
            text-align: center;
            color: rgba(255,255,255,0.7);
        }

        /* User Circle Styles */
        .user-circle {
            width: 32px;
            height: 32px;
            background-color: #e8a87c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .user-circle:hover {
            background-color: #d89666;
        }

        /* Updated Profile Dropdown Styles */
        .profile-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background-color: #1a1a1a;
            min-width: 200px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            border-radius: 4px;
            margin-top: 10px;
            z-index: 1000;
        }

        .profile-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 15px;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid #1a1a1a;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .profile-dropdown a:last-child {
            border-bottom: none;
        }

        .profile-dropdown a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: #e8a87c;
        }

        .profile-dropdown a:hover {
            background-color: #333;
        }

        .profile-dropdown.show {
            display: block;
        }

        .brands-dropdown {
            display: none;
            position: absolute;
            left: 100%;
            top: 0;
            background-color: #fff;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        .subcategory-item:hover .brands-dropdown {
            display: block;
        }

        .brands-dropdown a {
            color: #000 !important;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
            font-size: 1rem;
            font-weight: 400;
            text-transform: none;
            letter-spacing: normal;
            background-color: #fff;
        }

        .brands-dropdown a:hover {
            background-color: #f5f5f5;
            color: #e8a87c !important;
        }

        /* Ensure proper z-index and transitions */
        .dropdown-content,
        .subcategory-dropdown,
        .brands-dropdown {
            z-index: 1000;
        }

        .subcategory-item {
            position: relative;
        }

        .search-container {
            flex: 1;
            max-width: 600px;
            margin: 0 20px;
            position: relative;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 4px;
            overflow: hidden;
        }

        .search-box input {
            flex: 1;
            padding: 12px 16px;
            border: none;
            outline: none;
            font-size: 14px;
            width: 100%;
        }

        .search-button {
            padding: 12px 20px;
            background: #e8a87c;
            border: none;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
        }

        .search-button:hover {
            background: #d69a6f;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: none;
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
        }

        .suggestion-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }

        .suggestion-item:hover {
            background: #f8f8f8;
        }

        .suggestion-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            margin-right: 12px;
            border-radius: 4px;
        }

        .suggestion-details {
            flex: 1;
        }

        .suggestion-name {
            font-size: 14px;
            color: #212121;
            margin-bottom: 4px;
        }

        .suggestion-brand {
            font-size: 12px;
            color: #666;
        }

        .suggestion-price {
            font-weight: 500;
            color: #388e3c;
        }

        .suggestion-category {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            background: #f5f5f5;
            color: #666;
            font-size: 12px;
            font-weight: 500;
        }

        .no-suggestions {
            padding: 16px;
            text-align: center;
            color: #666;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .search-container {
                margin: 0 10px;
            }
            
            .search-box input {
                padding: 10px 12px;
            }
            
            .search-button {
                padding: 10px 15px;
            }
        }

        /* Add these styles while keeping your existing profile-icon styles */
        .user-initial {
            width: 35px;
            height: 35px;
            background: #e8a87c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .user-initial:hover {
            background: #d69a6f;
        }

        /* Add these specific styles for login/signup buttons */
        .login-btn, .signup-btn {
            color: #fff !important;
            transition: background-color 0.3s ease;
        }

        .login-btn:hover, .signup-btn:hover {
            background-color: #2a2a2a !important;
        }

        .login-btn i, .signup-btn i {
            color: #e8a87c !important;
        }

    </style>
</head>
<body>
<nav>
    <div class="logo">
        <a href="index.php">
            <img src="image/logo.png" alt="Perfume Paradise Logo">
        </a>
    </div>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <div class="nav-links">
        <a href="index.php">Home</a> 
        <div class="dropdown">
            <a href="#categories">Categories</a>
            <div class="dropdown-content">
                <div class="category-dropdown">
                    <?php foreach($categories as $category): ?>
                        <a href="productslist.php?category=<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <a href="Aboutas.php">Our story</a> 
        <a href="contactus.php">Contact Us</a>
    </div>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <div class="search-container">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search for perfumes...">
            <button type="button" class="search-button">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="search-suggestions" id="searchSuggestions"></div>
    </div>

    <div class="nav-icons">
        <a href="add_to_cart.php" class="cart-icon">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <?php if (isset($_SESSION['cart_count'])): ?>
                <span class="cart-count"><?php echo $_SESSION['cart_count']; ?></span>
            <?php endif; ?>
        </a>

        <div class="profile-icon">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="user-circle" id="userCircle">
                    <?php 
                        $username = $_SESSION['username'];
                        echo strtoupper(substr($username, 0, 1));
                    ?>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="orders.php">
                        <i class="fas fa-shopping-bag"></i> Orders
                    </a>
                    <a href="wishlist.php">
                        <i class="fas fa-heart"></i> Wishlist
                    </a>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <div class="user-circle" id="userCircle">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="login.php" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="signup.php" class="signup-btn">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="slider">
        <div class="slide active">
            <img src="image/image1.jpg" alt="Luxury perfume bottles with dramatic lighting" />
            <div class="slide-content">
                <a href="productslist.php" class="shop-now-btn">Shop Now</a>
            </div>
        </div>
        <div class="slide">
            <img src="image/image2.jpg" alt="Luxury perfume bottles with dramatic lighting" />
            <div class="slide-content">
                <a href="productslist.php" class="shop-now-btn">Shop Now</a>
            </div>
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

<section class="categories-section">
    <h2 style="font-family: 'Times New Roman', serif">Our Categories</h2>
    <div class="categories-grid">
        <?php foreach ($categories as $category): ?>
            <div class="category-card">
                <img src="<?php echo htmlspecialchars($category['image_path']); ?>" 
                     alt="<?php echo htmlspecialchars($category['name']); ?>">
                <div class="category-content">
                    <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                    <a href="productslist.php?category=<?php echo $category['category_id']; ?>" class="view-collection">View Collection</a>
                </div>
            </div>
        <?php endforeach; ?>
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
                    <li><a href="Aboutas.php">About Us</a></li>
                    <li><a href="contactus.php">Contact Us</a></li>
                </ul>
            </div>
            <div>
                <h3 class="footer-title">My Account</h3>
                <ul class="footer-links">
                    <li><a href="index.php">My Account</a></li>
                    <li><a href="orders.php">Order History</a></li>
                    <li><a href="wishlist.php">Wish List</a></li>
                   
                   
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
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        if (query.length > 2) {
            // Show loading state
            searchSuggestions.style.display = 'block';
            searchSuggestions.innerHTML = '<div class="suggestion-item">Searching...</div>';
            
            // Debounce the search
            searchTimeout = setTimeout(() => {
                fetch(`search_suggestions.php?query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.suggestions.length > 0) {
                            let html = '';
                            let currentCategory = '';
                            
                            data.suggestions.forEach(suggestion => {
                                if (suggestion.type === 'category') {
                                    html += `
                                        <div class="suggestion-category">
                                            <i class="fas fa-folder mr-2"></i>
                                            in ${suggestion.name}
                                        </div>
                                    `;
                                } else {
                                    html += `
                                        <div class="suggestion-item" onclick="window.location.href='product_details.php?id=${suggestion.id}'">
                                            <img src="${suggestion.image}" alt="${suggestion.name}" class="suggestion-image">
                                            <div class="suggestion-details">
                                                <div class="suggestion-name">${suggestion.name}</div>
                                                <div class="suggestion-brand">${suggestion.brand}</div>
                                                <div class="suggestion-price">₹${parseFloat(suggestion.price).toFixed(2)}</div>
                                            </div>
                                        </div>
                                    `;
                                }
                            });
                            
                            searchSuggestions.innerHTML = html;
                        } else {
                            searchSuggestions.innerHTML = '<div class="no-suggestions">No results found</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        searchSuggestions.innerHTML = '<div class="no-suggestions">Error searching products</div>';
                    });
            }, 300);
        } else {
            searchSuggestions.style.display = 'none';
        }
    });

    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
            searchSuggestions.style.display = 'none';
        }
    });

    // Handle search button click
    document.querySelector('.search-button').addEventListener('click', function() {
        const query = searchInput.value.trim();
        if (query) {
            window.location.href = `productslist.php?search=${encodeURIComponent(query)}`;
        }
    });

    // Handle enter key in search input
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                window.location.href = `productslist.php?search=${encodeURIComponent(query)}`;
            }
        }
    });
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove the old shop now button click handler
    // const shopNowButtons = document.querySelectorAll('.shop-now-btn');
    // shopNowButtons.forEach(button => { ... });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileIcon = document.querySelector('.profile-icon');
    const profileDropdown = document.querySelector('.profile-dropdown');

    // Toggle dropdown on click
    profileIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!profileIcon.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });

    // Prevent dropdown from closing when clicking inside it
    profileDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>

</body>
</html>