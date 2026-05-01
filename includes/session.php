<?php
require_once __DIR__ . '/../includes/session_params.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../../index.php");
    exit;
}

if (!function_exists('user_is_verified')) {
    function user_is_verified(): bool
    {
        return (($_SESSION['verification_status'] ?? '') === 'Verified');
    }
}

if (!function_exists('require_verified_user_action')) {
    function require_verified_user_action(bool $jsonResponse = true): void
    {
        if (($_SESSION['role'] ?? '') !== 'user') {
            return;
        }
        if (user_is_verified()) {
            return;
        }

        if ($jsonResponse) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(403);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Please verify your Gmail first before performing this action.',
            ]);
            exit;
        }

        $_SESSION['toast_error'] = 'Please verify your Gmail first before performing this action.';
        header('Location: ../../pages/user/profile.php');
        exit;
    }
}

if (!function_exists('require_csrf_token')) {
    function require_csrf_token(bool $jsonResponse = true): void
    {
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        $requestToken = (string)($_POST['csrf_token'] ?? '');

        if ($sessionToken !== '' && $requestToken !== '' && hash_equals($sessionToken, $requestToken)) {
            return;
        }

        if ($jsonResponse) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(403);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token.',
            ]);
            exit;
        }

        $_SESSION['toast_error'] = 'Invalid CSRF token.';
        header('Location: ../../index.php');
        exit;
    }
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>