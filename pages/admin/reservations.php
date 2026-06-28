<?php
include '../../includes/session.php';


$page_title = 'Reservations';
$active_page = 'reservations';
include '../../includes/db.php';
include '../../includes/unit_status_sync.php';
include '../../includes/layout_open.php';
require_once '../../lib/admin-queries/reservations_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/reservation.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner res-page">
    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Reservations</h1>
            <p class="dash-subtitle">Track all current and upcoming booking requests.</p>
        </div>
    </div>
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
                    <div class="res-search">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search guest, unit…" id="searchInput">
                    </div>
                    <div class="res-status-wrap" id="resStatusWrap">
                        <button class="res-status-trigger" id="resStatusTrigger" type="button" aria-expanded="false">
                            <span id="resStatusLabel">All Status</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <div class="res-status-menu" id="resStatusMenu">
                            <button class="res-status-opt active" data-val="all" type="button">All Status</button>
                            <button class="res-status-opt" data-val="pending" type="button">
                                <span class="res-status-dot" style="background:#d97706;"></span>Pending
                            </button>
                            <button class="res-status-opt" data-val="confirmed" type="button">
                                <span class="res-status-dot" style="background:#2563eb;"></span>Confirmed
                            </button>
                            <button class="res-status-opt" data-val="active" type="button">
                                <span class="res-status-dot" style="background:#16a34a;"></span>Active
                            </button>
                            <button class="res-status-opt" data-val="completed" type="button">
                                <span class="res-status-dot" style="background:#64748b;"></span>Completed
                            </button>
                            <button class="res-status-opt" data-val="cancelled" type="button">
                                <span class="res-status-dot" style="background:#dc2626;"></span>Cancelled
                            </button>
                        </div>
                    </div>
                    <?php if ($search): ?>
                        <a href="#" class="res-clear-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                style="width:12px;height:12px;">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                            Clear
                        </a>
                    <?php endif; ?>
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
                                            <?php
                                            $parts = array_filter(explode(' ', trim($b['user_name'])));
                                            $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice($parts, 0, 2)));
                                            $photo = $b['user_photo'] ?? '';
                                            ?>
                                            <?php if ($photo): ?>
                                                <img src="../../<?= htmlspecialchars($photo) ?>" class="guest-avatar-img"
                                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                <div class="guest-avatar" style="display:none;"><?= $initials ?></div>
                                            <?php else: ?>
                                                <div class="guest-avatar"><?= $initials ?></div>
                                            <?php endif; ?>
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

        <div id="detailModal" class="confirm-modal-overlay res-detail-overlay">
            <div class="confirm-modal res-detail-modal">
                <div class="confirm-modal-header res-detail-header">
                    <h3 class="confirm-modal-title" id="detailModalTitle">Booking Details</h3>
                    <button type="button" class="res-detail-close" id="detailModalClose" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <div class="confirm-modal-body res-detail-body" id="detailModalBody">
                    <div class="res-detail-loading">Loading booking details…</div>
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
                : trim(($b['property_name'] ?? '') . ' — ' . ($b['unit_number'] ?? ''));

            return [
                'booking_id' => (int) ($b['booking_id'] ?? 0),
                'user_name' => (string) ($b['user_name'] ?? ''),
                'user_email' => (string) ($b['user_email'] ?? ''),
                'user_photo' => (string) ($b['user_photo'] ?? ''),
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
<script>
    window.APP_BASE = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME'], 3), '/') ?>';
</script>
<script src="../../assets/js/toast.js"></script>
<script>window.PS_RT_PAGE = 'reservations';</script>
<script src="../../assets/js/admin/reservations.js"></script>

<?php include '../../includes/layout_close.php'; ?>