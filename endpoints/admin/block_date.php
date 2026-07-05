<?php
include '../../includes/session.php';
include '../../includes/db.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = $_GET['month'] ?? date('Y-m');
    [$y, $m] = array_pad(explode('-', $month), 2, date('m'));
    $y = (int) $y;
    $m = (int) $m;
    $res = mysqli_query($conn, "SELECT blocked_date AS date, reason FROM blocked_dates WHERE YEAR(blocked_date)=$y AND MONTH(blocked_date)=$m ORDER BY blocked_date");
    $dates = [];
    while ($r = mysqli_fetch_assoc($res))
        $dates[] = $r;
    echo json_encode(['success' => true, 'blocked_dates' => $dates]);
    exit;
}

require_csrf_token();
$action = $_POST['action'] ?? '';
$date = $_POST['date'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (!$date || !strtotime($date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date.']);
    exit;
}

$dateEsc = mysqli_real_escape_string($conn, $date);
$reasonEsc = mysqli_real_escape_string($conn, $reason);

if ($action === 'block') {
    $sql = "INSERT INTO blocked_dates (blocked_date, reason, created_by)
            VALUES ('$dateEsc', '$reasonEsc', {$_SESSION['user_id']})
            ON DUPLICATE KEY UPDATE reason = '$reasonEsc', created_by = {$_SESSION['user_id']}";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => "Date $date has been blocked."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }

} elseif ($action === 'unblock') {
    $sql = "DELETE FROM blocked_dates WHERE blocked_date = '$dateEsc'";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => "Date $date has been unblocked."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}