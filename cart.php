<?php
session_start();
require_once 'db.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        // Add item to cart
        $book_id = (int)$_POST['book_id'];
        $quantity = (int)$_POST['quantity'];
        $is_rental = isset($_POST['is_rental']) && $_POST['is_rental'] == 1;
        $rental_days = $is_rental ? (int)$_POST['rental_days'] : null;
        
        // Get book details
        $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $book = $result->fetch_assoc();
            
            // Check if book is in stock
            if ($book['stock'] < $quantity) {
                $_SESSION['cart_error'] = "Sorry, only {$book['stock']} copies available.";
                header('Location: book-details.php?id=' . $book_id);
                exit;
            }
            
            // Calculate price based on rental or purchase
            $price = $is_rental ? $book['rent_price_per_day'] * $rental_days : $book['price'];
            
            // Add to cart
            $_SESSION['cart'][$book_id] = [
                'book_id' => $book_id,
                'quantity' => $quantity,
                'price' => $price,
                'is_rental' => $is_rental,
                'rental_days' => $rental_days
            ];
            
            $_SESSION['cart_success'] = "Book added to cart successfully.";
        }
        
        header('Location: cart.php');
        exit;
    } elseif ($action === 'update') {
        // Update cart item quantity
        $book_id = (int)$_POST['book_id'];
        $quantity = (int)$_POST['quantity'];
        
        if (isset($_SESSION['cart'][$book_id])) {
            // Get book details to check stock
            $stmt = $conn->prepare("SELECT stock FROM books WHERE id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $book = $result->fetch_assoc();
                
                // Check if book is in stock
                if ($book['stock'] < $quantity) {
                    $_SESSION['cart_error'] = "Sorry, only {$book['stock']} copies available.";
                } else {
                    $_SESSION['cart'][$book_id]['quantity'] = $quantity;
                    $_SESSION['cart_success'] = "Cart updated successfully.";
                }
            }
        }
        
        header('Location: cart.php');
        exit;
    } elseif ($action === 'remove') {
        // Remove item from cart
        $book_id = (int)$_POST['book_id'];
        
        if (isset($_SESSION['cart'][$book_id])) {
            unset($_SESSION['cart'][$book_id]);
            $_SESSION['cart_success'] = "Item removed from cart.";
        }
        
        header('Location: cart.php');
        exit;
    } elseif ($action === 'clear') {
        // Clear entire cart
        $_SESSION['cart'] = [];
        $_SESSION['cart_success'] = "Cart cleared successfully.";
        
        header('Location: cart.php');
        exit;
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Redirect to login if not logged in
if (!$is_logged_in) {
    header("Location: login.php?redirect=cart.php");
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

// Get cart items for display
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $book_id => $item) {
        // Get book details
        $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $book = $result->fetch_assoc();
            $item['book'] = $book;
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $cart_items[] = $item;
            $total += $item['subtotal'];
        }
    }
}

// Calculate shipping
$shipping_fee = get_setting($conn, 'shipping_fee') ?? 50;
$free_shipping_threshold = get_setting($conn, 'free_shipping_threshold') ?? 500;
$shipping_cost = ($total < $free_shipping_threshold) ? $shipping_fee : 0;

// Calculate final total
$final_total = $total + $shipping_cost;

