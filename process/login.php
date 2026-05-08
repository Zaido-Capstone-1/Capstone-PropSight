<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
require_once '../includes/db.php';
require_once '../includes/rate_limiter.php';
require_once '../vendor/autoload.php';

// Apply rate limiting to login attempts (more restrictive)
applyRateLimit($conn, 'login', 10, 900); // 10 attempts per 15 minutes

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 5;
const OTP_EXPIRY_MINUTES = 5;

const DEFAULT_SMTP_HOST = 'smtp.gmail.com';
const DEFAULT_SMTP_PORT = 587;
const DEFAULT_MAIL_NAME = 'Boracay Accommodation';

//Functions
function json_error(string $message): never
{
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function json_response(string $status, string $message, array $data = []): never
{
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

function generate_otp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function smtp_config(): array
{
    return [
        'host' => MAIL_HOST,
        'port' => MAIL_PORT,
        'username' => MAIL_USERNAME,
        'password' => MAIL_PASSWORD,
        'mail_from' => MAIL_FROM_EMAIL,
        'mail_name' => MAIL_FROM_NAME,
    ];
}

function build_otp_email(string $otp): string
{
    $year = date('Y');
    $otp_safe = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    return '
    <div style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
            <tr>
                <td align="center">

                    <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                        style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">

                        <tr>
                            <td style="background:#1e3a5f;color:#ffffff;padding:20px;text-align:center;">
                                <h1 style="margin:0;font-size:18px;color:#ffffff;letter-spacing:0.5px;">
                                    Secure Verification Code
                                </h1>
                                <p style="margin:6px 0 0 0;font-size:13px;color:#dbeafe;">
                                    One-Time Password (OTP) Authentication
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:32px 28px;">

                                <p style="margin:0 0 10px 0;font-size:14px;color:#111827;">
                                    Hello,
                                </p>

                                <p style="margin:0 0 18px 0;font-size:14px;color:#4b5563;line-height:1.6;">
                                    We received a request to log in to your account.
                                    Please use the verification code below to continue.
                                </p>

                                <p style="margin:0 0 25px 0;font-size:13px;color:#6b7280;">
                                    This code will expire in
                                    <strong style="color:#111827;">' . OTP_EXPIRY_MINUTES . ' minutes</strong>.
                                </p>

                                <!-- OTP Box -->
                                <div style="text-align:center;margin:30px 0;">
                                    <div style="display:inline-block;font-size:34px;font-weight:700;letter-spacing:10px;color:#1d4ed8;padding:16px 26px;border:2px dashed #3b82f6;border-radius:12px;background:#eff6ff;min-width:220px;">
                                        ' . $otp_safe . '
                                    </div>
                                </div>

                                <div style="background:#fff7ed;padding:12px 14px;border-radius:8px;margin:25px 0;">
                                    <p style="margin:0;font-size:12.5px;color:#9a3412;line-height:1.5;">
                                        If you did not attempt to log in, ignore this email immediately.
                                        Your account remains secure unless you share this code.
                                    </p>
                                </div>

                                <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.6;">
                                    For your security, never share this code with anyone — not even support staff.
                                </p>

                            </td>
                        </tr>

                        <tr>
                            <td style="background:#f9fafb;padding:18px;text-align:center;">
                                <p style="margin:0;font-size:11.5px;color:#9ca3af;">
                                    © ' . $year . ' Boracay Accommodation. All rights reserved.
                                </p>
                                <p style="margin:6px 0 0 0;font-size:11px;color:#c0c4cc;">
                                    This is an automated message, please do not reply.
                                </p>
                            </td>
                        </tr>

                    </table>

                </td>
            </tr>
        </table>
    </div>
    ';
}

function send_otp_email(string $to_email, string $otp): void
{
    $smtp = smtp_config();
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = $smtp['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['username'];
    $mail->Password = $smtp['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom($smtp['mail_from'], $smtp['mail_name']);
    $mail->addAddress($to_email);
    $mail->isHTML(true);
    $mail->Subject = 'Your Login OTP Code';
    $mail->Body = build_otp_email($otp);

    $mail->send();
}

function store_otp_in_session(string $otp, array $user): void
{
    $_SESSION['pending_otp'] = $otp;
    $_SESSION['pending_otp_email'] = $user['email'];
    $_SESSION['pending_otp_expires'] = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $_SESSION['pending_user'] = $user;
}


if (!isset($_POST['login'])) {
    json_error('Invalid request!');
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    json_error('All fields are required!');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email!');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user === null) {
    json_error('Email not found!');
}

//Checking of locked account and expiration of lock

if ($user['is_locked']) {
    $lock_expired = !empty($user['locked_until']) && strtotime($user['locked_until']) <= time();

    if ($lock_expired) {
        $unlock = $conn->prepare("UPDATE users SET is_locked = 0, login_attempts = 0, locked_until = NULL WHERE user_id = ?");
        $unlock->bind_param('i', $user['user_id']);
        $unlock->execute();
        $unlock->close();

        $user['is_locked'] = 0;
    } else {
        json_error('Account is temporarily locked. Try again later.');
    }
}

//Validation of password

if (!password_verify($password, $user['password'])) {
    $attempts = $user['login_attempts'] + 1;
    $should_lock = $attempts >= MAX_LOGIN_ATTEMPTS;
    $locked_until = $should_lock
        ? date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_MINUTES . ' minutes'))
        : null;

    $update = $conn->prepare("
        UPDATE users
        SET login_attempts = ?, is_locked = ?, last_attempt = NOW(), locked_until = ?
        WHERE user_id = ?
    ");
    $update->bind_param('iisi', $attempts, $should_lock, $locked_until, $user['user_id']);
    $update->execute();
    $update->close();

    if ($should_lock) {
        json_error('Account locked due to too many failed attempts.');
    }

    json_error("Incorrect password! Attempt {$attempts}/" . MAX_LOGIN_ATTEMPTS);
}

// Reset login attempts on successful password verification
$resetStmt = $conn->prepare("UPDATE users SET login_attempts = 0, is_locked = 0, locked_until = NULL WHERE user_id = ?");
$resetStmt->bind_param('i', $user['user_id']);
$resetStmt->execute();
$resetStmt->close();

// Check if user has 2FA enabled in user_settings
$twoFaEnabled = false;
$settingsStmt = $conn->prepare("SELECT two_factor_enabled FROM user_settings WHERE user_id = ? LIMIT 1");
if ($settingsStmt) {
    $settingsStmt->bind_param('i', $user['user_id']);
    $settingsStmt->execute();
    $settingsRow = $settingsStmt->get_result()->fetch_assoc();
    $settingsStmt->close();
    $twoFaEnabled = !empty($settingsRow['two_factor_enabled']);
}

if (!$twoFaEnabled) {
    // 2FA is OFF — log the user in directly
    session_regenerate_id(true);
    $_SESSION['login'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['nationality'] = $user['nationality'];
    $_SESSION['birthday'] = $user['birthday'];
    $_SESSION['gender'] = $user['gender'];
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['verification_status'] = $user['verification_status'] ?? 'Not Verified';
    $_SESSION['profile_photo'] = $user['profile_photo'] ?? '';
    $_SESSION['is_blacklisted'] = (bool) ($user['is_blacklisted'] ?? false);
    json_response('success', 'Login successful!', ['role' => $_SESSION['role']]);
}

// 2FA is ON — send OTP
$otp = generate_otp();
store_otp_in_session($otp, $user);

try {
    send_otp_email($user['email'], $otp);
    json_response('otp_sent', 'OTP sent to your email!', [
        'otp_expires_in_seconds' => OTP_EXPIRY_MINUTES * 60,
    ]);
} catch (Exception $e) {
    error_log('Mailer error for ' . $user['email'] . ': ' . $e->getMessage());
    json_error('Failed to send OTP. Please try again.');
}