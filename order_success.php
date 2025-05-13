<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? sanitize_input($_GET['order_id']) : '';

// If no order ID, redirect to dashboard
if (empty($order_id)) {
    header('Location: customer_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        /* Success page specific styles */
        body {
            padding-top: 70px;
        }
        
        .success-section {
            padding: 50px 0;
            background-color: #f8f9fa;
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .success-container {
            width: 95%;
            max-width: 600px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .success-card {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background-color: #d4edda;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .success-icon i {
            font-size: 40px;
            color: #28a745;
        }
        
        .success-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .success-message {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .order-number {
            font-weight: bold;
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: inline-block;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-button {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .primary-button {
            background-color: #3498db;
            color: white;
        }
        
        .primary-button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .secondary-button {
            background-color: #f8f9fa;
            color: #2c3e50;
            border: 1px solid #dee2e6;
        }
        
        .secondary-button:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
    <script>
        // Clear cart on page load
        document.addEventListener('DOMContentLoaded', function() {
            localStorage.removeItem('megabooks_cart');
            localStorage.removeItem('megabooks_shipping');
        });
    </script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <h1>Mega Books</h1>
            </div>
            <div class="menu-toggle" id="mobile-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="index.php#services" class="nav-link">Services</a></li>
                <li><a href="explore.php" class="nav-link">Books</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="index.php#contact-form" class="nav-link">Contact</a></li>
                
                <li>
                    <a href="cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count" id="cartCountBadge">0</span>
                    </a>
                </li>
                <li>
                    <a href="customer_dashboard.php" class="profile-icon">
                        <i class="fas fa-user"></i>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Success Content -->
    <section class="success-section">
        <div class="success-container">
            <div class="success-card">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="success-title">Order Placed Successfully!</h1>
                <p class="success-message">Thank you for your purchase. Your order has been received and is being processed.</p>
                <div class="order-number"><?php echo htmlspecialchars($order_id); ?></div>
                <p class="success-message">You will receive an email confirmation shortly.</p>
                <div class="action-buttons">
                    <a href="explore.php" class="action-button primary-button">Continue Shopping</a>
                    <a href="customer_dashboard.php" class="action-button secondary-button">View My Orders</a>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Mobile Menu Toggle
        const mobileMenu = document.getElementById("mobile-menu");
        const navMenu = document.querySelector(".nav-menu");

        if (mobileMenu) {
            mobileMenu.addEventListener("click", () => {
                mobileMenu.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
        }

        // Close mobile menu when clicking on a nav link
        const navLinks = document.querySelectorAll(".nav-link");
        navLinks.forEach((link) => {
            link.addEventListener("click", () => {
                mobileMenu.classList.remove("active");
                navMenu.classList.remove("active");
            });
        });
        
        // Update cart count
        function updateCartCount() {
            const cart = localStorage.getItem('megabooks_cart');
            const cartItems = cart ? JSON.parse(cart) : [];
            const count = cartItems.reduce((total, item) => total + item.quantity, 0);
            const countBadge = document.getElementById('cartCountBadge');
            if (countBadge) {
                countBadge.textContent = count;
                countBadge.style.display = count > 0 ? 'flex' : 'none';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });
    </script>
</body>
</html>
