<?php
class SecureFileUpload {
    private array $allowedMimeTypes;
    private array $allowedExtensions;
    private int $maxFileSize;
    private string $uploadDir;
    private bool $createUniqueNames;

    public function __construct(array $config = []) {
        $this->allowedMimeTypes = $config['allowedMimeTypes'] ?? [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];

        $this->allowedExtensions = array_values($this->allowedMimeTypes);
        $this->maxFileSize = $config['maxFileSize'] ?? 5 * 1024 * 1024;
        $this->uploadDir = $config['uploadDir'] ?? 'uploads/';
        $this->createUniqueNames = $config['createUniqueNames'] ?? true;
    }

    public function processUpload(array $file, string $prefix = ''): array {
        // Validate file array structure
        if (!isset($file['tmp_name']) || !isset($file['name']) || !isset($file['size']) || !isset($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file upload structure'];
        }
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'error' => 'File size exceeds maximum allowed size'];
        }
        // Get MIME type using multiple methods for security
        $detectedMime = $this->getSecureMimeType($file['tmp_name'], $file['name']);
        if (!$detectedMime) {
            return ['success' => false, 'error' => 'Could not determine file type'];
        }
        // Validate MIME type
        if (!isset($this->allowedMimeTypes[$detectedMime])) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }
        // Validate file extension matches MIME type
        $expectedExtension = $this->allowedMimeTypes[$detectedMime];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($fileExtension !== $expectedExtension) {
            return ['success' => false, 'error' => 'File extension does not match file type'];
        }
        // Additional security checks
        if (!$this->isSecureFile($file['tmp_name'], $detectedMime)) {
            return ['success' => false, 'error' => 'File failed security checks'];
        }
        // Generate secure filename
        $filename = $this->generateSecureFilename($file['name'], $prefix, $expectedExtension);

        // Ensure upload directory exists and is secure
        $fullUploadDir = $this->ensureSecureUploadDirectory();

        // Move file to final location
        $destination = $fullUploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file'];
        }

        // Set proper permissions
        chmod($destination, 0644);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $destination,
            'relative_path' => $this->uploadDir . $filename,
            'size' => $file['size'],
            'mime_type' => $detectedMime,
            'extension' => $expectedExtension
        ];
    }

    private function getSecureMimeType(string $filePath, string $originalFilename): ?string {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        $extensionMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ];

        if (isset($extensionMap[$extension])) {
            if (function_exists('mime_content_type')) {
                $contentType = mime_content_type($filePath);
                if ($contentType && $contentType === $extensionMap[$extension]) {
                    return $contentType;
                }
            }
            return $extensionMap[$extension];
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($filePath);
            if ($mime && strpos($mime, '/') !== false) {
                return $mime;
            }
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if ($mime && strpos($mime, '/') !== false) {
                    return $mime;
                }
            }
        }

        return null;
    }

    private function isSecureFile(string $filePath, string $mimeType): bool {
        $isImage = strpos($mimeType, 'image/') === 0;

        if ($isImage) {
            if (function_exists('getimagesize')) {
                $imageInfo = @getimagesize($filePath);
                if ($imageInfo !== false) {
                    if ($imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
                        return false;
                    }
                }
            }
            return true;
        }

        $content = file_get_contents($filePath, false, null, 0, 1024);
        if ($content === false) {
            return false;
        }

        // Check for PHP/ASP tags or other dangerous patterns
        $dangerousPatterns = [
            '<?php', '<?=', '<%', '<script', 'eval(', 'exec(', 'system(',
            'passthru(', 'shell_exec(', 'popen(', 'proc_open('
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                return false;
            }
        }

        if (strpos($content, "\0") !== false) {
            return false;
        }

        return true;
    }

    private function generateSecureFilename(string $originalName, string $prefix, string $extension): string {
        if ($this->createUniqueNames) {
            $uniqueId = bin2hex(random_bytes(8));
            return $prefix . $uniqueId . '_' . time() . '.' . $extension;
        } else {
            $sanitized = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $originalName);
            $sanitized = preg_replace('/\.+/', '.', $sanitized);
            return $prefix . $sanitized;
        }
    }

    private function ensureSecureUploadDirectory(): string {
        $fullPath = __DIR__ . '/../' . $this->uploadDir;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        chmod($fullPath, 0755);

        $htaccessPath = $fullPath . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "Options -Indexes\n";
            $htaccessContent .= "<FilesMatch \"\.(php|php3|php4|php5|phtml|pl|cgi|asp|jsp)$\">\n";
            $htaccessContent .= "  Order Deny,Allow\n";
            $htaccessContent .= "  Deny from all\n";
            $htaccessContent .= "</FilesMatch>\n";
            file_put_contents($htaccessPath, $htaccessContent);
        }

        return $fullPath;
    }

    private function getUploadErrorMessage(int $errorCode): string {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds server upload size limit';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds form size limit';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary upload directory';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    public function cleanupOldFiles(int $maxAgeDays = 30): int {
        $fullPath = __DIR__ . '/../' . $this->uploadDir;
        $deletedCount = 0;

        if (!is_dir($fullPath)) {
            return 0;
        }

        $files = glob($fullPath . '/*');
        $maxAge = time() - ($maxAgeDays * 24 * 60 * 60);

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $maxAge) {
                unlink($file);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}

$secureUpload = new SecureFileUpload();
?>