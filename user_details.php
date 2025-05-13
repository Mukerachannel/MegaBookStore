<?php
session_start();
require_once 'db.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: login.php");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    // If no specific user ID is provided, fetch all users
    $all_users = [];
    $query = "SELECT id, fullname, email, role, status, created_at FROM users ORDER BY role, created_at DESC";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
} else {
    // If user ID is provided, get detailed information for that user
    $user_id = (int)$_GET['id'];
    $user_details = null;
    $error_message = '';

    // Get user details
    try {
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
    } catch (Exception $e) {
        error_log("Error fetching user details: " . $e->getMessage());
        $error_message = "Error fetching user details: " . $e->getMessage();
    }

    // Handle user deletion
    if (isset($_POST['delete_user']) && isset($_POST['confirm_delete'])) {
        // Check if the user has permission to delete this user
        $can_delete = false;

        if ($_SESSION['role'] === 'admin') {
            // Super admin can delete any user except themselves
            $can_delete = ($user_id != $_SESSION['user_id']);
        } else if ($_SESSION['role'] === 'manager') {
            // Manager can only delete sellers and customers
            $can_delete = ($user_details['role'] === 'seller' || $user_details['role'] === 'customer');
        }

        // Add a specific error message if trying to delete own account
        if ($user_id == $_SESSION['user_id'] && $_SESSION['role'] === 'admin') {
            $error_message = "Super admins cannot delete their own accounts for security reasons.";
        }
        
        if ($can_delete) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                // Redirect to appropriate dashboard with success message
                if ($_SESSION['role'] === 'admin') {
                    header("Location: super_admin_dashboard.php?deleted=true");
                } else {
                    header("Location: manager_dashboard.php?deleted=true");
                }
                exit;
            } else {
                $error_message = "Error deleting user: " . $stmt->error;
            }
        } else {
            $error_message = "You do not have permission to delete this user.";
        }
    }

    // Get user statistics based on role
    $statistics = [];

    if ($user_details) {
        try {
            if ($user_details['role'] === 'seller') {
                // Get book count for seller
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM books WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['books'] = $row['count'];
                }
                
                // Get total sales for seller
                $stmt = $conn->prepare("
                    SELECT COALESCE(SUM(o.total_amount), 0) as total 
                    FROM orders o 
                    JOIN books b ON o.book_id = b.id 
                    WHERE b.user_id = ?
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['sales'] = $row['total'] ?? 0;
                }
                
                // Get average rating for seller
                $stmt = $conn->prepare("
                    SELECT AVG(r.rating) as avg_rating 
                    FROM reviews r 
                    JOIN books b ON r.book_id = b.id 
                    WHERE b.user_id = ?
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['rating'] = $row['avg_rating'] ?? 0;
                }
            } else if ($user_details['role'] === 'customer') {
                // Get order count for customer
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['orders'] = $row['count'];
                }
                
                // Get total spent for customer
                $stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE customer_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['spent'] = $row['total'] ?? 0;
                }
                
                // Get review count for customer
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE customer_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['reviews'] = $row['count'];
                }
            } else if ($user_details['role'] === 'manager') {
                // Get count of users created by this manager
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_by = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['created_users'] = $row['count'];
                }
                
                // Get count of approved users by this manager
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_approvals WHERE approved_by = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $statistics['approved_users'] = $row['count'];
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching user statistics: " . $e->getMessage());
            // Don't show the error to the user, just log it
        }
    }

    // Get user activity log
    $activity_log = [];
    try {
        $query = "SELECT * FROM user_activity_log WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $activity_log[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching activity log: " . $e->getMessage());
    }
}

