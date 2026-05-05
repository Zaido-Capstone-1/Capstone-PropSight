<?php
/**
 * API: /api/admin/support.php
 * GET  ?action=messages&ticket_id=X  — load ticket messages
 * POST action=admin_reply            — send admin reply
 * POST action=update_status          — update ticket status
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$adminId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────────
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $ticketId = (int) ($_GET['ticket_id'] ?? 0);

    if ($action === 'messages' && $ticketId) {
        $stmt = $conn->prepare("
            SELECT sm.id, sm.body, sm.is_admin, sm.created_at,
                   CONCAT(u.first_name, ' ', u.last_name) AS sender_name
            FROM support_messages sm
            JOIN users u ON u.user_id = sm.user_id
            WHERE sm.ticket_id = ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $res = $stmt->get_result();
        $msgs = [];
        while ($row = $res->fetch_assoc())
            $msgs[] = $row;
        $stmt->close();

        echo json_encode(['success' => true, 'messages' => $msgs]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── POST ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    require_csrf_token();

    $action = $_POST['action'] ?? '';
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);

    if (!$ticketId) {
        echo json_encode(['success' => false, 'message' => 'Invalid ticket.']);
        exit;
    }

    // Verify ticket exists
    $chk = $conn->prepare('SELECT ticket_id, user_id, status FROM support_tickets WHERE ticket_id = ? LIMIT 1');
    $chk->bind_param('i', $ticketId);
    $chk->execute();
    $ticket = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    // ── Admin reply ──────────────────────────────────────────────
    if ($action === 'admin_reply') {
        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            echo json_encode(['success' => false, 'message' => 'Reply cannot be empty.']);
            exit;
        }

        // is_admin=1, user_id = admin's user_id
        $stmt = $conn->prepare(
            'INSERT INTO support_messages (ticket_id, user_id, is_admin, body) VALUES (?, ?, 1, ?)'
        );
        $stmt->bind_param('iis', $ticketId, $adminId, $body);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
            exit;
        }

        // Auto set in_progress if still open
        if ($ticket['status'] === 'open') {
            $upd = $conn->prepare(
                "UPDATE support_tickets SET status='in_progress' WHERE ticket_id=?"
            );
            $upd->bind_param('i', $ticketId);
            $upd->execute();
            $upd->close();
        }

        // Notify user
        $userId = (int) $ticket['user_id'];
        $notifBody = mb_strimwidth($body, 0, 100, '…');
        $notifStmt = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, body, link)
             VALUES (?, 'support', 'Admin replied to your ticket', ?, 'pages/user/support.php')"
        );
        $notifStmt->bind_param('is', $userId, $notifBody);
        $notifStmt->execute();
        $notifStmt->close();

        echo json_encode(['success' => true]);
        exit;
    }

    // ── Update status ────────────────────────────────────────────
    if ($action === 'update_status') {
        $allowed = ['open', 'in_progress', 'resolved', 'closed'];
        $status = $_POST['status'] ?? '';

        if (!in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            exit;
        }

        $stmt = $conn->prepare('UPDATE support_tickets SET status=? WHERE ticket_id=?');
        $stmt->bind_param('si', $status, $ticketId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}