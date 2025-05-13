<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get customer information
$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

// Get customer orders
$orders = [];
try {
    $query = "SELECT o.*, COUNT(oi.id) as item_count 
              FROM orders o 
              LEFT JOIN order_items oi ON o.id = oi.order_id 
              WHERE o.customer_id = ? 
              GROUP BY o.id 
              ORDER BY o.order_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
}

// Filter orders by status if requested
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($status_filter)) {
    $filtered_orders = [];
    foreach ($orders as $order) {
        if ($order['status'] == $status_filter) {
            $filtered_orders[] = $order;
        }
    }
    $orders = $filtered_orders;
}

// Initialize cart count
$cart_count = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Mega Book Store</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        
        /* Status filter tabs */
        .status-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .status-tab {
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #2c3e50;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .status-tab:hover {
            background-color: #e9ecef;
        }
        
        .status-tab.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* Order details modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 80%;
            max-width: 800px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .order-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .order-info-column {
            flex: 1;
            min-width: 250px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: #7f8c8d;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-value {
            color: #2c3e50;
        }
        
        .order-items {
            margin-top: 20px;
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .order-items-table th, .order-items-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .order-items-table th {
            font-weight: 600;
            color: #2c3e50;
            background-color: #f8f9fa;
        }
        
        .order-total {
            text-align: right;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Status timeline */
        .status-timeline {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .timeline-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-steps:before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .timeline-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 20%;
        }
        
        .step-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            color: white;
            font-size: 14px;
        }
        
        .step-label {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .step-active .step-icon {
            background-color: #3498db;
        }
        
        .step-active .step-label {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .step-completed .step-icon {
            background-color: #2ecc71;
        }
        
        .step-rejected .step-icon {
            background-color: #e74c3c;
        }
        
        /* Book image in order details */
        .book-image-small {
            width: 50px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #bbb;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin: 0 0 10px;
            color: #2c3e50;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: #7f8c8d;
            margin: 0 0 20px;
        }
        
        .empty-state-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .empty-state-btn:hover {
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
            
            .order-info {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
                padding: 15px;
            }
            
            .order-items-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Fix for badge in the navbar */
        .notifications {
            position: relative;
            margin-right: 15px;
        }
        
        .notifications a {
            color: #333;
            font-size: 18px;
        }
        
        .badge {
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
        
        /* Fix for action buttons */
        .btn-view {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background-color: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .btn-view:hover {
            background-color: #2980b9;
        }
        
        /* Fix for cancel button */
        .reject-btn {
            padding: 8px 15px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        
        .reject-btn:hover {
            background-color: #c0392b;
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
                    <li>
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
                    <li class="active">
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
                    <!-- Empty for consistency with dashboard -->
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
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($user['fullname']); ?></span>
                        <img src="asset/profile.png" alt="User Avatar">
                        <div class="dropdown-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="customer_order.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1>My Orders</h1>
                <p>View and manage your orders</p>

                <!-- Status filter tabs -->
                <div class="status-tabs">
                    <a href="customer_order.php" class="status-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">All</a>
                    <a href="customer_order.php?status=pending" class="status-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="customer_order.php?status=accepted" class="status-tab <?php echo $status_filter === 'accepted' ? 'active' : ''; ?>">Accepted</a>
                    <a href="customer_order.php?status=processing" class="status-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">Processing</a>
                    <a href="customer_order.php?status=shipped" class="status-tab <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">Shipped</a>
                    <a href="customer_order.php?status=delivered" class="status-tab <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">Delivered</a>
                    <a href="customer_order.php?status=rejected" class="status-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                </div>

                <!-- Orders Table -->
                <div class="table-container">
                    <?php if (count($orders) > 0): ?>
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
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td><?php echo $order['item_count']; ?> item(s)</td>
                                        <td>ETB<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge-table badge-<?php echo strtolower($order['status']); ?> status-badge status-<?php echo strtolower($order['status']); ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No Orders Found</h3>
                            <?php if (!empty($status_filter)): ?>
                                <p>No <?php echo $status_filter; ?> orders found.</p>
                                <a href="customer_order.php" class="empty-state-btn">View All Orders</a>
                            <?php else: ?>
                                <p>You haven't placed any orders yet.</p>
                                <a href="explore.php" class="empty-state-btn">Start Shopping</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeOrderModal()">&times;</span>
            
            <div class="modal-header">
                <h2>Order Details</h2>
            </div>
            
            <div id="orderDetailsContent">
                <!-- Order details will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <script>
        // View Order Details
        function viewOrderDetails(orderId) {
            // Show modal
            document.getElementById('orderDetailsModal').style.display = 'block';
            
            // Show loading indicator
            document.getElementById('orderDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #3498db;"></i>
                    <p>Loading order details...</p>
                </div>
            `;
            
            // Load order details via AJAX
            fetch('get_order_details.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderOrderDetails(data.order);
                    } else {
                        document.getElementById('orderDetailsContent').innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <h3>Error</h3>
                                <p>${data.message}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <h3>Error</h3>
                            <p>An error occurred while loading order details.</p>
                        </div>
                    `;
                });
        }
        
        function renderOrderDetails(order) {
            let statusClass = 'status-' + order.status.toLowerCase();
            
            // Create status timeline
            let timelineHtml = createStatusTimeline(order.status);
            
            let html = `
                <div class="status-timeline">
                    <h3 class="timeline-title">Order Status</h3>
                    ${timelineHtml}
                </div>
                
                <div class="order-info">
                    <div class="order-info-column">
                        <div class="info-group">
                            <div class="info-label">Order ID</div>
                            <div class="info-value">ORD-${order.id}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Order Date</div>
                            <div class="info-value">${new Date(order.order_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="status-badge ${statusClass}">${order.status}</span>
                            </div>
                        </div>
                    </div>
                    <div class="order-info-column">
                        <div class="info-group">
                            <div class="info-label">Shipping Name</div>
                            <div class="info-value">${order.shipping_name}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Shipping Phone</div>
                            <div class="info-value">${order.shipping_phone}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Shipping Address</div>
                            <div class="info-value">${order.shipping_address}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Payment Method</div>
                            <div class="info-value">${order.payment_method.replace('_', ' ').toUpperCase()}</div>
                        </div>
                    </div>
                </div>
                
                <div class="order-items">
                    <h3>Order Items</h3>
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Title</th>
                                <th>Seller</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Group items by seller
            const sellerItems = {};
            order.items.forEach(item => {
                if (!sellerItems[item.seller_id]) {
                    sellerItems[item.seller_id] = {
                        seller_name: item.seller_name,
                        items: []
                    };
                }
                sellerItems[item.seller_id].items.push(item);
            });
            
            order.items.forEach(item => {
                let itemType = item.is_rental ? `Rental (${item.rental_days} days)` : 'Purchase';
                let returnDate = item.is_rental ? `<br><small>Return by: ${new Date(item.return_date).toLocaleDateString()}</small>` : '';
                let subtotal = parseFloat(item.price) * parseInt(item.quantity);
                let imagePath = item.image ? 'assets/' + item.image : 'images/default_book.jpg';
                
                html += `
                    <tr>
                        <td><img src="${imagePath}" alt="${item.title}" class="book-image-small" onerror="this.src='images/default_book.jpg'"></td>
                        <td>${item.title}${returnDate}</td>
                        <td>${item.seller_name}</td>
                        <td>${itemType}</td>
                        <td>${item.quantity}</td>
                        <td>ETB${parseFloat(item.price).toFixed(2)}</td>
                        <td>ETB${subtotal.toFixed(2)}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                    <div class="order-total">
                        Total: ETB${parseFloat(order.total_amount).toFixed(2)}
                    </div>
                </div>
            `;
            
            // Add special message for rejected orders
            if (order.status === 'rejected') {
                html += `
                    <div class="seller-info" style="margin-top: 20px; padding: 15px; border-left: 4px solid #e74c3c; background-color: #fff9f9;">
                        <div class="seller-info-title" style="font-weight: 600; margin-bottom: 8px; display: flex; align-items: center;">
                            <i class="fas fa-exclamation-circle" style="color: #e74c3c; margin-right: 8px;"></i> Order Rejected
                        </div>
                        <p style="margin: 0; color: #555;">We're sorry, but your order has been rejected by the seller. This could be due to stock unavailability or other issues. Please contact customer support if you have any questions.</p>
                    </div>
                `;
            }
            
            // Add mark as delivered button for shipped orders
            if (order.status === 'shipped') {
                html += `
            <div style="margin-top: 20px; text-align: right;">
                <button class="btn-primary action-btn" onclick="updateOrderStatus(${order.id}, '${order.status}')">
                    <i class="fas fa-check"></i> Mark as Delivered
                </button>
            </div>
        `;
    }
    
    // Keep the cancel button for pending orders
    if (order.status === 'pending') {
        html += `
            <div style="margin-top: 20px; text-align: right;">
                <button class="reject-btn" onclick="cancelOrder(${order.id})">
                    <i class="fas fa-times"></i> Cancel Order
                </button>
            </div>
        `;
    }
            
            document.getElementById('orderDetailsContent').innerHTML = html;
        }
        
        function createStatusTimeline(currentStatus) {
            // Define the steps based on the current status
            const steps = [
                { key: 'pending', label: 'Pending', icon: '<i class="fas fa-clock"></i>' },
                { key: 'accepted', label: 'Accepted', icon: '<i class="fas fa-check"></i>' },
                { key: 'processing', label: 'Processing', icon: '<i class="fas fa-cog"></i>' },
                { key: 'shipped', label: 'Shipped', icon: '<i class="fas fa-truck"></i>' },
                { key: 'delivered', label: 'Delivered', icon: '<i class="fas fa-box-open"></i>' }
            ];
            
            let html = `<div class="timeline-steps">`;
            
            // Special case for rejected orders
            if (currentStatus === 'rejected') {
                html += `
                    <div class="timeline-step step-active step-rejected">
                        <div class="step-icon"><i class="fas fa-times"></i></div>
                        <div class="step-label">Order Rejected</div>
                    </div>
                `;
            } else if (currentStatus === 'cancelled') {
                html += `
                    <div class="timeline-step step-active step-rejected">
                        <div class="step-icon"><i class="fas fa-ban"></i></div>
                        <div class="step-label">Order Cancelled</div>
                    </div>
                `;
            } else {
                // Regular order flow
                let currentStepFound = false;
                
                steps.forEach(step => {
                    let stepClass = 'timeline-step';
                    
                    // If the current status matches this step or we've already found the current step
                    if (currentStepFound) {
                        // Future step
                        stepClass += '';
                    } else if (currentStatus === step.key) {
                        // Current step
                        stepClass += ' step-active';
                        currentStepFound = true;
                    } else {
                        // Past step
                        stepClass += ' step-completed';
                    }
                    
                    html += `
                        <div class="${stepClass}">
                            <div class="step-icon">${step.icon}</div>
                            <div class="step-label">${step.label}</div>
                        </div>
                    `;
                });
            }
            
            html += `</div>`;
            return html;
        }
        
        function updateOrderStatus(orderId, currentStatus) {
        // Only allow updating to "delivered" status
        if (currentStatus !== 'shipped') {
            alert('Only orders that are shipped can be marked as delivered.');
            return;
        }
        
        if (confirm('Are you sure you want to mark this order as delivered?')) {
            // Show loading state
            const actionBtn = document.querySelector('.action-btn');
            if (actionBtn) {
                actionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                actionBtn.disabled = true;
            }
            
            fetch('update_customer_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: 'delivered'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Order marked as delivered successfully');
                    // Refresh the page to show updated status
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    // Reset button state
                    if (actionBtn) {
                        actionBtn.innerHTML = '<i class="fas fa-check"></i> Mark as Delivered';
                        actionBtn.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the order status');
                // Reset button state
                if (actionBtn) {
                    actionBtn.innerHTML = '<i class="fas fa-check"></i> Mark as Delivered';
                    actionBtn.disabled = false;
                }
            });
        }
    }
        
        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                // Show loading state
                const cancelBtn = document.querySelector('.reject-btn');
                cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
                cancelBtn.disabled = true;
                
                fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order cancelled successfully');
                        // Refresh the page to show updated status
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        // Reset button state
                        cancelBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Order';
                        cancelBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the order');
                    // Reset button state
                    cancelBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Order';
                    cancelBtn.disabled = false;
                });
            }
        }
        
        function closeOrderModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderDetailsModal');
            if (event.target == modal) {
                closeOrderModal();
            }
        }
        
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
    </script>
</body>
</html>
