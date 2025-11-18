<?php
/**
 * SaaS PHP Application Configuration
 * Copy this file to config.local.php and update with your settings
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

// Environment
define('APP_ENV', 'development'); // 'production' or 'development'
define('APP_DEBUG', APP_ENV === 'development');

// Application
define('APP_NAME', 'SaaS Starter');
define('APP_URL', 'http://localhost'); // Your domain

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'saas_app');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Authentication
define('AUTH_SECRET', 'your-secret-key-min-32-characters-long'); // Change this!
define('JWT_EXPIRY', 86400); // 24 hours in seconds
define('SALT_ROUNDS', 10);

// Stripe Configuration (Optional)
define('STRIPE_SECRET_KEY', '');
define('STRIPE_PUBLISHABLE_KEY', '');
define('STRIPE_WEBHOOK_SECRET', '');

// Session Configuration
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'saas_session');

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

// Include local config overrides if exists
if (file_exists(__DIR__ . '/config.local.php')) {
    include __DIR__ . '/config.local.php';
}
