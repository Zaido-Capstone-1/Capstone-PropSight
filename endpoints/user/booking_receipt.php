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

  <div class="receipt-wrap">
    <div class="receipt" id="receiptCard">
      <div class="zigzag zigzag-top"></div>

      <div class="receipt-inner">

        <div class="r-store">
          <div class="r-store-name">PROPSIGHT</div>
          <div class="r-store-sub">Boracay Accommodation</div>
          <div class="r-store-addr"><?= $propAddr ?></div>
        </div>

        <div class="r-stamp-wrap">
          <span class="r-stamp" style="color:<?= $statusColor ?>;"><?= $status ?></span>
        </div>

        <div class="r-dash"></div>

        <div class="r-line"><span>Receipt No.</span><span><?= $bkRef ?></span></div>
        <div class="r-line"><span>Booked On</span><span><?= $bookedOn ?></span></div>

        <div class="r-dash"></div>

        <div class="r-section-label">Guest</div>
        <div class="r-line"><span>Name</span><span><?= $guestName ?></span></div>
        <div class="r-line"><span>Email</span><span><?= htmlspecialchars($row['email']) ?></span></div>
        <div class="r-line"><span>Phone</span><span><?= htmlspecialchars($row['phone'] ?? '—') ?></span></div>

        <div class="r-dash"></div>

        <div class="r-section-label">Stay</div>
        <div class="r-line"><span>Property</span><span><?= $propName ?></span></div>
        <div class="r-line"><span>Unit</span><span><?= htmlspecialchars($unitLabel) ?></span></div>
        <div class="r-line"><span>Check-in</span><span><?= $checkin ?></span></div>
        <div class="r-line"><span>Check-out</span><span><?= $checkout ?></span></div>
        <div class="r-line"><span>Nights</span><span><?= $nights ?></span></div>
        <div class="r-line"><span>Guests</span><span><?= $guests ?></span></div>

        <div class="r-dash"></div>

        <div class="r-section-label">Charges</div>
        <div class="r-item">
          <div class="r-item-desc"><?= htmlspecialchars($unitLabel) ?> — Accommodation</div>
          <div class="r-item-row">
            <span>₱<?= $ratePerNight ?> × <?= $nights ?> night<?= $nights == 1 ? '' : 's' ?></span>
            <span>₱<?= $total ?></span>
          </div>
        </div>

        <div class="r-dash r-dash-solid"></div>

        <div class="r-total"><span>TOTAL</span><span>₱<?= $total ?></span></div>

        <div class="r-dash"></div>

        <div class="r-section-label">Payment</div>
        <div class="r-line"><span>Method</span><span><?= $payMethod ?></span></div>
        <div class="r-line"><span>Status</span><span style="color:<?= $payStatus === 'Paid' ? '#16a34a' : '#d97706' ?>;"><?= $payStatus ?></span></div>
        <?php if ($payDate !== '—'): ?>
          <div class="r-line"><span>Paid On</span><span><?= $payDate ?></span></div>
        <?php endif; ?>

        <div class="r-dash"></div>

        <div class="r-barcode"></div>
        <div class="r-barcode-code">*<?= $bkRef ?>*</div>

        <div class="r-footer">
          <strong>Thank you for choosing us!</strong>
          This is an official receipt generated by PropSight.<br>
          For inquiries, message us through your account.
          <span class="r-footer-small">Generated <?= gmdate('M d, Y H:i') ?> UTC</span>
        </div>

      </div>

      <div class="zigzag zigzag-bottom"></div>
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