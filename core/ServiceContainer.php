<?php

declare(strict_types=1);

class ServiceContainer
{
    private \mysqli $db;
    private object $mailer; // EmailService

    private ?SessionManager $session = null;
    private ?AuthService $auth = null;
    private ?OtpService $otp = null;
    private ?AuthEmailService $authEmail = null;
    private ?RegistrationService $registration = null;
    private ?PasswordResetService $passwordReset = null;

    public function __construct(\mysqli $db, object $mailer)
    {
        $this->db = $db;
        $this->mailer = $mailer;
    }


    public function session(): SessionManager
    {
        return $this->session ??= new SessionManager();
    }

    public function auth(): AuthService
    {
        return $this->auth ??= new AuthService($this->db);
    }

    public function otp(): OtpService
    {
        return $this->otp ??= new OtpService($this->session());
    }

    public function authEmail(): AuthEmailService
    {
        return $this->authEmail ??= new AuthEmailService($this->mailer);
    }

    public function registration(): RegistrationService
    {
        return $this->registration ??= new RegistrationService($this->db);
    }

    public function passwordReset(): PasswordResetService
    {
        return $this->passwordReset ??= new PasswordResetService($this->db);
    }

    // Raw DB access

    public function db(): \mysqli
    {
        return $this->db;
    }
}