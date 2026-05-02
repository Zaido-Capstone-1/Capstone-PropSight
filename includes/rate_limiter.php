<?php
class RateLimiter {
    private $conn;
    private string $tableName;
    private int $maxRequests;
    private int $timeWindow; // in seconds

    public function __construct(mysqli $conn, string $tableName = 'api_rate_limits', int $maxRequests = 100, int $timeWindow = 3600) {
        $this->conn = $conn;
        $this->tableName = $tableName;
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;

        $this->ensureTableExists();
    }

    /**
     * Check if request is allowed
     */
    public function isAllowed(string $identifier, string $endpoint = 'general'): bool {
        $this->cleanupOldEntries();

        $currentTime = time();
        $windowStart = $currentTime - $this->timeWindow;

        // Get current request count
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as request_count
            FROM {$this->tableName}
            WHERE identifier = ? AND endpoint = ? AND timestamp >= ?
        ");
        $stmt->bind_param('ssi', $identifier, $endpoint, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $requestCount = (int)($row['request_count'] ?? 0);
        $stmt->close();

        if ($requestCount >= $this->maxRequests) {
            return false;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO {$this->tableName} (identifier, endpoint, timestamp, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param('ssis', $identifier, $endpoint, $currentTime, $ipAddress);
        $stmt->execute();
        $stmt->close();

        return true;
    }

    public function getRemainingRequests(string $identifier, string $endpoint = 'general'): int {
        $currentTime = time();
        $windowStart = $currentTime - $this->timeWindow;

        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as request_count
            FROM {$this->tableName}
            WHERE identifier = ? AND endpoint = ? AND timestamp >= ?
        ");
        $stmt->bind_param('ssi', $identifier, $endpoint, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $requestCount = (int)($row['request_count'] ?? 0);
        $stmt->close();

        return max(0, $this->maxRequests - $requestCount);
    }

    public function getTimeUntilReset(string $identifier, string $endpoint = 'general'): int {
        $currentTime = time();
        $windowStart = $currentTime - $this->timeWindow;

        $stmt = $this->conn->prepare("
            SELECT MIN(timestamp) as oldest_request
            FROM {$this->tableName}
            WHERE identifier = ? AND endpoint = ? AND timestamp >= ?
        ");
        $stmt->bind_param('ssi', $identifier, $endpoint, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $oldestRequest = (int)($row['oldest_request'] ?? $currentTime);
        $stmt->close();

        $resetTime = $oldestRequest + $this->timeWindow;
        return max(0, $resetTime - $currentTime);
    }

    private function ensureTableExists(): void {
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                timestamp INT NOT NULL,
                ip_address VARCHAR(45),
                INDEX idx_identifier_endpoint (identifier, endpoint),
                INDEX idx_timestamp (timestamp),
                INDEX idx_cleanup (identifier, endpoint, timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->conn->query($createTableSQL);
    }

    private function cleanupOldEntries(): void {
        $cutoffTime = time() - ($this->timeWindow * 2); // Keep 2x window for analysis

        $stmt = $this->conn->prepare("DELETE FROM {$this->tableName} WHERE timestamp < ?");
        $stmt->bind_param('i', $cutoffTime);
        $stmt->execute();
        $stmt->close();
    }

    public function getHeaders(string $identifier, string $endpoint = 'general'): array {
        return [
            'X-RateLimit-Limit' => $this->maxRequests,
            'X-RateLimit-Remaining' => $this->getRemainingRequests($identifier, $endpoint),
            'X-RateLimit-Reset' => time() + $this->getTimeUntilReset($identifier, $endpoint),
        ];
    }
}

/**
 * Middleware function to apply rate limiting to API endpoints
 */
function applyRateLimit(mysqli $conn, string $endpoint, int $maxRequests = 100, int $timeWindow = 3600): void {
    // Get user identifier (prefer user ID, fallback to IP)
    $identifier = $_SESSION['user_id'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'anonymous');

    $rateLimiter = new RateLimiter($conn, 'api_rate_limits', $maxRequests, $timeWindow);

    if (!$rateLimiter->isAllowed($identifier, $endpoint)) {
        // Rate limit exceeded
        $resetTime = $rateLimiter->getTimeUntilReset($identifier, $endpoint);

        http_response_code(429); // Too Many Requests

        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $resetTime
            ]);
        } else {
            header('Retry-After: ' . $resetTime);
            echo 'Rate limit exceeded. Please try again in ' . ceil($resetTime / 60) . ' minutes.';
        }

        exit;
    }

    // Add rate limit headers
    $headers = $rateLimiter->getHeaders($identifier, $endpoint);
    foreach ($headers as $header => $value) {
        header("$header: $value");
    }
}
?>