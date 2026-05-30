<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function out(bool $ok, string $msg): never
{
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

if (empty($_SESSION['login']) || empty($_SESSION['user_id']) || empty($_SESSION['pending_verification'])) {
    out(false, 'Unauthorized.');
}

$lastSent = (int) ($_SESSION['otp_last_sent'] ?? 0);
if (time() - $lastSent < 60) {
    $wait = 60 - (time() - $lastSent);
    out(false, "Please wait {$wait}s before requesting another code.");
}

$email = strtolower(trim((string) ($_SESSION['email'] ?? '')));
$firstName = (string) ($_SESSION['first_name'] ?? 'there');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, 'Session email is invalid. Please log in again.');
}

$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['verify_otp'] = $otp;
$_SESSION['verify_otp_expires'] = time() + (10 * 60);
$_SESSION['otp_last_sent'] = time();

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->Port = (int) MAIL_PORT;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your verification code - ' . MAIL_FROM_NAME;
    $mail->CharSet = 'UTF-8';

    $otp_safe = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $name_safe = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $brand = htmlspecialchars(MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    $mail->Body = "
    <div style='margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;'>
    <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='padding:40px 0;'>
    <tr><td align='center'>
        <table role='presentation' width='520' cellpadding='0' cellspacing='0'
            style='background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;'>
            <tr><td style='background:#1e3a5f;padding:24px;text-align:center;'>
                <h1 style='margin:0;font-size:18px;color:#fff;'>Verify Your Email</h1>
                <p style='margin:6px 0 0;font-size:13px;color:#dbeafe;'>One last step to activate your account</p>
            </td></tr>
            <tr><td style='padding:32px 28px;'>
                <p style='margin:0 0 10px;font-size:14px;color:#111827;'>Hi {$name_safe},</p>
                <p style='margin:0 0 18px;font-size:14px;color:#4b5563;line-height:1.6;'>
                    Enter the code below to verify your email address and start browsing.
                </p>
                <p style='margin:0 0 25px;font-size:13px;color:#6b7280;'>
                    Expires in <strong style='color:#111827;'>10 minutes</strong>.
                </p>
                <div style='text-align:center;margin:30px 0;'>
                    <div style='display:inline-block;font-size:34px;font-weight:700;letter-spacing:10px;
                                color:#1d4ed8;padding:16px 26px;border:2px dashed #3b82f6;
                                border-radius:12px;background:#eff6ff;min-width:220px;'>{$otp_safe}</div>
                </div>
                <div style='background:#fff7ed;padding:12px 14px;border-radius:8px;margin:25px 0;'>
                    <p style='margin:0;font-size:12.5px;color:#9a3412;line-height:1.5;'>
                        If you did not sign up, you can safely ignore this email.
                    </p>
                </div>
            </td></tr>
            <tr><td style='background:#f9fafb;padding:18px;text-align:center;'>
                <p style='margin:0;font-size:11.5px;color:#9ca3af;'>© {$year} {$brand}. All rights reserved.</p>
            </td></tr>
        </table>
    </td></tr>
    </table>
    </div>";

    $mail->send();
    out(true, 'Code sent to your email address.');
} catch (Exception $e) {
    error_log('Email OTP send failed: ' . $e->getMessage());
    out(false, 'Failed to send email. Please try again.');
}
