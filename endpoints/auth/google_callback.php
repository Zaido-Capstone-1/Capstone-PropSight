<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_params.php';
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/oauth_helpers.php';

function google_fail(string $message)
{
    header('Location: ../../index.php?oauth_error=' . urlencode($message));
    exit;
}

if (!empty($_GET['error'])) {
    // e.g. user clicked "Cancel" on Google's consent screen.
    google_fail('Google sign-in was cancelled.');
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '' || !oauth_verify_state('oauth_state_google', $state)) {
    google_fail('Google sign-in request could not be verified. Please try again.');
}

try {
    $redirectUri = oauth_build_redirect_uri('google_callback.php');

    // 1) Exchange the authorization code for an access token.
    $tokenRes = oauth_http_request(
        'POST',
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ])
    );

    $accessToken = $tokenRes['body']['access_token'] ?? null;
    if ($tokenRes['status'] !== 200 || !$accessToken) {
        error_log('Google token exchange failed: ' . json_encode($tokenRes));
        google_fail('Could not sign in with Google. Please try again.');
    }

    // 2) Fetch the user's profile.
    $profileRes = oauth_http_request(
        'GET',
        'https://www.googleapis.com/oauth2/v3/userinfo',
        ['Authorization: Bearer ' . $accessToken]
    );

    $profile = $profileRes['body'];
    $googleId = $profile['sub'] ?? null;
    $email = $profile['email'] ?? null;
    $emailVerified = $profile['email_verified'] ?? false;

    if (!$googleId || !$email || !$emailVerified) {
        google_fail('Your Google account must have a verified email to sign in.');
    }

    $firstName = $profile['given_name'] ?? '';
    $lastName = $profile['family_name'] ?? '';
    $avatarUrl = $profile['picture'] ?? null;

    $user = oauth_find_or_create_user($conn, 'google', $googleId, $email, $firstName, $lastName, $avatarUrl);
    if (!$user) {
        google_fail('Something went wrong creating your account. Please try again.');
    }

    if (!empty($user['is_blacklisted'])) {
        google_fail('This account has been restricted. Please contact support.');
    }
    if (!empty($user['is_locked'])) {
        $lockExpired = !empty($user['locked_until']) && strtotime((string) $user['locked_until']) <= time();
        if (!$lockExpired) {
            google_fail('This account is temporarily locked. Please try again later.');
        }
    }

    oauth_log_in_user($user);

    $role = $_SESSION['role'] ?? 'user';
    header('Location: ../../' . ($role === 'admin' ? 'pages/admin/index.php' : 'pages/user/user-dashboard.php'));
    exit;
} catch (Throwable $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    google_fail('Something went wrong signing in with Google. Please try again.');
}