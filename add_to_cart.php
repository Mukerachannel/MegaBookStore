<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=books.php");
    exit;
}

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid book selection.";
    header("Location: books.php");
    exit;
}

$book_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

// If action is view, show the add to cart options page
if ($action === 'view') {
    // Get book details
    $book_query = "SELECT * FROM books WHERE id = ?";
    $stmt = $conn->prepare($book_query);
    
    if (!$stmt) {
        $_SESSION['error'] = "Database error. Please try again.";
        header("Location: books.php");
        exit;
    }
    
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Book not found.";
        header("Location: books.php");
        exit;
    }
    
    $book = $result->fetch_assoc();
    
    // Check if book is already in cart
    $cart_check = "SELECT * FROM cart WHERE user_id = ? AND book_id = ?";
    $stmt = $conn->prepare($cart_check);
    $stmt->bind_param("ii", $user_id, $book_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    $in_cart = $cart_result->num_rows > 0;
    $cart_item = $in_cart ? $cart_result->fetch_assoc() : null;
    
    // Get default image path
    $default_image = 'images/default_book.jpg';
    
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
    
    // Include the HTML for the add to cart options page
    include 'add_to_cart_option.php';
    exit;
}

// If action is add, process the form submission
if ($action === 'add') {
    // Get form data
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $purchase_type = isset($_POST['purchase_type']) ? $_POST['purchase_type'] : 'buy';
    $rental_days = isset($_POST['rental_days']) ? intval($_POST['rental_days']) : 0;
    
    // Validate inputs
    if ($quantity <= 0) {
        $_SESSION['error'] = "Quantity must be at least 1.";
        header("Location: add_to_cart.php?id=$book_id");
        exit;
    }
    
    if ($purchase_type === 'rent' && $rental_days <= 0) {
        $_SESSION['error'] = "Please select a valid rental period.";
        header("Location: add_to_cart.php?id=$book_id");
        exit;
    }
    
    // Check if book exists and has enough stock
    $book_query = "SELECT * FROM books WHERE id = ?";
    $stmt = $conn->prepare($book_query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Book not found.";
        header("Location: books.php");
        exit;
    }
    
    $book = $result->fetch_assoc();
    
    if ($book['stock'] < $quantity) {
        $_SESSION['error'] = "Not enough stock available. Only " . $book['stock'] . " copies available.";
        header("Location: add_to_cart.php?id=$book_id");
        exit;
    }
    
    // Check if book is already in cart
    $cart_check = "SELECT * FROM cart WHERE user_id = ? AND book_id = ?";
    $stmt = $conn->prepare($cart_check);
    $stmt->bind_param("ii", $user_id, $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing cart item
        $cart_item = $result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        if ($book['stock'] < $new_quantity) {
            $_SESSION['error'] = "Not enough stock available. Only " . $book['stock'] . " copies available.";
            header("Location: add_to_cart.php?id=$book_id");
            exit;
        }
        
        $update_query = "UPDATE cart SET quantity = ?, is_rental = ?, rental_days = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $is_rental = ($purchase_type === 'rent') ? 1 : 0;
        $stmt->bind_param("iiii", $new_quantity, $is_rental, $rental_days, $cart_item['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Cart updated successfully!";
            header("Location: cart.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to update cart. Please try again.";
            header("Location: add_to_cart.php?id=$book_id");
            exit;
        }
    } else {
        // Add new cart item
        $insert_query = "INSERT INTO cart (user_id, book_id, quantity, is_rental, rental_days) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $is_rental = ($purchase_type === 'rent') ? 1 : 0;
        $stmt->bind_param("iiiii", $user_id, $book_id, $quantity, $is_rental, $rental_days);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Book added to cart successfully!";
            header("Location: cart.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to add book to cart. Please try again.";
            header("Location: add_to_cart.php?id=$book_id");
            exit;
        }
    }
}

// If we get here, something went wrong
$_SESSION['error'] = "Invalid action.";
header("Location: books.php");
exit;
?>