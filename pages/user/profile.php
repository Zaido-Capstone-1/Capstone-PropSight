<?php
include '../../includes/session.php';
require_not_blacklisted(false);

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

</head>
<body>

<script src="../../assets/js/user-js/profile-inline.js"></script>
</body>
</html>';
    exit;
}
$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$phone = htmlspecialchars($_SESSION['phone'] ?? '');
$nationality = htmlspecialchars($_SESSION['nationality'] ?? '');
$birthday = htmlspecialchars($_SESSION['birthday'] ?? '');
$gender = htmlspecialchars($_SESSION['gender'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title = 'View Profile';
$page_hero_html = 'My <em>Profile</em>';
$page_hero_sub = 'Manage your personal details and account information.';
$page_hero_icon = '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>';
$active_nav = 'profile';
require '../../includes/_layout.php';
require_once '../../lib/user-queries/profile_queries.php';

?>

<link rel="stylesheet" href="../../assets/css/user-css/profile.css">

<div class="page-two-col">
    <div class="col-main">

        <div class="card reveal rd1">
            <div class="avatar-block">
                <div class="avatar-circle">
                    <?php if ($profilePhoto): ?>
                        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo" class="avatar-photo"
                            onerror="this.style.display='none';this.parentElement.classList.add('avatar-photo-fallback');">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                    <div class="avatar-edit-btn" title="Change photo" onclick="openModal('profilePhotoModal')">
                        <svg viewBox="0 0 24 24">
                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                        </svg>
                    </div>
                </div>
                <div class="avatar-info">
                    <h2 id="profile-full-name"><?php echo $full_name; ?></h2>
                    <p id="profile-header-email"><?php echo $email; ?></p>
                    <div class="avatar-since">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        Member since <?php echo $memberSince; ?>
                    </div>
                </div>
            </div>

            <div class="card-title">
                <svg viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
                Personal Information
                <button class="btn-secondary" style="margin-left:auto;font-size:0.72rem;padding:7px 16px;"
                    onclick="openEditModal()">Edit Profile</button>
            </div>

            <div class="info-row">
                <span class="info-label">First Name</span>
                <span class="info-value" data-profile-field="first_name""><?php echo $first_name ?: '—'; ?></span>
            </div>
            <div class=" info-row">
                    <span class="info-label">Last Name</span>
                    <span class="info-value" data-profile-field="last_name""><?php echo $last_name ?: '—'; ?></span>
            </div>
            <div class=" info-row info-row-email">
                        <span class="info-label">Email</span>
                        <span class="info-value" data-profile-field="email"
                            info-email-value"><?php echo $email ?: '—'; ?></span>
                        <div class="info-email-actions">
                            <span class="badge <?php echo $isVerified ? 'badge-green' : 'badge-gold'; ?>">
                                <?php echo $isVerified ? '✓ Verified' : '⚠ Not Verified'; ?>
                            </span>
                            <?php if (!$isVerified): ?>
                                <button type="button" class="btn-secondary btn-verify-now" onclick="openVerifyEmailModal()">
                                    Verify now
                                </button>
                            <?php endif; ?>
                        </div>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value" data-profile-field="phone""><?php echo $phone ?: '—'; ?></span>
            </div>
            <div class=" info-row">
                    <span class="info-label">Country</span>
                    <span class="info-value"><?php echo $nationality ?: '—'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><?php echo $birthday ?: '—'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Gender</span>
                <span class="info-value"><?php echo $gender ?: '—'; ?></span>
            </div>
        </div>

        <div class="card reveal rd2">
            <div class="card-title">
                <svg viewBox="0 0 24 24">
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <line x1="2" y1="10" x2="22" y2="10" />
                </svg>
                Identity Verification
            </div>

            <?php if ($idVerified === 'approved'): ?>
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <p style="font-size:.88rem;color:var(--text-mid);margin-bottom:4px;">Government ID submitted</p>
                        <p style="font-size:.78rem;color:var(--text-soft);">Your identity has been verified. You're cleared
                            to book.</p>
                    </div>
                    <span class="badge badge-green">✓ Verified</span>
                </div>
                <div class="card-section-divider"></div>
                <button class="btn-secondary" id="reuploadIdBtn" onclick="openModal('uploadIdModal')">Re-upload ID</button>

            <?php elseif ($idVerified === 'pending'): ?>
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <p style="font-size:.88rem;color:var(--text-mid);margin-bottom:4px;">ID submitted — processing</p>
                        <p style="font-size:.78rem;color:var(--text-soft);">This should resolve shortly. Try refreshing the
                            page.</p>
                    </div>
                    <span class="badge" style="background:#fef3c7;color:#92400e;">⏳ Processing</span>
                </div>

            <?php elseif ($idVerified === 'rejected'): ?>
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <p style="font-size:.88rem;color:#dc2626;font-weight:600;margin-bottom:4px;">ID Verification Failed
                        </p>
                        <p style="font-size:.82rem;color:var(--text-soft);">
                            Reason:
                            <strong><?php echo htmlspecialchars($idRejectReason ?: 'Document was unclear or invalid.'); ?></strong>
                        </p>
                        <p style="font-size:.78rem;color:var(--text-soft);margin-top:4px;">Please upload a clearer copy of a
                            valid government ID to continue booking.</p>
                    </div>
                    <span class="badge" style="background:#fee2e2;color:#dc2626;">✗ Rejected</span>
                </div>
                <div class="card-section-divider"></div>
                <button class="btn-primary" id="reuploadIdBtn" onclick="openModal('uploadIdModal')">Re-upload ID</button>

            <?php else: /* none */ ?>
                <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <p style="font-size:.88rem;color:var(--text-mid);font-weight:600;margin-bottom:4px;">ID not yet
                            submitted</p>
                        <p style="font-size:.78rem;color:var(--text-soft);">A valid government ID is required before you can
                            book a unit (passport, driver's license, or national ID).</p>
                    </div>
                    <span class="badge" style="background:#fee2e2;color:#dc2626;">✗ Required</span>
                </div>
                <div class="card-section-divider"></div>
                <button class="btn-primary" id="reuploadIdBtn" onclick="openModal('uploadIdModal')">Upload Government
                    ID</button>
            <?php endif; ?>

        </div>

        <div class="card reveal rd3" style="border-color:#fecaca;">
            <div class="card-title" style="color:#dc2626;">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                Danger Zone
            </div>
            <p style="font-size:0.85rem;color:var(--text-soft);margin-bottom:16px;">Permanently delete your account
                and
                all associated data. This action cannot be undone.</p>
            <button class="btn-danger">Delete My Account</button>
        </div>

    </div><!-- /col-main -->

    <!-- ── Sidebar ── -->
    <div class="col-side">

        <div class="widget-card reveal rd1">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                Account Health
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Email Verified</span>
                <span class="mini-stat-val"><?php echo $isVerified ? '✓ Yes' : '✗ No'; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Phone Added</span>
                <span class="mini-stat-val"><?php echo $phone ? '✓ Yes' : '—'; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">ID Verified</span>
                <span
                    class="mini-stat-val <?php echo $idVerified === 'approved' ? 'text-success' : ($idVerified === 'pending' ? 'text-warn' : 'text-danger'); ?>">
                    <?php
                    echo match ($idVerified) {
                        'approved' => '✓ Verified',
                        'pending' => '⏳ Under Review',
                        'rejected' => '✗ Rejected',
                        default => '✗ Not Submitted',
                    };
                    ?>
                </span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Member Since</span>
                <span class="mini-stat-val"><?php echo $memberSince; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Total Stays</span>
                <span class="mini-stat-val"><?php echo $totalStays; ?></span>
            </div>
        </div>

        <div class="tip-card reveal rd2">
            <div class="tip-card-label">🥇 <?php echo $tierName; ?> Member</div>
            <div class="tip-card-title">
                <?php echo $tierName !== 'Diamond' ? 'Reach the next tier' : 'Diamond Status!'; ?>
            </div>
            <div class="tip-card-body">
                <?php if ($tierName !== 'Diamond'): ?>
                    You have <strong><?php echo number_format($loyaltyBal); ?> pts</strong>. Keep booking to unlock more
                    perks.
                <?php else: ?>
                    You've reached the highest tier. Enjoy all Diamond privileges!
                <?php endif; ?>
            </div>
            <a href="loyalty.php" class="tip-card-cta">View Loyalty →</a>
        </div>

    </div><!-- /col-side -->
</div><!-- /page-two-col -->

<div class="modal-overlay" id="profilePhotoModal">
    <div class="modal-box" style="max-width:440px;">
        <button type="button" class="modal-close-btn" onclick="closeModal('profilePhotoModal')">✕</button>
        <div class="modal-title">Change Profile Picture</div>
        <div class="modal-sub">Upload a clear square image (JPG, PNG, WEBP · max 2MB).</div>

        <form action="../../api/user/update_profile_photo.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">

            <div class="profile-photo-preview-wrap">
                <div class="profile-photo-preview">
                    <?php if ($profilePhoto): ?>
                        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Current profile photo">
                    <?php else: ?>
                        <span><?php echo $initials; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-grid cols-1" style="margin-bottom:18px;">
                <div class="form-field">
                    <label>Select Photo</label>
                    <input type="file" id="profilePhotoFileInput" name="profile_photo" accept=".jpg,.jpeg,.png,.webp"
                        required style="display:none;">
                    <button type="button" onclick="document.getElementById('profilePhotoFileInput').click()"
                        style="display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border:1.5px dashed var(--border);border-radius:var(--r-md);background:var(--off-white);color:var(--ink-soft);font-size:.82rem;font-weight:500;cursor:pointer;transition:border-color .2s,color .2s;"
                        onmouseenter="this.style.borderColor='var(--navy-400)';this.style.color='var(--navy-700)'"
                        onmouseleave="this.style.borderColor='var(--border)';this.style.color='var(--ink-soft)'"
                        id="profilePhotoPickerBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0;">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <span id="profilePhotoPickerLabel">Choose a photo…</span>
                    </button>
                </div>
            </div>
            <p id="profilePhotoMsg" style="font-size:.8rem;margin-top:-.5rem;margin-bottom:8px;display:none;"></p>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn-secondary" onclick="closeModal('profilePhotoModal')">Cancel</button>
                <button type="button" class="btn-primary" id="profilePhotoSubmitBtn" onclick="submitProfilePhoto()">
                    <svg viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Upload Photo
                </button>
            </div>
            <input type="hidden" id="profilePhotoCsrf"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box edit-profile-modal-box">
        <form action="../../api/user/edit_profile.php" method="POST">
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="edit_profile" value="1">

            <button type="button" class="modal-close-btn" onclick="closeEditModal()">✕</button>

            <div class="modal-title">Edit Profile</div>
            <div class="modal-sub">Update your personal information below.</div>

            <div class="form-grid" style="margin-bottom:14px;">
                <div class="form-field">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                </div>
                <div class="form-field">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                </div>
            </div>

            <div class="form-grid cols-1" style="margin-bottom:14px;">
                <div class="form-field">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
            </div>

            <div class="form-grid" style="margin-bottom:14px;">
                <div class="form-field">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                </div>
                <div class="form-field">
                    <label>Country</label>
                    <select name="nationality">
                        <option value="">-- Select Country --</option>
                        <?php
                        $countries = ["Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina", "Armenia", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia", "Cameroon", "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", "Congo", "Costa Rica", "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan", "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"];
                        $sel_nat = htmlspecialchars($nationality ?? '');
                        foreach ($countries as $c) {
                            $sel = ($sel_nat === $c) ? ' selected' : '';
                            echo '<option value="' . htmlspecialchars($c) . '"' . $sel . '>' . htmlspecialchars($c) . "</option>\n";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-grid" style="margin-bottom:22px;">
                <div class="form-field">
                    <label>Date of Birth</label>
                    <input type="date" name="birthday" value="<?php echo htmlspecialchars($birthday); ?>">
                </div>
                <div class="form-field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Prefer not to say" <?php echo $gender === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
                    </select>
                </div>
            </div>

            <div id="editError"
                style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">
                    <svg viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="uploadIdModal">
    <div class="modal-box" style="max-width:440px;">
        <button class="modal-close-btn" onclick="closeModal('uploadIdModal')">✕</button>
        <div class="modal-title">Upload New ID</div>
        <div class="modal-sub">Accepted formats: JPG, PNG, PDF · Max 5MB</div>
        <input type="hidden" id="uploadIdCsrfToken"
            value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
        <div id="dropzone"
            style="border:2px dashed var(--blue-200);border-radius:14px;padding:36px 20px;text-align:center;cursor:pointer;transition:border-color 0.2s,background 0.2s;margin-bottom:16px;"
            onclick="document.getElementById('idFileInput').click()"
            ondragover="event.preventDefault();this.style.borderColor='var(--blue-400)';this.style.background='var(--blue-50)'"
            ondragleave="this.style.borderColor='var(--blue-200)';this.style.background=''"
            ondrop="handleFileDrop(event)">
            <svg viewBox="0 0 24 24"
                style="width:36px;height:36px;stroke:var(--blue-300);fill:none;stroke-width:1.5;margin:0 auto 10px;display:block;">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
            <p style="font-size:0.85rem;font-weight:600;color:var(--text-dark);margin-bottom:4px;">Click to browse
                or
                drag & drop</p>
            <p style="font-size:0.74rem;color:var(--text-soft);" id="dropzoneLabel">No file selected</p>
        </div>
        <input type="file" id="idFileInput" accept=".jpg,.jpeg,.png,.pdf" style="display:none"
            onchange="handleFileSelect(this)">
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-secondary" onclick="closeModal('uploadIdModal')">Cancel</button>
            <button class="btn-primary" id="uploadIdBtn" onclick="submitUpload()" disabled style="opacity:0.5;">
                <svg viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Upload ID
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="max-width:420px;">
        <button class="modal-close-btn" onclick="closeModal('deleteModal')">✕</button>
        <div style="text-align:center;padding:10px 0 20px;">
            <div
                style="width:56px;height:56px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg viewBox="0 0 24 24" style="width:26px;height:26px;stroke:#ef4444;fill:none;stroke-width:2;">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                </svg>
            </div>
            <div class="modal-title" style="color:#dc2626;">Delete Account?</div>
            <p style="font-size:0.84rem;color:var(--text-soft);margin:8px 0 20px;line-height:1.7;">This will
                permanently
                delete your account, all bookings, and loyalty points. Type <strong>DELETE</strong> to confirm.</p>
            <input type="hidden" id="deleteCsrfToken"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
            <input type="text" id="deleteConfirmInput" placeholder='Type "DELETE"'
                style="width:100%;padding:10px 13px;border:1.5px solid #fecaca;border-radius:var(--radius);font-family:'Jost',sans-serif;font-size:0.88rem;color:var(--text-dark);outline:none;margin-bottom:16px;"
                oninput="document.getElementById('confirmDeleteBtn').disabled=this.value!=='DELETE';document.getElementById('confirmDeleteBtn').style.opacity=this.value==='DELETE'?'1':'0.5'">
            <div style="display:flex;gap:10px;justify-content:center;">
                <button class="btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button id="confirmDeleteBtn" disabled
                    style="opacity:0.5;font-family:'Jost',sans-serif;font-size:0.84rem;font-weight:600;background:#ef4444;color:#fff;border:none;padding:10px 22px;border-radius:40px;cursor:pointer;"
                    onclick="confirmDelete()">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<?php if (!$isVerified): ?>
    <div class="modal-overlay" id="verifyEmailModal">
        <div class="modal-box verify-email-modal-box">
            <button class="modal-close-btn" onclick="closeModal('verifyEmailModal')">✕</button>
            <div class="modal-title">Verify your email</div>
            <div class="modal-sub">Send a verification code to your email, then enter the 6-digit code below.</div>

            <input type="hidden" id="verifyCsrfToken"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="form-grid cols-1" style="margin:16px 0 12px;">
                <div class="form-field">
                    <label>Email Address</label>
                    <input type="email" id="verifyEmailInput" value="<?php echo $email; ?>" placeholder="you@example.com"
                        required>
                </div>
            </div>

            <div class="verify-email-row">
                <button type="button" class="btn-secondary" id="sendVerifyCodeBtn" onclick="sendVerificationCode()">
                    Send Code
                </button>
                <span class="verify-email-hint" id="verifyEmailHint">We will send a 6-digit code valid for 10
                    minutes.</span>
            </div>

            <div class="form-grid cols-1" style="margin:12px 0 18px;">
                <div class="form-field">
                    <label>Verification Code</label>
                    <input type="text" id="verifyCodeInput" placeholder="Enter 6-digit code" maxlength="6"
                        inputmode="numeric" autocomplete="one-time-code">
                </div>
            </div>

            <div id="verifyEmailError" class="verify-email-error" style="display:none;"></div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button class="btn-secondary" type="button" onclick="closeModal('verifyEmailModal')">Cancel</button>
                <button class="btn-primary" type="button" id="confirmVerifyBtn" onclick="confirmEmailVerification()">
                    Verify Email
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$toastSuccess = $_SESSION['toast_success'] ?? '';
$toastError = $_SESSION['toast_error'] ?? '';
unset($_SESSION['toast_success'], $_SESSION['toast_error']);
?>
<?php if ($toastSuccess || $toastError): ?>
    <script>
        window.__PS_PROFILE__ = {
            toastSuccess: <?php echo json_encode($toastSuccess ?? null); ?>,
            toastError: <?php echo json_encode($toastError ?? null); ?>
        };
    </script>
    <script src="../../assets/js/user-js/profile-toast.js"></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var inp = document.getElementById('profilePhotoFileInput');
    if (inp) {
        inp.addEventListener('change', function () {
            var lbl = document.getElementById('profilePhotoPickerLabel');
            if (lbl) lbl.textContent = this.files[0] ? this.files[0].name : 'Choose a photo…';
            // update preview
            if (this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var prev = document.querySelector('.profile-photo-preview img');
                    if (prev) { prev.src = e.target.result; }
                    else {
                        var wrap = document.querySelector('.profile-photo-preview');
                        if (wrap) { wrap.innerHTML = '<img src="' + e.target.result + '" alt="Preview">'; }
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>
<script src="../../assets/js/user-js/profile.js"></script>
<script>window.PS_RT_PAGE = 'profile';</script>
<?php require '../../includes/_layout_end.php'; ?>