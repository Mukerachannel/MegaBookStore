<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get counts for dashboard stats
$admin_count = 0;
$manager_count = 0;
$seller_count = 0;
$customer_count = 0;

// Get admin count
try {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $admin_count = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching admin count: " . $e->getMessage());
}

// Get manager count
try {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'manager'";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $manager_count = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching manager count: " . $e->getMessage());
}

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

// Get pending approval users
$pending_users = [];
try {
    $query = "SELECT id, fullname, email, created_at FROM users 
              WHERE role = 'pending' 
              ORDER BY created_at DESC";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pending_users[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching pending users: " . $e->getMessage());
}

// Get recent admins and managers
$recent_staff = [];
try {
    $query = "SELECT id, fullname, email, role, created_at FROM users 
              WHERE role IN ('admin', 'manager') AND id != {$_SESSION['user_id']}
              ORDER BY created_at DESC LIMIT 5";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_staff[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent staff: " . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Admin Dashboard specific styles */
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
        
        /* Approval buttons */
        .approval-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-approve-admin, .btn-approve-manager {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 12px;
        }
        
        .btn-approve-admin {
            background-color: #2ecc71;
        }
        
        .btn-approve-manager {
            background-color: #3498db;
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
                <span>Super Admin</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="super_admin_dashboard.php">
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
                        <a href="create_admin.php">
                            <i class="fas fa-user-shield"></i>
                            <span> Manage Admin</span>
                        </a>
                    </li>
                    <li>
                        <a href="create_manager.php">
                            <i class="fas fa-users-cog"></i>
                            <span> Manage Manager</span>
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
                        <span>Super Admin</span>
                        <a href="super_profile.php"><img src="asset/profile.png" alt="Admin"></a>
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
                            <a href="super_admin.php" class="btn-action btn-back">Back to Dashboard</a>
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
                        <div class="user-actions">
                            <a href="user_details.php?id=<?php echo $user_details['id']; ?>" class="btn-action btn-view">View Full Details</a>
                        </div>
                    </div>
                <?php else: ?>
                    <h1>Super Admin Dashboard</h1>
                    <p>Welcome to the super admin control panel</p>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <div class="action-card">
                            <i class="fas fa-user-plus"></i>
                            <h3>Add Admin</h3>
                            <p>Create a new admin account</p>
                            <a href="create_admin.php">Add Now</a>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-user-tie"></i>
                            <h3>Add Manager</h3>
                            <p>Create a new manager account</p>
                            <a href="create_manager.php">Add Now</a>
                        </div>
                        
                        <div class="action-card">
                            <i class="fas fa-user-circle"></i>
                            <h3>User Details</h3>
                            <p>View detailed user information</p>
                            <a href="user_details.php">View Details</a>
                        </div>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Admins</h3>
                                <p><?php echo $admin_count; ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Managers</h3>
                                <p><?php echo $manager_count; ?></p>
                            </div>
                        </div>
                        
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
                                              <td class="approval-actions">
                                                  <a href="approve_users.php?action=approve_admin&id=<?php echo $user['id']; ?>" class="btn-approve-admin">
                                                      Approve as Admin
                                                  </a>
                                                  <a href="approve_users.php?action=approve_manager&id=<?php echo $user['id']; ?>" class="btn-approve-manager">
                                                      Approve as Manager
                                                  </a>
                                                  <a href="super_admin.php?action=view&id=<?php echo $user['id']; ?>" class="btn-view">
                                                      <i class="fas fa-eye"></i>
                                                  </a>
                                                  <a href="approve_users.php?action=delete&id=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this user?')">
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
                  </div>
                    
                  <!-- Recent Staff -->
                  <div class="recent-section">
                      <h2>Recent Staff Members</h2>
                      <div class="table-container">
                          <table class="data-table">
                              <thead>
                                  <tr>
                                      <th>ID</th>
                                      <th>Name</th>
                                      <th>Email</th>
                                      <th>Role</th>
                                      <th>Joined Date</th>
                                      <th>Actions</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if (count($recent_staff) > 0): ?>
                                      <?php foreach ($recent_staff as $staff): ?>
                                          <tr>
                                              <td>#<?php echo $staff['id']; ?></td>
                                              <td><?php echo htmlspecialchars($staff['fullname']); ?></td>
                                              <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                              <td>
                                                  <span class="badge badge-<?php echo $staff['role']; ?>">
                                                      <?php echo ucfirst($staff['role']); ?>
                                                  </span>
                                              </td>
                                              <td><?php echo date('M d, Y', strtotime($staff['created_at'])); ?></td>
                                              <td class="actions">
                                                  <a href="user_details.php?action=view&id=<?php echo $staff['id']; ?>" class="btn-view">
                                                      <i class="fas fa-eye"></i>
                                                  </a>
                                                  <a href="approve_users.php?action=delete&id=<?php echo $staff['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this staff member?')">
                                                      <i class="fas fa-trash"></i>
                                                  </a>
                                              </td>
                                          </tr>
                                      <?php endforeach; ?>
                                  <?php else: ?>
                                      <tr>
                                          <td colspan="6" style="text-align: center;">No staff members found</td>
                                      </tr>
                                  <?php endif; ?>
                              </tbody>
                          </table>
                      </div>
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
