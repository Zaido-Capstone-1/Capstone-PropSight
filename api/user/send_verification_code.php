<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_params.php';
session_start();
header('Content-Type: application/json');

require_once '../../includes/db.php';
require_once '../../config.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

const OTP_EXPIRY_MINUTES = 10;
// SMTP config loaded from .env via config.php — no credentials in source code

function json_response(bool $success, string $message): never
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    json_response(false, 'Unauthorized request.');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    json_response(false, 'Invalid CSRF token.');
}

$email = strtolower(trim($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please use a valid email address.');
}

$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT email, verification_status FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    json_response(false, 'User account not found.');
}

if (strcasecmp((string)$user['email'], $email) !== 0) {
    json_response(false, 'Please use your registered account email.');
}

if (($user['verification_status'] ?? '') === 'Verified') {
    json_response(false, 'Your email is already verified.');
}

$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['verify_email_otp'] = $otp;
$_SESSION['verify_email_address'] = $email;
$_SESSION['verify_email_expires'] = time() + (OTP_EXPIRY_MINUTES * 60);

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->Port       = (int) MAIL_PORT;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Verify your email address';

    $year     = date('Y');
    $otp_safe = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    $mail->Body = '
    <div style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                        style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">

                        <!-- Header -->
                        <tr>
                            <td style="background:#1e3a5f;padding:24px;text-align:center;">
                                <h1 style="margin:0;font-size:18px;color:#ffffff;letter-spacing:0.5px;">
                                    Email Verification
                                </h1>
                                <p style="margin:6px 0 0 0;font-size:13px;color:#dbeafe;">
                                    Confirm your email address to continue
                                </p>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding:32px 28px;">

                                <p style="margin:0 0 10px 0;font-size:14px;color:#111827;">Hello,</p>

                                <p style="margin:0 0 18px 0;font-size:14px;color:#4b5563;line-height:1.6;">
                                    Thank you for registering. Please use the verification code below
                                    to confirm your email address and activate your account.
                                </p>

                                <p style="margin:0 0 25px 0;font-size:13px;color:#6b7280;">
                                    This code expires in
                                    <strong style="color:#111827;">' . OTP_EXPIRY_MINUTES . ' minutes</strong>.
                                </p>

                                <!-- OTP Box -->
                                <div style="text-align:center;margin:30px 0;">
                                    <div style="display:inline-block;font-size:34px;font-weight:700;
                                                letter-spacing:10px;color:#1d4ed8;padding:16px 26px;
                                                border:2px dashed #3b82f6;border-radius:12px;
                                                background:#eff6ff;min-width:220px;">
                                        ' . $otp_safe . '
                                    </div>
                                </div>

                                <!-- Warning -->
                                <div style="background:#fff7ed;padding:12px 14px;border-radius:8px;margin:25px 0;">
                                    <p style="margin:0;font-size:12.5px;color:#9a3412;line-height:1.5;">
                                        If you did not create an account, you can safely ignore this email.
                                        Someone may have entered your address by mistake.
                                    </p>
                                </div>

                                <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.6;">
                                    For your security, never share this code with anyone.
                                </p>

                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background:#f9fafb;padding:18px;text-align:center;">
                                <p style="margin:0;font-size:11.5px;color:#9ca3af;">
                                    © ' . $year . ' ' . MAIL_FROM_NAME . '. All rights reserved.
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
    </div>';

    $mail->send();
    json_response(true, 'Verification code sent to your email.');
} catch (Exception $e) {
    error_log('Verification OTP send failed: ' . $e->getMessage());
    json_response(false, 'Failed to send verification code. Please try again.');
}
