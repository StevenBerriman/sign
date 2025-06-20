<?php
/**
 * Kitchen & Bathroom Digital Contracts - Configuration
 * Generated: 2025-06-04T19:20:08.344Z
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbbn8ltv7kpava');
define('DB_USER', 'us4azsgnm3aue');
define('DB_PASS', 'Fit-plan123');
define('DB_PREFIX', 'kb_');

// Application Settings
define('APP_NAME', 'Kitchen and Bathroom (NE) Ltd Digital Contracts');
define('APP_URL', 'https://kitchen-bathroom.co.uk/sign');
define('APP_EMAIL', 'info@kitchen-bathroom.co.uk');
define('APP_VERSION', '1.0.0');

// Security Keys
define('JWT_SECRET', '428w5y2b0ob0qnk6sho3q9');
define('ENCRYPTION_KEY', '4obc2p0yz098dr9pnatk3x');
define('CSRF_TOKEN_SECRET', 'ogox9xzl388zjm9mes6vo');

// Email Configuration
define('SMTP_HOST', 'mail.kitchen-bathroom.co.uk');
define('SMTP_PORT', 465);
define('SMTP_USER', 'info@kitchen-bathroom.co.uk');
define('SMTP_PASS', 'Fit-plan123');
define('SMTP_SECURE', 'ssl');

// File Upload Settings
define('UPLOAD_MAX_SIZE', 10485760); // 10MB
define('UPLOAD_PATH', dirname(__FILE__) . '/../uploads/');
define('TEMP_PATH', dirname(__FILE__) . '/../temp/');
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'kb_contracts_session');

// Timezone
date_default_timezone_set('Europe/London');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../logs/error.log');

// Database connection function
function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}

// Autoloader for classes
spl_autoload_register(function ($class) {
    $file = dirname(__FILE__) . '/../classes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});