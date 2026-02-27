<?php
// garage_management_system/config/config.php
session_start();
ob_start();

// Base URL configuration
define('BASE_URL', 'https://garagemaster.sericsoft.com/');
define('SITE_NAME', 'Garage Master');

// Security settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes in seconds
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CSRF_TOKEN_LIFE', 1800); // 30 minutes

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Include other config files
require_once 'database.php';
require_once 'branding.php';

// Initialize database
$db = new Database();

// CSRF Token Generation
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || time() > $_SESSION['csrf_token_expiry']) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expiry'] = time() + CSRF_TOKEN_LIFE;
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Validation
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_expiry'])) {
        return false;
    }
    
    if (time() > $_SESSION['csrf_token_expiry']) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_expiry']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>