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

// Delete user
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Check if user is a customer (managers can only delete customers)
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['role'] === 'customer') {
            // Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Customer has been deleted successfully.";
            } else {
                $error_message = "Error deleting customer: " . $stmt->error;
            }
        } else {
            $error_message = "You do not have permission to delete this user.";
        }
    } else {
        $error_message = "User not found.";
    }
}

// Get all customers
$customers = [];
try {
    $query = "SELECT id, fullname, email, phone, status, created_at FROM users 
              WHERE role = 'customer' 
              ORDER BY created_at DESC";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching customers: " . $e->getMessage());
    $error_message = "Error fetching customers: " . $e->getMessage();
}

// View user details
$user_details = null;
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    $query = "SELECT * FROM users WHERE id = ? AND role = 'customer'";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user_details = $result->fetch_assoc();
        } else {
            $error_message = "Customer not found.";
        }
    } else {
        $error_message = "Database error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Manage Customers specific styles */
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
        
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
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
        
        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-suspended {
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
                    <li>
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
                    <li class="active">
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
                <div class="section-header">
                    <h1>Manage Customers</h1>
                    <a href="admin_dashboard.php" class="btn-action btn-back">Back to Dashboard</a>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($user_details): ?>
                    <!-- User Details View -->
                    <div class="user-details-card">
                        <div class="user-details-header">
                            <h2>Customer Details</h2>
                            <a href="manage_customers.php" class="btn-action btn-back">Back to List</a>
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
                            </div>
                        </div>
                        <div class="user-actions">
                            <a href="manage_customers.php?action=delete&id=<?php echo $user_details['id']; ?>" class="btn-action btn-delete-lg" onclick="return confirm('Are you sure you want to delete this customer?')">
                                Delete Customer
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Customers Table -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Joined Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($customers) > 0): ?>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td>#<?php echo $customer['id']; ?></td>
                                            <td><?php echo htmlspecialchars($customer['fullname']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['phone'] ?? 'Not provided'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($customer['status']); ?>">
                                                    <?php echo ucfirst($customer['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                            <td class="actions">
                                                <a href="manage_customers.php?action=view&id=<?php echo $customer['id']; ?>" class="btn-view">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="manage_customers.php?action=delete&id=<?php echo $customer['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this customer?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No customers found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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

