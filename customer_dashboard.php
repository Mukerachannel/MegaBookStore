<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Initialize variables with default values
$order_count = 0;
$user_data = [
    'fullname' => $_SESSION['fullname'] ?? 'Customer',
    'email' => $_SESSION['email'] ?? '',
    'phone' => '',
    'address' => ''
];

// Get user data
try {
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Get order count
try {
    // Check if orders table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($table_check && $table_check->num_rows > 0) {
        $order_query = "SELECT COUNT(*) as total_orders FROM orders WHERE customer_id = ?";
        $stmt = $conn->prepare($order_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                $order_count = $result->fetch_assoc()['total_orders'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching order count: " . $e->getMessage());
}

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

// Get recent orders with more details
$recent_orders = [];
try {
    // Check if orders table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($table_check && $table_check->num_rows > 0) {
        $orders_query = "SELECT o.id, o.order_date, o.total_amount, o.status, 
                        COUNT(oi.id) as item_count
                        FROM orders o
                        LEFT JOIN order_items oi ON o.id = oi.order_id
                        WHERE o.customer_id = ?
                        GROUP BY o.id
                        ORDER BY o.order_date DESC
                        LIMIT 5";
        
        $stmt = $conn->prepare($orders_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $recent_orders[] = $row;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Mega Book Store</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
<style>
/* Add these styles to fix the visibility issues */
        /* Home link style */
        .home-link {
            display: flex;
            align-items: center;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            margin-right: 15px;
        }
        
        .home-link i {
            margin-right: 5px;
        }
        
        .home-link:hover {
            color: #2980b9;
        }
        
        /* Adjust user menu to include home link */
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        /* Status badge styles */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #f39c12;
        }
        
        .status-accepted {
            background-color: #e1f5fe;
            color: #3498db;
        }
        
        .status-processing {
            background-color: #e1f5fe;
            color: #3498db;
        }
        
        .status-shipped {
            background-color: #e8f5e9;
            color: #2ecc71;
        }
        
        .status-delivered {
            background-color: #e8f5e9;
            color: #27ae60;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #e74c3c;
        }
        
        .status-rejected {
            background-color: #ffebee;
            color: #e74c3c;
        }
        
        /* Welcome card */
        .welcome-card {
            background-color: #3498db;
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-card h2 {
            margin-top: 0;
            font-size: 24px;
        }
        
        .welcome-card p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        /* View all orders button */
        .view-all-btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .view-all-btn:hover {
            background-color: #2980b9;
        }
        
        /* Add sidebar toggle button styles */
        .sidebar-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background-color: #2980b9;
        }
        
        /* Fix user info visibility */
        .user-info {
            position: relative;
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-info span {
            color: #333;
            margin-right: 10px;
            font-weight: 600;
            text-shadow: 0 0 1px rgba(255, 255, 255, 0.7);
        }
        
        .user-info img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Make sure dropdown menu is visible */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            padding: 10px 0;
            min-width: 180px;
            z-index: 1000;
            display: none;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .user-info:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-menu a {
            display: block;
            padding: 8px 15px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .dropdown-menu a:hover {
            background-color: #f8f9fa;
        }
        
        /* Ensure main content has proper positioning */
        .main-content {
            position: relative;
            flex: 1;
            padding: 20px;
            background-color: #f8f9fa;
            overflow-y: auto;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px;
                transition: left 0.3s ease;
                z-index: 1000;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar-toggle {
                display: flex;
            }
        }

    /* Notification Styles */
    .notification-icon {
        position: relative;
        cursor: pointer;
        margin-right: 20px;
    }
    
    .notification-count {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 350px;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        display: none;
        overflow: hidden;
    }
    
    .notification-dropdown.show {
        display: block;
    }
    
    .notification-header {
        padding: 15px;
        background-color: #f8f9fa;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .notification-header h3 {
        margin: 0;
        font-size: 16px;
        color: #333;
    }
    
    .notification-list {
        max-height: 350px;
        overflow-y: auto;
    }
    
    .notification-item {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s ease;
        cursor: pointer;
    }
    
    .notification-item:hover {
        background-color: #f8f9fa;
    }
    
    .notification-item.unread {
        background-color: #ebf7ff;
    }
    
    .notification-item.unread:hover {
        background-color: #daeeff;
    }
    
    .notification-content {
        display: flex;
        align-items: flex-start;
    }
    
    .notification-icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: rgba(52, 152, 219, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
    }
    
    .notification-icon-wrapper i {
        color: #3498db;
        font-size: 18px;
    }
    
    .notification-text {
        flex: 1;
    }
    
    .notification-title {
        font-weight: 600;
        margin: 0 0 5px;
        color: #333;
        font-size: 14px;
    }
    
    .notification-desc {
        color: #666;
        margin: 0;
        font-size: 13px;
    }
    
    .notification-time {
        font-size: 12px;
        color: #999;
        margin-top: 5px;
    }
    
    .notification-footer {
        padding: 10px 15px;
        text-align: center;
        border-top: 1px solid #eee;
    }
    
    .notification-footer a {
        color: #3498db;
        text-decoration: none;
        font-size: 14px;
    }
    
    .notification-footer a:hover {
        text-decoration: underline;
    }
    
    .no-notifications {
        padding: 30px 15px;
        text-align: center;
        color: #999;
    }
    
    .no-notifications i {
        font-size: 32px;
        margin-bottom: 10px;
        color: #ddd;
    }
</style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Mega Books</h2>
                <span>Customer Panel</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="customer_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="customer_order.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Add sidebar toggle button -->
           
    
            <!-- Top Navigation -->
            <header class="top-nav">
                <div class="search-bar">
                   <!-- Empty for consistency -->
                </div>
                <div class="user-menu">
                    <!-- Home Link -->
                    <a href="index.php" class="home-link">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                    
                    <div class="notifications">
                        <a href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="badge"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <!-- Order Notifications -->
                    <div class="notification-icon" id="notificationIcon">
                        <i class="fas fa-bell"></i>
                        <?php
                        // Get unread order notifications
                        $unread_count = 0;
                        try {
                            $notification_query = "SELECT COUNT(*) as count FROM orders 
                                                  WHERE customer_id = ? AND customer_viewed = 0";
                            $stmt = $conn->prepare($notification_query);
                            if ($stmt) {
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result && $row = $result->fetch_assoc()) {
                                    $unread_count = $row['count'];
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching notifications: " . $e->getMessage());
                        }
                        ?>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-count"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                        
                        <!-- Notification Dropdown -->
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h3>Order Updates</h3>
                                <a href="#" id="markAllRead">Mark all as read</a>
                            </div>
                            <div class="notification-list">
                                <?php
                                // Get recent order notifications
                                $notifications = [];
                                try {
                                    $notifications_query = "SELECT o.id, o.order_date, o.status, o.customer_viewed,
                                                          COUNT(oi.id) as item_count, o.total_amount
                                                          FROM orders o
                                                          JOIN order_items oi ON o.id = oi.order_id
                                                          WHERE o.customer_id = ?
                                                          GROUP BY o.id
                                                          ORDER BY o.updated_at DESC
                                                          LIMIT 10";
                                    $stmt = $conn->prepare($notifications_query);
                                    if ($stmt) {
                                        $stmt->bind_param("i", $_SESSION['user_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result) {
                                            while ($row = $result->fetch_assoc()) {
                                                $notifications[] = $row;
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    error_log("Error fetching order notifications: " . $e->getMessage());
                                }
                                ?>
                                
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo !$notification['customer_viewed'] ? 'unread' : ''; ?>" 
                                             onclick="window.location.href='customer_order.php?id=<?php echo $notification['id']; ?>'">
                                            <div class="notification-content">
                                                <div class="notification-icon-wrapper">
                                                    <i class="fas fa-shopping-bag"></i>
                                                </div>
                                                <div class="notification-text">
                                                    <h4 class="notification-title">Order #<?php echo $notification['id']; ?> - <?php echo ucfirst($notification['status']); ?></h4>
                                                    <p class="notification-desc">
                                                        Your order with <?php echo $notification['item_count']; ?> item(s) for 
                                                        ETB<?php echo number_format($notification['total_amount'], 2); ?> has been updated.
                                                    </p>
                                                    <p class="notification-time">
                                                        <?php echo date('M d, Y h:i A', strtotime($notification['order_date'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-notifications">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>No order updates</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="notification-footer">
                                <a href="customer_order.php">View All Orders</a>
                            </div>
                        </div>
                    </div>
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user_data['fullname']); ?></span>
                        <img src="asset/profile.png" alt="User Avatar">
                        <div class="dropdown-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="customer_orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Welcome Card -->
                <div class="welcome-card">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_data['fullname']); ?>!</h2>
                    <p>Manage your orders and explore our collection of books.</p>
                </div>
                
                <!-- Stats Cards - Only showing Orders -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3>My Orders</h3>
                            <p><?php echo $order_count; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders - Updated with more details -->
                <div class="recent-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2>Recent Orders</h2>
                        <a href="customer_order.php" class="view-all-btn">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_orders) > 0): ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td><?php echo $order['item_count']; ?> item(s)</td>
                                            <td>ETB<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="actions">
                                                <a href="customer_order?id=<?php echo $order['id']; ?>" class="btn-view">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No orders found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('expanded');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && 
            !sidebar.contains(event.target) && 
            !sidebarToggle.contains(event.target) && 
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            mainContent.classList.remove('expanded');
        }
    });
    
    // Make sure user info dropdown works
    const userInfo = document.querySelector('.user-info');
    if (userInfo) {
        userInfo.addEventListener('click', function(e) {
            const dropdown = this.querySelector('.dropdown-menu');
            if (dropdown) {
                // Prevent clicks inside dropdown from closing it
                if (e.target.closest('.dropdown-menu')) return;
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-info')) {
                const dropdown = document.querySelector('.user-info .dropdown-menu');
                if (dropdown) {
                    dropdown.style.display = 'none';
                }
            }
        });
    }
});

    // Notification dropdown toggle
    const notificationIcon = document.getElementById('notificationIcon');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationIcon && notificationDropdown) {
        notificationIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
        
        // Mark all as read
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Send AJAX request to mark all as read
                fetch('mark_all_customer_read.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove unread class from all notifications
                        const unreadItems = document.querySelectorAll('.notification-item.unread');
                        unreadItems.forEach(item => {
                            item.classList.remove('unread');
                        });
                        
                        // Hide notification count
                        const notificationCount = document.querySelector('.notification-count');
                        if (notificationCount) {
                            notificationCount.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        }
    }
</script>
</body>
</html>
