<?php
// ─── UTC datetime helpers ─────────────────────────────────────────────────
// Defined before DB_LOADED guard so they're always available regardless of
// include order (session.php also includes db.php).
if (!function_exists('fmt_dt')) {
    function fmt_dt(?string $dt): ?string
    {
        if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00')
            return null;
        if (preg_match('/[+-]\d{2}:\d{2}$|Z$/', $dt))
            return $dt;
        return rtrim($dt) . '+00:00';
    }

    if (!defined('DT_KEYS'))
        define('DT_KEYS', [
            'created_at',
            'updated_at',
            'paid_at',
            'approved_at',
            'sent_at',
            'expires_at',
            'blocked_at',
            'last_login',
            'booked_at',
            'payment_date',
            'refund_date',
            'checkin_date',
            'checkout_date',
            'transaction_date',
            'expense_date',
            'request_date',
            'last_time',
            'ts',
        ]);

    function fmt_dt_row(array &$row): void
    {
        foreach (DT_KEYS as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = fmt_dt((string) ($row[$k] ?? ''));
            }
        }
    }

    function fmt_dt_rows(array &$rows): void
    {
        foreach ($rows as &$row) {
            fmt_dt_row($row);
        }
    }
}

if (defined('DB_LOADED'))
    return;
define('DB_LOADED', true);
require_once __DIR__ . '/../config.php';

// Enable comprehensive error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database connection with retry mechanism and better error handling
if (!function_exists('createDatabaseConnection')):
    function createDatabaseConnection()
    {
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

                // Set connection options for better security and performance
                $conn->set_charset("utf8mb4");
                $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
                $conn->options(MYSQLI_OPT_READ_TIMEOUT, 30);

                // Test the connection
                if ($conn->ping()) {
                    return $conn;
                }

            } catch (mysqli_sql_exception $e) {
                $errorMsg = "Database connection attempt $attempt failed: " . $e->getMessage();
                error_log($errorMsg);

                // Log detailed connection info for debugging (remove sensitive data in production)
                $debugInfo = [
                    'server' => DB_SERVER,
                    'database' => DB_NAME,
                    'error_code' => $e->getCode(),
                    'attempt' => $attempt
                ];
                error_log("Database connection debug: " . json_encode($debugInfo));

                if ($attempt === $maxRetries) {
                    // Final attempt failed - provide user-friendly error
                    http_response_code(503); // Service Unavailable
                    die("Database service is temporarily unavailable. Please try again in a few moments.");
                }

                // Wait before retrying
                sleep($retryDelay);
                $retryDelay *= 2; // Exponential backoff
            }
        }

        // This should never be reached, but just in case
        http_response_code(503);
        die("Database connection failed after multiple attempts.");
    }
endif;

// Create the database connection
try {
    $conn = createDatabaseConnection();
} catch (Exception $e) {
    error_log("Unexpected database connection error: " . $e->getMessage());
    http_response_code(503);
    die("Service temporarily unavailable. Please try again later.");
}

// Set timezone to match application
$conn->query("SET time_zone = '+00:00'");
$conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");