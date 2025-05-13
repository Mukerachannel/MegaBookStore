<?php
session_start();
require_once 'db.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
  // Redirect based on user role
  switch ($_SESSION['role']) {
      case 'admin':
          header("Location: super_admin_dashboard.php");
          break;
      case 'manager':
          header("Location: manager_dashboard.php");
          break;
      case 'seller':
          header("Location: seller_dashboard.php");
          break;
      case 'customer':
          header("Location: books.php");
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

// Process signup form
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

  $fullname = sanitize_input($_POST['fullname']);
  $email = sanitize_input($_POST['email']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
  $address = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';
  
  // Validate input
  if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password) || empty($phone) || empty($address)) {
      $error = "All fields are required";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = "Invalid email format";
  } elseif ($password != $confirm_password) {
      $error = "Passwords do not match";
  } elseif (strlen($password) < 6) {
      $error = "Password must be at least 6 characters long";
  } else {
      // Check if email already exists
      $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
          $error = "Email already exists. Please use a different email or login.";
      } else {
          // Hash password
          $hashed_password = password_hash($password, PASSWORD_DEFAULT);
          
          // Set role as pending by default
          $role = 'pending';
          
          // Insert new user
          $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?)");
          $stmt->bind_param("ssssss", $fullname, $email, $hashed_password, $phone, $address, $role);
          
          if ($stmt->execute()) {
              $success = "Registration successful! Your account is pending approval. You will be redirected to the login page.";
              
              // Redirect to login page after 5 seconds
              header("refresh:5;url=login.php");
          } else {
              $error = "Error: " . $stmt->error;
          }
      }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - Mega Book Store</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="index.css">
  <style>
      .signup-container {
          max-width: 500px;
          margin: 50px auto;
          padding: 30px;
          background-color: white;
          border-radius: 10px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      }
      
      .signup-header {
          text-align: center;
          margin-bottom: 30px;
      }
      
      .signup-header h1 {
          color: #3498db;
          margin-bottom: 10px;
      }
      
      .signup-form .form-group {
          margin-bottom: 20px;
      }
      
      .signup-form label {
          display: block;
          margin-bottom: 8px;
          font-weight: 500;
          color: #333;
      }
      
      .signup-form input, .signup-form textarea {
          width: 100%;
          padding: 12px 15px;
          border: 1px solid #ddd;
          border-radius: 5px;
          font-size: 16px;
          transition: border-color 0.3s;
      }
      
      .signup-form textarea {
          resize: vertical;
          min-height: 100px;
      }
      
      .signup-form input:focus, .signup-form textarea:focus {
          border-color: #3498db;
          outline: none;
      }
      
      .signup-form .input-group {
          position: relative;
      }
      
      .signup-form .input-group i.icon {
          position: absolute;
          top: 50%;
          left: 15px;
          transform: translateY(-50%);
          color: #666;
      }
      
      .signup-form .input-group input {
          padding-left: 40px;
      }
      
      .signup-form .password-toggle {
          position: absolute;
          top: 50%;
          right: 15px;
          transform: translateY(-50%);
          color: #666;
          cursor: pointer;
      }
      
      .signup-form button {
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
      
      .signup-form button:hover {
          background-color: #2980b9;
      }
      
      .signup-footer {
          text-align: center;
          margin-top: 20px;
      }
      
      .signup-footer a {
          color: #3498db;
          text-decoration: none;
      }
      
      .signup-footer a:hover {
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
      
      .form-row {
          display: flex;
          gap: 20px;
      }
      
      .form-row .form-group {
          flex: 1;
      }
      
      @media (max-width: 768px) {
          .form-row {
              flex-direction: column;
              gap: 0;
          }
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

  <!-- Signup Form -->
  <div class="signup-container">
      <div class="signup-header">
          <h1>Sign Up</h1>
          <p>Create a new account</p>
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
      
      <form class="signup-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
          <div class="form-group">
              <label for="fullname">Full Name</label>
              <div class="input-group">
                  <i class="fas fa-user icon"></i>
                  <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required>
              </div>
          </div>
          
          <div class="form-group">
              <label for="email">Email</label>
              <div class="input-group">
                  <i class="fas fa-envelope icon"></i>
                  <input type="email" id="email" name="email" placeholder="Enter your email" required>
              </div>
          </div>
          
          <div class="form-row">
              <div class="form-group">
                  <label for="password">Password</label>
                  <div class="input-group">
                      <i class="fas fa-lock icon"></i>
                      <input type="password" id="password" name="password" placeholder="Enter your password" required>
                      <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                  </div>
              </div>
              
              <div class="form-group">
                  <label for="confirm_password">Confirm Password</label>
                  <div class="input-group">
                      <i class="fas fa-lock icon"></i>
                      <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                      <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                  </div>
              </div>
          </div>
          
          <div class="form-group">
              <label for="phone">Phone Number</label>
              <div class="input-group">
                  <i class="fas fa-phone icon"></i>
                  <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
              </div>
          </div>
          
          <div class="form-group">
              <label for="address">Address</label>
              <textarea id="address" name="address" placeholder="Enter your address" required></textarea>
          </div>
          
          <button type="submit">Sign Up</button>
      </form>
      
      <div class="signup-footer">
          <p>Already have an account? <a href="login.php">Login</a></p>
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

