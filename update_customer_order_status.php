<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get customer ID
$customer_id = $_SESSION['user_id'];

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate data
if (!$data || !isset($data['order_id']) || !isset($data['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Validate order status - customers can only mark as delivered
if ($data['status'] !== 'delivered') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Customers can only mark orders as delivered']);
    exit;
}

// Check if the order exists and belongs to this customer
try {
    // Check if orders table has updated_by column
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'updated_by'");
    
    if ($check_column->num_rows == 0) {
        // Add updated_by column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN updated_by INT(11) DEFAULT NULL");
    }
    
    // Check if orders table has updated_at column
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'updated_at'");
    
    if ($check_column->num_rows == 0) {
        // Add updated_at column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    
    // Check if orders table has seller_viewed column
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'seller_viewed'");
    
    if ($check_column->num_rows == 0) {
        // Add seller_viewed column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN seller_viewed TINYINT(1) DEFAULT 0");
    }
    
    // Check if orders table has customer_viewed column
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_viewed'");
    
    if ($check_column->num_rows == 0) {
        // Add customer_viewed column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN customer_viewed TINYINT(1) DEFAULT 0");
    }
    
    $query = "SELECT status FROM orders WHERE id = ? AND customer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $data['order_id'], $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found or does not belong to this customer']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    
    // Check if the order is in "shipped" status
    if ($order['status'] !== 'shipped') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Only shipped orders can be marked as delivered']);
        exit;
    }
    
    // Update order status
    $query = "UPDATE orders SET status = ?, updated_by = ?, seller_viewed = 0 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $data['status'], $customer_id, $data['order_id']);
    $stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
