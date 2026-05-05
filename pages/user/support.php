<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

<link rel="stylesheet" href="../../assets/css/user-css/support-inline.css">
</head>
<body>

<script src="../../assets/js/user-js/support-inline.js"></script>
</body>
</html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title = 'Support & Help';
$page_hero_html = 'Support <em>&amp; Help</em>';
$page_hero_sub = 'We\'re here for you 24/7. Find answers or reach our team directly.';
$page_hero_icon = '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>';
$active_nav = 'support';
require '../../includes/_layout.php';
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

$activeBookingRes = mysqli_query($conn, "
    SELECT booking_id, unit_id, checkin_date, checkout_date
    FROM bookings
    WHERE user_id = $userId
      AND status IN ('confirmed', 'active')
      AND checkout_date >= CURDATE()
    LIMIT 1
");
$activeBooking = $activeBookingRes ? mysqli_fetch_assoc($activeBookingRes) : null;
$hasActiveBooking = !empty($activeBooking);

$maintRes = $hasActiveBooking ? mysqli_query($conn, "
    SELECT request_id, issue_description, request_status, priority, request_date
    FROM maintenance_requests
    WHERE unit_id = " . (int) $activeBooking['unit_id'] . "
    ORDER BY request_date DESC
    LIMIT 10
") : null;
$myMaintenance = [];
if ($maintRes) {
    while ($mr = mysqli_fetch_assoc($maintRes))
        $myMaintenance[] = $mr;
}

$ticketsPerPage = 5;
$ticketPage = max(1, (int) ($_GET['ticket_page'] ?? 1));
$ticketOffset = ($ticketPage - 1) * $ticketsPerPage;

$ticketsCountRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM support_tickets WHERE user_id=$userId");
$ticketsTotal = (int) (mysqli_fetch_assoc($ticketsCountRes)['c'] ?? 0);
$ticketsTotalPages = max(1, (int) ceil($ticketsTotal / $ticketsPerPage));
if ($ticketPage > $ticketsTotalPages) {
    $ticketPage = $ticketsTotalPages;
    $ticketOffset = ($ticketPage - 1) * $ticketsPerPage;
}

$ticketsRes = mysqli_query($conn, "
    SELECT t.ticket_id, t.category, t.subject, t.status, t.created_at,
           (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id=t.ticket_id) AS msg_count
    FROM support_tickets t
    WHERE t.user_id=$userId ORDER BY t.created_at DESC LIMIT $ticketsPerPage OFFSET $ticketOffset
");
$myTickets = [];
while ($tk = mysqli_fetch_assoc($ticketsRes))
    $myTickets[] = $tk;

$faqs = [
    'Booking & Reservations' => [
        ['q' => 'How do I make a reservation?', 'a' => 'You can book directly through this website by browsing the Rooms section and clicking "Book Now." Select your check-in/check-out dates, number of guests, and confirm your booking using your saved payment method.'],
        ['q' => 'Can I modify my booking after confirmation?', 'a' => 'Yes, you can modify your booking up to 72 hours before your check-in date at no extra charge. Go to My Bookings and click "Manage Stay." Changes within 72 hours may incur a modification fee.'],
        ['q' => 'What is your cancellation policy?', 'a' => 'Free cancellation is available up to 48 hours before check-in. Cancellations within 48 hours are charged 50% of the total booking amount. No-shows are charged in full.'],
    ],
    'Check-in & Check-out' => [
        ['q' => 'What are the check-in and check-out times?', 'a' => 'Standard check-in is from 2:00 PM onwards. Check-out is at 12:00 PM noon. Early check-in and late check-out are subject to availability and may be arranged at the front desk.'],
        ['q' => 'Can I check in online?', 'a' => 'Yes! You can complete your pre-check-in online through My Bookings up to 24 hours before arrival. This speeds up the front-desk process significantly.'],
    ],
    'Payments & Billing' => [
        ['q' => 'What payment methods do you accept?', 'a' => 'We accept all major credit/debit cards (Visa, Mastercard), GCash, Maya, and PayPal. Cash payments are accepted at the property for incidentals only.'],
        ['q' => 'When is my card charged?', 'a' => 'Your card is pre-authorized at the time of booking. The full charge is applied upon check-in. Any additional charges (room service, etc.) are settled at check-out.'],
    ],
    'Loyalty Program' => [
        ['q' => 'How do I earn loyalty points?', 'a' => 'You earn 1 point for every ₱10 spent on room bookings. Bonus points are awarded during promotional periods, on your birthday, and when you refer friends.'],
        ['q' => 'When do points expire?', 'a' => 'Points are valid for 24 months from the date they were earned. Any account activity (booking or redemption) resets the expiry on all existing points.'],
    ],
];
?>

<link rel="stylesheet" href="../../assets/css/user-css/support.css">
<link rel="stylesheet" href="../../assets/css/user-css/support-inline.css">

<?php if (isset($_GET['suspended'])): ?>
    <div
        style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <svg fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"
            style="width:20px;height:20px;flex-shrink:0;">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <div>
            <div style="font-size:13px;font-weight:700;color:#dc2626;">Your account has been suspended.</div>
            <div style="font-size:12px;color:#ef4444;margin-top:2px;">Submit a support ticket below to appeal and request
                reactivation.</div>
        </div>
    </div>
<?php endif; ?>

<div class="contact-grid reveal">
    <div class="contact-card">
        <div class="contact-icon blue"><svg viewBox="0 0 24 24">
                <path
                    d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 015.17 12.9 19.79 19.79 0 012.1 4.27 2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" />
            </svg></div>
        <div class="contact-title">Call Us</div>
        <div class="contact-detail">+63 33 123 4567<br>+63 912 345 6789</div>
    </div>
    <div class="contact-card">
        <div class="contact-icon gold"><svg viewBox="0 0 24 24">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
            </svg></div>
        <div class="contact-title">Email Us</div>
        <div class="contact-detail">hello@filipinohomes.ph<br>support@filipinohomes.ph</div>
    </div>

</div>
<div class="page-two-col">
    <div class="col-main">

        <!-- ROW 1: My Tickets + Maintenance Requests -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:stretch;">

            <!-- MY TICKETS (existing) -->
            <div class="card reveal rd1" id="myTicketsList" data-current-page="<?php echo (int) $ticketPage; ?>">
                <div class="card-title">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z" />
                        <path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z" />
                        <path
                            d="M9.5 14c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.33 8 20.5v-5c0-.83.67-1.5 1.5-1.5z" />
                        <path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z" />
                        <path
                            d="M14 14.5c0-.83.67-1.5 1.5-1.5h5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-5c-.83 0-1.5-.67-1.5-1.5z" />
                        <path d="M15.5 19H14v1.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5-.67-1.5-1.5-1.5z" />
                        <path
                            d="M10 9.5C10 8.67 9.33 8 8.5 8h-5C2.67 8 2 8.67 2 9.5S2.67 11 3.5 11h5c.83 0 1.5-.67 1.5-1.5z" />
                        <path d="M8.5 5H10V3.5C10 2.67 9.33 2 8.5 2S7 2.67 7 3.5 7.67 5 8.5 5z" />
                    </svg>
                    My Tickets
                </div>
                <?php if (!empty($myTickets)): ?>
                    <?php foreach ($myTickets as $tk): ?>
                        <?php
                        $status = strtolower((string) ($tk['status'] ?? 'open'));
                        $badgeClass = $status === 'resolved' ? 'badge-green' : 'badge-gold';
                        $statusLabel = ucwords(str_replace('_', ' ', $status));
                        ?>
                        <div class="ticket-item" data-ticket-id="<?php echo (int) ($tk['ticket_id'] ?? 0); ?>">
                            <div>
                                <div class="ticket-subject">
                                    <?php echo htmlspecialchars(mb_strimwidth($tk['subject'] ?? 'Untitled ticket', 0, 70, '...')); ?>
                                </div>
                                <div class="ticket-meta">
                                    Submitted <?php echo date('M j, Y', strtotime($tk['created_at'] ?? 'now')); ?> ·
                                    <span
                                        class="ticket-num">#TKT-<?php echo str_pad((string) ($tk['ticket_id'] ?? '0'), 5, '0', STR_PAD_LEFT); ?></span>
                                </div>
                            </div>
                            <span class="badge <?php echo $badgeClass; ?>"
                                style="margin-left:auto;"><?php echo htmlspecialchars($statusLabel); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p id="myTicketsHint"
                    style="font-size:0.78rem;color:var(--text-soft);margin-top:14px;<?php echo !empty($myTickets) ? '' : 'display:none;'; ?>">
                    Showing <?php echo count($myTickets); ?> of <?php echo (int) $ticketsTotal; ?> ticket(s).
                </p>
                <p id="myTicketsEmpty"
                    style="font-size:0.78rem;color:var(--text-soft);margin-top:14px;<?php echo empty($myTickets) ? '' : 'display:none;'; ?>">
                    You do not have any support tickets yet.
                </p>
                <?php if ($ticketsTotalPages > 1): ?>
                    <?php
                    $qsPrev = $_GET;
                    $qsPrev['ticket_page'] = max(1, $ticketPage - 1);
                    $qsNext = $_GET;
                    $qsNext['ticket_page'] = min($ticketsTotalPages, $ticketPage + 1);
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;">
                        <a class="btn-secondary"
                            style="text-decoration:none;<?php echo $ticketPage <= 1 ? 'pointer-events:none;opacity:.45;' : ''; ?>"
                            href="?<?php echo htmlspecialchars(http_build_query($qsPrev)); ?>">Previous</a>
                        <span style="font-size:.74rem;color:var(--ink-faint);">Page <?php echo (int) $ticketPage; ?> of
                            <?php echo (int) $ticketsTotalPages; ?></span>
                        <a class="btn-secondary"
                            style="text-decoration:none;<?php echo $ticketPage >= $ticketsTotalPages ? 'pointer-events:none;opacity:.45;' : ''; ?>"
                            href="?<?php echo htmlspecialchars(http_build_query($qsNext)); ?>">Next</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- MAINTENANCE REQUESTS -->
            <div class="card reveal rd1">
                <div class="card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
                    </svg>
                    Maintenance Requests
                </div>
                <?php if (!$hasActiveBooking): ?>
                    <div class="no-booking-notice">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <div>
                            <div class="no-booking-title">No Active Booking</div>
                            <div class="no-booking-sub">Maintenance requests are only available while you have an active or
                                upcoming booking.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($myMaintenance)): ?>
                        <?php foreach ($myMaintenance as $mr): ?>
                            <?php
                            $mStatus = $mr['request_status'];
                            $mBadge = $mStatus === 'completed' ? 'badge-green' : ($mStatus === 'in_progress' ? 'badge-gold' : 'badge-gold');
                            $mLabel = ucwords(str_replace('_', ' ', $mStatus));
                            $mDesc = htmlspecialchars(mb_strimwidth($mr['issue_description'] ?? '', 0, 70, '...'));
                            ?>
                            <div class="ticket-item">
                                <div>
                                    <div class="ticket-subject">
                                        <?php echo $mDesc; ?>
                                    </div>
                                    <div class="ticket-meta">
                                        <?php echo date('M j, Y', strtotime($mr['request_date'])); ?> ·
                                        <span class="ticket-num">Priority:
                                            <?php echo ucfirst($mr['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="badge <?php echo $mBadge; ?>" style="margin-left:auto;">
                                    <?php echo $mLabel; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size:0.78rem;color:var(--text-soft);margin-top:14px;">
                            You have no maintenance requests yet.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- /row-1 -->

        <!-- FAQ (full width) -->
        <div class="card reveal rd2">
            <div class="card-title">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                Frequently Asked Questions
            </div>
            <?php foreach ($faqs as $category => $items): ?>
                <div class="faq-category">
                    <div class="faq-cat-title">
                        <svg viewBox="0 0 24 24">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                        <?php echo $category; ?>
                    </div>
                    <?php foreach ($items as $idx => $faq): ?>
                        <div class="faq-item" id="faq-<?php echo $category . $idx; ?>">
                            <div class="faq-q" onclick="toggleFaq('faq-<?php echo $category . $idx; ?>')">
                                <?php echo $faq['q']; ?>
                                <svg class="faq-chevron" viewBox="0 0 24 24">
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </div>
                            <div class="faq-a" id="faq-a-<?php echo $category . $idx; ?>">
                                <div class="faq-a-inner"><?php echo $faq['a']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ROW 2: Send Us a Message + Maintenance Request Form -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:start;">

            <!-- SEND US A MESSAGE (existing) -->
            <div class="card reveal rd3" id="contactFormCard">
                <div class="card-title">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    Send Us a Message
                </div>
                <p style="font-size:0.84rem;color:var(--text-soft);margin-bottom:16px;">Tell us what you need and
                    we'll get back to you within 2 hours.</p>
                <div style="margin-bottom:14px;">
                    <div
                        style="font-size:0.72rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-mid);margin-bottom:8px;">
                        Topic</div>
                    <div class="ticket-types">
                        <?php foreach (['Booking Inquiry', 'Payment Issue', 'Room Request', 'Feedback', 'Other'] as $t): ?>
                            <button class="ticket-type<?php echo $t === 'Booking Inquiry' ? ' selected' : ''; ?>"
                                onclick="selectTicketType(this)"><?php echo $t; ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-grid" style="margin-bottom:14px;">
                    <div class="form-field"><label>Full Name</label><input type="text" id="contact_name"
                            value="<?php echo $full_name; ?>"></div>
                    <div class="form-field"><label>Email</label><input type="email" id="contact_email"
                            value="<?php echo $email; ?>"></div>
                </div>
                <div class="form-grid cols-1" style="margin-bottom:14px;">
                    <div class="form-field"><label>Subject</label><input type="text" id="contact_subject"
                            placeholder="Brief description of your concern"></div>
                </div>
                <div class="form-field" style="margin-bottom:18px;">
                    <label>Message</label>
                    <textarea id="contact_message"
                        placeholder="Describe your concern in detail. Include your booking ID if applicable."></textarea>
                </div>
                <div id="contactError"
                    style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;">
                </div>
                <button class="btn-primary" id="sendMsgBtn" onclick="submitTicket()">
                    <svg viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                    Send Message
                </button>
            </div>

            <!-- MAINTENANCE REQUEST FORM -->
            <div class="card reveal rd4" id="maintenanceFormCard">
                <div class="card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
                    </svg>
                    Submit a Maintenance Request
                </div>

                <?php if (!$hasActiveBooking): ?>
                    <div class="no-booking-notice">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <div>
                            <div class="no-booking-title">No Active Booking</div>
                            <div class="no-booking-sub">You can only submit a maintenance request during an active stay.
                                Please make a booking first.</div>
                        </div>
                    </div>

                <?php else: ?>
                    <p style="font-size:0.84rem;color:var(--text-soft);margin-bottom:16px;">
                        Report a facility or room issue and our maintenance team will respond promptly.
                    </p>

                    <?php
                    // Pre-fill room from active booking if available
                    $activeRoom = htmlspecialchars($activeBooking['unit_id'] ?? '');
                    $checkOut = htmlspecialchars($activeBooking['checkout_date'] ?? '');
                    ?>
                    <div class="active-booking-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Active booking · Room <?php echo $activeRoom; ?> · Checks out
                        <?php echo date('M j, Y', strtotime($checkOut)); ?>
                    </div>

                    <div style="margin-bottom:14px;">
                        <div
                            style="font-size:0.72rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-mid);margin-bottom:8px;">
                            Issue Type</div>
                        <div class="ticket-types">
                            <?php foreach (['Plumbing', 'Electrical', 'Air Conditioning', 'Furniture', 'Other'] as $mt): ?>
                                <button class="ticket-type<?php echo $mt === 'Plumbing' ? ' selected' : ''; ?>"
                                    onclick="selectMaintenanceType(this)"><?php echo $mt; ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-grid" style="margin-bottom:14px;">
                        <div class="form-field">
                            <label>Full Name</label>
                            <input type="text" id="maint_name" value="<?php echo $full_name; ?>">
                        </div>
                        <div class="form-field">
                            <label>Room / Unit No.</label>
                            <input type="text" id="maint_room" value="Room <?php echo $activeRoom; ?>" readonly
                                style="background:var(--surface-alt,#f5f5f5);cursor:not-allowed;opacity:0.75;">
                        </div>
                    </div>

                    <div class="form-grid cols-1" style="margin-bottom:14px;">
                        <div class="form-field">
                            <label>Issue Summary</label>
                            <input type="text" id="maint_subject" placeholder="Brief description of the problem">
                        </div>
                    </div>

                    <div class="form-field" style="margin-bottom:14px;">
                        <label>Priority</label>
                        <select id="maint_priority"
                            style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:10px;font-size:0.88rem;color:var(--text-main);background:var(--surface);outline:none;">
                            <option value="Low">Low – Not urgent</option>
                            <option value="Normal" selected>Normal – Needs attention soon</option>
                            <option value="High">High – Affecting my stay</option>
                            <option value="Urgent">Urgent – Immediate risk</option>
                        </select>
                    </div>

                    <div class="form-field" style="margin-bottom:18px;">
                        <label>Details</label>
                        <textarea id="maint_message"
                            placeholder="Describe the issue in detail. When did it start? Any safety concerns?"></textarea>
                    </div>

                    <div id="maintenanceError"
                        style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;">
                    </div>

                    <button class="btn-primary" id="sendMaintBtn" onclick="submitMaintenance()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path
                                d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
                        </svg>
                        Submit Request
                    </button>
                <?php endif; ?>
            </div>

        </div><!-- /row-2 -->

    </div><!-- /col-main -->
</div><!-- /page-two-col -->

<div class="modal-overlay" id="ticketViewModal">
    <div class="modal-box ticket-modal-box" style="max-width:680px;">
        <button class="modal-close-btn" onclick="closeTicketModal()">✕</button>
        <div class="modal-title" id="ticketModalTitle">Ticket Details</div>
        <div class="modal-sub" id="ticketModalSub"></div>
        <div id="ticketModalBody" style="margin-bottom:14px;"></div>
    </div>
</div>

<script src="../../assets/js/user-js/support.js"></script>
<script>window.PS_RT_PAGE = 'support';</script>
<?php require '../../includes/_layout_end.php'; ?>