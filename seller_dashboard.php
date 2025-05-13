<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: login.php");
    exit;
}

// Initialize variables with default values
$book_count = 0;
$order_count = 0;
$revenue = 0;
$user_data = [
    'fullname' => $_SESSION['fullname'] ?? 'Seller',
    'email' => $_SESSION['email'] ?? '',
    'phone' => '',
    'address' => ''
];

// Get user data
try {
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);

    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Get book count
try {
    $book_query = "SELECT COUNT(*) as total_books FROM books WHERE seller_id = ?";
    $stmt = $conn->prepare($book_query);

    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $book_count = $result->fetch_assoc()['total_books'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching book count: " . $e->getMessage());
}

// Get order count and revenue
try {
    $order_query = "SELECT COUNT(DISTINCT o.id) as total_orders, SUM(oi.price * oi.quantity) as total_revenue
                   FROM orders o
                   JOIN order_items oi ON o.id = oi.order_id
                   JOIN books b ON oi.book_id = b.id
                   WHERE b.seller_id = ?";

    $stmt = $conn->prepare($order_query);

    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $order_count = $data['total_orders'] ?? 0;
            $revenue = $data['total_revenue'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching order data: " . $e->getMessage());
}

// Get recent orders
$recent_orders = [];
try {
    $orders_query = "SELECT o.id, o.order_date, o.total_amount, o.status, 
                    u.fullname as customer_name, b.title as book_title, oi.quantity, oi.price
                    FROM orders o
                    JOIN users u ON o.customer_id = u.id
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN books b ON oi.book_id = b.id
                    WHERE b.seller_id = ?
                    ORDER BY o.order_date DESC
                    LIMIT 5";

    $stmt = $conn->prepare($orders_query);

    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_orders[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent orders: " . $e->getMessage());
}

// Get notifications (new orders in the last 7 days)
$notifications = [];
try {
    $notifications_query = "SELECT o.id, o.order_date, o.status, u.fullname as customer_name, 
                           COUNT(oi.id) as item_count, SUM(oi.quantity) as total_quantity,
                           o.total_amount, o.viewed
                           FROM orders o
                           JOIN users u ON o.customer_id = u.id
                           JOIN order_items oi ON o.id = oi.order_id
                           JOIN books b ON oi.book_id = b.id
                           WHERE b.seller_id = ? AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           GROUP BY o.id
                           ORDER BY o.order_date DESC
                           LIMIT 10";

    $stmt = $conn->prepare($notifications_query);

    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if (!$notification['viewed']) {
        $unread_count++;
    }
}

// Handle mark notification as read
if (isset($_GET['mark_read']) && !empty($_GET['mark_read'])) {
    $order_id = intval($_GET['mark_read']);
    
    try {
        $update_query = "UPDATE orders SET viewed = 1 WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
    }
}

// Handle view order details
if (isset($_GET['view_order']) && !empty($_GET['view_order'])) {
    $order_id = intval($_GET['view_order']);
    
    // Mark as read
    try {
        $update_query = "UPDATE orders SET viewed = 1 WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
    }
    
    // Get order details
    $order_details = null;
    $order_items = [];
    
    try {
        // Get order header
        $order_query = "SELECT o.*, u.fullname as customer_name, u.email as customer_email, u.phone as customer_phone
                       FROM orders o
                       JOIN users u ON o.customer_id = u.id
                       WHERE o.id = ?";
        
        $stmt = $conn->prepare($order_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $order_details = $result->fetch_assoc();
                
                // Get order items
                $items_query = "SELECT oi.*, b.title, b.author, b.image
                              FROM order_items oi
                              JOIN books b ON oi.book_id = b.id
                              WHERE oi.order_id = ? AND b.seller_id = ?";
                
                $stmt = $conn->prepare($items_query);
                
                if ($stmt) {
                    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $order_items[] = $row;
                        }
                    }
                }
            } else {
                $_SESSION['error'] = "Order not found or you don't have permission to view it.";
                header("Location: seller_dashboard.php");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching order details: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while fetching order details.";
    }
}

// Check if we're on the manage books page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// If we're on the manage books page, get books for this seller
$books = [];
if ($page === 'manage_books') {
    try {
        $query = "SELECT * FROM books WHERE seller_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
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
        $_SESSION['error'] = "An error occurred while fetching books.";
    }
}

// Get categories for book management
$categories = [];
if ($page === 'manage_books' || $page === 'add_book' || $page === 'edit_book') {
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
}

// Handle delete book
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $book_id = intval($_GET['delete']);
    
    try {
        // Check if book belongs to this seller
        $check_query = "SELECT id FROM books WHERE id = ? AND seller_id = ?";
        $stmt = $conn->prepare($check_query);
        
        if ($stmt) {
            $stmt->bind_param("ii", $book_id, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                // Delete the book
                $delete_query = "DELETE FROM books WHERE id = ?";
                $stmt = $conn->prepare($delete_query);
                $stmt->bind_param("i", $book_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Book deleted successfully!";
                } else {
                    $_SESSION['error'] = "Failed to delete book.";
                }
            } else {
                $_SESSION['error'] = "You don't have permission to delete this book.";
            }
        }
    } catch (Exception $e) {
        error_log("Error deleting book: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the book.";
    }
    
    header("Location: seller_dashboard.php?page=manage_books");
    exit;
}

// Handle add book form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'add_book') {
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
                    header("Location: seller_dashboard.php?page=manage_books");
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

// Handle edit book
if ($page === 'edit_book') {
    // Check if book ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $_SESSION['error'] = "No book specified for editing.";
        header("Location: seller_dashboard.php?page=manage_books");
        exit;
    }

    $book_id = intval($_GET['id']);

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
                header("Location: seller_dashboard.php?page=manage_books");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching book: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while fetching the book.";
        header("Location: seller_dashboard.php?page=manage_books");
        exit;
    }

    // Handle form submission for edit
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
        $image_filename = $book['image']; // Keep existing image by default
        
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
        
        // If no errors, update book
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
                
                $query = "UPDATE books SET 
                          title = ?, 
                          author = ?, 
                          description = ?, 
                          price = ?, 
                          rent_price_per_day = ?, 
                          stock = ?, 
                          image = ?, 
                          category_id = ?,
                          is_rentable = ?
                          WHERE id = ? AND seller_id = ?";
                
                $stmt = $conn->prepare($query);
                
                if ($stmt) {
                    $stmt->bind_param("sssddisiiii", $title, $author, $description, $price, $rent_price_per_day, $stock, $image_filename, $category_id, $is_rentable, $book_id, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Book updated successfully!";
                        header("Location: seller_dashboard.php?page=manage_books");
                        exit;
                    } else {
                        $errors[] = "Failed to update book: " . $stmt->error;
                    }
                } else {
                    $errors[] = "Database error: " . $conn->error;
                }
            } catch (Exception $e) {
                error_log("Error updating book: " . $e->getMessage());
                $errors[] = "An error occurred: " . $e->getMessage();
            }
        }
    }
}

