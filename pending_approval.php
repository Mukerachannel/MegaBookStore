<?php
session_start();
require_once 'db.php';

// Check if user is logged in and role is pending
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pending') {
  header("Location: login.php");
  exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

// Handle logout
if (isset($_GET['logout'])) {
  session_destroy();
  header("Location: login.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Pending Approval - Mega Book Store</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="index.css">
  <style>
      .pending-container {
          max-width: 600px;
          margin: 100px auto;
          padding: 30px;
          background-color: white;
          border-radius: 10px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
          text-align: center;
      }
      
      .pending-icon {
          font-size: 80px;
          color: #f39c12;
          margin-bottom: 20px;
      }
      
      .pending-title {
          font-size: 24px;
          color: #333;
          margin-bottom: 15px;
      }
      
      .pending-message {
          color: #666;
          margin-bottom: 30px;
          line-height: 1.6;
      }
      
      .pending-actions {
          margin-top: 30px;
      }
      
      .btn {
          display: inline-block;
          padding: 10px 20px;
          background-color: #3498db;
          color: white;
          border-radius: 5px;
          text-decoration: none;
          font-weight: 500;
          transition: background-color 0.3s;
      }
      
      .btn:hover {
          background-color: #2980b9;
      }
      
      .btn-secondary {
          background-color: #6c757d;
      }
      
      .btn-secondary:hover {
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
          <ul class="nav-menu">
              <li><a href="index.php" class="nav-link">Home</a></li>
              <li><a href="pending_approval.php?logout=true" class="nav-link login-btn">Logout</a></li>
          </ul>
      </div>
  </nav>

  <!-- Pending Approval Content -->
  <div class="pending-container">
      <div class="pending-icon">
          <i class="fas fa-clock"></i>
      </div>
      <h1 class="pending-title">Account Pending Approval</h1>
      <p class="pending-message">
          Thank you for registering with Mega Book Store, <?php echo htmlspecialchars($user['fullname']); ?>!<br>
          Your account is currently pending approval by our administrators.<br>
          You will receive an email notification once your account has been approved.
      </p>
      <p class="pending-message">
          If you have any questions or need assistance, please contact our support team at:<br>
          <strong>info@megabooks.com</strong> or call <strong>+251921195638</strong>
      </p>
      <div class="pending-actions">
          <a href="index.php" class="btn">Return to Homepage</a>
          <a href="pending_approval.php?logout=true" class="btn btn-secondary">Logout</a>
      </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
      <div class="container">
          <div class="footer-content">
              <div class="footer-section about">
                  <h2>Mega Books</h2>
                  <p>Your trusted partner for books sales and rental services.</p>
                  <div class="contact">
                      <p><i class="fas fa-map-marker-alt"></i> Sidama, Hawassa </p>
                      <p><i class="fas fa-phone"></i> +251921195638</p>
                      <p><i class="fas fa-envelope"></i> info@megabooks.com</p>
                  </div>
                  <div class="socials">
                      <a href="https://facebook.com"><i class="fab fa-facebook"></i></a>
                      <a href="https://x.com"><i class="fab fa-twitter"></i></a>
                      <a href="https://instagram.com"><i class="fab fa-instagram"></i></a>
                      <a href="https://linkedin.com"><i class="fab fa-linkedin"></i></a>
                  </div>
              </div>
          </div>
          <div class="footer-bottom">
              <p>&copy; 2025 Mega Books. All Rights Reserved.</p>
          </div>
      </div>
  </footer>
</body>
</html>

