<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Global Configuration File
 * Location: /config/config.php
 */

// Prevent direct script execution if accessed via CLI without proper environment
if (defined('APP_CONFIG_LOADED')) {
    return;
}
define('APP_CONFIG_LOADED', true);

// -------------------------------------------------------------
// 1. ENVIRONMENT & ERROR REPORTING
// -------------------------------------------------------------
$appEnv = getenv('APP_ENV');
$appEnv = ($appEnv === false || $appEnv === '') ? 'development' : $appEnv;
if (!in_array($appEnv, ['development', 'production'], true)) {
    error_log('Invalid APP_ENV configuration.');
    http_response_code(500);
    exit('Application configuration error.');
}
define('APP_ENV', $appEnv);

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
    // Secure session cookies for production
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
}

// -------------------------------------------------------------
// 2. TIMEZONE & LOCALE (India Standard Time by default)
// -------------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');

// -------------------------------------------------------------
// 3. DATABASE CONFIGURATION (Supports Env Vars)
// -------------------------------------------------------------
$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');
$dbPort = getenv('DB_PORT');
define('DB_HOST', ($dbHost !== false && $dbHost !== '') ? $dbHost : (APP_ENV === 'development' ? '127.0.0.1' : ''));
define('DB_NAME', ($dbName !== false && $dbName !== '') ? $dbName : (APP_ENV === 'development' ? 'women_marketplace_db' : ''));
define('DB_USER', ($dbUser !== false && $dbUser !== '') ? $dbUser : (APP_ENV === 'development' ? 'root' : ''));
define('DB_PASSWORD', ($dbPassword !== false) ? $dbPassword : '');
define('DB_PORT', ($dbPort !== false && $dbPort !== '') ? $dbPort : '3306');
define('DB_CHARSET', 'utf8mb4');

// -------------------------------------------------------------
// 4. URL & PATH SETTINGS
// -------------------------------------------------------------
// Automatically detect the web root URL whether accessed via
// http://localhost/e-commerce3 or custom VirtualHost
$appUrl = getenv('APP_URL');
if (APP_ENV === 'production') {
    $urlParts = is_string($appUrl) ? parse_url($appUrl) : false;
    if (
        $urlParts === false ||
        filter_var($appUrl, FILTER_VALIDATE_URL) === false ||
        strtolower($urlParts['scheme'] ?? '') !== 'https' ||
        empty($urlParts['host']) ||
        isset($urlParts['user']) ||
        isset($urlParts['pass']) ||
        isset($urlParts['query']) ||
        isset($urlParts['fragment'])
    ) {
        error_log('Production APP_URL must be a trusted HTTPS URL without credentials, query, or fragment.');
        http_response_code(500);
        exit('Application configuration error.');
    }
    define('BASE_URL', rtrim($appUrl, '/'));
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim($scriptDir, '/');
    $basePath = preg_replace('/(\/(manager|provider|user|includes|config|api).*$)/', '', $basePath);
    define('BASE_URL', $protocol . $host . $basePath);
}
define('ROOT_PATH', realpath(__DIR__ . '/..'));

// -------------------------------------------------------------
// 5. FILE UPLOAD CONFIGURATION
// -------------------------------------------------------------
define('UPLOAD_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('PRODUCT_UPLOAD_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'products');
define('PROVIDER_UPLOAD_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'providers');

define('UPLOAD_URL', BASE_URL . '/uploads');
define('PRODUCT_UPLOAD_URL', UPLOAD_URL . '/products');
define('PROVIDER_UPLOAD_URL', UPLOAD_URL . '/providers');

define('MAX_FILE_SIZE_BYTES', 3 * 1024 * 1024); // 3 MB max per image
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_IMAGE_MIMES', [
    'image/jpeg',
    'image/png',
    'image/webp'
]);

// -------------------------------------------------------------
// 6. PAYMENT GATEWAY SETTINGS (Modular Adapter)
// -------------------------------------------------------------
// Set to 'mock' for local offline testing/presentations, or 'cashfree' for live/sandbox
define('ACTIVE_PAYMENT_GATEWAY', getenv('ACTIVE_PAYMENT_GATEWAY') ?: (APP_ENV === 'development' ? 'mock' : ''));

// Cashfree API Configuration (Used when ACTIVE_PAYMENT_GATEWAY is 'cashfree')
define('CASHFREE_APP_ID', getenv('CASHFREE_APP_ID') ?: '');
define('CASHFREE_SECRET_KEY', getenv('CASHFREE_SECRET_KEY') ?: '');
define('CASHFREE_ENV', getenv('CASHFREE_ENV') ?: 'TEST'); // 'TEST' for Sandbox or 'PROD' for Live
define('CASHFREE_API_VERSION', '2023-08-01');

// -------------------------------------------------------------
// 7. PLATFORM FINANCIAL DEFAULTS
// -------------------------------------------------------------
define('DEFAULT_PLATFORM_COMMISSION_PERCENT', 10.00); // 10% Platform fee
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

// -------------------------------------------------------------
// 8. SECURITY & SESSION SETTINGS
// -------------------------------------------------------------
define('SESSION_LIFETIME', 86400); // 24 hours
define('PASSWORD_MIN_LENGTH', 6);

