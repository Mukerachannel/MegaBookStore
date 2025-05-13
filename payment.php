<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=cart.php');
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        /* Payment page specific styles */
        body {
            padding-top: 70px;
        }
        
        .payment-section {
            padding: 30px 0;
            background-color: #f8f9fa;
            min-height: 70vh;
        }
        
        .payment-container {
            width: 95%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .section-header h2 {
            margin-bottom: 5px;
        }
        
        .section-header p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .payment-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .payment-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .payment-card h3 {
            margin: 0 0 15px;
            color: #2c3e50;
            font-size: 1.2rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .order-summary {
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .summary-row.total {
            font-weight: bold;
            font-size: 1.1rem;
            color: #2c3e50;
            padding-top: 8px;
            border-top: 1px solid #eee;
            margin-top: 8px;
        }
        
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .payment-method {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .payment-method:hover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }
        
        .payment-method.selected {
            border-color: #3498db;
            background-color: #e9f7fe;
        }
        
        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-method-radio {
            width: 18px;
            height: 18px;
        }
        
        .payment-method-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        .payment-method-logo {
            margin-left: auto;
            height: 30px;
        }
        
        .payment-method-details {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            display: none;
        }
        
        .payment-method.selected .payment-method-details {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .back-btn {
            padding: 10px 16px;
            background-color: #95a5a6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .back-btn:hover {
            background-color: #7f8c8d;
        }
        
        .pay-btn {
            padding: 10px 20px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .pay-btn:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .payment-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .payment-info i {
            color: #3498db;
            margin-right: 5px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .back-btn, .pay-btn {
                width: 100%;
            }
        }
        
        /* Telebirr specific styles */
        .telebirr-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            background-color: #f0f8ff;
            border-radius: 5px;
        }
        
        .telebirr-logo {
            height: 40px;
        }
        
        .telebirr-text {
            font-size: 0.9rem;
            color: #2c3e50;
        }
    </style>
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

    <!-- Payment Content -->
    <section class="payment-section">
        <div class="payment-container">
            <div class="section-header">
                <h2>Payment</h2>
                <p>Complete your purchase securely</p>
            </div>
            
            <div class="payment-content">
                <div class="payment-card">
                    <h3>Order Summary</h3>
                    <div class="order-summary" id="orderSummary">
                        <!-- Order summary will be loaded here via JavaScript -->
                    </div>
                </div>
                
                <div class="payment-card">
                    <h3>Payment Method</h3>
                    <div class="payment-methods">
                        <div class="payment-method selected" data-method="telebirr">
                            <div class="payment-method-header">
                                <input type="radio" name="payment_method" value="telebirr" class="payment-method-radio" checked>
                                <span class="payment-method-title">Telebirr</span>
                                <img src="https://www.ethiotelecom.et/wp-content/uploads/2021/05/telebirr-1.png" alt="Telebirr" class="payment-method-logo">
                            </div>
                            <div class="payment-method-details">
                                <div class="telebirr-info">
                                    <img src="https://www.ethiotelecom.et/wp-content/uploads/2021/05/telebirr-1.png" alt="Telebirr" class="telebirr-logo">
                                    <div class="telebirr-text">
                                        Pay securely using your Telebirr mobile wallet. You'll be redirected to complete the payment.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button class="back-btn" onclick="window.location.href='cart.php'">Back to Cart</button>
                    <button class="pay-btn" id="payButton">Pay Now</button>
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
        
        // Cart Management
        function getCart() {
            const cart = localStorage.getItem('megabooks_cart');
            return cart ? JSON.parse(cart) : [];
        }
        
        function updateCartCount() {
            const cart = getCart();
            const count = cart.reduce((total, item) => total + item.quantity, 0);
            const countBadge = document.getElementById('cartCountBadge');
            if (countBadge) {
                countBadge.textContent = count;
                countBadge.style.display = count > 0 ? 'flex' : 'none';
            }
        }
        
        function calculateSubtotal() {
            const cart = getCart();
            return cart.reduce((total, item) => {
                if (item.orderType === 'buy') {
                    return total + (item.price * item.quantity);
                } else {
                    return total + (item.rentPrice * item.rentDays * item.quantity);
                }
            }, 0);
        }
        
        function renderOrderSummary() {
            const cart = getCart();
            const subtotal = calculateSubtotal();
            const orderSummary = document.getElementById('orderSummary');
            
            if (orderSummary) {
                let summaryHTML = '';
                
                // Add item count
                summaryHTML += `
                    <div class="summary-row">
                        <span>Items (${cart.length}):</span>
                        <span>ETB${subtotal.toFixed(2)}</span>
                    </div>
                `;
                
                // Add total
                summaryHTML += `
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>ETB${subtotal.toFixed(2)}</span>
                    </div>
                `;
                
                orderSummary.innerHTML = summaryHTML;
            }
        }
        
        // Payment method selection
        const paymentMethods = document.querySelectorAll('.payment-method');
        paymentMethods.forEach(method => {
            method.addEventListener('click', () => {
                // Deselect all methods
                paymentMethods.forEach(m => m.classList.remove('selected'));
                
                // Select clicked method
                method.classList.add('selected');
                
                // Check the radio button
                const radio = method.querySelector('.payment-method-radio');
                radio.checked = true;
            });
        });
        
        // Handle payment button click
        document.getElementById('payButton').addEventListener('click', function() {
            // Get shipping info from localStorage
            const shippingInfo = JSON.parse(localStorage.getItem('megabooks_shipping'));
            
            // Get cart data
            const cart = getCart();
            const totalAmount = calculateSubtotal();
            
            // Prepare data for the server
            const orderData = {
                customer_info: shippingInfo,
                items: cart,
                total_amount: totalAmount,
                payment_method: 'telebirr'
            };
            
            // Send data to the server to initialize Chapa payment
            fetch('chapa_initialize.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orderData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to Chapa checkout URL
                    window.location.href = data.checkout_url;
                } else {
                    // Improved error handling
                    let errorMessage = 'Payment initialization failed';
                    if (data.message) {
                        errorMessage += ': ' + data.message;
                    }
                    alert(errorMessage);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your payment. Please try again.');
            });
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            renderOrderSummary();
            
            // Check if cart is empty
            const cart = getCart();
            if (cart.length === 0) {
                window.location.href = 'cart.php';
            }
            
            // Check if shipping info exists
            const shippingInfo = localStorage.getItem('megabooks_shipping');
            if (!shippingInfo) {
                window.location.href = 'cart.php';
            }
        });
    </script>
</body>
</html>
