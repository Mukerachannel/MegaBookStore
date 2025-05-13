<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add to Cart - <?php echo htmlspecialchars($book['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .add-to-cart-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .book-details {
            display: flex;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .book-image {
            width: 150px;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 20px;
        }
        
        .book-info {
            flex: 1;
        }
        
        .book-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px;
            color: #333;
        }
        
        .book-author {
            font-size: 16px;
            color: #666;
            margin: 0 0 15px;
        }
        
        .book-price {
            font-size: 20px;
            font-weight: 600;
            color: #e74c3c;
            margin: 0 0 15px;
        }
        
        .book-description {
            color: #666;
            margin: 0 0 15px;
            line-height: 1.5;
        }
        
        .purchase-options {
            padding: 20px;
        }
        
        .option-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 15px;
            color: #333;
        }
        
        .purchase-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .purchase-types {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .purchase-type {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .purchase-type.active {
            border-color: #3498db;
            background-color: #ebf7ff;
        }
        
        .purchase-type i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .purchase-type.active i {
            color: #3498db;
        }
        
        .purchase-type h3 {
            margin: 0 0 5px;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .purchase-type p {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        
        .rental-options {
            display: none;
            margin-top: 15px;
        }
        
        .rental-options.active {
            display: block;
        }
        
        .rental-periods {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .rental-period {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .rental-period.active {
            border-color: #3498db;
            background-color: #ebf7ff;
        }
        
        .rental-period h4 {
            margin: 0 0 5px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        
        .rental-period p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            color: #333;
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-add-cart, .btn-cancel {
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
        
        .btn-add-cart {
            background-color: #3498db;
            color: white;
            border: none;
            flex: 2;
        }
        
        .btn-cancel {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            flex: 1;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .in-cart-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Mega Books</h2>
                <span>Customer Panel</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="books.php">
                            <i class="fas fa-book"></i>
                            <span>Browse Books</span>
                        </a>
                    </li>
                    <li>
                        <a href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Cart</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php">
                            <i class="fas fa-shopping-bag"></i>
                            <span>My Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <header class="top-nav">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search for books...">
                </div>
                <div class="header-icons">
                    <a href="cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </a>
                    <a href="profile.php" class="profile-icon">
                        <i class="fas fa-user"></i>
                    </a>
                </div>
            </header>

            <!-- Add to Cart Content -->
            <div class="dashboard-content">
                <h1>Add to Cart</h1>
                <p>Choose your purchase options</p>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="error-message">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($in_cart): ?>
                    <div class="in-cart-message">
                        This book is already in your cart. You can update the quantity or options.
                    </div>
                <?php endif; ?>
                
                <div class="add-to-cart-container">
                    <div class="book-details">
                        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-image" onerror="this.onerror=null; this.src='<?php echo $default_image; ?>';">
                        
                        <div class="book-info">
                            <h2 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h2>
                            <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                            <p class="book-price">ETB <?php echo number_format($book['price'], 2); ?></p>
                            <p class="book-description">
                                <?php 
                                if (!empty($book['description'])) {
                                    echo htmlspecialchars(substr($book['description'], 0, 300));
                                    if (strlen($book['description']) > 300) {
                                        echo '...';
                                    }
                                } else {
                                    echo 'No description available.';
                                }
                                ?>
                            </p>
                            <p>Available Stock: <strong><?php echo $book['stock']; ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="purchase-options">
                        <h3 class="option-title">Purchase Options</h3>
                        
                        <form action="add_to_cart.php?id=<?php echo $book_id; ?>&action=add" method="post" class="purchase-form">
                            <div class="purchase-types">
                                <div class="purchase-type active" data-type="buy">
                                    <i class="fas fa-shopping-bag"></i>
                                    <h3>Buy</h3>
                                    <p>Purchase the book permanently</p>
                                </div>
                                
                                <?php if ($book['is_rentable']): ?>
                                <div class="purchase-type" data-type="rent">
                                    <i class="fas fa-clock"></i>
                                    <h3>Rent</h3>
                                    <p>Borrow the book for a period</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="purchase_type" id="purchase_type" value="buy">
                            
                            <?php if ($book['is_rentable']): ?>
                            <div class="rental-options">
                                <label>Select Rental Period:</label>
                                <div class="rental-periods">
                                    <div class="rental-period" data-days="15">
                                        <h4>15 Days</h4>
                                        <p>ETB 15.00</p>
                                    </div>
                                    <div class="rental-period" data-days="30">
                                        <h4>30 Days</h4>
                                        <p>ETB 30.00</p>
                                    </div>
                                    <div class="rental-period" data-days="50">
                                        <h4>50 Days</h4>
                                        <p>ETB 50.00</p>
                                    </div>
                                </div>
                                <input type="hidden" name="rental_days" id="rental_days" value="0">
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="quantity">Quantity:</label>
                                <div class="quantity-selector">
                                    <div class="quantity-btn" id="decrease-quantity">-</div>
                                    <input type="number" id="quantity" name="quantity" class="quantity-input" value="<?php echo $in_cart ? $cart_item['quantity'] : 1; ?>" min="1" max="<?php echo $book['stock']; ?>">
                                    <div class="quantity-btn" id="increase-quantity">+</div>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="submit" class="btn-add-cart">
                                    <?php echo $in_cart ? 'Update Cart' : 'Add to Cart'; ?>
                                </button>
                                <a href="books.php" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Purchase type selection
            const purchaseTypes = document.querySelectorAll('.purchase-type');
            const purchaseTypeInput = document.getElementById('purchase_type');
            const rentalOptions = document.querySelector('.rental-options');
            
            purchaseTypes.forEach(type => {
                type.addEventListener('click', function() {
                    // Remove active class from all types
                    purchaseTypes.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked type
                    this.classList.add('active');
                    
                    // Update hidden input
                    const purchaseType = this.getAttribute('data-type');
                    purchaseTypeInput.value = purchaseType;
                    
                    // Show/hide rental options
                    if (purchaseType === 'rent') {
                        rentalOptions.classList.add('active');
                    } else {
                        rentalOptions.classList.remove('active');
                    }
                });
            });
            
            // Rental period selection
            const rentalPeriods = document.querySelectorAll('.rental-period');
            const rentalDaysInput = document.getElementById('rental_days');
            
            rentalPeriods.forEach(period => {
                period.addEventListener('click', function() {
                    // Remove active class from all periods
                    rentalPeriods.forEach(p => p.classList.remove('active'));
                    
                    // Add active class to clicked period
                    this.classList.add('active');
                    
                    // Update hidden input
                    const days = this.getAttribute('data-days');
                    rentalDaysInput.value = days;
                });
            });
            
            // Quantity selector
            const decreaseBtn = document.getElementById('decrease-quantity');
            const increaseBtn = document.getElementById('increase-quantity');
            const quantityInput = document.getElementById('quantity');
            const maxStock = <?php echo $book['stock']; ?>;
            
            decreaseBtn.addEventListener('click', function() {
                let quantity = parseInt(quantityInput.value);
                if (quantity > 1) {
                    quantityInput.value = quantity - 1;
                }
            });
            
            increaseBtn.addEventListener('click', function() {
                let quantity = parseInt(quantityInput.value);
                if (quantity < maxStock) {
                    quantityInput.value = quantity + 1;
                }
            });
            
            // Initialize rental period if already in cart
            <?php if ($in_cart && $cart_item['is_rental']): ?>
            document.querySelector('.purchase-type[data-type="rent"]').click();
            const rentalDays = <?php echo $cart_item['rental_days']; ?>;
            const rentalPeriod = document.querySelector(`.rental-period[data-days="${rentalDays}"]`);
            if (rentalPeriod) {
                rentalPeriod.click();
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>