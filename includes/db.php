<?php
require_once __DIR__ . '/../config.php';

// Enable comprehensive error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database connection with retry mechanism and better error handling
function createDatabaseConnection() {
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
?>