// Get back URL based on user role
$back_url = ($_SESSION['role'] === 'admin') ? 'super_admin_dashboard.php' : 'manager_dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* User details specific styles */
        .dashboard-content {
            padding: 20px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h1 {
            margin: 0;
        }
        
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
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-admin {
            background-color: #2ecc71;
            color: white;
        }
        
        .badge-manager {
            background-color: #3498db;
            color: white;
        }
        
        .badge-seller {
            background-color: #f39c12;
            color: white;
        }
        
        .badge-customer {
            background-color: #9b59b6;
            color: white;
        }
        
        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
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
        
        .btn-delete {
            background-color: #e74c3c;
        }
        
        .btn-edit {
            background-color: #3498db;
        }
        
        .user-statistics {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .user-statistics h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            color: #333;
        }
        
        .statistics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .statistic-item {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
        }
        
        .statistic-item h4 {
            margin: 0 0 5px;
            font-size: 14px;
            color: #666;
        }
        
        .statistic-item p {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        /* Delete confirmation modal */
        .delete-confirmation {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            color: #721c24;
        }
        
        .delete-confirmation h3 {
            margin-top: 0;
            font-size: 16px;
        }
        
        .delete-confirmation p {
            margin-bottom: 15px;
        }
        
        .delete-confirmation form {
            display: flex;
            gap: 10px;
        }
        
        .delete-confirmation .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .delete-confirmation .checkbox-group input {
            margin-right: 10px;
        }
        
        .delete-confirmation button {
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        
        .delete-confirmation .btn-delete {
            background-color: #e74c3c;
            color: white;
        }
        
        .delete-confirmation .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Activity log */
        .activity-log {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .activity-log h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            color: #333;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item .activity-time {
            font-size: 12px;
            color: #666;
        }
        
        .activity-item .activity-description {
            margin: 5px 0 0;
        }
        
        /* User list table */
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
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-view, .btn-delete-small {
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
        
        .btn-delete-small {
            background-color: #e74c3c;
        }
        
        /* Additional user details */
        .additional-details {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .additional-details h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            color: #333;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        /* Tabs for different sections */
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 500;
        }
        
        .tab.active {
            border-bottom-color: #3498db;
            color: #3498db;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .user-details-content {
                grid-template-columns: 1fr;
            }
            
            .statistics-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                overflow-x: auto;
                white-space: nowrap;
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
                <span><?php echo ($_SESSION['role'] === 'admin') ? 'Super Admin' : 'Manager'; ?></span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'super_admin_dashboard.php' : 'manager_dashboard.php'; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li>
                            <a href="approve_users.php">
                                <i class="fas fa-user-check"></i>
                                <span>Approve Users</span>
                            </a>
                        </li>
                        <li>
                            <a href="create_admin.php">
                                <i class="fas fa-user-shield"></i>
                                <span>Manage Admins</span>
                            </a>
                        </li>
                        <li>
                            <a href="create_manager.php">
                                <i class="fas fa-users-cog"></i>
                                <span>Manage Manager</span>
                            </a>
                        </li>
                        
                    <?php else: ?>
                        <li>
                            <a href="approve_customers_sellers.php">
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
                    <?php endif; ?>
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
                        <span><?php echo ($_SESSION['role'] === 'admin') ? 'Super Admin' : 'Manager'; ?></span>
                        <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'super_profile.php' : 'manager_profile.php'; ?>">
                            <img src="asset/profile.png" alt="Profile">
                        </a>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <?php if (!isset($_GET['id'])): ?>
                    <!-- User List View -->
                    <div class="section-header">
                        <h1>User Details</h1>
                        <a href="super_admin.php"  class="btn-action btn-back">Back to Dashboard</a>
                    </div>
                    
                    <div class="user-details-card">
                        <div class="user-details-header">
                            <h2>All Users</h2>
                        </div>
                        
                        <div class="tabs">
                            <div class="tab active" data-tab="all-users">All Users</div>
                            <div class="tab" data-tab="admins">Admins</div>
                            <div class="tab" data-tab="managers">Managers</div>
                            <div class="tab" data-tab="sellers">Sellers</div>
                            <div class="tab" data-tab="customers">Customers</div>
                        </div>
                        
                        <div class="tab-content active" id="all-users">
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Joined Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($all_users)): ?>
                                            <?php foreach ($all_users as $user): ?>
                                                <tr>
                                                    <td>#<?php echo $user['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php echo strtolower($user['status']); ?>">
                                                            <?php echo ucfirst($user['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                    <td class="actions">
                                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn-view">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($_SESSION['role'] === 'admin' || ($user['role'] === 'seller' || $user['role'] === 'customer')): ?>
                                                            <a href="#" class="btn-delete-small" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
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
                        
                        <div class="tab-content" id="admins">
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Joined Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $admins = array_filter($all_users ?? [], function($user) {
                                            return $user['role'] === 'admin';
                                        });
                                        
                                        if (!empty($admins)): 
                                            foreach ($admins as $user): 
                                        ?>
                                            <tr>
                                                <td>#<?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo strtolower($user['status']); ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="actions">
                                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($_SESSION['role'] === 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="#" class="btn-delete-small" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php 
                                            endforeach; 
                                        else: 
                                        ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center;">No admin users found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="managers">
                            <!-- Similar structure for managers -->
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Joined Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $managers = array_filter($all_users ?? [], function($user) {
                                            return $user['role'] === 'manager';
                                        });
                                        
                                        if (!empty($managers)): 
                                            foreach ($managers as $user): 
                                        ?>
                                            <tr>
                                                <td>#<?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo strtolower($user['status']); ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="actions">
                                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                                        <a href="#"  === 'admin'): ?>
                                                        <a href="#" class="btn-delete-small" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php 
                                            endforeach; 
                                        else: 
                                        ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center;">No manager users found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="sellers">
                            <!-- Similar structure for sellers -->
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Joined Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $sellers = array_filter($all_users ?? [], function($user) {
                                            return $user['role'] === 'seller';
                                        });
                                        
                                        if (!empty($sellers)): 
                                            foreach ($sellers as $user): 
                                        ?>
                                            <tr>
                                                <td>#<?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo strtolower($user['status']); ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="actions">
                                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn-delete-small" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php 
                                            endforeach; 
                                        else: 
                                        ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center;">No seller users found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="customers">
                            <!-- Similar structure for customers -->
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Joined Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $customers = array_filter($all_users ?? [], function($user) {
                                            return $user['role'] === 'customer';
                                        });
                                        
                                        if (!empty($customers)): 
                                            foreach ($customers as $user): 
                                        ?>
                                            <tr>
                                                <td>#<?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo strtolower($user['status']); ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="actions">
                                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn-delete-small" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php 
                                            endforeach; 
                                        else: 
                                        ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center;">No customer users found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Single User Details View -->
                    <div class="section-header">
                        <h1>User Details</h1>
                        <a href="user_details.php" class="btn-action btn-back">Back to All Users</a>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($user_details): ?>
                        <div class="user-details-card">
                            <div class="user-details-header">
                                <h2><?php echo htmlspecialchars($user_details['fullname']); ?></h2>
                                <span class="badge badge-<?php echo $user_details['role']; ?>">
                                    <?php echo ucfirst($user_details['role']); ?>
                                </span>
                            </div>
                            
                            <div class="tabs">
                                <div class="tab active" data-tab="basic-info">Basic Info</div>
                                <div class="tab" data-tab="statistics">Statistics</div>
                                <div class="tab" data-tab="activity">Activity Log</div>
                                <?php if ($user_details['role'] === 'seller'): ?>
                                    <div class="tab" data-tab="books">Books</div>
                                <?php elseif ($user_details['role'] === 'customer'): ?>
                                    <div class="tab" data-tab="orders">Orders</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="tab-content active" id="basic-info">
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
                                        <div class="user-detail-item">
                                            <label>Address</label>
                                            <p><?php echo htmlspecialchars($user_details['address'] ?? 'Not provided'); ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="user-detail-item">
                                            <label>User ID</label>
                                            <p>#<?php echo $user_details['id']; ?></p>
                                        </div>
                                        <div class="user-detail-item">
                                            <label>Role</label>
                                            <p>
                                                <span class="badge badge-<?php echo $user_details['role']; ?>">
                                                    <?php echo ucfirst($user_details['role']); ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="user-detail-item">
                                            <label>Status</label>
                                            <p>
                                                <span class="badge badge-<?php echo strtolower($user_details['status']); ?>">
                                                    <?php echo ucfirst($user_details['status']); ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="user-detail-item">
                                            <label>Joined On</label>
                                            <p><?php echo date('F d, Y', strtotime($user_details['created_at'])); ?></p>
                                        </div>
                                        <div class="user-detail-item">
                                            <label>Last Login</label>
                                            <p><?php echo isset($user_details['last_login']) ? date('F d, Y H:i', strtotime($user_details['last_login'])) : 'Never'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="additional-details">
                                    <h3>Additional Information</h3>
                                    <div class="detail-grid">
                                        <?php if ($user_details['role'] === 'seller'): ?>
                                            <div class="user-detail-item">
                                                <label>Store Name</label>
                                                <p><?php echo htmlspecialchars($user_details['store_name'] ?? 'Not set'); ?></p>
                                            </div>
                                            <div class="user-detail-item">
                                                <label>Business Type</label>
                                                <p><?php echo htmlspecialchars($user_details['business_type'] ?? 'Not specified'); ?></p>
                                            </div>
                                        <?php elseif ($user_details['role'] === 'customer'): ?>
                                            <div class="user-detail-item">
                                                <label>Shipping Address</label>
                                                <p><?php echo htmlspecialchars($user_details['shipping_address'] ?? 'Not provided'); ?></p>
                                            </div>
                                            <div class="user-detail-item">
                                                <label>Billing Address</label>
                                                <p><?php echo htmlspecialchars($user_details['billing_address'] ?? 'Not provided'); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="user-actions">
                                    <a href="#" class="btn-action btn-edit">Edit User</a>
                                    <button id="deleteUserBtn" class="btn-action btn-delete">Delete User</button>
                                </div>
                                
                                <div id="deleteConfirmation" class="delete-confirmation" style="display: none;">
                                    <h3>Confirm Deletion</h3>
                                    <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $user_id); ?>">
                                        <div class="checkbox-group">
                                            <input type="checkbox" id="confirmDelete" name="confirm_delete" required>
                                            <label for="confirmDelete">I understand this action is permanent</label>
                                        </div>
                                        <button type="button" id="cancelDelete" class="btn-cancel">Cancel</button>
                                        <button type="submit" name="delete_user" class="btn-delete">Delete User</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="tab-content" id="statistics">
                                <?php if (!empty($statistics)): ?>
                                    <div class="user-statistics">
                                        <h3>User Statistics</h3>
                                        <div class="statistics-grid">
                                            <?php if (isset($statistics['books'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Books Listed</h4>
                                                    <p><?php echo $statistics['books']; ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($statistics['sales'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Total Sales</h4>
                                                    <p>$<?php echo number_format($statistics['sales'], 2); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($statistics['rating'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Average Rating</h4>
                                                    <p><?php echo number_format($statistics['rating'], 1); ?> / 5.0</p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($statistics['orders'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Orders Placed</h4>
                                                    <p><?php echo $statistics['orders']; ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($statistics['spent'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Total Spent</h4>
                                                    <p>$<?php echo number_format($statistics['spent'], 2); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($statistics['reviews'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Reviews Written</h4>
                                                    <p><?php echo $statistics['reviews']; ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($statistics['created_users'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Users Created</h4>
                                                    <p><?php echo $statistics['created_users']; ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($statistics['approved_users'])): ?>
                                                <div class="statistic-item">
                                                    <h4>Users Approved</h4>
                                                    <p><?php echo $statistics['approved_users']; ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p>No statistics available for this user.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="tab-content" id="activity">
                                <div class="activity-log">
                                    <h3>Recent Activity</h3>
                                    <?php if (!empty($activity_log)): ?>
                                        <?php foreach ($activity_log as $activity): ?>
                                            <div class="activity-item">
                                                <div class="activity-time">
                                                    <?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?>
                                                </div>
                                                <p class="activity-description">
                                                    <?php echo htmlspecialchars($activity['description']); ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>No recent activity found for this user.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($user_details['role'] === 'seller'): ?>
                                <div class="tab-content" id="books">
                                    <h3>Books Listed by This Seller</h3>
                                    <!-- Books listing would go here -->
                                    <p>Book listing functionality to be implemented.</p>
                                </div>
                            <?php elseif ($user_details['role'] === 'customer'): ?>
                                <div class="tab-content" id="orders">
                                    <h3>Orders Placed by This Customer</h3>
                                    <!-- Orders listing would go here -->
                                    <p>Order listing functionality to be implemented.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            User not found or you don't have permission to view this user.
                        </div>
                    <?php endif; ?>
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
            
            // Delete user confirmation
            const deleteUserBtn = document.getElementById('deleteUserBtn');
            const deleteConfirmation = document.getElementById('deleteConfirmation');
            const cancelDelete = document.getElementById('cancelDelete');
            
            if (deleteUserBtn && deleteConfirmation && cancelDelete) {
                deleteUserBtn.addEventListener('click', function() {
                    deleteConfirmation.style.display = 'block';
                });
                
                cancelDelete.addEventListener('click', function() {
                    deleteConfirmation.style.display = 'none';
                });
            }
            
            // Tab switching
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all tab content
                    const tabContents = document.querySelectorAll('.tab-content');
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Show the selected tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Confirm delete function for user list
            window.confirmDelete = function(userId) {
                if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                    window.location.href = 'user_details.php?id=' + userId + '&action=delete';
                }
            };
        });
    </script>
</body>
</html>
