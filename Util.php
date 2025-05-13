<?php
/**
 * Utility class for Chapa payment integration
 */
class Util {
    /**
     * Generate a unique transaction reference token
     * 
     * @param string $prefix Optional prefix for the token
     * @return string Unique transaction reference
     */
    public static function generateToken($prefix = 'MB') {
        $timestamp = time();
        $random = bin2hex(random_bytes(5));
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * Sanitize input data
     * 
     * @param string $data Input data
     * @return string Sanitized data
     */
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
    
    /**
     * Validate phone number (10 digits)
     * 
     * @param string $phone Phone number
     * @return bool True if valid
     */
    public static function validatePhone($phone) {
        return preg_match("/^[0-9]{10}$/", $phone);
    }
    
    /**
     * Format amount with 2 decimal places
     * 
     * @param float $amount Amount
     * @return string Formatted amount
     */
    public static function formatAmount($amount) {
        return number_format((float)$amount, 2, '.', '');
    }
}
