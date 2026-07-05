<?php
/**
 * API: /endpoints/user/notifications.php
 * GET  — list notifications for current user
 * POST — mark read / mark all read
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $unread = isset($_GET['unread_only']);

    $where = "user_id=$userId";
    if ($unread)
        $where .= ' AND is_read=0';

    $res = mysqli_query(
        $conn,
        "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
    );
    $notifs = [];
    while ($row = mysqli_fetch_assoc($res)) {
        fmt_dt_row($row);
        $notifs[] = $row;
    }

    $unreadCount = (int) (mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM notifications WHERE user_id=$userId AND is_read=0"
    ))['c'] ?? 0);

    $total = (int) (mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM notifications WHERE $where"
    ))['c'] ?? 0);

    echo json_encode([
        'success' => true,
        'notifications' => $notifs,
        'unread_count' => $unreadCount,
        'total' => $total,
        'has_more' => ($offset + count($notifs)) < $total,
    ]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token(true);
    $action = $_POST['action'] ?? 'mark_read';

    if ($action === 'mark_read') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID required.']);
            exit;
        }
        mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE id=$id AND user_id=$userId");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'mark_all_read') {
        mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE user_id=$userId");
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read.']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID required.']);
            exit;
        }
        mysqli_query($conn, "DELETE FROM notifications WHERE id=$id AND user_id=$userId");
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}