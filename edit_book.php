<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: login.php");
    exit;
}

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Book ID is required.";
    header("Location: seller_books.php");
    exit;
}

$book_id = $_GET['id'];

// Get book details
$book = null;
try {
    $query = "SELECT * FROM books WHERE id = ? AND seller_id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("ii", $book_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $book = $result->fetch_assoc();
        } else {
            $_SESSION['error'] = "Book not found or you don't have permission to edit it.";
            header("Location: books.php");
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching book: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again.";
    header("Location: seller_books.php");
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
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    
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
    
    if ($stock < 0) {
        $errors[] = "Stock cannot be negative.";
    }
    
    // Handle image upload
    $image_path = $book['image']; // Keep existing image by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $file_name = time() . '_' . $_FILES['image']['name'];
            $upload_dir = '../uploads/books/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_path = 'uploads/books/' . $file_name;
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid image format. Only JPEG, PNG, and GIF are allowed.";
        }
    }
    
    // If no errors, update book
    if (empty($errors)) {
        try {
            $query = "UPDATE books SET title = ?, author = ?, description = ?, price = ?, stock = ?, image = ?, category_id = ? WHERE id = ? AND seller_id = ?";
            
            $stmt = $conn->prepare($query);
            
            if ($stmt) {
                $stmt->bind_param("sssdssiis", $title, $author, $description, $price, $stock, $image_path, $category_id, $book_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Book updated successfully!";
                    header("Location: books.php");
                    exit;
                } else {
                    $errors[] = "Failed to update book. Please try again.";
                }
            } else {
                $errors[] = "Database error. Please try again.";
            }
        } catch (Exception $e) {
            error_log("Error updating book: " . $e->getMessage());
            $errors[] = "An error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book - Seller Dashboard</title>
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
                        <a href="seller_books.php">
                            <i class="fas fa-book"></i>
                            <span>Manage Books</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php">
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

            <!-- Edit Book Content -->
            <div class="dashboard-content">
                <h1>Edit Book</h1>
                <p>Update book information</p>
                
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
                    <form action="edit_book.php?id=<?php echo $book_id; ?>" method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Book Title *</label>
                                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="author">Author *</label>
                                <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">Price (ETB) *</label>
                                <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?php echo htmlspecialchars($book['price']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock">Stock *</label>
                                <input type="number" id="stock" name="stock" min="0" value="<?php echo htmlspecialchars($book['stock']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id">
                                    <option value="0">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo ($book['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Book Cover Image</label>
                                <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                                <div class="image-preview" id="imagePreview">
                                    <img src="../<?php echo htmlspecialchars($book['image']); ?>" alt="Book Cover Preview" id="previewImg">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Book Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($book['description']); ?></textarea>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" class="btn-primary">Update Book</button>
                            <a href="books.php" class="btn-secondary">Cancel</a>
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
    </script>
    <script src="dashboard.js"></script>
</body>
</html>