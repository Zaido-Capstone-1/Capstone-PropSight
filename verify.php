<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_params.php';
session_start();

if (empty($_SESSION['login']) || empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['pending_verification'])) {
    $role = $_SESSION['role'] ?? 'user';
    header('Location: ' . ($role === 'admin' ? 'pages/admin/index.php' : 'pages/user/user-dashboard.php'));
    exit;
}

$firstName = htmlspecialchars((string) ($_SESSION['first_name'] ?? 'there'), ENT_QUOTES, 'UTF-8');
$email = (string) ($_SESSION['email'] ?? '');

function maskEmail(string $e): string
{
    [$local, $domain] = explode('@', $e, 2) + ['', ''];
    return substr($local, 0, min(2, strlen($local))) . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}
$maskedEmail = htmlspecialchars(maskEmail($email), ENT_QUOTES, 'UTF-8');

$rateLimitSeconds = 60;
$lastSent = (int) ($_SESSION['otp_last_sent'] ?? 0);
$elapsed = $lastSent > 0 ? time() - $lastSent : $rateLimitSeconds;
$remainingCooldown = max(0, $rateLimitSeconds - $elapsed);
$codeAlreadySent = $lastSent > 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email — Boracay Accommodation</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Jost:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/verify.css">
</head>

<body>

    <a href="index.php" class="top-logo">
        <img src="assets/images/logo.png" alt="Logo">
        <div>
            <div class="top-logo-text">Boracay Accommodation</div>
            <div class="top-logo-sub">Investment Properties &amp; Services</div>
        </div>
    </a>

    <div class="card">

        <div class="steps">
            <div class="step done">
                <div class="step-dot">✓</div>
                <span>Sign Up</span>
            </div>
            <div class="step-line"></div>
            <div class="step active">
                <div class="step-dot">2</div>
                <span>Verify Email</span>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-dot">3</div>
                <span>Done</span>
            </div>
        </div>

        <div class="card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
            </svg>
        </div>

        <h1 class="card-title">Check Your Email</h1>
        <p class="card-sub">
            Hi
            <?= $firstName ?>! We'll send a 6-digit code to<br>
            <strong>
                <?= $maskedEmail ?>
            </strong><br>
            Enter it below to activate your account.
        </p>

        <div class="alert" id="alert"></div>

        <button class="btn-send" id="sendBtn" onclick="sendCode()">
            Send Verification Code
        </button>

        <div class="otp-section" id="otpSection">
            <div class="divider">Enter your code</div>

            <label class="otp-label" for="otpInput">6-Digit Code</label>
            <input type="text" id="otpInput" class="otp-input" inputmode="numeric" maxlength="6" placeholder="••••••"
                autocomplete="one-time-code">

            <div class="resend-row">
                <span id="countdown"></span>
                <button class="resend-btn" id="resendBtn" onclick="resendCode()">Resend code</button>
            </div>

            <button class="btn-verify" id="verifyBtn" onclick="submitOtp()">
                Verify &amp; Continue
            </button>
        </div>

        <div class="card-footer">
            Wrong account? <a href="process/logout.php">Sign out</a>
        </div>

    </div>

    <script>
        window.VERIFY_STATE = {
            codeAlreadySent: <?= $codeAlreadySent ? 'true' : 'false' ?>,
            remainingCooldown: <?= (int) $remainingCooldown ?>
        };
    </script>
    <script src="assets/js/verify.js"></script>
</body>

</html>