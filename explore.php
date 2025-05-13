<?php
session_start();
require_once 'db.php';

// Initialize variables
$books = [];
$categories = [];
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Default to newest

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add to cart action
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
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
        } else {
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
    }
    
    // Redirect to cart page
    header('Location: cart.php');
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get categories from categories table
try {
    $category_query = "SELECT * FROM categories ORDER BY name";
    $result = $conn->query($category_query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get books - modified to show all books regardless of seller
try {
    // Build query based on filters
    $query = "SELECT b.*, u.fullname as seller_name, u.email as seller_email, c.name as category_name
             FROM books b
             LEFT JOIN users u ON b.seller_id = u.id
             LEFT JOIN categories c ON b.category_id = c.id
             WHERE 1=1"; // Always true condition to start
    
    // Add category filter if selected
    if ($selected_category > 0) {
        $query .= " AND b.category_id = ?";
    }
    
    // Add sorting
    switch ($sort_by) {
        case 'price-low':
            $query .= " ORDER BY b.price ASC";
            break;
        case 'price-high':
            $query .= " ORDER BY b.price DESC";
            break;
        case 'popularity':
            $query .= " ORDER BY b.popularity DESC";
            break;
        default: // newest
            $query .= " ORDER BY b.created_at DESC";
            break;
    }
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Bind category parameter if needed
        if ($selected_category > 0) {
            $stmt->bind_param("i", $selected_category);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $books[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching books: " . $e->getMessage());
}

// Define the default image path
$default_image = 'images/default_book.jpg';

// Check if the user is admin
$is_admin = isset($_SESSION['email']) && $_SESSION['email'] === 'admin@gmail.com';

// Count items in cart
$cart_count = count($_SESSION['cart']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        /* Books page specific styles */
        body {
            padding-top: 70px;
        }
        
        .books-section {
            padding: 30px 0;
            background-color: #f8f9fa;
        }
        
        .books-container {
            width: 95%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            margin-bottom: 5px;
        }
        
        .section-header p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .books-filter {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .search-form {
            display: flex;
            gap: 8px;
        }
        
        .search-form input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 200px;
            font-size: 0.9rem;
        }
        
        .search-btn {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .book-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .book-cover {
            height: 160px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
            position: relative;
        }
        
        .book-cover img {
            max-height: 100%;
            width: auto;
            object-fit: contain;
        }
        
        .book-info {
            padding: 12px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        
        .book-info h3 {
            font-size: 0.95rem;
            margin: 0 0 5px;
            color: #2c3e50;
            height: 38px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .author {
            color: #7f8c8d;
            margin-bottom: 8px;
            font-size: 0.8rem;
        }
        
        .price {
            font-weight: bold;
            color: #3498db;
            margin-bottom: 3px;
            font-size: 0.9rem;
        }
        
        .rent-price {
            color: #e67e22;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }
        
        .category-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: rgba(52, 152, 219, 0.8);
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .book-actions {
            margin-top: auto;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 10px;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            text-decoration: none;
            width: 100%;
        }
        
        .order-btn {
            background-color: #3498db;
            color: white;
        }
        
        .order-btn:hover {
            background-color: #2980b9;
        }
        
        .login-redirect-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .login-redirect-btn:hover {
            background-color: #c0392b;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 40px;
            color: #bbb;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin: 0 0 8px;
            color: #2c3e50;
            font-size: 1.3rem;
        }
        
        .empty-state p {
            color: #7f8c8d;
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Cart icon styles */
        .cart-icon {
            position: relative;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Profile icon styles */
        .profile-icon {
            background-color: #3498db;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            transition: background-color 0.3s ease;
        }
        
        .profile-icon:hover {
            background-color: #2980b9;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5rem;
        }
        
        .modal-body {
            margin-bottom: 15px;
        }
        
        .book-details-flex {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .book-image-container {
            width: 100px;
            height: 150px;
            overflow: hidden;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }
        
        .book-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .book-details-info {
            flex: 1;
        }
        
        .book-details-info h3 {
            margin: 0 0 8px;
            color: #2c3e50;
            font-size: 1.2rem;
        }
        
        .book-details-info p {
            margin: 0 0 6px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .book-details-info .price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #3498db;
            margin: 8px 0;
        }
        
        .book-details-info .rent-price {
            font-size: 0.95rem;
            color: #e67e22;
            margin: 4px 0 8px;
        }
        
        .order-options {
            margin-top: 15px;
        }
        
        .order-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .order-type-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid #3498db;
            border-radius: 5px;
            background-color: white;
            color: #3498db;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .order-type-btn.active {
            background-color: #3498db;
            color: white;
        }
        
        .rent-options {
            display: none;
            margin-top: 12px;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .rent-options.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .rent-summary {
            margin-top: 12px;
            padding: 8px;
            background-color: #e9f7fe;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }
        
        .add-to-cart-btn {
            background-color: #2ecc71;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .add-to-cart-btn:hover {
            background-color: #27ae60;
        }
        
        .cancel-btn {
            background-color: #e74c3c;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .cancel-btn:hover {
            background-color: #c0392b;
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 0.8rem;
            margin-top: 4px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .books-filter {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-form {
                width: 100%;
            }
            
            .search-form {
                width: 100%;
            }
            
            .search-form input {
                flex: 1;
            }
            
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            }
            
            .book-cover {
                height: 140px;
            }
        }
        
        /* Featured book ribbon */
        .featured-ribbon {
            position: absolute;
            top: 0;
            left: 0;
            background-color: #f39c12;
            color: white;
            padding: 3px 8px;
            font-size: 0.7rem;
            font-weight: 600;
            transform: rotate(-45deg) translateX(-20px) translateY(-5px);
            width: 80px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Stock indicator */
        .stock-indicator {
            position: absolute;
            bottom: 5px;
            left: 5px;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 3px;
            background-color: rgba(0,0,0,0.6);
            color: white;
        }
        
        .in-stock {
            background-color: rgba(46, 204, 113, 0.8);
        }
        
        .low-stock {
            background-color: rgba(243, 156, 18, 0.8);
        }
        
        .out-of-stock {
            background-color: rgba(231, 76, 60, 0.8);
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
                <li><a href="explore.php" class="nav-link active">Books</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="index.php#contact-form" class="nav-link">Contact</a></li>
                
                <?php if ($is_logged_in): ?>
                    <li>
                        <a href="cart.php" class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count" id="cartCountBadge"><?php echo $cart_count; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="customer_dashboard.php" class="profile-icon">
                            <i class="fas fa-user"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link login-btn">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Books Content -->
    <section class="books-section">
        <div class="books-container">
            <div class="section-header">
                <h2>Browse Books</h2>
                <p>Discover our collection of books from various sellers</p>
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
            
            <div class="books-filter">
                <form action="explore.php" method="get" class="filter-form">
                    <div class="filter-group">
                        <label for="category_id">Category:</label>
                        <select id="category_id" name="category_id" onchange="this.form.submit()">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($selected_category == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort">Sort By:</label>
                        <select id="sort" name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Newest Arrivals</option>
                            <option value="price-low" <?php echo ($sort_by == 'price-low') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price-high" <?php echo ($sort_by == 'price-high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                </form>
                
                <div class="search-form">
                    <input type="text" id="searchInput" placeholder="Search for books...">
                    <button class="search-btn"><i class="fas fa-search"></i> Search</button>
                </div>
            </div>
            
            <div class="books-grid">
                <?php if (count($books) > 0): ?>
                    <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-cover">
                                <?php 
                                // Process the image path
                                if (!empty($book['image'])) {
                                    // Check if the image path already contains a directory
                                    if (strpos($book['image'], '/') !== false) {
                                        $image_path = $book['image']; // Use the full path
                                    } else {
                                        $image_path = 'assets/' . $book['image']; // Prepend the directory
                                    }
                                } else {
                                    $image_path = $default_image;
                                }
                                
                                // Add featured ribbon for popular books
                                if (isset($book['popularity']) && $book['popularity'] > 10) {
                                    echo '<div class="featured-ribbon">Popular</div>';
                                }
                                
                                // Add stock indicator
                                if (isset($book['stock'])) {
                                    $stock_class = 'in-stock';
                                    $stock_text = 'In Stock';
                                    
                                    if ($book['stock'] <= 0) {
                                        $stock_class = 'out-of-stock';
                                        $stock_text = 'Out of Stock';
                                    } elseif ($book['stock'] < 5) {
                                        $stock_class = 'low-stock';
                                        $stock_text = 'Low Stock';
                                    }
                                    
                                    echo '<div class="stock-indicator ' . $stock_class . '">' . $stock_text . '</div>';
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo $default_image; ?>';">
                                
                                <?php if (!empty($book['category_name'])): ?>
                                    <span class="category-badge"><?php echo htmlspecialchars($book['category_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="book-info">
                                <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="author">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <p class="price">ETB<?php echo number_format($book['price'], 2); ?></p>
                                
                                <?php if (isset($book['is_rentable']) && $book['is_rentable'] && isset($book['rent_price_per_day']) && $book['rent_price_per_day'] > 0): ?>
                                    <p class="rent-price">Rent: ETB<?php echo number_format($book['rent_price_per_day'], 2); ?>/day</p>
                                <?php endif; ?>
                                
                                <div class="book-actions">
                                    <button class="btn order-btn" 
                                            onclick="handleOrderClick(<?php echo $book['id']; ?>, 
                                            '<?php echo addslashes($book['title']); ?>', 
                                            '<?php echo addslashes($book['author']); ?>', 
                                            <?php echo $book['price']; ?>, 
                                            <?php echo isset($book['rent_price_per_day']) ? $book['rent_price_per_day'] : 0; ?>, 
                                            <?php echo isset($book['is_rentable']) ? $book['is_rentable'] : 0; ?>, 
                                            '<?php echo addslashes($image_path); ?>')">
                                        <i class="fas fa-shopping-cart"></i> Order
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>No books found</h3>
                        <p>Try adjusting your filters or check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeOrderModal()">&times;</span>
            
            <div class="modal-header">
                <h2>Order Book</h2>
            </div>
            
            <div class="modal-body">
                <div class="book-details-flex">
                    <div class="book-image-container">
                        <img id="modalBookImage" src="/placeholder.svg" alt="Book Cover">
                    </div>
                    
                    <div class="book-details-info">
                        <h3 id="modalBookTitle"></h3>
                        <p id="modalBookAuthor"></p>
                        <p class="price" id="modalBookPrice"></p>
                        <p class="rent-price" id="modalRentPrice"></p>
                    </div>
                </div>
                
                <div class="order-options">
                    <div class="order-type-selector">
                        <button type="button" class="order-type-btn active" id="buyButton" onclick="selectOrderType('buy')">Buy</button>
                        <button type="button" class="order-type-btn" id="rentButton" onclick="selectOrderType('rent')">Rent</button>
                    </div>
                    
                    <div class="rent-options" id="rentOptions">
                        <div class="form-group">
                            <label for="rentDays">Number of Days (Minimum 15 days):</label>
                            <input type="number" id="rentDays" min="15" value="15" onchange="updateRentSummary()">
                            <span id="rentDaysError" class="error-message"></span>
                        </div>
                        
                        <div class="rent-summary" id="rentSummary">
                            <p>Total for <span id="rentDaysDisplay">15</span> days: <span id="totalRentPrice">ETB0.00</span></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeOrderModal()">Cancel</button>
                <button type="button" class="add-to-cart-btn" id="addToCartBtn" onclick="addToCart()">Add to Cart</button>
            </div>
        </div>
    </div>

    <!-- Hidden form for adding to cart -->
    <form id="addToCartForm" method="post" action="explore.php" style="display: none;">
        <input type="hidden" name="action" value="add_to_cart">
        <input type="hidden" name="book_id" id="form_book_id">
        <input type="hidden" name="quantity" id="form_quantity" value="1">
        <input type="hidden" name="is_rental" id="form_is_rental" value="0">
        <input type="hidden" name="rental_days" id="form_rental_days" value="">
    </form>

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
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const bookCards = document.querySelectorAll('.book-card');
            
            bookCards.forEach(function(card) {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const author = card.querySelector('.author').textContent.toLowerCase();
                const category = card.querySelector('.category-badge') ? 
                                card.querySelector('.category-badge').textContent.toLowerCase() : '';
                
                if (title.includes(searchValue) || author.includes(searchValue) || category.includes(searchValue)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Ensure images load properly
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.book-cover img');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    // If image fails to load, replace with default
                    this.src = '<?php echo $default_image; ?>';
                    // Prevent infinite error loop
                    this.onerror = null;
                });
            });
        });
        
        // Order Modal Variables
        let currentBookId = 0;
        let currentBookTitle = '';
        let currentBookAuthor = '';
        let currentBookPrice = 0;
        let currentRentPrice = 0;
        let currentBookImage = '';
        let isRentable = false;
        let orderType = 'buy'; // Default to buy
        
        // Open Order Modal
        function openOrderModal(bookId, title, author, price, rentPrice, rentable, imagePath) {
            currentBookId = bookId;
            currentBookTitle = title;
            currentBookAuthor = author;
            currentBookPrice = price;
            currentRentPrice = rentPrice;
            currentBookImage = imagePath;
            isRentable = rentable == 1;
            
            // Set modal content
            document.getElementById('modalBookTitle').textContent = title;
            document.getElementById('modalBookAuthor').textContent = 'by ' + author;
            document.getElementById('modalBookPrice').textContent = 'ETB' + price.toFixed(2);
            document.getElementById('modalBookImage').src = imagePath;
            
            // Set rent price if available
            const modalRentPrice = document.getElementById('modalRentPrice');
            if (isRentable && currentRentPrice > 0) {
                modalRentPrice.textContent = 'Rent: ETB' + rentPrice.toFixed(2) + '/day';
                modalRentPrice.style.display = 'block';
            } else {
                modalRentPrice.style.display = 'none';
            }
            
            // Reset order type to buy
            selectOrderType('buy');
            
            // Show/hide rent button based on rentability
            const rentButton = document.getElementById('rentButton');
            if (isRentable && currentRentPrice > 0) {
                rentButton.style.display = 'block';
                updateRentSummary();
            } else {
                rentButton.style.display = 'none';
            }
            
            // Show the modal
            document.getElementById('orderModal').style.display = 'block';
        }
        
        // Close Order Modal
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        // Select Order Type (Buy or Rent)
        function selectOrderType(type) {
            orderType = type;
            
            // Update UI
            if (type === 'buy') {
                document.getElementById('buyButton').classList.add('active');
                document.getElementById('rentButton').classList.remove('active');
                document.getElementById('rentOptions').classList.remove('active');
            } else {
                document.getElementById('buyButton').classList.remove('active');
                document.getElementById('rentButton').classList.add('active');
                document.getElementById('rentOptions').classList.add('active');
                updateRentSummary();
            }
        }
        
        // Update Rent Summary
        function updateRentSummary() {
            const rentDays = parseInt(document.getElementById('rentDays').value);
            const rentDaysError = document.getElementById('rentDaysError');
            
            // Validate minimum days
            if (rentDays < 15) {
                rentDaysError.textContent = 'Minimum rental period is 15 days';
                return;
            } else {
                rentDaysError.textContent = '';
            }
            
            // Calculate total rent
            const totalRent = currentRentPrice * rentDays;
            document.getElementById('rentDaysDisplay').textContent = rentDays;
            document.getElementById('totalRentPrice').textContent = 'ETB' + totalRent.toFixed(2);
        }
        
        // Add to Cart
        function addToCart() {
            // Validate rent days if renting
            if (orderType === 'rent') {
                const rentDays = parseInt(document.getElementById('rentDays').value);
                if (rentDays < 15) {
                    document.getElementById('rentDaysError').textContent = 'Minimum rental period is 15 days';
                    return;
                }
            }
            
            // Set form values
            document.getElementById('form_book_id').value = currentBookId;
            document.getElementById('form_quantity').value = 1;
            
            if (orderType === 'rent') {
                document.getElementById('form_is_rental').value = 1;
                document.getElementById('form_rental_days').value = document.getElementById('rentDays').value;
            } else {
                document.getElementById('form_is_rental').value = 0;
                document.getElementById('form_rental_days').value = '';
            }
            
            // Submit the form
            document.getElementById('addToCartForm').submit();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target == modal) {
                closeOrderModal();
            }
        }

        // Handle order button click - check login status
        function handleOrderClick(bookId, title, author, price, rentPrice, isRentable, imagePath) {
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            
            if (isLoggedIn) {
                // User is logged in, open the order modal
                openOrderModal(bookId, title, author, price, rentPrice, isRentable, imagePath);
            } else {
                // User is not logged in, redirect to login page
                window.location.href = 'login.php?redirect=explore.php';
            }
        }
    </script>
</body>
</html>
s