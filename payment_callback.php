<?php
/**
* Chapa Payment Callback Handler
* 
* This file handles the callback from Chapa after payment
*/
require_once 'db.php';
require_once 'Chapa.php';
require_once 'ResponseData.php';

// Get callback data
$tx_ref = $_GET['trx_ref'] ?? '';
$ref_id = $_GET['ref_id'] ?? '';
$status = $_GET['status'] ?? '';

// Log callback data
$log_file = 'chapa_callback.log';
$log_data = date('Y-m-d H:i:s') . " - Callback received: tx_ref=$tx_ref, ref_id=$ref_id, status=$status\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// Verify the transaction
if (!empty($tx_ref)) {
    // Use the provided secret key
    $secret_key = "CHASECK_TEST-NfY2ckib86Zq5gLMcUeQ8tgIeBZA2kyQ"; // User's secret key
    
    // Initialize Chapa
    $chapa = new Chapa($secret_key);
    
    // Verify transaction
    $response = $chapa->verify($tx_ref);
    
    // Log verification response
    $log_data = date('Y-m-d H:i:s') . " - Verification response: " . $response->getRawJson() . "\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    
    if ($response->getStatus() === 'success') {
        // Update payment status
        $payment_status = $status === 'success' ? 'success' : 'failed';
        $verification_data = $response->getRawJson();
        
        $stmt = $conn->prepare("UPDATE payments SET status = ?, reference = ?, verification_data = ?, payment_date = NOW(), updated_at = NOW() WHERE tx_ref = ?");
        $stmt->bind_param("ssss", $payment_status, $ref_id, $verification_data, $tx_ref);
        
        if ($stmt->execute()) {
            // If payment is successful, update order payment status
            if ($payment_status === 'success') {
                // Get order ID from payment
                $stmt = $conn->prepare("SELECT order_id FROM payments WHERE tx_ref = ?");
                $stmt->bind_param("s", $tx_ref);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $payment = $result->fetch_assoc();
                    $order_id = $payment['order_id'];
                    
                    // Update order status
                    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
                    $stmt->bind_param("i", $order_id);
                    $stmt->execute();
                    
                    // Log success
                    $log_data = date('Y-m-d H:i:s') . " - Order #$order_id updated to paid status\n";
                    file_put_contents($log_file, $log_data, FILE_APPEND);
                }
            }
        } else {
            // Log error
            $log_data = date('Y-m-d H:i:s') . " - Error updating payment: " . $stmt->error . "\n";
            file_put_contents($log_file, $log_data, FILE_APPEND);
        }
    } else {
        // Log verification failure
        $log_data = date('Y-m-d H:i:s') . " - Verification failed: " . $response->getMessage() . "\n";
        file_put_contents($log_file, $log_data, FILE_APPEND);
    }
} else {
    // Log missing transaction reference
    $log_data = date('Y-m-d H:i:s') . " - Missing transaction reference\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
