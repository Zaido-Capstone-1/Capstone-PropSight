<?php
/**
 * Call this before session_start() everywhere.
 * Sets secure cookie params: HttpOnly, SameSite=Lax, Secure (HTTPS only).
 * Enhanced for production security.
 */
if (session_status() === PHP_SESSION_NONE) {
    // Detect if we're running on HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

    // Get domain for cookie
    $domain = '';
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        // Remove port from host if present
        $host = preg_replace('/:\d+$/', '', $host);

        // For production, set domain to the actual domain
        // For localhost/development, leave empty
        if (!preg_match('/^(localhost|127\.0\.0\.1|\.local)$/', $host)) {
            $domain = $host;
        }
    }

    // Enhanced session cookie parameters for production security
    session_set_cookie_params([
        'lifetime' => 0,           // Session cookie (expires when browser closes)
        'path' => '/',         // Available site-wide
        'domain' => $domain,     // Domain-specific or empty for localhost
        'secure' => $isHttps,    // HTTPS only in production
        'httponly' => true,        // Prevent JavaScript access
        'samesite' => 'Lax',       // CSRF protection while allowing navigation
    ]);

    // Additional session security settings
    ini_set('session.use_strict_mode', '1');        // Prevent session fixation
    ini_set('session.use_only_cookies', '1');       // Disable session IDs in URLs
    ini_set('session.cookie_httponly', '1');        // HttpOnly cookies
    ini_set('session.cookie_secure', $isHttps ? '1' : '0'); // Secure cookies in HTTPS
    ini_set('session.gc_maxlifetime', '14400');     // 4 hours session lifetime
    ini_set('session.gc_probability', '1');         // Run garbage collection
    ini_set('session.gc_divisor', '100');           // 1% chance to run GC

    // Regenerate session ID periodically for security
    if (isset($_SESSION) && !isset($_SESSION['_last_regeneration'])) {
        $_SESSION['_last_regeneration'] = time();
    } elseif (isset($_SESSION['_last_regeneration'])) {
        // Regenerate session ID every 30 minutes
        if (time() - $_SESSION['_last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_last_regeneration'] = time();
        }
    }
}
?>