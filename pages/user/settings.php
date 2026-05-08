<?php
include '../../includes/session.php';
require_not_blacklisted(false);

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

</head>
<body>

<script src="../../assets/js/user-js/settings-inline.js"></script>
</body>
</html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title = 'Settings';
$page_hero_html = 'Account <em>Settings</em>';
$page_hero_sub = 'Manage your notifications, privacy, security, and preferences.';
$page_hero_icon = '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>';
$active_nav = 'settings';
require '../../includes/_layout.php';
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

// Load or init settings row
$settingsRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT * FROM user_settings WHERE user_id=$userId LIMIT 1"
));
if (!$settingsRow) {
    mysqli_query($conn, "INSERT INTO user_settings (user_id) VALUES ($userId)");
    $settingsRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT * FROM user_settings WHERE user_id=$userId LIMIT 1"
    ));
}
$s = $settingsRow;
$langCurrent = (string) ($s['language'] ?? 'en');
$activeSessionsCount = max(1, (int) ($s['active_sessions_count'] ?? 2));
?>

<link rel="stylesheet" href="../../assets/css/user-css/settings.css" />

<div class="settings-layout">
    <div class="settings-nav reveal">
        <a class="sn-item active" href="#" onclick="return showSection('notifications',this,event)">
            <svg viewBox="0 0 24 24">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 01-3.46 0" />
            </svg>
            Notifications
        </a>
        <a class="sn-item" href="#" onclick="return showSection('security',this,event)">
            <svg viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" />
                <path d="M7 11V7a5 5 0 0110 0v4" />
            </svg>
            Security
        </a>
        <a class="sn-item" href="#" onclick="return showSection('privacy',this,event)">
            <svg viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
            Privacy
        </a>
        <a class="sn-item" href="#" onclick="return showSection('sessions',this,event)">
            <svg viewBox="0 0 24 24">
                <rect x="2" y="3" width="20" height="14" rx="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
            </svg>
            Active Sessions
        </a>
    </div>

    <div>
        <div class="settings-section active" id="sec-notifications">
            <div class="card reveal">
                <div class="card-title"><svg viewBox="0 0 24 24">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 01-3.46 0" />
                    </svg>Email Notifications</div>
                <?php
                $email_notifs = [
                    ['label' => 'Booking Confirmations', 'desc' => 'Receive a confirmation email every time you make a booking.', 'key' => 'notif_booking_confirm'],
                    ['label' => 'Check-in Reminders', 'desc' => 'Get reminded 24 hours before your check-in date.', 'key' => 'notif_checkin_remind'],
                    ['label' => 'Promotions & Offers', 'desc' => 'Be the first to know about special deals and seasonal discounts.', 'key' => 'notif_promotions'],
                    ['label' => 'Loyalty Points Updates', 'desc' => 'Notifications when you earn or redeem loyalty points.', 'key' => 'notif_loyalty'],
                    ['label' => 'Newsletter', 'desc' => 'Monthly updates about Boracay Accommodation and Boracay travel tips.', 'key' => 'notif_newsletter'],
                ];
                foreach ($email_notifs as $n):
                    ?>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="toggle-label"><?php echo $n['label']; ?></div>
                            <div class="toggle-desc"><?php echo $n['desc']; ?></div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" id="<?php echo $n['key']; ?>" name="<?php echo $n['key']; ?>" <?php echo (!empty($s[$n['key']])) ? 'checked' : ''; ?> onchange="saveNotifications()">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card reveal rd1">
                <div class="card-title"><svg viewBox="0 0 24 24">
                        <rect x="5" y="2" width="14" height="20" rx="2" />
                        <line x1="12" y1="18" x2="12.01" y2="18" />
                    </svg>Push Notifications</div>
                <?php
                $push_notifs = [
                    ['label' => 'In-app Alerts', 'desc' => 'Real-time alerts while you\'re browsing the site.', 'key' => 'push_inapp_alerts'],
                    ['label' => 'Check-out Reminders', 'desc' => 'Reminder on your check-out morning.', 'key' => 'push_checkout_reminder'],
                    ['label' => 'New Room Availability', 'desc' => 'Get notified when a saved room becomes available.', 'key' => 'push_room_availability'],
                ];
                foreach ($push_notifs as $n):
                    ?>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="toggle-label"><?php echo $n['label']; ?></div>
                            <div class="toggle-desc"><?php echo $n['desc']; ?></div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" id="<?php echo $n['key']; ?>" <?php echo !empty($s[$n['key']]) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:18px;">
                    <button class="btn-primary" id="savePushBtn" onclick="savePushNotifications(this)">
                        <svg viewBox="0 0 24 24">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Save Preferences
                    </button>
                </div>
            </div>
        </div>

        <div class="settings-section" id="sec-security">
            <div class="card reveal">
                <div class="card-title"><svg viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>Change Password</div>
                <div class="form-grid cols-1" style="margin-bottom:14px;">
                    <div class="form-field">
                        <label>Current Password</label>
                        <input type="password" placeholder="••••••••" id="currentPw">
                    </div>
                </div>
                <div class="form-grid" style="margin-bottom:8px;">
                    <div class="form-field">
                        <label>New Password</label>
                        <input type="password" placeholder="Min. 8 characters" id="newPw"
                            oninput="checkStrength(this.value)">
                        <div class="pw-strength" id="pwBar" style="width:0;background:var(--blue-100);"></div>
                        <span class="hint" id="pwHint"></span>
                    </div>
                    <div class="form-field">
                        <label>Confirm New Password</label>
                        <input type="password" placeholder="Repeat new password" id="confirmPw">
                    </div>
                </div>
                <button class="btn-primary" style="margin-top:8px;" id="updatePasswordBtn"
                    onclick="updatePassword(this)">
                    <svg viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Update Password
                </button>
            </div>
            <div class="card reveal rd1">
                <div class="card-title"><svg viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>Two-Factor Authentication</div>
                <div class="toggle-row">
                    <div class="toggle-info">
                        <div class="toggle-label">Enable 2FA via Email OTP</div>
                        <div class="toggle-desc">A one-time code will be sent to your email each time you log in.</div>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" id="twoFactorToggle" <?php echo !empty($s['two_factor_enabled']) ? 'checked' : ''; ?> onchange="toggle2FA(this)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="settings-section" id="sec-privacy">
            <div class="card reveal">
                <div class="card-title"><svg viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>Privacy Settings</div>
                <?php
                $privacy = [
                    ['label' => 'Show Profile to Staff', 'desc' => 'Allow our front-desk team to see your profile and preferences.', 'key' => 'privacy_profile', 'type' => 'profile'],
                    ['label' => 'Share Stay History with Partners', 'desc' => 'Let partner services (spa, tours) see your booking history.', 'key' => 'privacy_share_history', 'type' => 'bool'],
                    ['label' => 'Allow Personalized Recommendations', 'desc' => 'Use your stay data to suggest rooms you might love.', 'key' => 'privacy_recommendations', 'type' => 'bool'],
                    ['label' => 'Analytics & Cookies', 'desc' => 'Help us improve the site with anonymized usage data.', 'key' => 'privacy_analytics', 'type' => 'bool'],
                ];
                foreach ($privacy as $p):
                    ?>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <div class="toggle-label"><?php echo $p['label']; ?></div>
                            <div class="toggle-desc"><?php echo $p['desc']; ?></div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" id="<?php echo $p['key']; ?>" <?php
                               if ($p['type'] === 'profile') {
                                   echo (($s['privacy_profile'] ?? 'private') === 'public') ? 'checked' : '';
                               } else {
                                   echo !empty($s[$p['key']]) ? 'checked' : '';
                               }
                               ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:18px;">
                    <button class="btn-primary" id="savePrivacyBtn" onclick="savePrivacy(this)">
                        <svg viewBox="0 0 24 24">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Save Privacy
                    </button>
                    <button class="btn-secondary" style="margin-left:8px;" onclick="requestMyData(this)">Request My
                        Data</button>
                </div>
            </div>
        </div>

        <div class="settings-section" id="sec-sessions">
            <div class="card reveal">
                <div class="card-title"><svg viewBox="0 0 24 24">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                    </svg>Active Sessions</div>
                <div class="session-item">
                    <div class="session-device"><svg viewBox="0 0 24 24">
                            <rect x="2" y="3" width="20" height="14" rx="2" />
                            <line x1="8" y1="21" x2="16" y2="21" />
                            <line x1="12" y1="17" x2="12" y2="21" />
                        </svg></div>
                    <div class="session-info">
                        <div class="session-name">Chrome on Windows <span class="session-current">This device</span>
                        </div>
                        <div class="session-detail">Iloilo, Philippines · <?php echo date('M j, Y · g:i A'); ?></div>
                    </div>
                </div>
                <?php if ($activeSessionsCount > 1): ?>
                    <div class="session-item">
                        <div class="session-device"><svg viewBox="0 0 24 24">
                                <rect x="5" y="2" width="14" height="20" rx="2" />
                                <line x1="12" y1="18" x2="12.01" y2="18" />
                            </svg></div>
                        <div class="session-info">
                            <div class="session-name">Safari on iPhone</div>
                            <div class="session-detail">Iloilo, Philippines · Mar 17, 2026 · 9:14 AM</div>
                        </div>
                        <button class="btn-danger" style="font-size:0.72rem;padding:6px 13px;"
                            onclick="revokeSession(this)">Revoke</button>
                    </div>
                <?php endif; ?>
                <div style="margin-top:16px;">
                    <button class="btn-danger" onclick="signOutOtherDevices(this)">Sign Out
                        All Other Devices</button>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Settings Right Sidebar (3rd grid column) ── -->
    <div class="settings-right-side">

        <div class="widget-card reveal rd1">
            <div class="widget-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                Account Security
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Password</span>
                <span class="mini-stat-val" style="color:#16a34a;">✓ Set</span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">2FA / OTP</span>
                <span class="mini-stat-val" id="security2faStatus"
                    style="color:<?php echo !empty($s['two_factor_enabled']) ? '#16a34a' : '#94a3b8'; ?>;">
                    <?php echo !empty($s['two_factor_enabled']) ? '✓ On' : 'Off'; ?>
                </span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Active Sessions</span>
                <span class="mini-stat-val" id="activeSessionsCount"><?php echo $activeSessionsCount; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Last Login</span>
                <span class="mini-stat-val"><?php echo date('M j, g:i A'); ?></span>
            </div>
        </div>

        <div class="tip-card reveal rd2">
            <div class="tip-card-label">🔒 Security tip</div>
            <div class="tip-card-title">Keep your account safe</div>
            <div class="tip-card-body">Use a strong unique password and never share your login details with anyone.
            </div>
        </div>

    </div><!-- /settings-right-side -->
</div><!-- /settings-layout -->

<script src="../../assets/js/user-js/settings.js"></script>
<script src="../../assets/js/user-js/settings-inline.js"></script>
<script>window.PS_RT_PAGE = 'settings';</script>
<?php require '../../includes/_layout_end.php'; ?>