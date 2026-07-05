<?php
/**
 * endpoints/user/booking_receipt.php
 * Renders a receipt page that auto-downloads as PDF using jsPDF + html2canvas.
 * ?booking_id=XX         → view page with auto PDF download
 * ?booking_id=XX&view=1  → view only, no auto-download
 */

include '../../includes/session.php';
include '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  // Return JSON if called via fetch (XHR), plain HTML otherwise
  $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
  } else {
    header('Location: ../../index.php');
  }
  exit;
}

$userId = (int) $_SESSION['user_id'];
$bookingId = (int) ($_GET['booking_id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';
$viewOnly = isset($_GET['view']) && $_GET['view'] === '1';

if (!$bookingId) {
  http_response_code(400);
  echo '<p>Invalid booking ID.</p>';
  exit;
}

$ownerClause = ($role === 'admin') ? '' : "AND b.user_id = $userId";

$row = mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT
        b.booking_id, b.status, b.checkin_date, b.checkout_date,
        b.guests, b.total_amount, b.payment_method, b.created_at,
        DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
        DATE_FORMAT(b.checkin_date,  '%b %d, %Y')        AS checkin_fmt,
        DATE_FORMAT(b.checkout_date, '%b %d, %Y')        AS checkout_fmt,
        DATE_FORMAT(b.created_at,    '%b %d, %Y %H:%i')  AS booked_fmt,
        u.unit_name, u.unit_number, u.rent_amount,
        p.property_name, p.address, p.city, p.state,
        usr.first_name, usr.last_name, usr.email, usr.phone
     FROM bookings b
     JOIN units      u   ON u.unit_id     = b.unit_id
     JOIN properties p   ON p.property_id = u.property_id
     JOIN users      usr ON usr.user_id   = b.user_id
     WHERE b.booking_id = $bookingId $ownerClause
     LIMIT 1"
));

if (!$row) {
  http_response_code(404);
  echo '<p>Booking not found.</p>';
  exit;
}

$pay = mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT payment_status, payment_date, amount_paid
     FROM payments WHERE booking_id = $bookingId
     ORDER BY payment_id DESC LIMIT 1"
)) ?: [];

$bkRef = 'BK-' . str_pad($row['booking_id'], 6, '0', STR_PAD_LEFT);
$unitLabel = !empty($row['unit_name']) ? $row['unit_name'] : 'Unit ' . ($row['unit_number'] ?? $bookingId);
$guestName = htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name']));
$propName = htmlspecialchars($row['property_name'] ?? '');
$propAddr = htmlspecialchars(trim(($row['address'] ?? '') . ', ' . ($row['city'] ?? '') . ' ' . ($row['state'] ?? '')));
$checkin = htmlspecialchars($row['checkin_fmt']);
$checkout = htmlspecialchars($row['checkout_fmt']);
$nights = (int) $row['nights'];
$guests = (int) $row['guests'];
$ratePerNight = number_format((float) $row['rent_amount'], 2);
$total = number_format((float) $row['total_amount'], 2);
$payMethod = ucwords(str_replace('_', ' ', $row['payment_method'] ?? 'Cash'));
$payStatus = ucfirst($pay['payment_status'] ?? 'pending');
$payDate = !empty($pay['payment_date']) ? date('M d, Y', strtotime($pay['payment_date'])) : '—';
$bookedOn = htmlspecialchars($row['booked_fmt']);
$status = ucfirst($row['status']);

$statusColor = match ($row['status']) {
  'confirmed', 'active' => '#16a34a',
  'completed' => '#2563eb',
  'cancelled' => '#dc2626',
  default => '#d97706',
};

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Receipt <?= $bkRef ?></title>

  <link rel="stylesheet" href="../../assets/css/user-css/booking_receipt-inline.css">
</head>

