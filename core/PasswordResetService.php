<?php

declare(strict_types=1);

/**
 * PasswordResetService
 *
 * Handles the full password-reset lifecycle:
 *   1. Rate-limit check (per-user and per-IP)
 *   2. Token generation and storage
 *   3. Token validation
 *   4. Password update
 */
class PasswordResetService
{
    private const EXPIRY_MINUTES = 30;
    private const RATE_LIMIT_MINUTES = 5;   // min gap between reset emails per user
    private const IP_MAX_PER_HOUR = 10;   // max reset attempts per IP per hour

    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    // User lookup
    public function findActiveUser(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, first_name, verification_status, is_active, role
             FROM users
             WHERE email = ? AND is_active = 1
             LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // Rate limiting
    public function isIpRateLimited(string $ip): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) AS attempts
                 FROM password_reset_attempts
                 WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );
            $stmt->bind_param('s', $ip);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int) ($row['attempts'] ?? 0) >= self::IP_MAX_PER_HOUR;
        } catch (\mysqli_sql_exception) {
            return false;
        }
    }

    /**
     * Record one reset attempt (for IP-based rate limiting).
     * Silently ignores missing table.
     */
    public function recordAttempt(string $email, string $ip, bool $userFound): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO password_reset_attempts (email, ip_address, attempted_at, user_found)
                 VALUES (?, ?, NOW(), ?)'
            );
            $found = $userFound ? 1 : 0;
            $stmt->bind_param('ssi', $email, $ip, $found);
            $stmt->execute();
            $stmt->close();
        } catch (\mysqli_sql_exception) {
            // table may not exist in older installs — non-fatal
        }
    }

    public function isUserRateLimited(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM password_resets
             WHERE user_id = ? AND used = 0
               AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $minutes = self::RATE_LIMIT_MINUTES;
        $stmt->bind_param('ii', $userId, $minutes);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null;
    }

    // Token management
    public function generateToken(int $userId, string $email): string
    {
        // Invalidate old tokens
        $stmt = $this->db->prepare(
            'UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $token = bin2hex(random_bytes(32)); // 64-char hex, cryptographically secure
        $expires = date('Y-m-d H:i:s', time() + self::EXPIRY_MINUTES * 60);

        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, email, token, expires_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isss', $userId, $email, $token, $expires);
        $stmt->execute();
        $stmt->close();

        return $token;
    }

    public function buildResetLink(string $token): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        return rtrim($protocol . '://' . $host . $scriptDir, '/')
            . '/reset_password.php?token=' . urlencode($token);
    }

    // Token validation
    public function validateToken(string $token): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, expires_at, used
             FROM password_resets
             WHERE token = ?
             LIMIT 1'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new \RuntimeException('This reset link is invalid or has already been used.');
        }
        if ((int) $row['used'] === 1) {
            throw new \RuntimeException('This reset link has already been used. Please request a new one.');
        }
        if (strtotime($row['expires_at']) < time()) {
            throw new \RuntimeException('This reset link has expired. Please request a new one.');
        }

        return $row;
    }

    // Password update
    public function applyReset(int $userId, int $resetId, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare(
            "UPDATE users SET password = ? WHERE user_id = ?"
        );
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $this->db->prepare(
            'UPDATE password_resets SET used = 1 WHERE id = ?'
        );
        $stmt->bind_param('i', $resetId);
        $stmt->execute();
        $stmt->close();

        // Clear any existing lockout so the user can log in immediately
        $stmt = $this->db->prepare(
            'UPDATE users SET login_attempts = 0, is_locked = 0, locked_until = NULL WHERE user_id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    public function expiryMinutes(): int
    {
        return self::EXPIRY_MINUTES;
    }
    public function rateLimitMinutes(): int
    {
        return self::RATE_LIMIT_MINUTES;
    }
}