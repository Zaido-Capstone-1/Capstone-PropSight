<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>
<head>

</head>
<body>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/guests_clients-inline.js"></script>
</body>
</html>';
  exit;
}

$page_title = 'Guests / Clients';
$active_page = 'guests_clients';
include '../../includes/db.php';
include '../../includes/layout_open.php';
require_once '../../lib/admin-queries/guest_clients_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/guest_client.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Guests &amp; Clients</h1>
      <p class="dash-subtitle">Directory of all registered guests and tenants.</p>
    </div>
  </div>

  <div class="cards-area">

    <div class="stat-row">
      <div class="stat-card">
        <div>
          <div class="stat-label">Total Guests</div>
          <div class="stat-value" id="guest-stat-total"><?= $total ?></div>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div>
          <div class="stat-label">Active Tenants</div>
          <div class="stat-value" id="guest-stat-active"><?= $active_tenants ?></div>
        </div>
        <div class="stat-icon-wrap green">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div>
          <div class="stat-label">New This Month</div>
          <div class="stat-value" id="guest-stat-new-month"><?= $new_month ?></div>
        </div>
        <div class="stat-icon-wrap gold">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div>
          <div class="stat-label">Blacklisted</div>
          <div class="stat-value" id="guest-stat-blacklisted"><?= $blacklisted ?></div>
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

    <!-- ── Pending ID Verifications ── -->
    <?php if (!empty($pendingIds)): ?>
      <div class="card" id="pendingIdCard" style="margin-bottom:24px;">
        <div class="card-header">
          <div style="display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.8"
              style="width:18px;height:18px;flex-shrink:0;">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            <span class="card-title" style="color:#92400e;">Pending ID Verifications</span>
            <span
              style="margin-left:4px;background:#d97706;color:#fff;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:99px;"><?php echo count($pendingIds); ?></span>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Guest</th>
                <th>Email</th>
                <th>Submitted</th>
                <th>ID Document</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="pendingIdTbody">
              <?php foreach ($pendingIds as $u): ?>
                <tr id="pid-row-<?php echo $u['user_id']; ?>">
                  <td><strong><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong></td>
                  <td style="font-size:.82rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                  <td style="font-size:.82rem;color:#64748b;"><?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                  </td>
                  <td>
                    <button class="tbl-btn"
                      onclick="openViewIdModal(<?php echo $u['user_id']; ?>)">
                      View ID
                    </button>
                  </td>
                  <td>
                    <div class="action-wrap">
                      <button class="tbl-btn" style="background:#16a34a;color:#fff;border-color:#16a34a;"
                        onclick="confirmApprove(<?php echo $u['user_id']; ?>)">
                        ✓ Approve
                      </button>
                      <button class="tbl-btn danger" onclick="openRejectModal(<?php echo $u['user_id']; ?>)">
                        ✗ Reject
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- ── Guest Directory ── -->
    <div class="card">
      <div class="card-header" style="flex-wrap:wrap;gap:10px;">
        <span class="card-title">Guest Directory</span>
        <div style="display:flex;flex-direction:column;gap:8px;width:100%;">

          <div class="search-wrap">
            <svg viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input type="text" id="guestSearch" placeholder="Search guests…">
          </div>

          <div class="filter-pills" id="filterPills">
            <span class="filter-pill-sm active" data-filter="all">All</span>
            <span class="filter-pill-sm" data-filter="active">Active</span>
            <span class="filter-pill-sm" data-filter="inactive">Inactive</span>
            <span class="filter-pill-sm" data-filter="blacklisted">Blacklisted</span>
          </div>

        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Current Unit</th>
              <th>Member Since</th>
              <th style="text-align:center;">Stays</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="guestTableBody">
            <?php if (empty($guests)): ?>
              <tr>
                <td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">
                  No guests found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($guests as $g):
                [$statusLabel, $statusCls] = guestStatus($g);
                $fullName = htmlspecialchars(trim($g['first_name'] . ' ' . $g['last_name']));
                $initials = strtoupper(substr($g['first_name'], 0, 1)) . strtoupper(substr($g['last_name'], 0, 1));
                $photo = $g['profile_photo'] ?? '';
                // SSO logins (Google/Facebook) store the provider's full avatar
                // URL here instead of a local upload path — don't prepend
                // "../../" to an absolute URL, or the <img> src breaks.
                $photoUrl = $photo !== ''
                  ? (preg_match('#^https?://#i', $photo) ? $photo : '../../' . ltrim($photo, '/'))
                  : '';

                $filterStatus = $g['is_blacklisted'] ? 'blacklisted'
                  : ($g['is_active'] ? 'active'
                    : 'inactive');

                $searchIndex = strtolower(
                  $g['first_name'] . ' ' . $g['last_name'] . ' ' .
                  $g['email'] . ' ' . ($g['phone'] ?? '')
                );
                ?>
                <tr data-user-id="<?= $g['user_id'] ?>" data-status="<?= $filterStatus ?>"
                  data-search="<?= htmlspecialchars($searchIndex) ?>">
                  <td>
                    <div style="display:flex;align-items:center;gap:9px;">
                      <?php if ($photo): ?>
                        <img src="<?= htmlspecialchars($photoUrl) ?>" class="guest-avatar-img"
                          onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="guest-avatar" style="display:none;"><?= $initials ?></div>
                      <?php else: ?>
                        <div class="guest-avatar"><?= $initials ?></div>
                      <?php endif; ?>
                      <strong><?= $fullName ?></strong>
                    </div>
                  </td>
                  <td style="font-size:0.82rem;"><?= htmlspecialchars($g['email']) ?></td>
                  <td style="font-size:0.82rem;color:#64748b;"><?= htmlspecialchars($g['phone'] ?? '—') ?></td>
                  <td style="font-size:0.82rem;">
                    <?= $g['current_unit'] ? htmlspecialchars($g['current_unit']) : '<span style="color:#cbd5e1;">—</span>' ?>
                  </td>
                  <td style="font-size:0.82rem;color:#64748b;"><?= date('M Y', strtotime($g['created_at'])) ?></td>
                  <td style="text-align:center;font-weight:700;"><?= (int) $g['total_stays'] ?></td>
                  <td><span class="badge badge-<?= $statusCls ?>"><?= $statusLabel ?></span></td>
                  <td>
                    <div class="action-wrap">
                      <?php if (!$g['is_blacklisted']): ?>
                        <button class="tbl-btn danger" onclick="toggleBlacklist(<?= $g['user_id'] ?>, '<?= $fullName ?>', 1)">
                          Block
                        </button>
                      <?php else: ?>
                        <button class="tbl-btn" onclick="toggleBlacklist(<?= $g['user_id'] ?>, '<?= $fullName ?>', 0)">
                          Unblock
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <div id="noResults" style="display:none;text-align:center;padding:40px;color:#94a3b8;">
          No guests match your search or filter.
        </div>
      </div>

      <div style="padding:10px 20px;font-size:0.75rem;color:#94a3b8;border-top:1px solid #f1f5f9;">
        Showing <strong id="visibleCount"><?= count($guests) ?></strong>
        of <strong><?= count($guests) ?></strong>
        guest<?= count($guests) !== 1 ? 's' : '' ?>
      </div>

    </div><!-- /Guest Directory card -->

    <!-- ── Block Guest Modal ── -->
    <div id="blockModal" class="confirm-modal-overlay">
      <div class="confirm-modal">
        <div class="confirm-modal-header">
          <h3 class="confirm-modal-title" id="blockModalTitle">Block Guest</h3>
        </div>
        <div class="confirm-modal-body">
          <p id="blockModalDesc"></p>
          <div id="blockReasonWrap" style="margin-top:14px;">
            <label
              style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;display:block;margin-bottom:6px;">Reason
              (optional)</label>
            <input id="blockReasonInput" type="text" placeholder="e.g. Violation of terms, fraud…"
              style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
          </div>
        </div>
        <div class="confirm-modal-footer">
          <button id="blockModalCancelBtn" class="confirm-modal-btn confirm-btn-cancel">Cancel</button>
          <button id="blockModalConfirmBtn" class="confirm-modal-btn confirm-btn-confirm danger">Block</button>
        </div>
      </div>
    </div>

    <!-- ── Reject ID Modal ── -->
    <div id="rejectIdModal" class="confirm-modal-overlay">
      <div class="confirm-modal">
        <div class="confirm-modal-header">
          <h3 class="confirm-modal-title">Reject ID Submission</h3>
        </div>
        <div class="confirm-modal-body">
          <p style="font-size:.84rem;color:#64748b;margin-bottom:12px;">Provide a reason so the guest knows what to fix
            and can re-upload.</p>
          <label
            style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;display:block;margin-bottom:6px;">Reason</label>
          <input id="rejectReasonInput" type="text" placeholder="e.g. Image is blurry, ID is expired, wrong document…"
            style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;outline:none;box-sizing:border-box;">
        </div>
        <div class="confirm-modal-footer">
          <button class="confirm-modal-btn confirm-btn-cancel" onclick="closeRejectModal()">Cancel</button>
          <button class="confirm-modal-btn confirm-btn-confirm danger" onclick="submitReject()">Reject</button>
        </div>
      </div>
    </div>

  </div><!-- /cards-area -->

  <div id="approveIdModal" class="confirm-modal-overlay">
    <div class="confirm-modal">
      <div class="confirm-modal-header">
        <h3 class="confirm-modal-title">Approve ID Document</h3>
      </div>
      <div class="confirm-modal-body">
        <p style="font-size:.84rem;color:#64748b;">Are you sure you want to approve this ID document? The guest will be
          notified and can proceed with booking.</p>
      </div>
      <div class="confirm-modal-footer">
        <button class="confirm-modal-btn confirm-btn-cancel" onclick="closeApproveModal()">Cancel</button>
        <button class="confirm-modal-btn confirm-btn-confirm" id="approveModalConfirmBtn"
          style="background:#16a34a;border-color:#16a34a;color:#fff;" onclick="submitApprove()">
          ✓ Approve
        </button>
      </div>
    </div>
  </div>

  <!-- ── View ID Modal ── -->
  <div id="viewIdModal" class="confirm-modal-overlay">
    <div class="confirm-modal" style="max-width:800px;">
      <div class="confirm-modal-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3 class="confirm-modal-title" id="viewIdModalTitle">Identity Document</h3>
        <button class="modal-close-btn" onclick="closeViewIdModal()">&times;</button>
      </div>
      <div class="confirm-modal-body"
        style="padding:0;display:flex;align-items:center;justify-content:center;background:#f8fafc;min-height:400px;max-height:65vh;">
        <img id="viewIdImage" src="" alt="ID Document"
          style="max-width:100%;max-height:65vh;height:auto;width:auto;object-fit:contain;display:block;">
      </div>
      <div class="confirm-modal-footer">
        <button class="confirm-modal-btn confirm-btn-cancel" onclick="closeViewIdModal()">Close</button>
      </div>
    </div>
  </div>
</div><!-- /page-inner -->


<script>window.PS_RT_PAGE = 'guests_clients';</script>
<script src="../../assets/js/admin/guest_client.js"></script>
<link rel="stylesheet" href="../../assets/css/admin-css/reservation.css">

<?php include '../../includes/layout_close.php'; ?>