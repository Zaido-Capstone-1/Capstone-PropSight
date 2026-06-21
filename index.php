<?php
require_once __DIR__ . '/includes/session_params.php';
session_start();

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
include_once __DIR__ . '/includes/fetch_units.php';

$sysRes = mysqli_query($conn, "SELECT setting_key, value FROM admin_settings");
$sysCfg = [];
while ($sr = mysqli_fetch_assoc($sysRes)) $sysCfg[$sr['setting_key']] = $sr['value'];

$contactAddress = htmlspecialchars($sysCfg['contact_address'] ?? 'Station 3, Barangay Manoc-Manoc, Boracay Island, Aklan 5608');
$contactPhone   = htmlspecialchars($sysCfg['contact_phone']   ?? '+63 33 123 4567');
$contactEmail   = htmlspecialchars($sysCfg['contact_email']   ?? 'hello@boracayaccommodation.ph');

function landing_unit_name(array $unit): string
{
    $rawNum = trim(preg_replace('/^unit\s*/i', '', $unit['unit_number'] ?? ''));
    if (!empty($unit['unit_name'])) {
        return $unit['unit_name'];
    }
    if (!empty($unit['property_name']) && $rawNum !== '') {
        return $unit['property_name'] . ' — Unit ' . $rawNum;
    }
    if (!empty($unit['unit_number'])) {
        return $unit['unit_number'];
    }
    return 'Unit #' . ($unit['unit_id'] ?? '');
}

function hero_img_path(?string $raw): string {
    if (empty($raw)) return '';
    // Strip leading ../ or / so path is always relative to site root
    $clean = preg_replace('#^(\\.\\./)+#', '', ltrim($raw, '/'));
    return $clean;
}

$landingUnits = $units ?? [];
$heroUnits = array_slice($landingUnits, 0, 8);

// ── Dynamic hero stats ───────────────────────────────────────────────────────
$statRooms = count($units ?? []);

$statRating = null;
if (isset($conn) && $conn) {
    $rRes = mysqli_query($conn, "SELECT ROUND(AVG(rating), 1) AS avg_rating FROM booking_reviews");
    if ($rRes && ($rRow = mysqli_fetch_assoc($rRes))) {
        $statRating = $rRow['avg_rating'];
    }
}
$statRatingDisplay = $statRating ? number_format($statRating, 1) . '★' : 'N/A';

$foundingYear = 2012;
$statYears = (int) date('Y') - $foundingYear;
// ─────────────────────────────────────────────────────────────────────────────

