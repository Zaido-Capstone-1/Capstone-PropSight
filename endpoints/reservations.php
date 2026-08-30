<?php
ob_start();
/**
 * API: /endpoints/reservations.php
 * GET  — fetch bookings with optional filters
 * POST — create, update status, cancel booking
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/unit_status_sync.php';

function autoCompleteExpiredBookings(mysqli $conn): void
{
    // NOTE: Bookings are no longer auto-marked 'completed' just because the
    // checkout date has passed — that requires an explicit admin action
    // (the "Complete" button), same as check-in requires one.
}

/**
 * Builds the branded booking-status HTML email body.
 * Shared by the automatic status-change email and the manual "Resend" action.
 */
function buildBookingStatusEmailHtml(string $statusKey, array $vars): string
{
    $statusMeta = [
        'confirmed' => [
            'subject' => 'Booking Confirmed',
            'accent' => '#16a34a',
            'bg' => '#f0fdf4',
            'headline' => 'Your Booking is Confirmed!',
            'sub' => 'Great news — your reservation is all set. We look forward to welcoming you.',
            'icon' => '',
            'badge_bg' => '#dcfce7',
        ],
        'cancelled' => [
            'subject' => 'Booking Cancelled',
            'accent' => '#dc2626',
            'bg' => '#fef2f2',
            'headline' => 'Your Booking Has Been Cancelled',
            'sub' => "Your reservation has been cancelled. If you have questions, please don't hesitate to reach out.",
            'icon' => '',
            'badge_bg' => '#fee2e2',
        ],
        'completed' => [
            'subject' => 'Stay Completed',
            'accent' => '#2563eb',
            'bg' => '#eff6ff',
            'headline' => 'Thank You for Your Stay!',
            'sub' => "We hope you had a wonderful time. We'd love to welcome you back again soon.",
            'icon' => '<span style="color:#ffffff;font-size:30px;font-family:Arial,sans-serif;">&#9825;</span>',
            'badge_bg' => '#dbeafe',
        ],
        'active' => [
            'subject' => 'Check-In Confirmed',
            'accent' => '#0891b2',
            'bg' => '#ecfeff',
            'headline' => 'Welcome! Your Check-In is Confirmed',
            'sub' => "You're all checked in. We hope you enjoy every moment of your stay.",
            'icon' => '&#8962;',
            'badge_bg' => '#cffafe',
        ],
    ];

    $m = $statusMeta[$statusKey] ?? $statusMeta['confirmed'];
    $accent = $m['accent'];
    $bgColor = $m['bg'];
    $headline = $m['headline'];
    $subline = $m['sub'];
    $icon = $m['icon'];
    $badgeBg = $m['badge_bg'];

    $bkRef = $vars['bkRef'];
    $uLabelSafe = htmlspecialchars($vars['uLabel']);
    $userName = htmlspecialchars($vars['userName']);
    $checkin = $vars['checkin'];
    $checkout = $vars['checkout'];
    $amount = $vars['amount'];
    $year = date('Y');

    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
    <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;'>

    <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#f1f5f9;padding:40px 16px;'>
    <tr><td align='center'>

    <table role='presentation' width='100%' style='max-width:580px;' cellpadding='0' cellspacing='0'>

        <!-- Brand header -->
        <tr>
        <td style='background:#1e3a5f;border-radius:12px 12px 0 0;padding:22px 36px;text-align:center;'>
            <div style='color:#c9a84c;font-size:11px;font-weight:700;letter-spacing:0.2em;text-transform:uppercase;margin-bottom:4px;'>Boracay Accommodation</div>
            <div style='color:rgba(255,255,255,0.5);font-size:10px;letter-spacing:0.12em;text-transform:uppercase;'>Investment Properties &amp; Services</div>
        </td>
        </tr>

        <!-- Status banner -->
        <tr>
        <td style='background:{$accent};padding:32px 36px;text-align:center;'>
            " . ($icon ? "<table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 auto 16px;'><tr><td width='68' height='68' style='width:68px;height:68px;border-radius:50%;background:rgba(255,255,255,0.22);text-align:center;vertical-align:middle;font-size:34px;color:#ffffff;line-height:68px;font-family:Arial,sans-serif;'>{$icon}</td></tr></table>" : '') . "
            <h1 style='color:#ffffff;margin:0;font-size:22px;font-weight:700;line-height:1.3;letter-spacing:-0.2px;'>{$headline}</h1>
            <p style='color:rgba(255,255,255,0.85);margin:10px 0 0;font-size:14px;line-height:1.6;max-width:400px;display:inline-block;'>{$subline}</p>
        </td>
        </tr>

        <!-- Body -->
        <tr>
        <td style='background:#ffffff;padding:36px 36px 28px;'>
            <p style='color:#1e3a5f;font-size:16px;font-weight:600;margin:0 0 6px;'>Hi {$userName},</p>
            <p style='color:#6b7280;font-size:14px;margin:0 0 28px;line-height:1.6;'>Here are the details of your reservation:</p>

            <!-- Booking card -->
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0'
                style='background:{$bgColor};border:1.5px solid {$badgeBg};border-radius:10px;overflow:hidden;margin-bottom:24px;'>

            <!-- Ref badge row -->
            <tr>
                <td colspan='2' style='padding:14px 20px;border-bottom:1px solid {$badgeBg};'>
                <span style='font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:{$accent};'>Booking Reference</span>
                <span style='float:right;font-size:15px;font-weight:800;color:#1e3a5f;letter-spacing:0.05em;'>{$bkRef}</span>
                </td>
            </tr>

            <!-- Details rows -->
            <tr>
                <td style='padding:11px 20px 4px;font-size:12px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;'>Unit</td>
                <td style='padding:11px 20px 4px;font-size:14px;color:#374151;font-weight:600;text-align:right;'>{$uLabelSafe}</td>
            </tr>
            <tr>
                <td style='padding:4px 20px;font-size:12px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;'>Check-in</td>
                <td style='padding:4px 20px;font-size:14px;color:#374151;text-align:right;'>{$checkin}</td>
            </tr>
            <tr>
                <td style='padding:4px 20px;font-size:12px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;'>Check-out</td>
                <td style='padding:4px 20px;font-size:14px;color:#374151;text-align:right;'>{$checkout}</td>
            </tr>

            <!-- Total row -->
            <tr>
                <td colspan='2' style='padding:14px 20px;border-top:1px solid {$badgeBg};margin-top:6px;'>
                <span style='font-size:13px;color:#6b7280;font-weight:600;'>Total Amount</span>
                <span style='float:right;font-size:18px;font-weight:800;color:{$accent};'>{$amount}</span>
                </td>
            </tr>
            </table>

            <p style='color:#9ca3af;font-size:13px;margin:0;line-height:1.7;text-align:center;'>
            Questions or concerns? Reply to this email or<br>visit our website — we're happy to help.
            </p>
        </td>
        </tr>

        <!-- Footer -->
        <tr>
        <td style='background:#1e3a5f;border-radius:0 0 12px 12px;padding:20px 36px;text-align:center;'>
            <p style='margin:0 0 4px;font-size:11px;color:rgba(255,255,255,0.4);letter-spacing:0.08em;text-transform:uppercase;'>
            &copy; {$year} Boracay Accommodation. All rights reserved.
            </p>
            <p style='margin:0;font-size:11px;color:rgba(255,255,255,0.25);'>This is an automated message, please do not reply directly.</p>
        </td>
        </tr>

    </table>
    </td></tr>
    </table>

    </body>
    </html>";
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────
//  GET — search existing guests (for the "Add Reservation" modal)
// ────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['search_guests'])) {
    $q = trim((string) $_GET['search_guests']);
    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'guests' => []]);
        exit;
    }
    $like = '%' . $q . '%';
    $stmt = $conn->prepare(
        "SELECT user_id, first_name, last_name, email, phone, profile_photo
         FROM users
         WHERE role = 'user'
           AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ?)
         ORDER BY first_name, last_name
         LIMIT 8"
    );
    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $stmt->execute();
    $guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'guests' => $guests]);
    exit;
}

