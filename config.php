<?php
/**
 * Application Configuration
 * Loads environment variables and defines configuration constants
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        die("ERROR: .env file not found. Please copy .env.example to .env and configure your settings.");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Don't overwrite existing environment variables
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/.env');

// Validate required configuration
$required = ['DB_HOST', 'DB_USER', 'DB_NAME', 'SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD'];
foreach ($required as $key) {
    if (empty($_ENV[$key])) {
        die("ERROR: Required environment variable '$key' is not set. Check your .env file.");
    }
}

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);

// Email Configuration
define('SMTP_HOST', $_ENV['SMTP_HOST']);
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 465);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME']);
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD']);
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME']);
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Construct');

// Session Security Configuration
// Only set session ini settings if no session is active yet
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');

    // Enable secure cookies in production
    if (isset($_ENV['SESSION_SECURE']) && $_ENV['SESSION_SECURE'] === 'true') {
        ini_set('session.cookie_secure', 1);
    }

    // Session lifetime
    $lifetime = isset($_ENV['SESSION_LIFETIME']) ? (int)$_ENV['SESSION_LIFETIME'] : 3600;
    ini_set('session.gc_maxlifetime', $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => ($_ENV['SESSION_SECURE'] ?? 'false') === 'true',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Error Display (off by default — set APP_DEBUG=true in .env for development)
$debug = isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('log_errors', '1');

// Session Name (centralized)
define('SESSION_NAME', 'CONSTRUCT_SESSION');
define('CONSTRUCT_SESSION', 'CONSTRUCT_SESSION');

// Timezone
date_default_timezone_set('Asia/Kolkata');
