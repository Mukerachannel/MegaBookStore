<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit;
}

// Initialize variables
$total_orders = 0;
$total_books = 0;
$total_managers = 0;
$total_sellers = 0;
$total_customers = 0;

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get total orders
try {
    $query = "SELECT COUNT(*) as total_orders FROM orders WHERE order_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $total_orders = $row['total_orders'];
    }
} catch (Exception $e) {
    error_log("Error fetching order stats: " . $e->getMessage());
}

// Get total books
try {
    $query = "SELECT COUNT(*) as total_books FROM books";
    $result = $conn->query($query);
    
    if ($result && $row = $result->fetch_assoc()) {
        $total_books = $row['total_books'];
    }
} catch (Exception $e) {
    error_log("Error fetching book count: " . $e->getMessage());
}

// Get user counts by role
try {
    $query = "SELECT role, COUNT(*) as count FROM users WHERE role NOT IN ('admin', 'manager', 'user') GROUP BY role";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['role'] == 'seller') {
                $total_sellers = $row['count'];
            } else if ($row['role'] == 'customer') {
                $total_customers = $row['count'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching user counts: " . $e->getMessage());
}

// Get detailed order information
$orders = [];
try {
    $query = "SELECT o.id, o.order_date, o.total_amount, o.status, 
              u.fullname as customer_name, COUNT(oi.id) as item_count
              FROM orders o
              JOIN users u ON o.customer_id = u.id
              JOIN order_items oi ON o.id = oi.order_id
              WHERE o.order_date BETWEEN ? AND ?
              GROUP BY o.id
              ORDER BY o.order_date DESC
              LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
}

// Get detailed book information
$books = [];
try {
    $query = "SELECT b.id, b.title, b.author, b.price, b.stock, b.is_rentable, 
              c.name as category_name, u.fullname as seller_name
              FROM books b
              LEFT JOIN categories c ON b.category_id = c.id
              LEFT JOIN users u ON b.seller_id = u.id
              ORDER BY b.created_at DESC
              LIMIT 20";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching books: " . $e->getMessage());
}

