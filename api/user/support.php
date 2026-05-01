<?php
/**
 * API: /api/user/support.php
 * GET  — list user's tickets + messages
 * POST — create ticket, reply to ticket
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
    $action   = $_GET['action'] ?? 'list';
    $ticketId = (int)($_GET['ticket_id'] ?? 0);

    if ($action === 'list') {
        $res = mysqli_query($conn, "
            SELECT t.ticket_id, t.category, t.subject, t.priority, t.status, t.created_at,
                   (SELECT sm.body FROM support_messages sm WHERE sm.ticket_id=t.ticket_id
                    ORDER BY sm.created_at DESC LIMIT 1) AS last_message,
                   (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id=t.ticket_id) AS msg_count
            FROM support_tickets t
            WHERE t.user_id=$userId
            ORDER BY t.created_at DESC
        ");
        $tickets = [];
        while ($row = mysqli_fetch_assoc($res)) $tickets[] = $row;
        echo json_encode(['success' => true, 'tickets' => $tickets]);
        exit;
    }

    if ($action === 'messages' && $ticketId) {
        // Verify ticket belongs to user
        $ticketStmt = $conn->prepare(
            'SELECT * FROM support_tickets WHERE ticket_id = ? AND user_id = ? LIMIT 1'
        );
        $ticketStmt->bind_param('ii', $ticketId, $userId);
        $ticketStmt->execute();
        $ticket = $ticketStmt->get_result()->fetch_assoc();
        $ticketStmt->close();
        if (!$ticket) {
            echo json_encode(['success'=>false,'message'=>'Ticket not found.']); exit;
        }

        $msgStmt = $conn->prepare("
            SELECT sm.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name
            FROM support_messages sm
            JOIN users u ON u.user_id = sm.user_id
            WHERE sm.ticket_id=?
            ORDER BY sm.created_at ASC
        ");
        $msgStmt->bind_param('i', $ticketId);
        $msgStmt->execute();
        $res = $msgStmt->get_result();
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) $msgs[] = $row;
        $msgStmt->close();
        echo json_encode(['success' => true, 'ticket' => $ticket, 'messages' => $msgs]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($method === 'POST') {
    require_verified_user_action(true);
    require_csrf_token(true);
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $category = trim($_POST['category'] ?? 'General');
        $subject  = trim($_POST['subject']  ?? '');
        $body     = trim($_POST['body']     ?? '');
        $priority = in_array($_POST['priority'] ?? 'medium', ['low','medium','high','urgent'])
                    ? $_POST['priority'] : 'medium';

        if (!$subject || !$body) {
            echo json_encode(['success'=>false,'message'=>'Subject and message are required.']); exit;
        }

        // Create ticket
        $ticketStmt = $conn->prepare(
            'INSERT INTO support_tickets (user_id, category, subject, priority) VALUES (?, ?, ?, ?)'
        );
        $ticketStmt->bind_param('isss', $userId, $category, $subject, $priority);
        $ticketStmt->execute();
        $ticketId = $ticketStmt->insert_id;
        $ticketStmt->close();

        // Add first message
        $messageStmt = $conn->prepare(
            'INSERT INTO support_messages (ticket_id, user_id, body, is_admin) VALUES (?, ?, ?, 0)'
        );
        $messageStmt->bind_param('iis', $ticketId, $userId, $body);
        $messageStmt->execute();
        $messageStmt->close();

        // Notify all admins
        $adminsRes = mysqli_query($conn, "SELECT user_id FROM users WHERE role='admin' AND is_active=1");
        while ($admin = mysqli_fetch_assoc($adminsRes)) {
            $aid = (int)$admin['user_id'];
            $title = 'New Support Ticket';
            $link = 'pages/admin/messages.php';
            $notifStmt = $conn->prepare(
                "INSERT INTO notifications (user_id, type, title, body, link)
                 VALUES (?, 'support', ?, ?, ?)"
            );
            $notifStmt->bind_param('isss', $aid, $title, $subject, $link);
            $notifStmt->execute();
            $notifStmt->close();
        }

        echo json_encode([
            'success'   => true,
            'message'   => 'Support ticket created.',
            'ticket_id' => $ticketId,
            'ticket_ref'=> 'TKT-' . str_pad($ticketId, 5, '0', STR_PAD_LEFT),
        ]);
        exit;
    }

    if ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $body     = trim($_POST['body'] ?? '');

        if (!$ticketId || !$body) {
            echo json_encode(['success'=>false,'message'=>'Ticket ID and message required.']); exit;
        }

        // Verify ownership
        $ticketStmt = $conn->prepare(
            'SELECT * FROM support_tickets WHERE ticket_id = ? AND user_id = ? LIMIT 1'
        );
        $ticketStmt->bind_param('ii', $ticketId, $userId);
        $ticketStmt->execute();
        $ticket = $ticketStmt->get_result()->fetch_assoc();
        $ticketStmt->close();
        if (!$ticket) {
            echo json_encode(['success'=>false,'message'=>'Ticket not found.']); exit;
        }

        $replyStmt = $conn->prepare(
            'INSERT INTO support_messages (ticket_id, user_id, body, is_admin) VALUES (?, ?, ?, 0)'
        );
        $replyStmt->bind_param('iis', $ticketId, $userId, $body);
        $replyStmt->execute();
        $newReplyId = $replyStmt->insert_id;
        $replyStmt->close();

        // Re-open if closed
        if (in_array($ticket['status'], ['resolved','closed'])) {
            $reopenStmt = $conn->prepare("UPDATE support_tickets SET status = 'open' WHERE ticket_id = ?");
            $reopenStmt->bind_param('i', $ticketId);
            $reopenStmt->execute();
            $reopenStmt->close();
        }

        echo json_encode(['success'=>true,'message'=>'Reply sent.','id'=>$newReplyId]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