// Get currency
$currency = get_setting($conn, 'currency') ?? 'ETB';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        /* Cart page specific styles */
        body {
            padding-top: 70px;
        }
        
        .cart-section {
            padding: 30px 0;
            background-color: #f8f9fa;
            min-height: 70vh;
        }
        
        .cart-container {
            width: 95%;
            max-width: 1300px;
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
        
        /* Update the cart-content layout to be horizontal instead of vertical */
        .cart-content {
            display: flex;
            flex-direction: row;
            gap: 30px;
            align-items: flex-start;
        }
        
        /* Make the cart-items take up more space */
        .cart-items {
            flex: 2;
            min-width: 0;
        }

        /* Make the cart-summary fixed width and positioned on the right */
        .cart-summary {
            flex: 1;
            max-width: 350px;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 90px;
        }
        
        /* Make the book items smaller */
        .cart-item {
            display: flex;
            background-color: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }
        
        .cart-item-image {
            width: 60px;
            height: 90px;
            overflow: hidden;
            border-radius: 5px;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        
        
        .cart-item:hover {
            transform: translateY(-3px);
        }
        
        
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .cart-item-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 5px;
            color: #2c3e50;
        }
        
        .cart-item-author {
            color: #7f8c8d;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        
        .cart-item-price {
            font-weight: bold;
            color: #3498db;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .cart-item-type {
            display: inline-block;
            background-color: #e9f7fe;
            color: #3498db;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-bottom: 8px;
            align-self: flex-start;
        }
        
        .cart-item-type.rent {
            background-color: #fef5e9;
            color: #f39c12;
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: auto;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quantity-btn {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .quantity-btn:hover {
            background-color: #f8f9fa;
            border-color: #ccc;
        }
        
        .quantity-input {
            width: 35px;
            height: 28px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-size: 14px;
        }
        
        .remove-btn {
            color: #e74c3c;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        
        .remove-btn:hover {
            background-color: #fee;
        }
        
        .summary-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 10px;
            color: #2c3e50;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #7f8c8d;
            font-size: 0.85rem;
        }
        
        .summary-row.total {
            font-weight: bold;
            font-size: 15px;
            color: #2c3e50;
            padding-top: 8px;
            border-top: 1px solid #eee;
            margin-top: 8px;
        }
        
        .checkout-btn {
            display: block;
            width: 100%;
            padding: 8px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            margin-top: 12px;
        }
        
        .continue-shopping {
            display: inline-block;
            margin-top: 15px;
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        
        .continue-shopping:hover {
            color: #2980b9;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        
        .empty-cart i {
            font-size: 40px;
            color: #bbb;
            margin-bottom: 15px;
        }
        
        .empty-cart h3 {
            margin: 0 0 8px;
            color: #2c3e50;
            font-size: 1.3rem;
        }
        
        .empty-cart p {
            color: #7f8c8d;
            margin: 0 0 15px;
            font-size: 0.9rem;
        }
        
        .empty-cart-btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .empty-cart-btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        /* Checkout form styles */
        .checkout-form {
            display: none;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            margin-top: 25px;
            width: 100%;
        }
        
        .checkout-form h3 {
            margin: 0 0 15px;
            color: #2c3e50;
            font-size: 1.3rem;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .back-to-cart {
            padding: 8px 16px;
            background-color: #95a5a6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .back-to-cart:hover {
            background-color: #7f8c8d;
        }
        
        .place-order-btn {
            padding: 8px 16px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .place-order-btn:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        /* Success message styles */
        .success-message {
            display: none;
            text-align: center;
            padding: 40px 20px;
            background-color: #d4edda;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            margin-top: 25px;
            width: 100%;
        }
        
        .success-message i {
            font-size: 40px;
            color: #2ecc71;
            margin-bottom: 15px;
        }
        
        .success-message h3 {
            margin: 0 0 8px;
            color: #2c3e50;
            font-size: 1.3rem;
        }
        
        .success-message p {
            color: #7f8c8d;
            margin: 0 0 15px;
            font-size: 0.9rem;
        }
        
        .success-message .order-number {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .success-message .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            margin: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .success-message .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        /* Order summary card */
        .order-summary-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid #3498db;
        }
        
        .order-summary-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .order-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .cart-content {
                flex-direction: column;
            }
            
            .cart-summary {
                width: 100%;
                max-width: 100%;
                position: static;
            }
            
            .cart-item {
                flex-direction: row;
            }
            
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .cart-item {
                flex-direction: column;
            }
            
            .cart-item-image {
                width: 100%;
                height: 150px;
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .cart-item-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .quantity-control {
                width: 100%;
                justify-content: space-between;
            }
            
            .remove-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Coupon code section */
        .coupon-section {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .coupon-form {
            display: flex;
            gap: 5px;
        }
        
        .coupon-input {
            flex: 1;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .apply-coupon-btn {
            padding: 6px 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        
        .apply-coupon-btn:hover {
            background-color: #2980b9;
        }
        
        /* Estimated delivery */
        .estimated-delivery {
            margin-top: 10px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        .estimated-delivery i {
            color: #3498db;
            margin-right: 5px;
        }
        
        /* Payment method selection */
        .payment-methods {
            margin-top: 15px;
        }
        
        .payment-method-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .payment-method-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .payment-method-option {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .payment-method-option:hover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }
        
        .payment-method-option.selected {
            border-color: #3498db;
            background-color: #e9f7fe;
        }
        
        .payment-method-radio {
            margin-right: 10px;
        }
        
        .payment-method-label {
            font-size: 0.9rem;
            color: #2c3e50;
        }
        
        .payment-method-icon {
            margin-left: auto;
            font-size: 1.2rem;
            color: #3498db;
        }
        
        /* Alert styles */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Telebirr payment badge */
        .telebirr-badge {
            display: inline-flex;
            align-items: center;
            background-color: #f0f8ff;
            border: 1px solid #b3d7ff;
            border-radius: 4px;
            padding: 4px 8px;
            margin-left: 10px;
            font-size: 0.8rem;
            color: #0066cc;
        }
        
        .telebirr-badge img {
            height: 16px;
            margin-right: 5px;
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
                        <span class="cart-count" id="cartCountBadge"><?php echo count($_SESSION['cart']); ?></span>
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

    <!-- Cart Content -->
    <section class="cart-section">
        <div class="cart-container">
            <div class="section-header">
                <h2>Your Shopping Cart</h2>
                <p>Review your items and proceed to checkout</p>
            </div>
            
            <?php if (isset($_SESSION['cart_error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['cart_error']; ?>
                    <?php unset($_SESSION['cart_error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['cart_success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['cart_success']; ?>
                    <?php unset($_SESSION['cart_success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="cart-content" id="cartContent">
                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Looks like you haven't added any books to your cart yet.</p>
                        <a href="explore.php" class="empty-cart-btn">Browse Books</a>
                    </div>
                <?php else: ?>
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item-image">
                                    <img src="uploads/books/<?php echo $item['book']['image']; ?>" alt="<?php echo $item['book']['title']; ?>" onerror="this.src='images/default_book.jpg'">
                                </div>
                                <div class="cart-item-details">
                                    <h3 class="cart-item-title"><?php echo $item['book']['title']; ?></h3>
                                    <p class="cart-item-author">by <?php echo $item['book']['author']; ?></p>
                                    <?php if ($item['is_rental']): ?>
                                        <span class="cart-item-type rent">Rental - <?php echo $item['rental_days']; ?> days</span>
                                    <?php else: ?>
                                        <span class="cart-item-type">Purchase</span>
                                    <?php endif; ?>
                                    <p class="cart-item-price"><?php echo $currency; ?> <?php echo number_format($item['subtotal'], 2); ?></p>
                                    <div class="cart-item-actions">
                                        <div class="quantity-control">
                                            <form method="post" action="cart.php" style="display: flex; align-items: center;">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="book_id" value="<?php echo $item['book_id']; ?>">
                                                <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" class="quantity-btn" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>-</button>
                                                <input type="text" class="quantity-input" value="<?php echo $item['quantity']; ?>" readonly>
                                                <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" class="quantity-btn">+</button>
                                            </form>
                                        </div>
                                        <form method="post" action="cart.php">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="book_id" value="<?php echo $item['book_id']; ?>">
                                            <button type="submit" class="remove-btn">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="cart-summary">
                        <h3 class="summary-title">Order Summary</h3>
                        <div class="summary-row">
                            <span>Subtotal (<?php echo count($cart_items); ?> items)</span>
                            <span><?php echo $currency; ?> <?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <?php if ($shipping_cost > 0): ?>
                                <span><?php echo $currency; ?> <?php echo number_format($shipping_cost, 2); ?></span>
                            <?php else: ?>
                                <span class="text-success">Free</span>
                            <?php endif; ?>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?php echo $currency; ?> <?php echo number_format($final_total, 2); ?></span>
                        </div>
                        <button class="checkout-btn" onclick="showCheckoutForm()">Proceed to Checkout</button>
                        <a href="explore.php" class="continue-shopping">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                        
                        <div class="estimated-delivery">
                            <i class="fas fa-truck"></i> Estimated delivery: 3-5 business days
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Checkout Form -->
            <div class="checkout-form" id="checkoutForm">
                <h3>Shipping Information</h3>
                <form id="orderForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">Full Name</label>
                            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Delivery Address</label>
                        <textarea id="address" name="address" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="payment-methods">
                        <h4 class="payment-method-title">Payment Method</h4>
                        <div class="payment-method-options">
                            <div class="payment-method-option selected">
                                <input type="radio" name="payment_method" id="chapa" value="chapa" checked class="payment-method-radio">
                                <label for="chapa" class="payment-method-label">Pay with Telebirr</label>
                                <span class="telebirr-badge">
                                    <img src="assets/telebirr-logo.png" alt="Telebirr"> Telebirr
                                </span>
                                <i class="fas fa-mobile-alt payment-method-icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-summary-card">
                        <div class="order-summary-title">Order Summary</div>
                        <div id="checkout-summary">
                            <div class="order-summary-item">
                                <span>Items (<?php echo count($cart_items); ?>):</span>
                                <span><?php echo $currency; ?> <?php echo number_format($total, 2); ?></span>
                            </div>
                            <div class="order-summary-item">
                                <span>Shipping:</span>
                                <?php if ($shipping_cost > 0): ?>
                                    <span><?php echo $currency; ?> <?php echo number_format($shipping_cost, 2); ?></span>
                                <?php else: ?>
                                    <span>Free</span>
                                <?php endif; ?>
                            </div>
                            <div class="order-summary-item" style="font-weight: bold; margin-top: 5px;">
                                <span>Total:</span>
                                <span><?php echo $currency; ?> <?php echo number_format($final_total, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="back-to-cart" onclick="showCartView()">Back to Cart</button>
                        <button type="button" class="place-order-btn" onclick="continueToPayment()">Continue to Payment</button>
                    </div>
                </form>
            </div>
            
            <!-- Success Message -->
            <div class="success-message" id="successMessage">
                <i class="fas fa-check-circle"></i>
                <h3>Order Placed Successfully!</h3>
                <p>Your order has been received and is being processed.</p>
                <p>Order Number: <span class="order-number" id="orderNumber">ORD-12345</span></p>
                <div>
                    <a href="explore.php" class="btn">Continue Shopping</a>
                    <a href="customer_order.php" class="btn">View My Orders</a>
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
        
        // Show checkout form
        function showCheckoutForm() {
            document.getElementById('cartContent').style.display = 'none';
            document.getElementById('checkoutForm').style.display = 'block';
            document.getElementById('successMessage').style.display = 'none';
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Show cart view
        function showCartView() {
            document.getElementById('cartContent').style.display = 'flex';
            document.getElementById('checkoutForm').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';
        }
        
        // Continue to payment
        function continueToPayment() {
            // Get form data
            const fullname = document.getElementById('fullname').value;
            const phone = document.getElementById('phone').value;
            const address = document.getElementById('address').value;
            const paymentMethod = 'chapa'; // Only Chapa/Telebirr is available
            
            // Validate form
            if (!fullname || !phone || !address) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Validate phone number format (10 digits)
            if (!/^\d{10}$/.test(phone)) {
                alert('Please enter a valid 10-digit phone number');
                return;
            }
            
            // Show loading state
            const button = document.querySelector('.place-order-btn');
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            // Prepare order data
            const orderData = {
                customer_info: {
                    fullname: fullname,
                    phone: phone,
                    address: address
                },
                payment_method: paymentMethod,
                total_amount: <?php echo $final_total; ?>
            };
            
            // Send order data to server to initialize payment
            fetch('initialize_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orderData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                // Reset button state
                button.innerHTML = 'Continue to Payment';
                button.disabled = false;
                
                console.log('Response data:', data);
                
                if (data.success) {
                    // Redirect to Chapa checkout page
                    console.log('Redirecting to:', data.checkout_url);
                    window.location.href = data.checkout_url;
                } else {
                    // Show detailed error message
                    const errorMessage = data.message || 'Unknown error occurred';
                    alert('Error: ' + errorMessage);
                    console.error('Payment initialization failed:', errorMessage);
                }
            })
            .catch(error => {
                // Reset button state
                button.innerHTML = 'Continue to Payment';
                button.disabled = false;
                
                console.error('Fetch error:', error);
                alert('An error occurred while processing your order. Please try again. Error details: ' + error.message);
            });
        }
        
        // Check if returning from payment
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const orderId = urlParams.get('order_id');
            
            if (status === 'success' && orderId) {
                // Show success message
                document.getElementById('cartContent').style.display = 'none';
                document.getElementById('checkoutForm').style.display = 'none';
                document.getElementById('successMessage').style.display = 'block';
                document.getElementById('orderNumber').textContent = orderId;
            }
        });
    </script>
</body>
</html>
