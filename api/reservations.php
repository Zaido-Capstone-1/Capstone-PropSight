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
        syncUnitAvailabilityFromBookings($conn, (int) $unitRow['unit_id']);
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
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = (int) ($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $whereClauses = ['1=1'];
    $bindTypes = '';
    $bindParams = [];

    if ($status !== 'all') {
        $whereClauses[] = "b.status = ?";
        $bindTypes .= 's';
        $bindParams[] = $status;
    }

    if ($search !== '') {
        $like = '%' . $search . '%';
        $whereClauses[] = "(u2.first_name LIKE ? OR u2.last_name LIKE ?
                     OR u2.email LIKE ? OR un.unit_name LIKE ?
                     OR un.unit_number LIKE ? OR p.property_name LIKE ?
                     OR b.booking_id LIKE ?)";
        $bindTypes .= 'sssssss';
        for ($i = 0; $i < 7; $i++)
            $bindParams[] = $like;
    }

    autoCompleteExpiredBookings($conn);

    // stats_only=1 — lightweight endpoint just for updating KPI counters
    if (!empty($_GET['stats_only'])) {
        $stats = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT
                COUNT(*) AS total,
                SUM(status='pending')                AS pending,
                SUM(status IN('confirmed','active')) AS confirmed,
                SUM(status='completed')              AS completed,
                SUM(status='cancelled')              AS cancelled
             FROM bookings"
        ));
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    $whereSQL = implode(' AND ', $whereClauses);

    // stats
    $stats = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            COUNT(*) AS total,
            SUM(status='pending')                AS pending,
            SUM(status IN('confirmed','active')) AS confirmed,
            SUM(status='completed')              AS completed,
            SUM(status='cancelled')              AS cancelled
         FROM bookings"
    ));

    // records
    $sql = "
        SELECT
            b.booking_id, b.checkin_date, b.checkout_date, b.guests,
            b.total_amount, b.status, b.created_at, b.payment_method, b.paid_at,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            CONCAT(u2.first_name,' ',u2.last_name)    AS user_name,
            u2.email AS user_email, u2.phone AS user_phone,
            u2.profile_photo AS user_photo,
            un.unit_name, un.unit_number, un.unit_id,
            p.property_name, p.property_id
        FROM bookings b
        JOIN users     u2 ON u2.user_id    = b.user_id
        JOIN units     un ON un.unit_id    = b.unit_id
        LEFT JOIN properties p ON p.property_id = un.property_id
        WHERE $whereSQL
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $listStmt = $conn->prepare($sql);
    $listTypes = $bindTypes . 'ii';
    $listParams = array_merge($bindParams, [$limit, $offset]);
    $listStmt->bind_param($listTypes, ...$listParams);
    $listStmt->execute();
    $res = $listStmt->get_result();
    $bookings = [];
    while ($row = $res->fetch_assoc()) {

        fmt_dt_row($row);

        $bookings[] = $row;

    }
    $listStmt->close();

    $countSql = "SELECT COUNT(*) AS c FROM bookings b
        JOIN users u2 ON u2.user_id = b.user_id
        JOIN units un ON un.unit_id = b.unit_id
        LEFT JOIN properties p ON p.property_id = un.property_id
        WHERE $whereSQL";
    $countStmt = $conn->prepare($countSql);
    if ($bindTypes !== '') {
        $countStmt->bind_param($bindTypes, ...$bindParams);
    }
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    echo json_encode([
        'success' => true,
        'bookings' => $bookings,
        'stats' => $stats,
        'count' => $total,
        'pages' => ceil($total / $limit),
        'page' => $page,
    ]);
    exit;
}

