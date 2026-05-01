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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
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
        while ($row = mysqli_fetch_assoc($res)) $threads[] = $row;

        // Also get list of admins user hasn't messaged yet (so they can start a new thread)
        $adminRes = mysqli_query($conn,
            "SELECT user_id, first_name, last_name, email FROM users WHERE role='admin' AND is_active=1 ORDER BY first_name");
        $admins = [];
        while ($a = mysqli_fetch_assoc($adminRes)) $admins[] = $a;

        echo json_encode(['success' => true, 'threads' => $threads, 'admins' => $admins]);
        exit;
    }

    // ── Load full conversation with a specific admin ──
    if ($action === 'conversation') {
        $adminId = (int)($_GET['admin_id'] ?? 0);
        if (!$adminId) { echo json_encode(['success'=>false,'message'=>'admin_id required']); exit; }

        // Mark messages from admin to user as read
        $markStmt = $conn->prepare(
            'UPDATE messages SET is_read = 1 WHERE from_user = ? AND to_user = ? AND is_read = 0'
        );
        $markStmt->bind_param('ii', $adminId, $userId);
        $markStmt->execute();
        $markStmt->close();

        $convStmt = $conn->prepare("
            SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name
            FROM messages m
            JOIN users u ON u.user_id = m.from_user
            WHERE (m.from_user=? AND m.to_user=?)
               OR (m.from_user=? AND m.to_user=?)
            ORDER BY m.created_at ASC
        ");
        $convStmt->bind_param('iiii', $userId, $adminId, $adminId, $userId);
        $convStmt->execute();
        $res = $convStmt->get_result();
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) $msgs[] = $row;
        $convStmt->close();

        $adminStmt = $conn->prepare(
            'SELECT user_id, first_name, last_name, email FROM users WHERE user_id = ? LIMIT 1'
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
        $adminId = (int)($_GET['admin_id'] ?? 0);
        $since   = trim($_GET['since'] ?? '');
        if (!$adminId || !$since || !strtotime($since)) {
            echo json_encode(['success'=>false,'message'=>'admin_id and since required']); exit;
        }

        // Mark new ones as read
        $markStmt = $conn->prepare(
            'UPDATE messages SET is_read=1 WHERE from_user=? AND to_user=? AND is_read=0'
        );
        $markStmt->bind_param('ii', $adminId, $userId);
        $markStmt->execute();
        $markStmt->close();

        $pollStmt = $conn->prepare("
            SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name
            FROM messages m
            JOIN users u ON u.user_id = m.from_user
            WHERE ((m.from_user=? AND m.to_user=?)
                OR (m.from_user=? AND m.to_user=?))
              AND m.created_at > ?
            ORDER BY m.created_at ASC
        ");
        $pollStmt->bind_param('iiiis', $userId, $adminId, $adminId, $userId, $since);
        $pollStmt->execute();
        $res = $pollStmt->get_result();
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) $msgs[] = $row;
        $pollStmt->close();

        echo json_encode(['success' => true, 'messages' => $msgs, 'ts' => date('Y-m-d H:i:s')]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($method === 'POST') {
    require_verified_user_action(true);
    require_csrf_token(true);
    $action  = $_POST['action'] ?? 'send';

    if ($action === 'send') {
        $toAdmin = (int)($_POST['to_admin'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');

        if (!$toAdmin || (!$body && empty($_FILES['attachment']['name']))) {
            echo json_encode(['success'=>false,'message'=>'Recipient and message or attachment required.']); exit;
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
            echo json_encode(['success'=>false,'message'=>'Invalid recipient.']); exit;
        }

        // Handle file attachment
        $attachmentUrl = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/uploads/messages/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','txt'];
            $ext     = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($ext, $allowed)) {
                echo json_encode(['success'=>false,'message'=>'File type not allowed. Allowed: jpg, png, gif, pdf, doc, txt.']); exit;
            }
            if ($_FILES['attachment']['size'] > $maxSize) {
                echo json_encode(['success'=>false,'message'=>'File too large. Maximum size is 5MB.']); exit;
            }

            $filename = uniqid('msg_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename)) {
                $attachmentUrl = 'assets/uploads/messages/' . $filename;
            }
        }

        if (!$body) $body = '📎 Attachment';

        $insStmt = $conn->prepare(
            'INSERT INTO messages (from_user, to_user, subject, body, attachment_url) VALUES (?, ?, ?, ?, ?)'
        );
        $insStmt->bind_param('iisss', $userId, $toAdmin, $subject, $body, $attachmentUrl);

        if ($insStmt->execute()) {
            $newId = $insStmt->insert_id;
            $now   = date('Y-m-d H:i:s');
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

            echo json_encode([
                'success'    => true,
                'message'    => 'Message sent.',
                'message_id' => $newId,
                'ts'         => $now,
            ]);
        } else {
            $insStmt->close();
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
