<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

// safeLoad() (not load()) so this doesn't fatal when there's no .env file —
// on Railway, config comes from real process environment variables instead.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Reads a var regardless of whether it arrived via .env ($_ENV), Apache/FPM
// ($_SERVER), or a plain process env var (getenv) — Railway sets the latter.
function env(string $key, $default = null)
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    $val = getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
}

define('DB_SERVER', env('DB_SERVER'));
define('DB_USERNAME', env('DB_USERNAME'));
define('DB_PASSWORD', env('DB_PASSWORD'));
define('DB_NAME', env('DB_NAME'));
define('DB_PORT', (int) env('DB_PORT', 3306));

define('MAIL_HOST', env('MAIL_HOST'));
define('MAIL_PORT', env('MAIL_PORT'));
define('MAIL_USERNAME', env('MAIL_USERNAME'));
define('MAIL_PASSWORD', env('MAIL_PASSWORD'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME'));
define('MAIL_FROM_EMAIL', env('MAIL_FROM_EMAIL') ?: env('MAIL_USERNAME'));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION'));

// SSO
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', ''));

define('FACEBOOK_APP_ID', env('FACEBOOK_APP_ID', ''));
define('FACEBOOK_APP_SECRET', env('FACEBOOK_APP_SECRET', ''));
?>