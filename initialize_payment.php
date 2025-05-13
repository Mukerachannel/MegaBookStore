<?php
session_start();
require_once 'db.php';
require_once 'Chapa.php';
require_once 'PostData.php';
require_once 'ResponseData.php';
require_once 'Util.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Extract order data
$customer_info = $data['customer_info'] ?? null;
$payment_method = $data['payment_method'] ?? 'telebirr';
$total_amount = $data['total_amount'] ?? 0;

// Validate data
if (!$customer_info || $total_amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required order data']);
    exit;
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Create order
    $stmt = $conn->prepare("INSERT INTO orders (customer_id, order_date, total_amount, status, shipping_address, shipping_phone, shipping_name, payment_method, payment_status) VALUES (?, NOW(), ?, 'pending', ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("idssss", $user_id, $total_amount, $customer_info['address'], $customer_info['phone'], $customer_info['fullname'], $payment_method);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create order: " . $stmt->error);
    }
    
    $order_id = $conn->insert_id;
    
    // Process order items
    foreach ($_SESSION['cart'] as $book_id => $item) {
        // Get book details
        $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Book with ID $book_id not found");
        }
        
        $book = $result->fetch_assoc();
        
        // Check if book is in stock
        if ($book['stock'] < $item['quantity']) {
            throw new Exception("Sorry, only {$book['stock']} copies of '{$book['title']}' are available");
        }
        
        // Add order item
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price, is_rental, rental_days) VALUES (?, ?, ?, ?, ?, ?)");
        $is_rental = $item['is_rental'] ? 1 : 0;
        $rental_days = $item['rental_days'];
        $stmt->bind_param("iiidii", $order_id, $book_id, $item['quantity'], $item['price'], $is_rental, $rental_days);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to add order item: " . $stmt->error);
        }
        
        // Update book stock
        $stmt = $conn->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
        $stmt->bind_param("ii", $item['quantity'], $book_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update book stock: " . $stmt->error);
        }
    }
    
    // Process payment with Chapa
    // Use the provided secret key
    $secret_key = "CHASECK_TEST-NfY2ckib86Zq5gLMcUeQ8tgIeBZA2kyQ"; // User's secret key
    
    // Initialize Chapa
    $chapa = new Chapa($secret_key);
    
    // Generate transaction reference
    $tx_ref = Util::generateToken();
    
    // Create callback and return URLs
    $callback_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment_callback.php';
    $return_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment_complete.php?order_id=' . $order_id;
    
    // Split name into first and last name
    $name_parts = explode(' ', $customer_info['fullname'], 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
    
    // Create payment data
    $postData = new PostData();
    $postData->amount(Util::formatAmount($total_amount))
        ->currency('ETB')
        ->email($_SESSION['email'] ?? 'customer@example.com')
        ->firstname($first_name)
        ->lastname($last_name)
        ->phone($customer_info['phone'])
        ->transactionRef($tx_ref)
        ->callbackUrl($callback_url)
        ->returnUrl($return_url)
        ->customizations([
            'customization[title]' => 'Mega Books Order',
            'customization[description]' => 'Payment for your book order',
            'customization[logo]' => 'https://example.com/logo.png',
            'customization[payment_methods]' => 'telebirr', // Only allow telebirr payment method
            'customization[test_mode]' => 'true', // Enable test mode to accept any phone number
            'customization[test_bank_label]' => 'Telebirr', // Change test bank label to Telebirr
            'customization[test_card_label]' => 'Telebirr', // Change test card label to Telebirr
            'customization[hide_card_payment]' => 'true', // Hide card payment option
            'customization[hide_bank_payment]' => 'true', // Hide bank payment option
            'customization[show_only]' => 'telebirr' // Only show telebirr
        ]);
    
    // Initialize payment
    $response = $chapa->initialize($postData);
    
    if ($response->getStatus() === 'success') {
        // Save payment information
        $checkout_url = $response->getData()['checkout_url'];
        
        $stmt = $conn->prepare("INSERT INTO payments (order_id, tx_ref, amount, currency, status, checkout_url) VALUES (?, ?, ?, 'ETB', 'pending', ?)");
        $stmt->bind_param("isds", $order_id, $tx_ref, $total_amount, $checkout_url);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Clear cart after successful order creation
        $_SESSION['cart'] = [];
        
        // Return success response with checkout URL
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'tx_ref' => $tx_ref,
            'checkout_url' => $checkout_url
        ]);
    } else {
        // Payment initialization failed
        throw new Exception("Payment initialization failed: " . $response->getMessage());
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
