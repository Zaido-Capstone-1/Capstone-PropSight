<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $where = "WHERE u.role='user'";
    if ($search) {
        $sq = mysqli_real_escape_string($conn, $search);
        $where .= " AND (u.first_name LIKE '%$sq%' OR u.last_name LIKE '%$sq%' OR u.email LIKE '%$sq%' OR u.phone LIKE '%$sq%')";
    }

    $res = mysqli_query($conn, "
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone,
               u.created_at, u.is_blacklisted, u.is_active, u.profile_photo,
               COUNT(DISTINCT b.booking_id)     AS total_stays,
               COALESCE(SUM(b.total_amount), 0) AS total_spent,
               (SELECT COALESCE(NULLIF(TRIM(un.unit_name), ''), CONCAT(p2.property_name, ' — ', un.unit_number))
                FROM bookings bx
                JOIN units un ON un.unit_id = bx.unit_id
                LEFT JOIN properties p2 ON p2.property_id = un.property_id
                WHERE bx.user_id = u.user_id
                  AND bx.status IN ('confirmed', 'active', 'completed')
                ORDER BY bx.checkin_date DESC LIMIT 1
               ) AS current_unit
        FROM users u
        LEFT JOIN bookings b ON b.user_id = u.user_id AND b.status NOT IN ('cancelled')
        $where
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
    ");
    $guests = [];
    while ($row = mysqli_fetch_assoc($res))
        $guests[] = $row;

    $stats = [
        'total' => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user'"))['c'] ?? 0),
        'active' => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_active=1"))['c'] ?? 0),
        'blacklisted' => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_blacklisted=1"))['c'] ?? 0),
        'new_month' => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"))['c'] ?? 0),
    ];

    echo json_encode(['success' => true, 'guests' => $guests, 'stats' => $stats, 'count' => count($guests)]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';
    $uid = (int) ($_POST['user_id'] ?? 0);

    if (!$uid) {
        echo json_encode(['success' => false, 'message' => 'user_id required.']);
        exit;
    }

    if ($action === 'blacklist') {
        $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
        if (mysqli_query($conn, "UPDATE users SET is_blacklisted=1, is_active=0 WHERE user_id=$uid AND role='user'")) {
            mysqli_query($conn, "INSERT INTO activity_logs (user_id, action, module) VALUES ({$_SESSION['user_id']}, 'Blacklisted user #$uid: $reason', 'guests')");
            echo json_encode(['success' => true, 'message' => 'User has been blocked.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($action === 'unblacklist') {
        if (mysqli_query($conn, "UPDATE users SET is_blacklisted=0, is_active=1 WHERE user_id=$uid")) {
            mysqli_query($conn, "INSERT INTO activity_logs (user_id, action, module) VALUES ({$_SESSION['user_id']}, 'Unblacklisted user #$uid', 'guests')");

            mysqli_query($conn, "UPDATE users SET is_blacklisted=0, is_active=1 WHERE user_id=$uid");

            echo json_encode(['success' => true, 'message' => 'User has been unblocked and account reactivated.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($action === 'deactivate') {
        if (mysqli_query($conn, "UPDATE users SET is_active=0 WHERE user_id=$uid")) {
            echo json_encode(['success' => true, 'message' => 'Account deactivated.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}