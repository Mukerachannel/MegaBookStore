<?php
// Get cart count for notification badge
$cart_count = 0;

try {
    // Check if cart table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'cart'");
    if ($table_check && $table_check->num_rows > 0) {
        $cart_query = "SELECT COUNT(*) as total_cart FROM cart WHERE user_id = ?";
        $stmt = $conn->prepare($cart_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                $cart_count = $result->fetch_assoc()['total_cart'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching cart count: " . $e->getMessage());
}
?>

<header class="top-nav">
    <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search for books...">
    </div>
    <div class="user-menu">
        <div class="notifications">
            <a href="cart.php">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Customer'); ?></span>
            <img src="images/avatar.png" alt="User Avatar">
            <div class="dropdown-menu">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php 
        echo $_SESSION['success']; 
        unset($_SESSION['success']);
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <?php 
        echo $_SESSION['error']; 
        unset($_SESSION['error']);
        ?>
    </div>
<?php endif; ?>