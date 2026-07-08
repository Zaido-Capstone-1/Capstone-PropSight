<?php

declare(strict_types=1);
class SessionManager
{
    // User session
    public function populateUser(array $user): void
    {
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
    }

    public function populatePendingVerification(array $user): void
    {
        $_SESSION['login'] = true;
        $_SESSION['pending_verification'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['role'] = 'user';
        $_SESSION['verification_status'] = 'Not Verified';
    }

    // Login OTP (2FA)
    public function storeLoginOtp(string $otp, array $user, int $expiryMinutes): void
    {
        $_SESSION['pending_otp'] = $otp;
        $_SESSION['pending_otp_email'] = $user['email'];
        $_SESSION['pending_otp_expires'] = date(
            'Y-m-d H:i:s',
            strtotime('+' . $expiryMinutes . ' minutes')
        );
        $_SESSION['pending_user'] = $user;
    }

    public function hasLoginOtp(): bool
    {
        return isset(
            $_SESSION['pending_otp'],
            $_SESSION['pending_user'],
            $_SESSION['pending_otp_expires']
        );
    }

    public function getLoginOtp(): string
    {
        return (string) ($_SESSION['pending_otp'] ?? '');
    }

    public function getLoginOtpExpiry(): string
    {
        return (string) ($_SESSION['pending_otp_expires'] ?? '');
    }

    public function getPendingUser(): array
    {
        return (array) ($_SESSION['pending_user'] ?? []);
    }

    public function clearLoginOtp(): void
    {
        unset(
            $_SESSION['pending_otp'],
            $_SESSION['pending_otp_email'],
            $_SESSION['pending_otp_expires'],
            $_SESSION['pending_user']
        );
    }

    // Verify OTP (email verification)
    public function storeVerifyOtp(string $otp, int $expirySeconds = 600): void
    {
        $_SESSION['verify_otp'] = $otp;
        $_SESSION['verify_otp_expires'] = time() + $expirySeconds;
        $_SESSION['otp_last_sent'] = time();
    }

    public function getVerifyOtp(): string
    {
        return (string) ($_SESSION['verify_otp'] ?? '');
    }

    public function getVerifyOtpExpiry(): int
    {
        return (int) ($_SESSION['verify_otp_expires'] ?? 0);
    }

    public function getOtpLastSent(): int
    {
        return (int) ($_SESSION['otp_last_sent'] ?? 0);
    }

    public function clearVerifyOtp(): void
    {
        unset(
            $_SESSION['pending_verification'],
            $_SESSION['verify_otp'],
            $_SESSION['verify_method'],
            $_SESSION['verify_otp_expires'],
            $_SESSION['otp_last_sent']
        );
    }

    // Helpers
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function refreshCsrf(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function destroy(): void
    {
        session_unset();
        session_destroy();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }
}