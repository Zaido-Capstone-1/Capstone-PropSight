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

$sql = "
    SELECT
        u.user_id, u.first_name, u.last_name, u.email,
        u.phone, u.created_at, u.profile_photo,
        COALESCE(u.is_blacklisted, 0) AS is_blacklisted,
        COALESCE(u.is_active, 0) AS is_active,
        COUNT(DISTINCT b.booking_id) AS total_stays,
        (SELECT COALESCE(NULLIF(TRIM(un.unit_name),''), CONCAT(p2.property_name, ' — ', un.unit_number))
        FROM bookings bx
        JOIN units un ON un.unit_id = bx.unit_id
        LEFT JOIN properties p2 ON p2.property_id = un.property_id
        WHERE bx.user_id = u.user_id
          AND bx.status IN ('confirmed', 'active', 'completed')
        ORDER BY bx.checkin_date DESC LIMIT 1
        ) AS current_unit
    FROM users u
    LEFT JOIN bookings b ON b.user_id = u.user_id AND b.status NOT IN ('cancelled')
    WHERE u.role = 'user'
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
";
$res = mysqli_query($conn, $sql);
$guests = [];
while ($row = mysqli_fetch_assoc($res))
  $guests[] = $row;

$total_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user'");
$total = (int) mysqli_fetch_assoc($total_res)['c'];

$active_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_active=1");
$active_tenants = (int) mysqli_fetch_assoc($active_res)['c'];

$month_res = mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM users
    WHERE role='user'
      AND MONTH(created_at) = MONTH(NOW())
      AND YEAR(created_at)  = YEAR(NOW())
");
$new_month = (int) mysqli_fetch_assoc($month_res)['c'];

$black_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_blacklisted=1");
$blacklisted = (int) mysqli_fetch_assoc($black_res)['c'];

function guestStatus($row)
{
  if ($row['is_blacklisted'])
    return ['Blacklisted', 'danger'];
  if ($row['is_active'])
    return ['Active', 'success'];
  if ($row['total_stays'] > 0)
    return ['Guest', 'info'];
  return ['New', 'pending'];
}
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

    <div class="card">
      <div class="card-header" style="flex-wrap:wrap;gap:10px;">
        <span class="card-title">Guest Directory</span>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">

          <div class="filter-pills" id="filterPills">
            <span class="filter-pill-sm active" data-filter="all">All</span>
            <span class="filter-pill-sm" data-filter="active">Active</span>
            <span class="filter-pill-sm" data-filter="inactive">Inactive</span>
            <span class="filter-pill-sm" data-filter="blacklisted">Blacklisted</span>
          </div>

          <div class="search-wrap">
            <svg viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input type="text" id="guestSearch" placeholder="Search guests…">
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
                        <img src="../../<?= htmlspecialchars($photo) ?>" class="guest-avatar-img"
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
                      <!-- <a href="guest_profile.php?id=<?= $g['user_id'] ?>" class="tbl-btn"
                        style="text-decoration:none;">View</a> -->
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
    </div>

  </div>
</div>

<script>window.PS_RT_PAGE = 'guests_clients';</script>
<script src="../../assets/js/admin/guest_client.js"></script>
<link rel="stylesheet" href="../../assets/css/admin-css/reservation.css">

<?php include '../../includes/layout_close.php'; ?>