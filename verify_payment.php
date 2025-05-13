<?php
/**
* Verify Chapa Payment
* 
* This file is used to manually verify a payment status
*/
require_once 'db.php';
require_once 'Chapa.php';
require_once 'ResponseData.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$payment = null;
$verification_result = null;

if (isset($_GET['tx_ref'])) {
    $tx_ref = $_GET['tx_ref'];
    
    // Get payment details
    $stmt = $conn->prepare("SELECT p.*, o.id as order_id, o.customer_id, o.total_amount, o.status as order_status, o.payment_status as order_payment_status 
                          FROM payments p 
                          JOIN orders o ON p.order_id = o.id 
                          WHERE p.tx_ref = ?");
    $stmt->bind_param("s", $tx_ref);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        
        // Use the provided secret key
        $secret_key = "CHASECK_TEST-NfY2ckib86Zq5gLMcUeQ8tgIeBZA2kyQ"; // User's secret key
        
        // Initialize Chapa
        $chapa = new Chapa($secret_key);
        
        // Verify transaction
        $response = $chapa->verify($tx_ref);
        $verification_result = $response;
        
        if ($response->getStatus() === 'success') {
            // Update payment status
            $payment_status = $response->getData()['status'] === 'success' ? 'success' : 'failed';
            $reference = $response->getData()['reference'] ?? null;
            $verification_data = $response->getRawJson();
            
            $stmt = $conn->prepare("UPDATE payments SET status = ?, reference = ?, verification_data = ?, payment_date = NOW(), updated_at = NOW() WHERE tx_ref = ?");
            $stmt->bind_param("ssss", $payment_status, $reference, $verification_data, $tx_ref);
            
            if ($stmt->execute()) {
                // If payment is successful, update order payment status
                if ($payment_status === 'success') {
                    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
                    $stmt->bind_param("i", $payment['order_id']);
                    $stmt->execute();
                    
                    $message = "Payment verified successfully and order updated.";
                } else {
                    $message = "Payment verification completed, but payment was not successful.";
                }
            } else {
                $message = "Error updating payment information: " . $stmt->error;
            }
        } else {
            $message = "Payment verification failed: " . $response->getMessage();
        }
    } else {
        $message = "Payment with transaction reference $tx_ref not found.";
    }
}

// Include header

?>

<div class="container my-5">
    <h1 class="mb-4">Verify Chapa Payment</h1>
    
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Payment Verification</h5>
        </div>
        <div class="card-body">
            <form method="get" action="verify-payment.php">
                <div class="mb-3">
                    <label for="tx_ref" class="form-label">Transaction Reference</label>
                    <input type="text" class="form-control" id="tx_ref" name="tx_ref" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Verify Payment</button>
            </form>
        </div>
    </div>
    
    <?php if ($payment): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Payment Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Transaction Reference:</strong> <?php echo $payment['tx_ref']; ?></p>
                        <p><strong>Order ID:</strong> #<?php echo $payment['order_id']; ?></p>
                        <p><strong>Amount:</strong> <?php echo $payment['currency']; ?> <?php echo number_format($payment['amount'], 2); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge <?php echo $payment['status'] === 'success' ? 'bg-success' : ($payment['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created:</strong> <?php echo date('F j, Y, g:i a', strtotime($payment['created_at'])); ?></p>
                        <?php if ($payment['payment_date']): ?>
                            <p><strong>Payment Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($payment['payment_date'])); ?></p>
                        <?php endif; ?>
                        <?php if ($payment['reference']): ?>
                            <p><strong>Reference:</strong> <?php echo $payment['reference']; ?></p>
                        <?php endif; ?>
                        <p><strong>Order Status:</strong> 
                            <span class="badge bg-secondary">
                                <?php echo ucfirst($payment['order_status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($verification_result): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Verification Result</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3"><?php echo json_encode(json_decode($verification_result->getRawJson()), JSON_PRETTY_PRINT); ?></pre>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer

?>
