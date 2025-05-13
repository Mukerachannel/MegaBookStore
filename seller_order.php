<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: login.php");
    exit;
}

// Get seller information
$seller_id = $_SESSION['user_id'];
$seller = get_user_by_id($conn, $seller_id);

// Get seller orders (orders containing books sold by this seller)
$orders = [];
try {
    $query = "SELECT DISTINCT o.*, u.fullname as customer_name, u.phone as customer_phone
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN books b ON oi.book_id = b.id
              JOIN users u ON o.customer_id = u.id
              WHERE b.seller_id = ?
              ORDER BY o.order_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get items for this order that belong to this seller
        $items_query = "SELECT oi.*, b.title, b.author, b.image, b.price as book_price
                       FROM order_items oi
                       JOIN books b ON oi.book_id = b.id
                       WHERE oi.order_id = ? AND b.seller_id = ?";
        
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param("ii", $row['id'], $seller_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $items = [];
        $total_for_seller = 0;
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
            $total_for_seller += ($item['price'] * $item['quantity']);
        }
        
        $row['items'] = $items;
        $row['item_count'] = count($items);
        $row['total_for_seller'] = $total_for_seller;
        
        // Only add orders that have items from this seller
        if (count($items) > 0) {
            $orders[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching seller orders: " . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Orders - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Additional styles for seller orders */
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
        
        .status-update-form {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .status-update-form select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .accept-btn {
            padding: 8px 15px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .accept-btn:hover {
            background-color: #27ae60;
        }
        
        .reject-btn {
            padding: 8px 15px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .reject-btn:hover {
            background-color: #c0392b;
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
        
        .update-btn {
            padding: 5px 10px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        
        .update-btn:hover {
            background-color: #27ae60;
        }

        .status-note {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Mega Books</h2>
                <span>Seller Panel</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="seller_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="<?php echo in_array($page, ['manage_books', 'add_book', 'edit_book', 'view_book']) ? 'active' : ''; ?>">
                        <a href="seller_dashboard.php?page=manage_books">
                            <i class="fas fa-book"></i>
                            <span>Manage Books</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="seller_order.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="seller_profile.php">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
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
            <!-- Top Navigation -->
            <header class="top-nav">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search for orders...">
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($seller['fullname']); ?></span>
                        <img src="asset/profile.png" alt="User">
                        <div class="dropdown-menu">
                            <a href="seller_profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1>Manage Orders</h1>
                
                <!-- Status filter tabs -->
                <div class="status-tabs">
                    <a href="seller_order.php" class="status-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">All</a>
                    <a href="seller_order.php?status=pending" class="status-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="seller_order.php?status=accepted" class="status-tab <?php echo $status_filter === 'accepted' ? 'active' : ''; ?>">Accepted</a>
                    <a href="seller_order.php?status=processing" class="status-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">Processing</a>
                    <a href="seller_order.php?status=shipped" class="status-tab <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">Shipped</a>
                    <a href="seller_order.php?status=delivered" class="status-tab <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">Delivered</a>
                    <a href="seller_order.php?status=rejected" class="status-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                </div>
                
                <div class="table-container">
                    <?php if (count($orders) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>ORD-<?php echo $order['id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo $order['item_count']; ?> item(s)</td>
                                        <td>ETB<?php echo number_format($order['total_for_seller'], 2); ?></td>
                                        <td>
                                            <span class="badge-table badge-<?php echo strtolower($order['status']); ?> status-badge status-<?php echo strtolower($order['status']); ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 50px 20px;">
                            <i class="fas fa-shopping-bag" style="font-size: 48px; color: #bbb; margin-bottom: 20px;"></i>
                            <h3 style="margin: 0 0 10px; color: #2c3e50; font-size: 1.5rem;">No Orders Found</h3>
                            <?php if (!empty($status_filter)): ?>
                                <p style="color: #7f8c8d; margin: 0 0 20px;">No <?php echo $status_filter; ?> orders found.</p>
                                <a href="seller_order.php" style="display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; border-radius: 5px; text-decoration: none; font-weight: 600;">View All Orders</a>
                            <?php else: ?>
                                <p style="color: #7f8c8d; margin: 0 0 20px;">You haven't received any orders for your books yet.</p>
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
            fetch('get_seller_order_details.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderOrderDetails(data.order);
                    } else {
                        document.getElementById('orderDetailsContent').innerHTML = `
                            <div style="text-align: center; padding: 30px;">
                                <i class="fas fa-exclamation-circle" style="font-size: 30px; color: #e74c3c;"></i>
                                <h3>Error</h3>
                                <p>${data.message}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div style="text-align: center; padding: 30px;">
                            <i class="fas fa-exclamation-circle" style="font-size: 30px; color: #e74c3c;"></i>
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
                    <div class="info-label">Customer</div>
                    <div class="info-value">${order.customer_name}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Phone</div>
                    <div class="info-value">${order.customer_phone}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Shipping Address</div>
                    <div class="info-value">${order.shipping_address || 'Not provided'}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value">${order.payment_method ? order.payment_method.replace('_', ' ').toUpperCase() : 'Not specified'}</div>
                </div>
            </div>
        </div>
        
        <div class="order-items">
            <h3>Your Books in This Order</h3>
            <table class="order-items-table">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>`;
    
    let totalAmount = 0;
    
    order.items.forEach(item => {
        let itemType = item.is_rental ? `Rental (${item.rental_days} days)` : 'Purchase';
        let returnDate = item.is_rental ? `<br><small>Return by: ${new Date(item.return_date).toLocaleDateString()}</small>` : '';
        let subtotal = parseFloat(item.price) * parseInt(item.quantity);
        let imagePath = item.image ? 'assets/' + item.image : 'images/default_book.jpg';
        
        totalAmount += subtotal;
        
        html += `
            <tr>
                <td><img src="${imagePath}" alt="${item.title}" class="book-image-small" onerror="this.src='images/default_book.jpg'"></td>
                <td>${item.title}${returnDate}</td>
                <td>${itemType}</td>
                <td>${item.quantity}</td>
                <td>ETB${parseFloat(item.price).toFixed(2)}</td>
                <td>ETB${subtotal.toFixed(2)}</td>
            </tr>`;
    });
    
    html += `
                </tbody>
            </table>
            <div class="order-total">
                Total for Your Books: ETB${totalAmount.toFixed(2)}
            </div>
        </div>`;
    
    // Add action buttons based on order status
    if (order.status === 'pending') {
        html += `
            <div class="action-buttons">
                <button class="accept-btn" onclick="updateOrderStatus(${order.id}, 'accepted')">
                    <i class="fas fa-check"></i> Accept Order
                </button>
                <button class="reject-btn" onclick="updateOrderStatus(${order.id}, 'rejected')">
                    <i class="fas fa-times"></i> Reject Order
                </button>
            </div>`;
    }
    
    // Add status update form based on order status
    if (order.status !== 'rejected' && order.status !== 'cancelled' && order.status !== 'delivered') {
        html += `
            <div class="status-update-form">
                <h3>Update Order Status</h3>
                <form id="updateStatusForm">
                    <input type="hidden" id="orderId" value="${order.id}">
                    <select id="orderStatus">`;
                    
        // Only show appropriate status options based on current status
        if (order.status === 'pending') {
            html += `
                <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                <option value="accepted" ${order.status === 'accepted' ? 'selected' : ''}>Accepted</option>
                <option value="rejected" ${order.status === 'rejected' ? 'selected' : ''}>Rejected</option>`;
        } else if (order.status === 'accepted') {
            html += `
                <option value="accepted" ${order.status === 'accepted' ? 'selected' : ''}>Accepted</option>
                <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>`;
        } else if (order.status === 'processing') {
            html += `
                <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>
                <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>`;
        } else if (order.status === 'shipped') {
            html += `
                <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>`;
        }
                    
        html += `
                    </select>
                    <button type="button" class="update-btn" onclick="updateOrderStatus(${order.id}, document.getElementById('orderStatus').value)">Update Status</button>
                </form>
                <p class="status-note">Note: Only customers can mark orders as delivered.</p>
            </div>`;
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
    
    let html = '<div class="timeline-steps">';
    
    // Special case for rejected orders
    if (currentStatus === 'rejected') {
        html += `
            <div class="timeline-step step-active step-rejected">
                <div class="step-icon"><i class="fas fa-times"></i></div>
                <div class="step-label">Order Rejected</div>
            </div>`;
    } else if (currentStatus === 'cancelled') {
        html += `
            <div class="timeline-step step-active step-rejected">
                <div class="step-icon"><i class="fas fa-ban"></i></div>
                <div class="step-label">Order Cancelled</div>
            </div>`;
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
                </div>`;
        });
    }
    
    html += '</div>';
    return html;
}
        
        function updateOrderStatus(orderId, status) {
    // Show loading state
    const buttons = document.querySelectorAll('.action-buttons button, .update-btn');
    buttons.forEach(btn => {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;
    });
    
    fetch('update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            order_id: orderId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Order status updated successfully');
            // Refresh the page to show updated status
            location.reload();
        } else {
            alert('Error: ' + data.message);
            // Reset button state
            buttons.forEach(btn => {
                btn.disabled = false;
                if (btn.classList.contains('accept-btn')) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Accept Order';
                } else if (btn.classList.contains('reject-btn')) {
                    btn.innerHTML = '<i class="fas fa-times"></i> Reject Order';
                } else {
                    btn.innerHTML = 'Update Status';
                }
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating order status');
        // Reset button state
        buttons.forEach(btn => {
            btn.disabled = false;
            if (btn.classList.contains('accept-btn')) {
                btn.innerHTML = '<i class="fas fa-check"></i> Accept Order';
            } else if (btn.classList.contains('reject-btn')) {
                btn.innerHTML = '<i class="fas fa-times"></i> Reject Order';
            } else {
                btn.innerHTML = 'Update Status';
            }
        });
    });
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
    </script>
</body>
</html>
