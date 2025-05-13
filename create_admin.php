<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'megabooks');

// Application configuration
define('SITE_NAME', 'Mega Book Store');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/megabooks');
define('ADMIN_EMAIL', 'admin@gmail.com');

// File upload configuration
define('UPLOAD_DIR', 'uploads/');
define('BOOK_IMAGES_DIR', 'uploads/books/');
define('MAX_FILE_SIZE', 5000000); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Session configuration
define('SESSION_NAME', 'megabooks_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// Payment gateway configuration
// Chapa configuration
define('CHAPA_ENABLED', true);
define('CHAPA_SECRET_KEY', 'YOUR_CHAPA_SECRET_KEY'); // Replace with your actual Chapa secret key
define('CHAPA_PUBLIC_KEY', 'YOUR_CHAPA_PUBLIC_KEY'); // Replace with your actual Chapa public key
define('CHAPA_API_URL', 'https://api.chapa.co/v1');
define('CHAPA_VERIFY_URL', 'https://api.chapa.co/v1/transaction/verify/');
define('CHAPA_CALLBACK_URL', SITE_URL . '/chapa_callback.php');
define('CHAPA_RETURN_URL', SITE_URL . '/payment_success.php');

// Telebirr configuration
define('TELEBIRR_ENABLED', true);
define('TELEBIRR_APP_ID', 'YOUR_TELEBIRR_APP_ID'); // Replace with your actual Telebirr app ID
define('TELEBIRR_APP_KEY', 'YOUR_TELEBIRR_APP_KEY'); // Replace with your actual Telebirr app key
define('TELEBIRR_PUBLIC_KEY', 'YOUR_TELEBIRR_PUBLIC_KEY'); // Replace with your actual Telebirr public key
define('TELEBIRR_SHORT_CODE', 'YOUR_TELEBIRR_SHORT_CODE'); // Replace with your actual Telebirr short code
define('TELEBIRR_API_URL', 'https://api.telebirr.com/api/checkout/');
define('TELEBIRR_NOTIFY_URL', SITE_URL . '/telebirr_callback.php');
define('TELEBIRR_RETURN_URL', SITE_URL . '/payment_success.php');
define('TELEBIRR_TIMEOUT', 30); // seconds

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Time zone
date_default_timezone_set('Africa/Addis_Ababa');

// Connect to database
try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
?>
