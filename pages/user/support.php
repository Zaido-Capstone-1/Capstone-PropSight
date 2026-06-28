<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><head>
<link rel="stylesheet" href="../../assets/css/user-css/support-inline.css">
</head><body>
<script src="../../assets/js/user-js/support-inline.js"></script>
</body></html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$email = htmlspecialchars($_SESSION['email'] ?? '');
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title = 'Support & Help';
$page_hero_html = 'Support <em>&amp; Help</em>';
$page_hero_sub = 'We\'re here for you 24/7. Find answers or reach our team directly.';
$page_hero_icon = '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3v1"/><circle cx="12" cy="17" r="1" fill="currentColor"/>';
$active_nav = 'support';
require '../../includes/_layout.php';

// Load contact info from admin settings
$sysCfgRes = mysqli_query($conn, "SELECT setting_key, value FROM admin_settings WHERE setting_key IN ('contact_phone','contact_email','contact_phone2','support_hero_image')");
$sysCfg = [];
if ($sysCfgRes) {
    while ($sr = mysqli_fetch_assoc($sysCfgRes))
        $sysCfg[$sr['setting_key']] = $sr['value'];
}
$contactPhone = htmlspecialchars($sysCfg['contact_phone'] ?? '+63 33 123 4567');
$contactPhone2 = htmlspecialchars($sysCfg['contact_phone2'] ?? '+63 912 345 6789');
$contactEmail = htmlspecialchars($sysCfg['contact_email'] ?? 'hello@boracayaccommodation.ph');
$heroImage = $sysCfg['support_hero_image'] ?? '';

require_once '../../lib/user-queries/support_queries.php';

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
    <div class="support-suspended-alert">
        <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <div>
            <div class="support-suspended-alert-title">Your account has been suspended.</div>
            <div class="support-suspended-alert-sub">Submit a support ticket below to appeal and request reactivation.</div>
        </div>
    </div>
<?php endif; ?>

<!-- ── Hero ──────────────────────────────────────────── -->
<div class="support-hero reveal">
    <div class="support-hero-text">
        <div class="support-hero-eyebrow">Help Center</div>
        <h1><?php echo $greeting; ?>, <?php echo $first_name; ?> <em>👋</em></h1>
        <p class="support-hero-sub">We're here for you 24/7 — find answers instantly or reach our team directly below.
        </p>
    </div>
    <div class="support-hero-art">
        <?php if ($heroImage): ?>
            <img src="<?php echo htmlspecialchars('../../' . ltrim($heroImage, '/')); ?>" alt="Support"
                style="width:100%;height:100%;object-fit:cover;border-radius:16px;">
        <?php else: ?>
            <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" fill="none">
                <circle cx="40" cy="40" r="36" stroke-dasharray="4 6" stroke-width="1" />
                <circle cx="40" cy="40" r="22" stroke-width="1" />
                <circle cx="40" cy="40" r="8" fill="rgba(232,200,130,.15)" stroke-width="1.5" />
                <path d="M28 22 Q40 14 52 22" stroke-width="1" />
                <path d="M28 58 Q40 66 52 58" stroke-width="1" />
                <line x1="14" y1="40" x2="22" y2="40" stroke-width="1.5" />
                <line x1="58" y1="40" x2="66" y2="40" stroke-width="1.5" />
            </svg>
        <?php endif; ?>
    </div>
</div>

<!-- ── Contact cards ─────────────────────────────────── -->
<div class="contact-grid reveal">
    <div class="contact-card">
        <div class="contact-icon blue">
            <svg viewBox="0 0 24 24">
                <path
                    d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 015.17 12.9 19.79 19.79 0 012.1 4.27 2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" />
            </svg>
        </div>
        <div class="contact-title">Call Us</div>
        <div class="contact-detail"><?php echo $contactPhone; ?><br><?php echo $contactPhone2; ?></div>
    </div>
    <div class="contact-card">
        <div class="contact-icon gold">
            <svg viewBox="0 0 24 24">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
            </svg>
        </div>
        <div class="contact-title">Email Us</div>
        <div class="contact-detail"><?php echo $contactEmail; ?></div>
    </div>
