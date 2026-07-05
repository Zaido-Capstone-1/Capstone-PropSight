<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('DB_SERVER', $_ENV['DB_SERVER']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);
define('DB_NAME', $_ENV['DB_NAME']);

define('MAIL_HOST', $_ENV['MAIL_HOST']);
define('MAIL_PORT', $_ENV['MAIL_PORT']);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME']);
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD']);
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME']);
define('MAIL_FROM_EMAIL', (!empty($_ENV['MAIL_FROM_EMAIL'])) ? $_ENV['MAIL_FROM_EMAIL'] : $_ENV['MAIL_USERNAME']);
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION']);

// ── Social Login (Google / Facebook) ────────────────────────────────────
// Redirect URIs are auto-detected per-request (see includes/oauth_helpers.php),
// so only the Client ID / Secret need to live in .env.
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

define('FACEBOOK_APP_ID', $_ENV['FACEBOOK_APP_ID'] ?? '');
define('FACEBOOK_APP_SECRET', $_ENV['FACEBOOK_APP_SECRET'] ?? '');
?>