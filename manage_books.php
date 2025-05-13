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
    $_SESSION['error'] = "No book specified for editing.";
    header("Location: manage_books.php");
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
            header("Location: manage_books.php");
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching book: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching the book.";
    header("Location: manage_books.php");
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
        $}