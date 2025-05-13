<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Include sanitize_input function directly if it's not being found
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    header("Location: login.php");
    exit;
}

$user = $result->fetch_assoc();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password']) || $current_password === $user['password']) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Error updating password: " . $stmt->error;
            }
        } else {
            $error = "Current password is incorrect";
        }
    }
}

// Determine which dashboard to return to based on user role
$dashboard_url = "index.php";
switch ($user['role']) {
    case 'admin':
        $dashboard_url = "super_admin.php";
        break;
    case 'manager':
        $dashboard_url = "admin_dashboard.php";
        break;
    case 'seller':
        $dashboard_url = "seller_dashboard.php";
        break;
    case 'customer':
        $dashboard_url = "explore.php";
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .reset-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h1 {
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .reset-form .form-group {
            margin-bottom: 20px;
        }
        
        .reset-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .reset-form input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .reset-form input:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .reset-form .input-group {
            position: relative;
        }
        
        .reset-form .input-group i.icon {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #666;
        }
        
        .reset-form .input-group input {
            padding-left: 40px;
        }
        
        .reset-form .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
        }
        
        .reset-form button {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .reset-form button:hover {
            background-color: #2980b9;
        }
        
        .reset-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .reset-footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .reset-footer a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .button-group button {
            flex: 1;
        }
        
        .button-group .cancel-btn {
            background-color: #6c757d;
        }
        
        .button-group .cancel-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <h1>Mega Books</h1>
            </div>
            <div class="menu-toggle" id="mobile-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
            <ul class="nav-menu">
            <li><a href="index.php" class="nav-link">Home</a></li>
              <li><a href="index.php#services" class="nav-link">Services</a></li>
              <li><a href="explore.php" class="nav-link">Books</a></li>
              <li><a href="about.php" class="nav-link">About</a></li>
              <li><a href="index.php#contact-form" class="nav-link">Contact</a></li>
              
            </ul>
        </div>
    </nav>

    <!-- Reset Password Form -->
    <div class="reset-container">
        <div class="reset-header">
            <h1>Reset Password</h1>
            <p>Change your account password</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form class="reset-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="input-group">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
                    <i class="fas fa-eye password-toggle" id="toggleCurrentPassword"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="input-group">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                    <i class="fas fa-eye password-toggle" id="toggleNewPassword"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-group">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                </div>
            </div>
            
            <div class="button-group">
                <a href="<?php echo $dashboard_url; ?>" class="cancel-btn" style="flex: 1; text-align: center; padding: 12px; background-color: #6c757d; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 500; text-decoration: none;">Cancel</a>
                <button type="submit">Update Password</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle current password visibility
            const toggleCurrentPassword = document.getElementById('toggleCurrentPassword');
            const currentPasswordInput = document.getElementById('current_password');
            
            toggleCurrentPassword.addEventListener('click', function() {
                const type = currentPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                currentPasswordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Toggle new password visibility
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const newPasswordInput = document.getElementById('new_password');
            
            toggleNewPassword.addEventListener('click', function() {
                const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                newPasswordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Toggle confirm password visibility
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Mobile menu toggle
            const mobileMenu = document.getElementById('mobile-menu');
            const navMenu = document.querySelector('.nav-menu');
            
            mobileMenu.addEventListener('click', function() {
                mobileMenu.classList.toggle('active');
                navMenu.classList.toggle('active');
            });
        });
    </script>
</body>
</html>
