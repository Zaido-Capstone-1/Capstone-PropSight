<?php

declare(strict_types=1);

class OtpService
{
    private SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    // Generation of OTP codes

    public function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Login OTP (2FA)
    public function issueLoginOtp(array $user, int $expiryMinutes = 5): string
    {
        $otp = $this->generate();
        $this->session->storeLoginOtp($otp, $user, $expiryMinutes);
        return $otp;
    }

    public function verifyLoginOtp(string $submitted): string
    {
        if (!$this->session->hasLoginOtp()) {
            return 'no_session';
        }

        if (strtotime($this->session->getLoginOtpExpiry()) < time()) {
            $this->session->clearLoginOtp();
            return 'expired';
        }

        if (!hash_equals($this->session->getLoginOtp(), trim($submitted))) {
            return 'invalid';
        }

        return 'ok';
    }

    // Verify OTP (email verification)
    public function issueVerifyOtp(int $expirySeconds = 600, int $rateLimitSeconds = 60): string
    {
        $lastSent = $this->session->getOtpLastSent();
        $elapsed  = time() - $lastSent;

        if ($lastSent > 0 && $elapsed < $rateLimitSeconds) {
            $wait = $rateLimitSeconds - $elapsed;
            throw new \RuntimeException("Please wait {$wait}s before requesting another code.");
        }

        $otp = $this->generate();
        $this->session->storeVerifyOtp($otp, $expirySeconds);
        return $otp;
    }

    public function verifyEmailOtp(string $submitted): string
    {
        $saved   = $this->session->getVerifyOtp();
        $expires = $this->session->getVerifyOtpExpiry();

        if ($saved === '' || $expires === 0) {
            return 'no_session';
        }

        if (time() > $expires) {
            return 'expired';
        }

        if (!hash_equals($saved, trim($submitted))) {
            return 'invalid';
        }

        return 'ok';
    }
}