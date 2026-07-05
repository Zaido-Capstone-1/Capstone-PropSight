<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_params.php';
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/oauth_helpers.php';

if (empty(FACEBOOK_APP_ID)) {
    header('Location: ../../index.php?oauth_error=' . urlencode('Facebook login is not configured yet.'));
    exit;
}

$state = oauth_generate_state('oauth_state_facebook');
$redirectUri = oauth_build_redirect_uri('facebook_callback.php');

$params = http_build_query([
    'client_id' => FACEBOOK_APP_ID,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'email,public_profile',
    'state' => $state,
]);

header('Location: https://www.facebook.com/v20.0/dialog/oauth?' . $params);
exit;