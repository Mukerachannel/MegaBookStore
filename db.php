<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "megabooks";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Set charset to ensure proper handling of special characters
$conn->set_charset("utf8mb4");

// Function to sanitize input data
function sanitize_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

// Function to check if user exists
function user_exists($conn, $email) {
  $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->num_rows > 0;
}

// Function to check if phone exists
function phone_exists($conn, $phone) {
  $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
  $stmt->bind_param("s", $phone);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->num_rows > 0;
}

// Function to check if password was previously used by this user
function is_password_unique($conn, $user_id, $password) {
  $stmt = $conn->prepare("SELECT id FROM password_history WHERE user_id = ? AND password_hash = ?");
  $stmt->bind_param("is", $user_id, $password);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->num_rows == 0;
}

// Function to add password to history
function add_password_to_history($conn, $user_id, $password_hash) {
  $stmt = $conn->prepare("INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)");
  $stmt->bind_param("is", $user_id, $password_hash);
  return $stmt->execute();
}

// Function to validate phone number (10 digits)
function validate_phone($phone) {
  return preg_match("/^[0-9]{10}$/", $phone);
}

// Function to get user by ID
function get_user_by_id($conn, $user_id) {
  $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows > 0) {
      return $result->fetch_assoc();
  }
  
  return null;
}

// Function to get user by email
function get_user_by_email($conn, $email) {
  $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows > 0) {
      return $result->fetch_assoc();
  }
  
  return null;
}

// Function to save feedback from contact form
function save_feedback($conn, $user_id, $name, $email, $message) {
  $stmt = $conn->prepare("INSERT INTO feedback (user_id, name, email, message) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("isss", $user_id, $name, $email, $message);
  return $stmt->execute();
}

// Function to get system setting
function get_setting($conn, $key, $default = "") {
  $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
  $stmt->bind_param("s", $key);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      return $row["setting_value"];
  }
  
  return $default;
}

// Function to update system setting
function update_setting($conn, $key, $value) {
  $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
  $stmt->bind_param("ss", $value, $key);
  return $stmt->execute();
}

// Function to log payment activity
function log_payment_activity($payment_method, $order_id, $status, $data) {
  $log_dir = "logs/payments";
  if (!file_exists($log_dir)) {
      mkdir($log_dir, 0777, true);
  }
  
  $log_file = $log_dir . "/" . date("Y-m-d") . ".log";
  $timestamp = date("Y-m-d H:i:s");
  $log_data = "[{$timestamp}] [{$payment_method}] [Order: {$order_id}] [Status: {$status}] " . json_encode($data) . PHP_EOL;
  
  file_put_contents($log_file, $log_data, FILE_APPEND);
}

// Function to create a payment transaction record
function create_payment_transaction($conn, $order_id, $transaction_id, $payment_method, $amount, $status = "pending", $payment_data = null) {
  $stmt = $conn->prepare("INSERT INTO payment_transactions (order_id, transaction_id, payment_method, amount, status, payment_data) VALUES (?, ?, ?, ?, ?, ?)");
  $payment_data_json = $payment_data ? json_encode($payment_data) : null;
  $stmt->bind_param("issdss", $order_id, $transaction_id, $payment_method, $amount, $status, $payment_data_json);
  return $stmt->execute() ? $conn->insert_id : false;
}

// Function to update payment transaction status
function update_payment_transaction($conn, $transaction_id, $status, $callback_response = null) {
  $stmt = $conn->prepare("UPDATE payment_transactions SET status = ?, callback_response = ?, updated_at = CURRENT_TIMESTAMP WHERE transaction_id = ?");
  $stmt->bind_param("sss", $status, $callback_response, $transaction_id);
  return $stmt->execute();
}

// Function to create a Telebirr transaction
function create_telebirr_transaction($conn, $order_id, $mobile_number, $amount, $payment_note = "") {
  $stmt = $conn->prepare("INSERT INTO telebirr_transactions (order_id, mobile_number, amount, payment_note) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("isds", $order_id, $mobile_number, $amount, $payment_note);
  return $stmt->execute() ? $conn->insert_id : false;
}

// Function to verify Telebirr payment
function verify_telebirr_payment($conn, $transaction_id, $verification_code, $user_id) {
  $stmt = $conn->prepare("UPDATE telebirr_transactions SET status = 'completed', verification_code = ?, verified_by = ?, verified_at = NOW(), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
  $stmt->bind_param("sii", $verification_code, $user_id, $transaction_id);
  
  if ($stmt->execute()) {
      // Get the order ID for this transaction
      $stmt = $conn->prepare("SELECT order_id FROM telebirr_transactions WHERE id = ?");
      $stmt->bind_param("i", $transaction_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
          $row = $result->fetch_assoc();
          $order_id = $row["order_id"];
          
          // Update the order payment status
          $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
          $stmt->bind_param("i", $order_id);
          return $stmt->execute();
      }
  }
  
  return false;
}

// Function to create a Chapa transaction
function create_chapa_transaction($conn, $order_id, $tx_ref, $amount, $checkout_url = null) {
  $stmt = $conn->prepare("INSERT INTO chapa_transactions (order_id, tx_ref, amount, checkout_url) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("isds", $order_id, $tx_ref, $amount, $checkout_url);
  return $stmt->execute() ? $conn->insert_id : false;
}

// Function to update Chapa transaction status
function update_chapa_transaction($conn, $tx_ref, $status, $callback_data = null) {
  $callback_data_json = $callback_data ? json_encode($callback_data) : null;
  $stmt = $conn->prepare("UPDATE chapa_transactions SET status = ?, callback_data = ?, updated_at = CURRENT_TIMESTAMP WHERE tx_ref = ?");
  $stmt->bind_param("sss", $status, $callback_data_json, $tx_ref);
  
  if ($stmt->execute()) {
      // Get the order ID for this transaction
      $stmt = $conn->prepare("SELECT order_id FROM chapa_transactions WHERE tx_ref = ?");
      $stmt->bind_param("s", $tx_ref);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
          $row = $result->fetch_assoc();
          $order_id = $row["order_id"];
          
          // Update the order payment status based on Chapa status
          $payment_status = ($status == "success") ? "paid" : "failed";
          $order_status = ($status == "success") ? "processing" : "pending";
          
          $stmt = $conn->prepare("UPDATE orders SET payment_status = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
          $stmt->bind_param("ssi", $payment_status, $order_status, $order_id);
          return $stmt->execute();
      }
  }
  
  return false;
}

// Function to generate a unique transaction reference
function generate_transaction_reference($prefix = "TX") {
  return $prefix . time() . rand(1000, 9999);
}

// Function to sanitize JSON data
function sanitize_json($json_data) {
  if (empty($json_data)) {
      return null;
  }
  
  // Decode JSON data
  $data = json_decode($json_data, true);
  
  // If JSON is invalid, return null
  if (json_last_error() !== JSON_ERROR_NONE) {
      return null;
  }
  
  // Sanitize each field recursively
  $sanitized_data = array_map_recursive("htmlspecialchars", $data);
  
  // Return sanitized JSON
  return json_encode($sanitized_data);
}

// Helper function to apply a callback to all elements in an array recursively
function array_map_recursive($callback, $array) {
  $func = function ($item) use (&$func, &$callback) {
      return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
  };
  
  return array_map($func, $array);
}

// Function to validate JSON
function is_valid_json($string) {
  if (!is_string($string) || empty($string)) {
      return false;
  }
  
  json_decode($string);
  return json_last_error() === JSON_ERROR_NONE;
}
?>