</div>

<div class="page-two-col">
    <div class="col-main">

        <!-- ── Row 1: My Tickets + Maintenance ──────────────── -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;margin-bottom:16px;">

            <!-- MY TICKETS -->
            <div class="card reveal rd1" id="myTicketsList" data-current-page="<?php echo (int) $ticketPage; ?>">
                <div class="card-title">
                    <svg viewBox="0 0 24 24">
                        <rect x="2" y="7" width="20" height="14" rx="2" />
                        <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
                        <line x1="12" y1="12" x2="12" y2="16" />
                        <line x1="10" y1="14" x2="14" y2="14" />
                    </svg>
                    My Tickets
                    <button onclick="openNewTicketModal()"
                        style="margin-left:auto;display:inline-flex;align-items:center;gap:5px;font-family:var(--font-body);font-size:.72rem;font-weight:600;padding:6px 14px;border-radius:var(--r-pill);border:none;background:var(--navy-800);color:var(--sand);cursor:pointer;transition:background .2s;white-space:nowrap;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="3">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        New Ticket
                    </button>
                </div>

                <?php if (!empty($myTickets)): ?>
                    <?php foreach ($myTickets as $tk): ?>
                        <?php
                        $status = strtolower((string) ($tk['status'] ?? 'open'));
                        $badgeClass = $status === 'resolved' ? 'badge-green' : ($status === 'closed' ? 'badge-navy' : 'badge-gold');
                        $statusLabel = ucwords(str_replace('_', ' ', $status));
                        ?>
                        <div class="ticket-item" data-ticket-id="<?php echo (int) ($tk['ticket_id'] ?? 0); ?>">
                            <div style="flex:1;min-width:0;">
                                <div class="ticket-subject">
                                    <?php echo htmlspecialchars(mb_strimwidth($tk['subject'] ?? 'Untitled ticket', 0, 70, '...')); ?>
                                </div>
                                <div class="ticket-meta">
                                    Submitted <?php echo date('M j, Y', strtotime($tk['created_at'] ?? 'now')); ?> &middot;
                                    <span
                                        class="ticket-num">#TKT-<?php echo str_pad((string) ($tk['ticket_id'] ?? '0'), 5, '0', STR_PAD_LEFT); ?></span>
                                </div>
                            </div>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <p class="support-hint" id="myTicketsHint">Showing <?php echo count($myTickets); ?> of
                        <?php echo (int) $ticketsTotal; ?> ticket(s).
                    </p>
                <?php else: ?>
                    <div style="text-align:center;padding:28px 16px;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--border)"
                            stroke-width="1.5" style="margin-bottom:10px;">
                            <rect x="2" y="7" width="20" height="14" rx="2" />
                            <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
                        </svg>
                        <p id="myTicketsEmpty" style="font-size:.8rem;color:var(--ink-faint);margin:0;">No support tickets
                            yet.</p>
                    </div>
                <?php endif; ?>

                <?php if ($ticketsTotalPages > 1): ?>
                    <?php
                    $qsPrev = $_GET;
                    $qsPrev['ticket_page'] = max(1, $ticketPage - 1);
                    $qsNext = $_GET;
                    $qsNext['ticket_page'] = min($ticketsTotalPages, $ticketPage + 1);
                    ?>
                    <div class="support-pagination">
                        <a class="btn-secondary"
                            style="text-decoration:none;<?php echo $ticketPage <= 1 ? 'pointer-events:none;opacity:.4;' : ''; ?>"
                            href="?<?php echo htmlspecialchars(http_build_query($qsPrev)); ?>">← Prev</a>
                        <span class="support-pagination-info">Page <?php echo (int) $ticketPage; ?> of
                            <?php echo (int) $ticketsTotalPages; ?></span>
                        <a class="btn-secondary"
                            style="text-decoration:none;<?php echo $ticketPage >= $ticketsTotalPages ? 'pointer-events:none;opacity:.4;' : ''; ?>"
                            href="?<?php echo htmlspecialchars(http_build_query($qsNext)); ?>">Next →</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- MAINTENANCE REQUESTS LIST -->
            <div class="card reveal rd1">
                <div class="card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
                    </svg>
                    Maintenance Requests
                    <?php if ($hasActiveBooking): ?>
                        <button onclick="openNewMaintenanceModal()"
                            style="margin-left:auto;display:inline-flex;align-items:center;gap:5px;font-family:var(--font-body);font-size:.72rem;font-weight:600;padding:6px 14px;border-radius:var(--r-pill);border:none;background:var(--navy-800);color:var(--sand);cursor:pointer;transition:background .2s;white-space:nowrap;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="3">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            New Request
                        </button>
                    <?php endif; ?>
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
                            <div class="no-booking-sub">Maintenance requests are only available during an active or upcoming
                                stay.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($myMaintenance)): ?>
                        <?php foreach ($myMaintenance as $mr): ?>
                            <?php
                            $mStatus = $mr['request_status'];
                            $mBadge = $mStatus === 'completed' ? 'badge-green' : ($mStatus === 'in_progress' ? 'badge-gold' : 'badge-terra');
                            $mLabel = ucwords(str_replace('_', ' ', $mStatus));
                            $mDesc = htmlspecialchars(mb_strimwidth($mr['issue_description'] ?? '', 0, 70, '...'));
                            ?>
                            <div class="ticket-item">
                                <div style="flex:1;min-width:0;">
                                    <div class="ticket-subject"><?php echo $mDesc; ?></div>
                                    <div class="ticket-meta">
                                        <span class="ps-date" data-date="<?php echo htmlspecialchars($mr['request_date']); ?>">
                                            <?php echo htmlspecialchars($mr['request_date']); ?>
                                        </span> &middot;
                                        <span class="ticket-num">Priority: <?php echo ucfirst($mr['priority']); ?></span>
                                    </div>
                                </div>
                                <span class="badge <?php echo $mBadge; ?>"><?php echo $mLabel; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:28px 16px;">
                            <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="var(--border)"
                                stroke-width="1.5" style="margin-bottom:10px;">
                                <path
                                    d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
                            </svg>
                            <p style="font-size:.8rem;color:var(--ink-faint);margin:0;">No maintenance requests yet.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- /row-1 -->

        <!-- ── FAQ ──────────────────────────────────────────── -->
        <div class="card reveal rd2" style="margin-bottom:16px;">
            <div class="card-title">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                Frequently Asked Questions
                <span class="badge badge-navy"
                    style="margin-left:auto;font-size:.6rem;"><?php echo array_sum(array_map('count', $faqs)); ?>
                    articles</span>
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

    </div><!-- /col-main -->
