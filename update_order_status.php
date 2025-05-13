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

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate data
if (!$data || !isset($data['order_id']) || !isset($data['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Validate order status
$valid_statuses = ['pending', 'accepted', 'processing', 'shipped', 'delivered', 'rejected', 'cancelled'];
if (!in_array($data['status'], $valid_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Validate order status - sellers cannot mark as delivered
if ($data['status'] === 'delivered') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Only customers can mark orders as delivered']);
    exit;
}

// Check if the order exists and contains items from this seller
try {
    $query = "SELECT COUNT(*) as count
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN books b ON oi.book_id = b.id
              WHERE o.id = ? AND b.seller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $data['order_id'], $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found or does not contain items from this seller']);
        exit;
    }
    
    // Check if orders table has updated_by column
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'updated_by'");
    
    if ($check_column->num_rows == 0) {
        // Add updated_by column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN updated_by INT(11) DEFAULT NULL");
    }
    
    // Check if orders table has customer_viewed column
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_viewed'");
    
    if ($check_column->num_rows == 0) {
        // Add customer_viewed column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN customer_viewed TINYINT(1) DEFAULT 0");
    }
    
    // Update order status
    $query = "UPDATE orders SET status = ?, updated_by = ?, customer_viewed = 0 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $data['status'], $seller_id, $data['order_id']);
    $stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
