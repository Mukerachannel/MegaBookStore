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

// Check if the book belongs to the seller
try {
    $query = "SELECT * FROM books WHERE id = ? AND seller_id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("ii", $book_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $book = $result->fetch_assoc();
            
            // Delete the book
            $delete_query = "DELETE FROM books WHERE id = ? AND seller_id = ?";
            $stmt = $conn->prepare($delete_query);
            
            if ($stmt) {
                $stmt->bind_param("ii", $book_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Delete the book image if it's not the default image
                    if ($book['image'] != 'images/default_book.jpg' && file_exists('../' . $book['image'])) {
                        unlink('../' . $book['image']);
                    }
                    
                    $_SESSION['success'] = "Book deleted successfully!";
                } else {
                    $_SESSION['error'] = "Failed to delete book. Please try again.";
                }
            } else {
                $_SESSION['error'] = "Database error. Please try again.";
            }
        } else {
            $_SESSION['error'] = "Book not found or you don't have permission to delete it.";
        }
    } else {
        $_SESSION['error'] = "Database error. Please try again.";
    }
} catch (Exception $e) {
    error_log("Error deleting book: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again.";
}

header("Location: seller_books.php");
exit;
?>