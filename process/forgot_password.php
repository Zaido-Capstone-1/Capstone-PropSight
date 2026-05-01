<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
require_once '../includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

const RESET_EXPIRY_MINUTES = 30;
const RESET_RATE_LIMIT_MINUTES = 5; // don't send more than 1 email per 5 min per address

function json_out(bool $success, string $message): never
{
  echo json_encode(['success' => $success, 'message' => $message]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(false, 'Invalid request method.');
}

$email = trim(strtolower($_POST['email'] ?? ''));

// Enhanced email validation
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_out(false, 'Please enter a valid email address.');
}

// Additional email format checks to prevent common attacks
if (strlen($email) > 254 || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
  json_out(false, 'Please enter a valid email address.');
}

$emailEsc = mysqli_real_escape_string($conn, $email);

// Look up the user with additional security checks
$userRow = mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT user_id, first_name, verification_status, is_active, role FROM users WHERE email='$emailEsc' AND is_active = 1 LIMIT 1"
));

// Log the attempt for security monitoring (whether user exists or not)
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Additional IP-based rate limiting to prevent abuse
$ipEsc = mysqli_real_escape_string($conn, $ipAddress);
$ipAttempts = ['attempts' => 0];
try {
  $ipAttempts = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as attempts FROM password_reset_attempts 
     WHERE ip_address='$ipEsc' AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
  ));
} catch (mysqli_sql_exception $e) {
  error_log('Password reset rate-limit table missing or unavailable: ' . $e->getMessage());
}

if ($ipAttempts && $ipAttempts['attempts'] > 10) { // Max 10 attempts per hour per IP
  error_log("Password reset blocked: Too many attempts from IP $ipAddress");
  json_out(true, "If that email is registered and verified, you'll receive a reset link shortly. Check your inbox (and spam folder).");
}

// Log the attempt
$logMessage = sprintf(
  "[%s] Password reset attempt for email: %s from IP: %s - User %s",
  date('Y-m-d H:i:s'),
  $email,
  $ipAddress,
  $userRow ? 'found' : 'not found'
);
error_log($logMessage);

// Record the attempt for rate limiting
try {
  mysqli_query(
    $conn,
    "INSERT INTO password_reset_attempts (email, ip_address, attempted_at, user_found) 
     VALUES ('$emailEsc', '$ipEsc', NOW(), " . ($userRow ? 1 : 0) . ")"
  );
} catch (mysqli_sql_exception $e) {
  error_log('Password reset attempt log failed: ' . $e->getMessage());
}