// Get detailed user information
$users = [];
try {
    $query = "SELECT id, fullname, email, phone, role, status, created_at
              FROM users
              WHERE role NOT IN ('admin', 'manager', 'user')
              ORDER BY created_at DESC
              LIMIT 20";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Report page specific styles */
        .dashboard-content {
            padding: 20px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e9f7fe;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .stat-icon i {
            font-size: 24px;
            color: #3498db;
        }
        
        .stat-info h3 {
            margin: 0 0 5px;
            font-size: 16px;
            color: #666;
        }
        
        .stat-info p {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        .report-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .report-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .report-section h2 i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-accepted {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-processing {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-shipped {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        
        .filter-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-form label {
            font-size: 14px;
            color: #666;
        }
        
        .filter-form input, .filter-form select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filter-form button {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            align-self: flex-end;
        }
        
        .filter-form button:hover {
            background-color: #2980b9;
        }
        
        /* Role badge styles */
        .role-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .role-manager {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .role-seller {
            background-color: #d4edda;
            color: #155724;
        }
        
        .role-customer {
            background-color: #e9f7fe;
            color: #3498db;
        }
        
        /* Sidebar toggle styles */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 999;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            width: 40px;
            height: 40px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .sidebar {
                position: fixed;
                left: -250px;
                transition: left 0.3s ease;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filter-form {
                flex-direction: column;
            }
        }
        
        /* Print styles */
        @media print {
            .sidebar, .top-nav, .filter-form, .sidebar-toggle {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .dashboard-content {
                padding: 0 !important;
            }
            
            .report-section, .stat-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
        
        /* Tab navigation for report sections */
        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        
        .tab-button {
            padding: 10px 20px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #333;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        
        .tab-button:hover {
            background-color: #e9ecef;
        }
        
        .tab-button.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* Stock status styles */
        .stock-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        
        .low-stock {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Export button styles */
        .export-btn {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }
        
        .export-btn:hover {
            background-color: #218838;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Toggle for Mobile -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Mega Books</h2>
                <span>Manager</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="approve_users.php">
                            <i class="fas fa-user-check"></i>
                            <span>Approve Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_sellers.php">
                            <i class="fas fa-users-cog"></i>
                            <span>Manage Sellers</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_customers.php">
                            <i class="fas fa-users"></i>
                            <span>Manage Customers</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
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
        <main class="main-content" id="mainContent">
            <!-- Top Navigation -->
            <header class="top-nav">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <span>Manager</span>
                        <a href="admin_profile.php"><img src="asset/profile.png" alt="Manager"></a>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1>Reports & Analytics</h1>
                <p>Overview of users, orders, and books</p>
                
                <!-- Filter Form -->
                <form class="filter-form" method="GET" action="reports.php">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <button type="submit">Apply Filters</button>
                    
                   
                        
                        <button type="button" onclick="exportToExcel();" class="export-btn">
                            <i class="fas fa-file-excel"></i> Export to Excel
                        </button>
                    </div>
                </form>
                
                <!-- Stats Cards -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p><?php echo $total_sellers + $total_customers; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Orders</h3>
                            <p><?php echo $total_orders; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Books</h3>
                            <p><?php echo $total_books; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #e1f5fe;">
                            <i class="fas fa-user-tie" style="color: #0c5460;"></i>
                        </div>
                        <div class="stat-info">
                            <h3>User Breakdown</h3>
                            <p>S: <?php echo $total_sellers; ?> | C: <?php echo $total_customers; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-button active" onclick="showTab('users-tab', event)">
                        <i class="fas fa-users"></i> Users
                    </button>
                    <button class="tab-button" onclick="showTab('orders-tab', event)">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </button>
                    <button class="tab-button" onclick="showTab('books-tab', event)">
                        <i class="fas fa-book"></i> Books
                    </button>
                </div>
                
                <!-- Users Report -->
                <div class="report-section" id="users-tab">
                    <h2><i class="fas fa-users"></i> User Details</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($user['status']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Orders Report -->
                <div class="report-section" id="orders-tab" style="display: none;">
                    <h2><i class="fas fa-shopping-cart"></i> Order Details</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($orders) > 0): ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td><?php echo $order['item_count']; ?> item(s)</td>
                                            <td>ETB<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
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
                
                <!-- Books Report -->
                <div class="report-section" id="books-tab" style="display: none;">
                    <h2><i class="fas fa-book"></i> Book Details</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Category</th>
                                    <th>Seller</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Rentable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($books) > 0): ?>
                                    <?php foreach ($books as $book): ?>
                                        <?php 
                                            $stock_class = 'in-stock';
                                            if ($book['stock'] <= 0) {
                                                $stock_class = 'out-of-stock';
                                            } else if ($book['stock'] < 5) {
                                                $stock_class = 'low-stock';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo $book['id']; ?></td>
                                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                                            <td><?php echo htmlspecialchars($book['category_name'] ?: 'Uncategorized'); ?></td>
                                            <td><?php echo htmlspecialchars($book['seller_name']); ?></td>
                                            <td>ETB<?php echo number_format($book['price'], 2); ?></td>
                                            <td>
                                                <span class="stock-status <?php echo $stock_class; ?>">
                                                    <?php echo $book['stock']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $book['is_rentable'] ? 'Yes' : 'No'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">No books found</td>
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
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
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
        });
        
        // Tab navigation
        function showTab(tabId, event) {
            // Hide all tabs
            document.querySelectorAll('.report-section').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Show selected tab
            document.getElementById(tabId).style.display = 'block';
            
            // Update active button
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Find the button that was clicked and add active class
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            } else {
                // Fallback if event is not provided
                document.querySelector(`.tab-button[onclick*="${tabId}"]`).classList.add('active');
            }
        }
        
        // Export to Excel function
        function exportToExcel() {
            // Get current date filters
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            // Redirect to export script with date parameters
            window.location.href = `export_sales.php?start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>
