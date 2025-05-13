<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: login.php");
    exit;
}

// Get categories
$categories = [];
try {
    $query = "SELECT * FROM categories ORDER BY name";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $rent_price_per_day = floatval($_POST['rent_price_per_day'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $is_rentable = isset($_POST['is_rentable']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    if (empty($author)) {
        $errors[] = "Author is required.";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero.";
    }
    
    if ($is_rentable && $rent_price_per_day <= 0) {
        $errors[] = "Rent price must be greater than zero if the book is rentable.";
    }
    
    if ($stock < 0) {
        $errors[] = "Stock cannot be negative.";
    }
    
    // Handle image upload
    $image_filename = 'default_book.jpg'; // Default image
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            // Create a unique filename
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            
            // Create assets directory if it doesn't exist
            $upload_dir = 'assets/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Store only the filename in the database
                $image_filename = $file_name;
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid image format. Only JPEG, PNG, and GIF are allowed.";
        }
    }
    
    // If no errors, insert book
    if (empty($errors)) {
        try {
            // Check if the books table has the rent_price_per_day column
            $check_column = $conn->query("SHOW COLUMNS FROM books LIKE 'rent_price_per_day'");
            
            if ($check_column->num_rows == 0) {
                // Add rent_price_per_day column if it doesn't exist
                $conn->query("ALTER TABLE books ADD COLUMN rent_price_per_day DECIMAL(10,2) DEFAULT 0");
            }
            
            // Check if the books table has the is_rentable column
            $check_column = $conn->query("SHOW COLUMNS FROM books LIKE 'is_rentable'");
            
            if ($check_column->num_rows == 0) {
                // Add is_rentable column if it doesn't exist
                $conn->query("ALTER TABLE books ADD COLUMN is_rentable TINYINT(1) DEFAULT 0");
            }
            
            $query = "INSERT INTO books (title, author, description, price, rent_price_per_day, stock, image, category_id, seller_id, is_rentable) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            
            if ($stmt) {
                $stmt->bind_param("sssddisiii", $title, $author, $description, $price, $rent_price_per_day, $stock, $image_filename, $category_id, $_SESSION['user_id'], $is_rentable);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Book added successfully!";
                    header("Location: manage_books.php");
                    exit;
                } else {
                    $errors[] = "Failed to add book: " . $stmt->error;
                }
            } else {
                $errors[] = "Database error: " . $conn->error;
            }
        } catch (Exception $e) {
            error_log("Error adding book: " . $e->getMessage());
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// If we don't have any categories, let's create some
if (empty($categories)) {
    try {
        // Check if categories table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'categories'");
        
        if ($table_check->num_rows == 0) {
            // Create categories table
            $conn->query("CREATE TABLE IF NOT EXISTS categories (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
        
        // Add sample categories
        $sample_categories = ["Fiction", "Non-Fiction", "Science", "History", "Biography", "Self-Help", "Business", "Technology"];
        
        foreach ($sample_categories as $category) {
            $conn->query("INSERT INTO categories (name) VALUES ('$category')");
        }
        
        // Fetch categories again
        $result = $conn->query("SELECT * FROM categories ORDER BY name");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error creating categories: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - Seller Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .form-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .image-preview {
            width: 100%;
            max-width: 200px;
            height: 250px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }
        
        .btn-secondary {
            background-color: #7f8c8d;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .error-list {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error-list ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .rent-price-field {
            display: none;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Mega Books</h2>
                <span>Seller Panel</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="seller_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="manage_books.php">
                            <i class="fas fa-book"></i>
                            <span>Manage Books</span>
                        </a>
                    </li>
                    <li>
                        <a href="seller_order.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="seller_profile.php">
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
                <div class="user-menu">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                        <img src="asset/profile.png" alt="User Avatar">
                        <div class="dropdown-menu">
                            <a href="seller_profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Add Book Content -->
            <div class="dashboard-content">
                <h1>Add New Book</h1>
                <p>Add a new book to your inventory</p>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="error-list">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="form-container">
                    <form action="add_book.php" method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Book Title *</label>
                                <input type="text" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="author">Author *</label>
                                <input type="text" id="author" name="author" value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">Price (ETB) *</label>
                                <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
                            </div>
                              : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock">Stock *</label>
                                <input type="number" id="stock" name="stock" min="0" value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_rentable" name="is_rentable" <?php echo isset($_POST['is_rentable']) ? 'checked' : ''; ?> onchange="toggleRentPrice()">
                                    <label for="is_rentable">Available for Rent</label>
                                </div>
                            </div>
                            
                            <div class="form-group rent-price-field" id="rentPriceField">
                                <label for="rent_price_per_day">Rent Price Per Day (ETB)</label>
                                <input type="number" id="rent_price_per_day" name="rent_price_per_day" step="0.01" min="0" value="<?php echo isset($_POST['rent_price_per_day']) ? htmlspecialchars($_POST['rent_price_per_day']) : '0'; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id">
                                    <option value="0">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Book Cover Image</label>
                                <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                                <div class="image-preview" id="imagePreview">
                                    <img src="assets/default_book.jpg" alt="Book Cover Preview" id="previewImg">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Book Description</label>
                            <textarea id="description" name="description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" class="btn-primary">Add Book</button>
                            <a href="manage_books.php" class="btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function toggleRentPrice() {
            const isRentable = document.getElementById('is_rentable').checked;
            const rentPriceField = document.getElementById('rentPriceField');
            
            if (isRentable) {
                rentPriceField.style.display = 'block';
            } else {
                rentPriceField.style.display = 'none';
                document.getElementById('rent_price_per_day').value = '0';
            }
        }
        
        // Initialize rent price field visibility
        document.addEventListener('DOMContentLoaded', function() {
            toggleRentPrice();
        });
    </script>
    <script src="dashboard.js"></script>
</body>
</html>
