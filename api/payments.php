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
    $status = $_GET['status'] ?? 'all';
    $month = $_GET['month'] ?? date('Y-m');
    $search = trim($_GET['q'] ?? '');

    [$y, $m] = explode('-', $month . '-01');
    $y = (int) $y;
    $m = (int) $m;

    $stats = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT
            COALESCE(SUM(CASE WHEN payment_status='paid'    THEN amount_paid END),0) AS collected,
            COALESCE(SUM(CASE WHEN payment_status='pending' THEN amount_paid END),0) AS pending_amt,
            COALESCE(SUM(CASE WHEN payment_status='late'    THEN amount_paid END),0) AS overdue_amt,
            COUNT(CASE WHEN payment_status='pending' THEN 1 END) AS pending_cnt,
            COUNT(CASE WHEN payment_status='late'    THEN 1 END) AS overdue_cnt,
            COUNT(*)                                              AS total_cnt,
            COUNT(CASE WHEN payment_status='paid'    THEN 1 END) AS paid_cnt
        FROM payments
        WHERE " . ($month !== 'all' ? "YEAR(payment_date)=$y AND MONTH(payment_date)=$m" : "1") . "
    "));

    // Build WHERE
    $where = [];
    if ($month !== 'all') {
        $where[] = "YEAR(p.payment_date)=$y";
        $where[] = "MONTH(p.payment_date)=$m";
    }
    $whereSQL = $where ? implode(' AND ', $where) : '1';
    if ($status !== 'all') {
        $se = mysqli_real_escape_string($conn, $status);
        $where[] = "p.payment_status='$se'";
    }
    if ($search !== '') {
        $sq = mysqli_real_escape_string($conn, $search);
        $where[] = "(t.full_name LIKE '%$sq%' OR u.unit_number LIKE '%$sq%' OR CAST(p.payment_id AS CHAR) LIKE '%$sq%')";
    }
    $whereSQL = implode(' AND ', $where);

    $sql = "
        SELECT
            p.payment_id, p.booking_id, p.payment_date, p.amount_paid,
            p.payment_method, p.payment_status, p.notes, p.created_at,
            t.full_name  AS tenant_name, t.tenant_id,
            u.unit_number, u.unit_id,
            pr.property_name,
            b.checkin_date, b.checkout_date
        FROM payments p
        LEFT JOIN bookings   b  ON b.booking_id  = p.booking_id
        LEFT JOIN units      u  ON u.unit_id      = b.unit_id
        LEFT JOIN properties pr ON pr.property_id = u.property_id
        LEFT JOIN tenants    t  ON t.tenant_id    = b.tenant_id
        WHERE $whereSQL
        ORDER BY p.payment_date DESC, p.payment_id DESC
    ";

    $res = mysqli_query($conn, $sql);
    $records = [];
    while ($row = mysqli_fetch_assoc($res))
        $records[] = $row;

    // 6-month trend
    $trend = [];
    $tRes = mysqli_query($conn, "
        SELECT DATE_FORMAT(payment_date,'%b') AS mo,
               YEAR(payment_date) AS yr, MONTH(payment_date) AS mn,
               COALESCE(SUM(CASE WHEN payment_status='paid'               THEN amount_paid END),0) AS collected,
               COALESCE(SUM(CASE WHEN payment_status IN('pending','late') THEN amount_paid END),0) AS outstanding
        FROM payments
        WHERE payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01')
        GROUP BY yr, mn, mo ORDER BY yr, mn
    ");
    while ($row = mysqli_fetch_assoc($tRes))
        $trend[] = $row;

    echo json_encode([
        'success' => true,
        'records' => $records,
        'stats' => $stats,
        'trend' => $trend,
        'count' => count($records),
    ]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['form_action'] ?? '';

    // ── ADD ──────────────────────────────────────
    if ($action === 'add') {
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $date = trim($_POST['payment_date'] ?? '');
        $amount = (float) ($_POST['amount_paid'] ?? 0);
        $method_pay = mysqli_real_escape_string($conn, trim($_POST['payment_method'] ?? ''));
        $pstatus = mysqli_real_escape_string($conn, trim($_POST['payment_status'] ?? 'paid'));
        $notes = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
        $dateEsc = mysqli_real_escape_string($conn, $date);

        if (!$booking_id || !$date || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Booking, date, and amount are required.']);
            exit;
        }

        $sql = "INSERT INTO payments (booking_id,payment_date,amount_paid,payment_method,payment_status,notes)
                VALUES ($booking_id,'$dateEsc',$amount,'$method_pay','$pstatus','$notes')";

        if (mysqli_query($conn, $sql)) {
            $newId = mysqli_insert_id($conn);
            // Also create a transaction entry
            $ref = 'PMT-' . $newId;
            mysqli_query($conn, "INSERT INTO transactions (reference_no,description,category,type,amount,transaction_date,booking_id)
                VALUES ('$ref','Payment #$newId for Booking #$booking_id','Room Revenue','Income',$amount,'$dateEsc',$booking_id)");
            echo json_encode(['success' => true, 'message' => 'Payment recorded.', 'payment_id' => $newId]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── EDIT ─────────────────────────────────────
    if ($action === 'edit') {
        $pid = (int) ($_POST['payment_id'] ?? 0);
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $date = mysqli_real_escape_string($conn, trim($_POST['payment_date'] ?? ''));
        $amount = (float) ($_POST['amount_paid'] ?? 0);
        $method_pay = mysqli_real_escape_string($conn, trim($_POST['payment_method'] ?? ''));
        $pstatus = mysqli_real_escape_string($conn, trim($_POST['payment_status'] ?? 'paid'));
        $notes = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));

        if (!$pid || !$booking_id || !$date || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'All fields required.']);
            exit;
        }

        $sql = "UPDATE payments SET booking_id=$booking_id, payment_date='$date',
                amount_paid=$amount, payment_method='$method_pay',
                payment_status='$pstatus', notes='$notes'
                WHERE payment_id=$pid";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Payment updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // ── DELETE ────────────────────────────────────
    if ($action === 'delete') {
        $pid = (int) ($_POST['payment_id'] ?? 0);
        if (!$pid) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }
        if (mysqli_query($conn, "DELETE FROM payments WHERE payment_id=$pid")) {
            echo json_encode(['success' => true, 'message' => 'Payment deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
