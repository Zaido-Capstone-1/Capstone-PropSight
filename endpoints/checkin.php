<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/unit_status_sync.php';

function sendBookingStatusEmail(mysqli $conn, int $bookingId, string $statusKey): void
{
    try {
        require_once __DIR__ . '/../integrations/email_service.php';

        $row = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT b.*, u2.email AS user_email,
                    CONCAT(u2.first_name,' ',u2.last_name) AS user_name,
                    COALESCE(NULLIF(un.unit_name,''), CONCAT('Unit ', un.unit_number)) AS unit_label
             FROM bookings b
             JOIN users u2 ON u2.user_id = b.user_id
             LEFT JOIN units un ON un.unit_id = b.unit_id
             WHERE b.booking_id = $bookingId LIMIT 1"
        ));
        if (!$row || empty($row['user_email']))
            return;

        $bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
        $userName = htmlspecialchars($row['user_name']);
        $uLabel = htmlspecialchars($row['unit_label']);
        $checkin = date('F j, Y', strtotime($row['checkin_date']));
        $checkout = date('F j, Y', strtotime($row['checkout_date']));
        $amount = '&#8369;' . number_format((float) $row['total_amount'], 2);
        $year = date('Y');

        $meta = [

            'completed' => [
                'subject' => 'Stay Completed',
                'accent' => '#2563eb',
                'bg' => '#eff6ff',
                'badge_bg' => '#dbeafe',
                'headline' => 'Thank You for Your Stay!',
                'sub' => "We hope you had a wonderful time. We'd love to welcome you back again soon.",
                'icon' => '<span style="color:#ffffff;font-size:30px;line-height:1;">&#9825;</span>',
            ],
        ];

        $m = $meta[$statusKey];
        $accent = $m['accent'];
        $bgColor = $m['bg'];
        $badgeBg = $m['badge_bg'];
        $headline = $m['headline'];
        $subline = $m['sub'];
        $icon = $m['icon'];
        $subject = $m['subject'] . " — $bkRef";

        $html = "
        <!DOCTYPE html>
        <html lang='en'>
        <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
        <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#f1f5f9;padding:40px 16px;'>
        <tr><td align='center'>
        <table role='presentation' width='100%' style='max-width:580px;' cellpadding='0' cellspacing='0'>
            <tr>
            <td style='background:#1e3a5f;border-radius:12px 12px 0 0;padding:18px 32px;text-align:center;'>
                <div style='color:#c9a84c;font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;margin-bottom:3px;'>Boracay Accommodation</div>
                <div style='color:rgba(255,255,255,0.4);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;'>Investment Properties &amp; Services</div>
            </td>
            </tr>
            <tr>
            <td style='background:{$accent};padding:28px 32px;text-align:center;'>
                <table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 auto 16px;'><tr><td width='68' height='68' style='width:68px;height:68px;border-radius:50%;background:rgba(255,255,255,0.22);text-align:center;vertical-align:middle;font-size:34px;color:#ffffff;line-height:68px;font-family:Arial,sans-serif;'>{$icon}</td></tr></table>
                <h1 style='color:#ffffff;margin:0;font-size:20px;font-weight:700;line-height:1.3;letter-spacing:-0.2px;'>{$headline}</h1>
                <p style='color:rgba(255,255,255,0.85);margin:10px 0 0;font-size:13px;line-height:1.6;'>{$subline}</p>
            </td>
            </tr>
            <tr>
            <td style='background:#ffffff;padding:28px 32px 24px;'>
                <p style='color:#1e3a5f;font-size:15px;font-weight:700;margin:0 0 4px;'>Hi {$userName},</p>
                <p style='color:#6b7280;font-size:13px;margin:0 0 22px;line-height:1.6;'>Here are the details of your reservation:</p>
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0'
                    style='background:{$bgColor};border:1.5px solid {$badgeBg};border-radius:10px;overflow:hidden;margin-bottom:22px;'>
                <tr>
                    <td colspan='2' style='padding:12px 18px;border-bottom:1px solid {$badgeBg};'>
                    <span style='font-size:10px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:{$accent};'>Booking Reference</span>
                    <span style='float:right;font-size:15px;font-weight:800;color:#1e3a5f;letter-spacing:0.04em;'>{$bkRef}</span>
                    </td>
                </tr>
                <tr>
                    <td style='padding:8px 18px 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;'>Unit</td>
                    <td style='padding:8px 18px 4px;font-size:13px;color:#374151;font-weight:600;text-align:right;'>{$uLabel}</td>
                </tr>
                <tr>
                    <td style='padding:4px 18px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;border-bottom:1px solid {$badgeBg};'>Check-in</td>
                    <td style='padding:4px 18px;font-size:13px;color:#374151;text-align:right;border-bottom:1px solid {$badgeBg};'>{$checkin}</td>
                </tr>
                <tr>
                    <td style='padding:4px 18px 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;'>Check-out</td>
                    <td style='padding:4px 18px 8px;font-size:13px;color:#374151;text-align:right;'>{$checkout}</td>
                </tr>
                <tr>
                    <td colspan='2' style='padding:12px 18px;border-top:1px solid {$badgeBg};'>
                    <span style='font-size:13px;color:#6b7280;font-weight:600;'>Total Amount</span>
                    <span style='float:right;font-size:18px;font-weight:800;color:{$accent};'>{$amount}</span>
                    </td>
                </tr>
                </table>
                <p style='color:#9ca3af;font-size:12px;margin:0;line-height:1.7;text-align:center;'>Questions or concerns? Reply to this email or<br>visit our website — we're happy to help.</p>
            </td>
            </tr>
            <tr>
            <td style='background:#1e3a5f;border-radius:0 0 12px 12px;padding:16px 32px;text-align:center;'>
                <p style='margin:0 0 3px;font-size:10px;color:rgba(255,255,255,0.35);letter-spacing:0.08em;text-transform:uppercase;'>&copy; {$year} Boracay Accommodation. All rights reserved.</p>
                <p style='margin:0;font-size:10px;color:rgba(255,255,255,0.2);'>This is an automated message, please do not reply directly.</p>
            </td>
            </tr>
        </table>
        </td></tr>
        </table>
        </body>
        </html>";

        $emailService->sendEmail($row['user_email'], $subject, $html);
    } catch (\Throwable $e) {
        // non-fatal
    }
}