<body>

  <div class="action-bar">
    <span class="dl-status" id="dlStatus">
      <span class="dl-spinner"></span>
      Generating PDF…
    </span>
    <button class="action-btn secondary" onclick="history.back()">
      <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
        <polyline points="15 18 9 12 15 6" />
      </svg>
      Back
    </button>
    <button class="action-btn primary" id="dlBtn" onclick="downloadPDF()">
      <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line x1="12" y1="15" x2="12" y2="3" />
      </svg>
      Download PDF
    </button>
  </div>

  <div class="receipt" id="receiptCard">

    <div class="receipt-header">
      <div class="logo">PropSight <span>/ Boracay Accommodation</span></div>
      <div class="ref">
        <div class="label">Booking Receipt</div>
        <div class="value"><?= $bkRef ?></div>
      </div>
    </div>

    <div class="status-banner"><?= $status ?></div>

    <div class="receipt-body">

      <div class="section-title">Guest Information</div>
      <div class="info-grid" style="margin-bottom:20px;">
        <div class="info-item">
          <div class="label">Full Name</div>
          <div class="value"><?= $guestName ?></div>
        </div>
        <div class="info-item">
          <div class="label">Email</div>
          <div class="value"><?= htmlspecialchars($row['email']) ?></div>
        </div>
        <div class="info-item">
          <div class="label">Phone</div>
          <div class="value"><?= htmlspecialchars($row['phone'] ?? '—') ?></div>
        </div>
        <div class="info-item">
          <div class="label">Booked On</div>
          <div class="value"><?= $bookedOn ?></div>
        </div>
      </div>

      <hr class="divider">

      <div class="section-title">Property Details</div>
      <div class="info-grid" style="margin-bottom:20px;">
        <div class="info-item">
          <div class="label">Property</div>
          <div class="value"><?= $propName ?></div>
        </div>
        <div class="info-item">
          <div class="label">Unit</div>
          <div class="value"><?= htmlspecialchars($unitLabel) ?></div>
        </div>
        <div class="info-item" style="grid-column:1/-1;">
          <div class="label">Address</div>
          <div class="value"><?= $propAddr ?></div>
        </div>
      </div>

      <hr class="divider">

      <div class="section-title">Stay Details</div>
      <div class="info-grid" style="margin-bottom:20px;">
        <div class="info-item">
          <div class="label">Check-in</div>
          <div class="value"><?= $checkin ?></div>
        </div>
        <div class="info-item">
          <div class="label">Check-out</div>
          <div class="value"><?= $checkout ?></div>
        </div>
        <div class="info-item">
          <div class="label">Nights</div>
          <div class="value"><?= $nights ?></div>
        </div>
        <div class="info-item">
          <div class="label">Guests</div>
          <div class="value"><?= $guests ?></div>
        </div>
      </div>

      <hr class="divider">

      <div class="section-title">Charges</div>
      <table class="items-table">
        <thead>
          <tr>
            <th>Description</th>
            <th>Rate</th>
            <th>Qty</th>
            <th style="text-align:right;">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= htmlspecialchars($unitLabel) ?> — Accommodation</td>
            <td>₱<?= $ratePerNight ?>/night</td>
            <td><?= $nights ?> nights</td>
            <td style="text-align:right;">₱<?= $total ?></td>
          </tr>
          <tr class="total-row">
            <td colspan="3" style="text-align:right;">Total Amount</td>
            <td style="text-align:right;">₱<?= $total ?></td>
          </tr>
        </tbody>
      </table>

      <div class="section-title">Payment</div>
      <div class="info-grid">
        <div class="info-item">
          <div class="label">Method</div>
          <div class="value"><?= $payMethod ?></div>
        </div>
        <div class="info-item">
          <div class="label">Payment Status</div>
          <div class="value" style="color:<?= $payStatus === 'Paid' ? '#16a34a' : '#d97706' ?>;"><?= $payStatus ?></div>
        </div>
        <?php if ($payDate !== '—'): ?>
          <div class="info-item">
            <div class="label">Payment Date</div>
            <div class="value"><?= $payDate ?></div>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <div class="receipt-footer">
      <strong>Thank you for choosing Boracay Accommodation!</strong><br>
      This is an official receipt generated by PropSight.<br>
      For inquiries, contact us through the messaging section of your account.<br>
      <span style="font-size:10px;margin-top:6px;display:block;">Generated on <?= gmdate('M d, Y H:i') ?> UTC · Booking
        <?= $bkRef ?></span>
    </div>

  </div>

  <!-- jsPDF + html2canvas from CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

  <script>
    const FILENAME = 'Receipt-<?= $bkRef ?>.pdf';
    const AUTO_DOWNLOAD = <?= $viewOnly ? 'false' : 'true' ?>;
  </script>
  <script src="../../assets/js/user-js/booking_receipt.js"></script>

</body>

</html>