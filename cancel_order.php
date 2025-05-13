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

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate data
if (!$data || !isset($data['order_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    // Check if the order exists and belongs to this user
    $query = "SELECT * FROM orders WHERE id = ? AND customer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $data['order_id'], $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found or does not belong to you']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    
    // Check if the order can be cancelled (only pending orders can be cancelled)
    if ($order['status'] !== 'pending') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
        exit;
    }
    
    // Update order status to cancelled
    $query = "UPDATE orders SET status = 'cancelled' WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $data['order_id']);
    $stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
