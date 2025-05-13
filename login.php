<?php
session_start();
require_once 'db.php';

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

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Include sanitize_input function directly if it's not being found
  if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
      $data = trim($data);
      $data = stripslashes($data);
      $data = htmlspecialchars($data);
      return $data;
    }
  }

  $email = sanitize_input($_POST['email']);
  $password = $_POST['password'];
  
  // Validate input
  if (empty($email) || empty($password)) {
      $error = "Email and password are required";
  } else {
      // Check if user exists
      $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows == 1) {
          $user = $result->fetch_assoc();
          
          // Verify password
          if (password_verify($password, $user['password']) || $password === $user['password']) {
              // Check if user is active
              if ($user['status'] == 'active') {
                  // Set session variables
                  $_SESSION['user_id'] = $user['id'];
                  $_SESSION['fullname'] = $user['fullname'];
                  $_SESSION['email'] = $user['email'];
                  $_SESSION['role'] = $user['role'];
                  
                  // Redirect based on user role
                  switch ($user['role']) {
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
                      case 'pending':
                          header("Location: pending_approval.php");
                          break;
                      default:
                          header("Location: index.php");
                          break;
                  }
                  exit;
              } else {
                  $error = "Your account is inactive or suspended. Please contact support.";
              }
          } else {
              $error = "Invalid email or password";
          }
      } else {
          $error = "Invalid email or password";
      }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Mega Book Store</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="index.css">
  <style>
      .login-container {
          max-width: 400px;
          margin: 50px auto;
          padding: 30px;
          background-color: white;
          border-radius: 10px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      }
      
      .login-header {
          text-align: center;
          margin-bottom: 30px;
      }
      
      .login-header h1 {
          color: #3498db;
          margin-bottom: 10px;
      }
      
      .login-form .form-group {
          margin-bottom: 20px;
      }
      
      .login-form label {
          display: block;
          margin-bottom: 8px;
          font-weight: 500;
          color: #333;
      }
      
      .login-form input {
          width: 100%;
          padding: 12px 15px;
          border: 1px solid #ddd;
          border-radius: 5px;
          font-size: 16px;
          transition: border-color 0.3s;
      }
      
      .login-form input:focus {
          border-color: #3498db;
          outline: none;
      }
      
      .login-form .input-group {
          position: relative;
      }
      
      .login-form .input-group i.icon {
          position: absolute;
          top: 50%;
          left: 15px;
          transform: translateY(-50%);
          color: #666;
      }
      
      .login-form .input-group input {
          padding-left: 40px;
      }
      
      .login-form .password-toggle {
          position: absolute;
          top: 50%;
          right: 15px;
          transform: translateY(-50%);
          color: #666;
          cursor: pointer;
      }
      
      .login-form button {
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
      
      .login-form button:hover {
          background-color: #2980b9;
      }
      
      .login-footer {
          text-align: center;
          margin-top: 20px;
      }
      
      .login-footer a {
          color: #3498db;
          text-decoration: none;
      }
      
      .login-footer a:hover {
          text-decoration: underline;
      }
      
      .error-message {
          background-color: #f8d7da;
          color: #721c24;
          padding: 10px;
          border-radius: 5px;
          margin-bottom: 20px;
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

  <!-- Login Form -->
  <div class="login-container">
      <div class="login-header">
          <h1>Login</h1>
          <p>Sign in to your account</p>
      </div>
      
      <?php if (!empty($error)): ?>
          <div class="error-message">
              <?php echo $error; ?>
          </div>
      <?php endif; ?>
      
      <form class="login-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
          <div class="form-group">
              <label for="email">Email</label>
              <div class="input-group">
                  <i class="fas fa-envelope icon"></i>
                  <input type="email" id="email" name="email" placeholder="Enter your email" required>
              </div>
          </div>
          
          <div class="form-group">
              <label for="password">Password</label>
              <div class="input-group">
                  <i class="fas fa-lock icon"></i>
                  <input type="password" id="password" name="password" placeholder="Enter your password" required>
                  <i class="fas fa-eye password-toggle" id="togglePassword"></i>
              </div>
          </div>
          
          <button type="submit">Login</button>
      </form>
      
      <div class="login-footer">
          <p>Don't have an account? <a href="customer_signup.php">Sign up</a></p>
          <p><a href="forgot_password.php">Forgot password?</a></p>
      </div>
  </div>


  <script>
      document.addEventListener('DOMContentLoaded', function() {
          // Toggle password visibility
          const togglePassword = document.getElementById('togglePassword');
          const passwordInput = document.getElementById('password');
          
          togglePassword.addEventListener('click', function() {
              const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
              passwordInput.setAttribute('type', type);
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
