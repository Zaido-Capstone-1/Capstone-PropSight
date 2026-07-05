<?php
declare(strict_types=1);

/**
 * Shared helpers for Google / Facebook social login.
 * Mirrors the cURL + CA-cert fallback pattern already used in includes/paymongo.php
 * so local XAMPP installs without a configured CA bundle still work.
 */

function oauth_http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ];

    $caInfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($caInfo && file_exists($caInfo)) {
        $opts[CURLOPT_CAINFO] = $caInfo;
    } else {
        $defaultCa = __DIR__ . '/../extras/ssl/cacert.pem';
        if (file_exists($defaultCa)) {
            $opts[CURLOPT_CAINFO] = $defaultCa;
        } else {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }
    }

    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('OAuth HTTP request failed: ' . $error);
    }

    $decoded = json_decode($response, true);
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : []];
}

/**
 * Builds the redirect_uri for the current request automatically, so the
 * same code works unmodified on http://localhost and on an ngrok https://
 * tunnel — no .env editing needed when switching between them.
 *
 * It reuses the current script's own directory (api/auth/) and just swaps
 * in the given callback filename, so it also survives the project folder
 * being renamed (e.g. Propsight-Capstone -> something else).
 */
function oauth_build_redirect_uri(string $callbackFilename): string
{
    // ngrok (and most reverse proxies) forward the original scheme/host here.
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
    if ($forwardedProto) {
        $scheme = strtolower(trim(explode(',', $forwardedProto)[0])) === 'https' ? 'https' : 'http';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    return $scheme . '://' . $host . $dir . '/' . $callbackFilename;
}

/**
 * Generates a cryptographically random `state` value, stores it in the
 * session under the given key, and returns it (for CSRF protection on the
 * OAuth redirect flow).
 */
function oauth_generate_state(string $sessionKey): string
{
    $state = bin2hex(random_bytes(24));
    $_SESSION[$sessionKey] = $state;
    return $state;
}

function oauth_verify_state(string $sessionKey, ?string $receivedState): bool
{
    $expected = $_SESSION[$sessionKey] ?? null;
    unset($_SESSION[$sessionKey]);
    return $expected !== null && $receivedState !== null && hash_equals($expected, $receivedState);
}

/**
 * Finds an existing user by (provider, provider_id), falling back to
 * matching by email (to link an existing password-based account the first
 * time someone uses social login with the same email). Creates a new user
 * if neither match is found.
 *
 * Returns the full users row (assoc array) on success, or null on failure.
 */
function oauth_find_or_create_user(
    mysqli $conn,
    string $provider,
    string $providerId,
    string $email,
    string $firstName,
    string $lastName,
    ?string $avatarUrl
): ?array {
    // 1) Already linked to this provider?
    $stmt = $conn->prepare("SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1");
    $stmt->bind_param('ss', $provider, $providerId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($user) {
        return $user;
    }

    // 2) Existing account with the same email — link it to this provider.
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $link = $conn->prepare("UPDATE users SET oauth_provider = ?, oauth_id = ? WHERE user_id = ?");
        $link->bind_param('ssi', $provider, $providerId, $user['user_id']);
        $link->execute();
        $link->close();
        $user['oauth_provider'] = $provider;
        $user['oauth_id'] = $providerId;
        return $user;
    }

    // 3) Brand new user — create the account.
    // Social accounts don't set a real password; store an unusable random
    // hash so the NOT NULL column is satisfied and the hash can never match
    // a real login attempt.
    $unusablePassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $firstName = $firstName !== '' ? $firstName : 'Guest';
    $lastName = $lastName !== '' ? $lastName : ucfirst($provider);

    $stmt = $conn->prepare("
        INSERT INTO users
            (first_name, last_name, email, password, oauth_provider, oauth_id, verification_status, profile_photo)
        VALUES
            (?, ?, ?, ?, ?, ?, 'Verified', ?)
    ");
    $stmt->bind_param(
        'sssssss',
        $firstName,
        $lastName,
        $email,
        $unusablePassword,
        $provider,
        $providerId,
        $avatarUrl
    );

    if (!$stmt->execute()) {
        error_log('oauth_find_or_create_user insert error: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    // Default notification/2FA settings row, same as the normal signup flow.
    $settingsStmt = $conn->prepare("INSERT IGNORE INTO user_settings (user_id) VALUES (?)");
    if ($settingsStmt) {
        $settingsStmt->bind_param('i', $newId);
        $settingsStmt->execute();
        $settingsStmt->close();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $newId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user;
}

/**
 * Populates $_SESSION for a logged-in user the same way process/login.php
 * does after a successful password (or OTP) check. Social logins skip 2FA,
 * since the identity provider has already authenticated the person.
 */
function oauth_log_in_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['login'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['phone'] = $user['phone'] ?? '';
    $_SESSION['nationality'] = $user['nationality'] ?? '';
    $_SESSION['birthday'] = $user['birthday'] ?? '';
    $_SESSION['gender'] = $user['gender'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['verification_status'] = $user['verification_status'] ?? 'Not Verified';
    $_SESSION['profile_photo'] = $user['profile_photo'] ?? '';
    $_SESSION['is_blacklisted'] = (bool) ($user['is_blacklisted'] ?? false);
}