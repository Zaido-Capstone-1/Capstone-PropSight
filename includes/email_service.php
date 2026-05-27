<?php
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private array $config;
    private array $fallbackConfigs = [];
    public function __construct()
    {
        $this->config = [
            'host' => MAIL_HOST,
            'port' => (int) MAIL_PORT,
            'username' => MAIL_USERNAME,
            'password' => MAIL_PASSWORD,
            'from_email' => MAIL_FROM_EMAIL,
            'from_name' => MAIL_FROM_NAME,
            'encryption' => MAIL_ENCRYPTION,
        ];
    }

    public function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = '', array $attachments = []): bool
    {
        $args = [$to, $subject, $htmlBody, $textBody, $attachments];
        $errors = [];

        foreach ([$this->config, ...$this->fallbackConfigs] as $config) {
            try {
                return $this->sendWithConfig($config, ...$args);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                error_log("Email send failed [{$config['host']}]: " . $e->getMessage());
            }
        }

        error_log("All email attempts failed: " . implode('; ', $errors));
        $this->storeFailedEmail($to, $subject, $htmlBody, $textBody, $errors);
        return false;
    }

    private function sendWithConfig(array $cfg, string $to, string $subject, string $htmlBody, string $textBody = '', array $attachments = []): bool
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->SMTPSecure = strtolower($cfg['encryption'] ?? 'tls') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions = ['ssl' => ['cafile' => '/etc/ssl/certs/ca-certificates.crt']];

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        if ($textBody)
            $mail->AltBody = $textBody;
        foreach ($attachments as ['path' => $path, 'name' => $name]) {
            $mail->addAttachment($path, $name);
        }
        return $mail->send();
    }
    
    private function storeFailedEmail(string $to, string $subject, string $htmlBody, string $textBody, array $errors): void
    {
        $logFile = __DIR__ . '/../logs/failed_emails.log';
        if (!is_dir(dirname($logFile)))
            mkdir(dirname($logFile), 0755, true);

        $entry = json_encode(['to' => $to, 'subject' => $subject, 'errors' => $errors, 'at' => date('Y-m-d H:i:s')]);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " $entry\n", FILE_APPEND | LOCK_EX);
    }

    public function validateConfiguration(): array
    {
        $issues = [];
        if (empty($this->config['host']))
            $issues[] = "MAIL_HOST is not configured";
        if (empty($this->config['username']))
            $issues[] = "MAIL_USERNAME is not configured";
        if (empty($this->config['password']))
            $issues[] = "MAIL_PASSWORD is not configured";
        if (!filter_var($this->config['from_email'], FILTER_VALIDATE_EMAIL))
            $issues[] = "MAIL_FROM_EMAIL is invalid";
        return $issues;
    }
}

$emailService = new EmailService();