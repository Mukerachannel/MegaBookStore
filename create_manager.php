<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle manager deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
  $manager_id = (int)$_GET['id'];
  
  // Check if user is a manager
  $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
  $stmt->bind_param("i", $manager_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result && $result->num_rows > 0) {
      $user = $result->fetch_assoc();
      if ($user['role'] === 'manager') {
          // Delete the manager
          $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
          $stmt->bind_param("i", $manager_id);
          
          if ($stmt->execute()) {
              $success_message = "Manager has been deleted successfully.";
          } else {
              $error_message = "Error deleting manager: " . $stmt->error;
          }
      } else {
          $error_message = "Selected user is not a manager.";
      }
  } else {
      $error_message = "Manager not found.";
  }
}

// Get all managers
$managers = [];
try {
  $query = "SELECT id, fullname, email, created_at FROM users 
            WHERE role = 'manager'
            ORDER BY created_at DESC";
  $result = $conn->query($query);
  if ($result) {
      while ($row = $result->fetch_assoc()) {
          $managers[] = $row;
      }
  }
} catch (Exception $e) {
  error_log("Error fetching managers: " . $e->getMessage());
  $error_message = "Error fetching managers: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Managers - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Manage Managers specific styles */
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
        
        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .required-field::after {
            content: " *";
            color: #e74c3c;
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
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .table-container h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #333;
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
                    <li class="active">
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
                        <a href="admin_profile.php"><img src="asset/profile.png" alt="Admin"></a>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="section-header">
                    <h1>Manage Manager Accounts</h1>
                    <a href="super_admin.php" class="btn btn-secondary">Back to Dashboard</a>
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
                
                <div class="table-container">
                    <h2>Existing Managers</h2>
                    <p>To create new manager accounts, please use the <a href="approve_users.php" style="color: #3498db;">Approve Users</a> page to approve pending users as managers.</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php            
                        if (count($managers) > 0) {
                            foreach ($managers as $manager) {
                                echo '
                                <tr>
                                    <td>#' . $manager['id'] . '</td>
                                    <td>' . htmlspecialchars($manager['fullname']) . '</td>
                                    <td>' . htmlspecialchars($manager['email']) . '</td>
                                    <td>' . date('M d, Y', strtotime($manager['created_at'])) . '</td>
                                    <td class="actions">
                                        <a href="user_details.php?id=' . $manager['id'] . '" class="btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="manage_managers.php?action=delete&id=' . $manager['id'] . '" class="btn-delete" onclick="return confirm(\'Are you sure you want to delete this manager?\')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" style="text-align: center;">No managers found</td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
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
    </script>
</body>
</html>
