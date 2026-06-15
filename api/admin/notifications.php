<?php
/**
 * API: /api/admin/notifications.php
 * Manages admin_notifications table.
 *
 * GET  ?action=list&offset=0&limit=10  — paginated notifications (read+unread) for this admin
 * POST action=mark_read  id=N  — mark one notification as read
 * POST action=mark_all_read    — mark all as read for this admin
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_notif_helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$adminId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

/* ── GET ─────────────────────────────────────────────────────────────────── */
if ($method === 'GET') {
    sync_notifications($conn, $adminId);

    $limit  = (int) ($_GET['limit'] ?? 20);
    $offset = (int) ($_GET['offset'] ?? 0);
    if ($limit  <= 0 || $limit  > 50) $limit  = 20;
    if ($offset < 0) $offset = 0;

    $res = mysqli_query(
        $conn,
        "SELECT id, type, ref_id, text, path, ts, is_read
         FROM admin_notifications
         WHERE admin_id = $adminId
         ORDER BY ts DESC LIMIT $limit OFFSET $offset"
    );
    $notifs = [];
    while ($row = mysqli_fetch_assoc($res)) {
        fmt_dt_row($row);
        $notifs[] = $row;
    }

    $count = (int) (mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin_notifications WHERE admin_id=$adminId AND is_read=0")
    )['c'] ?? 0);

    $total = (int) (mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin_notifications WHERE admin_id=$adminId")
    )['c'] ?? 0);

    echo json_encode([
        'success' => true,
        'notifications' => $notifs,
        'unread_count' => $count,
        'total' => $total,
        'has_more' => ($offset + count($notifs)) < $total,
    ]);
    exit;
}

/* ── POST ────────────────────────────────────────────────────────────────── */
if ($method === 'POST') {
    // No CSRF check — sendBeacon doesn't guarantee session cookie delivery.
    // Security: already protected by admin session check above + writes only
    // to admin_notifications rows owned by the logged-in admin.
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID required.']);
            exit;
        }
        mysqli_query(
            $conn,
            "UPDATE admin_notifications SET is_read=1 WHERE id=$id AND admin_id=$adminId"
        );
        $count = (int) (mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin_notifications WHERE admin_id=$adminId AND is_read=0")
        )['c'] ?? 0);
        echo json_encode(['success' => true, 'unread_count' => $count]);
        exit;
    }

    if ($action === 'mark_all_read') {
        mysqli_query(
            $conn,
            "UPDATE admin_notifications SET is_read=1 WHERE admin_id=$adminId"
        );
        echo json_encode(['success' => true, 'unread_count' => 0]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}