<?php
include '../../includes/session.php';


$page_title = 'Reservations';
$active_page = 'reservations';
include '../../includes/db.php';
include '../../includes/unit_status_sync.php';
include '../../includes/layout_open.php';

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

$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$whereClause = "WHERE 1=1";
if ($statusFilter !== 'all') {
    $statusEsc = mysqli_real_escape_string($conn, $statusFilter);
    $whereClause .= " AND b.status = '$statusEsc'";
}
if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $whereClause .= " AND (
        u2.first_name LIKE '%$searchEsc%' OR
        u2.last_name  LIKE '%$searchEsc%' OR
        u2.email      LIKE '%$searchEsc%' OR
        un.unit_name   LIKE '%$searchEsc%' OR
        un.unit_number LIKE '%$searchEsc%' OR
        p.property_name LIKE '%$searchEsc%' OR
        b.booking_id LIKE '%$searchEsc%'
    )";
}

autoCompleteExpiredBookings($conn);

$statsRes = mysqli_query($conn, "
    SELECT
        COUNT(*)                                      AS total,
        SUM(status = 'pending')                       AS pending,
        SUM(status IN ('confirmed','active'))         AS confirmed,
        SUM(status = 'completed')                     AS completed,
        SUM(status = 'cancelled')                     AS cancelled
    FROM bookings
");
$stats = mysqli_fetch_assoc($statsRes);

$bookingsSql = "
    SELECT
        b.booking_id,
        b.checkin_date,
        b.checkout_date,
        b.guests,
        b.total_amount,
        b.status,
        b.created_at,
        DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
        CONCAT(u2.first_name, ' ', u2.last_name)  AS user_name,
        u2.email                                   AS user_email,
        un.unit_name,
        un.unit_number,
        p.property_name
    FROM   bookings b
    JOIN   users      u2 ON u2.user_id      = b.user_id
    JOIN   units      un ON un.unit_id      = b.unit_id
    LEFT JOIN properties p ON p.property_id = un.property_id
    $whereClause
    ORDER  BY b.created_at DESC
";
$bookingsRes = mysqli_query($conn, $bookingsSql);
$bookings = [];
while ($row = mysqli_fetch_assoc($bookingsRes))
    $bookings[] = $row;

function badgeClass($s)
{
    return match ($s) {
        'confirmed', 'active' => 'success',
        'pending' => 'pending',
        'completed' => 'info',
        'cancelled' => 'danger',
        default => 'pending',
    };
}
function badgeLabel($s)
{
    return match ($s) {
        'active' => 'Active',
        'confirmed' => 'Confirmed',
        'pending' => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst($s),
    };
}
function fmtDate($d)
{
    return $d ? date('M j, Y', strtotime($d)) : '—';
}
?>

<link rel="stylesheet" href="../../assets/css/admin-css/reservation.css">

<div class="page-header">
    <div class="top-header">
        <h2>Reservations</h2>
        <div class="page-header-sub">Track all current and upcoming booking requests</div>
    </div>
</div>

<div class="page-inner res-page">
    <div class="cards-area">

        <div class="res-stats" id="statsRow">
            <div class="res-stat">
                <div>
                    <div class="res-stat-label">Total Reservations</div>
                    <div class="res-stat-value" id="stat-total"><?= (int) $stats['total'] ?></div>
                </div>
                <div class="res-stat-icon si-blue">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                </div>
            </div>
            <div class="res-stat">
                <div>
                    <div class="res-stat-label">Pending</div>
                    <div class="res-stat-value" id="stat-pending"><?= (int) $stats['pending'] ?></div>
                </div>
                <div class="res-stat-icon si-gold">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                </div>
            </div>
            <div class="res-stat">
                <div>
                    <div class="res-stat-label">Confirmed</div>
                    <div class="res-stat-value" id="stat-confirmed"><?= (int) $stats['confirmed'] ?></div>
                </div>
                <div class="res-stat-icon si-green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                </div>
            </div>
            <div class="res-stat">
                <div>
                    <div class="res-stat-label">Cancelled</div>
                    <div class="res-stat-value" id="stat-cancelled"><?= (int) $stats['cancelled'] ?></div>
                </div>
                <div class="res-stat-icon si-red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="res-card">
            <div class="res-card-header">
                <div class="res-card-title">
                    All Reservations
                </div>
                <div class="res-controls">
                    <form method="GET" style="display:contents;">
                        <div class="res-search">
                            <svg viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search guest, unit…" id="searchInput">
                        </div>
                        <select name="status" class="res-select" onchange="this.form.submit()">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed
                            </option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed
                            </option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled
                            </option>
                        </select>
                        <?php if ($search): ?>
                            <a href="?status=<?= htmlspecialchars($statusFilter) ?>" class="res-clear-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    style="width:12px;height:12px;">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                                Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="res-table-wrap">
                <table class="res-table" id="reservationsTable">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Guest</th>
                            <th>Unit</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th style="text-align:center;">Nights</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reservationsTbody">
                        <?php if (empty($bookings)): ?>
                            <tr id="emptyRow">
                                <td colspan="9">
                                    <div class="res-empty">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                        No reservations
                                        found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b):
                                $unitLabel = !empty($b['unit_name'])
                                    ? $b['unit_name']
                                    : (($b['property_name'] ?? '') . ' — ' . ($b['unit_number'] ?? ''));
                                ?>
                                <tr data-id="<?= $b['booking_id'] ?>">
                                    <td><span
                                            class="booking-id">#BK-<?= str_pad($b['booking_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                    </td>
                                    <td>
                                        <div class="guest-cell">
                                            <div class="guest-avatar"><?= strtoupper(substr($b['user_name'], 0, 1)) ?></div>
                                            <div>
                                                <div class="guest-name"><?= htmlspecialchars($b['user_name']) ?></div>
                                                <div class="guest-email"><?= htmlspecialchars($b['user_email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="unit-name"><?= htmlspecialchars($unitLabel) ?></div>
                                        <div class="unit-prop"><?= htmlspecialchars($b['property_name'] ?? '') ?></div>
                                    </td>
                                    <td><?= fmtDate($b['checkin_date']) ?></td>
                                    <td><?= fmtDate($b['checkout_date']) ?></td>
                                    <td style="text-align:center;font-weight:700;"><?= (int) $b['nights'] ?></td>
                                    <td><span class="amount-cell">₱<?= number_format((float) $b['total_amount'], 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="res-badge res-badge-<?= badgeClass($b['status']) ?>">
                                            <?= badgeLabel($b['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <?php if ($b['status'] === 'pending'): ?>
                                                <button class="action-btn btn-confirm"
                                                    onclick="updateStatus(<?= $b['booking_id'] ?>, 'confirmed', this)">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    Confirm
                                                </button>
                                                <button class="action-btn btn-cancel"
                                                    onclick="updateStatus(<?= $b['booking_id'] ?>, 'cancelled', this)">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <line x1="18" y1="6" x2="6" y2="18" />
                                                        <line x1="6" y1="6" x2="18" y2="18" />
                                                    </svg>
                                                    Cancel
                                                </button>
                                            <?php elseif ($b['status'] === 'confirmed'): ?>
                                                <button class="action-btn btn-complete"
                                                    onclick="updateStatus(<?= $b['booking_id'] ?>, 'completed', this)">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    Complete
                                                </button>
                                                <button class="action-btn btn-cancel"
                                                    onclick="updateStatus(<?= $b['booking_id'] ?>, 'cancelled', this)">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <line x1="18" y1="6" x2="6" y2="18" />
                                                        <line x1="6" y1="6" x2="18" y2="18" />
                                                    </svg>
                                                    Cancel
                                                </button>
                                            <?php else: ?>
                                                <span style="font-size:12px;color:#cbd5e1;">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="res-pagination" id="paginationBar">
                <span class="res-pagination-info" id="footerCount">
                    Showing <strong><?= count($bookings) ?></strong> reservation<?= count($bookings) !== 1 ? 's' : '' ?>
                    <?= $statusFilter !== 'all' ? '· filtered by <strong>' . htmlspecialchars(ucfirst($statusFilter)) . '</strong>' : '' ?>
                    <?= $search ? '· search: <strong>' . htmlspecialchars($search) . '</strong>' : '' ?>
                </span>
                <div class="res-pagination-btns" id="paginationBtns"></div>
            </div>
        </div>

        <div id="confirmModal" class="confirm-modal-overlay">
            <div class="confirm-modal">
                <div class="confirm-modal-header">
                    <h3 class="confirm-modal-title" id="confirmModalTitle">Confirm Action</h3>
                </div>
                <div class="confirm-modal-body">
                    <p id="confirmModalMessage">Are you sure you want to proceed?</p>
                </div>
                <div class="confirm-modal-footer">
                    <button class="confirm-modal-btn confirm-btn-cancel" id="confirmModalCancel">Cancel</button>
                    <button class="confirm-modal-btn confirm-btn-confirm" id="confirmModalConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.__PS_RESERVATIONS__ = {
        currentStatus: '<?= $statusFilter ?>',
        currentSearch: '<?= addslashes($search) ?>',
        allRows: <?= json_encode(array_map(function ($b) {
            $unitLabel = !empty($b['unit_name'])
                ? $b['unit_name']
                : trim(($b['property_name'] ?? '') . ' — Unit ' . ($b['unit_number'] ?? ''));

            return [
                'booking_id' => (int) ($b['booking_id'] ?? 0),
                'user_name' => (string) ($b['user_name'] ?? ''),
                'user_email' => (string) ($b['user_email'] ?? ''),
                'unit_name' => (string) $unitLabel,
                'unit_number' => (string) ($b['unit_number'] ?? ''),
                'property_name' => (string) ($b['property_name'] ?? ''),
                'checkin_date' => (string) ($b['checkin_date'] ?? ''),
                'checkout_date' => (string) ($b['checkout_date'] ?? ''),
                'nights' => (int) ($b['nights'] ?? 0),
                'total_amount' => (float) ($b['total_amount'] ?? 0),
                'status' => (string) ($b['status'] ?? 'pending'),
            ];
        }, $bookings), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
</script>
<script src="../../assets/js/toast.js"></script>
<script>window.PS_RT_PAGE = 'reservations';</script>
<script src="../../assets/js/admin/reservations.js"></script>

<?php include '../../includes/layout_close.php'; ?>