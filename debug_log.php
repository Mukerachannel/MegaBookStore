<?php
// Simple debugging script to check if PHP is working correctly
// and to verify database connection

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP Debug Information</h1>";

// PHP Version
echo "<h2>PHP Version</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Extensions
echo "<h2>PHP Extensions</h2>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";

// Check if cURL is installed
echo "<h2>cURL Information</h2>";
if (function_exists('curl_version')) {
    $curl_info = curl_version();
    echo "<p>cURL Version: " . $curl_info['version'] . "</p>";
    echo "<p>SSL Version: " . $curl_info['ssl_version'] . "</p>";
} else {
    echo "<p>cURL is not installed</p>";
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    require_once 'db.php';
    
    if (isset($conn) && $conn instanceof mysqli) {
        echo "<p>Database connection successful</p>";
        
        // Check if payments table exists
        $result = $conn->query("SHOW TABLES LIKE 'payments'");
        if ($result->num_rows > 0) {
            echo "<p>Payments table exists</p>";
            
            // Check payments table structure
            $result = $conn->query("DESCRIBE payments");
            echo "<p>Payments table structure:</p>";
            echo "<pre>";
            while ($row = $result->fetch_assoc()) {
                print_r($row);
            }
            echo "</pre>";
        } else {
            echo "<p>Payments table does not exist</p>";
        }
    } else {
        echo "<p>Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Test Chapa API connection
echo "<h2>Chapa API Connection Test</h2>";
try {
    require_once 'chapa_helpers.php';
    
    echo "<p>CHAPA_API_URL: " . CHAPA_API_URL . "</p>";
    echo "<p>CHAPA_PUBLIC_KEY: " . substr(CHAPA_PUBLIC_KEY, 0, 10) . "...</p>";
    echo "<p>CHAPA_SECRET_KEY: " . substr(CHAPA_SECRET_KEY, 0, 10) . "...</p>";
    
    // Test API connection
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => CHAPA_API_URL . '/transaction/initialize',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'amount' => '1',
            'currency' => 'ETB',
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'tx_ref' => 'test-' . time(),
            'callback_url' => 'http://example.com/callback',
            'return_url' => 'http://example.com/return'
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . CHAPA_SECRET_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    echo "<p>HTTP Status: " . $http_status . "</p>";
    
    if ($err) {
        echo "<p>cURL Error: " . $err . "</p>";
    } else {
        echo "<p>Response:</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
