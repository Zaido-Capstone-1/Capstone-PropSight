<?php
/**
 * endpoints/admin/backup.php
 * POST action=generate  — generate and save a backup server-side
 * GET  action=list       — list all saved backups
 * GET  action=download&file=X — download a specific backup
 * POST action=restore    — restore from a saved backup file
 * POST action=delete     — delete a saved backup
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

define('BACKUP_DIR', __DIR__ . '/../../storage/backups/');

if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── List backups ──────────────────────────────────────────────────────────
if ($action === 'list') {
    $files = glob(BACKUP_DIR . '*.sql');
    $backups = [];
    if ($files) {
        foreach ($files as $f) {
            $name = basename($f);
            $size = filesize($f);
            $backups[] = [
                'filename' => $name,
                'size_kb' => round($size / 1024, 1),
                'size_mb' => round($size / 1024 / 1024, 2),
                'created' => gmdate('Y-m-d H:i:s', filemtime($f)) . '+00:00',
            ];
        }
        // Sort newest first
        usort($backups, fn($a, $b) => strcmp($b['created'], $a['created']));
    }
    echo json_encode(['success' => true, 'backups' => $backups]);
    exit;
}

// ── Generate backup ───────────────────────────────────────────────────────
if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token(true);
    global $conn;

    $filename = 'backup_' . gmdate('Ymd_His') . '.sql';
    $filepath = BACKUP_DIR . $filename;

    $output = "-- PropSight Database Backup\n";
    $output .= "-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n";
    $output .= "-- Database: " . DB_NAME . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = [];
    $res = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($res))
        $tables[] = $row[0];

    foreach ($tables as $table) {
        $tableEsc = mysqli_real_escape_string($conn, $table);

        $output .= "-- Table: `$table`\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $createRes = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$tableEsc`"));
        $output .= $createRes[1] . ";\n\n";

        $dataRes = mysqli_query($conn, "SELECT * FROM `$tableEsc`");
        if (!$dataRes || mysqli_num_rows($dataRes) === 0)
            continue;

        $cols = [];
        for ($i = 0; $i < mysqli_num_fields($dataRes); $i++) {
            $field = mysqli_fetch_field_direct($dataRes, $i);
            $cols[] = '`' . $field->name . '`';
        }
        $colList = implode(', ', $cols);

        $rows = [];
        while ($row = mysqli_fetch_row($dataRes)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'", $row);
            $rows[] = '(' . implode(', ', $vals) . ')';
            if (count($rows) >= 500) {
                $output .= "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $rows) . ";\n";
                $rows = [];
            }
        }
        if ($rows) {
            $output .= "INSERT INTO `$table` ($colList) VALUES\n" . implode(",\n", $rows) . ";\n";
        }
        $output .= "\n";
    }

    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

    if (file_put_contents($filepath, $output) === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to write backup file. Check folder permissions.']);
        exit;
    }

    $size = filesize($filepath);
    echo json_encode([
        'success' => true,
        'message' => 'Backup generated successfully.',
        'filename' => $filename,
        'size_kb' => round($size / 1024, 1),
        'size_mb' => round($size / 1024 / 1024, 2),
        'created' => gmdate('Y-m-d H:i:s') . '+00:00',
    ]);
    exit;
}

// ── Download a backup ─────────────────────────────────────────────────────
if ($action === 'download') {
    $file = basename($_GET['file'] ?? '');
    if (!$file || !preg_match('/^backup_\d{8}_\d{6}\.sql$/', $file)) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename.']);
        exit;
    }
    $filepath = BACKUP_DIR . $file;
    if (!file_exists($filepath)) {
        echo json_encode(['success' => false, 'message' => 'File not found.']);
        exit;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-store');
    readfile($filepath);
    exit;
}

// ── Restore from saved backup ─────────────────────────────────────────────
if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token(true);

    $file = basename($_POST['file'] ?? '');
    if (!$file || !preg_match('/^backup_\d{8}_\d{6}\.sql$/', $file)) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename.']);
        exit;
    }
    $filepath = BACKUP_DIR . $file;
    if (!file_exists($filepath)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found.']);
        exit;
    }

    $sql = file_get_contents($filepath);
    if (!$sql) {
        echo json_encode(['success' => false, 'message' => 'Could not read backup file.']);
        exit;
    }

    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
    $conn->multi_query($sql);

    $errors = [];
    $count = 0;
    do {
        $count++;
        if ($conn->error)
            $errors[] = $conn->error;
    } while ($conn->more_results() && $conn->next_result());
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");

    if ($errors) {
        echo json_encode(['success' => false, 'message' => 'Restore completed with errors: ' . implode('; ', array_slice($errors, 0, 3))]);
    } else {
        echo json_encode(['success' => true, 'message' => "Restore complete. $count statements executed."]);
    }
    exit;
}

// ── Delete a backup ───────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token(true);

    $file = basename($_POST['file'] ?? '');
    if (!$file || !preg_match('/^backup_\d{8}_\d{6}\.sql$/', $file)) {
        echo json_encode(['success' => false, 'message' => 'Invalid filename.']);
        exit;
    }
    $filepath = BACKUP_DIR . $file;
    if (!file_exists($filepath)) {
        echo json_encode(['success' => false, 'message' => 'File not found.']);
        exit;
    }

    unlink($filepath);
    echo json_encode(['success' => true, 'message' => 'Backup deleted.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);