</div><!-- /page-two-col -->

<!-- ── Ticket view modal ────────────────────────────── -->
<div class="modal-overlay" id="ticketViewModal">
    <div class="modal-box ticket-modal-box" style="max-width:680px;">
        <button class="modal-close-btn" onclick="closeTicketModal()">✕</button>
        <div class="modal-title" id="ticketModalTitle">Ticket Details</div>
        <div class="modal-sub" id="ticketModalSub"></div>
        <div id="ticketModalBody" style="margin-bottom:14px;"></div>
    </div>
</div>

<!-- ── New Ticket Modal ──────────────────────────────── -->
<div class="modal-overlay" id="newTicketModal">
    <div class="modal-box" style="max-width:560px;">
        <button class="modal-close-btn" onclick="closeNewTicketModal()">✕</button>
        <div class="modal-title">New Support Ticket</div>

        <div style="margin-bottom:16px;margin-top:6px;">
            <div
                style="font-size:.67rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--ink-soft);margin-bottom:10px;">
                Topic</div>
            <div class="ticket-types" id="modalTicketTypes">
                <?php foreach (['Booking Inquiry', 'Payment Issue', 'Room Request', 'Feedback', 'Other'] as $t): ?>
                    <button class="ticket-type<?php echo $t === 'Booking Inquiry' ? ' selected' : ''; ?>"
                        onclick="selectModalTicketType(this)"><?php echo $t; ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-grid" style="margin-bottom:14px;">
            <div class="form-field"><label>Full Name</label><input type="text" id="modal_contact_name"
                    value="<?php echo $full_name; ?>"></div>
            <div class="form-field"><label>Email</label><input type="email" id="modal_contact_email"
                    value="<?php echo $email; ?>"></div>
        </div>
        <div class="form-grid cols-1" style="margin-bottom:14px;">
            <div class="form-field"><label>Subject</label><input type="text" id="modal_contact_subject"
                    placeholder="Brief description of your concern"></div>
        </div>
        <div class="form-field" style="margin-bottom:18px;">
            <label>Message</label>
            <textarea id="modal_contact_message"
                placeholder="Describe your concern in detail. Include your booking ID if applicable."></textarea>
        </div>
        <div id="modalTicketError" class="form-error"></div>
        <button class="btn-primary" id="modalSendMsgBtn" onclick="submitModalTicket()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" fill="none" />
            </svg>
            Send Message
        </button>
    </div>
