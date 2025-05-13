<?php
/**
* Payment Complete Page
* 
* This page is shown after payment is completed
*/
session_start();
require_once 'db.php';

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$tx_ref = isset($_GET['tx_ref']) ? $_GET['tx_ref'] : '';

// Check if order exists
$order = null;
$payment = null;

if ($order_id > 0) {
    // Get order details
    $stmt = $conn->prepare("SELECT o.*, u.email, u.name FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Get payment details
        $stmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $payment = $result->fetch_assoc();
        }
        
        // Get order items
        $stmt = $conn->prepare("SELECT oi.*, b.title, b.author, b.cover_image FROM order_items oi 
                              JOIN books b ON oi.book_id = b.id 
                              WHERE oi.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order_items = $stmt->get_result();
    }
}

// Include header

?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body text-center p-5">
                    <?php if ($order && $payment): ?>
                        <?php if ($payment['status'] === 'success'): ?>
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h1 class="mb-4">Payment Successful!</h1>
                            <p class="lead mb-4">Thank you for your order. Your payment has been processed successfully.</p>
                            <div class="alert alert-success">
                                <p class="mb-1"><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
                                <p class="mb-1"><strong>Amount:</strong> ETB <?php echo number_format($payment['amount'], 2); ?></p>
                                <p class="mb-0"><strong>Transaction Reference:</strong> <?php echo $payment['tx_ref']; ?></p>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <i class="fas fa-info-circle text-primary" style="font-size: 4rem;"></i>
                            </div>
                            <h1 class="mb-4">Payment Processing</h1>
                            <p class="lead mb-4">Your payment is being processed. We'll update you once it's confirmed.</p>
                            <div class="alert alert-info">
                                <p class="mb-1"><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
                                <p class="mb-1"><strong>Amount:</strong> ETB <?php echo number_format($payment['amount'], 2); ?></p>
                                <p class="mb-0"><strong>Transaction Reference:</strong> <?php echo $payment['tx_ref']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($order_items && $order_items->num_rows > 0): ?>
                            <div class="mt-4 text-start">
                                <h4>Order Details</h4>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Book</th>
                                                <th>Price</th>
                                                <th>Quantity</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total = 0;
                                            while ($item = $order_items->fetch_assoc()): 
                                                $item_total = $item['price'] * $item['quantity'];
                                                $total += $item_total;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($item['cover_image'])): ?>
                                                                <img src="<?php echo htmlspecialchars($item['cover_image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="img-thumbnail me-2" style="width: 50px; height: 70px; object-fit: cover;">
                                                            <?php endif; ?>
                                                            <div>
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($item['title']); ?></h6>
                                                                <small class="text-muted"><?php echo htmlspecialchars($item['author']); ?></small>
                                                                <?php if ($item['is_rental']): ?>
                                                                    <br><small class="badge bg-info">Rental: <?php echo $item['rental_days']; ?> days</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>ETB <?php echo number_format($item['price'], 2); ?></td>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                    <td>ETB <?php echo number_format($item_total, 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-end">Total:</th>
                                                <th>ETB <?php echo number_format($total, 2); ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                            <button onclick="printReceipt()" class="btn btn-outline-primary ms-2">
                                <i class="fas fa-download me-1"></i> Download Receipt
                            </button>
                        </div>
                        
                        <!-- Hidden receipt for printing/downloading -->
                        <div id="receipt" style="display: none;">
                            <div style="max-width: 800px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <h2 style="margin: 0;">Mega Books</h2>
                                    <p style="margin: 5px 0;">Sidama, Hawassa</p>
                                    <p style="margin: 5px 0;">Phone: +251921195638</p>
                                    <p style="margin: 5px 0;">Email: info@megabooks.com</p>
                                </div>
                                
                                <div style="border-top: 1px solid #ddd; border-bottom: 1px solid #ddd; padding: 10px 0; margin-bottom: 20px;">
                                    <h3 style="margin: 0; text-align: center;">Payment Receipt</h3>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <p><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
                                    <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($payment['created_at'])); ?></p>
                                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                    <p><strong>Payment Method:</strong> Telebirr</p>
                                    <p><strong>Transaction Reference:</strong> <?php echo $payment['tx_ref']; ?></p>
                                </div>
                                
                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                                    <thead>
                                        <tr style="background-color: #f2f2f2;">
                                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Book</th>
                                            <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Price</th>
                                            <th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Quantity</th>
                                            <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Reset the result pointer
                                        $order_items->data_seek(0);
                                        $total = 0;
                                        while ($item = $order_items->fetch_assoc()): 
                                            $item_total = $item['price'] * $item['quantity'];
                                            $total += $item_total;
                                        ?>
                                            <tr>
                                                <td style="padding: 10px; border: 1px solid #ddd;">
                                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                    <?php if ($item['is_rental']): ?>
                                                        <br><small>Rental: <?php echo $item['rental_days']; ?> days</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">ETB <?php echo number_format($item['price'], 2); ?></td>
                                                <td style="padding: 10px; text-align: center; border: 1px solid #ddd;"><?php echo $item['quantity']; ?></td>
                                                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">ETB <?php echo number_format($item_total, 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" style="padding: 10px; text-align: right; border: 1px solid #ddd;">Total:</th>
                                            <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">ETB <?php echo number_format($total, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                                
                                <div style="text-align: center; margin-top: 30px; font-size: 14px; color: #777;">
                                    <p>Thank you for your purchase!</p>
                                    <p>For any questions or concerns, please contact us at info@megabooks.com</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                        </div>
                        <h1 class="mb-4">Order Information Not Found</h1>
                        <p class="lead">We couldn't find information about your order. Please contact customer support.</p>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to print/download receipt
function printReceipt() {
    const receiptContent = document.getElementById('receipt').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Receipt - Order #<?php echo $order_id; ?></title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            ${receiptContent}
            <script>
                // Auto print when loaded
                window.onload = function() {
                    window.print();
                }
            </script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Auto-redirect to home page after 5 seconds if payment is not successful
<?php if (!$payment || $payment['status'] !== 'success'): ?>
setTimeout(function() {
    window.location.href = 'index.php';
}, 5000);
<?php endif; ?>
</script>

<?php
// Include footer

?>
