<?php
session_start();
require_once 'db.php';
require_once 'Chapa.php';
require_once 'PostData.php';
require_once 'ResponseData.php';
require_once 'Util.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

// Process checkout form
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate shipping information
    $shipping_name = Util::sanitizeInput($_POST['shipping_name'] ?? '');
    $shipping_address = Util::sanitizeInput($_POST['shipping_address'] ?? '');
    $shipping_phone = Util::sanitizeInput($_POST['shipping_phone'] ?? '');
    $payment_method = Util::sanitizeInput($_POST['payment_method'] ?? '');
    $notes = Util::sanitizeInput($_POST['notes'] ?? '');
    
    // Validate inputs
    if (empty($shipping_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($shipping_address)) {
        $errors[] = "Shipping address is required";
    }
    
    if (empty($shipping_phone)) {
        $errors[] = "Phone number is required";
    } elseif (!Util::validatePhone($shipping_phone)) {
        $errors[] = "Phone number must be 10 digits";
    }
    
    if (empty($payment_method)) {
        $errors[] = "Payment method is required";
    }
    
    // If no errors, process the order
    if (empty($errors)) {
        // Calculate order total
        $total_amount = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }
        
        // Add shipping fee if applicable
        $shipping_fee = get_setting($conn, 'shipping_fee') ?? 50;
        $free_shipping_threshold = get_setting($conn, 'free_shipping_threshold') ?? 500;
        
        if ($total_amount < $free_shipping_threshold) {
            $total_amount += $shipping_fee;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create order
            $stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, status, shipping_address, shipping_phone, shipping_name, payment_method, payment_status, notes) VALUES (?, ?, 'pending', ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("idssss", $user_id, $total_amount, $shipping_address, $shipping_phone, $shipping_name, $payment_method, $notes);
            $stmt->execute();
            
            $order_id = $conn->insert_id;
            
            // Add order items
            foreach ($_SESSION['cart'] as $book_id => $item) {
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price, is_rental, rental_days) VALUES (?, ?, ?, ?, ?, ?)");
                $is_rental = $item['is_rental'] ? 1 : 0;
                $rental_days = $item['rental_days'] ?? null;
                $stmt->bind_param("iiidii", $order_id, $book_id, $item['quantity'], $item['price'], $is_rental, $rental_days);
                $stmt->execute();
                
                // Update book stock
                $stmt = $conn->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
                $stmt->bind_param("ii", $item['quantity'], $book_id);
                $stmt->execute();
            }
            
            // Process payment based on method
            if ($payment_method === 'chapa') {
                // Get Chapa secret key
                $secret_key = get_setting($conn, 'chapa_secret_key');
                
                if (!$secret_key) {
                    throw new Exception("Chapa payment configuration is missing");
                }
                
                // Initialize Chapa
                $chapa = new Chapa($secret_key);
                
                // Generate transaction reference
                $tx_ref = Util::generateToken();
                
                // Create callback and return URLs
                $callback_url = 'https://' . $_SERVER['HTTP_HOST'] . '/callback.php';
                $return_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment-complete.php?order_id=' . $order_id;
                
                // Split name into first and last name
                $name_parts = explode(' ', $shipping_name, 2);
                $first_name = $name_parts[0];
                $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                
                // Create payment data
                $postData = new PostData();
                $postData->amount(Util::formatAmount($total_amount))
                    ->currency('ETB')
                    ->email($user['email'])
                    ->firstname($first_name)
                    ->lastname($last_name)
                    ->phone($shipping_phone)
                    ->transactionRef($tx_ref)
                    ->callbackUrl($callback_url)
                    ->returnUrl($return_url)
                    ->customizations([
                        'customization[title]' => 'Mega Books Order',
                        'customization[description]' => 'Payment for your book order'
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
                    
                    // Clear cart
                    $_SESSION['cart'] = [];
                    
                    // Redirect to Chapa checkout
                    header('Location: ' . $checkout_url);
                    exit;
                } else {
                    // Payment initialization failed
                    throw new Exception("Payment initialization failed: " . $response->getMessage());
                }
            } elseif ($payment_method === 'cash_on_delivery') {
                // For cash on delivery, just complete the order
                $stmt = $conn->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Clear cart
                $_SESSION['cart'] = [];
                
                // Set success message
                $success = true;
                $_SESSION['order_success'] = "Your order has been placed successfully! Order ID: " . $order_id;
                
                // Redirect to order confirmation
                header('Location: order-confirmation.php?order_id=' . $order_id);
                exit;
            } else {
                throw new Exception("Invalid payment method");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

// Get cart items for display
$cart_items = [];
$total = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $book_id => $item) {
        // Get book details
        $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $book = $result->fetch_assoc();
            $item['book'] = $book;
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $cart_items[] = $item;
            $total += $item['subtotal'];
        }
    }
}

// Calculate shipping
$shipping_fee = get_setting($conn, 'shipping_fee') ?? 50;
$free_shipping_threshold = get_setting($conn, 'free_shipping_threshold') ?? 500;
$shipping_cost = ($total < $free_shipping_threshold) ? $shipping_fee : 0;

// Calculate final total
$final_total = $total + $shipping_cost;

// Get currency
$currency = get_setting($conn, 'currency') ?? 'ETB';

// Include header
include 'header.php';
?>

<div class="container my-5">
    <h1 class="mb-4">Checkout</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            Your order has been placed successfully!
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Shipping Information</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="checkout.php">
                        <div class="mb-3">
                            <label for="shipping_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="shipping_name" name="shipping_name" value="<?php echo $user['fullname'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Shipping Address</label>
                            <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required><?php echo $user['address'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="shipping_phone" name="shipping_phone" value="<?php echo $user['phone'] ?? ''; ?>" required>
                            <small class="text-muted">Must be 10 digits (e.g., 0912345678)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Order Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Payment Method</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="chapa" value="chapa" checked>
                                <label class="form-check-label" for="chapa">
                                    Pay with Chapa (Credit/Debit Card, Mobile Money)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="cash_on_delivery" value="cash_on_delivery">
                                <label class="form-check-label" for="cash_on_delivery">
                                    Cash on Delivery
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Place Order</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?php echo $item['book']['title']; ?> x <?php echo $item['quantity']; ?></span>
                            <span><?php echo $currency; ?> <?php echo number_format($item['subtotal'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span><?php echo $currency; ?> <?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping</span>
                        <?php if ($shipping_cost > 0): ?>
                            <span><?php echo $currency; ?> <?php echo number_format($shipping_cost, 2); ?></span>
                        <?php else: ?>
                            <span class="text-success">Free</span>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <strong>Total</strong>
                        <strong><?php echo $currency; ?> <?php echo number_format($final_total, 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'footer.php';
?>
