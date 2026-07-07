<?php
/**
 * endpoints/user/booking_receipt.php
 * Renders a receipt page for viewing (used standalone and inside the
 * shared receipt modal). ?view=1 is used by the modal to fetch the
 * markup without the page's own action bar.
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
$unitNumberRaw = trim((string) ($row['unit_number'] ?? ''));
$unitLabel = !empty($row['unit_name'])
  ? $row['unit_name']
  : (($unitNumberRaw !== '' && stripos($unitNumberRaw, 'unit') === 0)
    ? $unitNumberRaw
    : 'Unit ' . ($unitNumberRaw !== '' ? $unitNumberRaw : $bookingId));
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
    <button class="action-btn secondary" onclick="history.back()">
      <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
        <polyline points="15 18 9 12 15 6" />
      </svg>
      Back
    </button>
  </div>

  <div class="receipt-wrap">
    <div class="receipt" id="receiptCard">
      <div class="r-watermark"></div>

      <div class="r-main">

        <div class="r-header-row">
          <div class="r-brand">
            <div class="r-brand-name">PROPSIGHT</div>
            <div class="r-brand-sub">Boracay Accommodation</div>
          </div>
          <div class="r-pass-label">
            <div class="r-pass-label-title">Receipt</div>
            <span class="r-stamp" style="color:<?= $statusColor ?>;"><?= $status ?></span>
          </div>
        </div>

        <div class="r-dash"></div>

        <div class="r-route">
          <div class="r-route-pt">
            <div class="r-route-date"><?= $checkin ?></div>
            <div class="r-route-sub">Check-in</div>
          </div>
          <div class="r-route-arrow">
            <span class="r-route-line"></span>
            <span class="r-route-nights"><?= $nights ?> Night<?= $nights == 1 ? '' : 's' ?></span>
            <span class="r-route-line"></span>
          </div>
          <div class="r-route-pt" style="text-align:right;">
            <div class="r-route-date"><?= $checkout ?></div>
            <div class="r-route-sub">Check-out</div>
          </div>
        </div>
        <div class="r-route-prop"><?= $propName ?> &middot; <?= htmlspecialchars($unitLabel) ?></div>

        <div class="r-dash"></div>

        <div class="r-fields">
          <div class="r-field"><span class="r-field-label">Guest</span><span
              class="r-field-value"><?= $guestName ?></span></div>
          <div class="r-field"><span class="r-field-label">Nights</span><span
              class="r-field-value"><?= $nights ?></span></div>
          <div class="r-field"><span class="r-field-label">Guests</span><span
              class="r-field-value"><?= $guests ?></span></div>
          <div class="r-field"><span class="r-field-label">Unit</span><span
              class="r-field-value"><?= htmlspecialchars($unitLabel) ?></span></div>
        </div>

        <div class="r-fields">
          <div class="r-field"><span class="r-field-label">Email</span><span
              class="r-field-value"><?= htmlspecialchars($row['email']) ?></span></div>
          <div class="r-field"><span class="r-field-label">Phone</span><span
              class="r-field-value"><?= htmlspecialchars($row['phone'] ?? '—') ?></span></div>
          <div class="r-field"><span class="r-field-label">Receipt No.</span><span
              class="r-field-value"><?= $bkRef ?></span></div>
          <div class="r-field"><span class="r-field-label">Booked On</span><span
              class="r-field-value"><?= $bookedOn ?></span></div>
        </div>

        <div class="r-dash"></div>

        <div class="r-fields">
          <div class="r-field"><span class="r-field-label">Rate</span><span
              class="r-field-value">&#8369;<?= $ratePerNight ?> / night</span></div>
          <div class="r-field"><span class="r-field-label">Method</span><span
              class="r-field-value"><?= $payMethod ?></span></div>
          <div class="r-field"><span class="r-field-label">Payment</span><span class="r-field-value"
              style="color:<?= $payStatus === 'Paid' ? '#16a34a' : '#d97706' ?>;"><?= $payStatus ?></span></div>
          <?php if ($payDate !== '—'): ?>
            <div class="r-field"><span class="r-field-label">Paid On</span><span
                class="r-field-value"><?= $payDate ?></span></div>
          <?php endif; ?>
        </div>

      </div>

      <div class="r-stub">
        <div class="r-stub-label">Total Due</div>
        <div class="r-stub-total">&#8369;<?= $total ?></div>
        <div class="r-stub-sub">&#8369;<?= $ratePerNight ?> &times; <?= $nights ?> night<?= $nights == 1 ? '' : 's' ?>
        </div>

        <div class="r-stub-divider"></div>

        <div class="r-stub-seal">
          <svg viewBox="0 0 24 24">
            <path d="M20 6L9 17l-5-5" />
          </svg>
        </div>
        <div class="r-stub-seal-label">Verified Receipt</div>

        <div class="r-stub-divider"></div>

        <div class="r-stub-address-label">Property Address</div>
        <div class="r-stub-address"><?= $propAddr ?></div>

        <div class="r-stub-spacer"></div>

        <div class="r-stub-barcode"></div>
        <div class="r-stub-code">*<?= $bkRef ?>*</div>
        <div class="r-stub-foot">Thank you for choosing us!</div>
      </div>

    </div>
  </div>

</body>

</html>