// ────────────────────────────────────────────────
//  GET — single booking detail (for the detail modal)
// ────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['detail'])) {
    $id = (int) $_GET['detail'];
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking id.']);
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT
            b.booking_id, b.checkin_date, b.checkout_date, b.guests,
            b.total_amount, b.status, b.created_at,
            b.payment_method, b.paid_at, b.special_requests, b.booking_source,
            b.payment_ref, b.payment_notes,
            b.checkin_status, b.checkout_status, b.checkin_actual, b.checkout_actual,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            CONCAT(u2.first_name,' ',u2.last_name)    AS user_name,
            u2.email AS user_email, u2.phone AS user_phone,
            u2.profile_photo AS user_photo,
            un.unit_name, un.unit_number, un.max_guests,
            p.property_name, p.address AS property_address
         FROM bookings b
         JOIN users     u2 ON u2.user_id    = b.user_id
         JOIN units     un ON un.unit_id    = b.unit_id
         LEFT JOIN properties p ON p.property_id = un.property_id
         WHERE b.booking_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }
    fmt_dt_row($row);

    $payStmt = $conn->prepare(
        "SELECT payment_id, payment_date, amount_paid, payment_method, payment_status
         FROM payments WHERE booking_id = ? ORDER BY created_at DESC"
    );
    $payStmt->bind_param('i', $id);
    $payStmt->execute();
    $payments = $payStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $payStmt->close();

    echo json_encode(['success' => true, 'booking' => $row, 'payments' => $payments]);
    exit;
}

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

    // ── CREATE (manual / walk-in reservation) ───
    if ($action === 'create') {
        $guestMode = trim($_POST['guest_mode'] ?? 'new'); // 'existing' | 'new'
        $unitId = (int) ($_POST['unit_id'] ?? 0);
        $checkin = trim($_POST['checkin'] ?? '');
        $checkout = trim($_POST['checkout'] ?? '');
        $guests = max(1, (int) ($_POST['guests'] ?? 1));
        $paymentMethod = trim($_POST['payment_method'] ?? 'cash');
        $status = trim($_POST['status'] ?? 'confirmed');
        $bookingSource = trim($_POST['booking_source'] ?? 'Walk-in');
        $specialRequests = trim($_POST['special_requests'] ?? '');
        $clientTotal = (float) ($_POST['total_amount'] ?? 0);

        $allowedStatuses = ['pending', 'confirmed', 'active'];
        if (!in_array($status, $allowedStatuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid initial status.']);
            exit;
        }
        if ($bookingSource === '')
            $bookingSource = 'Walk-in';
        if (!$unitId || !$checkin || !$checkout) {
            echo json_encode(['success' => false, 'message' => 'Missing required booking details.']);
            exit;
        }

        $dtIn = DateTime::createFromFormat('Y-m-d', $checkin);
        $dtOut = DateTime::createFromFormat('Y-m-d', $checkout);
        if (!$dtIn || !$dtOut) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
            exit;
        }
        if ($dtOut <= $dtIn) {
            echo json_encode(['success' => false, 'message' => 'Check-out must be after check-in.']);
            exit;
        }
        $nights = $dtIn->diff($dtOut)->days;
        if ($guests > 10) {
            echo json_encode(['success' => false, 'message' => 'Maximum 10 guests allowed.']);
            exit;
        }

        // ── Resolve guest → user_id ──────────────
        $userId = 0;
        $fullName = '';
        $guestEmail = '';

        if ($guestMode === 'existing') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'Please select a guest.']);
                exit;
            }
            $uStmt = $conn->prepare("SELECT user_id, email, first_name, last_name FROM users WHERE user_id = ? AND role='user' LIMIT 1");
            $uStmt->bind_param('i', $userId);
            $uStmt->execute();
            $uRow = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
            if (!$uRow) {
                echo json_encode(['success' => false, 'message' => 'Guest not found.']);
                exit;
            }
            $fullName = trim($uRow['first_name'] . ' ' . $uRow['last_name']);
            $guestEmail = $uRow['email'];
        } else {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $guestEmail = trim($_POST['email'] ?? '');
            $guestPhone = trim($_POST['phone'] ?? '');

            if ($firstName === '' || $lastName === '' || $guestEmail === '') {
                echo json_encode(['success' => false, 'message' => 'Guest first name, last name and email are required.']);
                exit;
            }
            if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid guest email.']);
                exit;
            }

            // Reuse an existing account with this email, if one exists
            $chkEmail = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE email = ? LIMIT 1");
            $chkEmail->bind_param('s', $guestEmail);
            $chkEmail->execute();
            $existingUser = $chkEmail->get_result()->fetch_assoc();
            $chkEmail->close();

            if ($existingUser) {
                $userId = (int) $existingUser['user_id'];
                $fullName = trim($existingUser['first_name'] . ' ' . $existingUser['last_name']);
            } else {
                $randomPassword = bin2hex(random_bytes(16));
                $hash = password_hash($randomPassword, PASSWORD_DEFAULT);
                $phoneParam = $guestPhone !== '' ? $guestPhone : null;

                $insUserStmt = $conn->prepare(
                    "INSERT INTO users (first_name, last_name, email, phone, password, role, verification_status, id_verified)
                     VALUES (?, ?, ?, ?, ?, 'user', 'Verified', 'approved')"
                );
                $insUserStmt->bind_param('sssss', $firstName, $lastName, $guestEmail, $phoneParam, $hash);
                if (!$insUserStmt->execute()) {
                    $dupe = $conn->errno === 1062;
                    $insUserStmt->close();
                    echo json_encode(['success' => false, 'message' => $dupe ? 'That email or phone number is already registered to another guest.' : 'Could not create the guest record.']);
                    exit;
                }
                $userId = (int) $insUserStmt->insert_id;
                $insUserStmt->close();
                $fullName = trim($firstName . ' ' . $lastName);
            }
        }

        // ── Validate the unit ────────────────────
        $unitStmt = $conn->prepare(
            "SELECT u.unit_id, u.rent_amount, u.status, u.unit_name, u.unit_number, p.property_name
             FROM units u LEFT JOIN properties p ON p.property_id = u.property_id
             WHERE u.unit_id = ? LIMIT 1"
        );
        $unitStmt->bind_param('i', $unitId);
        $unitStmt->execute();
        $unit = $unitStmt->get_result()->fetch_assoc();
        $unitStmt->close();

        if (!$unit) {
            echo json_encode(['success' => false, 'message' => 'Unit not found.']);
            exit;
        }
        if ($unit['status'] === 'maintenance') {
            echo json_encode(['success' => false, 'message' => 'This unit is currently under maintenance.']);
            exit;
        }

        $conflictStmt = $conn->prepare(
            "SELECT booking_id FROM bookings
             WHERE unit_id = ? AND status NOT IN ('cancelled','completed')
               AND checkin_date < ? AND checkout_date > ? LIMIT 1"
        );
        $conflictStmt->bind_param('iss', $unitId, $checkout, $checkin);
        $conflictStmt->execute();
        $hasConflict = $conflictStmt->get_result()->fetch_assoc();
        $conflictStmt->close();
        if ($hasConflict) {
            echo json_encode(['success' => false, 'message' => 'These dates are already booked for this unit. Please choose different dates.']);
            exit;
        }

        $baseTotal = $nights * (float) $unit['rent_amount'];
        $totalAmount = $clientTotal > 0 ? $clientTotal : $baseTotal;

        // ── Tenant record (mirrors the guest-facing booking flow) ──
        $tenantStmt = $conn->prepare('SELECT tenant_id FROM tenants WHERE email = ? LIMIT 1');
        $tenantStmt->bind_param('s', $guestEmail);
        $tenantStmt->execute();
        $tenant = $tenantStmt->get_result()->fetch_assoc();
        $tenantStmt->close();

        if (!$tenant) {
            $newTenantStmt = $conn->prepare('INSERT INTO tenants (full_name, email, move_in_date) VALUES (?, ?, ?)');
            $newTenantStmt->bind_param('sss', $fullName, $guestEmail, $checkin);
            $newTenantStmt->execute();
            $tenantId = (int) $newTenantStmt->insert_id;
            $newTenantStmt->close();
        } else {
            $tenantId = (int) $tenant['tenant_id'];
        }

        mysqli_begin_transaction($conn);
        $bookingId = null;
        try {
            for ($attempt = 1; $attempt <= 8; $attempt++) {
                $candidateId = random_int(100000, 999999);
                try {
                    $confirmedAtSql = $status === 'confirmed' ? 'NOW()' : 'NULL';
                    $insStmt = $conn->prepare(
                        "INSERT INTO bookings
                         (booking_id, unit_id, tenant_id, user_id, checkin_date, checkout_date, guests,
                          total_amount, payment_method, status, special_requests, booking_source, confirmed_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, $confirmedAtSql)"
                    );
                    $insStmt->bind_param(
                        'iiiissidsss',
                        $candidateId,
                        $unitId,
                        $tenantId,
                        $userId,
                        $checkin,
                        $checkout,
                        $guests,
                        $totalAmount,
                        $paymentMethod,
                        $status,
                        $specialRequests,
                        $bookingSource
                    );
                    $insStmt->execute();
                    $insStmt->close();
                    $bookingId = $candidateId;
                    break;
                } catch (\mysqli_sql_exception $dupErr) {
                    if ($dupErr->getCode() === 1062 && $attempt < 8)
                        continue;
                    throw $dupErr;
                }
            }
            if ($bookingId === null) {
                throw new \RuntimeException('Could not generate a unique booking ID. Please try again.');
            }

            $tenantUpdateStmt = $conn->prepare('UPDATE units SET tenant_name = ?, tenant_id = ? WHERE unit_id = ?');
            $tenantUpdateStmt->bind_param('sii', $fullName, $tenantId, $unitId);
            $tenantUpdateStmt->execute();
            $tenantUpdateStmt->close();

            if (!syncUnitAvailabilityFromBookings($conn, $unitId)) {
                throw new \RuntimeException('Failed to sync unit availability.');
            }

            // Auto-create a payment record, mirroring what happens when a booking is confirmed
            if (in_array($status, ['confirmed', 'active'], true) && $totalAmount > 0) {
                $payStatus = (strtolower($paymentMethod) === 'cash') ? 'pending' : 'paid';
                $piStmt = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (?, CURDATE(), ?, ?, ?, 'Auto-created for manual reservation')");
                $piStmt->bind_param('idss', $bookingId, $totalAmount, $paymentMethod, $payStatus);
                $piStmt->execute();
                $piStmt->close();
            }

            mysqli_commit($conn);
        } catch (\Throwable $e) {
            mysqli_rollback($conn);
            error_log('[reservations.php] create failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not create the reservation. Please try again.']);
            exit;
        }

        // Best-effort guest notification (never blocks the response)
        try {
            $unitDisplay = !empty($unit['unit_name'])
                ? $unit['unit_name']
                : (($unit['property_name'] ?? '') . ' — Unit ' . ($unit['unit_number'] ?? $unitId));
            $bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);

            if ($status !== 'pending') {
                $notifTitle = ($status === 'active' ? 'Check-in confirmed' : 'Booking confirmed') . ": $bkRef";
                $notifBody = "$unitDisplay · " . $dtIn->format('M j') . '–' . $dtOut->format('M j, Y') . " ($nights nights)";
                $ntStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, 'pages/user/bookings.php')");
                $ntStmt->bind_param('iss', $userId, $notifTitle, $notifBody);
                $ntStmt->execute();
                $ntStmt->close();

                if (!empty($guestEmail)) {
                    require_once __DIR__ . '/../integrations/email_service.php';
                    $html = buildBookingStatusEmailHtml($status === 'active' ? 'active' : 'confirmed', [
                        'bkRef' => $bkRef,
                        'uLabel' => $unitDisplay,
                        'userName' => htmlspecialchars($fullName),
                        'checkin' => $dtIn->format('F j, Y'),
                        'checkout' => $dtOut->format('F j, Y'),
                        'amount' => '₱' . number_format($totalAmount, 2),
                    ]);
                    $subject = ($status === 'active' ? 'Check-In Confirmed' : 'Booking Confirmed') . " — $bkRef";
                    $emailService->sendEmail($guestEmail, $subject, $html);
                }
            }
        } catch (\Throwable $notifErr) {
            error_log('[reservations.php] create notification failed (non-fatal): ' . $notifErr->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Reservation created successfully.',
            'booking_id' => $bookingId,
        ]);
        exit;
    }

    // ── UPDATE STATUS ──────────────────────────
    if ($action === 'update_status') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['confirmed', 'cancelled', 'completed', 'active', 'pending'];

        if (!$bookingId || !in_array($newStatus, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }

        $bkStmt = $conn->prepare("SELECT unit_id, status, total_amount, payment_method, user_id FROM bookings WHERE booking_id = ? LIMIT 1");
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
                    $exStmt = $conn->prepare("SELECT id FROM transactions WHERE booking_id = ? AND type = 'Income' LIMIT 1");
                    $exStmt->bind_param('i', $bookingId);
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

                // Award loyalty points (1 point per PHP 10 spent), avoiding duplicates
                $lpUserId = (int) ($bkRow['user_id'] ?? 0);
                if ($lpUserId > 0) {
                    $lpChk = $conn->prepare("SELECT id FROM loyalty_points WHERE booking_id = ? AND user_id = ? AND type = 'earn' LIMIT 1");
                    $lpChk->bind_param('ii', $bookingId, $lpUserId);
                    $lpChk->execute();
                    $lpExists = $lpChk->get_result()->fetch_assoc();
                    $lpChk->close();
                    if (!$lpExists) {
                        $lpPts = max(1, (int) floor($amt / 10));
                        $lpDesc = "Booking #$bookingId stay completed";
                        $lpStmt = $conn->prepare("INSERT INTO loyalty_points (user_id, points, type, description, booking_id) VALUES (?, ?, 'earn', ?, ?)");
                        $lpStmt->bind_param('iisi', $lpUserId, $lpPts, $lpDesc, $bookingId);
                        $lpStmt->execute();
                        $lpStmt->close();

                        $lpNotifBody = "You earned $lpPts loyalty points from your stay!";
                        $lpNotifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'loyalty', 'Points Earned!', ?)");
                        $lpNotifStmt->bind_param('is', $lpUserId, $lpNotifBody);
                        $lpNotifStmt->execute();
                        $lpNotifStmt->close();
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
                $uLabel = $bkExtra['unit_name']
                    ? (($bkExtra['property_name'] ?? '') . ' — ' . $bkExtra['unit_name'])
                    : (($bkExtra['property_name'] ?? '') . ' — Unit ' . ($bkExtra['unit_number'] ?? ''));
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
                        require_once __DIR__ . '/../integrations/email_service.php';
                        $checkin = date('F j, Y', strtotime($bkExtra['checkin_date']));
                        $checkout = date('F j, Y', strtotime($bkExtra['checkout_date']));
                        $amount = '₱' . number_format((float) $bkExtra['total_amount'], 2);
                        $userName = htmlspecialchars($bkExtra['user_name']);

                        $subjects = [
                            'confirmed' => 'Booking Confirmed',
                            'cancelled' => 'Booking Cancelled',
                            'completed' => 'Stay Completed',
                            'active' => 'Check-In Confirmed',
                        ];

                        $html = buildBookingStatusEmailHtml($newStatus, [
                            'bkRef' => $bkRef,
                            'uLabel' => $uLabel,
                            'userName' => $userName,
                            'checkin' => $checkin,
                            'checkout' => $checkout,
                            'amount' => $amount,
                        ]);

                        $emailService->sendEmail($bkExtra['user_email'], $subjects[$newStatus] . " — $bkRef", $html);
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

        $chkStmt = $conn->prepare("SELECT status FROM bookings WHERE booking_id = ? LIMIT 1");
        $chkStmt->bind_param('i', $id);
        $chkStmt->execute();
        $chkRow = $chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();

        if (!$chkRow) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }
        if (!in_array($chkRow['status'], ['pending', 'cancelled'], true)) {
            echo json_encode(['success' => false, 'message' => 'Only pending or cancelled bookings can be deleted. This booking has payment or stay history attached to it — cancel it first if needed.']);
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

    // ── RESEND CONFIRMATION EMAIL ───────────────
    if ($action === 'resend_confirmation') {
        $id = (int) ($_POST['booking_id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking id.']);
            exit;
        }

        $stmt = $conn->prepare(
            "SELECT b.status, b.checkin_date, b.checkout_date, b.total_amount,
                    u2.email AS user_email, CONCAT(u2.first_name,' ',u2.last_name) AS user_name,
                    un.unit_name, un.unit_number, p.property_name
             FROM bookings b
             JOIN users u2 ON u2.user_id = b.user_id
             JOIN units un ON un.unit_id = b.unit_id
             LEFT JOIN properties p ON p.property_id = un.property_id
             WHERE b.booking_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $bk = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bk) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }
        if (empty($bk['user_email'])) {
            echo json_encode(['success' => false, 'message' => 'Guest has no email on file.']);
            exit;
        }
        if (!in_array($bk['status'], ['confirmed', 'active', 'completed'], true)) {
            echo json_encode(['success' => false, 'message' => 'Only confirmed, active, or completed bookings have a confirmation email to resend.']);
            exit;
        }

        try {
            require_once __DIR__ . '/../integrations/email_service.php';
            $bkRef = 'BK-' . str_pad($id, 6, '0', STR_PAD_LEFT);
            $uLabel = $bk['unit_name']
                ? (($bk['property_name'] ?? '') . ' — ' . $bk['unit_name'])
                : (($bk['property_name'] ?? '') . ' — Unit ' . ($bk['unit_number'] ?? ''));
            $statusForEmail = $bk['status'] === 'completed' ? 'completed' : 'confirmed';

            $html = buildBookingStatusEmailHtml($statusForEmail, [
                'bkRef' => $bkRef,
                'uLabel' => $uLabel,
                'userName' => htmlspecialchars($bk['user_name']),
                'checkin' => date('F j, Y', strtotime($bk['checkin_date'])),
                'checkout' => date('F j, Y', strtotime($bk['checkout_date'])),
                'amount' => '₱' . number_format((float) $bk['total_amount'], 2),
            ]);

            $subject = ($statusForEmail === 'completed' ? 'Stay Completed' : 'Booking Confirmed') . " — $bkRef";
            $sent = $emailService->sendEmail($bk['user_email'], $subject, $html);

            if ($sent) {
                echo json_encode(['success' => true, 'message' => 'Confirmation email resent to ' . $bk['user_email'] . '.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Email service could not send the message. Please try again.']);
            }
        } catch (\Throwable $e) {
            error_log('[reservations.php] resend_confirmation failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not send email right now.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}