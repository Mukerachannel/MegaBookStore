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

// Get admin details
$admin_id = $_SESSION['user_id'];
$admin_data = [];

try {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
    } else {
        $error_message = "Admin data not found.";
    }
} catch (Exception $e) {
    error_log("Error fetching admin data: " . $e->getMessage());
    $error_message = "Error fetching admin data: " . $e->getMessage();
}

// Process profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Include sanitize_input function directly if it's not being found
    if (!function_exists('sanitize_input')) {
        function sanitize_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }
    }

    $fullname = sanitize_input($_POST['fullname']);
    $email = sanitize_input($_POST['email']);
    $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
    $address = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';

    // Validate input
    if (empty($fullname) || empty($email)) {
        $error_message = "Name and email are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Check if email already exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Email already exists. Please use a different email.";
        } else {
            // Update profile
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $fullname, $email, $phone, $address, $admin_id);
            
            if ($stmt->execute()) {
                $success_message = "Profile updated successfully!";
                
                // Update session data
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $email;
                
                // Refresh admin data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $admin_data = $result->fetch_assoc();
                }
            } else {
                $error_message = "Error updating profile: " . $stmt->error;
            }
        }
    }
}

// Process password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required";
    } elseif ($new_password != $confirm_password) {
        $error_message = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long";
    } else {
        // Verify current password
        if (password_verify($current_password, $admin_data['password']) || $current_password === $admin_data['password']) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $admin_id);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error changing password: " . $stmt->error;
            }
        } else {
            $error_message = "Current password is incorrect";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Admin Profile specific styles */
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
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        
        .profile-sidebar {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            overflow: hidden;
            border: 5px solid #f0f0f0;
        }
        
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info h2 {
            margin: 0 0 5px;
            font-size: 20px;
        }
        
        .profile-info p {
            margin: 0 0 15px;
            color: #666;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 5px 15px;
            background-color: #d4edda;
            color: #155724;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        
        .stat-item {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .stat-item h3 {
            margin: 0 0 5px;
            font-size: 14px;
            color: #666;
        }
        
        .stat-item p {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .profile-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .profile-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .profile-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
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
        
        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
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
                            <span>Create Admin</span>
                        </a>
                    </li>
                    <li>
                        <a href="create_manager.php">
                            <i class="fas fa-users-cog"></i>
                            <span>Create Manager</span>
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
                        <span>Super Admin</span>
                        <a href="admin_profile.php"><img src="asset/profile.png" alt="Admin"></a>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="section-header">
                    <h1>My Profile</h1>
                    <a href="super_admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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
                
                <div class="profile-container">
                    <!-- Profile Sidebar -->
                    <div class="profile-sidebar">
                        <div class="profile-image">
                            <img src="asset/profile.png" alt="Admin Profile">
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($admin_data['fullname']); ?></h2>
                            <p><?php echo htmlspecialchars($admin_data['email']); ?></p>
                            <div class="profile-badge">Super Admin</div>
                        </div>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <h3>Joined</h3>
                                <p><?php echo date('M d, Y', strtotime($admin_data['created_at'])); ?></p>
                            </div>
                            <div class="stat-item">
                                <h3>Status</h3>
                                <p><?php echo ucfirst($admin_data['status']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Content -->
                    <div class="profile-content">
                        <!-- Edit Profile -->
                        <div class="profile-card">
                            <h2>Edit Profile</h2>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <div class="form-group">
                                    <label for="fullname" class="required-field">Full Name</label>
                                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($admin_data['fullname']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="required-field">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($admin_data['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($admin_data['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="profile-card">
                            <h2>Change Password</h2>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <div class="form-group">
                                    <label for="current_password" class="required-field">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="new_password" class="required-field">New Password</label>
                                        <input type="password" id="new_password" name="new_password" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password" class="required-field">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                </div>
                            </form>
                        </div>
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
    </script>
</body>
</html>

