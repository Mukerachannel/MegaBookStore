<?php
session_start();
require_once 'db.php';

// Check if password_resets table exists, create if it doesn't
$table_check = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE password_resets (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_table_sql) === TRUE) {
        // Table created successfully
    } else {
        die("Error creating password_resets table: " . $conn->error);
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: super_admin.php");
            break;
        case 'manager':
            header("Location: admin_dashboard.php");
            break;
        case 'seller':
            header("Location: seller_dashboard.php");
            break;
        case 'customer':
            header("Location: explore.php");
            break;
        default:
            // For pending users
            header("Location: pending_approval.php");
            break;
    }
    exit;
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// Include sanitize_input function directly if it's not being found
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

// Process step 1: Verify email and phone
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 1) {
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    
    // Validate input
    if (empty($email) || empty($phone)) {
        $error = "Email and phone number are required";
    } else {
        // Check if user exists with this email and phone
        $stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ? AND phone = ?");
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Generate a token
            $token = bin2hex(random_bytes(16)); // 32 characters
            
            // Set expiration time (1 hour from now)
            $expires = date('Y-m-d H:i:s', time() + 3600);
            
            // Delete any existing tokens for this email
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            // Store token in database
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token, $expires);
            
            if ($stmt->execute()) {
                // Redirect to step 2
                header("Location: forgot_password.php?step=2&email=" . urlencode($email) . "&token=" . urlencode($token));
                exit;
            } else {
                $error = "Database error: " . $stmt->error;
            }
        } else {
            $error = "No account found with this email and phone number combination";
        }
    }
}

// Process step 2: Reset password
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 2) {
    $email = sanitize_input($_POST['email']);
    $token = sanitize_input($_POST['token']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($password) || empty($confirm_password)) {
        $error = "Both password fields are required";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Verify token is valid
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ?");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $reset = $result->fetch_assoc();
            
            // Check if token is expired
            $expires_at = strtotime($reset['expires_at']);
            $now = time();
            
            if ($expires_at > $now) {
                // Hash new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update user password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->bind_param("ss", $hashed_password, $email);
                
                if ($stmt->execute()) {
                    // Delete used token
                    $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    
                    $success = "Password has been reset successfully! You will be redirected to the login page.";
                    
                    // Redirect to login page after 5 seconds
                    header("refresh:5;url=login.php");
                } else {
                    $error = "Error updating password: " . $stmt->error;
                }
            } else {
                $error = "Password reset link has expired. Please request a new one.";
            }
        } else {
            $error = "Invalid password reset link. Please request a new one.";
        }
    }
}

// Check if we're on step 2 with GET parameters
if ($step == 2 && !isset($_POST['email']) && isset($_GET['email']) && isset($_GET['token'])) {
    $email = sanitize_input($_GET['email']);
    $token = sanitize_input($_GET['token']);
    
    // Verify token is valid
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ?");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows != 1) {
        $error = "Invalid password reset link. Please request a new one.";
        // Redirect back to step 1
        header("Location: forgot_password.php?step=1&error=invalid_token");
        exit;
    } else {
        $reset = $result->fetch_assoc();
        
        // Check if token is expired
        $expires_at = strtotime($reset['expires_at']);
        $now = time();
        
        if ($expires_at < $now) {
            $error = "Password reset link has expired. Please request a new one.";
            // Redirect back to step 1
            header("Location: forgot_password.php?step=1&error=expired_token");
            exit;
        }
    }
}

// Check if there's an error parameter in the URL
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'invalid_token') {
        $error = "Invalid password reset link. Please request a new one.";
    } elseif ($_GET['error'] == 'expired_token') {
        $error = "Password reset link has expired. Please request a new one.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Mega Book Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .forgot-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .forgot-header h1 {
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .forgot-form .form-group {
            margin-bottom: 20px;
        }
        
        .forgot-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .forgot-form input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .forgot-form input:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .forgot-form .input-group {
            position: relative;
        }
        
        .forgot-form .input-group i.icon {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #666;
        }
        
        .forgot-form .input-group input {
            padding-left: 40px;
        }
        
        .forgot-form .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
        }
        
        .forgot-form button {
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
        
        .forgot-form button:hover {
            background-color: #2980b9;
        }
        
        .forgot-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .forgot-footer a:hover {
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
        
        .steps {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        
        .step.active {
            background-color: #3498db;
            color: white;
        }
        
        .step-line {
            height: 2px;
            width: 50px;
            background-color: #e0e0e0;
            margin-top: 15px;
        }
        
        .step-line.active {
            background-color: #3498db;
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
                <li><a href="books.php" class="nav-link">Books</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
                <li><a href="login.php" class="nav-link login-btn">Login</a></li>
            </ul>
        </div>
    </nav>

    <!-- Forgot Password Form -->
    <div class="forgot-container">
        <div class="forgot-header">
            <h1>Forgot Password</h1>
            <p><?php echo $step == 1 ? 'Verify your account' : 'Reset your password'; ?></p>
        </div>
        
        <div class="steps">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
            <div class="step-line <?php echo $step >= 2 ? 'active' : ''; ?>"></div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
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
        
        <?php if ($step == 1): ?>
            <!-- Step 1: Verify Email and Phone -->
            <form class="forgot-form" method="POST" action="forgot_password.php?step=1">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope icon"></i>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-group">
                        <i class="fas fa-phone icon"></i>
                        <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
                    </div>
                </div>
                
                <button type="submit">Verify Account</button>
            </form>
        <?php elseif ($step == 2): ?>
            <!-- Step 2: Reset Password -->
            <form class="forgot-form" method="POST" action="forgot_password.php?step=2">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" id="password" name="password" placeholder="Enter new password" required>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
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
                
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
        
        <div class="forgot-footer">
            <p>Remember your password? <a href="login.php">Login</a></p>
            <p>Don't have an account? <a href="signup.php">Sign up</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            if (togglePassword) {
                const passwordInput = document.getElementById('password');
                
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
            
            // Toggle confirm password visibility
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            if (toggleConfirmPassword) {
                const confirmPasswordInput = document.getElementById('confirm_password');
                
                toggleConfirmPassword.addEventListener('click', function() {
                    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
            
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
