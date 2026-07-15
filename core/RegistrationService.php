<?php

declare(strict_types=1);

class RegistrationService
{
    // Blocked disposable-email domains
    private const BLOCKED_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwam.com',
        'sharklasers.com', 'guerrillamailblock.com', 'grr.la', 'guerrillamail.info',
        'spam4.me', 'trashmail.com', 'trashmail.me', 'trashmail.net',
        'dispostable.com', 'yopmail.com', 'yopmail.fr', 'cool.fr.nf',
        'jetable.fr.nf', 'nospam.ze.tc', 'nomail.xl.cx', 'mega.zik.dj',
        'speed.1s.fr', 'courriel.fr.nf', 'moncourrier.fr.nf', 'monemail.fr.nf',
        'monmail.fr.nf', 'fakeinbox.com', 'mailnull.com', 'spamgourmet.com',
        'spamgourmet.net', 'spamgourmet.org', 'maildrop.cc', 'discard.email',
        'spamspot.com', 'spamthisplease.com', 'spamhereplease.com',
        'getnada.com', 'filzmail.com', 'tempr.email', 'mailnesia.com', 'owlpic.com',
    ];

    // Allowed email providers
    private const ALLOWED_DOMAINS = [
        'gmail.com', 'googlemail.com', 'phinmaed.com',
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'hotmail.co.uk', 'hotmail.fr', 'hotmail.de', 'hotmail.it', 'hotmail.es',
        'live.co.uk', 'live.fr', 'live.de', 'live.it', 'live.es', 'live.com.au',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.co.in', 'yahoo.fr', 'yahoo.de',
        'yahoo.it', 'yahoo.es', 'yahoo.com.au', 'yahoo.com.ph',
        'icloud.com', 'me.com', 'mac.com',
        'proton.me', 'protonmail.com', 'protonmail.ch',
        'zoho.com', 'zohomail.com',
        'aol.com', 'aol.co.uk',
        'pldtmydsl.net', 'globe.com.ph', 'smart.com.ph',
        'mail.com', 'email.com',
        'fastmail.com', 'fastmail.fm',
        'tutanota.com', 'tutamail.com', 'tuta.io',
        'gmx.com', 'gmx.net', 'gmx.de',
        'hey.com', 'pm.me',
    ];

    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    // Input validation
    public function validate(array $data): void
    {
        foreach (['first_name', 'last_name', 'email', 'password', 'confirm_password'] as $field) {
            if (($data[$field] ?? '') === '') {
                throw new \InvalidArgumentException('All fields are required!');
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email!');
        }

        $domain = $this->emailDomain($data['email']);

        if (in_array($domain, self::BLOCKED_DOMAINS, true)) {
            throw new \InvalidArgumentException(
                'Disposable or temporary email addresses are not allowed!'
            );
        }

        if (!in_array($domain, self::ALLOWED_DOMAINS, true)) {
            throw new \InvalidArgumentException(
                'Please use a valid email provider (e.g. Gmail, Outlook, Yahoo)!'
            );
        }

        if (!checkdnsrr($domain, 'A') && !checkdnsrr($domain, 'AAAA')) {
            throw new \InvalidArgumentException('Email domain does not appear to exist!');
        }

        if (!checkdnsrr($domain, 'MX')) {
            throw new \InvalidArgumentException('Email domain does not exist or cannot receive emails!');
        }

        if ($data['password'] !== $data['confirm_password']) {
            throw new \InvalidArgumentException('Passwords do not match!');
        }

        if (strlen($data['password']) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters!');
        }

        if (
            !preg_match('/[A-Z]/', $data['password']) ||
            !preg_match('/[0-9]/', $data['password'])
        ) {
            throw new \InvalidArgumentException(
                'Password must contain at least 1 uppercase letter and 1 number!'
            );
        }
    }

    // Duplicate check
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT user_id FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    // Insert new user
    public function create(array $data): int
    {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'INSERT INTO users
                (first_name, last_name, email, password, verification_status)
             VALUES
                (?, ?, ?, ?, \'Not Verified\')'
        );
        $stmt->bind_param(
            'ssss',
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $hash
        );

        if (!$stmt->execute()) {
            error_log('Registration DB error: ' . $stmt->error);
            $stmt->close();
            throw new \RuntimeException('Something went wrong. Please try again.');
        }

        $newUserId = $stmt->insert_id;
        $stmt->close();

        $settings = $this->db->prepare('INSERT IGNORE INTO user_settings (user_id, two_factor_enabled) VALUES (?, DEFAULT)');
        if ($settings) {
            $settings->bind_param('i', $newUserId);
            $settings->execute();
            $settings->close();
        }

        return $newUserId;
    }

    // Verification
    public function markVerified(int $userId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET verification_status = 'Verified' WHERE user_id = ?"
        );
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new \RuntimeException('Could not verify your account. Please try again.');
        }
        $stmt->close();
    }

    // Helpers

    private function emailDomain(string $email): string
    {
        return strtolower(substr(strrchr($email, '@'), 1));
    }
}