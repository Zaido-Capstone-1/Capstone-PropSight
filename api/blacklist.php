<?php
/**
 * API: /api/blacklist.php
 * GET  — list blacklisted users
 * POST — blacklist / unblacklist / reactivate user
 */
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
    $filter = $_GET['filter'] ?? 'blacklisted'; // blacklisted | all | inactive

    $where = "WHERE u.role='user'";
    if ($filter === 'blacklisted')
        $where .= " AND u.is_blacklisted=1";
    elseif ($filter === 'inactive')
        $where .= " AND u.is_active=0";

    if ($search !== '') {
        $sq = mysqli_real_escape_string($conn, $search);
        $where .= " AND (u.first_name LIKE '%$sq%' OR u.last_name LIKE '%$sq%' OR u.email LIKE '%$sq%')";
    }

    $res = mysqli_query(
        $conn,
        "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone,
                u.is_blacklisted, u.is_active, u.created_at,
                COUNT(DISTINCT b.booking_id) AS total_bookings
         FROM users u
         LEFT JOIN bookings b ON b.user_id=u.user_id
         $where
         GROUP BY u.user_id
         ORDER BY u.created_at DESC"
    );

    $users = [];
    while ($r = mysqli_fetch_assoc($res))
        $users[] = $r;

    $stats = [
        'total_blacklisted' => (int) (mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_blacklisted=1"
        ))['c'] ?? 0),
        'total_inactive' => (int) (mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_active=0"
        ))['c'] ?? 0),
    ];

    echo json_encode(['success' => true, 'users' => $users, 'stats' => $stats, 'count' => count($users)]);
    exit;
}

if ($method === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $uid = (int) ($_POST['user_id'] ?? 0);

    if (!$uid) {
        echo json_encode(['success' => false, 'message' => 'user_id required.']);
        exit;
    }

    // Prevent operating on admins
    $target = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT role FROM users WHERE user_id=$uid LIMIT 1"
    ));
    if (!$target || $target['role'] !== 'user') {
        echo json_encode(['success' => false, 'message' => 'Target user not found or not a guest.']);
        exit;
    }

    if ($action === 'blacklist') {
        $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? 'No reason provided'));
        mysqli_query($conn, "UPDATE users SET is_blacklisted=1, is_active=0 WHERE user_id=$uid");
        mysqli_query($conn, "INSERT INTO activity_logs (user_id, action, module, created_at)
            VALUES ({$_SESSION['user_id']}, 'Blacklisted user #$uid — $reason', 'blacklist', NOW())");
        echo json_encode(['success' => true, 'message' => 'User has been blacklisted.']);
        exit;
    }

    if ($action === 'unblacklist') {
        mysqli_query($conn, "UPDATE users SET is_blacklisted=0, is_active=1 WHERE user_id=$uid");
        mysqli_query($conn, "INSERT INTO activity_logs (user_id, action, module, created_at)
            VALUES ({$_SESSION['user_id']}, 'Removed blacklist for user #$uid', 'blacklist', NOW())");
        echo json_encode(['success' => true, 'message' => 'Blacklist removed. User account reactivated.']);
        exit;
    }

    if ($action === 'deactivate') {
        mysqli_query($conn, "UPDATE users SET is_active=0 WHERE user_id=$uid");
        echo json_encode(['success' => true, 'message' => 'Account deactivated.']);
        exit;
    }

    if ($action === 'reactivate') {
        mysqli_query($conn, "UPDATE users SET is_active=1, is_blacklisted=0 WHERE user_id=$uid");
        echo json_encode(['success' => true, 'message' => 'Account reactivated.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}