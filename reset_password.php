<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/session_params.php';
session_start();
require_once __DIR__ . '/includes/db.php';

// Validate the token on page load — show an error page if it's bad/expired
$token = trim($_GET['token'] ?? '');
$tokenErr = '';

if ($token === '') {
    $tokenErr = 'No reset token provided.';
} else {
    $tokenEsc = mysqli_real_escape_string($conn, $token);
    $tokenRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT id, expires_at, used FROM password_resets WHERE token='$tokenEsc' LIMIT 1"
    ));
    if (!$tokenRow) {
        $tokenErr = 'This reset link is invalid or has already been used.';
    } elseif ((int) $tokenRow['used'] === 1) {
        $tokenErr = 'This reset link has already been used. Please request a new one.';
    } elseif (strtotime($tokenRow['expires_at']) < time()) {
        $tokenErr = 'This reset link has expired (links are valid for 30 minutes). Please request a new one.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Boracay Accommodation</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Jost:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/reset_password.css">
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

        <?php if ($tokenErr): ?>

            <div class="token-error">
                <div class="token-error-icon">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                </div>
                <h1 class="card-title">Link Expired or Invalid</h1>
                <p class="card-sub"><?= htmlspecialchars($tokenErr) ?></p>
            </div>

        <?php else: ?>

            <div class="card-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </div>

            <h1 class="card-title">Set a New Password</h1>
            <p class="card-sub">Enter a new password for your account.</p>

            <div class="alert error" id="alertError"></div>
            <div class="alert success" id="alertSuccess"></div>

            <form id="resetForm" autocomplete="off">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="field">
                    <label for="rpPassword">New Password</label>
                    <div class="field-wrap">
                        <input type="password" id="rpPassword" name="password" placeholder="At least 8 characters"
                            autocomplete="new-password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('rpPassword', this)"
                            aria-label="Show password">
                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none"
                                stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="strength-bar-wrap">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>
                </div>

                <div class="field">
                    <label for="rpConfirm">Confirm New Password</label>
                    <div class="field-wrap">
                        <input type="password" id="rpConfirm" name="confirm" placeholder="Repeat your new password"
                            autocomplete="new-password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('rpConfirm', this)"
                            aria-label="Show password">
                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none"
                                stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="error-msg" id="matchError">Passwords do not match.</div>
                </div>

                <button type="submit" class="btn" id="submitBtn">Reset Password</button>
            </form>

        <?php endif; ?>

        <div class="back-link">
            <?php if ($tokenErr): ?>
                <a href="index.php?forgot=1">Request a new reset link</a> &nbsp;·&nbsp;
            <?php endif; ?>
            <a href="index.php">Back to Login</a>
        </div>

    </div>

    <script src="assets/js/reset_password.js"></script>
</body>

</html>