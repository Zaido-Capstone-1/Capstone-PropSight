<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html><html><body>
    <script src="../../assets/js/responsive.js"></script>
    </body></html>';
    exit;
}

$page_title = 'Refunds';
$active_page = 'refunds';

include '../../includes/db.php';
include '../../includes/layout_open.php';

function fmt_peso(float $v): string
{
    return '₱ ' . number_format($v, 2);
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(CASE WHEN refund_status = 'pending'  THEN 1 END)                        AS pending_count,
        COUNT(CASE WHEN refund_status = 'completed' AND MONTH(processed_date) = MONTH(CURDATE()) AND YEAR(processed_date) = YEAR(CURDATE()) THEN 1 END) AS approved_this_month,
        COALESCE(SUM(CASE WHEN refund_status = 'completed' AND MONTH(processed_date) = MONTH(CURDATE()) AND YEAR(processed_date) = YEAR(CURDATE()) THEN refund_amount END), 0) AS approved_amount,
        COUNT(CASE WHEN refund_status = 'rejected' THEN 1 END)                        AS rejected_count
    FROM refunds
")->fetch_assoc();

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = ['1=1'];
$types = '';
$params = [];

if ($filter_status !== 'all') {
    $where[] = 'r.refund_status = ?';
    $types .= 's';
    $params[] = $filter_status;
}
if ($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR CAST(r.booking_id AS CHAR) LIKE ? OR CAST(r.refund_id AS CHAR) LIKE ?)';
    $types .= 'ssss';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$where_sql = implode(' AND ', $where);

$stmt = $conn->prepare("
    SELECT
        r.refund_id, r.booking_id, r.payment_id, r.refund_amount,
        r.refund_reason, r.refund_status, r.refund_method,
        r.refund_date, r.processed_date, r.admin_notes, r.created_at,
        u.first_name, u.last_name, u.email, u.profile_photo,
        un.unit_number
    FROM   refunds r
    JOIN   users   u  ON u.user_id   = r.user_id
    LEFT JOIN bookings b  ON b.booking_id = r.booking_id
    LEFT JOIN units    un ON un.unit_id   = b.unit_id
    WHERE  $where_sql
    ORDER  BY
        CASE r.refund_status WHEN 'pending' THEN 0 ELSE 1 END,
        r.created_at DESC
");

if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$refunds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page = 10;
$total = count($refunds);
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min((int) ($_GET['pg'] ?? 1), $total_pages));
$offset = ($page - 1) * $per_page;
$page_rows = array_slice($refunds, $offset, $per_page);

// Build base URL for pagination
$url_params = $_GET;
unset($url_params['pg']);
$base_url = '?' . http_build_query($url_params) . (empty($url_params) ? '' : '&');
?>

<link rel="stylesheet" href="../../assets/css/admin-css/payments.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">
<style>
    .badge-pending {
        background: #fef9c3;
        color: #854d0e;
        border: 1px solid #fde68a;
    }

    .badge-approved {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .badge-rejected {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .refund-reason-cell {
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--text-soft);
        font-size: 12px;
    }

    .btn-approve {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border: 1.5px solid #16a34a;
        background: #f0fdf4;
        color: #16a34a;
        transition: all .15s;
    }

    .btn-approve:hover {
        background: #16a34a;
        color: #fff;
    }

    .btn-reject {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border: 1.5px solid #dc2626;
        background: #fff1f2;
        color: #dc2626;
        transition: all .15s;
    }

    .btn-reject:hover {
        background: #dc2626;
        color: #fff;
    }

    .refund-empty {
        text-align: center;
        padding: 52px 16px;
        color: #aaa;
        font-size: 14px;
    }
</style>

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Refunds</h1>
            <p class="dash-subtitle">Review and process guest refund requests.</p>
        </div>
    </div>

    <div class="cards-area">

        <!-- Stat Cards -->
        <div class="stat-row">
            <div class="stat-card sc-gold">
                <div class="stat-card-left">
                    <div class="stat-label">Pending Refunds</div>
                    <div class="stat-value"><?= (int) $stats['pending_count'] ?></div>
                </div>
                <div class="stat-icon-wrap gold">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                </div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-card-left">
                    <div class="stat-label">Approved This Month</div>
                    <div class="stat-value">
                        <?= fmt_peso((float) $stats['approved_amount']) ?>
                        <span class="stat-trend neutral"><?= (int) $stats['approved_this_month'] ?> requests</span>
                    </div>
                </div>
                <div class="stat-icon-wrap green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card sc-red">
                <div class="stat-card-left">
                    <div class="stat-label">Rejected</div>
                    <div class="stat-value"><?= (int) $stats['rejected_count'] ?></div>
                </div>
                <div class="stat-icon-wrap red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Refund Requests Table -->
        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <span class="card-title">Refund Requests</span>
                    <?php if ((int) $stats['pending_count'] > 0): ?>
                        <span class="record-count"><?= (int) $stats['pending_count'] ?> pending</span>
                    <?php endif; ?>
                </div>
                <form method="GET" style="display:flex;align-items:center;gap:8px;">
                    <!-- Search -->
                    <div style="position:relative;flex-shrink:0;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                            height="13"
                            style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--text-soft);pointer-events:none;">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search guest, booking ID…"
                            style="padding:7px 10px 7px 28px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:12.5px;width:190px;background:var(--white);">
                    </div>

                    <!-- Status filter -->
                    <div class="inv-status-dropdown-wrap" id="refStatusWrap">
                        <button type="button" class="inv-status-trigger" onclick="toggleRefStatus()">
                            <span id="refStatusLabel">
                                <?php
                                $slabels = ['all' => 'All Status', 'pending' => 'Pending', 'completed' => 'Approved', 'rejected' => 'Rejected'];
                                echo $slabels[$filter_status] ?? 'All Status';
                                ?>
                            </span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12"
                                height="12">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <input type="hidden" name="status" id="refStatusInput"
                            value="<?= htmlspecialchars($filter_status) ?>">
                        <div class="inv-status-menu" id="refStatusMenu" style="display:none;right:0;left:auto;">
                            <?php foreach (['all' => 'All Status', 'pending' => 'Pending', 'completed' => 'Approved', 'rejected' => 'Rejected'] as $val => $lbl): ?>
                                <button type="button" class="inv-status-opt <?= $filter_status === $val ? 'active' : '' ?>"
                                    data-value="<?= $val ?>" onclick="selectRefStatus(this)">
                                    <?php if ($val !== 'all'): ?>
                                        <span
                                            class="inv-status-dot <?= $val === 'completed' ? 'paid' : ($val === 'pending' ? 'pending' : 'overdue') ?>"></span>
                                    <?php endif; ?>
                                    <?= $lbl ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Guest</th>
                            <th>Unit</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($page_rows)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="refund-empty">
                                        <svg width="40" height="40" fill="none" stroke="#ccc" stroke-width="1.5"
                                            viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="15" y1="9" x2="9" y2="15" />
                                            <line x1="9" y1="9" x2="15" y2="15" />
                                        </svg>
                                        No refund requests found.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($page_rows as $r):
                                $fullName = htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name']));
                                $initial = strtoupper(mb_substr($fullName, 0, 1));
                                $refId = '#REF-' . str_pad($r['refund_id'], 4, '0', STR_PAD_LEFT);
                                $bkRef = 'BK-' . str_pad($r['booking_id'], 6, '0', STR_PAD_LEFT);
                                $isPending = $r['refund_status'] === 'pending';
                                $badgeClass = $r['refund_status'] === 'completed' ? 'badge-approved' : 'badge-' . $r['refund_status'];
                                $statusLabel = $r['refund_status'] === 'completed' ? 'Approved' : ucfirst($r['refund_status']);
                                $dataJson = htmlspecialchars(json_encode([
                                    'refund_id' => $r['refund_id'],
                                    'booking_id' => $r['booking_id'],
                                    'refund_amount' => $r['refund_amount'],
                                    'refund_reason' => $r['refund_reason'],
                                    'guest_name' => $fullName,
                                    'unit_number' => $r['unit_number'] ?? '—',
                                    'bk_ref' => $bkRef,
                                ]), ENT_QUOTES);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= $refId ?></strong>
                                        <div style="font-size:11px;color:var(--text-soft);"><?= $bkRef ?></div>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <?php if (!empty($r['profile_photo'])): ?>
                                                <img src="../../<?= htmlspecialchars($r['profile_photo']) ?>" alt="<?= $initial ?>"
                                                    style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;"
                                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                <div class="avatar" style="display:none;"><?= $initial ?></div>
                                            <?php else: ?>
                                                <div class="avatar"><?= $initial ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <div><?= $fullName ?></div>
                                                <div style="font-size:11px;color:var(--text-soft);">
                                                    <?= htmlspecialchars($r['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($r['unit_number'] ?? '—') ?></td>
                                    <td style="font-weight:700;"><?= fmt_peso((float) $r['refund_amount']) ?></td>
                                    <td>
                                        <div class="refund-reason-cell" title="<?= htmlspecialchars($r['refund_reason']) ?>">
                                            <?= htmlspecialchars($r['refund_reason']) ?>
                                        </div>
                                    </td>
                                    <td style="font-size:12px;color:var(--text-soft);">
                                        <?= date('M j, Y', strtotime($r['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                                        <?php if (!$isPending && !empty($r['admin_notes'])): ?>
                                            <div style="font-size:11px;color:var(--text-soft);margin-top:2px;"
                                                title="<?= htmlspecialchars($r['admin_notes']) ?>">
                                                <?= htmlspecialchars(mb_substr($r['admin_notes'], 0, 30)) ?>…
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;justify-content:center;">
                                            <?php if ($isPending): ?>
                                                <button class="btn-approve" onclick="openApproveModal(<?= $dataJson ?>)">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                                                        width="13" height="13">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                    Approve
                                                </button>
                                                <button class="btn-reject" onclick="openRejectModal(<?= $dataJson ?>)">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                                                        width="13" height="13">
                                                        <line x1="18" y1="6" x2="6" y2="18" />
                                                        <line x1="6" y1="6" x2="18" y2="18" />
                                                    </svg>
                                                    Reject
                                                </button>
                                            <?php else: ?>
                                                <span style="font-size:12px;color:var(--text-soft);">
                                                    <?= $r['processed_date'] ? date('M j, Y', strtotime($r['processed_date'])) : '—' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($total_pages > 1): ?>
                        <tfoot>
                            <tr>
                                <td colspan="8">
                                    <div class="txn-pagination">
                                        <span class="txn-page-info">
                                            Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> of
                                            <?= $total ?> request(s)
                                        </span>
                                        <div class="txn-page-controls">
                                            <?php if ($page > 1): ?>
                                                <a href="<?= $base_url ?>pg=<?= $page - 1 ?>" class="txn-chevron-btn">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2.2"
                                                        viewBox="0 0 24 24" width="14" height="14">
                                                        <polyline points="15 18 9 12 15 6" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <a href="<?= $base_url ?>pg=<?= $i ?>"
                                                    class="txn-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                                            <?php endfor; ?>
                                            <?php if ($page < $total_pages): ?>
                                                <a href="<?= $base_url ?>pg=<?= $page + 1 ?>" class="txn-chevron-btn">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2.2"
                                                        viewBox="0 0 24 24" width="14" height="14">
                                                        <polyline points="9 18 15 12 9 6" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    <?php else: ?>
                        <tfoot>
                            <tr>
                                <td colspan="8">
                                    <div class="txn-pagination">
                                        <span class="txn-page-info">Showing <?= $total ?> request(s)</span>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Approve Modal ──────────────────────────────────────────────────────── -->
<div id="approveModal" class="modal-backdrop" style="display:none;"
    onclick="if(event.target===this)closeApproveModal()">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3>Approve Refund</h3>
            <button class="modal-close" onclick="closeApproveModal()">×</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-soft);margin:0 0 16px;">
                You are about to approve a refund of <strong id="approveAmount"></strong> for
                <strong id="approveGuest"></strong> (<span id="approveBkRef"></span>).
            </p>
            <p style="color:var(--text-soft);margin:0;font-size:13px;">
                This will trigger the PayMongo refund and notify the guest by email.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeApproveModal()">Cancel</button>
            <button class="btn btn-primary" id="approveSubmitBtn" onclick="submitApprove()">
                Confirm Approve
            </button>
        </div>
    </div>
</div>

<!-- ── Reject Modal ───────────────────────────────────────────────────────── -->
<div id="rejectModal" class="modal-backdrop" style="display:none;" onclick="if(event.target===this)closeRejectModal()">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3>Reject Refund</h3>
            <button class="modal-close" onclick="closeRejectModal()">×</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-soft);margin:0 0 16px;">
                Rejecting refund of <strong id="rejectAmount"></strong> for
                <strong id="rejectGuest"></strong> (<span id="rejectBkRef"></span>).
            </p>
            <div class="form-group">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">
                    Reason for rejection <span style="color:var(--terra,#e07043);">*</span>
                </label>
                <textarea id="rejectReason" rows="3" placeholder="Explain why this refund is being rejected…"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeRejectModal()">Cancel</button>
            <button class="btn btn-danger" id="rejectSubmitBtn" onclick="submitReject()">
                Confirm Reject
            </button>
        </div>
    </div>
</div>

<script>
    window.PS_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

    let _refundId = null;

    // ── Status filter dropdown ────────────────────────────────────────────────────
    function toggleRefStatus() {
        const m = document.getElementById('refStatusMenu');
        m.style.display = m.style.display === 'none' ? 'block' : 'none';
    }
    function selectRefStatus(btn) {
        document.getElementById('refStatusInput').value = btn.dataset.value;
        document.getElementById('refStatusLabel').textContent = btn.textContent.trim();
        document.getElementById('refStatusMenu').style.display = 'none';
        btn.closest('form').submit();
    }
    document.addEventListener('click', e => {
        const wrap = document.getElementById('refStatusWrap');
        if (wrap && !wrap.contains(e.target))
            document.getElementById('refStatusMenu').style.display = 'none';
    });

    // ── Approve ───────────────────────────────────────────────────────────────────
    function openApproveModal(data) {
        _refundId = data.refund_id;
        document.getElementById('approveAmount').textContent = '₱' + parseFloat(data.refund_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('approveGuest').textContent = data.guest_name;
        document.getElementById('approveBkRef').textContent = data.bk_ref;
        document.getElementById('approveModal').style.display = 'flex';
    }
    function closeApproveModal() {
        document.getElementById('approveModal').style.display = 'none';
        _refundId = null;
    }
    function submitApprove() {
        const btn = document.getElementById('approveSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Processing…';

        const fd = new FormData();
        fd.append('refund_id', _refundId);
        fd.append('action', 'approve');
        fd.append('csrf_token', window.PS_CSRF_TOKEN ?? '');

        fetch('../../api/admin/process_refund.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeApproveModal();
                    showToast(data.message ?? 'Refund approved.', false);
                    setTimeout(() => location.reload(), 1800);
                } else {
                    showToast(data.message ?? 'Something went wrong.', true);
                }
            })
            .catch(() => showToast('Network error. Please try again.', true))
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Confirm Approve';
            });
    }

    // ── Reject ────────────────────────────────────────────────────────────────────
    function openRejectModal(data) {
        _refundId = data.refund_id;
        document.getElementById('rejectAmount').textContent = '₱' + parseFloat(data.refund_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('rejectGuest').textContent = data.guest_name;
        document.getElementById('rejectBkRef').textContent = data.bk_ref;
        document.getElementById('rejectReason').value = '';
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
        _refundId = null;
    }
    function submitReject() {
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            document.getElementById('rejectReason').focus();
            return;
        }

        const btn = document.getElementById('rejectSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Processing…';

        const fd = new FormData();
        fd.append('refund_id', _refundId);
        fd.append('action', 'reject');
        fd.append('reason', reason);
        fd.append('csrf_token', window.PS_CSRF_TOKEN ?? '');

        fetch('../../api/admin/process_refund.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeRejectModal();
                    showToast(data.message ?? 'Refund rejected.', false);
                    setTimeout(() => location.reload(), 1800);
                } else {
                    showToast(data.message ?? 'Something went wrong.', true);
                }
            })
            .catch(() => showToast('Network error. Please try again.', true))
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Confirm Reject';
            });
    }
</script>

<?php include '../../includes/layout_close.php'; ?>