<?php
// Database connection
require_once 'db.php';

// Create payments table for Chapa payment integration
$sql = "CREATE TABLE IF NOT EXISTS payments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    order_id INT(11) NOT NULL,
    tx_ref VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'ETB',
    payment_method VARCHAR(50),
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    checkout_url VARCHAR(255),
    reference VARCHAR(100),
    chapa_reference VARCHAR(100),
    payment_date TIMESTAMP NULL,
    response_data TEXT,
    verification_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating payments table: " . $conn->error);
} else {
    echo "Payments table created or already exists.<br>";
}

// Add Chapa payment keys to system settings if they don't exist
$chapa_public_key = "CHAPUBK_TEST-RfWeQGOkLxeIblZHcS7gi7l5NuqK5S7g";
$chapa_secret_key = "CHASECK_TEST-RfWeQGOkLxeIblZHcS7gi7l5NuqK5S7g"; // Replace with your actual secret key

// Check if public key exists
$check_public_key = "SELECT id FROM system_settings WHERE setting_key = 'chapa_public_key'";
$result = $conn->query($check_public_key);

if ($result->num_rows == 0) {
    // Add public key
    $sql = "INSERT INTO system_settings (setting_key, setting_value, description) VALUES ('chapa_public_key', ?, 'Chapa payment public key')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $chapa_public_key);
    
    if ($stmt->execute()) {
        echo "Added Chapa public key<br>";
    } else {
        echo "Error adding Chapa public key: " . $stmt->error . "<br>";
    }
} else {
    // Update public key
    $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'chapa_public_key'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $chapa_public_key);
    
    if ($stmt->execute()) {
        echo "Updated Chapa public key<br>";
    } else {
        echo "Error updating Chapa public key: " . $stmt->error . "<br>";
    }
}

// Check if secret key exists
$check_secret_key = "SELECT id FROM system_settings WHERE setting_key = 'chapa_secret_key'";
$result = $conn->query($check_secret_key);

if ($result->num_rows == 0) {
    // Add secret key
    $sql = "INSERT INTO system_settings (setting_key, setting_value, description) VALUES ('chapa_secret_key', ?, 'Chapa payment secret key')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $chapa_secret_key);
    
    if ($stmt->execute()) {
        echo "Added Chapa secret key<br>";
    } else {
        echo "Error adding Chapa secret key: " . $stmt->error . "<br>";
    }
} else {
    // Update secret key
    $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'chapa_secret_key'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $chapa_secret_key);
    
    if ($stmt->execute()) {
        echo "Updated Chapa secret key<br>";
    } else {
        echo "Error updating Chapa secret key: " . $stmt->error . "<br>";
    }
}

echo "Chapa payment integration setup completed successfully!";
$conn->close();
?>
