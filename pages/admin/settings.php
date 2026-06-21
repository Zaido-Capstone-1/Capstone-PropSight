<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/settings-inline.js"></script>
</body>
</html>';
    exit;
}

$page_title = 'Settings';
$active_page = 'settings';
include '../../includes/db.php';
include '../../includes/layout_open.php';

$adminId = (int) $_SESSION['user_id'];
$adminRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT * FROM users WHERE user_id=$adminId LIMIT 1"
));

$initials = strtoupper(mb_substr($adminRow['first_name'], 0, 1) . mb_substr($adminRow['last_name'], 0, 1));
?>

<link rel="stylesheet" href="../../assets/css/admin-css/header.css">
<link rel="stylesheet" href="../../assets/css/admin-css/settings.css">
<?php include '../../lib/admin-queries/settings_queries.php'; ?>

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Settings</h1>
            <p class="dash-subtitle">Manage your account, system preferences, and integrations.</p>
        </div>
    </div>

    <div class="cards-area">

        <div class="two-col">

            <div class="card">
                <div class="card-header"><span class="card-title">Profile Information</span></div>
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                    <?php
                    $adminPhotoRaw = trim((string) ($adminRow['profile_photo'] ?? ''));
                    $adminPhotoUrl = $adminPhotoRaw !== '' ? '../../' . ltrim($adminPhotoRaw, '/') : '';
                    ?>
                    <div id="settingsAvatarWrap"
                        style="width:64px;height:64px;border-radius:50%;<?= $adminPhotoUrl ? '' : 'background:linear-gradient(135deg,var(--blue-300),var(--blue-700));' ?>display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:white;flex-shrink:0;overflow:hidden;position:relative;cursor:pointer;"
                        onclick="document.getElementById('adminPhotoInput').click();" title="Click to change photo">
                        <?php if ($adminPhotoUrl): ?>
                            <img id="settingsAvatarImg" src="<?= htmlspecialchars($adminPhotoUrl) ?>" alt="Profile photo"
                                style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                            <img id="settingsAvatarImg" src="" alt=""
                                style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php endif; ?>
                        <span id="settingsAvatarInitials"
                            style="<?= $adminPhotoUrl ? 'display:none;' : 'display:flex;' ?>position:absolute;inset:0;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:white;">
                            <?= htmlspecialchars($initials) ?>
                        </span>
                        <div style="position:absolute;inset:0;background:rgba(0,0,0,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s;"
                            onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0'">
                            <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"
                                style="width:22px;height:22px;">
                                <path
                                    d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                <circle cx="12" cy="13" r="4" />
                            </svg>
                        </div>
                    </div>
                    <input type="file" id="adminPhotoInput" accept="image/*" style="display:none;"
                        onchange="uploadAdminPhoto(this)">
                    <div>
                        <div style="font-size:16px;font-weight:700;">
                            <?php echo htmlspecialchars($adminRow['first_name'] . '  ' . $adminRow['last_name']); ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-soft);">Property Manager · Super Admin</div>
                        <button class="btn btn-secondary" type="button"
                            style="margin-top:8px;padding:5px 12px;font-size:12px;"
                            onclick="document.getElementById('adminPhotoInput').click();">Change Photo</button>
                        <?php if ($adminPhotoUrl): ?>
                            <button class="btn" type="button" id="removePhotoBtn"
                                style="margin-top:8px;margin-left:6px;padding:5px 12px;font-size:12px;color:var(--danger,#dc2626);border-color:var(--danger,#dc2626);"
                                onclick="removeAdminPhoto()">Remove</button>
                        <?php endif; ?>
                    </div>
                </div>
                <form id="profileForm">
                    <div class="form-grid">
                        <div class="form-group"><label>First Name</label><input id="adm_fn" type="text"
                                value="<?php echo htmlspecialchars($adminRow['first_name']); ?>" /></div>
                        <div class="form-group"><label>Last Name</label><input id="adm_ln" type="text"
                                value="<?php echo htmlspecialchars($adminRow['last_name']); ?>" /></div>
                        <div class="form-group"><label>Email</label><input id="adm_email" type="email"
                                value="<?php echo htmlspecialchars($adminRow['email']); ?>" />
                        </div>
                        <div class="form-group"><label>Phone</label><input id="adm_phone" type="tel"
                                value="<?php echo htmlspecialchars($adminRow['phone'] ?? ''); ?>" /></div>
                        <div class="form-group full"><label>Address</label><input id="adm_addr" type="text"
                                value="<?php echo htmlspecialchars($adminRow['address'] ?? ''); ?>" /></div>
                    </div>
                    <div class="form-actions" style="margin-top:16px;"><button type="submit" class="btn btn-primary"
                            onclick="saveProfile(event)">Save Changes</button></div>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">Security</span></div>
                <form id="securityForm">
                    <div class="form-grid" style="grid-template-columns:1fr;">
                        <div class="form-group"><label>Current Password</label><input id="cur_pw" type="password"
                                placeholder="••••••••" /></div>
                        <div class="form-group"><label>New Password</label><input id="new_pw" type="password"
                                placeholder="••••••••" /></div>
                        <div class="form-group"><label>Confirm New Password</label><input id="conf_pw" type="password"
                                placeholder="••••••••" /></div>
                    </div>
                    <div class="form-actions" style="margin-top:16px;"><button type="submit" class="btn btn-primary"
                            onclick="changePassword(event)">Update Password</button></div>
                </form>
                <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border);">
                    <div style="font-size:14px;font-weight:700;margin-bottom:12px;">Two-Factor Authentication</div>
                    <div
                        style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--gray-light);border-radius:var(--radius);">
                        <div>
                            <div style="font-size:13.5px;font-weight:600;">Enable 2FA via Email OTP</div>
                            <div style="font-size:11px;color:var(--text-soft);">A one-time code will be sent to your
                                email each time you log in.</div>
                        </div>
                        <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;">
                            <input type="checkbox" id="admin2faToggle" <?php
                            $admin2faRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT two_factor_enabled FROM user_settings WHERE user_id=$adminId LIMIT 1"));
                            echo !empty($admin2faRow['two_factor_enabled']) ? 'checked' : '';
                            ?>
                                onchange="toggleAdmin2FA(this)" style="opacity:0;width:0;height:0;position:absolute;">
                            <span id="admin2faSlider"
                                style="position:absolute;cursor:pointer;inset:0;background:<?php echo !empty($admin2faRow['two_factor_enabled']) ? 'var(--blue-500,#3b82f6)' : 'var(--border,#cbd5e1)' ?>;border-radius:24px;transition:background .2s;">
                                <span
                                    style="position:absolute;content:'';height:18px;width:18px;left:<?php echo !empty($admin2faRow['two_factor_enabled']) ? '23px' : '3px' ?>;bottom:3px;background:white;border-radius:50%;transition:left .2s;display:block;"
                                    id="admin2faKnob"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Contact Information</span></div>
            <p style="font-size:12px;color:var(--text-soft);margin-bottom:16px;">
                These details appear in the public-facing footer of the landing page.
            </p>
            <div style="display:grid;gap:14px;">
                <div class="form-group">
                    <label>Address</label>
                    <input id="contact_address" type="text"
                        value="<?php echo htmlspecialchars($sysCfg['contact_address'] ?? 'Station 3, Barangay Manoc-Manoc, Boracay Island, Aklan 5608'); ?>"
                        placeholder="Full address" />
                </div>
                <div class="form-group">
                    <label>Phone (Primary)</label>
                    <input id="contact_phone" type="text"
                        value="<?php echo htmlspecialchars($sysCfg['contact_phone'] ?? '+63 33 123 4567'); ?>"
                        placeholder="+63 33 123 4567" />
                </div>
                <div class="form-group">
                    <label>Phone (Secondary)</label>
                    <input id="contact_phone2" type="text"
                        value="<?php echo htmlspecialchars($sysCfg['contact_phone2'] ?? '+63 912 345 6789'); ?>"
                        placeholder="+63 912 345 6789" />
                </div>
                <div class="form-group">
                    <label>Support Email</label>
                    <input id="contact_email" type="email"
                        value="<?php echo htmlspecialchars($sysCfg['contact_email'] ?? 'hello@boracayaccommodation.ph'); ?>"
                        placeholder="hello@example.com" />
                </div>
            </div>
            <div class="form-actions" style="margin-top:16px;">
                <button class="btn btn-primary" onclick="saveContactInfo(event)">Save Contact Info</button>
            </div>
        </div>


        <!-- ── Backup & Recovery ────────────────────────────────── -->
        <div class="card">
            <div class="card-header backup-card-header">
                <div>
                    <span class="card-title" style="display:block;margin-bottom:4px;">Backup &amp; Recovery</span>
                    <p style="font-size:12px;color:var(--text-soft);margin:0;">Generate a full SQL snapshot of the
                        database. Each backup can be downloaded or used to restore data.</p>
                </div>
                <button onclick="generateBackup()" id="generateBackupBtn"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:var(--blue-500,#3b82f6);color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                        height="14">
                        <ellipse cx="12" cy="5" rx="9" ry="3" />
                        <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" />
                        <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" />
                    </svg>
                    Generate Backup
                </button>
            </div>

            <!-- Backup list -->
            <div id="backupList" style="margin-top:16px;">
                <div style="text-align:center;padding:32px 16px;color:var(--text-soft);font-size:13px;">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="32" height="32"
                        style="opacity:.3;display:block;margin:0 auto 10px;">
                        <ellipse cx="12" cy="5" rx="9" ry="3" />
                        <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" />
                        <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" />
                    </svg>
                    Loading backups…
                </div>
            </div>

            <!-- Warning -->
            <div
                style="display:flex;align-items:flex-start;gap:8px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:9px;margin-top:14px;">
                <svg fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"
                    style="flex-shrink:0;margin-top:1px;">
                    <path
                        d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                <span style="font-size:11.5px;color:#92400e;line-height:1.5;">
                    Restoring will <strong>permanently overwrite</strong> all current data. Always generate a fresh
                    backup before restoring.
                </span>
            </div>
        </div>

    </div>
</div>
<script>
    window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
</script>
<script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/settings.js"></script>
<?php include '../../includes/layout_close.php'; ?>