if ($method === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // ── UPDATE STATUS ──────────────────────────
    // ── UPDATE STATUS ──────────────────────────
    if ($action === 'update_status') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['confirmed', 'cancelled', 'completed', 'active', 'pending'];

        if (!$bookingId || !in_array($newStatus, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }

        $bkStmt = $conn->prepare("SELECT unit_id, status, total_amount, payment_method FROM bookings WHERE booking_id = ? LIMIT 1");
        $bkStmt->bind_param('i', $bookingId);
        $bkStmt->execute();
        $bkRow = $bkStmt->get_result()->fetch_assoc();
        $bkStmt->close();

        if (!$bkRow) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            $confirmedAtSql = $newStatus === 'confirmed' ? ', confirmed_at = NOW()' : '';
            $updStmt = $conn->prepare("UPDATE bookings SET status = ? $confirmedAtSql WHERE booking_id = ?");
            $updStmt->bind_param('si', $newStatus, $bookingId);
            if (!$updStmt->execute())
                throw new Exception($updStmt->error);
            $updStmt->close();

            $unitId = (int) $bkRow['unit_id'];
            if (!syncUnitAvailabilityFromBookings($conn, $unitId)) {
                throw new Exception('Failed to sync unit availability.');
            }

            // Auto-create payment record on confirmation
            if (in_array($newStatus, ['confirmed', 'active'])) {
                $amt = (float) ($bkRow['total_amount'] ?? 0);
                $payMethod = strtolower(trim($bkRow['payment_method'] ?? ''));
                if ($amt > 0) {
                    $chkStmt = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id = ? LIMIT 1");
                    $chkStmt->bind_param('i', $bookingId);
                    $chkStmt->execute();
                    $payExists = $chkStmt->get_result()->fetch_assoc();
                    $chkStmt->close();
                    if (!$payExists) {
                        $payStatus = ($payMethod === 'cash') ? 'pending' : 'paid';
                        $piStmt = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (?, CURDATE(), ?, ?, ?, 'Auto-created on booking confirmation')");
                        $piStmt->bind_param('idss', $bookingId, $amt, $bkRow['payment_method'], $payStatus);
                        $piStmt->execute();
                        $piStmt->close();
                    }
                }
            }

            // Auto-create transaction on completion
            if ($newStatus === 'completed') {
                $bkFull = $conn->prepare("SELECT total_amount FROM bookings WHERE booking_id = ?");
                $bkFull->bind_param('i', $bookingId);
                $bkFull->execute();
                $bkFullRow = $bkFull->get_result()->fetch_assoc();
                $bkFull->close();
                $amt = (float) ($bkFullRow['total_amount'] ?? 0);
                if ($amt > 0) {
                    $mpStmt = $conn->prepare("UPDATE payments SET payment_status='paid', payment_date=CURDATE() WHERE booking_id = ? AND payment_status != 'paid'");
                    $mpStmt->bind_param('i', $bookingId);
                    $mpStmt->execute();
                    $mpStmt->close();

                    $ref = 'TXN-BK-' . $bookingId;
                    $exStmt = $conn->prepare("SELECT id FROM transactions WHERE reference_no = ? LIMIT 1");
                    $exStmt->bind_param('s', $ref);
                    $exStmt->execute();
                    $existing = $exStmt->get_result()->fetch_assoc();
                    $exStmt->close();
                    if (!$existing) {
                        $prStmt = $conn->prepare("SELECT u.property_id FROM units u JOIN bookings b ON b.unit_id = u.unit_id WHERE b.booking_id = ? LIMIT 1");
                        $prStmt->bind_param('i', $bookingId);
                        $prStmt->execute();
                        $propRow = $prStmt->get_result()->fetch_assoc();
                        $prStmt->close();
                        $propId = (int) ($propRow['property_id'] ?? 0);
                        $desc = 'Booking #' . $bookingId . ' payment';
                        $cat = 'Room Revenue';
                        $type = 'Income';
                        $tiStmt = $conn->prepare("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id, property_id) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?)");
                        $tiStmt->bind_param('ssssdii', $ref, $desc, $cat, $type, $amt, $bookingId, $propId);
                        $tiStmt->execute();
                        $tiStmt->close();
                    }
                }
            }

            mysqli_commit($conn);

            // ── Fetch guest info for notification + email ──
            $bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
            $infoStmt = $conn->prepare(
                "SELECT b.user_id, u2.email AS user_email, CONCAT(u2.first_name,' ',u2.last_name) AS user_name,
                        u.unit_name, u.unit_number, p.property_name,
                        b.checkin_date, b.checkout_date, b.total_amount
                 FROM bookings b
                 JOIN users u2 ON u2.user_id = b.user_id
                 JOIN units u  ON u.unit_id  = b.unit_id
                 LEFT JOIN properties p ON p.property_id = u.property_id
                 WHERE b.booking_id = ? LIMIT 1"
            );
            $infoStmt->bind_param('i', $bookingId);
            $infoStmt->execute();
            $bkExtra = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();

            if ($bkExtra) {
                $gId = (int) $bkExtra['user_id'];
                $uLabel = $bkExtra['unit_name'] ?: (($bkExtra['property_name'] ?? '') . ' — Unit ' . ($bkExtra['unit_number'] ?? ''));
                $ntMsgs = [
                    'confirmed' => ['Your booking is confirmed! 🎉', "Booking $bkRef for $uLabel has been confirmed."],
                    'cancelled' => ['Booking cancelled', "Booking $bkRef for $uLabel has been cancelled."],
                    'completed' => ['Stay completed — thanks for visiting!', "Booking $bkRef at $uLabel is now complete."],
                    'active' => ['Your stay is now active 🏠', "Check-in confirmed for booking $bkRef."],
                ];

                // In-app notification
                if (isset($ntMsgs[$newStatus]) && $gId > 0) {
                    [$nt, $nb] = $ntMsgs[$newStatus];
                    $ntStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, 'pages/user/bookings.php')");
                    $ntStmt->bind_param('iss', $gId, $nt, $nb);
                    $ntStmt->execute();
                    $ntStmt->close();
                }

                // Email notification
                if (in_array($newStatus, ['confirmed', 'cancelled', 'completed', 'active']) && !empty($bkExtra['user_email'])) {
                    try {
                        require_once __DIR__ . '/../includes/email_service.php';
                        $checkin = date('F j, Y', strtotime($bkExtra['checkin_date']));
                        $checkout = date('F j, Y', strtotime($bkExtra['checkout_date']));
                        $amount = '₱' . number_format((float) $bkExtra['total_amount'], 2);
                        $userName = htmlspecialchars($bkExtra['user_name']);

                        $statusLabels = [
                            'confirmed' => ['Booking Confirmed', '#16a34a', '🎉 Your booking has been confirmed!'],
                            'cancelled' => ['Booking Cancelled', '#dc2626', 'Your booking has been cancelled.'],
                            'completed' => ['Stay Completed', '#2563eb', 'Thank you for your stay!'],
                            'active' => ['Check-In Confirmed', '#0891b2', 'Your check-in has been confirmed. Welcome!'],
                        ];
                        [$emailSubject, $accentColor, $headline] = $statusLabels[$newStatus];

                        $html = "
                        <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;'>
                            <div style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);'>
                                <div style='background:{$accentColor};padding:28px 32px;'>
                                    <h1 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>{$headline}</h1>
                                </div>
                                <div style='padding:28px 32px;'>
                                    <p style='color:#374151;font-size:15px;margin:0 0 20px;'>Hi {$userName},</p>
                                    <div style='background:#f1f5f9;border-radius:8px;padding:18px 20px;margin-bottom:20px;'>
                                        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>
                                            <tr><td style='padding:5px 0;color:#6b7280;'>Booking Ref</td><td style='text-align:right;font-weight:700;'>{$bkRef}</td></tr>
                                            <tr><td style='padding:5px 0;color:#6b7280;'>Unit</td><td style='text-align:right;'>" . htmlspecialchars($uLabel) . "</td></tr>
                                            <tr><td style='padding:5px 0;color:#6b7280;'>Check-in</td><td style='text-align:right;'>{$checkin}</td></tr>
                                            <tr><td style='padding:5px 0;color:#6b7280;'>Check-out</td><td style='text-align:right;'>{$checkout}</td></tr>
                                            <tr><td style='padding:5px 0;color:#6b7280;'>Total</td><td style='text-align:right;font-weight:700;color:{$accentColor};'>{$amount}</td></tr>
                                        </table>
                                    </div>
                                    <p style='color:#6b7280;font-size:13px;margin:0;'>If you have questions, please contact us.</p>
                                </div>
                            </div>
                        </div>";

                        $emailService->sendEmail($bkExtra['user_email'], $emailSubject . " — $bkRef", $html);
                    } catch (\Throwable $emailErr) {
                        error_log('[reservations.php] Email failed (non-fatal): ' . $emailErr->getMessage());
                    }
                }
            }

            $labels = [
                'confirmed' => 'Booking confirmed successfully.',
                'cancelled' => 'Booking cancelled. Unit is now vacant.',
                'completed' => 'Booking completed. Unit is now vacant.',
                'active' => 'Booking set to active.',
                'pending' => 'Booking reset to pending.',
            ];

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
        $id = (int) ($_POST['booking_id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }
        $delStmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
        $delStmt->bind_param('i', $id);
        if ($delStmt->execute()) {
            $delStmt->close();
            echo json_encode(['success' => true, 'message' => 'Booking deleted.']);
        } else {
            $err = $delStmt->error;
            $delStmt->close();
            echo json_encode(['success' => false, 'message' => $err]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}