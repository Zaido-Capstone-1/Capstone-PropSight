<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>
<script src="../../assets/js/responsive.js"></script>

</body>
</html>';
    exit;
}

$page_title = 'Staff / Admin Roles';
$active_page = 'staff_roles';
include '../../includes/db.php';
include '../../includes/layout_open.php';
include '../../lib/admin-queries/staff_roles_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/staff_roles.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Staff &amp; Admin Roles</h1>
            <p class="dash-subtitle">Manage team members and their access permissions.</p>
        </div>
        <div class="dash-header-actions">
            <!-- id="open-invite-modal" -->
            <button class="btn btn-primary" id="open-invite-modal">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Invite Staff
            </button>
        </div>
    </div>

    <div class="cards-area">

        <div class="stat-row">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Staff</div>
                    <div class="stat-value" id="stat-total"><?= $counts['total'] ?></div>
                </div>
                <div class="stat-icon-wrap blue">
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
                    <div class="stat-label">Admins</div>
                    <div class="stat-value" id="role-count-admin"><?= $counts['admin'] ?></div>
                </div>
                <div class="stat-icon-wrap gold">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Managers</div>
                    <div class="stat-value" id="role-count-manager"><?= $counts['manager'] ?></div>
                </div>
                <div class="stat-icon-wrap green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="2" y="7" width="20" height="14" rx="2" />
                        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Field Staff</div>
                    <div class="stat-value" id="role-count-field"><?= $counts['frontdesk'] + $counts['maintenance'] ?></div>
                </div>
                <div class="stat-icon-wrap red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path
                            d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="two-col">

            <div class="card" style="flex:2;">
                <div class="card-header">
                    <span class="card-title">Team Members</span>
                    <form method="GET" style="display:contents;">
                        <div class="search-wrap">
                            <svg viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" id="searchStaff"
                                placeholder="Search staff…"
                                oninput="clearTimeout(st2);st2=setTimeout(()=>this.form.submit(),450)">
                        </div>
                    </form>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Last Active</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staffTableBody">
                            <?php if (empty($staff)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:40px;color:#94a3b8;">No staff found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staff as $s):
                                    $fullName = htmlspecialchars(trim($s['first_name'] . ' ' . $s['last_name']));
                                    $initials = strtoupper(substr($s['first_name'], 0, 1)) . strtoupper(substr($s['last_name'], 0, 1));
                                    $photo = $s['profile_photo'] ?? '';
                                    $roleCls = 'role-' . strtolower($s['role']);
                                    $isActive = (int) $s['is_active'];
                                    $isSelf = (int) $s['user_id'] === (int) $_SESSION['user_id'];
                                    ?>
                                    <tr data-user-id="<?= $s['user_id'] ?>" data-active="<?= $isActive ?>">
                                        <td>
                                            <div style="display:flex;align-items:center;gap:9px;">
                                                <?php if ($photo): ?>
                                                    <img src="../../<?= htmlspecialchars($photo) ?>" class="staff-avatar-img"
                                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                    <div class="staff-avatar" style="display:none;"><?= $initials ?></div>
                                                <?php else: ?>
                                                    <div class="staff-avatar"><?= $initials ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight:700;font-size:0.84rem;"><?= $fullName ?></div>
                                                    <div style="font-size:0.72rem;color:#94a3b8;">
                                                        <?= htmlspecialchars($s['email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="role-pill <?= $roleCls ?>"><?= roleLabel($s['role']) ?></span></td>
                                        <td style="font-size:0.78rem;color:#94a3b8;"><?= lastActiveLabel($s['last_login']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $isActive ? 'success' : 'gray' ?>">
                                                <?= $isActive ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-wrap">
                                                <button class="tbl-btn"
                                                    onclick="toggleActive(<?= $s['user_id'] ?>, '<?= $fullName ?>', <?= $isActive ?>)">
                                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                                <?php if (!$isSelf): ?>
                                                    <button class="tbl-btn danger"
                                                        onclick="removeStaff(<?= $s['user_id'] ?>, '<?= $fullName ?>')">
                                                        Remove
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding:10px 20px;font-size:0.75rem;color:#94a3b8;border-top:1px solid #f1f5f9;">
                    <span id="staff-count"><?= count($staff) ?></span>
                    team member<?= count($staff) !== 1 ? 's' : '' ?>
                    <?= $search ? '· search: <strong>' . htmlspecialchars($search) . '</strong>' : '' ?>
                </div>
            </div>

            <div class="card" style="flex:1;">
                <div class="card-header"><span class="card-title">Roles &amp; Access</span></div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($role_defs as $roleKey => [$roleName, $roleDesc, $roleColor]):
                        $cnt = $counts[$roleKey] ?? 0;
                        ?>
                        <div class="role-card">
                            <div class="role-card-dot" style="background:<?= $roleColor ?>;"></div>
                            <div class="role-card-body">
                                <div class="role-card-name"><?= $roleName ?></div>
                                <div class="role-card-desc"><?= $roleDesc ?></div>
                            </div>
                            <div class="role-card-count" id="role-count-<?= $roleKey ?>"><?= $cnt ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Invite Modal — id="inviteOverlay" -->
<div id="inviteOverlay" class="modal-overlay">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3 class="modal-title">Invite Staff Member</h3>
            <button class="modal-close" onclick="closeInvite()">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;padding:20px;">
            <div style="display:flex;gap:10px;">
                <div style="flex:1;">
                    <label class="form-label">First Name</label>
                    <!-- id="invFirst" -->
                    <input id="invFirst" type="text" class="form-input" placeholder="Juan">
                </div>
                <div style="flex:1;">
                    <label class="form-label">Last Name</label>
                    <!-- id="invLast" -->
                    <input id="invLast" type="text" class="form-input" placeholder="Dela Cruz">
                </div>
            </div>
            <div>
                <label class="form-label">Email Address</label>
                <!-- id="invEmail" -->
                <input id="invEmail" type="email" class="form-input" placeholder="staff@example.com">
            </div>
            <div>
                <label class="form-label">Role</label>
                <!-- id="invRole" -->
                <select id="invRole" class="form-input">
                    <option value="frontdesk">Front Desk</option>
                    <option value="manager">Property Manager</option>
                    <option value="accounting">Accounting</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <button onclick="submitInvite()" class="btn btn-primary" style="width:100%;margin-top:4px;">
                Send Invite
            </button>
        </div>
    </div>
</div>

<!-- Toggle Active/Inactive Confirm Modal -->
<div id="toggleActiveOverlay" class="modal-overlay">
    <div class="modal-box" style="max-width:400px;text-align:center;">
        <div style="padding:28px 24px 0;">
            <div id="toggleActiveIcon"
                style="width:56px;height:56px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
                    <circle cx="12" cy="12" r="9" />
                    <polyline points="9 12 11 14 15 10" />
                </svg>
            </div>
            <h3 class="modal-title" id="toggleActiveTitle" style="margin-bottom:8px;">Deactivate Staff?</h3>
            <p style="color:#64748b;font-size:13.5px;margin:0 0 6px;" id="toggleActiveName"></p>
            <p style="color:#94a3b8;font-size:12px;margin:0 0 24px;" id="toggleActiveNote"></p>
        </div>
        <div style="display:flex;gap:10px;justify-content:center;padding:0 24px 24px;">
            <button class="inv-btn-cancel" onclick="closeToggleModal()">Cancel</button>
            <button id="toggleActiveConfirmBtn"
                style="padding:9px 20px;border-radius:10px;border:none;font-size:0.83rem;font-weight:600;cursor:pointer;color:#fff;"
                onclick="confirmToggleActive()">Confirm</button>
        </div>
    </div>
</div>

<!-- Remove Staff Confirm Modal -->
<div id="removeStaffOverlay" class="modal-overlay">
    <div class="modal-box" style="max-width:400px;text-align:center;">
        <div style="padding:28px 24px 0;">
            <div
                style="width:56px;height:56px;margin:0 auto 14px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;">
                <svg fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                </svg>
            </div>
            <h3 class="modal-title" style="margin-bottom:8px;">Remove Staff Member?</h3>
            <p style="color:#64748b;font-size:13.5px;margin:0 0 6px;" id="removeStaffName"></p>
            <p style="color:#94a3b8;font-size:12px;margin:0 0 24px;">This will permanently remove this staff member.
                This action cannot be undone.</p>
        </div>
        <div style="display:flex;gap:10px;justify-content:center;padding:0 24px 24px;">
            <button class="inv-btn-cancel" onclick="closeRemoveModal()">Cancel</button>
            <button id="removeStaffConfirmBtn"
                style="padding:9px 20px;border-radius:10px;border:none;background:#dc2626;color:#fff;font-size:0.83rem;font-weight:600;cursor:pointer;"
                onclick="confirmRemoveStaff()">Remove</button>
        </div>
    </div>
</div>

<script>
    // Pass current user ID to staff_roles.js for isSelf check in buildStaffRow
    window.__PS_STAFF__ = {
        currentUserId: <?= (int) $_SESSION['user_id'] ?>,
    };
</script>
<script src="../../assets/js/admin/staff_roles.js"></script>

<?php include '../../includes/layout_close.php'; ?>