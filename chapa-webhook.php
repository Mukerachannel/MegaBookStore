<?php
/**
* Chapa Webhook Handler
* 
* This file handles webhooks from Chapa for payment notifications
*/
require_once 'db.php';

// Get the JSON payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_CHAPA_SIGNATURE'] ?? '';

// Log the webhook
$log_file = 'chapa_webhook.log';
$log_data = date('Y-m-d H:i:s') . " - Webhook received\n";
$log_data .= "Signature: $signature\n";
$log_data .= "Payload: $payload\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// Verify webhook signature
$secret_key = "CHASECK_TEST-NfY2ckib86Zq5gLMcUeQ8tgIeBZA2kyQ"; // User's secret key
$computed_signature = hash_hmac('sha256', $payload, $secret_key);

if (hash_equals($computed_signature, $signature)) {
    // Signature is valid, process the webhook
    $data = json_decode($payload, true);
    
    if ($data && isset($data['event']) && isset($data['data'])) {
        $event = $data['event'];
        $event_data = $data['data'];
        
        // Log event details
        $log_data = date('Y-m-d H:i:s') . " - Event: $event\n";
        file_put_contents($log_file, $log_data, FILE_APPEND);
        
        if ($event === 'charge.completed' && isset($event_data['tx_ref'])) {
            $tx_ref = $event_data['tx_ref'];
            $status = $event_data['status'] ?? '';
            
            // Update payment status
            if ($status === 'success') {
                // Get payment details
                $stmt = $conn->prepare("SELECT order_id FROM payments WHERE tx_ref = ?");
                $stmt->bind_param("s", $tx_ref);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $payment = $result->fetch_assoc();
                    $order_id = $payment['order_id'];
                    
                    // Update payment status
                    $stmt = $conn->prepare("UPDATE payments SET status = 'success', payment_date = NOW(), updated_at = NOW(), verification_data = ? WHERE tx_ref = ?");
                    $verification_data = json_encode($event_data);
                    $stmt->bind_param("ss", $verification_data, $tx_ref);
                    $stmt->execute();
                    
                    // Update order status
                    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
                    $stmt->bind_param("i", $order_id);
                    $stmt->execute();
                    
                    // Log success
                    $log_data = date('Y-m-d H:i:s') . " - Payment successful for order #$order_id\n";
                    file_put_contents($log_file, $log_data, FILE_APPEND);
                } else {
                    // Log payment not found
                    $log_data = date('Y-m-d H:i:s') . " - Payment with tx_ref $tx_ref not found\n";
                    file_put_contents($log_file, $log_data, FILE_APPEND);
                }
            } else {
                // Log failed payment
                $log_data = date('Y-m-d H:i:s') . " - Payment failed for tx_ref $tx_ref: $status\n";
                file_put_contents($log_file, $log_data, FILE_APPEND);
            }
        }
    } else {
        // Log invalid payload
        $log_data = date('Y-m-d H:i:s') . " - Invalid payload structure\n";
        file_put_contents($log_file, $log_data, FILE_APPEND);
    }
} else {
    // Log invalid signature
    $log_data = date('Y-m-d H:i:s') . " - Invalid signature\n";
    $log_data .= "Computed: $computed_signature\n";
    $log_data .= "Received: $signature\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
}

// Return 200 OK response
http_response_code(200);
echo json_encode(['status' => 'success']);