// Rate-limit: if a reset was sent in the last RESET_RATE_LIMIT_MINUTES, silently succeed
if ($userRow) {
  $userId = (int) $userRow['user_id'];
  $firstName = htmlspecialchars($userRow['first_name'], ENT_QUOTES, 'UTF-8');
  $verificationStatus = $userRow['verification_status'] ?? 'Not Verified';

  $isAdmin = isset($userRow['role']) && strtolower($userRow['role']) === 'admin';

  if (!$isAdmin && $verificationStatus !== 'Verified') {
    error_log("Password reset blocked: Email not verified for user ID $userId (status={$verificationStatus})");
    json_out(false, "Your email address needs to be verified before you can reset your password. Check your inbox for the verification link, or contact support.");
  }

  $recentRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT id FROM password_resets
         WHERE user_id=$userId AND used=0
           AND created_at > DATE_SUB(NOW(), INTERVAL " . RESET_RATE_LIMIT_MINUTES . " MINUTE)
         ORDER BY created_at DESC LIMIT 1"
  ));

  if (!$recentRow) {
    // Invalidate all previous tokens for this user
    mysqli_query($conn, "UPDATE password_resets SET used=1 WHERE user_id=$userId AND used=0");

    // Generate a cryptographically secure token
    $token = bin2hex(random_bytes(32)); // 64-char hex
    $expires = date('Y-m-d H:i:s', time() + RESET_EXPIRY_MINUTES * 60);
    $tokenEsc = mysqli_real_escape_string($conn, $token);

    mysqli_query(
      $conn,
      "INSERT INTO password_resets (user_id, email, token, expires_at)
             VALUES ($userId, '$emailEsc', '$tokenEsc', '$expires')"
    );

    // Build reset link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $resetLink = rtrim($protocol . '://' . $host . $scriptDir, '/') . '/reset_password.php?token=' . urlencode($token);

    // Send email (rest of email sending code remains the same)
    try {
      // Validate mail configuration exists
      if (empty(MAIL_HOST) || empty(MAIL_PORT) || empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) {
        throw new Exception('Mail service is not configured. Please contact support.');
      }

      $mail = new PHPMailer(true);
      $mail->CharSet = 'UTF-8';
      $mail->isSMTP();
      $mail->Host = MAIL_HOST;
      $mail->Port = (int) MAIL_PORT;
      $mail->SMTPAuth = true;
      $mail->Username = MAIL_USERNAME;
      $mail->Password = MAIL_PASSWORD;

      // Enable debugging for development (remove in production)
      // $mail->SMTPDebug = 2;

      // Respect MAIL_ENCRYPTION from .env (tls = STARTTLS, ssl = SMTPS)
      $encryptionSetting = strtolower(trim(MAIL_ENCRYPTION));
      if ($encryptionSetting === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // default: tls / port 587
      }

      $mail->SMTPOptions = [
        'ssl' => [
          'verify_peer' => false,
          'verify_peer_name' => false,
          'allow_self_signed' => true,
        ],
      ];

      $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
      $mail->addAddress($email, $firstName);
      $mail->isHTML(true);
      $mail->Subject = 'Reset Your Password — ' . MAIL_FROM_NAME;

      $year = date('Y');
      $expMinutes = RESET_EXPIRY_MINUTES;
      $linkSafe = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
      $siteName = htmlspecialchars(MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');

      $mail->Body = <<<HTML
<div style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr><td align="center">
      <table role="presentation" width="520" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">

        <tr>
          <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d5a8e 100%);padding:32px 40px;text-align:center;">
            <div style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">$siteName</div>
            <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;">Password Reset Request</div>
          </td>
        </tr>

        <tr>
          <td style="padding:36px 40px;">
            <p style="font-size:15px;color:#374151;margin:0 0 18px;">Hi <strong>$firstName</strong>,</p>
            <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 28px;">
              We received a request to reset the password for your account. Click the button below
              to choose a new password. This link expires in <strong>$expMinutes minutes</strong>.
            </p>

            <div style="text-align:center;margin-bottom:28px;">
              <a href="$linkSafe"
                 style="display:inline-block;background:#1e3a5f;color:#ffffff;text-decoration:none;
                        padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600;
                        letter-spacing:0.2px;">
                Reset My Password
              </a>
            </div>

            <p style="font-size:12px;color:#9ca3af;line-height:1.6;margin:0 0 8px;">
              Or copy and paste this URL into your browser:
            </p>
            <p style="font-size:11px;color:#6b7280;word-break:break-all;background:#f9fafb;
                      padding:10px 14px;border-radius:6px;border:1px solid #e5e7eb;margin:0 0 24px;">
              $linkSafe
            </p>

            <p style="font-size:13px;color:#9ca3af;margin:0;">
              If you did not request a password reset, you can safely ignore this email —
              your password will not be changed.
            </p>
          </td>
        </tr>

        <tr>
          <td style="background:#f9fafb;padding:18px 40px;border-top:1px solid #e5e7eb;text-align:center;">
            <p style="font-size:12px;color:#9ca3af;margin:0;">
              &copy; $year $siteName. All rights reserved.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</div>
HTML;
      $mail->AltBody = "Hi $firstName,\n\nReset your password by visiting:\n$resetLink\n\nThis link expires in $expMinutes minutes.\n\nIf you did not request this, ignore this email.";
      $mail->send();

      error_log("Password reset email sent successfully to user ID $userId ($email)");
      json_out(true, "Reset link sent! Check your email (and spam folder) for instructions.");
    } catch (Exception $e) {
      $errorMsg = $e->getMessage();
      $phpMailerError = $mail->ErrorInfo ?? '';
      error_log("Password reset mail failed for user ID $userId ($email): " . $errorMsg . " | PHPMailer: " . $phpMailerError);

      // Provide specific error messages based on the issue
      if (strpos($errorMsg, 'configured') !== false || strpos($errorMsg, 'SMTP') !== false) {
        json_out(false, 'Email service is not available. Please contact support.');
      } elseif (strpos($errorMsg, 'authentication') !== false || strpos($phpMailerError, 'authentication') !== false) {
        json_out(false, 'Email service authentication failed. Please contact support.');
      } elseif (strpos($errorMsg, 'timeout') !== false) {
        json_out(false, 'Email service timeout. Please try again in a few moments.');
      } else {
        json_out(false, 'Could not send reset email. Please try again later.');
      }
    }
  } else {
    // Rate limit reached for this user
    error_log("Password reset rate-limited: User ID $userId tried to reset password too soon");
    json_out(true, "A reset link was recently sent. Please check your email or wait a few minutes before requesting another.");
  }
} else {
  // Account not found in database
  error_log("Password reset failed: No active account found for email: $email");
  json_out(false, "No account found with that email address. Please check and try again, or sign up if you don't have an account.");
}