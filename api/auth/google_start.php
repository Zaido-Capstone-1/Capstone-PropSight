<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_params.php';
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/oauth_helpers.php';

if (empty(GOOGLE_CLIENT_ID)) {
    header('Location: ../../index.php?oauth_error=' . urlencode('Google login is not configured yet.'));
    exit;
}

$state = oauth_generate_state('oauth_state_google');
$redirectUri = oauth_build_redirect_uri('google_callback.php');

$params = http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;