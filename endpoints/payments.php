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
        $where[] = "(t.full_name LIKE '%$sq%' OR p.manual_tenant_name LIKE '%$sq%' OR u.unit_number LIKE '%$sq%' OR mu.unit_number LIKE '%$sq%' OR CAST(p.payment_id AS CHAR) LIKE '%$sq%')";
    }
    $whereSQL = implode(' AND ', $where);

    $sql = "
        SELECT
            p.payment_id, p.booking_id, p.manual_tenant_name, p.manual_unit_id, p.payment_date, p.amount_paid,
            p.payment_method, p.payment_status, p.notes, p.created_at,
            COALESCE(NULLIF(t.full_name,''), NULLIF(p.manual_tenant_name,'')) AS tenant_name,
            COALESCE(NULLIF(t.full_name,''), NULLIF(p.manual_tenant_name,''), '—') AS full_name,
            t.tenant_id,
            COALESCE(u.unit_number, mu.unit_number) AS unit_number,
            COALESCE(u.unit_id, mu.unit_id) AS unit_id,
            pr.property_name,
            b.checkin_date, b.checkout_date
        FROM payments p
        LEFT JOIN bookings   b  ON b.booking_id  = p.booking_id
        LEFT JOIN units      u  ON u.unit_id      = b.unit_id
        LEFT JOIN units      mu ON mu.unit_id     = p.manual_unit_id
        LEFT JOIN properties pr ON pr.property_id = u.property_id
        LEFT JOIN tenants    t  ON t.tenant_id    = b.tenant_id
        WHERE $whereSQL
        ORDER BY p.payment_date DESC, p.payment_id DESC
    ";

    $res = mysqli_query($conn, $sql);
    $records = [];
    while ($row = mysqli_fetch_assoc($res)) {
        fmt_dt_row($row);
        $records[] = $row;
    }

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
        $manual_name = trim($_POST['manual_tenant_name'] ?? '');
        $manual_unit_id = (int) ($_POST['manual_unit_id'] ?? 0);
        $date = trim($_POST['payment_date'] ?? '');
        $amount = (float) ($_POST['amount_paid'] ?? 0);
        $method_pay = trim($_POST['payment_method'] ?? '');
        $pstatus = trim($_POST['payment_status'] ?? 'paid');
        $notes = trim($_POST['notes'] ?? '');

        $usingManual = $booking_id <= 0 && $manual_name !== '';

        if ((!$booking_id && !$usingManual) || !$date || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Tenant/booking, date, and amount are required.']);
            exit;
        }

        $bookingParam = $usingManual ? null : $booking_id;
        $manualParam = $usingManual ? $manual_name : null;
        $manualUnitParam = ($usingManual && $manual_unit_id > 0) ? $manual_unit_id : null;

        $stmt = $conn->prepare("INSERT INTO payments (booking_id,manual_tenant_name,manual_unit_id,payment_date,amount_paid,payment_method,payment_status,notes)
                VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('isisdsss', $bookingParam, $manualParam, $manualUnitParam, $date, $amount, $method_pay, $pstatus, $notes);

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            $ref = 'PMT-' . $newId;
            $txPropId = null;
            if (!$usingManual) {
                $propStmt = $conn->prepare("SELECT p.property_id FROM bookings b JOIN units u ON u.unit_id = b.unit_id JOIN properties p ON p.property_id = u.property_id WHERE b.booking_id = ? LIMIT 1");
                $propStmt->bind_param('i', $booking_id);
                $propStmt->execute();
                $propIdRow = $propStmt->get_result()->fetch_assoc();
                $propStmt->close();
                $txPropId = $propIdRow ? (int) $propIdRow['property_id'] : null;
            } elseif ($manualUnitParam) {
                $propStmt = $conn->prepare("SELECT property_id FROM units WHERE unit_id = ? LIMIT 1");
                $propStmt->bind_param('i', $manualUnitParam);
                $propStmt->execute();
                $propIdRow = $propStmt->get_result()->fetch_assoc();
                $propStmt->close();
                $txPropId = $propIdRow ? (int) $propIdRow['property_id'] : null;
            }
            $desc = $usingManual
                ? "Payment #$newId for $manual_name (walk-in)"
                : "Payment #$newId for Booking #$booking_id";

            $txStmt = $conn->prepare("INSERT INTO transactions (reference_no,description,category,type,amount,transaction_date,booking_id,property_id)
                VALUES (?,?,?,?,?,?,?,?)");
            $category = 'Room Revenue';
            $type = 'Income';
            $txStmt->bind_param('ssssdsii', $ref, $desc, $category, $type, $amount, $date, $bookingParam, $txPropId);
            $txStmt->execute();
            $txStmt->close();
            echo json_encode(['success' => true, 'message' => 'Payment recorded.', 'payment_id' => $newId]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    // ── EDIT ─────────────────────────────────────
    if ($action === 'edit') {
        $pid = (int) ($_POST['payment_id'] ?? 0);
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $manual_name = trim($_POST['manual_tenant_name'] ?? '');
        $manual_unit_id = (int) ($_POST['manual_unit_id'] ?? 0);
        $date = trim($_POST['payment_date'] ?? '');
        $amount = (float) ($_POST['amount_paid'] ?? 0);
        $method_pay = trim($_POST['payment_method'] ?? '');
        $pstatus = trim($_POST['payment_status'] ?? 'paid');
        $notes = trim($_POST['notes'] ?? '');

        $usingManual = $booking_id <= 0 && $manual_name !== '';

        if (!$pid || (!$booking_id && !$usingManual) || !$date || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'All fields required.']);
            exit;
        }

        $bookingParam = $usingManual ? null : $booking_id;
        $manualParam = $usingManual ? $manual_name : null;
        $manualUnitParam = ($usingManual && $manual_unit_id > 0) ? $manual_unit_id : null;

        $stmt = $conn->prepare("UPDATE payments SET booking_id=?, manual_tenant_name=?, manual_unit_id=?,
                payment_date=?, amount_paid=?, payment_method=?, payment_status=?, notes=?
                WHERE payment_id=?");
        $stmt->bind_param('isisdsssi', $bookingParam, $manualParam, $manualUnitParam, $date, $amount, $method_pay, $pstatus, $notes, $pid);

        if ($stmt->execute()) {
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

        $pRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT booking_id, notes FROM payments WHERE payment_id=$pid LIMIT 1"));
        if ($pRow) {
            $notes = $pRow['notes'] ?? '';
            if (str_starts_with($notes, 'INV-PMT-')) {
                $txRef = mysqli_real_escape_string($conn, $notes);
            } else {
                $txRef = 'PMT-' . $pid;
            }
            mysqli_query($conn, "DELETE FROM transactions WHERE reference_no = '$txRef'");
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