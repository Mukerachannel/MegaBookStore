<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

// Get counts for dashboard stats
$seller_count = 0;
$customer_count = 0;
$pending_count = 0;

// Get seller count
try {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'seller'";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $seller_count = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching seller count: " . $e->getMessage());
}

// Get customer count
try {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $customer_count = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching customer count: " . $e->getMessage());
}

// Get pending approval count
try {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'pending'";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $pending_count = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching pending count: " . $e->getMessage());
}

// Get pending approval users
$pending_users = [];
try {
    $query = "SELECT id, fullname, email, created_at FROM users 
              WHERE role = 'pending' 
              ORDER BY created_at DESC LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pending_users[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching pending users: " . $e->getMessage());
}

// Get recent sellers
$recent_sellers = [];
try {
    $query = "SELECT id, fullname, email, created_at FROM users 
              WHERE role = 'seller'
              ORDER BY created_at DESC LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_sellers[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent sellers: " . $e->getMessage());
}

// Get recent customers
$recent_customers = [];
try {
    $query = "SELECT id, fullname, email, created_at FROM users 
              WHERE role = 'customer'
              ORDER BY created_at DESC LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_customers[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent customers: " . $e->getMessage());
}

// View user details
$user_details = null;
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user_details = $result->fetch_assoc();
        } else {
            $error_message = "User not found.";
        }
    } else {
        $error_message = "Database error: " . $conn->error;
    }
}

// Delete user
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Check if user is a seller or customer (managers can only delete these roles)
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['role'] === 'seller' || $user['role'] === 'customer' || $user['role'] === 'pending') {
            // Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success_message = "User has been deleted successfully.";
                
                // Redirect to dashboard to refresh counts
                header("Location: manager_dashboard.php?deleted=true");
                exit;
            } else {
                $error_message = "Error deleting user: " . $stmt->error;
            }
        } else {
            $error_message = "You do not have permission to delete this user.";
        }
    } else {
        $error_message = "User not found.";
    }
}

