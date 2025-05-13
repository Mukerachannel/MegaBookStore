<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Check if this is a callback from Chapa
$payment_status = $_GET['payment_status'] ?? '';
$tx_ref = $_GET['tx_ref'] ?? '';

// If this is a direct POST request (from your existing code)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from request
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Validate data
    if (!$data || !isset($data['customer_info']) || !isset($data['items']) || empty($data['items'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    // Process the order using your existing code
    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert order with enhanced details
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, shipping_address, shipping_phone, shipping_name, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idssss", 
            $user_id, 
            $data['total_amount'], 
            $data['customer_info']['address'], 
            $data['customer_info']['phone'],
            $data['customer_info']['fullname'],
            $data['customer_info']['payment_method']
        );
        $stmt->execute();
        
        // Get order ID
        $order_id = $conn->insert_id;
        
        // Update user information if needed
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", 
            $data['customer_info']['fullname'], 
            $data['customer_info']['phone'], 
            $data['customer_info']['address'], 
            $user_id
        );
        $stmt->execute();
        
        // Insert order items
        foreach ($data['items'] as $item) {
            $is_rental = $item['orderType'] === 'rent' ? 1 : 0;
            $rental_days = $is_rental ? $item['rentDays'] : null;
            
            // Calculate price based on order type
            if ($is_rental) {
                $price = $item['rentPrice'] * $item['rentDays'];
            } else {
                $price = $item['price'];
            }
            
            // Calculate return date for rentals
            $return_date = null;
            if ($is_rental) {
                $return_date = date('Y-m-d', strtotime("+{$rental_days} days"));
            }
            
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price, is_rental, rental_days, return_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidiis", 
                $order_id, 
                $item['id'], 
                $item['quantity'], 
                $price, 
                $is_rental, 
                $rental_days, 
                $return_date
            );
            $stmt->execute();
            
            // Update book stock
            $stmt = $conn->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
            $stmt->bind_param("ii", $item['quantity'], $item['id']);
            $stmt->execute();
            
            // Update book popularity
            $stmt = $conn->prepare("UPDATE books SET popularity = popularity + 1 WHERE id = ?");
            $stmt->bind_param("i", $item['id']);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'order_id' => 'ORD-' . $order_id]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// If this is a callback from Chapa
if ($payment_status === 'success' && !empty($tx_ref)) {
    // Get pending order from session
    $pending_order = $_SESSION['pending_order'] ?? null;
    
    if ($pending_order) {
        // Process the order using your existing code
        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert order with enhanced details
            $stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, shipping_address, shipping_phone, shipping_name, payment_method, payment_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $payment_method = 'telebirr';
            $payment_status = 'paid';
            $notes = "Chapa Transaction: " . $tx_ref;
            $stmt->bind_param("idsssss", 
                $user_id, 
                $pending_order['total_amount'], 
                $pending_order['customer_info']['address'], 
                $pending_order['customer_info']['phone'],
                $pending_order['customer_info']['fullname'],
                $payment_method,
                $payment_status,
                $notes
            );
            $stmt->execute();
            
            // Get order ID
            $order_id = $conn->insert_id;
            
            // Update user information if needed
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssi", 
                $pending_order['customer_info']['fullname'], 
                $pending_order['customer_info']['phone'], 
                $pending_order['customer_info']['address'], 
                $user_id
            );
            $stmt->execute();
            
            // Insert order items
            foreach ($pending_order['items'] as $item) {
                $is_rental = $item['orderType'] === 'rent' ? 1 : 0;
                $rental_days = $is_rental ? $item['rentDays'] : null;
                
                // Calculate price based on order type
                if ($is_rental) {
                    $price = $item['rentPrice'] * $item['rentDays'];
                } else {
                    $price = $item['price'];
                }
                
                // Calculate return date for rentals
                $return_date = null;
                if ($is_rental) {
                    $return_date = date('Y-m-d', strtotime("+{$rental_days} days"));
                }
                
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price, is_rental, rental_days, return_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiidiis", 
                    $order_id, 
                    $item['id'], 
                    $item['quantity'], 
                    $price, 
                    $is_rental, 
                    $rental_days, 
                    $return_date
                );
                $stmt->execute();
                
                // Update book stock
                $stmt = $conn->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
                $stmt->bind_param("ii", $item['quantity'], $item['id']);
                $stmt->execute();
                
                // Update book popularity
                $stmt = $conn->prepare("UPDATE books SET popularity = popularity + 1 WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear session data
            unset($_SESSION['pending_order']);
            unset($_SESSION['chapa_tx_ref']);
            
            // Redirect to success page
            header('Location: order_success.php?order_id=ORD-' . $order_id);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            // Redirect to error page
            header('Location: payment_error.php?message=' . urlencode($e->getMessage()));
            exit;
        }
    } else {
        // Redirect to error page
        header('Location: payment_error.php?message=' . urlencode('Invalid session data'));
        exit;
    }
} elseif ($payment_status === 'failed' || $payment_status === 'error') {
    // Redirect to error page
    $message = $_GET['message'] ?? 'Payment failed';
    header('Location: payment_error.php?message=' . urlencode($message));
    exit;
} else {
    // If no valid parameters, redirect to cart
    header('Location: cart.php');
    exit;
}
?>
