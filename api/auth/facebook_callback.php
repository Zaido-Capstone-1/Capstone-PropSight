<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_params.php';
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/oauth_helpers.php';

function facebook_fail(string $message): never
{
    header('Location: ../../index.php?oauth_error=' . urlencode($message));
    exit;
}

if (!empty($_GET['error'])) {
    facebook_fail('Facebook sign-in was cancelled.');
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '' || !oauth_verify_state('oauth_state_facebook', $state)) {
    facebook_fail('Facebook sign-in request could not be verified. Please try again.');
}

try {
    $redirectUri = oauth_build_redirect_uri('facebook_callback.php');

    // 1) Exchange the authorization code for an access token.
    $tokenUrl = 'https://graph.facebook.com/v20.0/oauth/access_token?' . http_build_query([
        'client_id' => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ]);

    $tokenRes = oauth_http_request('GET', $tokenUrl);
    $accessToken = $tokenRes['body']['access_token'] ?? null;

    if ($tokenRes['status'] !== 200 || !$accessToken) {
        error_log('Facebook token exchange failed: ' . json_encode($tokenRes));
        facebook_fail('Could not sign in with Facebook. Please try again.');
    }

    // 2) Fetch the user's profile.
    $profileUrl = 'https://graph.facebook.com/v20.0/me?' . http_build_query([
        'fields' => 'id,first_name,last_name,email,picture.type(large)',
        'access_token' => $accessToken,
    ]);

    $profileRes = oauth_http_request('GET', $profileUrl);
    $profile = $profileRes['body'];

    $facebookId = $profile['id'] ?? null;
    $email = $profile['email'] ?? null;

    if (!$facebookId) {
        facebook_fail('Could not read your Facebook profile. Please try again.');
    }
    if (!$email) {
        // Facebook accounts can be created via phone number only, with no email on file.
        facebook_fail('Your Facebook account needs a verified email address to sign in here.');
    }

    $firstName = $profile['first_name'] ?? '';
    $lastName = $profile['last_name'] ?? '';
    $avatarUrl = $profile['picture']['data']['url'] ?? null;

    $user = oauth_find_or_create_user($conn, 'facebook', $facebookId, $email, $firstName, $lastName, $avatarUrl);
    if (!$user) {
        facebook_fail('Something went wrong creating your account. Please try again.');
    }

    if (!empty($user['is_blacklisted'])) {
        facebook_fail('This account has been restricted. Please contact support.');
    }
    if (!empty($user['is_locked'])) {
        $lockExpired = !empty($user['locked_until']) && strtotime((string) $user['locked_until']) <= time();
        if (!$lockExpired) {
            facebook_fail('This account is temporarily locked. Please try again later.');
        }
    }

    oauth_log_in_user($user);

    $role = $_SESSION['role'] ?? 'user';
    header('Location: ../../' . ($role === 'admin' ? 'pages/admin/index.php' : 'pages/user/user-dashboard.php'));
    exit;
} catch (Throwable $e) {
    error_log('Facebook OAuth error: ' . $e->getMessage());
    facebook_fail('Something went wrong signing in with Facebook. Please try again.');
}