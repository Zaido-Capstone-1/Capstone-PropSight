<?php
/**
 * API: /api/user/messages.php
 * GET  ?action=threads          — list all conversations for this user
 * GET  ?action=conversation&admin_id=X — load messages with admin X
 * POST action=send              — send a message to admin
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_not_blacklisted();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'threads';

    // ── List all threads (distinct admins this user has talked to) ──
    if ($action === 'threads') {
        $res = mysqli_query($conn, "
            SELECT
                IF(m.from_user=$userId, m.to_user, m.from_user) AS admin_id,
                CONCAT(u.first_name,' ',u.last_name)            AS admin_name,
                u.email                                          AS admin_email,
                u.profile_photo                                  AS admin_photo,
                (SELECT body FROM messages
                 WHERE (from_user=$userId AND to_user=admin_id)
                    OR (from_user=admin_id AND to_user=$userId)
                 ORDER BY created_at DESC LIMIT 1)              AS last_body,
                (SELECT created_at FROM messages
                 WHERE (from_user=$userId AND to_user=admin_id)
                    OR (from_user=admin_id AND to_user=$userId)
                 ORDER BY created_at DESC LIMIT 1)              AS last_time,
                (SELECT COUNT(*) FROM messages
                 WHERE from_user=admin_id AND to_user=$userId AND is_read=0) AS unread
            FROM messages m
            JOIN users u ON u.user_id = IF(m.from_user=$userId, m.to_user, m.from_user)
            WHERE (m.from_user=$userId OR m.to_user=$userId)
            GROUP BY admin_id
            ORDER BY last_time DESC
        ");
        $threads = [];
        while ($row = mysqli_fetch_assoc($res)) {
            fmt_dt_row($row);
            $threads[] = $row;
        }

        // Also get list of admins user hasn't messaged yet (so they can start a new thread)
        $adminRes = mysqli_query(
            $conn,
            "SELECT user_id, first_name, last_name, email, profile_photo FROM users WHERE role='admin' AND is_active=1 ORDER BY first_name"
        );
        $admins = [];
        while ($a = mysqli_fetch_assoc($adminRes))
            $admins[] = $a;

        echo json_encode(['success' => true, 'threads' => $threads, 'admins' => $admins]);
        exit;
    }

    // ── Load full conversation with a specific admin ──
    if ($action === 'conversation') {
        $adminId = (int) ($_GET['admin_id'] ?? 0);
        if (!$adminId) {
            echo json_encode(['success' => false, 'message' => 'admin_id required']);
            exit;
        }

        // Mark messages from admin to user as read
        $markStmt = $conn->prepare(
            'UPDATE messages SET is_read = 1 WHERE from_user = ? AND to_user = ? AND is_read = 0'
        );
        $markStmt->bind_param('ii', $adminId, $userId);
        $markStmt->execute();
        $markStmt->close();

        $convStmt = $conn->prepare("
            SELECT m.*,
                   CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                   p.body AS parent_body,
                   CONCAT(pu.first_name,' ',pu.last_name) AS parent_sender_name
            FROM messages m
            JOIN users u ON u.user_id = m.from_user
            LEFT JOIN messages p ON p.message_id = m.parent_id
            LEFT JOIN users pu ON pu.user_id = p.from_user
            WHERE (m.from_user=? AND m.to_user=?)
               OR (m.from_user=? AND m.to_user=?)
            ORDER BY m.created_at ASC
        ");
        $convStmt->bind_param('iiii', $userId, $adminId, $adminId, $userId);
        $convStmt->execute();
        $res = $convStmt->get_result();
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) {
            fmt_dt_row($row);
            $msgs[] = $row;
        }
        $convStmt->close();

        $adminStmt = $conn->prepare(
            'SELECT user_id, first_name, last_name, email, profile_photo FROM users WHERE user_id = ? LIMIT 1'
        );
        $adminStmt->bind_param('i', $adminId);
        $adminStmt->execute();
        $adminRow = $adminStmt->get_result()->fetch_assoc();
        $adminStmt->close();

        echo json_encode(['success' => true, 'messages' => $msgs, 'admin' => $adminRow]);
        exit;
    }

    // ── Poll for new messages since a timestamp (real-time) ──
    if ($action === 'poll') {
        $adminId = (int) ($_GET['admin_id'] ?? 0);
        $since = trim($_GET['since'] ?? '');
        if (!$adminId || !$since || !strtotime($since)) {
            echo json_encode(['success' => false, 'message' => 'admin_id and since required']);
            exit;
        }

        // Mark new ones as read
        $markStmt = $conn->prepare(
            'UPDATE messages SET is_read=1 WHERE from_user=? AND to_user=? AND is_read=0'
        );
        $markStmt->bind_param('ii', $adminId, $userId);
        $markStmt->execute();
        $markStmt->close();

        $pollStmt = $conn->prepare("
            SELECT m.*,
                   CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                   p.body AS parent_body,
                   CONCAT(pu.first_name,' ',pu.last_name) AS parent_sender_name
            FROM messages m
            JOIN users u ON u.user_id = m.from_user
            LEFT JOIN messages p ON p.message_id = m.parent_id
            LEFT JOIN users pu ON pu.user_id = p.from_user
            WHERE ((m.from_user=? AND m.to_user=?)
                OR (m.from_user=? AND m.to_user=?))
              AND m.created_at > ?
            ORDER BY m.created_at ASC
        ");
        $pollStmt->bind_param('iiiis', $userId, $adminId, $adminId, $userId, $since);
        $pollStmt->execute();
        $res = $pollStmt->get_result();
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) {
            fmt_dt_row($row);
            $msgs[] = $row;
        }
        $pollStmt->close();

        echo json_encode(['success' => true, 'messages' => $msgs, 'ts' => gmdate('Y-m-d\TH:i:s') . '+00:00']);
        exit;
    }

    // ── Mark messages from an admin as read ─────────────────
    if ($action === 'mark_read') {
        $adminId = (int) ($_GET['admin_id'] ?? 0);
        if (!$adminId) {
            echo json_encode(['success' => false, 'message' => 'admin_id required']);
            exit;
        }
        $markStmt = $conn->prepare(
            'UPDATE messages SET is_read = 1 WHERE from_user = ? AND to_user = ? AND is_read = 0'
        );
        $markStmt->bind_param('ii', $adminId, $userId);
        $markStmt->execute();
        $markStmt->close();
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Check if MY sent messages have been read ─────────────
    if ($action === 'check_seen') {
        $adminId = (int) ($_GET['admin_id'] ?? 0);
        if (!$adminId) {
            echo json_encode(['success' => false]);
            exit;
        }
        // Get the last message sent BY this user TO admin, and whether it's been read
        $stmt = $conn->prepare('
        SELECT message_id, is_read 
        FROM messages 
        WHERE from_user = ? AND to_user = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ');
        $stmt->bind_param('ii', $userId, $adminId);
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
    require_verified_user_action(true);
    require_csrf_token(true);
    $action = $_POST['action'] ?? 'send';

    if ($action === 'send') {
        $toAdmin = (int) ($_POST['to_admin'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if (!$toAdmin || (!$body && empty($_FILES['attachment']['name']))) {
            echo json_encode(['success' => false, 'message' => 'Recipient and message or attachment required.']);
            exit;
        }

        // Verify target is actually an admin
        $adminCheckStmt = $conn->prepare(
            "SELECT user_id FROM users WHERE user_id = ? AND role = 'admin' AND is_active = 1 LIMIT 1"
        );
        $adminCheckStmt->bind_param('i', $toAdmin);
        $adminCheckStmt->execute();
        $adminCheck = $adminCheckStmt->get_result()->fetch_assoc();
        $adminCheckStmt->close();
        if (!$adminCheck) {
            echo json_encode(['success' => false, 'message' => 'Invalid recipient.']);
            exit;
        }

        // Handle file attachment
        $attachmentUrl = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/uploads/messages/';
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
                $attachmentUrl = 'assets/uploads/messages/' . $filename;
            }
        }

        if (!$body)
            $body = '📎 Attachment';

        $replyTo = isset($_POST['reply_to']) ? (int) $_POST['reply_to'] : null;
        if ($replyTo) {
            $insStmt = $conn->prepare(
                'INSERT INTO messages (from_user, to_user, subject, body, attachment_url, parent_id) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insStmt->bind_param('iisssi', $userId, $toAdmin, $subject, $body, $attachmentUrl, $replyTo);
        } else {
            $insStmt = $conn->prepare(
                'INSERT INTO messages (from_user, to_user, subject, body, attachment_url) VALUES (?, ?, ?, ?, ?)'
            );
            $insStmt->bind_param('iisss', $userId, $toAdmin, $subject, $body, $attachmentUrl);
        }

        if ($insStmt->execute()) {
            $newId = $insStmt->insert_id;
            $now = gmdate('Y-m-d\TH:i:s') . '+00:00';
            $insStmt->close();

            // Notify admin
            $uStmt = $conn->prepare('SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1');
            $uStmt->bind_param('i', $userId);
            $uStmt->execute();
            $uRow = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
            $senderName = trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? ''));
            $bodyPreview = mb_strimwidth($body, 0, 100, '...');
            $notifTitle = 'New message from ' . $senderName;
            $notifLink = 'pages/admin/messages.php';
            $notifStmt = $conn->prepare(
                "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'message', ?, ?, ?)"
            );
            $notifStmt->bind_param('isss', $toAdmin, $notifTitle, $bodyPreview, $notifLink);
            $notifStmt->execute();
            $notifStmt->close();
            require_once __DIR__ . '/../../includes/admin_notif_helpers.php';
            upsert_notif(
                $conn,
                $toAdmin,
                'message',
                'msg-' . $newId,
                $notifTitle . ': ' . mb_substr($bodyPreview, 0, 80),
                'messages.php',
                gmdate('Y-m-d H:i:s')
            );

            echo json_encode([
                'success' => true,
                'message' => 'Message sent.',
                'message_id' => $newId,
                'ts' => $now,
            ]);
        } else {
            $insStmt->close();
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
        $stmt->bind_param('ii', $messageId, $userId);
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