// Handle view book
if ($page === 'view_book') {
    // Check if book ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $_SESSION['error'] = "No book specified for viewing.";
        header("Location: seller_dashboard.php?page=manage_books");
        exit;
    }

    $book_id = intval($_GET['id']);

    // Get book details
    $book = null;
    try {
        $query = "SELECT b.*, c.name as category_name 
                  FROM books b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  WHERE b.id = ? AND b.seller_id = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("ii", $book_id, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $book = $result->fetch_assoc();
            } else {
                $_SESSION['error'] = "Book not found or you don't have permission to view it.";
                header("Location: seller_dashboard.php?page=manage_books");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching book: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while fetching the book.";
        header("Location: seller_dashboard.php?page=manage_books");
        exit;
    }
}

// Check if orders table has viewed column
try {
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'viewed'");
    
    if ($check_column->num_rows == 0) {
        // Add viewed column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN viewed TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    error_log("Error checking/adding viewed column: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Additional styles for seller dashboard */
        .welcome-banner {
            background-color: #3498db;
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .welcome-banner h2 {
            margin: 0 0 10px;
            font-size: 24px;
        }
        
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
        }
        
        .stat-icon.books {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .stat-icon.books i {
            color: #3498db;
        }
        
        .stat-icon.orders {
            background-color: rgba(46, 204, 113, 0.1);
        }
        
        .stat-icon.orders i {
            color: #2ecc71;
        }
        
        .stat-icon.revenue {
            background-color: rgba(241, 196, 15, 0.1);
        }
        
        .stat-icon.revenue i {
            color: #f1c40f;
        }
        
        .book-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-edit {
            background-color: #f39c12;
        }
        
        .btn-delete {
            background-color: #e74c3c;
        }
        
        .add-book-btn {
            display: inline-block;
            background-color: #2ecc71;
            color: #fff;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
            text-decoration: none;
        }
        
        .add-book-btn i {
            margin-right: 5px;
        }
        
        /* Book Management Styles */
        .books-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .book-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .book-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .book-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .book-details {
            padding: 15px;
        }
        
        .book-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 5px;
            color: #333;
        }
        
        .book-author {
            font-size: 14px;
            color: #666;
            margin: 0 0 10px;
        }
        
        .book-price {
            font-size: 16px;
            font-weight: 600;
            color: #2ecc71;
            margin: 0 0 5px;
        }
        
        .book-rent {
            font-size: 14px;
            color: #3498db;
            margin: 0 0 10px;
        }
        
        .book-stock {
            font-size: 14px;
            color: #666;
            margin: 0 0 15px;
        }
        
        .btn-view, .btn-edit, .btn-delete {
            padding: 5px 10px;
            border-radius: 5px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-view {
            background-color: #3498db;
        }
        
        .btn-view i, .btn-edit i, .btn-delete i {
            margin-right: 5px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #adb5bd;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        /* Form Styles */
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
        
        /* View Book Styles */
        .book-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .book-image-large {
            width: 100%;
            height: 400px;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .book-image-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .book-details-large h1 {
            font-size: 24px;
            margin: 0 0 10px;
            color: #333;
        }
        
        .book-author-large {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .book-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .meta-item h3 {
            font-size: 16px;
            margin: 0 0 5px;
            color: #666;
        }
        
        .meta-item p {
            font-size: 18px;
            margin: 0;
            font-weight: 600;
            color: #333;
        }
        
        .meta-item.price p {
            color: #2ecc71;
        }
        
        .meta-item.rent p {
            color: #3498db;
        }
        
        .book-description {
            margin-top: 20px;
        }
        
        .book-description h2 {
            font-size: 20px;
            margin: 0 0 15px;
            color: #333;
        }
        
        .book-description p {
            line-height: 1.6;
            color: #555;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: #3498db;
        }
        
        .btn-warning {
            background-color: #f39c12;
        }
        
        .btn-danger {
            background-color: #e74c3c;
        }
        
        /* Notification Styles */
        .notification-icon {
            position: relative;
            cursor: pointer;
            margin-right: 20px;
        }
        
        .notification-count {
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
        
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
            overflow: hidden;
        }
        
        .notification-dropdown.show {
            display: block;
        }
        
        .notification-header {
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        
        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #ebf7ff;
        }
        
        .notification-item.unread:hover {
            background-color: #daeeff;
        }
        
        .notification-content {
            display: flex;
            align-items: flex-start;
        }
        
        .notification-icon-wrapper {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(52, 152, 219, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .notification-icon-wrapper i {
            color: #3498db;
            font-size: 18px;
        }
        
        .notification-text {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin: 0 0 5px;
            color: #333;
            font-size: 14px;
        }
        
        .notification-desc {
            color: #666;
            margin: 0;
            font-size: 13px;
        }
        
        .notification-time {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .notification-footer {
            padding: 10px 15px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .notification-footer a {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
        }
        
        .notification-footer a:hover {
            text-decoration: underline;
        }
        
        .no-notifications {
            padding: 30px 15px;
            text-align: center;
            color: #999;
        }
        
        .no-notifications i {
            font-size: 32px;
            margin-bottom: 10px;
            color: #ddd;
        }
        
        /* Order Details Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 50px auto;
            width: 90%;
            max-width: 800px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .order-info-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .order-info-card h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #666;
        }
        
        .order-info-card p {
            margin: 5px 0;
            color: #333;
        }
        
        .order-items {
            margin-top: 20px;
        }
        
        .order-items h3 {
            margin: 0 0 15px;
            font-size: 18px;
            color: #333;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .order-item-image {
            width: 60px;
            height: 80px;
            border-radius: 5px;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-title {
            font-weight: 600;
            margin: 0 0 5px;
            color: #333;
        }
        
        .order-item-author {
            color: #666;
            margin: 0 0 5px;
            font-size: 14px;
        }
        
        .order-item-price {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .order-item-quantity {
            margin-left: auto;
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            color: #666;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .book-container {
                grid-template-columns: 1fr;
            }
            
            .book-image-large {
                height: 300px;
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .notification-dropdown {
                width: 300px;
                right: -100px;
            }
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
                    <li class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                        <a href="seller_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="<?php echo in_array($page, ['manage_books', 'add_book', 'edit_book', 'view_book']) ? 'active' : ''; ?>">
                        <a href="seller_dashboard.php?page=manage_books">
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
                    <input type="text" placeholder="Search for books..." id="searchInput">
                </div>
                <div class="user-menu">
                    <!-- Notification Icon -->
                    <div class="notification-icon" id="notificationIcon">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-count"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                        
                        <!-- Notification Dropdown -->
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <a href="#" id="markAllRead">Mark all as read</a>
                            </div>
                            <div class="notification-list">
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo !$notification['viewed'] ? 'unread' : ''; ?>" 
                                             onclick="viewOrderDetails(<?php echo $notification['id']; ?>)">
                                            <div class="notification-content">
                                                <div class="notification-icon-wrapper">
                                                    <i class="fas fa-shopping-bag"></i>
                                                </div>
                                                <div class="notification-text">
                                                    <h4 class="notification-title">New Order #<?php echo $notification['id']; ?></h4>
                                                    <p class="notification-desc">
                                                        <?php echo htmlspecialchars($notification['customer_name']); ?> ordered 
                                                        <?php echo $notification['total_quantity']; ?> item(s) for 
                                                        ETB<?php echo number_format($notification['total_amount'], 2); ?>
                                                    </p>
                                                    <p class="notification-time">
                                                        <?php echo date('M d, Y h:i A', strtotime($notification['order_date'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-notifications">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>No new notifications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="notification-footer">
                                <a href="seller_order.php">View All Orders</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user_data['fullname']); ?></span>
                        <a href="seller_profile.php">  <img src="asset/profile.png" alt="User"> </a>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                            echo $_SESSION['success']; 
                            unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                            echo $_SESSION['error']; 
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['view_order'])): ?>
                    <!-- Order Details View -->
                    <h1>Order #<?php echo $order_id; ?> Details</h1>
                    <p>View complete order information</p>
                    
                    <div class="order-info">
                        <div class="order-info-card">
                            <h3>Order Information</h3>
                            <p><strong>Order ID:</strong> #<?php echo $order_details['id']; ?></p>
                            <p><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order_details['order_date'])); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge-table badge-<?php echo $order_details['status']; ?>">
                                    <?php echo ucfirst($order_details['status']); ?>
                                </span>
                            </p>
                            <p><strong>Total Amount:</strong> ETB<?php echo number_format($order_details['total_amount'], 2); ?></p>
                            <p><strong>Payment Method:</strong> <?php echo ucfirst($order_details['payment_method'] ?? 'Not specified'); ?></p>
                        </div>
                        
                        <div class="order-info-card">
                            <h3>Customer Information</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($order_details['customer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order_details['customer_email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order_details['customer_phone']); ?></p>
                            <p><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order_details['shipping_address']); ?></p>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h3>Order Items</h3>
                        
                        <?php if (count($order_items) > 0): ?>
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item">
                                    <div class="order-item-image">
                                        <img src="<?php echo !empty($item['image']) ? 'assets/' . $item['image'] : 'assets/default_book.jpg'; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    </div>
                                    <div class="order-item-details">
                                        <h4 class="order-item-title"><?php echo htmlspecialchars($item['title']); ?></h4>
                                        <p class="order-item-author">By <?php echo htmlspecialchars($item['author']); ?></p>
                                        <p class="order-item-price">
                                            ETB<?php echo number_format($item['price'], 2); ?>
                                            <?php if ($item['is_rental']): ?>
                                                (Rental - <?php echo $item['rental_days']; ?> days)
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="order-item-quantity">
                                        Qty: <?php echo $item['quantity']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No items found for this order.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-buttons">
    <a href="seller_order.php?view_order=<?php echo $order_id; ?>" class="btn btn-primary">
        <i class="fas fa-external-link-alt"></i> Go to Order Details
    </a>
    <a href="seller_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>
                
                <?php elseif ($page === 'dashboard'): ?>
                    <!-- Dashboard Home -->
                    <div class="welcome-banner">
                        <h2>Welcome, <?php echo htmlspecialchars($user_data['fullname']); ?>!</h2>
                        <p>Manage your books, track orders, and grow your business.</p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon books">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Books</h3>
                                <p><?php echo $book_count; ?></p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon orders">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Orders</h3>
                                <p><?php echo $order_count; ?></p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon revenue">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Revenue</h3>
                                <p>ETB<?php echo number_format($revenue, 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="recent-section">
                        <h2>Recent Orders</h2>
                        
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Book</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_orders) > 0): ?>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['id']; ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($order['book_title']); ?></td>
                                                <td><?php echo $order['quantity']; ?></td>
                                                <td>ETB<?php echo number_format($order['price'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                                <td>
                                                    <span class="badge-table badge-<?php echo $order['status']; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="seller_dashboard.php?view_order=<?php echo $order['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center;">No orders found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                <?php elseif ($page === 'manage_books'): ?>
                    <!-- Manage Books Page -->
                    <div class="books-header">
                        <div>
                            <h1>Manage Books</h1>
                            <p>Add, edit, and manage your book inventory</p>
                        </div>
                        <a href="seller_dashboard.php?page=add_book" class="add-book-btn"><i class="fas fa-plus"></i> Add New Book</a>
                    </div>
                    
                    <?php if (count($books) > 0): ?>
                        <div class="books-grid" id="booksGrid">
                            <?php foreach ($books as $book): ?>
                                <div class="book-card">
                                    <div class="book-image">
                                        <img src="<?php echo !empty($book['image']) ? 'assets/' . $book['image'] : 'assets/default_book.jpg'; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                    </div>
                                    <div class="book-details">
                                        <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                        <p class="book-author">By <?php echo htmlspecialchars($book['author']); ?></p>
                                        <p class="book-price">ETB<?php echo number_format($book['price'], 2); ?></p>
                                        <p class="book-rent">
                                            <?php if ($book['rent_price_per_day'] > 0): ?>
                                                Rent: ETB<?php echo number_format($book['rent_price_per_day'], 2); ?>/day
                                            <?php else: ?>
                                                Not available for rent
                                            <?php endif; ?>
                                        </p>
                                        <p class="book-stock  ?>
                                        </p>
                                        <p class="book-stock">
                                            <?php if ($book['stock'] > 0): ?>
                                                <span class="in-stock">In Stock (<?php echo $book['stock']; ?>)</span>
                                            <?php else: ?>
                                                <span class="out-of-stock">Out of Stock</span>
                                            <?php endif; ?>
                                        </p>
                                        <div class="book-actions">
                                            <a href="seller_dashboard.php?page=view_book&id=<?php echo $book['id']; ?>" class="btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="seller_dashboard.php?page=edit_book&id=<?php echo $book['id']; ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="seller_dashboard.php?page=manage_books&delete=<?php echo $book['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this book?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <h3>No Books Found</h3>
                            <p>You haven't added any books to your inventory yet.</p>
                            <a href="seller_dashboard.php?page=add_book" class="add-book-btn"><i class="fas fa-plus"></i> Add Your First Book</a>
                        </div>
                    <?php endif; ?>
                
                <?php elseif ($page === 'add_book'): ?>
                    <!-- Add Book Page -->
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
                        <form action="seller_dashboard.php?page=add_book" method="post" enctype="multipart/form-data">
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
                                <a href="seller_dashboard.php?page=manage_books" class="btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                
                <?php elseif ($page === 'edit_book'): ?>
                    <!-- Edit Book Page -->
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
                        <form action="seller_dashboard.php?page=edit_book&id=<?php echo $book_id; ?>" method="post" enctype="multipart/form-data">
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
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_rentable" name="is_rentable" <?php echo $book['is_rentable'] ? 'checked' : ''; ?> onchange="toggleRentPrice()">
                                        <label for="is_rentable">Available for Rent</label>
                                    </div>
                                </div>
                                
                                <div class="form-group rent-price-field" id="rentPriceField">
                                    <label for="rent_price_per_day">Rent Price Per Day (ETB)</label>
                                    <input type="number" id="rent_price_per_day" name="rent_price_per_day" step="0.01" min="0" value="<?php echo htmlspecialchars($book['rent_price_per_day']); ?>">
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
                                        <img src="<?php echo !empty($book['image']) ? 'assets/' . $book['image'] : 'assets/default_book.jpg'; ?>" alt="Book Cover Preview" id="previewImg">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="description">Book Description</label>
                                <textarea id="description" name="description"><?php echo htmlspecialchars($book['description']); ?></textarea>
                            </div>
                            
                            <div class="btn-container">
                                <button type="submit" class="btn-primary">Update Book</button>
                                <a href="seller_dashboard.php?page=manage_books" class="btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                
                <?php elseif ($page === 'view_book'): ?>
                    <!-- View Book Page -->
                    <div class="book-container">
                        <div class="book-image-large">
                            <img src="<?php echo !empty($book['image']) ? 'assets/' . $book['image'] : 'assets/default_book.jpg'; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                        </div>
                        
                        <div class="book-details-large">
                            <h1><?php echo htmlspecialchars($book['title']); ?></h1>
                            <p class="book-author-large">By <?php echo htmlspecialchars($book['author']); ?></p>
                            
                            <div class="book-meta">
                                <div class="meta-item price">
                                    <h3>Price</h3>
                                    <p>ETB<?php echo number_format($book['price'], 2); ?></p>
                                </div>
                                
                                <div class="meta-item rent">
                                    <h3>Rent Price</h3>
                                    <p>
                                        <?php if ($book['rent_price_per_day'] > 0): ?>
                                            ETB<?php echo number_format($book['rent_price_per_day'], 2); ?>/day
                                        <?php else: ?>
                                            Not available for rent
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div class="meta-item">
                                    <h3>Stock</h3>
                                    <p><?php echo $book['stock']; ?> copies</p>
                                </div>
                                
                                <div class="meta-item">
                                    <h3>Category</h3>
                                    <p><?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?></p>
                                </div>
                            </div>
                            
                            <div class="book-description">
                                <h2>Description</h2>
                                <p><?php echo nl2br(htmlspecialchars($book['description'] ?? 'No description available.')); ?></p>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="seller_dashboard.php?page=edit_book&id=<?php echo $book_id; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Book
                                </a>
                                <a href="seller_dashboard.php?page=manage_books&delete=<?php echo $book_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this book?');">
                                    <i class="fas fa-trash"></i> Delete Book
                                </a>
                                <a href="seller_dashboard.php?page=manage_books" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Books
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <button class="modal-close" onclick="closeOrderModal()">&times;</button>
            </div>
            <div class="modal-body" id="orderModalBody">
                <!-- Order details will be loaded here via AJAX -->
                <p>Loading order details...</p>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeOrderModal()">Close</button>
            </div>
        </div>
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
        
        // Notification dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
            const notificationIcon = document.getElementById('notificationIcon');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (notificationIcon && notificationDropdown) {
                notificationIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                    }
                });
                
                // Mark all as read
                const markAllReadBtn = document.getElementById('markAllRead');
                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Send AJAX request to mark all as read
                        fetch('mark_all_read.php', {
                            method: 'POST'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove unread class from all notifications
                                const unreadItems = document.querySelectorAll('.notification-item.unread');
                                unreadItems.forEach(item => {
                                    item.classList.remove('unread');
                                });
                                
                                // Hide notification count
                                const notificationCount = document.querySelector('.notification-count');
                                if (notificationCount) {
                                    notificationCount.style.display = 'none';
                                }
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    });
                }
            }
            
            // Initialize rent price field visibility
            if (document.getElementById('is_rentable')) {
                toggleRentPrice();
            }
            
            // Search functionality for books grid
            const searchInput = document.getElementById('searchInput');
            if (searchInput && document.getElementById('booksGrid')) {
                searchInput.addEventListener('keyup', function() {
                    const searchValue = this.value.toLowerCase();
                    const bookCards = document.querySelectorAll('.book-card');
                    
                    bookCards.forEach(card => {
                        const title = card.querySelector('.book-title').textContent.toLowerCase();
                        const author = card.querySelector('.book-author').textContent.toLowerCase();
                        
                        if (title.includes(searchValue) || author.includes(searchValue)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
        
        // View order details
        function viewOrderDetails(orderId) {
            window.location.href = 'seller_dashboard.php?view_order=' + orderId;
        }
        
        // Open order modal
        function openOrderModal(orderId) {
            const modal = document.getElementById('orderModal');
            const modalBody = document.getElementById('orderModalBody');
            
            modal.style.display = 'block';
            modalBody.innerHTML = '<p>Loading order details...</p>';
            
            // Load order details via AJAX
            fetch('get_order_details.php?id=' + orderId)
            .then(response => response.text())
            .then(data => {
                modalBody.innerHTML = data;
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<p>Error loading order details. Please try again.</p>';
            });
        }
        
        // Close order modal
        function closeOrderModal() {
            const modal = document.getElementById('orderModal');
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('orderModal');
            if (e.target === modal) {
                closeOrderModal();
            }
        });
    </script>
    <script src="dashboard.js"></script>
</body>
</html>