if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
require_csrf_token();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if (!$bookingId || !in_array($action, ['checkin', 'checkout'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$booking = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT * FROM bookings WHERE booking_id=$bookingId LIMIT 1"
));

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    if ($action === 'checkin') {
        if ($booking['checkin_status'] === 'done') {
            throw new Exception('Guest has already checked in.');
        }
        // Block check-in if the scheduled check-in date is in the past
        $today = date('Y-m-d');
        $checkinDate = date('Y-m-d', strtotime($booking['checkin_date']));
        if ($checkinDate < $today) {
            throw new Exception('Cannot check in: the check-in date (' . date('M j, Y', strtotime($checkinDate)) . ') has already passed.');
        }
        if (
            !mysqli_query($conn, "UPDATE bookings SET
            checkin_status='done', checkin_actual=NOW(), status='active'
            WHERE booking_id=$bookingId")
        )
            throw new Exception(mysqli_error($conn));

        if (!syncUnitAvailabilityFromBookings($conn, (int) $booking['unit_id']))
            throw new Exception('Failed to sync unit availability.');

        $msg = 'Guest checked in successfully.';
    } else {
        if ($booking['checkout_status'] === 'done') {
            throw new Exception('Guest has already checked out.');
        }
        if ($booking['checkin_status'] !== 'done') {
            throw new Exception('Guest must be checked in before checking out.');
        }

        if (
            !mysqli_query($conn, "UPDATE bookings SET
            checkout_status='done', checkout_actual=NOW(), status='completed'
            WHERE booking_id=$bookingId")
        )
            throw new Exception(mysqli_error($conn));

        if (!syncUnitAvailabilityFromBookings($conn, (int) $booking['unit_id']))
            throw new Exception('Failed to sync unit availability.');

        // Record income transaction on checkout
        $amt = (float) $booking['total_amount'];
        if ($amt > 0) {
            // Mark payment as paid
            mysqli_query($conn, "UPDATE payments SET payment_status='paid', payment_date=CURDATE()
                WHERE booking_id=$bookingId AND payment_status != 'paid'");

            $ref = 'TXN-BK-' . $bookingId;
            $txnExists = mysqli_fetch_assoc(mysqli_query(
                $conn,
                "SELECT id FROM transactions WHERE booking_id=$bookingId AND type='Income' LIMIT 1"
            ));
            if (!$txnExists) {
                $propRow = mysqli_fetch_assoc(mysqli_query(
                    $conn,
                    "SELECT u.property_id FROM units u
                     JOIN bookings b ON b.unit_id = u.unit_id
                     WHERE b.booking_id=$bookingId LIMIT 1"
                ));
                $propId = (int) ($propRow['property_id'] ?? 0);
                $propIdSql = $propId > 0 ? $propId : 'NULL';
                mysqli_query($conn, "INSERT INTO transactions
                    (reference_no, description, category, type, amount, transaction_date, booking_id, property_id)
                    VALUES ('$ref', 'Booking #$bookingId payment', 'Room Revenue', 'Income', $amt, CURDATE(), $bookingId, $propIdSql)");
            }
        }

        // Award loyalty points (1 point per PHP 10 spent)
        $userId = (int) $booking['user_id'];
        $amt = (float) $booking['total_amount'];
        $pts = max(1, (int) floor($amt / 10));
        $desc = mysqli_real_escape_string($conn, "Booking #$bookingId stay completed");
        mysqli_query($conn, "INSERT INTO loyalty_points (user_id, points, type, description, booking_id)
            VALUES ($userId, $pts, 'earn', '$desc', $bookingId)");

        // Notification
        $notifBody = mysqli_real_escape_string($conn, "You earned $pts loyalty points from your stay!");
        mysqli_query($conn, "INSERT INTO notifications (user_id, type, title, body)
            VALUES ($userId, 'loyalty', 'Points Earned!', '$notifBody')");

        sendBookingStatusEmail($conn, $bookingId, 'completed');
        $msg = "Guest checked out. Unit is now vacant. $pts loyalty points awarded.";
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => $msg, 'action' => $action]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}