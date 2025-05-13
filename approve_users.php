<?php
session_start();
require_once 'db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.php");
  exit;
}

$success_message = '';
$error_message = '';

// Handle user approval actions
if (isset($_GET['action']) && isset($_GET['id'])) {
  $action = $_GET['action'];
  $user_id = (int)$_GET['id'];
  
  // Approve as admin (only super admin can do this)
  if ($action === 'approve_admin') {
      $query = "UPDATE users SET role = 'admin', status = 'active' WHERE id = ?";
      $stmt = $conn->prepare($query);
      
      if ($stmt) {
          $stmt->bind_param("i", $user_id);
          if ($stmt->execute()) {
              $success_message = "User has been approved as an admin successfully.";
          } else {
              $error_message = "Error approving user: " . $conn->error;
          }
      } else {
          $error_message = "Database error: " . $conn->error;
      }
  }
  
  // Approve as manager (only super admin can do this)
  else if ($action === 'approve_manager') {
      $query = "UPDATE users SET role = 'manager', status = 'active' WHERE id = ?";
      $stmt = $conn->prepare($query);
      
      if ($stmt) {
          $stmt->bind_param("i", $user_id);
          if ($stmt->execute()) {
              $success_message = "User has been approved as a manager successfully.";
          } else {
              $error_message = "Error approving user: " . $conn->error;
          }
      } else {
          $error_message = "Database error: " . $conn->error;
      }
  }
  
  // Delete user
  else if ($action === 'delete') {
      $query = "DELETE FROM users WHERE id = ?";
      $stmt = $conn->prepare($query);
      
      if ($stmt) {
          $stmt->bind_param("i", $user_id);
          if ($stmt->execute()) {
              $success_message = "User has been deleted successfully.";
          } else {
              $error_message = "Error deleting user: " . $conn->error;
          }
      } else {
          $error_message = "Database error: " . $conn->error;
      }
  }
}

// Get all pending users
$pending_users = [];
try {
  $query = "SELECT id, fullname, email, phone, address, created_at FROM users 
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
  $error_message = "Error fetching pending users: " . $e->getMessage();
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
  <title>Approve Admins & Managers - Mega Book Store</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
      /* Approve Users specific styles */
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
      
      .badge-pending {
          background-color: #fff3cd;
          color: #856404;
      }
      
      .actions {
          display: flex;
          gap: 10px;
      }
      
      .btn-view, .btn-edit, .btn-delete, .btn-approve {
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
      
      .btn-edit {
          background-color: #f39c12;
      }
      
      .btn-delete {
          background-color: #e74c3c;
      }
      
      .btn-approve {
          background-color: #2ecc71;
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
      
      .btn-approve-admin-lg {
          background-color: #2ecc71;
      }
      
      .btn-approve-manager-lg {
          background-color: #3498db;
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
          
          .approval-actions {
              flex-direction: column;
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
                  <li>
                      <a href="super_admin.php">
                          <i class="fas fa-tachometer-alt"></i>
                          <span>Dashboard</span>
                      </a>
                  </li>
                  <li class="active">
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
                          <span>Manage Managers</span>
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
                      <a href="admin_profile.php">
                          <img src="asset/profile.png" alt="Admin">
                      </a>
                  </div>
              </div>
          </header>

          <!-- Dashboard Content -->
          <div class="dashboard-content">
              <div class="section-header">
                  <h1>Approve Admins & Managers</h1>
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
                          <h2>User Details</h2>
                          <a href="approve_users.php" class="btn-action" style="background-color: #6c757d;">Back to List</a>
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
                                  <p><span class="badge badge-pending">Pending Approval</span></p>
                              </div>
                              <div class="user-detail-item">
                                  <label>Registered On</label>
                                  <p><?php echo date('F d, Y', strtotime($user_details['created_at'])); ?></p>
                              </div>
                          </div>
                      </div>
                      <div class="user-actions">
                          <a href="approve_users.php?action=approve_admin&id=<?php echo $user_details['id']; ?>" class="btn-action btn-approve-admin-lg">
                              Approve as Admin
                          </a>
                          <a href="approve_users.php?action=approve_manager&id=<?php echo $user_details['id']; ?>" class="btn-action btn-approve-manager-lg">
                              Approve as Manager
                          </a>
                          <a href="approve_users.php?action=delete&id=<?php echo $user_details['id']; ?>" class="btn-action btn-delete-lg" onclick="return confirm('Are you sure you want to delete this user?')">
                              Delete User
                          </a>
                      </div>
                  </div>
              <?php else: ?>
                  <!-- Pending Users Table -->
                  <div class="table-container">
                      <table class="data-table">
                          <thead>
                              <tr>
                                  <th>ID</th>
                                  <th>Name</th>
                                  <th>Email</th>
                                  <th>Phone</th>
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
                                          <td><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></td>
                                          <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                          <td class="approval-actions">
                                              <a href="approve_users.php?action=approve_admin&id=<?php echo $user['id']; ?>" class="btn-approve-admin">
                                                  Approve as Admin
                                              </a>
                                              <a href="approve_users.php?action=approve_manager&id=<?php echo $user['id']; ?>" class="btn-approve-manager">
                                                  Approve as Manager
                                              </a>
                                              <a href="approve_users.php?action=view&id=<?php echo $user['id']; ?>" class="btn-view">
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
                                      <td colspan="6" style="text-align: center;">No pending approvals</td>
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
