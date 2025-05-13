<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Get order ID from request
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Get order details
    $query = "SELECT o.* FROM orders o WHERE o.id = ? AND o.customer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    
    // Get order items with seller information
    $query = "SELECT oi.*, b.title, b.author, b.image, b.seller_id, u.fullname as seller_name 
              FROM order_items oi 
              JOIN books b ON oi.book_id = b.id 
              JOIN users u ON b.seller_id = u.id
              WHERE oi.order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $order['items'] = $items;
    
    // Return order details
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'order' => $order]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
