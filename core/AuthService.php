<?php

declare(strict_types=1);

class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 5;

    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    // User lookup
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // Lockout
    public function handleExpiredLockout(array $user): array
    {
        if (
            $user['is_locked'] &&
            !empty($user['locked_until']) &&
            strtotime($user['locked_until']) <= time()
        ) {
            $stmt = $this->db->prepare(
                'UPDATE users SET is_locked = 0, login_attempts = 0, locked_until = NULL WHERE user_id = ?'
            );
            $stmt->bind_param('i', $user['user_id']);
            $stmt->execute();
            $stmt->close();
            $user['is_locked'] = 0;
        }

        return $user;
    }

    public function recordFailedAttempt(array $user): array
    {
        $attempts   = $user['login_attempts'] + 1;
        $shouldLock = $attempts >= self::MAX_ATTEMPTS;
        $lockedUntil = $shouldLock
            ? date('Y-m-d H:i:s', strtotime('+' . self::LOCKOUT_MINUTES . ' minutes'))
            : null;

        $stmt = $this->db->prepare(
            'UPDATE users
             SET login_attempts = ?, is_locked = ?, last_attempt = NOW(), locked_until = ?
             WHERE user_id = ?'
        );
        $stmt->bind_param('iisi', $attempts, $shouldLock, $lockedUntil, $user['user_id']);
        $stmt->execute();
        $stmt->close();

        return ['locked' => $shouldLock, 'attempts' => $attempts];
    }

    public function resetAttempts(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET login_attempts = 0, is_locked = 0, locked_until = NULL WHERE user_id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Password
    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    // 2FA detection

    public function isTwoFactorEnabled(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT two_factor_enabled FROM user_settings WHERE user_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($row['two_factor_enabled']);
    }

    public function maxAttempts(): int
    {
        return self::MAX_ATTEMPTS;
    }
}