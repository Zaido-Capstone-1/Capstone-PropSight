<?php
ob_start();
/**
 * API: /api/reservations.php
 * GET  — fetch bookings with optional filters
 * POST — create, update status, cancel booking
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/unit_status_sync.php';

function autoCompleteExpiredBookings(mysqli $conn): void
{
    mysqli_query($conn, "UPDATE bookings
        SET status='completed', checkout_date=CURDATE()
        WHERE status IN ('confirmed','active')
          AND checkout_date < CURDATE()");

    $unitRes = mysqli_query($conn, "SELECT DISTINCT unit_id FROM bookings
        WHERE status = 'completed' AND checkout_date = CURDATE()");
    while ($unitRes && ($unitRow = mysqli_fetch_assoc($unitRes))) {
        syncUnitAvailabilityFromBookings($conn, (int)$unitRow['unit_id']);
    }
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────
//  GET — list bookings
// ────────────────────────────────────────────────
if ($method === 'GET') {
    $status = $_GET['status'] ?? 'all';
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];

    if ($status !== 'all') {
        $s = mysqli_real_escape_string($conn, $status);
        $where[] = "b.status = '$s'";
    }

    if ($search !== '') {
        $sq = mysqli_real_escape_string($conn, $search);
        $where[] = "(u2.first_name LIKE '%$sq%' OR u2.last_name LIKE '%$sq%'
                     OR u2.email LIKE '%$sq%' OR un.unit_name LIKE '%$sq%'
                     OR un.unit_number LIKE '%$sq%' OR p.property_name LIKE '%$sq%'
                     OR b.booking_id LIKE '%$sq%')";
    }

    autoCompleteExpiredBookings($conn);

    // stats_only=1 — lightweight endpoint just for updating KPI counters
    if (!empty($_GET['stats_only'])) {
        $stats = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT
                COUNT(*) AS total,
                SUM(status='pending')                AS pending,
                SUM(status IN('confirmed','active')) AS confirmed,
                SUM(status='completed')              AS completed,
                SUM(status='cancelled')              AS cancelled
             FROM bookings"));
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    $whereSQL = implode(' AND ', $where);

    // stats
    $stats = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            COUNT(*) AS total,
            SUM(status='pending')                AS pending,
            SUM(status IN('confirmed','active')) AS confirmed,
            SUM(status='completed')              AS completed,
            SUM(status='cancelled')              AS cancelled
         FROM bookings"));

    // records
    $sql = "
        SELECT
            b.booking_id, b.checkin_date, b.checkout_date, b.guests,
            b.total_amount, b.status, b.created_at, b.payment_method, b.paid_at,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            CONCAT(u2.first_name,' ',u2.last_name)    AS user_name,
            u2.email AS user_email, u2.phone AS user_phone,
            un.unit_name, un.unit_number, un.unit_id,
            p.property_name, p.property_id
        FROM bookings b
        JOIN users     u2 ON u2.user_id    = b.user_id
        JOIN units     un ON un.unit_id    = b.unit_id
        LEFT JOIN properties p ON p.property_id = un.property_id
        WHERE $whereSQL
        ORDER BY b.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $res = mysqli_query($conn, $sql);
    $bookings = [];
    while ($row = mysqli_fetch_assoc($res)) $bookings[] = $row;

    $countRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings b
        JOIN users u2 ON u2.user_id = b.user_id
        JOIN units un ON un.unit_id = b.unit_id
        LEFT JOIN properties p ON p.property_id = un.property_id
        WHERE $whereSQL");
    $total = (int)mysqli_fetch_assoc($countRes)['c'];

    echo json_encode([
        'success'  => true,
        'bookings' => $bookings,
        'stats'    => $stats,
        'count'    => $total,
        'pages'    => ceil($total / $limit),
        'page'     => $page,
    ]);
    exit;
}

// ────────────────────────────────────────────────
//  POST — create or update booking
// ────────────────────────────────────────────────
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── UPDATE STATUS ──────────────────────────
    if ($action === 'update_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowed   = ['confirmed','cancelled','completed','active','pending'];

        if (!$bookingId || !in_array($newStatus, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
        }

        $bkRow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT unit_id, status, total_amount, payment_method FROM bookings WHERE booking_id=$bookingId LIMIT 1"));
        if (!$bkRow) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']); exit;
        }

        mysqli_begin_transaction($conn);
        try {
            $statusEsc = mysqli_real_escape_string($conn, $newStatus);
            $confirmedAtSql = $newStatus === 'confirmed' ? ", confirmed_at = NOW()" : "";
            // If completing or cancelling early, set checkout to today so the unit frees immediately
            if (in_array($newStatus, ['completed', 'cancelled'])) {
                if (!mysqli_query($conn, "UPDATE bookings SET status='$statusEsc', checkout_date=CURDATE() WHERE booking_id=$bookingId AND checkout_date > CURDATE()"))
                    throw new Exception(mysqli_error($conn));
                // fallback for bookings already past checkout
                if (!mysqli_query($conn, "UPDATE bookings SET status='$statusEsc' WHERE booking_id=$bookingId"))
                    throw new Exception(mysqli_error($conn));
            } else {
                if (!mysqli_query($conn, "UPDATE bookings SET status='$statusEsc'$confirmedAtSql WHERE booking_id=$bookingId"))
                    throw new Exception(mysqli_error($conn));
            }

            $unitId = (int)$bkRow['unit_id'];
            if (!syncUnitAvailabilityFromBookings($conn, $unitId)) {
                throw new Exception('Failed to sync unit availability.');
            }

            // Auto-create payment record on confirmation
            // Cash → pending (admin collects manually); everything else → paid immediately
            if (in_array($newStatus, ['confirmed', 'active'])) {
                $amt       = (float)($bkRow['total_amount'] ?? 0);
                $payMethod = strtolower(trim($bkRow['payment_method'] ?? ''));
                if ($amt > 0) {
                    $payExists = mysqli_fetch_assoc(mysqli_query($conn,
                        "SELECT payment_id FROM payments WHERE booking_id=$bookingId LIMIT 1"));
                    if (!$payExists) {
                        $payMethodEsc  = mysqli_real_escape_string($conn, $bkRow['payment_method'] ?? '');
                        $payStatus     = ($payMethod === 'cash') ? 'pending' : 'paid';
                        mysqli_query($conn, "INSERT INTO payments
                            (booking_id, payment_date, amount_paid, payment_method, payment_status, notes)
                            VALUES ($bookingId, CURDATE(), $amt, '$payMethodEsc', '$payStatus',
                                    'Auto-created on booking confirmation')");
                    }
                }
            }

            // Auto-create transaction on completion and mark payment as paid
            if ($newStatus === 'completed') {
                $bkFull = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT total_amount FROM bookings WHERE booking_id=$bookingId"));
                $amt = (float)($bkFull['total_amount'] ?? 0);
                if ($amt > 0) {
                    // Mark existing payment record as paid
                    mysqli_query($conn, "UPDATE payments SET payment_status='paid', payment_date=CURDATE()
                        WHERE booking_id=$bookingId AND payment_status != 'paid'");

                    $ref = 'TXN-BK-' . $bookingId;
                    $existing = mysqli_fetch_assoc(mysqli_query($conn,
                        "SELECT id FROM transactions WHERE reference_no='$ref' LIMIT 1"));
                    if (!$existing) {
                        $propRow = mysqli_fetch_assoc(mysqli_query($conn,
                            "SELECT u.property_id FROM units u
                             JOIN bookings b ON b.unit_id = u.unit_id
                             WHERE b.booking_id=$bookingId LIMIT 1"));
                        $propId = (int)($propRow['property_id'] ?? 0);
                        $propIdSql = $propId > 0 ? $propId : 'NULL';
                        mysqli_query($conn, "INSERT INTO transactions
                            (reference_no, description, category, type, amount, transaction_date, booking_id, property_id)
                            VALUES ('$ref','Booking #$bookingId payment','Room Revenue','Income',$amt,CURDATE(),$bookingId,$propIdSql)");
                    }
                }
            }

            mysqli_commit($conn);

            // ── Notify guest of status change ────────────────
            $bkRef   = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
            $bkExtra = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT b.user_id, u.unit_name, u.unit_number, p.property_name
                 FROM bookings b
                 JOIN units u ON u.unit_id = b.unit_id
                 LEFT JOIN properties p ON p.property_id = u.property_id
                 WHERE b.booking_id=$bookingId LIMIT 1"));
            if ($bkExtra) {
                $gId      = (int)$bkExtra['user_id'];
                $uLabel   = $bkExtra['unit_name']
                    ?: (($bkExtra['property_name'] ?? '') . ' — Unit ' . ($bkExtra['unit_number'] ?? ''));
                $ntMsgs   = [
                    'confirmed' => ['Your booking is confirmed! 🎉',
                                    "Booking $bkRef for $uLabel has been confirmed."],
                    'cancelled' => ['Booking cancelled',
                                    "Booking $bkRef for $uLabel has been cancelled."],
                    'completed' => ['Stay completed — thanks for visiting!',
                                    "Booking $bkRef at $uLabel is now complete."],
                    'active'    => ['Your stay is now active 🏠',
                                    "Check-in confirmed for booking $bkRef."],
                ];
                if (isset($ntMsgs[$newStatus]) && $gId > 0) {
                    [$nt, $nb] = $ntMsgs[$newStatus];
                    $ntE = mysqli_real_escape_string($conn, $nt);
                    $nbE = mysqli_real_escape_string($conn, $nb);
                    mysqli_query($conn,
                        "INSERT INTO notifications (user_id, type, title, body, link)
                         VALUES ($gId, 'booking', '$ntE', '$nbE', 'pages/user/bookings.php')");
                }
            }

            $labels = [
                'confirmed' => 'Booking confirmed successfully.',
                'cancelled' => 'Booking cancelled. Unit is now vacant.',
                'completed' => 'Booking completed. Unit is now vacant.',
                'active'    => 'Booking set to active.',
                'pending'   => 'Booking reset to pending.',
            ];

            // Clean any leaked output then send JSON
            ob_clean();
            echo json_encode(['success' => true, 'message' => $labels[$newStatus], 'new_status' => $newStatus]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── DELETE ─────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['booking_id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit; }
        if (mysqli_query($conn, "DELETE FROM bookings WHERE booking_id=$id")) {
            echo json_encode(['success'=>true,'message'=>'Booking deleted.']);
        } else {
            echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}