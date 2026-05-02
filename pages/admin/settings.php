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

$sysSettingsRes = mysqli_query($conn, "SELECT setting_key, value FROM admin_settings");
$sysCfg = [];
while ($r = mysqli_fetch_assoc($sysSettingsRes))
    $sysCfg[$r['setting_key']] = $r['value'];

$initials = strtoupper(mb_substr($adminRow['first_name'], 0, 1) . mb_substr($adminRow['last_name'], 0, 1));
?>
<style>
    @media (max-width: 480px) {
        #ps-toast-container {
            bottom: 120px !important;
        }
    }
</style>

<div class="page-header">
    <div class="top-header">
        <h2>Settings</h2>
        <div class="page-header-sub">Manage your account, system preferences, and integrations</div>
    </div>
</div>

<div class="page-inner">
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
                        style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--blue-300),var(--blue-700));display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:white;flex-shrink:0;overflow:hidden;position:relative;cursor:pointer;"
                        onclick="document.getElementById('adminPhotoInput').click();" title="Click to change photo">
                        <?php if ($adminPhotoUrl): ?>
                            <img id="settingsAvatarImg" src="<?= htmlspecialchars($adminPhotoUrl) ?>" alt="Profile photo"
                                style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
                                onerror="this.style.display='none';document.getElementById('settingsAvatarInitials').style.display='flex';">
                            <span id="settingsAvatarInitials"
                                style="display:none;position:absolute;inset:0;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:white;">
                                <?= htmlspecialchars($initials) ?>
                            </span>
                        <?php else: ?>
                            <img id="settingsAvatarImg" src="" alt=""
                                style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;">
                            <span id="settingsAvatarInitials"
                                style="display:flex;position:absolute;inset:0;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:white;">
                                <?= htmlspecialchars($initials) ?>
                            </span>
                        <?php endif; ?>
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
                    <div class="form-actions" style="margin-top:16px;"><button type="submit"
                            class="btn btn-primary" onclick="saveProfile(event)">Save Changes</button></div>
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
                    <div class="form-actions" style="margin-top:16px;"><button type="submit"
                            class="btn btn-primary" onclick="changePassword(event)">Update Password</button></div>
                </form>
                <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border);">
                    <div style="font-size:14px;font-weight:700;margin-bottom:12px;">Two-Factor Authentication</div>
                    <div
                        style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--gray-light);border-radius:var(--radius);">
                        <div>
                            <div style="font-size:13.5px;font-weight:600;">Authenticator App</div>
                            <div style="font-size:11px;color:var(--text-soft);">Use Google Authenticator or similar
                            </div>
                        </div>
                        <span class="badge badge-success">Enabled</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">System Preferences</span></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
                <?php
                $prefs = [
                    ['Default Currency', 'PHP (₱ Philippine Peso)', 'select', ['PHP (₱)', 'USD ($)', 'EUR (€)']],
                    ['Date Format', 'MM/DD/YYYY', 'select', ['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD']],
                    ['Time Zone', 'Asia/Manila (UTC+8)', 'select', ['Asia/Manila', 'Asia/Singapore', 'UTC']],
                    ['Language', 'English', 'select', ['English', 'Filipino', 'Español']],
                ];
                foreach ($prefs as $pref): ?>
                    <div class="form-group">
                        <label><?= $pref[0] ?></label>
                        <select>
                            <?php foreach ($pref[3] as $opt): ?>
                                <option <?= $opt === $pref[1] ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Notification Preferences</span></div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php
                $notifs = [
                    ['New Reservation', 'Get notified when a new booking is made', true],
                    ['Check-in Reminders', 'Alert 1 hour before guest check-in', true],
                    ['Payment Received', 'Notify when a payment is confirmed', true],
                    ['Maintenance Requests', 'Alert when a maintenance task is filed', true],
                    ['Monthly Reports', 'Receive auto-generated monthly reports', false],
                    ['Low Occupancy Alerts', 'Notify when occupancy drops below 50%', false],
                ];
                foreach ($notifs as $n): ?>
                    <div
                        style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:var(--gray-light);border-radius:var(--radius);">
                        <div>
                            <div style="font-size:13.5px;font-weight:600;"><?= $n[0] ?></div>
                            <div style="font-size:11px;color:var(--text-soft);"><?= $n[1] ?></div>
                        </div>
                        <div
                            style="width:40px;height:22px;border-radius:20px;background:<?= $n[2] ? 'var(--blue-400)' : 'var(--border)' ?>;position:relative;cursor:pointer;transition:background .2s;">
                            <div
                                style="width:16px;height:16px;border-radius:50%;background:white;position:absolute;top:3px;left:<?= $n[2] ? '21px' : '3px' ?>;transition:left .2s;">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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