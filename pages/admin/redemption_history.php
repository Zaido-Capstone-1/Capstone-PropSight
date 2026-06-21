<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html><html><body></body></html>';
    exit;
}

$page_title = 'Redemption History';
$active_page = 'redemption_history';
include '../../includes/db.php';
include '../../includes/layout_open.php';

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page = 15;
$current_pg = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($current_pg - 1) * $per_page;

// ── Filters ───────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$status_f = $_GET['status'] ?? '';
$reward_f = (int) ($_GET['reward_id'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR r.voucher_code LIKE ? OR r.reward_name LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= 'ssss';
}
if ($status_f !== '') {
    $where[] = "r.status = ?";
    $params[] = $status_f;
    $types .= 's';
}
if ($reward_f > 0) {
    $where[] = "r.reward_id = ?";
    $params[] = $reward_f;
    $types .= 'i';
}
if ($date_from !== '') {
    $where[] = "DATE(r.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $where[] = "DATE(r.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Total count ───────────────────────────────────────────────────────────────
$count_sql = "
    SELECT COUNT(*) AS cnt
    FROM loyalty_redemptions r
    JOIN users u ON u.user_id = r.user_id
    $where_sql";
$cs = $conn->prepare($count_sql);
if ($params)
    $cs->bind_param($types, ...$params);
$cs->execute();
$total_rows = (int) $cs->get_result()->fetch_assoc()['cnt'];
$cs->close();
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$current_pg = min($current_pg, $total_pages);
$offset = ($current_pg - 1) * $per_page;

// ── Fetch rows ────────────────────────────────────────────────────────────────
$rows = [];
$data_sql = "
    SELECT r.id, r.reward_id, r.reward_name, r.points_used, r.voucher_code,
           r.status, r.created_at,
           CONCAT(u.first_name,' ',u.last_name) AS guest_name,
           u.email, u.user_id, u.profile_photo
    FROM loyalty_redemptions r
    JOIN users u ON u.user_id = r.user_id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?";
$ps = $conn->prepare($data_sql);
$all_params = array_merge($params, [$per_page, $offset]);
$all_types = $types . 'ii';
$ps->bind_param($all_types, ...$all_params);
$ps->execute();
$res = $ps->get_result();
while ($row = $res->fetch_assoc())
    $rows[] = $row;
$ps->close();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*)                                      AS total,
        SUM(status = 'active')                        AS active_cnt,
        SUM(status = 'used')                          AS used_cnt,
        SUM(status = 'expired')                       AS expired_cnt,
        COALESCE(SUM(points_used), 0)                 AS total_pts
    FROM loyalty_redemptions")->fetch_assoc();

// ── Reward list for filter dropdown ──────────────────────────────────────────
$reward_list = [];
$rs = $conn->query("SELECT reward_id, name FROM loyalty_rewards ORDER BY name ASC");
while ($rr = $rs->fetch_assoc())
    $reward_list[] = $rr;
?>

<link rel="stylesheet" href="../../assets/css/admin-css/header.css">
<link rel="stylesheet" href="../../assets/css/admin-css/loyalty_rewards.css">

<style>
    /* ── Page-specific overrides ──────────────────────────────────────── */

    /* Filters: two-row layout */
    .rdh-filter-bar {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
        margin-top: 4px;
    }

    .rdh-filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .rdh-input {
        height: 36px;
        padding: 0 12px;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        font-size: 13px;
        color: #0f172a;
        background: #fff;
        outline: none;
        transition: border-color .15s;
        box-sizing: border-box;
    }

    .rdh-input:focus {
        border-color: #2563eb;
    }

    .rdh-search-wrap {
        position: relative;
        flex: 1;
        min-width: 200px;
    }

    .rdh-search-wrap svg {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        stroke: #94a3b8;
        pointer-events: none;
    }

    .rdh-search-wrap .rdh-input {
        padding-left: 32px;
        width: 100%;
    }

    .rdh-select {
        height: 36px;
        padding: 0 30px 0 12px;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        font-size: 13px;
        color: #0f172a;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpolyline points='1 1 5 5 9 1' fill='none' stroke='%2394a3b8' stroke-width='1.5'/%3E%3C/svg%3E") no-repeat right 10px center;
        appearance: none;
        outline: none;
        cursor: pointer;
        transition: border-color .15s;
    }

    .rdh-select:focus {
        border-color: #2563eb;
    }

    .rdh-date-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .rdh-date-sep {
        font-size: 12px;
        color: #94a3b8;
        white-space: nowrap;
    }

    .rdh-date-wrap .rdh-input {
        width: 140px;
    }

    .rdh-btn-reset {
        height: 36px;
        padding: 0 14px;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        background: #f8fafc;
        color: #64748b;
        font-size: 12.5px;
        font-weight: 500;
        cursor: pointer;
        white-space: nowrap;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: background .15s;
    }

    .rdh-btn-reset:hover {
        background: #f1f5f9;
    }

    /* Table */
    .rdh-table-wrap {
        overflow-x: auto;
    }

    .rdh-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .rdh-table thead th {
        padding: 10px 14px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #6b7a90;
        border-bottom: 2px solid #f1f5f9;
        white-space: nowrap;
    }

    .rdh-table tbody tr {
        border-bottom: 1px solid #f8fafc;
        transition: background .12s;
    }

    .rdh-table tbody tr:hover {
        background: #fafbfc;
    }

    .rdh-table tbody td {
        padding: 11px 14px;
        vertical-align: middle;
    }

    /* Guest cell */
    .rdh-guest {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .rdh-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, #a78bfa, #7c3aed);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .rdh-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        display: block;
    }

    .rdh-guest-name {
        font-weight: 600;
        color: #0f172a;
        font-size: 13px;
    }

    .rdh-guest-email {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 1px;
    }

    /* Voucher */
    .rdh-voucher {
        font-family: 'SF Mono', ui-monospace, monospace;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .04em;
        color: #1d4ed8;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        padding: 3px 8px;
        display: inline-block;
        cursor: pointer;
    }

    .rdh-voucher:hover {
        background: #dbeafe;
    }

    /* Points chip */
    .rdh-pts {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #fefce8;
        border: 1px solid #fde68a;
        border-radius: 20px;
        padding: 3px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #92400e;
    }

    /* Status badges */
    .rdh-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
    }

    .rdh-badge-active {
        background: #dcfce7;
        color: #15803d;
    }

    .rdh-badge-used {
        background: #f1f5f9;
        color: #475569;
    }

    .rdh-badge-expired {
        background: #fee2e2;
        color: #b91c1c;
    }

    /* Pagination */
    .rdh-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        border-top: 1px solid #f1f5f9;
        font-size: 13px;
        color: #64748b;
        flex-wrap: wrap;
        gap: 10px;
    }

    .rdh-pages {
        display: flex;
        gap: 4px;
    }

    .rdh-page-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        background: #fff;
        color: #475569;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .15s;
        text-decoration: none;
    }

    .rdh-page-btn:hover:not(:disabled) {
        background: #f1f5f9;
    }

    .rdh-page-btn.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .rdh-page-btn:disabled {
        opacity: .4;
        cursor: default;
    }

    /* Empty state */
    .rdh-empty {
        text-align: center;
        padding: 52px 20px;
        color: #94a3b8;
    }

    .rdh-empty svg {
        width: 44px;
        height: 44px;
        margin-bottom: 12px;
        stroke: #cbd5e1;
    }

    /* card-header override: stack title above filters */
    .rdh-card-header {
        padding: 16px 20px 14px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* Responsive */
    @media (max-width: 640px) {
        .rw-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .rdh-filter-row {
            flex-direction: column;
            align-items: stretch;
        }

        .rdh-search-wrap {
            max-width: 100%;
        }

        .rdh-date-wrap {
            flex-wrap: wrap;
        }

        .rdh-date-wrap .rdh-input {
            width: 100%;
            flex: 1;
        }
    }
</style>

<div class="page-inner">

    <!-- Header -->
    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Redemption History</h1>
            <p class="dash-subtitle">Track all loyalty reward redemptions made by guests.</p>
        </div>
        <div class="dash-header-actions">
            <a href="loyalty_rewards.php" class="btn btn-outline"
                style="display:inline-flex;align-items:center;gap:7px;text-decoration:none;font-size:13px;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14">
                    <circle cx="12" cy="8" r="5" />
                    <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" />
                </svg>
                Manage Rewards
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="rw-stats">
        <div class="rw-stat">
            <div class="rw-stat-left">
                <div class="rw-stat-label">Total Redemptions</div>
                <div class="rw-stat-value"><?= number_format($stats['total']) ?></div>
            </div>
            <div class="rw-stat-icon si-blue">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M20 12V22H4V12" />
                    <path d="M22 7H2v5h20V7z" stroke-linejoin="round" />
                    <path d="M12 22V7" />
                    <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z" />
                    <path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" />
                </svg>
            </div>
        </div>
        <div class="rw-stat">
            <div class="rw-stat-left">
                <div class="rw-stat-label">Active Vouchers</div>
                <div class="rw-stat-value"><?= number_format($stats['active_cnt']) ?></div>
            </div>
            <div class="rw-stat-icon si-green">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </div>
        </div>
        <div class="rw-stat">
            <div class="rw-stat-left">
                <div class="rw-stat-label">Used / Expired</div>
                <div class="rw-stat-value">
                    <?= number_format((int) $stats['used_cnt'] + (int) $stats['expired_cnt']) ?></div>
                </div>
                <div class="rw-stat-icon si-red">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                </div>
            </div>
            <div class="rw-stat">
                <div class="rw-stat-left">
                    <div class="rw-stat-label">Total Points Redeemed</div>
                    <div class="rw-stat-value"><?= number_format($stats['total_pts']) ?></div>
                </div>
                <div class="rw-stat-icon si-gold">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="5" />
                        <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Table card -->
        <div class="rw-card">
            <div class="rdh-card-header">
                <div class="rw-card-title">All Redemptions</div>

                <!-- Filters: row 1 = search + status + reward | row 2 = date range + clear -->
                <form method="GET" action="" id="filterForm">
                    <div class="rdh-filter-bar">

                        <!-- Row 1 -->
                        <div class="rdh-filter-row">
                            <div class="rdh-search-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" width="14" height="14">
                                    <circle cx="11" cy="11" r="7" />
                                    <line x1="16.5" y1="16.5" x2="22" y2="22" />
                                </svg>
                                <input type="text" name="search" class="rdh-input"
                                    placeholder="Search guest, voucher, reward…"
                                    value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
                            </div>

                            <select name="status" class="rdh-select" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="active" <?= $status_f === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="used" <?= $status_f === 'used' ? 'selected' : '' ?>>Used</option>
                                <option value="expired" <?= $status_f === 'expired' ? 'selected' : '' ?>>Expired</option>
                            </select>

                            <select name="reward_id" class="rdh-select" onchange="this.form.submit()">
                                <option value="">All Rewards</option>
                                <?php foreach ($reward_list as $rl): ?>
                                    <option value="<?= $rl['reward_id'] ?>" <?= $reward_f === (int) $rl['reward_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rl['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Row 2 -->
                        <div class="rdh-filter-row">
                            <div class="rdh-date-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"
                                    height="14" style="stroke:#94a3b8;flex-shrink:0;">
                                    <rect x="3" y="4" width="18" height="18" rx="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                <input type="date" name="date_from" class="rdh-input"
                                    value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
                                <span class="rdh-date-sep">→</span>
                                <input type="date" name="date_to" class="rdh-input"
                                    value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
                            </div>

                            <?php if ($search || $status_f || $reward_f || $date_from || $date_to): ?>
                                <a href="redemption_history.php" class="rdh-btn-reset">✕ Clear filters</a>
                            <?php endif; ?>
                        </div>

                    </div>
                    <input type="hidden" name="page" value="1">
                </form>
            </div>

            <!-- Table -->
            <div class="rdh-table-wrap">
                <table class="rdh-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Guest</th>
                            <th>Reward</th>
                            <th>Points Used</th>
                            <th>Voucher Code</th>
                            <th>Status</th>
                            <th>Redeemed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="rdh-empty">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M20 12V22H4V12" />
                                            <path d="M22 7H2v5h20V7z" />
                                            <path d="M12 22V7" />
                                            <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z" />
                                            <path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" />
                                        </svg>
                                        <p style="font-size:14px;font-weight:600;color:#64748b;margin:0 0 4px;">No
                                            redemptions found</p>
                                        <p style="font-size:13px;margin:0;">
                                            <?= ($search || $status_f || $reward_f || $date_from || $date_to)
                                                ? 'Try adjusting your filters.'
                                                : 'Redemptions will appear here once guests redeem rewards.' ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $row_num = $offset + 1;
                            foreach ($rows as $row):
                                $initials = strtoupper(mb_substr($row['guest_name'], 0, 1));
                                $parts = explode(' ', $row['guest_name']);
                                if (count($parts) > 1)
                                    $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
                                $photo_raw = trim((string) ($row['profile_photo'] ?? ''));
                                $photo_url = $photo_raw !== '' ? '../../' . ltrim($photo_raw, '/') : '';
                                $badge_class = match ($row['status']) {
                                    'active' => 'rdh-badge-active',
                                    'used' => 'rdh-badge-used',
                                    'expired' => 'rdh-badge-expired',
                                    default => 'rdh-badge-used',
                                };
                                $badge_dot_color = match ($row['status']) {
                                    'active' => '#16a34a',
                                    'used' => '#94a3b8',
                                    'expired' => '#dc2626',
                                    default => '#94a3b8',
                                };
                                ?>
                                <tr>
                                    <td style="color:#94a3b8;font-size:12px;"><?= $row_num++ ?></td>
                                    <td>
                                        <div class="rdh-guest">
                                            <div class="rdh-avatar">
                                                <?php if ($photo_url): ?>
                                                    <img src="<?= htmlspecialchars($photo_url) ?>"
                                                        alt="<?= htmlspecialchars($initials) ?>"
                                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                    <span
                                                        style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($initials) ?></span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($initials) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="rdh-guest-name"><?= htmlspecialchars($row['guest_name']) ?></div>
                                                <div class="rdh-guest-email"><?= htmlspecialchars($row['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight:500;color:#0f172a;"><?= htmlspecialchars($row['reward_name']) ?></td>
                                    <td>
                                        <span class="rdh-pts">
                                            <svg viewBox="0 0 24 24" fill="none" width="11" height="11" stroke="currentColor"
                                                stroke-width="2">
                                                <circle cx="12" cy="8" r="5" />
                                                <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" />
                                            </svg>
                                            <?= number_format($row['points_used']) ?> pts
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rdh-voucher" title="Click to copy"
                                            onclick="copyVoucher(this, '<?= htmlspecialchars($row['voucher_code']) ?>')">
                                            <?= htmlspecialchars($row['voucher_code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rdh-badge <?= $badge_class ?>">
                                            <svg viewBox="0 0 8 8" width="7" height="7">
                                                <circle cx="4" cy="4" r="4" fill="<?= $badge_dot_color ?>" />
                                            </svg>
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <div class="ps-dt-date" data-date="<?= htmlspecialchars($row['created_at']) ?>"
                                            style="font-size:.82rem;color:#475569;"></div>
                                        <div class="ps-dt-time" data-date="<?= htmlspecialchars($row['created_at']) ?>"
                                            style="font-size:.7rem;color:#94a3b8;"></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_rows > 0): ?>
                <div class="rdh-pagination">
                    <span>
                        Showing
                        <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $per_page, $total_rows)) ?>
                        of <?= number_format($total_rows) ?> redemption<?= $total_rows !== 1 ? 's' : '' ?>
                    </span>
                    <div class="rdh-pages">
                        <?php
                        $qs = http_build_query(array_filter([
                            'search' => $search,
                            'status' => $status_f,
                            'reward_id' => $reward_f ?: '',
                            'date_from' => $date_from,
                            'date_to' => $date_to,
                        ]));
                        $qs = $qs ? "&$qs" : '';
                        ?>

                        <!-- Prev -->
                        <?php if ($current_pg > 1): ?>
                            <a href="?page=<?= $current_pg - 1 ?><?= $qs ?>" class="rdh-page-btn">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                                    height="13">
                                    <polyline points="15 18 9 12 15 6" />
                                </svg>
                            </a>
                        <?php else: ?>
                            <button class="rdh-page-btn" disabled>
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                                    height="13">
                                    <polyline points="15 18 9 12 15 6" />
                                </svg>
                            </button>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <?php
                        $start_p = max(1, $current_pg - 2);
                        $end_p = min($total_pages, $current_pg + 2);
                        if ($start_p > 1)
                            echo '<span class="rdh-page-btn" style="cursor:default;border:none;">…</span>';
                        for ($p = $start_p; $p <= $end_p; $p++):
                            ?>
                            <?php if ($p === $current_pg): ?>
                                <button class="rdh-page-btn active"><?= $p ?></button>
                            <?php else: ?>
                                <a href="?page=<?= $p ?><?= $qs ?>" class="rdh-page-btn"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($end_p < $total_pages)
                            echo '<span class="rdh-page-btn" style="cursor:default;border:none;">…</span>'; ?>

                        <!-- Next -->
                        <?php if ($current_pg < $total_pages): ?>
                            <a href="?page=<?= $current_pg + 1 ?><?= $qs ?>" class="rdh-page-btn">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                                    height="13">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg>
                            </a>
                        <?php else: ?>
                            <button class="rdh-page-btn" disabled>
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                                    height="13">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ── Datetime rendering (same pattern as payment.php) ─────────────────
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.ps-dt-date[data-date]').forEach(function (el) {
                const d = psDate ? psDate(el.dataset.date) : new Date(el.dataset.date.includes('T') ? el.dataset.date : el.dataset.date + 'Z');
                if (d && !isNaN(d)) el.textContent = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            });
            document.querySelectorAll('.ps-dt-time[data-date]').forEach(function (el) {
                const d = psDate ? psDate(el.dataset.date) : new Date(el.dataset.date.includes('T') ? el.dataset.date : el.dataset.date + 'Z');
                if (d && !isNaN(d)) el.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            });
        });

        // ── Copy voucher code ─────────────────────────────────────────────────
        function copyVoucher(el, code) {
            navigator.clipboard.writeText(code).then(function () {
                const orig = el.textContent;
                el.textContent = 'Copied!';
                el.style.background = '#dcfce7';
                el.style.color = '#15803d';
                el.style.borderColor = '#86efac';
                setTimeout(function () {
                    el.textContent = orig;
                    el.style.background = '';
                    el.style.color = '';
                    el.style.borderColor = '';
                }, 1400);
            });
        }
    </script>

    <?php include '../../includes/layout_close.php'; ?>