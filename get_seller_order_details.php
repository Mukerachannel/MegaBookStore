<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get seller ID
$seller_id = $_SESSION['user_id'];

// Get order ID from request
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Get order details
    $query = "SELECT o.*, u.fullname as customer_name, u.phone as customer_phone, u.email as customer_email
              FROM orders o
              JOIN users u ON o.customer_id = u.id
              WHERE o.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    
    // Get order items that belong to this seller
    $query = "SELECT oi.*, b.title, b.author, b.image, b.price as book_price
              FROM order_items oi
              JOIN books b ON oi.book_id = b.id
              WHERE oi.order_id = ? AND b.seller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $order_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    // Check if this seller has any items in this order
    if (count($items) === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No items from this seller in this order']);
        exit;
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
