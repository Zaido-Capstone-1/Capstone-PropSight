<?php
/**
 * API: /api/messages.php  (admin-side)
 * GET  ?action=threads              — list all user conversations
 * GET  ?action=conversation&user_id=X — load conversation with user X
 * GET  ?action=poll&user_id=X&since=TS — poll for new messages
 * GET  ?action=users                — list all active users
 * POST action=send                  — send message to a user
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$adminId = (int) $_SESSION['user_id'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'threads';

    // ── List threads ──────────────────────────────────────────
    if ($action === 'threads') {
        $res = mysqli_query($conn, "
            SELECT
                IF(m.from_user=$adminId, m.to_user, m.from_user)   AS other_id,
                CONCAT(u.first_name,' ',u.last_name)                AS other_name,
                u.email                                              AS other_email,
                (SELECT body FROM messages
                 WHERE (from_user=$adminId AND to_user=IF(m.from_user=$adminId,m.to_user,m.from_user))
                    OR (from_user=IF(m.from_user=$adminId,m.to_user,m.from_user) AND to_user=$adminId)
                 ORDER BY created_at DESC LIMIT 1)                  AS last_body,
                (SELECT created_at FROM messages
                 WHERE (from_user=$adminId AND to_user=IF(m.from_user=$adminId,m.to_user,m.from_user))
                    OR (from_user=IF(m.from_user=$adminId,m.to_user,m.from_user) AND to_user=$adminId)
                 ORDER BY created_at DESC LIMIT 1)                  AS last_time,
                (SELECT COUNT(*) FROM messages
                 WHERE from_user=IF(m.from_user=$adminId,m.to_user,m.from_user)
                   AND to_user=$adminId AND is_read=0)               AS unread
            FROM messages m
            JOIN users u ON u.user_id = IF(m.from_user=$adminId, m.to_user, m.from_user)
            WHERE (m.from_user=$adminId OR m.to_user=$adminId)
            GROUP BY other_id
            ORDER BY last_time DESC
        ");
        $threads = [];
        while ($row = mysqli_fetch_assoc($res))
            $threads[] = $row;
        echo json_encode(['success' => true, 'threads' => $threads]);
        exit;
    }

    // ── Single conversation ───────────────────────────────────
    if ($action === 'conversation') {
        $userId = (int) ($_GET['user_id'] ?? 0);
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'user_id required']);
            exit;
        }

        // Mark as read
        mysqli_query(
            $conn,
            "UPDATE messages SET is_read=1
             WHERE from_user=$userId AND to_user=$adminId AND is_read=0"
        );

        $res = mysqli_query($conn, "
            SELECT m.*,
                   CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                   p.body AS parent_body,
                   CONCAT(pu.first_name,' ',pu.last_name) AS parent_sender_name
            FROM messages m
            JOIN users u ON u.user_id = m.from_user
            LEFT JOIN messages p ON p.message_id = m.parent_id
            LEFT JOIN users pu ON pu.user_id = p.from_user
            WHERE (m.from_user=$adminId AND m.to_user=$userId)
               OR (m.from_user=$userId  AND m.to_user=$adminId)
            ORDER BY m.created_at ASC
        ");
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) {
            fmt_dt_row($row);
            $msgs[] = $row;
        }

        $userRow = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT user_id, first_name, last_name, email FROM users WHERE user_id=$userId LIMIT 1"
        ));

        echo json_encode(['success' => true, 'messages' => $msgs, 'user' => $userRow]);
        exit;
    }

    // ── Poll for new messages since a timestamp ───────────────
    if ($action === 'poll') {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $since = trim($_GET['since'] ?? '');
        if (!$userId || !$since || !strtotime($since)) {
            echo json_encode(['success' => false, 'message' => 'user_id and since required']);
            exit;
        }
        $sinceEsc = mysqli_real_escape_string($conn, $since);

        // Mark newly arrived messages as read
        mysqli_query(
            $conn,
            "UPDATE messages SET is_read=1
             WHERE from_user=$userId AND to_user=$adminId AND is_read=0"
        );

        $res = mysqli_query($conn, "
            SELECT m.*,
                   CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                   p.body AS parent_body,
                   CONCAT(pu.first_name,' ',pu.last_name) AS parent_sender_name
            FROM messages m
            JOIN users u ON u.user_id = m.from_user
            LEFT JOIN messages p ON p.message_id = m.parent_id
            LEFT JOIN users pu ON pu.user_id = p.from_user
            WHERE ((m.from_user=$adminId AND m.to_user=$userId)
                OR (m.from_user=$userId  AND m.to_user=$adminId))
              AND m.created_at > '$sinceEsc'
            ORDER BY m.created_at ASC
        ");
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) {
            fmt_dt_row($row);
            $msgs[] = $row;
        }

        echo json_encode(['success' => true, 'messages' => $msgs, 'ts' => gmdate('Y-m-d H:i:s')]);
        exit;
    }

    // ── List users for new-message picker ─────────────────────
    if ($action === 'users') {
        $res = mysqli_query(
            $conn,
            "SELECT user_id, first_name, last_name, email
             FROM users WHERE role='user' AND is_active=1
             ORDER BY first_name, last_name"
        );
        $users = [];
        while ($row = mysqli_fetch_assoc($res))
            $users[] = $row;
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }

    // ── Mark messages from a user as read ────────────────────
    if ($action === 'mark_read') {
        $userId = (int) ($_GET['user_id'] ?? 0);
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'user_id required']);
            exit;
        }
        mysqli_query(
            $conn,
            "UPDATE messages SET is_read=1
             WHERE from_user=$userId AND to_user=$adminId AND is_read=0"
        );
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Check if MY sent messages have been read ─────────────
    if ($action === 'check_seen') {
        $userId = (int) ($_GET['user_id'] ?? 0);
        if (!$userId) {
            echo json_encode(['success' => false]);
            exit;
        }
        $stmt = $conn->prepare('
        SELECT message_id, is_read 
        FROM messages 
        WHERE from_user = ? AND to_user = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ');
        $stmt->bind_param('ii', $adminId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'message_id' => $row['message_id'] ?? null, 'is_read' => (bool) ($row['is_read'] ?? false)]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? 'send';

    if ($action === 'mark_all_read') {
        $ok = mysqli_query(
            $conn,
            "UPDATE messages
             SET is_read = 1
             WHERE to_user = $adminId AND is_read = 0"
        );
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'All messages marked as read.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($action === 'send') {
        $toUser = (int) ($_POST['to_user'] ?? 0);
        $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));
        $body = mysqli_real_escape_string($conn, trim($_POST['body'] ?? ''));

        if (!$toUser || (!$body && empty($_FILES['attachment']['name']))) {
            echo json_encode(['success' => false, 'message' => 'Recipient and message or attachment required.']);
            exit;
        }

        // Handle file attachment
        $attachmentUrl = 'NULL';
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/uploads/messages/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);

            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt'];
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: jpg, png, gif, pdf, doc, txt.']);
                exit;
            }
            if ($_FILES['attachment']['size'] > $maxSize) {
                echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                exit;
            }

            $filename = uniqid('msg_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename)) {
                $urlPath = 'assets/uploads/messages/' . $filename;
                $attachmentUrl = "'" . mysqli_real_escape_string($conn, $urlPath) . "'";
            }
        }

        if (!$body)
            $body = mysqli_real_escape_string($conn, '📎 Attachment');

        $replyTo = isset($_POST['reply_to']) ? (int) $_POST['reply_to'] : 0;
        if ($replyTo) {
            $sql = "INSERT INTO messages (from_user, to_user, subject, body, attachment_url, parent_id)
                    VALUES ($adminId, $toUser, '$subject', '$body', $attachmentUrl, $replyTo)";
        } else {
            $sql = "INSERT INTO messages (from_user, to_user, subject, body, attachment_url)
                    VALUES ($adminId, $toUser, '$subject', '$body', $attachmentUrl)";
        }

        if (mysqli_query($conn, $sql)) {
            $newId = mysqli_insert_id($conn);
            $now = gmdate('Y-m-d\\TH:i:s') . '+00:00';
            $bodyPreview = mb_strimwidth(trim($_POST['body'] ?? '📎 Attachment'), 0, 100, '...');
            $bodyEsc = mysqli_real_escape_string($conn, $bodyPreview);

            // Notify the user
            mysqli_query(
                $conn,
                "INSERT INTO notifications (user_id, type, title, body, link)
                 VALUES ($toUser, 'message', 'New message from admin', '$bodyEsc', 'pages/user/messages.php')"
            );

            echo json_encode(['success' => true, 'message' => 'Message sent.', 'message_id' => $newId, 'ts' => $now]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($action === 'unsend') {
        $messageId = (int) ($_POST['message_id'] ?? 0);
        if (!$messageId) {
            echo json_encode(['success' => false, 'message' => 'message_id required']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM messages WHERE message_id = ? AND from_user = ?');
        $stmt->bind_param('ii', $messageId, $adminId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Message not found or not yours.']);
        }
        $stmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}