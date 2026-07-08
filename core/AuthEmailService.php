<?php

declare(strict_types=1);

class AuthEmailService
{
  private object $mailer; // EmailService instance

  public function __construct(object $mailer)
  {
    $this->mailer = $mailer;
  }

  // Login OTP

  public function sendLoginOtp(string $toEmail, string $otp, int $expiryMinutes = 5): void
  {
    $subject = 'Your Login OTP Code';
    $html = $this->buildLoginOtpHtml($otp, $expiryMinutes);
    $text = "Your login verification code is: {$otp}\n\nExpires in {$expiryMinutes} minutes.\n\nDo not share this code with anyone.";

    if (!$this->mailer->sendEmail($toEmail, $subject, $html, $text)) {
      throw new \RuntimeException('Failed to send OTP email.');
    }
  }

  // Email Verification OTP
  public function sendVerifyOtp(string $toEmail, string $firstName, string $otp): void
  {
    $brand = htmlspecialchars(MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');
    $subject = "Your verification code - {$brand}";
    $html = $this->buildVerifyOtpHtml($otp, $firstName);
    $text = "Hi {$firstName},\n\nYour verification code is: {$otp}\n\nExpires in 10 minutes.\n\nIf you didn't sign up, ignore this email.";

    if (!$this->mailer->sendEmail($toEmail, $subject, $html, $text)) {
      throw new \RuntimeException('Failed to send verification email.');
    }
  }

  // Password Reset
  public function sendPasswordReset(
    string $toEmail,
    string $firstName,
    string $resetLink,
    int $expiryMinutes = 30
  ): void {
    $siteName = htmlspecialchars(MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');
    $subject = "Reset Your Password — {$siteName}";
    $html = $this->buildPasswordResetHtml($firstName, $resetLink, $expiryMinutes);
    $text = "Hi {$firstName},\n\nReset your password by visiting:\n{$resetLink}\n\nThis link expires in {$expiryMinutes} minutes.\n\nIf you did not request this, ignore this email.";

    if (!$this->mailer->sendEmail($toEmail, $subject, $html, $text)) {
      throw new \RuntimeException('Failed to send password reset email.');
    }
  }

  // HTML builders

  private function buildLoginOtpHtml(string $otp, int $expiryMinutes): string
  {
    $year = date('Y');
    $otpSafe = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <div style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr><td align="center">
          <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
            <tr>
              <td style="background:#1e3a5f;color:#ffffff;padding:20px;text-align:center;">
                <h1 style="margin:0;font-size:18px;color:#ffffff;letter-spacing:0.5px;">Secure Verification Code</h1>
                <p style="margin:6px 0 0;font-size:13px;color:#dbeafe;">One-Time Password (OTP) Authentication</p>
              </td>
            </tr>
            <tr>
              <td style="padding:32px 28px;">
                <p style="margin:0 0 18px;font-size:14px;color:#4b5563;line-height:1.6;">
                  We received a request to log in to your account.
                  Please use the verification code below to continue.
                </p>
                <p style="margin:0 0 25px;font-size:13px;color:#6b7280;">
                  This code will expire in <strong style="color:#111827;">{$expiryMinutes} minutes</strong>.
                </p>
                <div style="text-align:center;margin:30px 0;">
                  <div style="display:inline-block;font-size:34px;font-weight:700;letter-spacing:10px;
                              color:#1d4ed8;padding:16px 26px;border:2px dashed #3b82f6;
                              border-radius:12px;background:#eff6ff;min-width:220px;">{$otpSafe}</div>
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
                  &copy; {$year} Boracay Accommodation. All rights reserved.
                </p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </div>
    HTML;
  }

  private function buildVerifyOtpHtml(string $otp, string $firstName): string
  {
    $year = date('Y');
    $otpSafe = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $nameSafe = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $brand = htmlspecialchars(MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <div style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr><td align="center">
          <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                style="background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
            <tr>
              <td style="background:#1e3a5f;padding:24px;text-align:center;">
                <h1 style="margin:0;font-size:18px;color:#fff;">Verify Your Email</h1>
                <p style="margin:6px 0 0;font-size:13px;color:#dbeafe;">One last step to activate your account</p>
              </td>
            </tr>
            <tr>
              <td style="padding:32px 28px;">
                <p style="margin:0 0 10px;font-size:14px;color:#111827;">Hi {$nameSafe},</p>
                <p style="margin:0 0 18px;font-size:14px;color:#4b5563;line-height:1.6;">
                  Enter the code below to verify your email address and start browsing.
                </p>
                <p style="margin:0 0 25px;font-size:13px;color:#6b7280;">
                  Expires in <strong style="color:#111827;">10 minutes</strong>.
                </p>
                <div style="text-align:center;margin:30px 0;">
                  <div style="display:inline-block;font-size:34px;font-weight:700;letter-spacing:10px;
                              color:#1d4ed8;padding:16px 26px;border:2px dashed #3b82f6;
                              border-radius:12px;background:#eff6ff;min-width:220px;">{$otpSafe}</div>
                </div>
                <div style="background:#fff7ed;padding:12px 14px;border-radius:8px;margin:25px 0;">
                  <p style="margin:0;font-size:12.5px;color:#9a3412;line-height:1.5;">
                    If you did not sign up, you can safely ignore this email.
                  </p>
                </div>
              </td>
            </tr>
            <tr>
              <td style="background:#f9fafb;padding:18px;text-align:center;">
                <p style="margin:0;font-size:11.5px;color:#9ca3af;">&copy; {$year} {$brand}. All rights reserved.</p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </div>
    HTML;
  }

  private function buildPasswordResetHtml(
    string $firstName,
    string $resetLink,
    int $expiryMinutes
  ): string {
    $year = date('Y');
    $siteName = htmlspecialchars(MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');
    $linkSafe = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <div style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr><td align="center">
          <table role="presentation" width="520" cellpadding="0" cellspacing="0"
                style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
            <tr>
              <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d5a8e 100%);padding:32px 40px;text-align:center;">
                <div style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">{$siteName}</div>
                <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;">Password Reset Request</div>
              </td>
            </tr>
            <tr>
              <td style="padding:36px 40px;">
                <p style="font-size:15px;color:#374151;margin:0 0 18px;">Hi <strong>{$firstName}</strong>,</p>
                <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 28px;">
                  We received a request to reset the password for your account. Click the button below
                  to choose a new password. This link expires in <strong>{$expiryMinutes} minutes</strong>.
                </p>
                <div style="text-align:center;margin-bottom:28px;">
                  <a href="{$linkSafe}"
                    style="display:inline-block;background:#1e3a5f;color:#ffffff;text-decoration:none;
                            padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600;">
                    Reset My Password
                  </a>
                </div>
                <p style="font-size:12px;color:#9ca3af;margin:0 0 8px;">
                  Or copy and paste this URL into your browser:
                </p>
                <p style="font-size:11px;color:#6b7280;word-break:break-all;background:#f9fafb;
                          padding:10px 14px;border-radius:6px;border:1px solid #e5e7eb;margin:0 0 24px;">
                  {$linkSafe}
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
                  &copy; {$year} {$siteName}. All rights reserved.
                </p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </div>
    HTML;
  }
}