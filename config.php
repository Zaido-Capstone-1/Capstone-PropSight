<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Only load .env if it exists (for local development)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Helper to read from $_ENV or getenv()
function envValue($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// define('DB_SERVER', $_ENV['DB_SERVER']);
// define('DB_USERNAME', $_ENV['DB_USERNAME']);
// define('DB_PASSWORD', $_ENV['DB_PASSWORD']);
// define('DB_NAME', $_ENV['DB_NAME']);

define('DB_SERVER', $_ENV['DB_SERVER']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_PORT', (int) ($_ENV['DB_PORT'] ?? 3306));

define('MAIL_HOST', $_ENV['MAIL_HOST']);
define('MAIL_PORT', $_ENV['MAIL_PORT']);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME']);
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD']);
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME']);
define('MAIL_FROM_EMAIL', (!empty($_ENV['MAIL_FROM_EMAIL'])) ? $_ENV['MAIL_FROM_EMAIL'] : $_ENV['MAIL_USERNAME']);
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION']);

// SSO
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

define('FACEBOOK_APP_ID', $_ENV['FACEBOOK_APP_ID'] ?? '');
define('FACEBOOK_APP_SECRET', $_ENV['FACEBOOK_APP_SECRET'] ?? '');
?>