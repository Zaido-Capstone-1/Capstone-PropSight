<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../integrations/email_service.php';

if (!function_exists('validateCsrf')) {
    function validateCsrf(string $submitted): bool
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        return $stored !== '' && hash_equals($stored, $submitted);
    }
}

// Service classes 
require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/../core/AuthService.php';
require_once __DIR__ . '/../core/OtpService.php';
require_once __DIR__ . '/../core/AuthEmailService.php';
require_once __DIR__ . '/../core/RegistrationService.php';
require_once __DIR__ . '/../core/PasswordResetService.php';
require_once __DIR__ . '/../core/ServiceContainer.php';

// Assemble and return container 
return new ServiceContainer($conn, $emailService);