// Check for deletion success message from redirect
if (isset($_GET['deleted']) && $_GET['deleted'] === 'true') {
    $success_message = "User has been deleted successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Manager Dashboard specific styles */
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
        
        .recent-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .recent-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #333;
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
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-seller {
            background-color: #f39c12;
            color: #fff;
        }
        
        .badge-customer {
            background-color: #9b59b6;
            color: #fff;
        }
        
        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-view, .btn-delete {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
        }
        
        .btn-view {
            background-color: #3498db;
        }
        
        .btn-delete {
            background-color: #e74c3c;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
        }
        
        .action-card i {
            font-size: 36px;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            margin: 0 0 10px;
            font-size: 18px;
            color: #333;
        }
        
        .action-card p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .action-card a {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        
        /* User details card */
        .user-details-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .user-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .user-details-header h2 {
            margin: 0;
        }
        
        .user-details-content {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .user-detail-item {
            margin-bottom: 15px;
        }
        
        .user-detail-item label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .user-detail-item p {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-action {
            padding: 8px 15px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-back {
            background-color: #6c757d;
        }
        
        .btn-delete-lg {
            background-color: #e74c3c;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                    <li class="active">
                        <a href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="approve_sellers.php">
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
                    <li>
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
                <?php if ($user_details): ?>
                    <!-- User Details View -->
                    <div class="user-details-card">
                        <div class="user-details-header">
                            <h2>User Details</h2>
                            <a href="admin_dashboard.php" class="btn-action btn-back">Back to Dashboard</a>
                        </div>
                        <div class="user-details-content">
                            <div>
                                <div class="user-detail-item">
                                    <label>Full Name</label>
                                    <p><?php echo htmlspecialchars($user_details['fullname']); ?></p>
                                </div>
                                <div class="user-detail-item">
                                    <label>Email</label>
                                    <p><?php echo htmlspecialchars($user_details['email']); ?></p>
                                </div>
                                <div class="user-detail-item">
                                    <label>Phone</label>
                                    <p><?php echo htmlspecialchars($user_details['phone'] ?? 'Not provided'); ?></p>
                                </div>
                            </div>
                            <div>
                                <div class="user-detail-item">
                                    <label>Address</label>
                                    <p><?php echo htmlspecialchars($user_details['address'] ?? 'Not provided'); ?></p>
                                </div>
                                <div class="user-detail-item">
                                    <label>Role</label>
                                    <p>
                                        <span class="badge <?php echo 'badge-' . $user_details['role']; ?>">
                                            <?php echo ucfirst($user_details['role']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="user-detail-item">
                                    <label>Status</label>
                                    <p><?php echo ucfirst($user_details['status']); ?></p>
                                </div>
                                <div class="user-detail-item">
                                    <label>Joined On</label>
                                    <p><?php echo date('F d, Y', strtotime($user_details['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php if ($user_details['role'] === 'seller' || $user_details['role'] === 'customer' || $user_details['role'] === 'pending'): ?>
                            <div class="user-actions">
                                <a href="admin_dashboard.php?action=delete&id=<?php echo $user_details['id']; ?>" class="btn-action btn-delete-lg" onclick="return confirm('Are you sure you want to delete this user?')">
                                    Delete User
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <h1>Manager Dashboard</h1>
                    <p>Welcome to the manager control panel</p>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <div class="action-card">
                            <i class="fas fa-user-check"></i>
                            <h3>Approve Users</h3>
                            <p>Approve pending user requests</p>
                            <a href="approve_users.php">Manage</a>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-users-cog"></i>
                            <h3>Manage Sellers</h3>
                            <p>View and manage seller accounts</p>
                            <a href="manage_sellers.php">Manage</a>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-users"></i>
                            <h3>Manage Customers</h3>
                            <p>View and manage customer accounts</p>
                            <a href="manage_customers.php">Manage</a>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-chart-line"></i>
                            <h3>View Reports</h3>
                            <p>Check system reports</p>
                            <a href="reports.php">View Reports</a>
                        </div>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Sellers</h3>
                                <p><?php echo $seller_count; ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Customers</h3>
                                <p><?php echo $customer_count; ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Pending Approvals</h3>
                                <p><?php echo $pending_count; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Approvals -->
                    <div class="recent-section">
                        <h2>Pending User Approvals</h2>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Requested Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($pending_users) > 0): ?>
                                        <?php foreach ($pending_users as $user): ?>
                                            <tr>
                                                <td>#<?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="actions">
                                                    <a href="admin_dashboard.php?action=view&id=<?php echo $user['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="admin_dashboard.php?action=delete&id=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center;">No pending approvals</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($pending_users) > 0): ?>
                            <div style="text-align: right; margin-top: 10px;">
                                <a href="approve_user.php" style="color: #3498db; text-decoration: none;">View All Pending Users</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Sellers -->
                    <div class="recent-section">
                        <h2>Recent Sellers</h2>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_sellers) > 0): ?>
                                        <?php foreach ($recent_sellers as $seller): ?>
                                            <tr>
                                                <td>#<?php echo $seller['id']; ?></td>
                                                <td><?php echo htmlspecialchars($seller['fullname']); ?></td>
                                                <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($seller['created_at'])); ?></td>
                                                <td class="actions">
                                                    <a href="manage_sellers.php?action=view&id=<?php echo $seller['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="manager_dashboard.php?action=delete&id=<?php echo $seller['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this seller?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center;">No sellers found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($recent_sellers) > 0): ?>
                            <div style="text-align: right; margin-top: 10px;">
                                <a href="manage_sellers.php" style="color: #3498db; text-decoration: none;">View All Sellers</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Customers -->
                    <div class="recent-section">
                        <h2>Recent Customers</h2>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_customers) > 0): ?>
                                        <?php foreach ($recent_customers as $customer): ?>
                                            <tr>
                                                <td>#<?php echo $customer['id']; ?></td>
                                                <td><?php echo htmlspecialchars($customer['fullname']); ?></td>
                                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                                <td class="actions">
                                                    <a href="manage_customers.php?action=view&id=<?php echo $customer['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="manager_dashboard.php?action=delete&id=<?php echo $customer['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this customer?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center;">No customers found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($recent_customers) > 0): ?>
                            <div style="text-align: right; margin-top: 10px;">
                                <a href="manage_customers.php" style="color: #3498db; text-decoration: none;">View All Customers</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
    </script>
</body>
</html>