$landingReviews = [];
if (isset($conn) && $conn) {
    $reviewsRes = mysqli_query($conn, "
    SELECT br.rating, br.comment, br.created_at,
           u.first_name, u.last_name, u.profile_photo,
           COALESCE(un.unit_name, un.unit_number, 'Room') AS room_name,
           p.property_name
    FROM booking_reviews br
    JOIN users u ON u.user_id = br.user_id
    LEFT JOIN units un ON un.unit_id = br.unit_id
    LEFT JOIN properties p ON p.property_id = un.property_id
    LEFT JOIN bookings b ON b.booking_id = br.booking_id
    WHERE br.comment IS NOT NULL AND br.comment != ''
    ORDER BY RAND()
    LIMIT 6
");
    while ($reviewsRes && ($rr = mysqli_fetch_assoc($reviewsRes))) {
        $landingReviews[] = $rr;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boracay Accommodation — Investment Properties & Services</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="assets/images/logo.png" />
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap"
        rel="stylesheet">
</head>

<body>

    <header id="hdr">
        <a href="#" class="logo">
            <img src="assets/images/logo.png" alt="Boracay Accommodation Logo" class="logo-icon">
            <span>
                <span style="display:block;line-height:1.1;">Boracay Accommodation</span>
                <span
                    style="display:block;font-family:'Jost',sans-serif;font-size:0.45rem;font-weight:500;letter-spacing:0.12em;color:var(--text-soft);text-transform:uppercase;">Investment
                    Properties & Services</span>
            </span>
        </a>

        <nav>
            <a href="#hero">Home</a>
            <a href="#about">About</a>
            <a href="#rooms">Rooms</a>
            <a href="#reviews">Reviews</a>
            <a href="#contact">Contact</a>
        </nav>

        <div style="display:flex;align-items:center;gap:0.75rem;">
            <button class="btn-login-header">Log In</button>
            <button class="btn-book-header" onclick="openModal('signup')">Sign Up</button>
            <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
        </div>
    </header>

    <div class="mobile-nav" id="mobileNav">
        <a href="#hero" onclick="closeMob()">Home</a>
        <a href="#about" onclick="closeMob()">About</a>
        <a href="#rooms" onclick="closeMob()">Rooms</a>
        <a href="#reviews" onclick="closeMob()">Reviews</a>
        <a href="#contact" onclick="closeMob()">Contact</a>
        <a href="#" onclick="closeMob();openModal('signup');return false;" style="color:var(--gold);font-weight:600;">Sign Up</a>
        <button class="btn-login-header">Log In</button>
    </div>

    <div class="modal-overlay" id="loginModal">
        <div class="modal-box">
            <button class="modal-close" id="modalClose">&times;</button>
            <div id="modalAlert" style="display:none;"></div>

            <form id="loginForm">
                <div class="modal-form" id="tab-login">
                    <div class="modal-header">
                        <h2 class="modal-title">Welcome Back</h2>
                        <p class="modal-sub">Log in to continue your booking</p>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="modal-field">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>

                    <div class="modal-field">
                        <label>Password</label>
                        <div class="password-field-wrap">
                            <input id="loginPassword" type="password" name="password" placeholder="••••••••" required>
                            <button type="button" class="toggle-password" data-target="loginPassword"
                                aria-label="Show password" title="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button class="modal-btn-primary" type="submit">Log In</button>

                    <p style="text-align:center;margin:10px 0 0;font-size:13px;">
                        <a href="#" onclick="openForgotModal();return false;"
                            style="color:#1e3a5f;font-weight:500;text-decoration:none;">
                            Forgot your password?
                        </a>
                    </p>

                    <p class="modal-switch">
                        Don't have an account?
                        <a href="#" onclick="switchTab('signup')">Sign Up</a>
                    </p>
                </div>
            </form>

            <form id="registerForm">
                <div class="modal-form" id="tab-signup">
                    <div class="modal-header">
                        <h2 class="modal-title">Create Account</h2>
                        <p class="modal-sub">Sign up to book your stay</p>
                    </div>
                    <div class="modal-field">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    </div>

                    <div class="field-name-row">
                        <div class="modal-field">
                            <label>First Name</label>
                            <input type="text" name="first_name" placeholder="First Name" required>
                        </div>
                        <div class="modal-field">
                            <label>Last Name</label>
                            <input type="text" name="last_name" placeholder="Last Name" required>
                        </div>
                    </div>

                    <div class="modal-field">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>

                    <div class="modal-field">
                        <label>Phone Number</label>
                        <div class="phone-input-wrap">
                            <div class="phone-flag-select" id="phoneFlagSelect">
                                <span class="phone-flag-icon" id="phoneFlagIcon">🇵🇭</span>
                                <span class="phone-dial-code" id="phoneDialCode">+63</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11" class="phone-chevron">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                                <select id="signupCountryCode" name="country_code" required autocomplete="tel-country-code" class="phone-select-hidden">
                                    <option value="+63" data-flag="🇵🇭" selected>🇵🇭 Philippines +63</option>
                                    <option value="+1"  data-flag="🇺🇸">🇺🇸 United States +1</option>
                                    <option value="+44" data-flag="🇬🇧">🇬🇧 United Kingdom +44</option>
                                    <option value="+61" data-flag="🇦🇺">🇦🇺 Australia +61</option>
                                    <option value="+65" data-flag="🇸🇬">🇸🇬 Singapore +65</option>
                                    <option value="+81" data-flag="🇯🇵">🇯🇵 Japan +81</option>
                                    <option value="+82" data-flag="🇰🇷">🇰🇷 South Korea +82</option>
                                    <option value="+91" data-flag="🇮🇳">🇮🇳 India +91</option>
                                    <option value="+971" data-flag="🇦🇪">🇦🇪 UAE +971</option>
                                    <option value="+966" data-flag="🇸🇦">🇸🇦 Saudi Arabia +966</option>
                                    <option value="+974" data-flag="🇶🇦">🇶🇦 Qatar +974</option>
                                    <option value="+973" data-flag="🇧🇭">🇧🇭 Bahrain +973</option>
                                    <option value="+965" data-flag="🇰🇼">🇰🇼 Kuwait +965</option>
                                </select>
                            </div>
                            <div class="phone-divider"></div>
                            <input id="signupPhoneNumber" type="tel" name="phone" placeholder="9123456789"
                                inputmode="numeric" maxlength="10" required class="phone-number-input">
                        </div>
                        <small style="display:block;margin-top:6px;color:var(--text-soft);font-size:.72rem;">
                            Use your local number without leading 0.
                        </small>
                    </div>

                    <div class="modal-field">
                        <label>Password</label>
                        <div class="password-field-wrap">
                            <input id="signupPassword" type="password" name="password" placeholder="••••••••" required
                                minlength="6">
                            <button type="button" class="toggle-password" data-target="signupPassword"
                                aria-label="Show password" title="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <div style="margin-top:8px;">
                            <div style="height:6px;background:#e0eefa;border-radius:999px;overflow:hidden;">
                                <div id="signupPasswordStrengthBar"
                                    style="height:100%;width:0;background:#ef4444;transition:all .2s ease;"></div>
                            </div>
                            <small id="signupPasswordStrengthText"
                                style="display:block;margin-top:6px;color:var(--text-soft);font-size:.72rem;">Password
                                strength: Weak</small>
                        </div>
                    </div>

                    <div class="modal-field">
                        <label>Confirm Password</label>
                        <div class="password-field-wrap">
                            <input id="signupConfirmPassword" type="password" name="confirm_password"
                                placeholder="••••••••" required>
                            <button type="button" class="toggle-password" data-target="signupConfirmPassword"
                                aria-label="Show password" title="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <small id="signupConfirmPasswordAlert"
                            style="display:none;margin-top:6px;color:#dc2626;font-size:.72rem;"></small>
                    </div>

                    <div class="form-field" style="margin-bottom:14px;">
                        <label style="display:block;margin-bottom:6px;font-size:.8rem;font-weight:600;">Country</label>
                        <select name="nationality"
                            style="width:100%;padding:10px 12px;border:1.5px solid var(--border,#e2e8f0);border-radius:8px;font-size:.85rem;font-family:inherit;background:#fff;color:var(--text-dark);">
                            <option value="">Select your country</option>
                            <?php
                            $reg_countries = ["Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina", "Armenia", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia", "Cameroon", "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", "Congo", "Costa Rica", "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait", "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan", "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Timor-Leste", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"];
                            foreach ($reg_countries as $c) {
                                echo '<option value="' . htmlspecialchars($c) . '">' . htmlspecialchars($c) . "</option>\n";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="signup-consent">
                        <label class="signup-consent-row">
                            <input id="signupAgreeTerms" type="checkbox" required>
                            <span>
                                I agree to the
                                <a href="#" class="policy-link" data-policy="terms">Terms</a>,
                                <a href="#" class="policy-link" data-policy="privacy">Privacy Policy</a>,
                                and <a href="#" class="policy-link" data-policy="booking">Booking Policy</a>.
                            </span>
                        </label>
                        <small id="signupConsentAlert" class="signup-consent-alert"></small>
                    </div>

                    <button class="modal-btn-primary" type="submit" name="register">
                        Create Account
                    </button>

                    <p class="modal-switch">
                        Already have an account?
                        <a href="#" onclick="switchTab('login')">Log In</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <section class="hero" id="hero">
        <div class="hero-text">
            <div class="hero-eyebrow">
                <span class="eyebrow-line"></span>
                Welcome to Boracay Accommodation
            </div>
            <h1>Experience the<br>Warmth of<br><em>Filipino Hospitality</em></h1>
            <p class="hero-desc">
                Located on the vibrant shores of Boracay, Boracay Accommodation offers stylish apartment accommodations
                where modern comfort meets Filipino hospitality. From sunlit balconies with ocean views to thoughtfully
                furnished interiors, every space is designed for relaxation and a true sense of <em
                    style="font-style:normal;color:var(--terra)">home</em>.
            </p>
            <div class="hero-stats">
                <div>
                    <div class="hero-stat-num"><?php echo $statRooms; ?>+</div>
                    <div class="hero-stat-lbl">Unique Rooms</div>
                </div>
                <div>
                    <div class="hero-stat-num"><?php echo $statRatingDisplay; ?></div>
                    <div class="hero-stat-lbl"><?php echo $statRating ? 'Guest Rating' : 'No Reviews Yet'; ?></div>
                </div>
                <div>
                    <div class="hero-stat-num"><?php echo $statYears; ?>yr</div>
                    <div class="hero-stat-lbl">of Hospitality</div>
                </div>
            </div>
        </div>

        <div class="hero-carousel">
            <div class="carousel-frame" id="carouselFrame">
                <div class="carousel-slides" id="carouselSlides">

                    <?php if (empty($heroUnits)): ?>
                        <div class="carousel-slide active room-1" data-label="Featured Room" data-type="No units available yet">
                            <div style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;background:linear-gradient(145deg,#dbeafe,#93c5fd,#1a3d7c);flex-direction:column;gap:12px;">
                                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.7)" stroke-width="1.2">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <path d="M21 15l-5-5L5 21"/>
                                </svg>
                                <span style="color:rgba(255,255,255,0.6);font-size:13px;letter-spacing:0.05em;">No rooms listed yet</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($heroUnits as $idx => $unit):
                            $heroName = landing_unit_name($unit);
                            $heroType = $unit['unit_type'] ?: 'Room';
                            $heroImg  = hero_img_path($unit['image_path']);
                            if ($heroImg && !file_exists(__DIR__ . '/' . $heroImg)) $heroImg = '';
                        ?>
                            <div class="carousel-slide <?php echo $idx === 0 ? 'active' : ''; ?> room-<?php echo ($idx % 5) + 1; ?>"
                                data-label="<?php echo htmlspecialchars($heroName); ?>"
                                data-type="<?php echo htmlspecialchars($heroType); ?>">
                                <?php if ($heroImg): ?>
                                    <img src="<?php echo htmlspecialchars($heroImg); ?>"
                                        alt="<?php echo htmlspecialchars($heroName); ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <?php endif; ?>
                                <div style="display:<?php echo $heroImg ? 'none' : 'flex'; ?>;width:100%;height:100%;align-items:center;justify-content:center;background:linear-gradient(145deg,#dbeafe,#3b82f6,#1a3d7c);color:#fff;position:absolute;top:0;left:0;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" opacity="0.6">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="M21 15l-5-5L5 21"/>
                                    </svg>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

                <div class="carousel-overlay"></div>
                <div class="carousel-label">
                    <div class="carousel-label-name" id="slideLabel">
                        <?php echo !empty($heroUnits) ? htmlspecialchars(landing_unit_name($heroUnits[0])) : 'No Rooms Yet'; ?>
                    </div>
                    <div class="carousel-label-type" id="slideType">
                        <?php echo !empty($heroUnits) ? htmlspecialchars($heroUnits[0]['unit_type'] ?: 'Room') : 'Check back soon'; ?>
                    </div>
                </div>

                <div class="carousel-controls">
                    <button class="carousel-btn" id="prevBtn" aria-label="Previous">
                        <svg viewBox="0 0 24 24">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <button class="carousel-btn" id="nextBtn" aria-label="Next">
                        <svg viewBox="0 0 24 24">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="carousel-dots" id="carouselDots">
                <?php foreach ($heroUnits as $idx => $_hu): ?>
                    <div class="dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-idx="<?php echo $idx; ?>"></div>
                <?php endforeach; ?>
            </div>

            <div class="carousel-thumbs" id="carouselThumbs">
                <?php foreach ($heroUnits as $idx => $unit):
                    $heroName = landing_unit_name($unit);
                    $heroImg = hero_img_path($unit['image_path']);
                    if ($heroImg && !file_exists(__DIR__ . '/' . $heroImg)) $heroImg = '';
                    ?>
                    <div class="thumb <?php echo $idx === 0 ? 'active' : ''; ?>" data-idx="<?php echo $idx; ?>">
                        <?php if ($heroImg): ?>
                           <img src="<?php echo htmlspecialchars($heroImg); ?>"
    alt="<?php echo htmlspecialchars($heroName); ?>"
    onerror="console.log('Failed to load:', this.src); this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <?php endif; ?>
                        <div style="display:<?php echo $heroImg ? 'none' : 'flex' ?>;position:absolute;inset:0;align-items:center;justify-content:center;background:linear-gradient(145deg,#dbeafe,#3b82f6);color:#fff;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                            </svg>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about" id="about">
        <div class="about-inner">

            <div class="about-visual reveal">
                <div class="about-img-main">
                    <img src="assets/images/owner.jpg" alt="Owner of Boracay Accommodation">
                </div>
                <div class="about-img-accent">
                    <img src="assets/images/hero-img.jpg" alt="Image of Boracay Accommodation">
                </div>
                <div class="about-badge">
                    <div class="badge-num"><?php echo $statYears; ?>+</div>
                    <div class="badge-lbl">Years of<br>Hospitality</div>
                </div>
            </div>

            <div class="about-text reveal reveal-delay-1">
                <div class="eyebrow">Our Story</div>
                <h2 class="section-heading">A <em>Home</em> Away<br>From Home</h2>
                <p class="body-text">
                    Since 2012, the Magdaong family has welcomed guests with one simple belief: every visitor deserves
                    the warmth and care of a Filipino household. What began as a small collection of well-designed
                    apartments has grown into a cherished retreat, loved by travelers from all over the world.
                </p>
                <p class="body-text">
                    Each apartment is thoughtfully designed with a mix of contemporary finishes and local touches — from
                    capiz-inspired accents to hand-woven textiles — ensuring comfort without losing character. Our staff
                    treats every guest like family, because at Boracay Accommodation, <em
                        style="font-style:normal;color:var(--terra)">you are truly at home</em>.
                </p>
                <div class="about-pillars">
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">Filipino Warmth</div>
                            <div class="pillar-desc">Genuine malasakit in every interaction</div>
                        </div>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">Modern Comfort</div>
                            <div class="pillar-desc">Stylish apartments with Filipino touches.</div>
                        </div>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">24/7 Care</div>
                            <div class="pillar-desc">Always here whenever you need us</div>
                        </div>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <path d="M12 22s-8-4.5-8-11.8A8 8 0 0112 2a8 8 0 018 8.2c0 7.3-8 11.8-8 11.8z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">Prime Location</div>
                            <div class="pillar-desc">Steps from Boracay's beach and attractions</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <section class="lp-avail-section" id="rooms">
        <style>
        /* ══ Availability Section ══════════════════════════════════════════ */
        .lp-avail-section {
            background: var(--blue-50, #f0f7ff);
            padding: 64px 5vw;
            position: relative;
            overflow: hidden;
        }
        .lp-avail-inner {
            max-width: 1160px;
            margin: 0 auto;
        }
        /* Header */
        .lp-avail-hdr { text-align: center; margin-bottom: 40px; }
        .lp-eyebrow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--gold, #c9aa71);
            margin-bottom: 14px;
        }
        .lp-eyebrow span { display: block; width: 26px; height: 1.5px; background: var(--gold, #c9aa71); }
        .lp-avail-hdr h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 600;
            color: var(--text-dark, #0b1829);
            line-height: 1.15;
            margin: 0 0 10px;
        }
        .lp-avail-hdr h2 em { font-style: italic; color: var(--gold, #c9aa71); }
        .lp-avail-hdr p { color: var(--text-soft, #64748b); font-size: .9rem; max-width: 440px; margin: 0 auto; line-height: 1.65; }
        /* Picker card */
        .lp-picker-card {
            background: var(--white, #fff);
            border: 1.5px solid var(--border-light, #e2e8f0);
            border-radius: 20px;
            box-shadow: 0 2px 16px rgba(11,24,41,.06);
            padding: 24px 28px;
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .lp-picker-field-wrap { flex: 1; min-width: 140px; }
        .lp-picker-field-wrap.narrow { flex: 0 0 auto; min-width: 0; }
        /* Guests stepper */
        .lp-guests-field {
            display: flex;
            align-items: center;
            gap: 0;
            background: var(--blue-50, #f0f7ff);
            border: 1.5px solid var(--border-light, #e2e8f0);
            border-radius: 12px;
            overflow: hidden;
            transition: border-color .18s;
        }
        .lp-guests-field:focus-within { border-color: var(--gold, #c9aa71); }
        .lp-guests-btn {
            width: 34px; height: 42px;
            border: none; background: transparent;
            font-size: 1rem; font-weight: 700;
            color: var(--navy-700, #1a3a5c);
            cursor: pointer; flex-shrink: 0;
            transition: background .15s;
            display: flex; align-items: center; justify-content: center;
        }
        .lp-guests-btn:hover { background: rgba(11,24,41,.06); }
        .lp-guests-num {
            flex: 1; text-align: center;
            font-size: .9rem; font-weight: 700;
            color: var(--text-dark, #0b1829);
            min-width: 28px;
            pointer-events: none;
        }
        /* Unit type select */
        .lp-type-field {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--blue-50, #f0f7ff);
            border: 1.5px solid var(--border-light, #e2e8f0);
            border-radius: 12px;
            padding: 0 14px;
            transition: border-color .18s;
            height: 42px;
        }
        .lp-type-field:focus-within { border-color: var(--gold, #c9aa71); }
        .lp-type-field > svg { width: 14px; height: 14px; stroke: var(--navy-500, #2563a8); fill: none; flex-shrink: 0; }
        .lp-type-select {
            border: none; background: transparent; outline: none;
            font-size: .87rem; font-weight: 600;
            color: var(--text-dark, #0b1829);
            font-family: inherit; cursor: pointer;
            width: 100%; appearance: none;
        }
        .lp-picker-label {
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-soft, #64748b);
            margin-bottom: 8px;
        }
        .lp-date-field {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--blue-50, #f0f7ff);
            border: 1.5px solid var(--border-light, #e2e8f0);
            border-radius: 12px;
            padding: 11px 14px;
            cursor: pointer;
            transition: border-color .18s, background .18s;
            position: relative;
        }
        .lp-date-field:hover, .lp-date-field:focus-within {
            border-color: var(--gold, #c9aa71);
            background: var(--white, #fff);
        }
        .lp-date-field > svg { width: 15px; height: 15px; stroke: var(--navy-500, #2563a8); flex-shrink: 0; fill: none; }
        .lp-date-field input[type="date"] {
            border: none;
            background: transparent;
            outline: none;
            font-size: .88rem;
            font-weight: 600;
            color: var(--text-dark, #0b1829);
            font-family: inherit;
            cursor: pointer;
            width: 100%;
        }
        .lp-date-field input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            position: absolute;
            inset: 0;
            width: 100%;
            cursor: pointer;
        }
        .lp-picker-actions { flex-shrink: 0; display: flex; gap: 10px; align-items: center; }
        .btn-lp-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 99px;
            background: var(--navy-800, #112240);
            color: var(--gold, #c9aa71);
            border: none;
            font-size: .83rem;
            font-weight: 800;
            letter-spacing: .04em;
            cursor: pointer;
            font-family: inherit;
            transition: opacity .18s, transform .18s;
            white-space: nowrap;
        }
        .btn-lp-check:hover  { opacity: .85; transform: translateY(-1px); }
        .btn-lp-check > svg  { width: 14px; height: 14px; stroke: var(--gold, #c9aa71); fill: none; }
        .lp-check-icon       { display: block; }
        .lp-spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(201,170,113,.3);
            border-top-color: var(--gold, #c9aa71);
            border-radius: 50%;
            animation: lpSpin .6s linear infinite;
            display: none;
        }
        .btn-lp-check.loading .lp-check-icon { display: none; }
        .btn-lp-check.loading .lp-spinner    { display: block; }
        @keyframes lpSpin { to { transform: rotate(360deg); } }
        .btn-lp-clear {
            padding: 11px 16px;
            border-radius: 99px;
            border: 1.5px solid var(--border-light, #e2e8f0);
            background: transparent;
            font-size: .79rem;
            font-weight: 600;
            color: var(--text-soft, #64748b);
            cursor: pointer;
            font-family: inherit;
            transition: border-color .18s, color .18s;
            display: none;
        }
        .btn-lp-clear:hover   { border-color: var(--navy-400, #3b82d4); color: var(--navy-700, #1a3a5c); }
        .btn-lp-clear.visible { display: block; }
        /* Results */
        .lp-avail-results { min-height: 60px; }
        .lp-prompt-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-soft, #64748b);
            border: 1.5px dashed var(--border-light, #e2e8f0);
            border-radius: 16px;
            background: var(--white, #fff);
        }
        .lp-prompt-state svg { width: 36px; height: 36px; display: block; margin: 0 auto 12px; fill: none; stroke: currentColor; opacity: .4; }
        .lp-prompt-state p   { font-size: .86rem; margin: 0; }
        .lp-results-hdr {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .lp-results-hdr h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text-dark, #0b1829);
            margin: 0;
        }
        .lp-avail-badge {
            background: var(--navy-800, #112240);
            color: var(--gold, #c9aa71);
            font-size: .7rem;
            font-weight: 800;
            padding: 3px 11px;
            border-radius: 99px;
        }
        .lp-range-note { font-size: .75rem; color: var(--text-soft, #64748b); margin-left: auto; }
        .lp-empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-soft, #64748b);
            border: 1.5px dashed var(--border-light, #e2e8f0);
            border-radius: 16px;
            background: var(--white, #fff);
        }
        .lp-empty-state svg { width: 36px; height: 36px; display: block; margin: 0 auto 12px; fill: none; stroke: currentColor; opacity: .3; }
        .lp-empty-state p   { font-size: .86rem; margin: 0; }
        /* Unit cards */
        .lp-units-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .lp-unit-card {
            background: var(--white, #fff);
            border: 1px solid var(--border-light, #e2e8f0);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(11,24,41,.05);
            transition: transform .2s, box-shadow .2s, border-color .2s;
            cursor: pointer;
        }
        .lp-unit-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(11,24,41,.1); border-color: var(--navy-200, #bfdbf7); }
        .lp-card-img { position: relative; height: 132px; overflow: hidden; background: linear-gradient(135deg,#1e2d40,#2c3e50); }
        .lp-card-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
        .lp-unit-card:hover .lp-card-img img { transform: scale(1.04); }
        .lp-card-type { position: absolute; top: 9px; left: 9px; background: rgba(11,24,41,.72); color: #fff; font-size: .6rem; font-weight: 800; letter-spacing: .08em; padding: 3px 9px; border-radius: 99px; }
        .lp-until-pill { position: absolute; bottom: 8px; left: 8px; right: 8px; background: rgba(11,24,41,.65); color: rgba(255,255,255,.9); font-size: .66rem; font-weight: 600; padding: 4px 10px; border-radius: 7px; display: flex; align-items: center; gap: 4px; }
        .lp-until-pill > svg { width: 10px; height: 10px; fill: none; stroke: currentColor; flex-shrink: 0; }
        .lp-until-pill span { margin-left: auto; font-weight: 800; color: #4ade80; }
        .lp-card-body  { padding: 12px 13px 14px; }
        .lp-card-name  { font-weight: 700; font-size: .84rem; color: var(--text-dark, #0b1829); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lp-card-loc   { font-size: .68rem; color: var(--text-soft, #64748b); display: flex; align-items: center; gap: 3px; margin-bottom: 10px; }
        .lp-card-loc svg { width: 10px; height: 10px; fill: none; stroke: var(--text-soft, #64748b); flex-shrink: 0; }
        .lp-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .lp-card-price  { font-weight: 800; font-size: .88rem; color: var(--text-dark, #0b1829); }
        .lp-card-price sub { font-size: .6em; font-weight: 400; color: var(--text-soft, #64748b); }
        .btn-lp-view { padding: 6px 13px; border-radius: 8px; background: var(--navy-800, #112240); color: var(--gold, #c9aa71); border: none; font-size: .71rem; font-weight: 800; cursor: pointer; font-family: inherit; transition: opacity .15s; }
        .btn-lp-view:hover { opacity: .85; }
        /* CTA nudge */
        .lp-avail-cta  { text-align: center; margin-top: 40px; }
        .lp-avail-cta p { color: var(--text-soft, #64748b); font-size: .85rem; margin: 0 0 12px; }
        .lp-avail-cta a { display: inline-block; padding: 11px 28px; border-radius: 99px; background: var(--navy-800, #112240); color: var(--gold, #c9aa71); font-size: .82rem; font-weight: 700; text-decoration: none; transition: opacity .18s; letter-spacing: .04em; }
        .lp-avail-cta a:hover { opacity: .85; }
        </style>

        <div class="lp-avail-inner">

            <!-- Header -->
            <div class="lp-avail-hdr reveal">
                <div class="lp-eyebrow"><span></span>Availability<span></span></div>
                <h2>Check <em>Unit</em> Availability</h2>
                <p>Pick a date — or a date range — to instantly see which units are open and for how long.</p>
            </div>

            <!-- Date Picker -->
            <div class="lp-picker-card reveal">

                <!-- Check-in -->
                <div class="lp-picker-field-wrap">
                    <div class="lp-picker-label">Check-in</div>
                    <div class="lp-date-field" onclick="document.getElementById('lpDateIn').showPicker?.()">
                        <svg viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" id="lpDateIn" onchange="lpOnDateChange()">
                    </div>
                </div>

                <!-- Check-out -->
                <div class="lp-picker-field-wrap">
                    <div class="lp-picker-label">Check-out <span style="font-weight:400;letter-spacing:0;text-transform:none;font-size:.68rem;">(optional)</span></div>
                    <div class="lp-date-field" onclick="document.getElementById('lpDateOut').showPicker?.()">
                        <svg viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" id="lpDateOut" onchange="lpOnDateChange()">
                    </div>
                </div>

                <!-- Guests -->
                <div class="lp-picker-field-wrap narrow" style="min-width:110px;">
                    <div class="lp-picker-label">Guests</div>
                    <div class="lp-guests-field">
                        <button class="lp-guests-btn" onclick="lpAdjGuests(-1)" type="button">−</button>
                        <span class="lp-guests-num" id="lpGuestsNum">2</span>
                        <button class="lp-guests-btn" onclick="lpAdjGuests(1)" type="button">+</button>
                    </div>
                </div>

                <!-- Unit Type -->
                <div class="lp-picker-field-wrap" style="min-width:150px;">
                    <div class="lp-picker-label">Unit Type</div>
                    <div class="lp-type-field">
                        <svg viewBox="0 0 24 24" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <select class="lp-type-select" id="lpUnitType">
                            <option value="">Any type</option>
                            <?php
                            $lpTypes = [];
                            foreach ($units as $u) {
                                $t = trim((string)($u['unit_type'] ?? ''));
                                if ($t && !in_array($t, $lpTypes)) $lpTypes[] = $t;
                            }
                            sort($lpTypes);
                            foreach ($lpTypes as $t):
                            ?>
                            <option value="<?php echo htmlspecialchars(strtolower($t)); ?>">
                                <?php echo htmlspecialchars(ucfirst($t)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Actions -->
                <div class="lp-picker-actions">
                    <button class="btn-lp-clear" id="lpClearBtn" onclick="lpClearAvail()">Clear</button>
                    <button class="btn-lp-check" id="lpCheckBtn" onclick="lpCheckAvail()">
                        <svg class="lp-check-icon" viewBox="0 0 24 24" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <div class="lp-spinner"></div>
                        Check Availability
                    </button>
                </div>
            </div>

            <!-- Results -->
            <div class="lp-avail-results" id="lpAvailResults">
                <div class="lp-prompt-state">
                    <svg viewBox="0 0 24 24" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <p>Select a check-in date to see available units.</p>
                </div>
            </div>

            <!-- Guest CTA -->
            <div class="lp-avail-cta">
                <p>Found the perfect unit? Create an account to book your stay.</p>
                <a href="pages/user/register.php">Get Started — It's Free</a>
            </div>
        </div>

        <script>
        (function () {
            const SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const today = new Date().toISOString().slice(0,10);
            document.getElementById('lpDateIn').min  = today;
            document.getElementById('lpDateOut').min = today;

            let _lpGuests = 2;

            window.lpAdjGuests = function (delta) {
                _lpGuests = Math.min(10, Math.max(1, _lpGuests + delta));
                document.getElementById('lpGuestsNum').textContent = _lpGuests;
            };

            window.lpOnDateChange = function () {
                const inVal = document.getElementById('lpDateIn').value;
                const outEl = document.getElementById('lpDateOut');
                if (inVal) {
                    const next = new Date(inVal + 'T00:00:00');
                    next.setDate(next.getDate() + 1);
                    outEl.min = next.toISOString().slice(0,10);
                    if (outEl.value && outEl.value <= inVal) outEl.value = '';
                }
                document.getElementById('lpClearBtn').classList.toggle('visible', !!inVal);
            };

            window.lpClearAvail = function () {
                document.getElementById('lpDateIn').value  = '';
                document.getElementById('lpDateOut').value = '';
                const typeEl = document.getElementById('lpUnitType');
                if (typeEl) typeEl.value = '';
                _lpGuests = 2;
                const gEl = document.getElementById('lpGuestsNum');
                if (gEl) gEl.textContent = 2;
                document.getElementById('lpClearBtn').classList.remove('visible');
                document.getElementById('lpAvailResults').innerHTML = promptHtml();
            };

            window.lpCheckAvail = function () {
                const dateIn  = document.getElementById('lpDateIn').value;
                const dateOut = document.getElementById('lpDateOut').value;
                if (!dateIn) { document.getElementById('lpDateIn').focus(); return; }
                const btn = document.getElementById('lpCheckBtn');
                btn.classList.add('loading'); btn.disabled = true;
                fetch('api/user/unit_availability.php?date=' + encodeURIComponent(dateIn))
                    .then(r => r.json())
                    .then(data => { btn.classList.remove('loading'); btn.disabled = false; renderResults(data.units || [], dateIn, dateOut); })
                    .catch(() => { btn.classList.remove('loading'); btn.disabled = false;
                        document.getElementById('lpAvailResults').innerHTML = '<div class="lp-empty-state"><p>Could not load availability. Please try again.</p></div>'; });
            };

            function promptHtml() {
                return '<div class="lp-prompt-state"><svg viewBox="0 0 24 24" stroke-width="1.5" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><p>Select a check-in date to see available units.</p></div>';
            }
            function fmt(iso) { if (!iso) return ''; const [y,m,d]=iso.split('-'); return SHORT[parseInt(m,10)-1]+' '+parseInt(d,10)+', '+y; }
            function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
            function num(n) { return Number(n).toLocaleString('en-PH',{maximumFractionDigits:0}); }

            function renderResults(units, dateIn, dateOut) {
                const panel = document.getElementById('lpAvailResults');
                if (!units.length) {
                    panel.innerHTML = '<div class="lp-results-hdr"><h3>Available on '+fmt(dateIn)+'</h3><span class="lp-avail-badge">0 units</span></div><div class="lp-empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg><p>No units available on this date. Try a different date.</p></div>';
                    return;
                }
                const selType = (document.getElementById('lpUnitType')?.value || '').toLowerCase();
                let filtered = units;
                if (selType) filtered = filtered.filter(u => (u.unit_type || '').toLowerCase() === selType);
                if (dateOut) filtered = filtered.filter(u => !u.available_until || u.available_until > dateOut);
                const rangeNote = dateOut ? '<span class="lp-range-note">'+fmt(dateIn)+' → '+fmt(dateOut)+'</span>' : '';
                if (!filtered.length && dateOut) {
                    panel.innerHTML = '<div class="lp-results-hdr"><h3>Available on '+fmt(dateIn)+'</h3><span class="lp-avail-badge">0 for full stay</span>'+rangeNote+'</div><div class="lp-empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg><p>No units free for the full stay ('+fmt(dateIn)+' → '+fmt(dateOut)+'). Try adjusting dates.</p></div>';
                    return;
                }
                let cards = '';
                filtered.forEach(u => {
                    const img = u.image_path ? u.image_path : '';
                    const imgTag = img ? `<img src="${esc(img)}" alt="${esc(u.name)}" onerror="this.style.display='none'">` : '';
                    const until = u.available_until ? 'Until '+fmt(u.available_until) : 'Indefinitely';
                    cards += '<div class="lp-unit-card" onclick="location.href=\'pages/user/register.php\'">'+
                        '<div class="lp-card-img">'+imgTag+
                        '<span class="lp-card-type">'+esc(u.unit_type.toUpperCase())+'</span>'+
                        '<div class="lp-until-pill"><svg viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Available<span>'+esc(until)+'</span></div>'+
                        '</div><div class="lp-card-body">'+
                        '<div class="lp-card-name">'+esc(u.name)+'</div>'+
                        '<div class="lp-card-loc"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>'+esc(u.property_name)+(u.city?', '+esc(u.city):'')+'</div>'+
                        '<div class="lp-card-footer"><div class="lp-card-price">₱'+num(u.rent_amount)+' <sub>/ night</sub></div>'+
                        '<button class="btn-lp-view" onclick="event.stopPropagation();location.href=\'pages/user/register.php\'">Book Now</button></div>'+
                        '</div></div>';
                });
                panel.innerHTML = '<div class="lp-results-hdr"><h3>Available on '+fmt(dateIn)+'</h3><span class="lp-avail-badge">'+filtered.length+' unit'+(filtered.length!==1?'s':'')+'</span>'+rangeNote+'</div><div class="lp-units-grid">'+cards+'</div>';
            }
        })();
        </script>
    </section>

    <section class="testimonials" id="reviews">
        <div class="testimonials-header reveal">
            <div class="eyebrow">Guest Reviews</div>
            <h2 class="section-heading" style="color:var(--white);">What Our Guests <em
                    style="color:var(--gold)">Say</em></h2>
        </div>

        <?php if (empty($landingReviews)): ?>
            <div class="testi-empty reveal" style="position:relative;z-index:1;max-width:1100px;margin:0 auto;">
                Guest reviews will appear here once our first visitors share their stay experience.
            </div>
        <?php else: ?>
            <div class="testi-grid">
                <?php foreach ($landingReviews as $ri => $rv):
                    $fullName = trim(($rv['first_name'] ?? '') . ' ' . ($rv['last_name'] ?? ''));
                    $initials = strtoupper(substr($rv['first_name'] ?? 'G', 0, 1) . substr($rv['last_name'] ?? 'U', 0, 1));
                    $rating = max(1, min(5, (int) ($rv['rating'] ?? 0)));
                    $avatarClass = 'av-' . (($ri % 6) + 1);
                    $reviewPhotoRaw = trim((string) ($rv['profile_photo'] ?? ''));
                    $reviewPhoto = $reviewPhotoRaw !== '' ? ltrim($reviewPhotoRaw, '/') : '';
                    ?>
                    <div class="testi-card reveal <?php echo $ri % 2 ? 'reveal-delay-1' : ''; ?>">
                        <span class="testi-quote-icon">"</span>
                        <div class="testi-stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <svg viewBox="0 0 24 24" style="<?php echo $s <= $rating ? '' : 'opacity:.25;'; ?>">
                                    <polygon
                                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <div class="testi-text"><?php echo htmlspecialchars($rv['comment'] ?? 'Great stay!'); ?></div>
                        <div class="testi-author">
                            <div class="testi-avatar <?php echo $reviewPhoto ? '' : $avatarClass; ?>" <?php echo $reviewPhoto ? 'style="background:none;"' : ''; ?>>
                                <?php if ($reviewPhoto): ?>
                                    <img src="<?php echo htmlspecialchars($reviewPhoto); ?>"
                                        alt="<?php echo htmlspecialchars($fullName ?: 'Guest'); ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>
                                <span class="testi-avatar-initials" <?php echo $reviewPhoto ? 'style="display:none;"' : ''; ?>>
                                    <?php echo htmlspecialchars($initials); ?>
                                </span>
                            </div>
                            <div>
                                <div class="testi-name"><?php echo htmlspecialchars($fullName ?: 'Guest'); ?></div>
                                <?php
                                $propertyLabel = trim($rv['property_name'] ?? '');
                                $unitLabel = trim($rv['room_name'] ?? '');
                                $locationLine = $propertyLabel !== '' && $unitLabel !== '' && $unitLabel !== $propertyLabel
                                    ? $propertyLabel . ' · ' . $unitLabel
                                    : ($propertyLabel ?: $unitLabel ?: 'Room');
                                ?>
                                <div class="testi-location"><?php echo htmlspecialchars($locationLine); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Mobile-only swiper (shown via CSS at <=768px) -->
            <div class="testi-swiper-wrap" id="testiSwiper">
                <div class="testi-swiper-track">
                    <div class="testi-swiper-inner" id="testiSwiperInner">
                        <?php foreach ($landingReviews as $ri => $rv):
                            $fullName = trim(($rv['first_name'] ?? '') . ' ' . ($rv['last_name'] ?? ''));
                            $initials = strtoupper(substr($rv['first_name'] ?? 'G', 0, 1) . substr($rv['last_name'] ?? 'U', 0, 1));
                            $rating = max(1, min(5, (int) ($rv['rating'] ?? 0)));
                            $avatarClass = 'av-' . (($ri % 6) + 1);
                            $reviewPhotoRaw = trim((string) ($rv['profile_photo'] ?? ''));
                            $reviewPhoto = $reviewPhotoRaw !== '' ? ltrim($reviewPhotoRaw, '/') : '';
                            $propertyLabel = trim($rv['property_name'] ?? '');
                            $unitLabel = trim($rv['room_name'] ?? '');
                            $locationLine = $propertyLabel !== '' && $unitLabel !== '' && $unitLabel !== $propertyLabel
                                ? $propertyLabel . ' · ' . $unitLabel
                                : ($propertyLabel ?: $unitLabel ?: 'Room');
                            ?>
                            <div class="testi-card">
                                <span class="testi-quote-icon">"</span>
                                <div class="testi-stars">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <svg viewBox="0 0 24 24" style="<?php echo $s <= $rating ? '' : 'opacity:.25;'; ?>">
                                            <polygon
                                                points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                                <div class="testi-text"><?php echo htmlspecialchars($rv['comment'] ?? 'Great stay!'); ?></div>
                                <div class="testi-author">
                                    <div class="testi-avatar <?php echo $reviewPhoto ? '' : $avatarClass; ?>" <?php echo $reviewPhoto ? 'style="background:none;"' : ''; ?>>
                                        <?php if ($reviewPhoto): ?>
                                            <img src="<?php echo htmlspecialchars($reviewPhoto); ?>"
                                                alt="<?php echo htmlspecialchars($fullName ?: 'Guest'); ?>"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <?php endif; ?>
                                        <span class="testi-avatar-initials" <?php echo $reviewPhoto ? 'style="display:none;"' : ''; ?>>
                                            <?php echo htmlspecialchars($initials); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="testi-name"><?php echo htmlspecialchars($fullName ?: 'Guest'); ?></div>
                                        <div class="testi-location"><?php echo htmlspecialchars($locationLine); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="testi-swiper-nav">
                    <button class="testi-swiper-btn" id="testiPrev" aria-label="Previous review">
                        <svg viewBox="0 0 24 24">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <div class="testi-swiper-dots" id="testiDots">
                        <?php foreach ($landingReviews as $ri => $_r): ?>
                            <div class="testi-swiper-dot <?php echo $ri === 0 ? 'active' : ''; ?>"
                                data-idx="<?php echo $ri; ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <button class="testi-swiper-btn" id="testiNext" aria-label="Next review">
                        <svg viewBox="0 0 24 24">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>

    </section>

    <section class="cta-section" id="cta">
        <div class="cta-pattern"></div>
        <div class="eyebrow cta-eyebrow reveal" style="justify-content:center;">
            <span style="width:24px;height:1.5px;background:var(--gold);display:block;"></span>
            &nbsp; Start Your Journey
        </div>
        <h2 class="cta-heading reveal">
            Where Every Stay<br>Feels Like <em>Coming Home</em>
        </h2>
        <p class="cta-sub reveal">
            From the gentle sea breeze at dawn to the glow of Boracay's sunsets, your most memorable Filipino experience
            awaits. Book your stay at Boracay Accommodation in Boracay today.
        </p>
        <button class="btn-book-big reveal">
            Book Your Stay Now
            <svg viewBox="0 0 24 24">
                <line x1="5" y1="12" x2="19" y2="12" />
                <polyline points="12 5 19 12 12 19" />
            </svg>
        </button>
        <p class="cta-note reveal">Free cancellation up to 48 hours before check-in · No hidden fees</p>
    </section>

    <footer id="contact">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo" style="margin-bottom:1rem;display:inline-flex;">
                    <img src="assets/images/logo.png" alt="Boracay Accommodation Logo" class="logo-icon-2">
                    Boracay Accommodation
                </a>
                <p>A modern apartment retreat on Boracay, blending authentic Filipino warmth with contemporary comfort.
                </p>
                <div class="social-row">
                    <a href="#" class="soc" aria-label="Facebook">
                        <svg fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                        </svg>
                    </a>
                    <a href="#" class="soc" aria-label="Instagram">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="2" y="2" width="20" height="20" rx="5" />
                            <circle cx="12" cy="12" r="4" />
                            <circle cx="17.5" cy="6.5" r=".5" fill="currentColor" />
                        </svg>
                    </a>
                    <a href="#" class="soc" aria-label="TripAdvisor">
                        <svg fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18a8 8 0 110-16 8 8 0 010 16zm-1-5a1 1 0 102 0 1 1 0 00-2 0zm-4-4a1 1 0 102 0 1 1 0 00-2 0zm8 0a1 1 0 102 0 1 1 0 00-2 0z" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="footer-col">
                <h5>Navigate</h5>
                <ul class="footer-links">
                    <li><a href="#hero">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#rooms">Rooms</a></li>
                    <li><a href="#reviews">Reviews</a></li>
                    <li><a href="#cta">Book Now</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h5>Contact Us</h5>
                <div class="contact-row">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                    <?php echo $contactAddress; ?>
                </div>
                <div class="contact-row">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" />
                    </svg>
                    <?php echo $contactPhone; ?>
                </div>
                <div class="contact-row">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    <?php echo $contactEmail; ?>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© 2025 Boracay Accommodation Investment Properties & Services. All rights reserved.</span>
            <span class="footer-policy-links">
                <a href="#" class="footer-policy-link policy-link" data-policy="privacy">Privacy Policy</a>
                <span>·</span>
                <a href="#" class="footer-policy-link policy-link" data-policy="terms">Terms</a>
                <span>·</span>
                <a href="#" class="footer-policy-link policy-link" data-policy="booking">Booking Policy</a>
            </span>
        </div>
    </footer>

    <script src="assets/js/toast.js"></script>

    <!-- ── Room Preview Modal ── -->
    <div class="room-preview-overlay" id="roomPreviewModal">
        <div class="room-preview-box">
            <button class="room-preview-close" id="roomPreviewClose">&times;</button>
            <div class="room-preview-media" id="roomPreviewMedia" style="display:none;">
                <img id="roomPreviewImage" src="" alt="Room image" style="display:none;"
                    onerror="this.style.display='none';document.getElementById('roomPreviewMedia').style.display='none';">
            </div>
            <div class="room-preview-body">
                <h3 id="roomPreviewName">Room</h3>
                <p id="roomPreviewType" class="room-preview-type"></p>
                <p id="roomPreviewLocation" class="room-preview-location"></p>
                <p id="roomPreviewDesc" class="room-preview-desc"></p>
                <div id="roomPreviewAmenities" class="room-preview-amenities"></div>
                <div class="room-preview-footer">
                    <div id="roomPreviewPrice" class="room-preview-price"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Forgot Password Modal ── -->
    <div class="modal-overlay" id="forgotModal">
        <div class="modal-box" style="max-width:420px;">
            <button class="modal-close" onclick="closeForgotModal()">&times;</button>
            <div id="forgotAlert"
                style="display:none;margin-bottom:16px;padding:12px 14px;border-radius:8px;font-size:13px;line-height:1.5;">
            </div>
            <div id="forgotFormWrap">
                <div class="modal-header">
                    <h2 class="modal-title" style="font-size:1.35rem;">Forgot Password?</h2>
                    <p class="modal-sub">Enter the email you signed up with and we'll send you a reset link.</p>
                </div>
                <div class="modal-field" style="margin-top:20px;">
                    <label>Email Address</label>
                    <input type="email" id="forgotEmail" placeholder="your@email.com" autocomplete="email">
                </div>
                <button class="modal-btn-primary" id="forgotSubmitBtn" onclick="submitForgotPassword()"
                    style="width:100%;margin-top:4px;">
                    Send Reset Link
                </button>
                <p style="text-align:center;margin-top:14px;font-size:13px;color:#6b7280;">
                    Remembered it? <a href="#" onclick="closeForgotModal();openModal('login');return false;"
                        style="color:#1e3a5f;font-weight:600;text-decoration: none;">Back to Login</a>
                </p>
            </div>
        </div>
    </div>

    <!-- ── Policy Modal ── -->
    <div class="policy-modal-overlay" id="policyModal">
        <div class="policy-modal-box">
            <button class="policy-modal-close" id="policyModalClose" aria-label="Close policy modal">&times;</button>
            <h3 class="policy-modal-title" id="policyModalTitle">Policy</h3>
            <div class="policy-modal-meta">Last updated: April 2026</div>
            <div class="policy-modal-content" id="policyModalContent"></div>
        </div>
    </div>

    <script src="assets/js/index.js"></script>

    <?php
    if (isset($_SESSION['alert'])) {
        $type = $_SESSION['alert']['type'];
        $message = $_SESSION['alert']['message'];
        echo "";
        unset($_SESSION['alert']);
    }
    ?>

</body>

</html>