</div>

<!-- ── New Maintenance Modal ─────────────────────────── -->
<?php if ($hasActiveBooking): ?>
    <div class="modal-overlay" id="newMaintenanceModal">
        <div class="modal-box" style="max-width:560px;">
            <button class="modal-close-btn" onclick="closeNewMaintenanceModal()">✕</button>
            <div class="modal-title">Submit Maintenance Request</div>

            <div class="active-booking-badge" style="margin-top:6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Active booking &middot; Room <?php echo $activeRoom; ?> &middot; Checks out
                <?php echo date('M j, Y', strtotime($checkOut)); ?>
            </div>

            <div style="margin-bottom:16px;">
                <div
                    style="font-size:.67rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--ink-soft);margin-bottom:10px;">
                    Issue Type</div>
                <div class="ticket-types" id="modalMaintenanceTypes">
                    <?php foreach (['Plumbing', 'Electrical', 'Air Conditioning', 'Furniture', 'Other'] as $mt): ?>
                        <button class="ticket-type<?php echo $mt === 'Plumbing' ? ' selected' : ''; ?>"
                            onclick="selectModalMaintenanceType(this)"><?php echo $mt; ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-grid" style="margin-bottom:14px;">
                <div class="form-field"><label>Full Name</label><input type="text" id="modal_maint_name"
                        value="<?php echo $full_name; ?>"></div>
                <div class="form-field"><label>Room / Unit No.</label><input type="text" id="modal_maint_room"
                        value="Room <?php echo $activeRoom; ?>" readonly></div>
            </div>
            <div class="form-grid cols-1" style="margin-bottom:14px;">
                <div class="form-field"><label>Issue Summary</label><input type="text" id="modal_maint_subject"
                        placeholder="Brief description of the problem"></div>
            </div>
            <div class="form-field" style="margin-bottom:14px;">
                <label>Priority</label>
                <select id="modal_maint_priority">
                    <option value="Low">Low – Not urgent</option>
                    <option value="Normal" selected>Normal – Needs attention soon</option>
                    <option value="High">High – Affecting my stay</option>
                    <option value="Urgent">Urgent – Immediate risk</option>
                </select>
            </div>
            <div class="form-field" style="margin-bottom:18px;">
                <label>Details</label>
                <textarea id="modal_maint_message"
                    placeholder="Describe the issue in detail. When did it start? Any safety concerns?"></textarea>
            </div>
            <div id="modalMaintenanceError" class="form-error"></div>
            <button class="btn-primary" id="modalSendMaintBtn" onclick="submitModalMaintenance()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                    <path
                        d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />
                </svg>
                Submit Request
            </button>
        </div>
    </div>
<?php endif; ?>

<script src="../../assets/js/user-js/support.js"></script>
<script>window.PS_RT_PAGE = 'support';</script>
<?php require '../../includes/_layout_end.php'; ?>